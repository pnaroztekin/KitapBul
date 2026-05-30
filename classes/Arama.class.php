<?php

class Arama {
    private mysqli $conn;

    public function __construct(mysqli $conn) {
        $this->conn = $conn;
    }

    /**
     * Tüm aktif ilanları sayfalı ve sıralı getirir.
     */
    public function listele(int $sayfa = 1, string $siralama = 'yeni'): array {
        $order = $this->siralamaSQL($siralama);
        $limit  = SAYFA_BASINA_ILAN;
        $offset = ($sayfa - 1) * $limit;

        $sql = "SELECT i.*, b.ad AS bolum_adi
                FROM ilanlar i
                LEFT JOIN bolumler b ON i.bolum_id = b.id
                WHERE i.aktif = 1
                ORDER BY {$order}
                LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('ii', $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Toplam aktif ilan sayısını döndürür.
     */
    public function toplamIlanSayisi(): int {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM ilanlar WHERE aktif = 1'
        );
        if (!$stmt) return 0;
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /**
     * Anahtar kelime ve opsiyonel filtrelerle ilan arar.
     *
     * $filtreler anahtarları: bolum_id, durum, min_fiyat, max_fiyat
     */
    public function ara(string $kelime, array $filtreler = [], int $sayfa = 1): array {
        $limit  = SAYFA_BASINA_ILAN;
        $offset = ($sayfa - 1) * $limit;

        [$where, $tipler, $degerler] = $this->whereOlustur($kelime, $filtreler);

        $sql = "SELECT i.*, b.ad AS bolum_adi
                FROM ilanlar i
                LEFT JOIN bolumler b ON i.bolum_id = b.id
                WHERE {$where}
                ORDER BY i.olusturma_tarihi DESC
                LIMIT ? OFFSET ?";

        $tipler  .= 'ii';
        $degerler[] = $limit;
        $degerler[] = $offset;

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($tipler, ...$degerler);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * ara() ile aynı koşullarla toplam sonuç sayısını döndürür.
     */
    public function aramaSonucSayisi(string $kelime, array $filtreler = []): int {
        [$where, $tipler, $degerler] = $this->whereOlustur($kelime, $filtreler);

        $sql  = "SELECT COUNT(*) FROM ilanlar i WHERE {$where}";
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return 0;
        if ($tipler !== '') {
            $stmt->bind_param($tipler, ...$degerler);
        }
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /**
     * Belirli bir bölümün aktif ilanlarını sayfalı getirir.
     */
    public function bolumIlanlari(int $bolum_id, int $sayfa = 1, string $siralama = 'yeni'): array {
        $order  = $this->siralamaSQL($siralama);
        $limit  = SAYFA_BASINA_ILAN;
        $offset = ($sayfa - 1) * $limit;

        $sql = "SELECT i.*, b.ad AS bolum_adi
                FROM ilanlar i
                LEFT JOIN bolumler b ON i.bolum_id = b.id
                WHERE i.aktif = 1 AND i.bolum_id = ?
                ORDER BY {$order}
                LIMIT ? OFFSET ?";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param('iii', $bolum_id, $limit, $offset);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Belirli bir bölümün aktif ilan sayısını döndürür.
     */
    public function bolumIlanSayisi(int $bolum_id): int {
        $stmt = $this->conn->prepare(
            'SELECT COUNT(*) FROM ilanlar WHERE aktif = 1 AND bolum_id = ?'
        );
        if (!$stmt) return 0;
        $stmt->bind_param('i', $bolum_id);
        $stmt->execute();
        return (int) $stmt->get_result()->fetch_row()[0];
    }

    /**
     * Anahtar kelime olmadan yalnızca filtrelerle listeler.
     */
    public function filtreUygula(array $filtreler, int $sayfa = 1): array {
        return $this->ara('', $filtreler, $sayfa);
    }

    /**
     * Tüm bölümleri aktif ilan sayısıyla birlikte getirir (azalan sıra).
     */
    public function tumBolumler(): array {
        $sql = "SELECT b.id, b.ad, b.fakulte,
                       COUNT(CASE WHEN i.aktif = 1 THEN 1 END) AS ilan_sayisi
                FROM bolumler b
                LEFT JOIN ilanlar i ON i.bolum_id = b.id
                GROUP BY b.id, b.ad, b.fakulte
                ORDER BY ilan_sayisi DESC";

        $sonuc = $this->conn->query($sql);
        if (!$sonuc) return [];
        return $sonuc->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Tek bir bölümü ID'ye göre getirir; bulamazsa null döndürür.
     */
    public function bolumBul(int $bolum_id): ?array {
        $stmt = $this->conn->prepare(
            'SELECT * FROM bolumler WHERE id = ?'
        );
        if (!$stmt) return null;
        $stmt->bind_param('i', $bolum_id);
        $stmt->execute();
        $satir = $stmt->get_result()->fetch_assoc();
        return $satir ?: null;
    }

    /**
     * Bir bölüme ait istatistikleri tek sorguda döndürür.
     *
     * Dönen anahtarlar: toplam_ilan, ort_fiyat, min_fiyat, en_son_ilan
     */
    public function bolumIstatistik(int $bolum_id): array {
        $stmt = $this->conn->prepare(
            "SELECT
                COUNT(*)           AS toplam_ilan,
                ROUND(AVG(fiyat))  AS ort_fiyat,
                MIN(fiyat)         AS min_fiyat,
                MAX(olusturma_tarihi) AS en_son_ilan
             FROM ilanlar
             WHERE aktif = 1 AND bolum_id = ?"
        );
        if (!$stmt) return [];
        $stmt->bind_param('i', $bolum_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?? [];
    }

    /* --------------------------------------------------------
       Özel yardımcı metodlar
    -------------------------------------------------------- */

    /**
     * Sıralama parametresini güvenli SQL ifadesine çevirir.
     */
    private function siralamaSQL(string $siralama): string {
        return match ($siralama) {
            'ucuz'   => 'i.fiyat ASC',
            'pahali' => 'i.fiyat DESC',
            default  => 'i.olusturma_tarihi DESC',
        };
    }

    /**
     * Dinamik WHERE cümlesi ve bind_param malzemelerini üretir.
     *
     * @return array{0: string, 1: string, 2: array}
     */
    private function whereOlustur(string $kelime, array $filtreler): array {
        $kosullar = ['i.aktif = 1'];
        $tipler   = '';
        $degerler = [];

        if ($kelime !== '') {
            $kosullar[] = '(i.kitap_adi LIKE ? OR i.yazar LIKE ?)';
            $like        = '%' . $kelime . '%';
            $tipler     .= 'ss';
            $degerler[]  = $like;
            $degerler[]  = $like;
        }

        if (!empty($filtreler['bolum_id'])) {
            $kosullar[] = 'i.bolum_id = ?';
            $tipler    .= 'i';
            $degerler[] = (int) $filtreler['bolum_id'];
        }

        if (!empty($filtreler['durum'])) {
            $kosullar[] = 'i.durum = ?';
            $tipler    .= 's';
            $degerler[] = $filtreler['durum'];
        }

        if (isset($filtreler['min_fiyat']) && $filtreler['min_fiyat'] !== '') {
            $kosullar[] = 'i.fiyat >= ?';
            $tipler    .= 'd';
            $degerler[] = (float) $filtreler['min_fiyat'];
        }

        if (isset($filtreler['max_fiyat']) && $filtreler['max_fiyat'] !== '') {
            $kosullar[] = 'i.fiyat <= ?';
            $tipler    .= 'd';
            $degerler[] = (float) $filtreler['max_fiyat'];
        }

        $where = implode(' AND ', $kosullar);
        return [$where, $tipler, $degerler];
    }
}
