<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Ilan.class.php';

$conn = db_baglan();
$ilan = new Ilan($conn);

$bolumler = $ilan->bolumler();

/* ── Form durumu ─────────────────────────────────────────── */
$hatalar  = [];
$basarili = false;
$yonetim_link = '';
$detay_link   = '';

/* Form alanları — POST'tan veya bölüm sayfasından gelen ön seçimden */
$form = [
    'kitap_adi' => '',
    'yazar'     => '',
    'bolum_id'  => isset($_GET['bolum_id']) ? (int) $_GET['bolum_id'] : '',
    'fiyat'     => '',
    'durum'     => '',
    'aciklama'  => '',
    'iletisim'  => '',
];

/* ── POST işleme ─────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $alan => $_) {
        $form[$alan] = trim((string) ($_POST[$alan] ?? ''));
    }

    $hatalar = Ilan::dogrula($form);

    // Bölümün gerçekten var olduğunu da doğrula (dropdown manipülasyonuna karşı).
    if (!isset($hatalar['bolum_id']) && !$ilan->bolumGecerliMi((int) $form['bolum_id'])) {
        $hatalar['bolum_id'] = 'Seçilen bölüm bulunamadı.';
    }

    if (empty($hatalar)) {
        try {
            $sonuc = $ilan->ekle($form);
            $basarili = true;
            $yonetim_link = SITE_URL . '/yonet.php?id=' . $sonuc['id'] . '&token=' . $sonuc['token'];
            $detay_link   = SITE_URL . '/ilan.php?id=' . $sonuc['id'];
        } catch (\Throwable $e) {
            $hatalar['genel'] = 'İlan kaydedilirken bir hata oluştu. Lütfen tekrar deneyin.';
        }
    }
}

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa   = 'ilan-ekle';
$sayfa_basligi = 'İlan Ver';
require_once __DIR__ . '/header.php';
?>

<div class="icerik-alani">
    <div class="container form-sayfa">

        <?php if ($basarili): ?>
            <!-- ═══════════════ BAŞARI EKRANI ═══════════════ -->
            <div class="form-kart">
                <div class="basari-ikon">✅</div>
                <h1 class="form-baslik" style="text-align:center;">İlanın yayında!</h1>
                <p class="metin-soluk" style="text-align:center; margin-bottom:var(--sp-6);">
                    Kitabın artık ana sayfada listeleniyor.
                </p>

                <div class="alert alert-info">
                    <strong>Yönetim linkini sakla!</strong><br>
                    Hesap oluşturmadan ilanını bu özel linkle düzenleyebilir veya
                    silebilirsin. Linki kaybedersen ilanına tekrar erişemezsin.
                </div>

                <label class="form-label">Yönetim linkin</label>
                <div class="token-kutu">
                    <input type="text" class="form-input" id="yonetim-link"
                           value="<?= temizle($yonetim_link) ?>" readonly
                           onclick="this.select()">
                    <button type="button" class="btn-secondary"
                            onclick="navigator.clipboard.writeText(document.getElementById('yonetim-link').value); this.textContent='Kopyalandı ✓';">
                        Kopyala
                    </button>
                </div>

                <div class="form-aksiyon" style="margin-top:var(--sp-6);">
                    <a href="<?= temizle($detay_link) ?>" class="btn-primary">İlanı Görüntüle →</a>
                    <a href="yonet.php?id=<?= (int) $sonuc['id'] ?>&token=<?= temizle($sonuc['token']) ?>"
                       class="btn-secondary">Yönetim Paneline Git</a>
                </div>
            </div>

        <?php else: ?>
            <!-- ═══════════════ İLAN FORMU ═══════════════ -->
            <nav class="breadcrumb" aria-label="Konum">
                <a href="index.php">Ana Sayfa</a>
                <span class="breadcrumb-ayrac">›</span>
                <span>İlan Ver</span>
            </nav>

            <h1 class="form-baslik">İkinci el kitabını sat</h1>
            <p class="metin-soluk" style="margin-bottom:var(--sp-6);">
                Bilgileri doldur, ilanın saniyeler içinde yayına girsin. Giriş yapmana gerek yok.
            </p>

            <?php if (!empty($hatalar['genel'])): ?>
                <div class="alert alert-danger"><?= temizle($hatalar['genel']) ?></div>
            <?php endif; ?>

            <form class="form-kart" method="POST" action="ilan-ekle.php" novalidate>

                <div class="form-group">
                    <label class="form-label" for="kitap_adi">Kitap Adı *</label>
                    <input type="text" class="form-input" id="kitap_adi" name="kitap_adi"
                           value="<?= temizle($form['kitap_adi']) ?>"
                           placeholder="Örn. Veri Yapıları ve Algoritmalar" maxlength="200">
                    <?php if (isset($hatalar['kitap_adi'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['kitap_adi']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="yazar">Yazar</label>
                    <input type="text" class="form-input" id="yazar" name="yazar"
                           value="<?= temizle($form['yazar']) ?>"
                           placeholder="Örn. Rıfat Çölkesen" maxlength="150">
                    <?php if (isset($hatalar['yazar'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['yazar']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label class="form-label" for="bolum_id">Bölüm *</label>
                        <select class="form-select" id="bolum_id" name="bolum_id">
                            <option value="">Bölüm seç...</option>
                            <?php foreach ($bolumler as $b): ?>
                                <option value="<?= (int) $b['id'] ?>"
                                    <?= ((string) $form['bolum_id'] === (string) $b['id']) ? 'selected' : '' ?>>
                                    <?= temizle($b['ad']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($hatalar['bolum_id'])): ?>
                            <span class="form-hata"><?= temizle($hatalar['bolum_id']) ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fiyat">Fiyat (₺) *</label>
                        <input type="text" class="form-input" id="fiyat" name="fiyat"
                               value="<?= temizle($form['fiyat']) ?>"
                               placeholder="Örn. 120" inputmode="decimal">
                        <?php if (isset($hatalar['fiyat'])): ?>
                            <span class="form-hata"><?= temizle($hatalar['fiyat']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Kitabın Durumu *</label>
                    <div class="durum-secenekleri">
                        <?php
                        $durumlar = ['yeni' => 'Yeni', 'iyi' => 'İyi', 'orta' => 'Orta'];
                        foreach ($durumlar as $deger => $etiket): ?>
                            <label class="durum-secenek">
                                <input type="radio" name="durum" value="<?= $deger ?>"
                                    <?= $form['durum'] === $deger ? 'checked' : '' ?>>
                                <span><?= $etiket ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php if (isset($hatalar['durum'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['durum']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="aciklama">Açıklama</label>
                    <textarea class="form-textarea" id="aciklama" name="aciklama"
                              placeholder="Kitabın durumu, baskı yılı, notlar hakkında detay ver..."><?= temizle($form['aciklama']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="iletisim">İletişim (e-posta veya telefon) *</label>
                    <input type="text" class="form-input" id="iletisim" name="iletisim"
                           value="<?= temizle($form['iletisim']) ?>"
                           placeholder="ornek@std.yeditepe.edu.tr veya 05XX XXX XX XX" maxlength="100">
                    <?php if (isset($hatalar['iletisim'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['iletisim']) ?></span>
                    <?php else: ?>
                        <span class="form-yardim">Alıcılar bu bilgiyle sana ulaşır. E-posta veya cep telefonu girebilirsin.</span>
                    <?php endif; ?>
                </div>

                <div class="form-aksiyon">
                    <button type="submit" class="btn-primary">İlanı Yayınla</button>
                    <a href="index.php" class="btn-secondary">Vazgeç</a>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
