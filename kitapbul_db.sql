-- ============================================================
-- KitapBul — Veritabanı Kurulum Scripti
-- ACM368 Web Programlama Projesi | Yeditepe Üniversitesi
-- ============================================================

CREATE DATABASE IF NOT EXISTS kitapbul_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_turkish_ci;

USE kitapbul_db;

-- ------------------------------------------------------------
-- Tablo: bolumler
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bolumler (
    id       INT          PRIMARY KEY AUTO_INCREMENT,
    ad       VARCHAR(100) NOT NULL,
    fakulte  VARCHAR(100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ------------------------------------------------------------
-- Tablo: ilanlar
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ilanlar (
    id               INT            PRIMARY KEY AUTO_INCREMENT,
    kitap_adi        VARCHAR(200)   NOT NULL,
    yazar            VARCHAR(150),
    bolum_id         INT,
    fiyat            DECIMAL(8,2)   NOT NULL,
    durum            ENUM('yeni','iyi','orta') NOT NULL,
    aciklama         TEXT,
    iletisim         VARCHAR(100)   NOT NULL,
    yonetim_token    VARCHAR(64)    NOT NULL,
    aktif            TINYINT        DEFAULT 1,
    olusturma_tarihi TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bolum_id) REFERENCES bolumler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ------------------------------------------------------------
-- Tablo: talepler
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS talepler (
    id       INT   PRIMARY KEY AUTO_INCREMENT,
    ilan_id  INT   NOT NULL,
    mesaj    TEXT,
    iletisim VARCHAR(100) NOT NULL,
    tarih    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ilan_id) REFERENCES ilanlar(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;

-- ============================================================
-- SEED: bolumler (ID 1-6 sabit)
-- ============================================================
INSERT INTO bolumler (id, ad, fakulte) VALUES
    (1, 'Bilgisayar Mühendisliği',   'Mühendislik'),
    (2, 'Elektrik-Elektronik Müh.',  'Mühendislik'),
    (3, 'Matematik',                 'Fen-Edebiyat'),
    (4, 'Fizik',                     'Fen-Edebiyat'),
    (5, 'İşletme',                   'İktisadi ve İdari Bilimler'),
    (6, 'Kimya Mühendisliği',        'Mühendislik');

-- ============================================================
-- SEED: ilanlar (12 ilan)
-- ============================================================
INSERT INTO ilanlar
    (kitap_adi, yazar, bolum_id, fiyat, durum, aciklama, iletisim, yonetim_token, aktif)
VALUES

-- Bilgisayar Mühendisliği (bolum_id = 1) — 4 ilan
(
    'Veri Yapıları ve Algoritmalar',
    'Rıfat Çölkesen',
    1, 120.00, 'iyi',
    'Sayfaların birkaçında kurşun kalem notu var, aksi hâlde temiz.',
    'ayse.kaya2023@std.yeditepe.edu.tr',
    'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6a1b2',
    1
),
(
    'Modern İşletim Sistemleri',
    'Andrew S. Tanenbaum',
    1, 220.00, 'orta',
    'Kapak biraz yıpranmış, içi gayet temiz. 3. baskı.',
    'mehmet.demir@std.yeditepe.edu.tr',
    'b2c3d4e5f6a7b2c3d4e5f6a7b2c3d4e5f6a7b2c3d4e5f6a7b2c3d4e5f6a7b2c3',
    1
),
(
    'C ile Programlama',
    'Deitel & Deitel',
    1, 110.00, 'yeni',
    'Hiç açılmamış, ambalajından yeni çıktı.',
    'zeynep.arslan@std.yeditepe.edu.tr',
    'c3d4e5f6a7b8c3d4e5f6a7b8c3d4e5f6a7b8c3d4e5f6a7b8c3d4e5f6a7b8c3d4',
    1
),
(
    'Bilgisayar Ağları',
    'James F. Kurose',
    1, 185.00, 'iyi',
    'Ders bittikten sonra rafta kaldı, temiz.',
    'can.yilmaz@std.yeditepe.edu.tr',
    'd4e5f6a7b8c9d4e5f6a7b8c9d4e5f6a7b8c9d4e5f6a7b8c9d4e5f6a7b8c9d4e5',
    1
),

-- Matematik (bolum_id = 3) — 3 ilan
(
    'Calculus: Early Transcendentals',
    'James Stewart',
    3, 85.00, 'orta',
    'Sayfa kenarlarında bazı notlar var. Fiyata dahil değil ama highlighter kalem hediye.',
    'selin.celik@std.yeditepe.edu.tr',
    'e5f6a7b8c9d0e5f6a7b8c9d0e5f6a7b8c9d0e5f6a7b8c9d0e5f6a7b8c9d0e5f6',
    1
),
(
    'Lineer Cebir',
    'Gilbert Strang',
    3, 150.00, 'iyi',
    '4. baskı. Üniversitenin önerdiği baskı. Bir sömestr kullandım.',
    'burak.sahin@std.yeditepe.edu.tr',
    'f6a7b8c9d0e1f6a7b8c9d0e1f6a7b8c9d0e1f6a7b8c9d0e1f6a7b8c9d0e1f6a7',
    1
),
(
    'Diferansiyel Denklemler',
    'Dennis Zill',
    3, 200.00, 'yeni',
    'Sınavdan önce aldım ama kullanmadım. Ambalajlı.',
    'nisa.erdogan@std.yeditepe.edu.tr',
    'a7b8c9d0e1f2a7b8c9d0e1f2a7b8c9d0e1f2a7b8c9d0e1f2a7b8c9d0e1f2a7b8',
    1
),

-- Elektrik-Elektronik (bolum_id = 2) — 1 ilan
(
    'Devre Analizi',
    'Hayt & Kemmerly',
    2, 200.00, 'yeni',
    '8. baskı. Hiç kullanılmadı.',
    'emre.ozturk@std.yeditepe.edu.tr',
    'b8c9d0e1f2a3b8c9d0e1f2a3b8c9d0e1f2a3b8c9d0e1f2a3b8c9d0e1f2a3b8c9',
    1
),

-- Fizik (bolum_id = 4) — 1 ilan
(
    'Fizik 1',
    'Serway & Jewett',
    4, 65.00, 'iyi',
    'Ön kapakta hafif çizik var, içi temiz. 9. baskı.',
    'dilan.kurt@std.yeditepe.edu.tr',
    'c9d0e1f2a3b4c9d0e1f2a3b4c9d0e1f2a3b4c9d0e1f2a3b4c9d0e1f2a3b4c9d0',
    1
),

-- İşletme (bolum_id = 5) — 1 ilan
(
    'İşletme Yönetimi',
    'Robbins & Coulter',
    5, 90.00, 'orta',
    'Birkaç sayfada üzeri çizili notlar var, genel olarak okunabilir durumda.',
    'haluk.boz@std.yeditepe.edu.tr',
    'd0e1f2a3b4c5d0e1f2a3b4c5d0e1f2a3b4c5d0e1f2a3b4c5d0e1f2a3b4c5d0e1',
    1
),

-- Kimya Mühendisliği (bolum_id = 6) — 1 ilan
(
    'Kimya Mühendisliği Termodinamiği',
    'Smith, Van Ness & Abbott',
    6, 280.00, 'iyi',
    'Orijinal İngilizce baskı. 8. edition. Tek bir dönem kullandım.',
    'pelin.aktaş@std.yeditepe.edu.tr',
    'e1f2a3b4c5d6e1f2a3b4c5d6e1f2a3b4c5d6e1f2a3b4c5d6e1f2a3b4c5d6e1f2',
    1
),

-- Bilgisayar Mühendisliği — ekstra (bolum_id = 1)
(
    'Yazılım Mühendisliği',
    'Ian Sommerville',
    1, 160.00, 'orta',
    '10. baskı. İlk 5 bölüm yoğun vurgulanmış, geri kalanı temiz.',
    'arda.toprak@std.yeditepe.edu.tr',
    'f2a3b4c5d6e7f2a3b4c5d6e7f2a3b4c5d6e7f2a3b4c5d6e7f2a3b4c5d6e7f2a3',
    1
);

-- ============================================================
-- SEED: talepler (4 örnek talep)
-- ============================================================
INSERT INTO talepler (ilan_id, mesaj, iletisim) VALUES
(
    1,
    'Merhaba, kitap hâlâ satılık mı? Fiyatta biraz esneklik olur mu?',
    'oguz.polat@std.yeditepe.edu.tr'
),
(
    1,
    'Yarın kampüste görüşebilir miyiz? Kitabı görmek istiyorum.',
    'canan.yuce@std.yeditepe.edu.tr'
),
(
    5,
    'Calculus kitabı için ilgileniyorum, kargo yapıyor musunuz?',
    'furkan.dinc@std.yeditepe.edu.tr'
),
(
    8,
    '200 TL\'ye verir misiniz? Nakit ödeme yapabilirim.',
    'irem.ozdemir@std.yeditepe.edu.tr'
);
