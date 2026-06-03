<?php

/**
 * Ilan — KitapBul ilan & yönetim modülünün OOP çekirdeği.
 *
 * Kişi 1'in tüm sayfaları (ilan-ekle.php, ilan.php, yonet.php) bu sınıfı
 * kullanır. Veritabanı erişimi prepared statement ile yapılır; doğrulama
 * (regex) statik yardımcı metodlarla sağlanır.
 */
class Ilan {
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    /* ========================================================
       OKUMA
    ======================================================== */

    /**
     * Aktif bir ilanı ID'ye göre, bölüm adıyla birlikte getirir.
     * Bulunamazsa veya pasifse null döner.
     */
    public function bul(int $id): ?array {
        $stmt = $this->conn->prepare(
            "SELECT i.*, b.ad AS bolum_adi, b.fakulte
             FROM ilanlar i
             LEFT JOIN bolumler b ON i.bolum_id = b.id
             WHERE i.id = ? AND i.aktif = 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $satir = $stmt->get_result()->fetch_assoc();
        return $satir ?: null;
    }

    /**
     * Yönetim için ilanı ID + token ile getirir (aktiflik koşulu yok).
     * Token eşleşmezse null döner — yetkilendirme bu metodla sağlanır.
     */
    public function tokenIleBul(int $id, string $token): ?array {
        $stmt = $this->conn->prepare(
            "SELECT i.*, b.ad AS bolum_adi
             FROM ilanlar i
             LEFT JOIN bolumler b ON i.bolum_id = b.id
             WHERE i.id = ? AND i.yonetim_token = ?"
        );
        if (!$stmt) return null;
        $stmt->bind_param('is', $id, $token);
        $stmt->execute();
        $satir = $stmt->get_result()->fetch_assoc();
        return $satir ?: null;
    }

    /**
     * Form dropdown'ı için tüm bölümleri (id, ad) getirir.
     */
    public function bolumler(): array {
        $sonuc = $this->conn->query('SELECT id, ad FROM bolumler ORDER BY ad');
        return $sonuc ? $sonuc->fetch_all(MYSQLI_ASSOC) : [];
    }

    /**
     * Belirli bir bölüm ID'sinin geçerli olup olmadığını kontrol eder.
     */
    public function bolumGecerliMi(int $bolum_id): bool {
        $stmt = $this->conn->prepare('SELECT 1 FROM bolumler WHERE id = ?');
        if (!$stmt) return false;
        $stmt->bind_param('i', $bolum_id);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    /* ========================================================
       YAZMA — ekle / guncelle / sil
    ======================================================== */

    /**
     * Yeni ilan ekler. Rastgele yönetim token'ı üretir.
     *
     * @param array $v  kitap_adi, yazar, bolum_id, fiyat, durum, aciklama, iletisim
     * @return array{id:int, token:string}
     */
    public function ekle(array $v): array {
        $token = self::tokenUret();

        $stmt = $this->conn->prepare(
            "INSERT INTO ilanlar
                (kitap_adi, yazar, bolum_id, fiyat, durum, aciklama, iletisim, yonetim_token, aktif)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)"
        );
        if (!$stmt) {
            throw new RuntimeException('İlan kaydedilemedi (hazırlık hatası).');
        }

        $kitap_adi = (string) $v['kitap_adi'];
        $yazar     = ($v['yazar'] ?? '') !== '' ? (string) $v['yazar'] : null;
        $bolum_id  = (int) $v['bolum_id'];
        $fiyat     = (float) $v['fiyat'];
        $durum     = (string) $v['durum'];
        $aciklama  = ($v['aciklama'] ?? '') !== '' ? (string) $v['aciklama'] : null;
        $iletisim  = (string) $v['iletisim'];

        $stmt->bind_param(
            'ssidssss',
            $kitap_adi, $yazar, $bolum_id, $fiyat, $durum, $aciklama, $iletisim, $token
        );

        if (!$stmt->execute()) {
            throw new RuntimeException('İlan kaydedilemedi: ' . $stmt->error);
        }

        return ['id' => (int) $stmt->insert_id, 'token' => $token];
    }

    /**
     * Token doğrulandıktan sonra ilanı günceller.
     * Token eşleşmezse hiçbir satır etkilenmez ve false döner.
     */
    public function guncelle(int $id, string $token, array $v): bool {
        $stmt = $this->conn->prepare(
            "UPDATE ilanlar
             SET kitap_adi = ?, yazar = ?, bolum_id = ?, fiyat = ?,
                 durum = ?, aciklama = ?, iletisim = ?
             WHERE id = ? AND yonetim_token = ?"
        );
        if (!$stmt) return false;

        $kitap_adi = (string) $v['kitap_adi'];
        $yazar     = ($v['yazar'] ?? '') !== '' ? (string) $v['yazar'] : null;
        $bolum_id  = (int) $v['bolum_id'];
        $fiyat     = (float) $v['fiyat'];
        $durum     = (string) $v['durum'];
        $aciklama  = ($v['aciklama'] ?? '') !== '' ? (string) $v['aciklama'] : null;
        $iletisim  = (string) $v['iletisim'];

        $stmt->bind_param(
            'ssidsssis',
            $kitap_adi, $yazar, $bolum_id, $fiyat, $durum, $aciklama, $iletisim, $id, $token
        );
        return $stmt->execute() && $stmt->affected_rows >= 0
            && $this->tokenDogrula($id, $token);
    }

    /**
     * Token doğrulandıktan sonra ilanı ve ona bağlı talepleri kalıcı siler.
     * FK kısıtı nedeniyle önce talepler, sonra ilan silinir (transaction).
     */
    public function sil(int $id, string $token): bool {
        if (!$this->tokenDogrula($id, $token)) {
            return false;
        }

        $this->conn->begin_transaction();
        try {
            $t = $this->conn->prepare('DELETE FROM talepler WHERE ilan_id = ?');
            $t->bind_param('i', $id);
            $t->execute();

            $i = $this->conn->prepare('DELETE FROM ilanlar WHERE id = ? AND yonetim_token = ?');
            $i->bind_param('is', $id, $token);
            $i->execute();
            $silindi = $i->affected_rows > 0;

            $this->conn->commit();
            return $silindi;
        } catch (\Throwable $e) {
            $this->conn->rollback();
            return false;
        }
    }

    /**
     * Verilen ID + token kombinasyonunun geçerli olup olmadığını kontrol eder.
     */
    public function tokenDogrula(int $id, string $token): bool {
        $stmt = $this->conn->prepare(
            'SELECT 1 FROM ilanlar WHERE id = ? AND yonetim_token = ?'
        );
        if (!$stmt) return false;
        $stmt->bind_param('is', $id, $token);
        $stmt->execute();
        return (bool) $stmt->get_result()->fetch_row();
    }

    /* ========================================================
       TALEPLER
    ======================================================== */

    /**
     * Bir ilana yeni talep (alıcı mesajı) ekler.
     */
    public function talepEkle(int $ilan_id, string $mesaj, string $iletisim): bool {
        $stmt = $this->conn->prepare(
            'INSERT INTO talepler (ilan_id, mesaj, iletisim) VALUES (?, ?, ?)'
        );
        if (!$stmt) return false;
        $temiz_mesaj = $mesaj !== '' ? $mesaj : null;
        $stmt->bind_param('iss', $ilan_id, $temiz_mesaj, $iletisim);
        return $stmt->execute();
    }

    /**
     * Bir ilana ait talepleri (en yeni önce) getirir.
     */
    public function talepleriGetir(int $ilan_id): array {
        $stmt = $this->conn->prepare(
            'SELECT * FROM talepler WHERE ilan_id = ? ORDER BY tarih DESC'
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $ilan_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Bir ilana gelen talep sayısını döndürür.
     */
    public function talepSayisi(int $ilan_id): int {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM talepler WHERE ilan_id = ?'
        );
        if (!$stmt) return 0;
        $stmt->bind_param('i', $ilan_id);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /* ========================================================
       DOĞRULAMA — statik regex yardımcıları
    ======================================================== */

    /**
     * İlan form verisini doğrular; hata mesajlarını alan adına göre döndürür.
     * Boş dizi dönerse veri geçerlidir.
     *
     * @return array<string,string>
     */
    public static function dogrula(array $v): array {
        $hatalar = [];

        $kitap_adi = trim((string) ($v['kitap_adi'] ?? ''));
        if ($kitap_adi === '') {
            $hatalar['kitap_adi'] = 'Kitap adı zorunludur.';
        } elseif (mb_strlen($kitap_adi) > 200) {
            $hatalar['kitap_adi'] = 'Kitap adı en fazla 200 karakter olabilir.';
        }

        if (mb_strlen(trim((string) ($v['yazar'] ?? ''))) > 150) {
            $hatalar['yazar'] = 'Yazar adı en fazla 150 karakter olabilir.';
        }

        $bolum_id = (int) ($v['bolum_id'] ?? 0);
        if ($bolum_id < 1) {
            $hatalar['bolum_id'] = 'Lütfen bir bölüm seçin.';
        }

        $fiyat = trim((string) ($v['fiyat'] ?? ''));
        if ($fiyat === '') {
            $hatalar['fiyat'] = 'Fiyat zorunludur.';
        } elseif (!self::gecerliFiyat($fiyat)) {
            $hatalar['fiyat'] = 'Geçerli bir fiyat girin (ör. 120 veya 99.90).';
        }

        $durum = (string) ($v['durum'] ?? '');
        if (!in_array($durum, ['yeni', 'iyi', 'orta'], true)) {
            $hatalar['durum'] = 'Lütfen kitabın durumunu seçin.';
        }

        $iletisim = trim((string) ($v['iletisim'] ?? ''));
        if ($iletisim === '') {
            $hatalar['iletisim'] = 'İletişim bilgisi zorunludur.';
        } elseif (!self::gecerliIletisim($iletisim)) {
            $hatalar['iletisim'] = 'Geçerli bir e-posta veya telefon numarası girin.';
        }

        return $hatalar;
    }

    /**
     * Talep formu (ilan detay sayfası) verisini doğrular.
     *
     * @return array<string,string>
     */
    public static function talepDogrula(array $v): array {
        $hatalar = [];

        $iletisim = trim((string) ($v['iletisim'] ?? ''));
        if ($iletisim === '') {
            $hatalar['iletisim'] = 'Sizinle iletişime geçebilmesi için bir e-posta veya telefon girin.';
        } elseif (!self::gecerliIletisim($iletisim)) {
            $hatalar['iletisim'] = 'Geçerli bir e-posta veya telefon numarası girin.';
        }

        if (mb_strlen(trim((string) ($v['mesaj'] ?? ''))) > 1000) {
            $hatalar['mesaj'] = 'Mesaj en fazla 1000 karakter olabilir.';
        }

        return $hatalar;
    }

    /**
     * İletişim alanı: e-posta VEYA Türkiye cep telefonu formatı.
     */
    public static function gecerliIletisim(string $deger): bool {
        return self::gecerliEposta($deger) || self::gecerliTelefon($deger);
    }

    /** E-posta regex doğrulaması. */
    public static function gecerliEposta(string $deger): bool {
        return (bool) preg_match('/^[\w.+\-]+@[\w\-]+(\.[\w\-]+)+$/', $deger);
    }

    /**
     * Türkiye cep telefonu: 05XX XXX XX XX / +905XXXXXXXXX / 5XXXXXXXXX.
     * Aradaki boşluk, tire ve parantezlere izin verir.
     */
    public static function gecerliTelefon(string $deger): bool {
        $sade = preg_replace('/[\s\-()]/', '', $deger);
        return (bool) preg_match('/^(?:0|\+90|90)?5\d{9}$/', $sade);
    }

    /**
     * Fiyat: pozitif sayı, en fazla 2 ondalık, DECIMAL(8,2) sınırı içinde.
     */
    public static function gecerliFiyat(string $deger): bool {
        $deger = str_replace(',', '.', trim($deger));
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $deger)) {
            return false;
        }
        return (float) $deger > 0 && (float) $deger < 1000000;
    }

    /**
     * 64 karakterlik rastgele yönetim token'ı üretir.
     */
    public static function tokenUret(): string {
        return bin2hex(random_bytes(32));
    }
}
