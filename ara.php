<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Arama.class.php';

/* ── GET parametreleri ───────────────────────────────────── */
$q = trim($_GET['q'] ?? '');

$sayfa = isset($_GET['sayfa']) ? (int) $_GET['sayfa'] : 1;
if ($sayfa < 1) $sayfa = 1;

$siralama_gecerli = ['yeni', 'ucuz', 'pahali'];
$siralama = $_GET['siralama'] ?? 'yeni';
if (!in_array($siralama, $siralama_gecerli, true)) $siralama = 'yeni';

/* ── Filtre parametreleri ────────────────────────────────── */
$filtreler = [];
if (!empty($_GET['bolum_id']))
    $filtreler['bolum_id'] = (int) $_GET['bolum_id'];
if (!empty($_GET['durum']) && in_array($_GET['durum'], ['yeni', 'iyi', 'orta'], true))
    $filtreler['durum'] = $_GET['durum'];
if (isset($_GET['min_fiyat']) && $_GET['min_fiyat'] !== '')
    $filtreler['min_fiyat'] = (float) $_GET['min_fiyat'];
if (isset($_GET['max_fiyat']) && $_GET['max_fiyat'] !== '')
    $filtreler['max_fiyat'] = (float) $_GET['max_fiyat'];

/* ── Doğrulama ───────────────────────────────────────────── */
$hata = '';
if (mb_strlen($q) > 200) {
    $hata = 'Arama terimi çok uzun (maks. 200 karakter).';
    $q    = '';
}

/* ── Veri çekme ──────────────────────────────────────────── */
$conn    = db_baglan();
$arama   = new Arama($conn);
$ilanlar = [];
$toplam  = 0;

if (!$hata) {
    $ilanlar = $arama->ara($q, $filtreler, $sayfa);
    $toplam  = $arama->aramaSonucSayisi($q, $filtreler);
}

$bolumler     = $arama->tumBolumler();
$toplam_sayfa = $toplam > 0 ? (int) ceil($toplam / SAYFA_BASINA_ILAN) : 0;

/* ── Sayfalama URL yardımcısı ────────────────────────────── */
function ara_sayfa_url(int $s): string {
    $p = $_GET;
    $p['sayfa'] = $s;
    return 'ara.php?' . http_build_query($p);
}

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa   = 'arama';
$sayfa_basligi = $q !== '' ? 'Arama: ' . $q : 'Arama';
require_once __DIR__ . '/header.php';
?>

<!-- ═══════════════════════════════════════════════════════
     SAYFA BAŞLIĞI
═══════════════════════════════════════════════════════ -->
<div class="ara-ust">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a>
            <span class="breadcrumb-ayrac">›</span>
            <span>Arama Sonuçları</span>
        </nav>

        <!-- Arama kutusu -->
        <form class="arama-kutusu ara-hero-kutusu" action="ara.php" method="GET">
            <input
                type="text"
                name="q"
                value="<?= temizle($q) ?>"
                placeholder="Kitap adı, yazar veya bölüm ara..."
                aria-label="Arama">
            <button type="submit">Ara</button>
        </form>

        <?php if ($hata): ?>
            <div class="alert alert-danger" style="margin-top:12px;">
                <?= temizle($hata) ?>
            </div>
        <?php endif; ?>

        <p class="ara-sonuc-ozet">
            <?php if ($q !== ''): ?>
                <strong>"<?= temizle($q) ?>"</strong> için
                <strong><?= $toplam ?></strong> sonuç bulundu
            <?php else: ?>
                Tüm ilanlar listeleniyor — <strong><?= $toplam ?></strong> ilan
            <?php endif; ?>
        </p>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════
     İKİ KOLONLU LAYOUT
═══════════════════════════════════════════════════════ -->
<div class="icerik-alani">
    <div class="container ara-layout">

        <!-- ─────────────────────────────────────────────
             SOL SİDEBAR — filtre formu
        ───────────────────────────────────────────────── -->
        <aside class="ara-sidebar">
            <form method="GET" action="ara.php">

                <!-- q'yu form submit'te kaybetme -->
                <input type="hidden" name="q" value="<?= temizle($q) ?>">

                <div class="sidebar-ust">
                    <span class="sidebar-baslik-metin">Filtrele</span>
                    <a href="ara.php?q=<?= urlencode($q) ?>" class="sidebar-sifirla">Sıfırla</a>
                </div>

                <!-- Bölüm -->
                <div class="sidebar-grup">
                    <h4 class="sidebar-grup-baslik">Bölüm</h4>
                    <select name="bolum_id" class="form-select">
                        <option value="">Tüm Bölümler</option>
                        <?php foreach ($bolumler as $b): ?>
                            <option
                                value="<?= (int) $b['id'] ?>"
                                <?= (isset($filtreler['bolum_id']) && $filtreler['bolum_id'] == $b['id']) ? 'selected' : '' ?>>
                                <?= temizle($b['ad']) ?> (<?= (int) $b['ilan_sayisi'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Kitap Durumu -->
                <div class="sidebar-grup">
                    <h4 class="sidebar-grup-baslik">Kitap Durumu</h4>
                    <div class="radio-listesi">
                        <label class="radio-etiket">
                            <input type="radio" name="durum" value=""
                                <?= empty($filtreler['durum']) ? 'checked' : '' ?>>
                            <span>Tümü</span>
                        </label>
                        <?php
                        $durumlar = ['yeni' => 'Yeni', 'iyi' => 'İyi', 'orta' => 'Orta'];
                        foreach ($durumlar as $deger => $etiket):
                            $secili = (($filtreler['durum'] ?? '') === $deger);
                        ?>
                            <label class="radio-etiket">
                                <input type="radio" name="durum" value="<?= $deger ?>"
                                    <?= $secili ? 'checked' : '' ?>>
                                <span><?= $etiket ?></span>
                                <?= durum_badge($deger) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Fiyat Aralığı -->
                <div class="sidebar-grup">
                    <h4 class="sidebar-grup-baslik">Fiyat Aralığı</h4>
                    <div class="filtre-grup">
                        <input
                            type="number" name="min_fiyat" class="form-input"
                            placeholder="Min ₺" min="0"
                            value="<?= temizle((string) ($_GET['min_fiyat'] ?? '')) ?>"
                            aria-label="Minimum fiyat">
                        <span class="filtre-ayrac">—</span>
                        <input
                            type="number" name="max_fiyat" class="form-input"
                            placeholder="Max ₺" min="0"
                            value="<?= temizle((string) ($_GET['max_fiyat'] ?? '')) ?>"
                            aria-label="Maksimum fiyat">
                    </div>
                </div>

                <button type="submit" class="btn-primary sidebar-submit">
                    Filtreyi Uygula
                </button>

            </form>
        </aside>

        <!-- ─────────────────────────────────────────────
             SAĞ SONUÇLAR
        ───────────────────────────────────────────────── -->
        <section class="ara-sonuclar">

            <!-- Sıralama barı -->
            <div class="siralama-bari">
                <span class="sonuc-bilgi">
                    <?= $toplam ?> ilan bulundu
                </span>

                <form class="siralama-form" method="GET" action="ara.php">
                    <!-- Tüm mevcut parametreleri koru -->
                    <input type="hidden" name="q"        value="<?= temizle($q) ?>">
                    <?php if (!empty($filtreler['bolum_id'])): ?>
                        <input type="hidden" name="bolum_id" value="<?= (int) $filtreler['bolum_id'] ?>">
                    <?php endif; ?>
                    <?php if (!empty($filtreler['durum'])): ?>
                        <input type="hidden" name="durum"    value="<?= temizle($filtreler['durum']) ?>">
                    <?php endif; ?>
                    <?php if (isset($filtreler['min_fiyat'])): ?>
                        <input type="hidden" name="min_fiyat" value="<?= (float) $filtreler['min_fiyat'] ?>">
                    <?php endif; ?>
                    <?php if (isset($filtreler['max_fiyat'])): ?>
                        <input type="hidden" name="max_fiyat" value="<?= (float) $filtreler['max_fiyat'] ?>">
                    <?php endif; ?>

                    <label for="siralama-sec" style="font-size:13px; color:var(--metin-soluk);">Sırala:</label>
                    <select id="siralama-sec" name="siralama" class="form-select siralama-select"
                            onchange="this.form.submit()">
                        <option value="yeni"   <?= $siralama === 'yeni'   ? 'selected' : '' ?>>En Yeni</option>
                        <option value="ucuz"   <?= $siralama === 'ucuz'   ? 'selected' : '' ?>>En Ucuz</option>
                        <option value="pahali" <?= $siralama === 'pahali' ? 'selected' : '' ?>>En Pahalı</option>
                    </select>
                </form>
            </div>

            <?php if (empty($ilanlar)): ?>
                <!-- Boş durum -->
                <div class="bos-durum">
                    <div class="bos-durum-ikon">🔍</div>
                    <h3>Sonuç bulunamadı</h3>
                    <p>
                        <?php if ($q !== ''): ?>
                            "<?= temizle($q) ?>" için herhangi bir ilan bulunamadı.
                            Lütfen anahtar kelimelerinizi kontrol edin veya farklı
                            bir kategoriyle aramayı deneyin.
                        <?php else: ?>
                            Seçilen filtrelere uygun ilan bulunamadı.
                        <?php endif; ?>
                    </p>
                    <div style="display:flex; gap:8px; justify-content:center; flex-wrap:wrap;">
                        <a href="index.php" class="btn-primary">Tüm İlanları Gör</a>
                        <?php if (!empty($filtreler)): ?>
                            <a href="ara.php?q=<?= urlencode($q) ?>" class="btn-secondary">
                                Filtreleri Temizle
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <!-- Kart grid -->
                <div class="grid-2">
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
                                <?php if (!empty($ilan['bolum_adi'])): ?>
                                    <span class="bolum-tagi" style="margin-top:6px; display:inline-block;">
                                        <?= temizle($ilan['bolum_adi']) ?>
                                    </span>
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
                            <a href="<?= ara_sayfa_url($sayfa - 1) ?>" aria-label="Önceki">‹</a>
                        <?php else: ?>
                            <span class="devre-disi">‹</span>
                        <?php endif; ?>

                        <?php
                        $bas = max(1, $sayfa - 2);
                        $bit = min($toplam_sayfa, $sayfa + 2);
                        if ($bas > 1): ?>
                            <a href="<?= ara_sayfa_url(1) ?>">1</a>
                            <?php if ($bas > 2): ?><span class="devre-disi">…</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $bas; $i <= $bit; $i++): ?>
                            <?php if ($i === $sayfa): ?>
                                <span class="aktif"><?= $i ?></span>
                            <?php else: ?>
                                <a href="<?= ara_sayfa_url($i) ?>"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($bit < $toplam_sayfa): ?>
                            <?php if ($bit < $toplam_sayfa - 1): ?>
                                <span class="devre-disi">…</span>
                            <?php endif; ?>
                            <a href="<?= ara_sayfa_url($toplam_sayfa) ?>"><?= $toplam_sayfa ?></a>
                        <?php endif; ?>

                        <?php if ($sayfa < $toplam_sayfa): ?>
                            <a href="<?= ara_sayfa_url($sayfa + 1) ?>" aria-label="Sonraki">›</a>
                        <?php else: ?>
                            <span class="devre-disi">›</span>
                        <?php endif; ?>

                    </nav>
                <?php endif; ?>

            <?php endif; ?>
        </section>

    </div><!-- .container -->
</div><!-- .icerik-alani -->

<?php require_once __DIR__ . '/footer.php'; ?>
