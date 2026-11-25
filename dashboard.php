<?php
/* =====================================================
   DASHBOARD MONITORING CUACA BMKG KEMAYORAN
   Desain: Dark Premium + Navigasi Horizontal
   Penambahan: Menu Pemantauan Kota
   Data dan fitur lama tetap dipertahankan
===================================================== */

require_once __DIR__ . '/includes/bmkg_data.php';

$hasilCuaca = bmkg_muat_cuaca($_GET['adm4'] ?? null);
$data = $hasilCuaca['records'];
$lokasiAktif = $hasilCuaca['lokasi'];
$sumberCuaca = $hasilCuaca['sumber'];
$waktuAmbilCuaca = $hasilCuaca['diambil'];
$galatCuaca = $hasilCuaca['error'];

/* ================= STATISTIK CUACA ================= */

$totalData = count($data);
$totalSuhu = 0;
$totalKelembapan = 0;
$totalAngin = 0;
$totalHujan = 0;
$suhuMax = null;
$suhuMin = null;

foreach ($data as $item) {
    $suhu = (float)($item['suhu'] ?? 0);
    $kelembapan = (float)($item['kelembapan'] ?? 0);
    $angin = (float)($item['kecepatan_angin'] ?? 0);
    $hujan = (float)($item['curah_hujan'] ?? 0);

    $totalSuhu += $suhu;
    $totalKelembapan += $kelembapan;
    $totalAngin += $angin;
    $totalHujan += $hujan;

    if ($suhuMax === null || $suhu > $suhuMax) {
        $suhuMax = $suhu;
    }

    if ($suhuMin === null || $suhu < $suhuMin) {
        $suhuMin = $suhu;
    }
}

$rataSuhu = $totalData ? $totalSuhu / $totalData : 0;
$rataKelembapan = $totalData ? $totalKelembapan / $totalData : 0;
$rataAngin = $totalData ? $totalAngin / $totalData : 0;

/* ================= PRAKIRAAN TERDEKAT ================= */

$terbaru = [];

if (!empty($data)) {
    $sekarang = time();

    foreach ($data as $item) {
        $waktuItem = strtotime(($item['tanggal'] ?? '') . ' ' . ($item['waktu'] ?? ''));

        if ($waktuItem !== false && $waktuItem >= $sekarang) {
            $terbaru = $item;
            break;
        }
    }

    if (empty($terbaru)) {
        $terbaru = end($data);
    }
}

/* ================= FUNGSI ICON CUACA ================= */

function iconCuaca($kondisi)
{
    $kondisi = strtolower((string)$kondisi);

    if (strpos($kondisi, 'petir') !== false) return "⛈️";
    if (strpos($kondisi, 'hujan lebat') !== false) return "🌧️";
    if (strpos($kondisi, 'hujan sedang') !== false) return "🌧️";
    if (strpos($kondisi, 'hujan ringan') !== false) return "🌦️";
    if (strpos($kondisi, 'hujan') !== false) return "🌧️";
    if (strpos($kondisi, 'berawan') !== false) return "☁️";
    if (strpos($kondisi, 'mendung') !== false) return "☁️";
    if (strpos($kondisi, 'cerah') !== false) return "☀️";

    return "🌤️";
}

/* ================= HALAMAN AKTIF ================= */

$currentPage = basename($_SERVER['PHP_SELF']);

function menuAktif($halaman, $currentPage)
{
    return $halaman === $currentPage ? "active" : "";
}

/*
 * Menandai dropdown Atmosfer aktif saat salah satu halaman
 * di dalamnya sedang dibuka, termasuk Pemantauan Kota.
 */
$halamanAtmosfer = [
    'inarcm.php',
    'inawms.php',
    'city.php',
    'galeri-produk.php'
];

$atmosferAktif = in_array(
    $currentPage,
    $halamanAtmosfer,
    true
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Dasbor Pemantauan Cuaca | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --bg: #eef3f9;
    --bg-soft: #eef3f9;
    --surface: #ffffff;
    --surface-hover: #f5f9fd;
    --line: rgba(35,70,110,0.208);
    --text: #16293f;
    --muted: #5c748c;
    --blue: #0f61bd;
    --cyan: #10a6bc;
    --green: #24a873;
    --radius: 20px;
    --shadow: 0 16px 40px rgba(16,42,67,0.048);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    min-height: 100vh;
    font-family: 'Inter', Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(ellipse at 8% 0%, rgba(37,140,255,.13), transparent 32%),
        radial-gradient(ellipse at 95% 20%, rgba(62,216,239,.07), transparent 30%),
        var(--bg);
}

a {
    color: inherit;
    text-decoration: none;
}

button {
    font: inherit;
}

button, a {
    -webkit-tap-highlight-color: transparent;
}

/* ================ KONTEN ================ */

.main {
    width: min(1500px, 100%);
    margin: 0 auto;
    padding: 30px 4% 55px;
}

.page-hero {
    position: relative;
    overflow: hidden;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 34px;
    border: 1px solid rgba(15,101,189,0.200);
    border-radius: 25px;
    background:
        radial-gradient(circle at 85% 20%, rgba(62,216,239,.18), transparent 25%),
        linear-gradient(120deg, #f5f9fd, #ffffff 62%, #f5f9fd);
    box-shadow: var(--shadow);
}

.page-hero::after {
    content: "";
    position: absolute;
    width: 250px;
    height: 250px;
    right: -85px;
    bottom: -170px;
    border: 1px solid rgba(15,124,189,0.220);
    border-radius: 50%;
    box-shadow:
        0 0 0 30px rgba(15,124,189,0.035),
        0 0 0 65px rgba(15,124,189,0.025);
    pointer-events: none;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 13px;
    color: #11659c;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 12px rgba(66,214,155,.7);
}

.page-hero h1 {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(23px, 3vw, 34px);
    line-height: 1.35;
    letter-spacing: -.7px;
}

.page-hero p {
    margin-top: 10px;
    color: #5c748c;
    font-size: 13px;
    line-height: 1.8;
}

.hero-chip {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    border: 1px solid rgba(23,52,86,0.055);
    border-radius: 14px;
    color: #16293f;
    background: rgba(23,52,86,0.030);
    font-size: 12px;
    white-space: nowrap;
}

.hero-chip i {
    color: var(--cyan);
    font-size: 18px;
}

/* ================ STATISTIK ================ */

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin: 30px 0 16px;
}

.section-heading h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 17px;
    font-weight: 700;
}

.section-heading p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
}

.section-tag {
    padding: 8px 11px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: #23598a;
    background: rgba(23,52,86,0.030);
    font-size: 10px;
    white-space: nowrap;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.stat-card {
    position: relative;
    overflow: hidden;
    min-width: 0;
    padding: 22px;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: linear-gradient(145deg, #ffffff, #eef3f9);
    box-shadow: var(--shadow);
    transition: transform .25s, border-color .25s;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(15,114,189,0.350);
}

.stat-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.stat-icon {
    width: 46px;
    height: 46px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(15,105,189,0.170);
    border-radius: 14px;
    color: #115f9c;
    background: rgba(37,140,255,.12);
    font-size: 19px;
}

.stat-label {
    margin-top: 20px;
    color: #5c748c;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .6px;
    text-transform: uppercase;
}

.stat-value {
    margin-top: 8px;
    color: #16293f;
    font-family: 'Poppins', sans-serif;
    font-size: clamp(20px, 2vw, 28px);
    font-weight: 700;
    overflow-wrap: anywhere;
}

.stat-value span {
    color: #5c748c;
    font-family: 'Inter', sans-serif;
    font-size: 12px;
    font-weight: 500;
}

.stat-foot {
    margin-top: 12px;
    color: #5c748c;
    font-size: 11px;
}

/* ================ KARTU CUACA ================ */

.panel {
    margin-top: 25px;
    padding: 25px;
    border: 1px solid var(--line);
    border-radius: 23px;
    background: linear-gradient(145deg, rgba(255,255,255,0.94), rgba(255,255,255,0.94));
    box-shadow: var(--shadow);
}

.panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 21px;
}

.panel-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.panel-title-icon {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(62,216,239,.14);
    border-radius: 13px;
    color: var(--cyan);
    background: rgba(62,216,239,.08);
    font-size: 17px;
}

.panel-title h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 16px;
}

.panel-title p {
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
}

.status-pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 12px;
    border: 1px solid rgba(66,214,155,.17);
    border-radius: 30px;
    color: #1b935d;
    background: rgba(66,214,155,.07);
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.weather-banner {
    position: relative;
    overflow: hidden;
    display: flex;
    align-items: center;
    gap: 23px;
    padding: 26px;
    border: 1px solid rgba(15,108,189,0.180);
    border-radius: 20px;
    background:
        radial-gradient(circle at 90% 0%, rgba(62,216,239,.17), transparent 28%),
        linear-gradient(115deg, #eaf2fa, #dbe5f0 55%, #f5f9fd);
}

.weather-symbol {
    flex: 0 0 76px;
    width: 76px;
    height: 76px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(23,52,86,0.072);
    border-radius: 21px;
    background: rgba(23,52,86,0.044);
    font-size: 43px;
}

.weather-condition {
    font-family: 'Poppins', sans-serif;
    font-size: clamp(19px, 2.5vw, 27px);
    font-weight: 700;
}

.weather-date {
    margin-top: 9px;
    color: #285986;
    font-size: 12px;
}

.weather-details {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 14px;
}

.weather-item {
    min-width: 0;
    padding: 17px;
    border: 1px solid var(--line);
    border-radius: 15px;
    background: rgba(23,52,86,0.030);
    transition: .2s;
}

.weather-item:hover {
    border-color: rgba(62,216,239,.23);
    background: rgba(62,216,239,.035);
}

.weather-item span {
    display: block;
    color: #5c748c;
    font-size: 10px;
    font-weight: 600;
    letter-spacing: .5px;
    text-transform: uppercase;
}

.weather-item strong {
    display: block;
    margin-top: 9px;
    color: #16293f;
    font-family: 'Poppins', sans-serif;
    font-size: 16px;
    overflow-wrap: anywhere;
}

/* ================ GALERI ================ */

.gallery-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

.gallery-card {
    overflow: hidden;
    min-width: 0;
    border: 1px solid var(--line);
    border-radius: 17px;
    background: #eef3f9;
    transition: transform .25s, border-color .25s;
}

.gallery-card:hover {
    transform: translateY(-4px);
    border-color: rgba(62,216,239,.3);
}

.gallery-image-wrap {
    position: relative;
    overflow: hidden;
    height: 195px;
    background: #ffffff;
}

.gallery-image-wrap img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    cursor: zoom-in;
    transition: transform .4s;
}

.gallery-card:hover img {
    transform: scale(1.045);
}

.gallery-image-wrap::after {
    content: "\f00e";
    position: absolute;
    right: 12px;
    top: 12px;
    width: 33px;
    height: 33px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(23,52,86,0.110);
    border-radius: 10px;
    color: #16293f;
    background: rgba(255,255,255,0.94);
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
    pointer-events: none;
}

.gallery-name {
    padding: 14px;
    color: #16293f;
    font-size: 12px;
    font-weight: 600;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.gallery-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 13px;
    border: 1px solid rgba(37,140,255,.2);
    border-radius: 11px;
    color: #115d9c;
    background: rgba(37,140,255,.08);
    font-size: 11px;
    font-weight: 700;
    transition: .2s;
}

.gallery-link:hover {
    color: #16293f;
    background: rgba(37,140,255,.2);
}

.empty {
    padding: 32px 20px;
    border: 1px dashed #ced8e3;
    border-radius: 15px;
    color: #5c748c;
    background: rgba(23,52,86,0.030);
    text-align: center;
    font-size: 13px;
    line-height: 1.9;
}

.empty i {
    display: block;
    margin-bottom: 12px;
    color: #11569c;
    font-size: 27px;
}

/* ================ FOOTER ================ */

.footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--line);
    color: #5c748c;
    font-size: 11px;
    line-height: 1.7;
}

.footer strong {
    color: #5c748c;
}

/* ================ MODAL FOTO ================ */

.modal {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 25px;
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(12px);
}

.modal.show {
    display: flex;
}

.modal img {
    max-width: 95%;
    max-height: 86vh;
    object-fit: contain;
    border: 1px solid rgba(23,52,86,0.083);
    border-radius: 13px;
    box-shadow: 0 25px 90px rgba(16,42,67,0.110);
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 25px;
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(23,52,86,0.083);
    border-radius: 50%;
    color: #16293f;
    background: rgba(23,52,86,0.044);
    font-size: 24px;
    cursor: pointer;
}

/* ================ RESPONSIVE ================ */

@media (max-width: 1250px) {

    .main {
        padding-left: 2.5%;
        padding-right: 2.5%;
    }
}

@media (max-width: 1000px) {

    .stats-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .weather-details {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .main {
        padding-top: 18px;
        padding-bottom: 35px;
    }

    .page-hero {
        align-items: flex-start;
        flex-direction: column;
        padding: 23px 20px;
        border-radius: 20px;
    }

    .hero-chip {
        padding: 10px 12px;
    }

    .section-heading {
        align-items: flex-start;
    }

    .section-tag {
        display: none;
    }

    .stats-grid {
        gap: 10px;
    }

    .stat-card {
        padding: 15px;
        border-radius: 16px;
    }

    .stat-icon {
        width: 39px;
        height: 39px;
        font-size: 16px;
    }

    .stat-label {
        margin-top: 14px;
        font-size: 9px;
        line-height: 1.5;
    }

    .stat-value {
        font-size: 20px;
    }

    .stat-foot {
        font-size: 10px;
    }

    .panel {
        padding: 17px;
        border-radius: 19px;
    }

    .panel-heading {
        align-items: flex-start;
    }

    .panel-title h2 {
        font-size: 14px;
    }

    .status-pill {
        padding: 8px;
        font-size: 9px;
    }

    .weather-banner {
        align-items: flex-start;
        gap: 14px;
        padding: 18px;
    }

    .weather-symbol {
        flex-basis: 54px;
        width: 54px;
        height: 54px;
        border-radius: 15px;
        font-size: 30px;
    }

    .weather-condition {
        font-size: 18px;
    }

    .weather-date {
        line-height: 1.7;
        overflow-wrap: anywhere;
    }

    .weather-item {
        padding: 13px;
    }

    .weather-item span {
        font-size: 9px;
    }

    .weather-item strong {
        font-size: 14px;
    }

    .gallery-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .gallery-image-wrap {
        height: 135px;
    }

    .gallery-name {
        padding: 11px;
        font-size: 11px;
    }

    .footer {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (max-width: 360px) {
    .stats-grid,
    .weather-details,
    .gallery-grid {
        grid-template-columns: 1fr;
    }

    .gallery-image-wrap {
        height: 190px;
    }

    .weather-banner {
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation: none !important;
        transition: none !important;
    }
}

@media print {
    body {
        color: #172033 !important;
        background: #fff !important;
    }
    .hero-chip,
    .gallery-link,
    .modal{
        display: none !important;
    }

    .main {
        width: 100%;
        padding: 0;
    }

    .page-hero,
    .stat-card,
    .panel,
    .weather-banner,
    .weather-item,
    .gallery-card {
        color: #172033 !important;
        background: #fff !important;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }

    .page-hero h1,
    .page-hero p,
    .stat-value,
    .weather-condition,
    .weather-item strong,
    .gallery-name,
    .section-heading h2,
    .panel-title h2 {
        color: #172033 !important;
    }

    .stat-label,
    .stat-foot,
    .weather-date,
    .weather-item span {
        color: #555 !important;
    }
}

/* ================ LOKASI & STATUS DATA ================ */

.location-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
    flex-wrap: wrap;
    margin-bottom: 22px;
    padding: 18px 20px;
    border: 1px solid var(--line);
    border-radius: 16px;
    background: linear-gradient(135deg, rgba(37,140,255,.10), rgba(255,255,255,0.94));
}

.location-main {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    min-width: 0;
}

.location-main > i {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    flex: 0 0 auto;
    color: var(--cyan);
    background: rgba(62,216,239,.10);
    border: 1px solid rgba(62,216,239,.22);
}

.location-label {
    display: block;
    color: var(--muted);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.location-main strong {
    display: block;
    margin: 3px 0 4px;
    font-family: 'Poppins', sans-serif;
    font-size: 17px;
    line-height: 1.35;
}

.location-main small {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.6;
}

.location-actions {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
}

.location-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    min-height: 40px;
    padding: 10px 14px;
    border: 1px solid #ced8e3;
    border-radius: 10px;
    color: #16293f;
    background: #eaf2fa;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition: .2s;
}

.location-btn:hover {
    border-color: var(--blue);
    background: #dbe5f0;
}

.location-btn.secondary {
    background: #ffffff;
}

.data-warning {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 0 0 22px;
    padding: 14px 16px;
    border: 1px solid #e3d2ce;
    border-radius: 13px;
    color: #16293f;
    background: #ffffff;
    font-size: 12px;
    line-height: 1.7;
}

@media (max-width: 640px) {
    .location-bar {
        align-items: flex-start;
    }

    .location-actions,
    .location-btn {
        width: 100%;
    }
}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=4">
</head>

<body>


<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- ================= KONTEN DASHBOARD ================= -->

<main class="main">

    <section class="page-hero">

        <div>

            <div class="eyebrow">
                <span class="live-dot"></span>
                Pusat Monitoring Cuaca
            </div>

            <h1>Dasbor Pemantauan Cuaca</h1>

            <p>
                BMKG Kemayoran · Ringkasan pengamatan cuaca dan kondisi atmosfer.<br>
                Pantau data cuaca melalui satu dashboard terpadu.
            </p>

        </div>

    </section>

    <!-- ================= LOKASI & SUMBER DATA ================= -->

    <section class="location-bar" aria-label="Lokasi prakiraan cuaca">

        <div class="location-main">
            <i class="fa-solid fa-location-dot"></i>

            <div>
                <span class="location-label">Lokasi prakiraan</span>
                <strong>
                    <?= htmlspecialchars(
                        bmkg_ringkas_lokasi($lokasiAktif),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
                <small>
                    ADM4 <?= htmlspecialchars($lokasiAktif['adm4'], ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars(bmkg_label_sumber($sumberCuaca), ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($waktuAmbilCuaca): ?>
                        · diperbarui <?= htmlspecialchars(bmkg_waktu_ambil($waktuAmbilCuaca), ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </small>
            </div>
        </div>

        <div class="location-actions">
            <button type="button" class="location-btn" data-deteksi-lokasi>
                <i class="fa-solid fa-crosshairs"></i>
                Deteksi Lokasi Saya
            </button>

            <a class="location-btn secondary" href="prediksi.php">
                <i class="fa-solid fa-list"></i>
                Pilih Manual
            </a>
        </div>

    </section>

    <?php if ($galatCuaca !== '' && empty($data)): ?>
        <div class="data-warning">
            <i class="fa-solid fa-triangle-exclamation"></i>
            Data prakiraan BMKG belum dapat dimuat: <?= htmlspecialchars($galatCuaca, ENT_QUOTES, 'UTF-8') ?>.
            Periksa koneksi internet, lalu muat ulang halaman.
        </div>
    <?php endif; ?>

    <!-- ================= STATISTIK ================= -->

    <section aria-label="Statistik cuaca">

        <div class="section-heading">

            <div>
                <h2>Ringkasan Statistik</h2>
                <p>Perhitungan dari prakiraan resmi BMKG untuk lokasi di atas.</p>
            </div>

            <span class="section-tag">
                <i class="fa-solid fa-database"></i>
                <?= number_format($totalData) ?> DATA TERCATAT
            </span>

        </div>

        <div class="stats-grid">

            <article class="stat-card">

                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-temperature-half"></i>
                    </div>

                    <i class="fa-solid fa-arrow-trend-up" style="color:#115c9c"></i>
                </div>

                <div class="stat-label">Rata-rata Suhu</div>

                <div class="stat-value">
                    <?= number_format($rataSuhu, 1) ?>
                    <span>°C</span>
                </div>

                <div class="stat-foot">
                    Suhu seluruh data pengamatan
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-droplet"></i>
                    </div>

                    <i class="fa-solid fa-water" style="color:#12879c"></i>
                </div>

                <div class="stat-label">Rata-rata Kelembapan</div>

                <div class="stat-value">
                    <?= number_format($rataKelembapan, 1) ?>
                    <span>%</span>
                </div>

                <div class="stat-foot">
                    Kelembapan udara rata-rata
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-wind"></i>
                    </div>

                    <i class="fa-solid fa-arrow-right-long" style="color:#113a9c"></i>
                </div>

                <div class="stat-label">Rata-rata Angin</div>

                <div class="stat-value">
                    <?= number_format($rataAngin, 1) ?>
                    <span>km/jam</span>
                </div>

                <div class="stat-foot">
                    Kecepatan angin rata-rata
                </div>

            </article>

            <article class="stat-card">

                <div class="stat-top">
                    <div class="stat-icon">
                        <i class="fa-solid fa-cloud-rain"></i>
                    </div>

                    <i class="fa-solid fa-chart-column" style="color:#1a9370"></i>
                </div>

                <div class="stat-label">Total Curah Hujan</div>

                <div class="stat-value">
                    <?= number_format($totalHujan, 1) ?>
                    <span>mm</span>
                </div>

                <div class="stat-foot">
                    Akumulasi nilai dari seluruh data
                </div>

            </article>

        </div>
    </section>

    <!-- ================= CUACA TERBARU ================= -->

    <section class="panel">

        <div class="panel-heading">

            <div class="panel-title">

                <div class="panel-title-icon">
                    <i class="fa-solid fa-cloud-sun-rain"></i>
                </div>

                <div>
                    <h2>Prakiraan Terdekat</h2>
                    <p>Periode prakiraan resmi BMKG yang paling dekat dengan waktu sekarang.</p>
                </div>

            </div>

            <div class="status-pill">
                <span class="live-dot"></span>
                DATA TERBARU
            </div>

        </div>

        <?php if (!empty($terbaru)): ?>

            <div class="weather-banner">

                <div class="weather-symbol">
                    <?= iconCuaca($terbaru['kondisi_cuaca'] ?? '') ?>
                </div>

                <div>

                    <div class="weather-condition">
                        <?= htmlspecialchars(
                            (string)($terbaru['kondisi_cuaca'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                    <div class="weather-date">
                        <i class="fa-regular fa-calendar"></i>

                        <?= htmlspecialchars(
                            (string)($terbaru['tanggal'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                        &nbsp; · &nbsp;

                        <i class="fa-regular fa-clock"></i>

                        <?= htmlspecialchars(
                            (string)($terbaru['waktu'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                </div>

            </div>

            <div class="weather-details">

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-temperature-half"></i>
                        Suhu
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            (string)($terbaru['suhu'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?> °C
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-droplet"></i>
                        Kelembapan
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            (string)($terbaru['kelembapan'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?> %
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-cloud-rain"></i>
                        Curah Hujan
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            (string)($terbaru['curah_hujan'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?> mm
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-wind"></i>
                        Kecepatan Angin
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            (string)($terbaru['kecepatan_angin'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?> km/jam
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-compass"></i>
                        Arah Angin
                    </span>

                    <strong>
                        <?= htmlspecialchars(
                            (string)($terbaru['arah_angin'] ?? '-'),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-temperature-arrow-up"></i>
                        Suhu Maksimum
                    </span>

                    <strong>
                        <?= $suhuMax !== null
                            ? number_format($suhuMax, 1) . " °C"
                            : "-" ?>
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-temperature-arrow-down"></i>
                        Suhu Minimum
                    </span>

                    <strong>
                        <?= $suhuMin !== null
                            ? number_format($suhuMin, 1) . " °C"
                            : "-" ?>
                    </strong>
                </div>

                <div class="weather-item">
                    <span>
                        <i class="fa-solid fa-database"></i>
                        Total Data
                    </span>

                    <strong>
                        <?= number_format($totalData) ?> Data
                    </strong>
                </div>

            </div>

        <?php else: ?>

            <div class="empty">
                <i class="fa-solid fa-cloud"></i>
                Data prakiraan BMKG belum tersedia.<br>
                Periksa koneksi internet lalu muat ulang halaman, atau pilih lokasi lain melalui menu
                <strong>Prakiraan Cuaca</strong>.
            </div>

        <?php endif; ?>

    </section>

    <footer class="footer">

        <div>
            <strong>BMKG KEMAYORAN</strong><br>
            Sistem Pemantauan Data Cuaca
        </div>

        <div>
            Dasbor Pemantauan · Sumber: prakiraan resmi BMKG
        </div>

    </footer>

</main>

<!-- ================= MODAL GAMBAR ================= -->

<div
    class="modal"
    id="imageModal"
    role="dialog"
    aria-modal="true"
    aria-label="Pratinjau gambar"
    onclick="closeModal()"
>

    <button
        class="modal-close"
        type="button"
        aria-label="Tutup gambar"
        onclick="closeModal()"
    >
        <i class="fa-solid fa-xmark"></i>
    </button>

    <img
        id="modalImage"
        src=""
        alt="Pratinjau gambar"
    >

</div>

<script>
</script>

<script src="assets/lokasi-otomatis.js" data-auto="1"></script>

</body>
</html>
