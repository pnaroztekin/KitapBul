<?php
/*
 * BİRLEŞTİRME NOTU:
 * Kişi 1'in dosyaları (ilan.php, ilan-ekle.php, yonet.php, Ilan.class.php)
 * bu config.php'yi require_once ile kullanacak.
 * db_baglan(), temizle(), para_formatla(), durum_badge() fonksiyonları
 * Kişi 1'in tüm dosyalarında da kullanılabilir — çakışma yok.
 * SAYFA_BASINA_ILAN sabiti Kişi 1'de de kullanılabilir.
 */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'kitapbul_db');
define('DB_CHARSET', 'utf8mb4');

define('SITE_ADI', 'KitapBul');
define('SITE_URL', 'http://localhost/kitapbul');

function db_baglan(): mysqli {
    $baglanti = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($baglanti->connect_error) {
        http_response_code(500);
        die('Veritabanı bağlantısı kurulamadı: ' . htmlspecialchars($baglanti->connect_error));
    }
    $baglanti->set_charset(DB_CHARSET);
    return $baglanti;
}

function temizle(string $deger): string {
    return htmlspecialchars(trim($deger), ENT_QUOTES, 'UTF-8');
}

function para_formatla(float $tutar): string {
    return number_format($tutar, 0, ',', '.') . ' ₺';
}

function durum_badge(string $durum): string {
    $map = [
        'yeni' => ['label' => 'Yeni',  'class' => 'badge-yeni'],
        'iyi'  => ['label' => 'İyi',   'class' => 'badge-iyi'],
        'orta' => ['label' => 'Orta',  'class' => 'badge-orta'],
    ];
    $info = $map[$durum] ?? ['label' => temizle($durum), 'class' => ''];
    return '<span class="badge ' . $info['class'] . '">' . $info['label'] . '</span>';
}

function sayfalama_url(array $params, int $sayfa): string {
    $params['sayfa'] = $sayfa;
    return '?' . http_build_query($params);
}
