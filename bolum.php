<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Arama.class.php';

$conn  = db_baglan();
$arama = new Arama($conn);

/* ── Yardımcı: hata sayfası göster ve çık ───────────────── */
function bolum_hata(string $mesaj): never {
    global $aktif_sayfa, $sayfa_basligi;
    $aktif_sayfa   = 'bolumler';
    $sayfa_basligi = 'Hata';
    require_once __DIR__ . '/header.php';
    echo '<div class="icerik-alani"><div class="container">';
    echo '<div class="alert alert-danger">' . temizle($mesaj) . '</div>';
    echo '<a href="index.php" class="btn-primary" style="margin-top:12px;">Ana Sayfaya Dön</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── 1. bolum_id doğrulama ───────────────────────────────── */
if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id']) || (int) $_GET['id'] < 1) {
    bolum_hata('Geçersiz bölüm.');
}
$bolum_id = (int) $_GET['id'];

/* ── 2. Bölümü DB'den çek ────────────────────────────────── */
$bolum = $arama->bolumBul($bolum_id);
if (!$bolum) {
    bolum_hata('Böyle bir bölüm bulunamadı.');
}

/* ── 3. Diğer parametreler ───────────────────────────────── */
$siralama = $_GET['siralama'] ?? 'yeni';
if (!in_array($siralama, ['yeni', 'ucuz', 'pahali'], true)) $siralama = 'yeni';

$sayfa = isset($_GET['sayfa']) ? max(1, (int) $_GET['sayfa']) : 1;

$ilanlar      = $arama->bolumIlanlari($bolum_id, $sayfa, $siralama);
$toplam       = $arama->bolumIlanSayisi($bolum_id);
$istatistik   = $arama->bolumIstatistik($bolum_id);
$tum_bolumler = $arama->tumBolumler();
$toplam_sayfa = $toplam > 0 ? (int) ceil($toplam / SAYFA_BASINA_ILAN) : 0;

/* ── Sayfalama URL yardımcısı ────────────────────────────── */
function bolum_sayfa_url(int $s): string {
    $p = ['id' => (int) $_GET['id'], 'siralama' => $_GET['siralama'] ?? 'yeni', 'sayfa' => $s];
    return 'bolum.php?' . http_build_query($p);
}

/* ── Tarih formatlama ────────────────────────────────────── */
function tarih_formatla(?string $tarih): string {
    if (!$tarih) return '—';
    $ts = strtotime($tarih);
    $fark = time() - $ts;
    if ($fark < 3600)   return (int)($fark / 60) . ' dakika önce';
    if ($fark < 86400)  return (int)($fark / 3600) . ' saat önce';
    if ($fark < 604800) return (int)($fark / 86400) . ' gün önce';
    return date('d.m.Y', $ts);
}

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa   = 'bolumler';
$sayfa_basligi = $bolum['ad'];
require_once __DIR__ . '/header.php';
?>

<!-- ═══════════════════════════════════════════════════════
     BÖLÜM HEADER
═══════════════════════════════════════════════════════ -->
<div class="bolum-hero">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a>
            <span class="breadcrumb-ayrac">›</span>
            <a href="bolum.php?id=<?= $bolum_id ?>">Bölümler</a>
            <span class="breadcrumb-ayrac">›</span>
            <span><?= temizle($bolum['ad']) ?></span>
        </nav>

        <div class="bolum-hero-icerik">
            <div>
                <h1 class="bolum-hero-baslik"><?= temizle($bolum['ad']) ?></h1>
                <?php if (!empty($bolum['fakulte'])): ?>
                    <p class="bolum-hero-fakulte"><?= temizle($bolum['fakulte']) ?></p>
                <?php endif; ?>

                <!-- İstatistik satırı -->
                <div class="bolum-istatistik-sati">
                    <span class="bolum-istat-item">
                        📦 <strong><?= (int) ($istatistik['toplam_ilan'] ?? 0) ?></strong> aktif ilan
                    </span>
                    <?php if (!empty($istatistik['ort_fiyat'])): ?>
                        <span class="bolum-istat-ayrac">·</span>
                        <span class="bolum-istat-item">
                            💰 Ortalama <strong><?= para_formatla((float) $istatistik['ort_fiyat']) ?></strong>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($istatistik['min_fiyat'])): ?>
                        <span class="bolum-istat-ayrac">·</span>
                        <span class="bolum-istat-item">
                            📉 En ucuz: <strong><?= para_formatla((float) $istatistik['min_fiyat']) ?></strong>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     BÖLÜM NAVİGASYONU — yatay pill sekmeler
═══════════════════════════════════════════════════════ -->
<div class="bolum-nav-wrapper">
    <div class="container">
        <div class="bolum-nav-scroll">
            <a href="bolum.php?id=<?= $bolum_id ?>"
               class="bolum-chip <?= !isset($_GET['id']) ? '' : 'aktif-devre' ?>">
                Tüm Bölümler
            </a>
            <?php foreach ($tum_bolumler as $b): ?>
                <a href="bolum.php?id=<?= (int) $b['id'] ?>"
                   class="bolum-chip <?= ($b['id'] == $bolum_id) ? 'aktif' : '' ?>">
                    <?= temizle($b['ad']) ?>
                    <span class="bolum-chip-sayi">(<?= (int) $b['ilan_sayisi'] ?>)</span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     FİLTRE + SIRALAMA BARI
═══════════════════════════════════════════════════════ -->
<div class="filtre-bar">
    <div class="container">
        <div class="filtre-bar-icerik">

            <span class="sonuc-bilgi" style="margin:0;">
                <?= $toplam ?> ilan bulundu
            </span>

            <form method="GET" action="bolum.php" style="display:flex; align-items:center; gap:8px; margin-left:auto;">
                <input type="hidden" name="id" value="<?= $bolum_id ?>">
                <label for="bolum-siralama" style="font-size:13px; color:var(--metin-soluk); white-space:nowrap;">
                    Sırala:
                </label>
                <select id="bolum-siralama" name="siralama" class="form-select"
                        style="width:auto;" onchange="this.form.submit()">
                    <option value="yeni"   <?= $siralama === 'yeni'   ? 'selected' : '' ?>>En Yeni</option>
                    <option value="ucuz"   <?= $siralama === 'ucuz'   ? 'selected' : '' ?>>En Ucuz</option>
                    <option value="pahali" <?= $siralama === 'pahali' ? 'selected' : '' ?>>En Pahalı</option>
                </select>
            </form>

        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     ANA ALAN + SAĞ BİLGİ KARTI
═══════════════════════════════════════════════════════ -->
<div class="icerik-alani">
    <div class="container bolum-layout">

        <!-- ── ANA ALAN ─────────────────────────────── -->
        <div class="bolum-ilanlar">

            <?php if (empty($ilanlar)): ?>
                <div class="bos-durum">
                    <div class="bos-durum-ikon">📚</div>
                    <h3>Bu bölümde henüz ilan yok</h3>
                    <p>Bu bölüme ait ikinci el kitabın varsa ilk ilanı sen ver!</p>
                    <a href="ilan-ekle.php?bolum_id=<?= $bolum_id ?>" class="btn-primary">
                        İlk İlanı Sen Ver
                    </a>
                </div>

            <?php else: ?>
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
                                <span style="font-size:12px; color:var(--metin-soluk);">
                                    <?= temizle(date('d.m.Y', strtotime($ilan['olusturma_tarihi']))) ?>
                                </span>
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
                            <a href="<?= bolum_sayfa_url($sayfa - 1) ?>" aria-label="Önceki">‹</a>
                        <?php else: ?>
                            <span class="devre-disi">‹</span>
                        <?php endif; ?>

                        <?php
                        $bas = max(1, $sayfa - 2);
                        $bit = min($toplam_sayfa, $sayfa + 2);
                        if ($bas > 1): ?>
                            <a href="<?= bolum_sayfa_url(1) ?>">1</a>
                            <?php if ($bas > 2): ?><span class="devre-disi">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $bas; $i <= $bit; $i++): ?>
                            <?php if ($i === $sayfa): ?>
                                <span class="aktif"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= bolum_sayfa_url($i) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($bit < $toplam_sayfa): ?>
                            <?php if ($bit < $toplam_sayfa - 1): ?>
                                <span class="devre-disi">…</span>
                            <?php endif; ?>
                            <a href="<?= bolum_sayfa_url($toplam_sayfa) ?>"><?= $toplam_sayfa ?></a>
                        <?php endif; ?>

                        <?php if ($sayfa < $toplam_sayfa): ?>
                            <a href="<?= bolum_sayfa_url($sayfa + 1) ?>" aria-label="Sonraki">›</a>
                        <?php else: ?>
                            <span class="devre-disi">›</span>
                        <?php endif; ?>

                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </div>

        <!-- ── SAĞ BİLGİ KARTI ──────────────────────── -->
        <aside class="bolum-bilgi-karti">
            <h3 class="bilgi-karti-baslik">Bu Bölüm Hakkında</h3>

            <ul class="bilgi-karti-liste">
                <li class="bilgi-karti-satir">
                    <span class="bilgi-karti-etiket">Toplam ilan</span>
                    <strong><?= (int) ($istatistik['toplam_ilan'] ?? 0) ?></strong>
                </li>
                <?php if (!empty($istatistik['ort_fiyat'])): ?>
                    <li class="bilgi-karti-satir">
                        <span class="bilgi-karti-etiket">Ortalama fiyat</span>
                        <strong><?= para_formatla((float) $istatistik['ort_fiyat']) ?></strong>
                    </li>
                <?php endif; ?>
                <?php if (!empty($istatistik['min_fiyat'])): ?>
                    <li class="bilgi-karti-satir">
                        <span class="bilgi-karti-etiket">En ucuz</span>
                        <strong><?= para_formatla((float) $istatistik['min_fiyat']) ?></strong>
                    </li>
                <?php endif; ?>
                <?php if (!empty($istatistik['en_son_ilan'])): ?>
                    <li class="bilgi-karti-satir">
                        <span class="bilgi-karti-etiket">Son ilan</span>
                        <strong><?= temizle(tarih_formatla($istatistik['en_son_ilan'])) ?></strong>
                    </li>
                <?php endif; ?>
            </ul>

            <a href="ara.php?bolum_id=<?= $bolum_id ?>"
               class="btn-secondary bilgi-karti-ara-link">
                Bu Bölümde Ara →
            </a>

            <!-- İlan ver CTA -->
            <div class="bilgi-karti-cta">
                <p>İlanın mı var?</p>
                <strong>Kitabını hemen sat,<br>başkasına yardımcı ol.</strong>
                <a href="ilan-ekle.php?bolum_id=<?= $bolum_id ?>" class="btn-primary" style="margin-top:10px; width:100%; justify-content:center;">
                    Ücretsiz İlan Ver
                </a>
            </div>

        </aside>

    </div><!-- .container -->
</div><!-- .icerik-alani -->

<?php require_once __DIR__ . '/footer.php'; ?>
