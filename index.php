<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Arama.class.php';

/* ── GET parametreleri ───────────────────────────────────── */
$siralama_gecerli = ['yeni', 'ucuz', 'pahali'];
$siralama = $_GET['siralama'] ?? 'yeni';
if (!in_array($siralama, $siralama_gecerli)) $siralama = 'yeni';

$sayfa = isset($_GET['sayfa']) ? (int) $_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;

/* ── Filtre parametreleri ────────────────────────────────── */
$filtreler = [];
if (!empty($_GET['bolum_id']))
    $filtreler['bolum_id'] = (int) $_GET['bolum_id'];
if (!empty($_GET['durum']) && in_array($_GET['durum'], ['yeni', 'iyi', 'orta']))
    $filtreler['durum'] = $_GET['durum'];
if (isset($_GET['min_fiyat']) && $_GET['min_fiyat'] !== '')
    $filtreler['min_fiyat'] = (float) $_GET['min_fiyat'];
if (isset($_GET['max_fiyat']) && $_GET['max_fiyat'] !== '')
    $filtreler['max_fiyat'] = (float) $_GET['max_fiyat'];

/* Bu haftanın ilanları için max_fiyat ile çakışmasın diye ayrı flag */
$hafta_filtresi = !empty($_GET['hafta']);

/* ── Veri çekme ──────────────────────────────────────────── */
$conn   = db_baglan();
$arama  = new Arama($conn);

if (!empty($filtreler)) {
    $ilanlar = $arama->filtreUygula($filtreler, $sayfa);
    $toplam  = $arama->aramaSonucSayisi('', $filtreler);
} else {
    $ilanlar = $arama->listele($sayfa, $siralama);
    $toplam  = $arama->toplamIlanSayisi();
}

$bolumler     = $arama->tumBolumler();
$toplam_sayfa = (int) ceil($toplam / SAYFA_BASINA_ILAN);
if ($sayfa > $toplam_sayfa && $toplam_sayfa > 0) $sayfa = $toplam_sayfa;

/* ── Mevcut GET'i koruyarak URL üret ────────────────────── */
function url_degistir(array $degisiklikler = [], array $kaldir = []): string {
    $params = $_GET;
    foreach ($kaldir as $k) unset($params[$k]);
    foreach ($degisiklikler as $k => $v) $params[$k] = $v;
    unset($params['sayfa']); // siralama/filtre değişince 1. sayfaya dön
    $qs = http_build_query($params);
    return 'index.php' . ($qs ? '?' . $qs : '');
}

function sayfa_url(int $s): string {
    $params = $_GET;
    $params['sayfa'] = $s;
    return 'index.php?' . http_build_query($params);
}

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa  = 'anasayfa';
$sayfa_basligi = 'Ana Sayfa';
require_once __DIR__ . '/header.php';
?>

<!-- ═══════════════════════════════════════════════════════
     HERO — arama kutusu + hızlı filtre pilleri
═══════════════════════════════════════════════════════ -->
<section class="sayfa-hero" style="text-align:center;">
    <div class="container">
        <h1>İkinci el ders kitabı ara</h1>
        <p>Üniversite öğrencilerinden, üniversite öğrencilerine.</p>

        <form class="arama-kutusu" action="ara.php" method="GET">
            <input
                type="text"
                name="q"
                placeholder="Kitap adı, yazar veya bölüm ara..."
                aria-label="Arama">
            <button type="submit">Ara</button>
        </form>

        <div class="hizli-filtreler">
            <a href="index.php"
               class="bolum-chip <?= empty($_GET) ? 'aktif' : '' ?>">
                Tüm İlanlar
            </a>
            <a href="index.php?hafta=1"
               class="bolum-chip <?= $hafta_filtresi ? 'aktif' : '' ?>">
                Bu Hafta Eklendi
            </a>
            <a href="index.php?max_fiyat=100"
               class="bolum-chip <?= (!$hafta_filtresi && isset($filtreler['max_fiyat']) && $filtreler['max_fiyat'] == 100 && count($filtreler) === 1) ? 'aktif' : '' ?>">
                100₺ Altı
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════
     FİLTRE BARI
═══════════════════════════════════════════════════════ -->
<div class="filtre-bar">
    <div class="container">
        <form class="filtre-bar-icerik" method="GET" action="index.php">

            <!-- Bölüm seçimi -->
            <select name="bolum_id" class="form-select" aria-label="Bölüm">
                <option value="">Tüm Bölümler</option>
                <?php foreach ($bolumler as $b): ?>
                    <option value="<?= $b['id'] ?>"
                        <?= (isset($filtreler['bolum_id']) && $filtreler['bolum_id'] == $b['id']) ? 'selected' : '' ?>>
                        <?= temizle($b['ad']) ?>
                        (<?= (int) $b['ilan_sayisi'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <!-- Durum -->
            <select name="durum" class="form-select" aria-label="Durum">
                <option value="">Tüm Durumlar</option>
                <option value="yeni"  <?= (($filtreler['durum'] ?? '') === 'yeni')  ? 'selected' : '' ?>>Yeni</option>
                <option value="iyi"   <?= (($filtreler['durum'] ?? '') === 'iyi')   ? 'selected' : '' ?>>İyi</option>
                <option value="orta"  <?= (($filtreler['durum'] ?? '') === 'orta')  ? 'selected' : '' ?>>Orta</option>
            </select>

            <!-- Fiyat aralığı -->
            <div class="filtre-grup">
                <input
                    type="number"
                    name="min_fiyat"
                    class="form-input"
                    placeholder="Min ₺"
                    min="0"
                    value="<?= temizle((string) ($_GET['min_fiyat'] ?? '')) ?>"
                    style="width: 90px;"
                    aria-label="Minimum fiyat">
                <span class="filtre-ayrac">—</span>
                <input
                    type="number"
                    name="max_fiyat"
                    class="form-input"
                    placeholder="Max ₺"
                    min="0"
                    value="<?= temizle((string) ($_GET['max_fiyat'] ?? '')) ?>"
                    style="width: 90px;"
                    aria-label="Maksimum fiyat">
            </div>

            <!-- Sıralama -->
            <select name="siralama" class="form-select" aria-label="Sıralama">
                <option value="yeni"   <?= $siralama === 'yeni'   ? 'selected' : '' ?>>En Yeni</option>
                <option value="ucuz"   <?= $siralama === 'ucuz'   ? 'selected' : '' ?>>En Ucuz</option>
                <option value="pahali" <?= $siralama === 'pahali' ? 'selected' : '' ?>>En Pahalı</option>
            </select>

            <button type="submit" class="btn-primary">Filtrele</button>

            <?php if (!empty($filtreler) || $hafta_filtresi): ?>
                <a href="index.php" class="btn-secondary">Sıfırla</a>
            <?php endif; ?>

        </form>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     İLAN LİSTESİ
═══════════════════════════════════════════════════════ -->
<main class="icerik-alani">
    <div class="container">

        <!-- Sonuç bilgisi -->
        <p class="sonuc-bilgi">
            <?php if ($toplam > 0): ?>
                <?= $toplam ?> ilan bulundu
            <?php else: ?>
                Hiç ilan bulunamadı.
            <?php endif; ?>
        </p>

        <?php if (empty($ilanlar)): ?>
            <!-- Boş durum -->
            <div class="bos-durum">
                <div class="bos-durum-ikon">📚</div>
                <h3>İlan bulunamadı</h3>
                <p>Filtreleri değiştirmeyi veya arama yaparak aramayı deneyebilirsin.</p>
                <a href="index.php" class="btn-primary">Tüm İlanları Gör</a>
            </div>

        <?php else: ?>
            <!-- Kart grid -->
            <div class="grid-3">
                <?php foreach ($ilanlar as $ilan): ?>
                    <article class="kitap-karti">

                        <a href="ilan.php?id=<?= (int) $ilan['id'] ?>" class="kitap-karti-resim">
                            <div class="kitap-karti-resim-icerik">📖</div>
                            <span class="kitap-karti-badge">
                                <?= durum_badge($ilan['durum']) ?>
                            </span>
                        </a>

                        <div class="kitap-karti-body">
                            <h3 class="kitap-karti-baslik">
                                <a href="ilan.php?id=<?= (int) $ilan['id'] ?>">
                                    <?= temizle($ilan['kitap_adi']) ?>
                                </a>
                            </h3>
                            <?php if (!empty($ilan['yazar'])): ?>
                                <p class="kitap-karti-yazar"><?= temizle($ilan['yazar']) ?></p>
                            <?php endif; ?>
                            <p class="kitap-karti-fiyat"><?= para_formatla((float) $ilan['fiyat']) ?></p>
                        </div>

                        <div class="kitap-karti-footer">
                            <div>
                                <?php if (!empty($ilan['bolum_adi'])): ?>
                                    <a href="bolum.php?id=<?= (int) $ilan['bolum_id'] ?>"
                                       class="bolum-tagi">
                                        <?= temizle($ilan['bolum_adi']) ?>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <a href="ilan.php?id=<?= (int) $ilan['id'] ?>"
                               class="kitap-karti-detay">Detay →</a>
                        </div>

                    </article>
                <?php endforeach; ?>
            </div>

            <!-- Sayfalama -->
            <?php if ($toplam_sayfa > 1): ?>
                <nav class="sayfalama" aria-label="Sayfalama">

                    <?php if ($sayfa > 1): ?>
                        <a href="<?= sayfa_url($sayfa - 1) ?>" aria-label="Önceki sayfa">‹</a>
                    <?php else: ?>
                        <span class="devre-disi">‹</span>
                    <?php endif; ?>

                    <?php
                    $baslangic = max(1, $sayfa - 2);
                    $bitis     = min($toplam_sayfa, $sayfa + 2);
                    if ($baslangic > 1): ?>
                        <a href="<?= sayfa_url(1) ?>">1</a>
                        <?php if ($baslangic > 2): ?>
                            <span class="devre-disi">…</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $baslangic; $i <= $bitis; $i++): ?>
                        <?php if ($i === $sayfa): ?>
                            <span class="aktif"><?= $i ?></span>
                        <?php else: ?>
                            <a href="<?= sayfa_url($i) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($bitis < $toplam_sayfa): ?>
                        <?php if ($bitis < $toplam_sayfa - 1): ?>
                            <span class="devre-disi">…</span>
                        <?php endif; ?>
                        <a href="<?= sayfa_url($toplam_sayfa) ?>"><?= $toplam_sayfa ?></a>
                    <?php endif; ?>

                    <?php if ($sayfa < $toplam_sayfa): ?>
                        <a href="<?= sayfa_url($sayfa + 1) ?>" aria-label="Sonraki sayfa">›</a>
                    <?php else: ?>
                        <span class="devre-disi">›</span>
                    <?php endif; ?>

                </nav>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
