<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Ilan.class.php';

$conn = db_baglan();
$ilan_repo = new Ilan($conn);

/* ── Yardımcı: hata sayfası göster ve çık ───────────────── */
function ilan_hata(string $mesaj): never {
    global $aktif_sayfa, $sayfa_basligi;
    $aktif_sayfa   = '';
    $sayfa_basligi = 'İlan Bulunamadı';
    require_once __DIR__ . '/header.php';
    echo '<div class="icerik-alani"><div class="container">';
    echo '<div class="alert alert-danger">' . temizle($mesaj) . '</div>';
    echo '<a href="index.php" class="btn-primary" style="margin-top:12px;">Ana Sayfaya Dön</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── 1. id doğrulama ─────────────────────────────────────── */
if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id']) || (int) $_GET['id'] < 1) {
    ilan_hata('Geçersiz ilan adresi.');
}
$id = (int) $_GET['id'];

/* ── 2. Talep formu POST işleme (PRG deseni) ─────────────── */
$talep_hatalar = [];
$talep_form    = ['mesaj' => '', 'iletisim' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'talep') {
    $talep_form['mesaj']    = trim((string) ($_POST['mesaj'] ?? ''));
    $talep_form['iletisim'] = trim((string) ($_POST['iletisim'] ?? ''));

    $talep_hatalar = Ilan::talepDogrula($talep_form);

    // İlanın hâlâ aktif olduğunu doğrula
    if (empty($talep_hatalar) && !$ilan_repo->bul($id)) {
        $talep_hatalar['genel'] = 'Bu ilan artık yayında değil.';
    }

    if (empty($talep_hatalar)) {
        if ($ilan_repo->talepEkle($id, $talep_form['mesaj'], $talep_form['iletisim'])) {
            // Form tekrar gönderilmesin diye yönlendir
            header('Location: ilan.php?id=' . $id . '&talep=ok');
            exit;
        }
        $talep_hatalar['genel'] = 'Talebin gönderilemedi. Lütfen tekrar deneyin.';
    }
}

/* ── 3. İlanı çek ────────────────────────────────────────── */
$ilan = $ilan_repo->bul($id);
if (!$ilan) {
    ilan_hata('Aradığın ilan bulunamadı veya yayından kaldırılmış.');
}

$talep_gonderildi = isset($_GET['talep']) && $_GET['talep'] === 'ok';

/* ── Tarih formatlama ────────────────────────────────────── */
function ilan_tarih(?string $tarih): string {
    if (!$tarih) return '—';
    $ts = strtotime($tarih);
    $fark = time() - $ts;
    if ($fark < 3600)   return max(1, (int)($fark / 60)) . ' dakika önce';
    if ($fark < 86400)  return (int)($fark / 3600) . ' saat önce';
    if ($fark < 604800) return (int)($fark / 86400) . ' gün önce';
    return date('d.m.Y', $ts);
}

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa   = 'anasayfa';
$sayfa_basligi = $ilan['kitap_adi'];
require_once __DIR__ . '/header.php';
?>

<div class="icerik-alani">
    <div class="container">

        <!-- Breadcrumb -->
        <nav class="breadcrumb" aria-label="Konum">
            <a href="index.php">Ana Sayfa</a>
            <span class="breadcrumb-ayrac">›</span>
            <?php if (!empty($ilan['bolum_adi'])): ?>
                <a href="bolum.php?id=<?= (int) $ilan['bolum_id'] ?>"><?= temizle($ilan['bolum_adi']) ?></a>
                <span class="breadcrumb-ayrac">›</span>
            <?php endif; ?>
            <span><?= temizle($ilan['kitap_adi']) ?></span>
        </nav>

        <div class="ilan-detay-layout">

            <!-- ── SOL: görsel + açıklama ─────────────────── -->
            <div class="ilan-detay-ana">
                <div class="ilan-detay-gorsel">
                    <span class="ilan-detay-emoji">📖</span>
                    <span class="ilan-detay-badge"><?= durum_badge($ilan['durum']) ?></span>
                </div>

                <div class="ilan-detay-aciklama">
                    <h2>Açıklama</h2>
                    <?php if (!empty($ilan['aciklama'])): ?>
                        <p><?= nl2br(temizle($ilan['aciklama'])) ?></p>
                    <?php else: ?>
                        <p class="metin-soluk">Bu ilan için açıklama eklenmemiş.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── SAĞ: bilgi + talep formu ───────────────── -->
            <aside class="ilan-detay-yan">
                <div class="ilan-bilgi-karti">
                    <h1 class="ilan-detay-baslik"><?= temizle($ilan['kitap_adi']) ?></h1>
                    <?php if (!empty($ilan['yazar'])): ?>
                        <p class="ilan-detay-yazar"><?= temizle($ilan['yazar']) ?></p>
                    <?php endif; ?>

                    <p class="ilan-detay-fiyat"><?= para_formatla((float) $ilan['fiyat']) ?></p>

                    <ul class="bilgi-karti-liste">
                        <li class="bilgi-karti-satir">
                            <span class="bilgi-karti-etiket">Durum</span>
                            <strong><?= durum_badge($ilan['durum']) ?></strong>
                        </li>
                        <?php if (!empty($ilan['bolum_adi'])): ?>
                            <li class="bilgi-karti-satir">
                                <span class="bilgi-karti-etiket">Bölüm</span>
                                <a href="bolum.php?id=<?= (int) $ilan['bolum_id'] ?>" class="metin-mavi font-600">
                                    <?= temizle($ilan['bolum_adi']) ?>
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="bilgi-karti-satir">
                            <span class="bilgi-karti-etiket">İletişim</span>
                            <strong><?= temizle($ilan['iletisim']) ?></strong>
                        </li>
                        <li class="bilgi-karti-satir">
                            <span class="bilgi-karti-etiket">Eklenme</span>
                            <strong><?= temizle(ilan_tarih($ilan['olusturma_tarihi'])) ?></strong>
                        </li>
                    </ul>
                </div>

                <!-- Talep bırakma formu -->
                <div class="ilan-bilgi-karti" id="talep">
                    <h3 class="bilgi-karti-baslik">İlgileniyor musun?</h3>

                    <?php if ($talep_gonderildi): ?>
                        <div class="alert alert-success">
                            Talebin satıcıya iletildi! En kısa sürede seninle iletişime geçecek.
                        </div>
                    <?php else: ?>
                        <p class="metin-soluk" style="font-size:13px; margin-bottom:var(--sp-4);">
                            Satıcıya mesaj bırak; iletişim bilgini görüp sana dönsün.
                        </p>

                        <?php if (!empty($talep_hatalar['genel'])): ?>
                            <div class="alert alert-danger"><?= temizle($talep_hatalar['genel']) ?></div>
                        <?php endif; ?>

                        <form method="POST" action="ilan.php?id=<?= $id ?>#talep" novalidate>
                            <input type="hidden" name="islem" value="talep">

                            <div class="form-group">
                                <label class="form-label" for="t-iletisim">İletişim bilgin *</label>
                                <input type="text" class="form-input" id="t-iletisim" name="iletisim"
                                       value="<?= temizle($talep_form['iletisim']) ?>"
                                       placeholder="E-posta veya telefon" maxlength="100">
                                <?php if (isset($talep_hatalar['iletisim'])): ?>
                                    <span class="form-hata"><?= temizle($talep_hatalar['iletisim']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="t-mesaj">Mesajın</label>
                                <textarea class="form-textarea" id="t-mesaj" name="mesaj"
                                          placeholder="Merhaba, kitap hâlâ satılık mı?"><?= temizle($talep_form['mesaj']) ?></textarea>
                                <?php if (isset($talep_hatalar['mesaj'])): ?>
                                    <span class="form-hata"><?= temizle($talep_hatalar['mesaj']) ?></span>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn-primary" style="width:100%; justify-content:center;">
                                Talep Gönder
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
