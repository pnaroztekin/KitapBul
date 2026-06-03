<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Arama.class.php';

$conn         = db_baglan();
$arama        = new Arama($conn);
$bolumler     = $arama->tumBolumler();
$toplam_ilan  = array_sum(array_column($bolumler, 'ilan_sayisi'));

$ikonlar = [
    'Bilgisayar' => '&#128187;',
    'Elektrik'   => '&#9889;',
    'Matematik'  => '&#128208;',
    'Fizik'      => '&#128301;',
    'Kimya'      => '&#9879;',
    'default'    => '&#128218;',
];

function bolum_ikon(string $ad): string {
    global $ikonlar;
    foreach ($ikonlar as $anahtar => $ikon) {
        if ($anahtar !== 'default' && stripos($ad, $anahtar) !== false) return $ikon;
    }
    return $ikonlar['default'];
}

/* Fakulteye gore grupla */
$gruplar = [];
foreach ($bolumler as $b) {
    $fak = $b['fakulte'] ?? 'Diger';
    $gruplar[$fak][] = $b;
}

$aktif_sayfa   = 'bolumler';
$sayfa_basligi = 'Bolumler';
require_once __DIR__ . '/header.php';
?>

<div class="bolumler-hero-bolum">
    <div class="container" style="text-align:center; padding-top:48px; padding-bottom:40px;">
        <h1 style="font-size:28px; font-weight:700; color:var(--metin-birincil); margin-bottom:8px;">
            B&ouml;l&uuml;mlere G&ouml;re Kitap Bul
        </h1>
        <p style="font-size:15px; color:var(--metin-soluk);">
            <?= count($bolumler) ?> b&ouml;l&uuml;m
            &middot; <strong style="color:var(--mavi-birincil);"><?= (int)$toplam_ilan ?></strong> aktif ilan
        </p>
    </div>
</div>

<main class="icerik-alani">
    <div class="container">

        <?php foreach ($gruplar as $fakulte => $liste): ?>
        <section style="margin-bottom:48px;">

            <h2 style="
                font-size:12px;
                font-weight:600;
                text-transform:uppercase;
                letter-spacing:0.07em;
                color:var(--metin-soluk);
                padding-bottom:12px;
                margin-bottom:20px;
                border-bottom:1px solid var(--border);
            "><?= htmlspecialchars($fakulte, ENT_QUOTES, 'UTF-8') ?></h2>

            <div class="grid-3">
                <?php foreach ($liste as $b): ?>
                <a href="bolum.php?id=<?= (int)$b['id'] ?>" style="
                    display:flex;
                    flex-direction:column;
                    gap:6px;
                    background:#fff;
                    border:1px solid var(--border);
                    border-radius:12px;
                    padding:24px;
                    box-shadow:0 1px 3px rgba(0,0,0,0.08);
                    text-decoration:none;
                    color:inherit;
                    transition:box-shadow 0.15s, transform 0.15s, border-color 0.15s;
                " onmouseover="this.style.boxShadow='0 4px 16px rgba(37,99,235,0.13)';this.style.transform='translateY(-3px)';this.style.borderColor='#BFDBFE';"
                   onmouseout="this.style.boxShadow='0 1px 3px rgba(0,0,0,0.08)';this.style.transform='translateY(0)';this.style.borderColor='var(--border)';">

                    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                        <span style="font-size:30px; line-height:1;"><?= bolum_ikon($b['ad']) ?></span>
                        <?php if ((int)$b['ilan_sayisi'] > 0): ?>
                            <span style="
                                font-size:12px; font-weight:600;
                                background:#EFF6FF; color:#2563EB;
                                padding:3px 10px; border-radius:20px;
                            "><?= (int)$b['ilan_sayisi'] ?> ilan</span>
                        <?php else: ?>
                            <span style="
                                font-size:12px; font-weight:500;
                                background:#F1F5F9; color:#94A3B8;
                                padding:3px 10px; border-radius:20px;
                            ">Hen&uuml;z ilan yok</span>
                        <?php endif; ?>
                    </div>

                    <h3 style="font-size:16px; font-weight:600; color:var(--metin-birincil); line-height:1.3;">
                        <?= htmlspecialchars($b['ad'], ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <p style="font-size:12px; color:var(--metin-soluk);">
                        <?= htmlspecialchars($b['fakulte'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                    </p>

                    <span style="
                        font-size:13px; font-weight:500;
                        color:var(--mavi-birincil);
                        margin-top:auto; padding-top:12px;
                    ">Kitaplar&#305; G&ouml;r &rarr;</span>

                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endforeach; ?>

        <!-- Alt CTA -->
        <div style="
            background:linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border:1px solid #BFDBFE;
            border-radius:12px;
            padding:32px;
            margin-top:16px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:24px;
            flex-wrap:wrap;
        ">
            <div>
                <h3 style="font-size:18px; font-weight:600; color:var(--metin-birincil); margin-bottom:4px;">
                    B&ouml;l&uuml;m&uuml;n&uuml; bulamad&#305;n m&#305;?
                </h3>
                <p style="font-size:14px; color:var(--metin-soluk);">
                    T&uuml;m ilanlar aras&#305;nda arama yapabilirsin.
                </p>
            </div>
            <div style="display:flex; gap:12px; flex-wrap:wrap;">
                <a href="ara.php" class="btn-secondary">T&uuml;m &Icirc;lanlar&#305; Ara</a>
                <a href="ilan-ekle.php" class="btn-primary">&Icirc;lan Ver</a>
            </div>
        </div>

    </div>
</main>

<?php require_once __DIR__ . '/footer.php'; ?>
