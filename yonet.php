<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Ilan.class.php';

$conn = db_baglan();
$ilan_repo = new Ilan($conn);

/* ── Yardımcı: yetkisiz/hatalı erişim sayfası ───────────── */
function yonet_hata(string $mesaj): never {
    global $aktif_sayfa, $sayfa_basligi;
    $aktif_sayfa   = '';
    $sayfa_basligi = 'Erişim Hatası';
    require_once __DIR__ . '/header.php';
    echo '<div class="icerik-alani"><div class="container form-sayfa">';
    echo '<div class="alert alert-danger">' . temizle($mesaj) . '</div>';
    echo '<a href="index.php" class="btn-primary" style="margin-top:12px;">Ana Sayfaya Dön</a>';
    echo '</div></div>';
    require_once __DIR__ . '/footer.php';
    exit;
}

/* ── 1. id + token doğrulama ─────────────────────────────── */
$id    = isset($_GET['id']) && ctype_digit((string) $_GET['id']) ? (int) $_GET['id'] : 0;
$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    $id = ctype_digit((string) $_POST['id']) ? (int) $_POST['id'] : 0;
}

if ($id < 1 || $token === '') {
    yonet_hata('Geçersiz yönetim linki. Lütfen ilanını oluştururken aldığın bağlantıyı kullan.');
}

/* Token ile ilanı bul — yetkilendirme buradadır. */
$ilan = $ilan_repo->tokenIleBul($id, $token);
if (!$ilan) {
    yonet_hata('Bu yönetim linki geçersiz. İlan bulunamadı veya token yanlış.');
}

/* ── 2. İşlem yönetimi ───────────────────────────────────── */
$hatalar  = [];
$mesaj    = '';   // başarı bildirimi
$silindi  = false;

$form = [
    'kitap_adi' => $ilan['kitap_adi'],
    'yazar'     => $ilan['yazar'] ?? '',
    'bolum_id'  => $ilan['bolum_id'],
    'fiyat'     => rtrim(rtrim((string) $ilan['fiyat'], '0'), '.'),
    'durum'     => $ilan['durum'],
    'aciklama'  => $ilan['aciklama'] ?? '',
    'iletisim'  => $ilan['iletisim'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $islem = $_POST['islem'] ?? '';

    /* ── SİLME ── */
    if ($islem === 'sil') {
        if ($ilan_repo->sil($id, $token)) {
            $silindi = true;
        } else {
            $hatalar['genel'] = 'İlan silinemedi. Lütfen tekrar deneyin.';
        }

    /* ── GÜNCELLEME ── */
    } elseif ($islem === 'guncelle') {
        foreach ($form as $alan => $_) {
            $form[$alan] = trim((string) ($_POST[$alan] ?? ''));
        }

        $hatalar = Ilan::dogrula($form);
        if (!isset($hatalar['bolum_id']) && !$ilan_repo->bolumGecerliMi((int) $form['bolum_id'])) {
            $hatalar['bolum_id'] = 'Seçilen bölüm bulunamadı.';
        }

        if (empty($hatalar)) {
            if ($ilan_repo->guncelle($id, $token, $form)) {
                $mesaj = 'İlanın güncellendi.';
                $ilan  = $ilan_repo->tokenIleBul($id, $token); // tazele
            } else {
                $hatalar['genel'] = 'Güncelleme sırasında bir hata oluştu.';
            }
        }
    }
}

$bolumler = $ilan_repo->bolumler();
$talepler = $silindi ? [] : $ilan_repo->talepleriGetir($id);

/* ── Header ──────────────────────────────────────────────── */
$aktif_sayfa   = '';
$sayfa_basligi = 'İlan Yönetimi';
require_once __DIR__ . '/header.php';
?>

<div class="icerik-alani">
    <div class="container form-sayfa">

        <?php if ($silindi): ?>
            <!-- ═══════════════ SİLME ONAYI ═══════════════ -->
            <div class="form-kart" style="text-align:center;">
                <div class="basari-ikon">🗑️</div>
                <h1 class="form-baslik">İlanın silindi</h1>
                <p class="metin-soluk" style="margin-bottom:var(--sp-6);">
                    İlanın ve ona gelen tüm talepler kalıcı olarak kaldırıldı.
                </p>
                <a href="index.php" class="btn-primary">Ana Sayfaya Dön</a>
            </div>

        <?php else: ?>
            <nav class="breadcrumb" aria-label="Konum">
                <a href="index.php">Ana Sayfa</a>
                <span class="breadcrumb-ayrac">›</span>
                <a href="ilan.php?id=<?= $id ?>"><?= temizle($ilan['kitap_adi']) ?></a>
                <span class="breadcrumb-ayrac">›</span>
                <span>Yönetim</span>
            </nav>

            <h1 class="form-baslik">İlan Yönetim Paneli</h1>
            <p class="metin-soluk" style="margin-bottom:var(--sp-6);">
                Bu özel link ile ilanını düzenleyebilir veya silebilirsin. Giriş gerekmez.
            </p>

            <?php if ($mesaj): ?>
                <div class="alert alert-success"><?= temizle($mesaj) ?></div>
            <?php endif; ?>
            <?php if (!empty($hatalar['genel'])): ?>
                <div class="alert alert-danger"><?= temizle($hatalar['genel']) ?></div>
            <?php endif; ?>

            <!-- ═══════════════ DÜZENLEME FORMU ═══════════════ -->
            <form class="form-kart" method="POST" action="yonet.php" novalidate>
                <input type="hidden" name="islem" value="guncelle">
                <input type="hidden" name="id" value="<?= $id ?>">
                <input type="hidden" name="token" value="<?= temizle($token) ?>">

                <div class="form-group">
                    <label class="form-label" for="kitap_adi">Kitap Adı *</label>
                    <input type="text" class="form-input" id="kitap_adi" name="kitap_adi"
                           value="<?= temizle($form['kitap_adi']) ?>" maxlength="200">
                    <?php if (isset($hatalar['kitap_adi'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['kitap_adi']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label class="form-label" for="yazar">Yazar</label>
                    <input type="text" class="form-input" id="yazar" name="yazar"
                           value="<?= temizle($form['yazar']) ?>" maxlength="150">
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
                               value="<?= temizle($form['fiyat']) ?>" inputmode="decimal">
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
                    <textarea class="form-textarea" id="aciklama" name="aciklama"><?= temizle($form['aciklama']) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="iletisim">İletişim (e-posta veya telefon) *</label>
                    <input type="text" class="form-input" id="iletisim" name="iletisim"
                           value="<?= temizle($form['iletisim']) ?>" maxlength="100">
                    <?php if (isset($hatalar['iletisim'])): ?>
                        <span class="form-hata"><?= temizle($hatalar['iletisim']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="form-aksiyon">
                    <button type="submit" class="btn-primary">Değişiklikleri Kaydet</button>
                    <a href="ilan.php?id=<?= $id ?>" class="btn-secondary">İlanı Görüntüle</a>
                </div>
            </form>

            <!-- ═══════════════ GELEN TALEPLER ═══════════════ -->
            <div class="form-kart" style="margin-top:var(--sp-6);">
                <h3 class="bilgi-karti-baslik">
                    Gelen Talepler
                    <span class="talep-sayac"><?= count($talepler) ?></span>
                </h3>

                <?php if (empty($talepler)): ?>
                    <p class="metin-soluk" style="font-size:14px;">Henüz talep gelmedi.</p>
                <?php else: ?>
                    <ul class="talep-listesi">
                        <?php foreach ($talepler as $t): ?>
                            <li class="talep-item">
                                <div class="talep-ust">
                                    <strong class="metin-mavi"><?= temizle($t['iletisim']) ?></strong>
                                    <span class="talep-tarih">
                                        <?= temizle(date('d.m.Y H:i', strtotime($t['tarih']))) ?>
                                    </span>
                                </div>
                                <?php if (!empty($t['mesaj'])): ?>
                                    <p class="talep-mesaj"><?= nl2br(temizle($t['mesaj'])) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <!-- ═══════════════ TEHLİKELİ BÖLGE — SİLME ═══════════════ -->
            <div class="form-kart tehlike-bolge" style="margin-top:var(--sp-6);">
                <h3 class="bilgi-karti-baslik">İlanı Sil</h3>
                <p class="metin-soluk" style="font-size:14px; margin-bottom:var(--sp-4);">
                    İlanı sildiğinde geri alamazsın; gelen tüm talepler de silinir.
                </p>
                <form method="POST" action="yonet.php"
                      onsubmit="return confirm('Bu ilanı kalıcı olarak silmek istediğine emin misin?');">
                    <input type="hidden" name="islem" value="sil">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="token" value="<?= temizle($token) ?>">
                    <button type="submit" class="btn-sil">İlanı Kalıcı Olarak Sil</button>
                </form>
            </div>

        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
