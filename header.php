<?php
/*
 * BİRLEŞTİRME NOTU:
 * Kişi 1'in sayfaları bu header.php'yi include edecek.
 * Her sayfada header.php'den ÖNCE şunları tanımla:
 *   $sayfa_basligi = 'Sayfa Adı';
 *   $aktif_sayfa   = 'index' | 'arama' | 'bolumler';
 *   (Kişi 1'in sayfaları için yeni değer eklenebilir — ör. 'ilan-ekle')
 * Navbar'a yeni link eklemek gerekirse header.php'deki
 * .navbar-links bölümüne ekle.
 */
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/config.php';
}
$aktif_sayfa = $aktif_sayfa ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= temizle($sayfa_basligi ?? SITE_ADI) ?> — <?= SITE_ADI ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_URL ?>/style.css">
</head>
<body>

<nav class="navbar">
    <div class="container navbar-inner">
        <a href="<?= SITE_URL ?>/index.php" class="navbar-logo"><?= SITE_ADI ?></a>

        <div class="navbar-links">
            <a href="<?= SITE_URL ?>/index.php"
               class="<?= $aktif_sayfa === 'anasayfa' ? 'aktif' : '' ?>">Ana Sayfa</a>
            <a href="<?= SITE_URL ?>/bolum.php"
               class="<?= $aktif_sayfa === 'bolumler' ? 'aktif' : '' ?>">Bölümler</a>
            <a href="<?= SITE_URL ?>/ara.php"
               class="<?= $aktif_sayfa === 'arama' ? 'aktif' : '' ?>">Arama</a>
        </div>

        <a href="<?= SITE_URL ?>/ilan-ekle.php" class="btn-primary">İlan Ver</a>
    </div>
</nav>
