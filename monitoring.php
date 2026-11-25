<?php
/* =========================================================
   MONITORING CUACA BMKG KEMAYORAN
   Tema: Dark Premium + Navbar Horizontal
   Data: data/cuaca.json
   Tanpa database
   Seluruh data dan fitur monitoring dipertahankan
   Penambahan: Menu Pemantauan Kota
========================================================= */

require_once __DIR__ . '/includes/bmkg_data.php';

$hasilCuaca = bmkg_muat_cuaca();
$data = $hasilCuaca['records'];
$lokasiAktif = $hasilCuaca['lokasi'];
$sumberCuaca = $hasilCuaca['sumber'];
$waktuAmbilCuaca = $hasilCuaca['diambil'];
$galatCuaca = $hasilCuaca['error'];

$totalData = count($data);
$terbaru = !empty($data) ? end($data) : [];

$tanggal = $terbaru['tanggal'] ?? '-';
$waktu = $terbaru['waktu'] ?? '-';
$suhu = $terbaru['suhu'] ?? '-';
$kelembapan = $terbaru['kelembapan'] ?? '-';
$curahHujan = $terbaru['curah_hujan'] ?? '-';
$kecepatanAngin = $terbaru['kecepatan_angin'] ?? '-';
$kondisiCuaca = $terbaru['kondisi_cuaca'] ?? 'Belum ada data';
$arahAngin = $terbaru['arah_angin'] ?? '-';

$currentPage = basename($_SERVER['PHP_SELF']);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function menuAktif($halaman, $currentPage) {
    return $halaman === $currentPage ? 'active' : '';
}

function iconCuaca($kondisi) {
    $kondisi = strtolower((string)$kondisi);

    if (strpos($kondisi, 'petir') !== false) return '⛈️';
    if (strpos($kondisi, 'hujan lebat') !== false) return '🌧️';
    if (strpos($kondisi, 'hujan sedang') !== false) return '🌧️';
    if (strpos($kondisi, 'hujan ringan') !== false) return '🌦️';
    if (strpos($kondisi, 'hujan') !== false) return '🌦️';
    if (strpos($kondisi, 'berawan') !== false) return '🌥️';
    if (strpos($kondisi, 'mendung') !== false) return '☁️';
    if (strpos($kondisi, 'cerah') !== false) return '☀️';

    return '🌤️';
}

function statusCuaca($kondisi) {
    $kondisi = strtolower((string)$kondisi);

    if (
        strpos($kondisi, 'petir') !== false ||
        strpos($kondisi, 'hujan lebat') !== false
    ) {
        return 'Waspada';
    }

    if (strpos($kondisi, 'hujan') !== false) return 'Hujan';

    if (
        strpos($kondisi, 'mendung') !== false ||
        strpos($kondisi, 'berawan') !== false
    ) {
        return 'Berawan';
    }

    return 'Normal';
}

function warnaStatus($kondisi) {
    $kondisi = strtolower((string)$kondisi);

    if (
        strpos($kondisi, 'petir') !== false ||
        strpos($kondisi, 'hujan lebat') !== false
    ) {
        return 'danger';
    }

    if (strpos($kondisi, 'hujan') !== false) return 'warning';

    if (
        strpos($kondisi, 'mendung') !== false ||
        strpos($kondisi, 'berawan') !== false
    ) {
        return 'cloud';
    }

    return 'normal';
}

/* ================= STATUS INDIKATOR ================= */

$nilaiAngin = is_numeric($kecepatanAngin)
    ? (float)$kecepatanAngin : 0;

$nilaiKelembapan = is_numeric($kelembapan)
    ? (float)$kelembapan : 0;

$nilaiHujan = is_numeric($curahHujan)
    ? (float)$curahHujan : 0;

if ($nilaiAngin >= 40) {
    $statusAngin = 'Kencang';
    $classAngin = 'danger';
} elseif ($nilaiAngin >= 20) {
    $statusAngin = 'Sedang';
    $classAngin = 'warning';
} else {
    $statusAngin = 'Normal';
    $classAngin = 'normal';
}

if ($nilaiKelembapan >= 80) {
    $statusKelembapan = 'Tinggi';
} elseif ($nilaiKelembapan >= 60) {
    $statusKelembapan = 'Normal';
} else {
    $statusKelembapan = 'Rendah';
}

if ($nilaiHujan > 20) {
    $statusHujan = 'Tinggi';
} elseif ($nilaiHujan > 5) {
    $statusHujan = 'Sedang';
} elseif ($nilaiHujan > 0) {
    $statusHujan = 'Ringan';
} else {
    $statusHujan = 'Tidak Ada';
}

$statusMonitoring = $totalData > 0 ? 'Aktif' : 'Tidak Ada Data';
$statusClass = $totalData > 0 ? 'online' : 'offline';

/* ================= DATA GRAFIK ================= */

$chartData = array_slice($data, -10);
$chartSuhu = [];
$chartKelembapan = [];
$chartLabel = [];

foreach ($chartData as $item) {
    $chartSuhu[] = (float)($item['suhu'] ?? 0);
    $chartKelembapan[] = (float)($item['kelembapan'] ?? 0);
    $chartLabel[] = (string)($item['waktu'] ?? '');
}

/* Pemantauan Kota ikut dianggap bagian dari menu Atmosfer. */
$halamanAtmosfer = in_array(
    $currentPage,
    ['inarcm.php', 'inawms.php', 'city.php', 'galeri-produk.php'],
    true
);

$halamanLainnya = in_array(
    $currentPage,
    ['info_bmkg.php', 'laporan.php'],
    true
);

$halamanProduk = in_array(
    $currentPage,
    ['prediksi.php', 'gempa.php'],
    true
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Monitoring Cuaca | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    --radius: 18px;
    --shadow: 0 16px 40px rgba(16,42,67,0.048);
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
    background: var(--bg);
    color-scheme: dark;
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

/* ================= KONTEN ================= */

.main {
    min-height: 100vh;
}

.content {
    width: min(1600px,100%);
    margin: 0 auto;
    padding: 28px 3% 20px;
}

.hero {
    position: relative;
    overflow: hidden;
    padding: 30px;
    border: 1px solid rgba(15,101,189,0.200);
    border-radius: 23px;
    background:
        radial-gradient(circle at 85% 20%,rgba(62,216,239,.15),transparent 25%),
        linear-gradient(120deg,#f5f9fd,#ffffff 62%,#f5f9fd);
    box-shadow: var(--shadow);
    margin-bottom: 24px;
}

.hero:after {
    content: "🌦️";
    position: absolute;
    right: 45px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 80px;
    opacity: .2;
    pointer-events: none;
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #11659c;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.6px;
    text-transform: uppercase;
}

.hero h1 {
    position: relative;
    z-index: 1;
    font: 800 clamp(23px,3vw,31px) 'Poppins',sans-serif;
    margin: 11px 0;
}

.hero p {
    position: relative;
    z-index: 1;
    color: #5c748c;
    font-size: 12px;
    line-height: 1.9;
    max-width: 680px;
}

.hero-meta {
    display: flex;
    gap: 9px;
    flex-wrap: wrap;
    margin-top: 18px;
}

.pill {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 9px 12px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: #16293f;
    background: rgba(23,52,86,0.030);
    font-size: 10px;
}

.section-title {
    font: 700 17px 'Poppins',sans-serif;
    margin: 26px 0 5px;
}

.section-subtitle {
    margin: 0 0 17px;
    color: var(--muted);
    font-size: 11px;
}

/* ================= KARTU STATISTIK ================= */

.cards {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 15px;
}

.card,
.box,
.chart-box {
    min-width: 0;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: linear-gradient(145deg,#ffffff,#eef3f9);
    box-shadow: var(--shadow);
}

.card {
    position: relative;
    overflow: hidden;
    padding: 20px;
    transition: transform .25s,border-color .25s;
}

.card:hover {
    transform: translateY(-4px);
    border-color: rgba(15,114,189,0.350);
}

.card:after {
    content: "";
    position: absolute;
    right: -25px;
    top: -30px;
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(53,169,255,.05);
    pointer-events: none;
}

.card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.card-label {
    color: #5c748c;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .6px;
    text-transform: uppercase;
}

.card-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(15,105,189,0.170);
    border-radius: 12px;
    color: #115f9c;
    background: rgba(37,140,255,.12);
    font-size: 19px;
}

.card-value {
    margin: 18px 0 5px;
    color: #16293f;
    font: 800 clamp(21px,2.2vw,27px) 'Poppins',sans-serif;
    overflow-wrap: anywhere;
}

.card-unit {
    color: #5c748c;
    font: 500 12px 'Inter',sans-serif;
}

.card-foot {
    margin-top: 12px;
    color: #5c748c;
    font-size: 10px;
    line-height: 1.6;
}

.card-foot strong {
    color: #295885;
}

/* ================= PANEL ================= */

.columns {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 17px;
    margin-top: 19px;
}

.box {
    padding: 22px;
}

.box-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 18px;
}

.box-header h2 {
    font: 700 14px 'Poppins',sans-serif;
}

.box-header p {
    margin-top: 6px;
    color: var(--muted);
    font-size: 10px;
    line-height: 1.7;
}

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 6px 9px;
    border-radius: 7px;
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

.normal,
.online {
    color: #1a936a;
    background: rgba(36,214,154,.10);
}

.warning {
    color: #9c6811;
    background: rgba(189,125,15,0.100);
}

.danger,
.offline {
    color: #9c1123;
    background: rgba(189,15,38,0.100);
}

.cloud {
    color: #115b9c;
    background: rgba(53,169,255,.10);
}

.status-panel {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 17px;
    border: 1px solid var(--line);
    border-radius: 13px;
    background: rgba(23,52,86,0.030);
}

.status-symbol {
    flex-shrink: 0;
    font-size: 35px;
}

.status-panel h3 {
    font: 700 14px 'Poppins',sans-serif;
    margin-bottom: 6px;
}

.status-panel p {
    color: var(--muted);
    font-size: 11px;
    line-height: 1.7;
}

.status-list {
    display: grid;
    gap: 0;
    margin-top: 15px;
}

.status-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(23,52,86,0.030);
    font-size: 11px;
}

.status-row:last-child {
    border: 0;
}

.status-row span:first-child {
    color: #5c748c;
}

/* ================= DETAIL OBSERVASI ================= */

.details {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 10px;
}

.detail-item {
    min-width: 0;
    padding: 14px;
    border: 1px solid rgba(23,52,86,0.039);
    border-radius: 11px;
    background: rgba(23,52,86,0.030);
}

.detail-label {
    color: #5c748c;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .6px;
    line-height: 1.6;
}

.detail-value {
    margin-top: 9px;
    color: #16293f;
    font-size: 12px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* ================= KONDISI CUACA ================= */

.weather-main {
    padding: 15px 10px 22px;
    border-bottom: 1px solid var(--line);
    text-align: center;
}

.weather-icon {
    font-size: 53px;
}

.weather-condition {
    margin-top: 7px;
    font: 700 20px 'Poppins',sans-serif;
    overflow-wrap: anywhere;
}

.weather-date {
    margin-top: 7px;
    color: var(--muted);
    font-size: 10px;
}

.weather-status {
    display: inline-flex;
    margin-top: 13px;
    padding: 7px 12px;
    border-radius: 8px;
    font-size: 10px;
    font-weight: 800;
}

.indicators {
    display: grid;
    margin-top: 10px;
}

.indicator {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid rgba(23,52,86,0.030);
}

.indicator:last-child {
    border: 0;
}

.indicator-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.indicator-icon {
    width: 31px;
    height: 31px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: rgba(23,52,86,0.033);
}

.indicator-name {
    color: #5c748c;
    font-size: 11px;
}

.indicator-value {
    font-size: 11px;
    font-weight: 700;
    text-align: right;
    overflow-wrap: anywhere;
}

/* ================= GRAFIK ================= */

.chart-box {
    margin-top: 18px;
    padding: 22px;
}

.chart-container {
    position: relative;
    height: 290px;
    width: 100%;
}

.footer {
    padding: 25px 10px;
    color: #5c748c;
    font-size: 10px;
    line-height: 1.9;
    text-align: center;
}

.footer strong {
    color: #5c748c;
}

/* ================= RESPONSIVE ================= */

@media (max-width: 1250px) {

    .content {
        padding-left: 2%;
        padding-right: 2%;
    }
}

@media (max-width: 1000px) {

    .cards {
        grid-template-columns: repeat(2,minmax(0,1fr));
    }

    .columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 620px) {

    .content {
        padding: 17px 12px;
    }

    .hero {
        padding: 22px 19px;
        border-radius: 19px;
    }

    .hero:after {
        right: 14px;
        top: 22px;
        transform: none;
        font-size: 43px;
    }

    .hero h1 {
        max-width: 80%;
        font-size: 22px;
    }

    .hero p {
        font-size: 11px;
    }

    .hero-meta {
        gap: 7px;
    }

    .pill {
        padding: 8px 9px;
        font-size: 9px;
    }

    .cards {
        gap: 10px;
    }

    .card {
        padding: 14px;
        border-radius: 15px;
    }

    .card-label {
        font-size: 9px;
        line-height: 1.6;
    }

    .card-value {
        font-size: 20px;
    }

    .card-icon {
        width: 33px;
        height: 33px;
        font-size: 16px;
    }

    .card-foot {
        font-size: 9px;
    }

    .box,
    .chart-box {
        padding: 15px;
    }

    .box-header h2 {
        font-size: 13px;
    }

    .details {
        grid-template-columns: repeat(2,minmax(0,1fr));
        gap: 8px;
    }

    .detail-item {
        padding: 10px;
    }

    .detail-value {
        font-size: 11px;
    }

    .chart-container {
        height: 250px;
    }

    .status-row {
        font-size: 10px;
    }
}

@media (max-width: 360px) {
    .cards {
        grid-template-columns: 1fr;
    }

    .details {
        grid-template-columns: 1fr;
    }

    .hero h1 {
        max-width: 100%;
    }

    .hero:after {
        display: none;
    }
}

/* ================= MODE CETAK ================= */

@media print {
    body {
        color: #172033 !important;
        background: #fff !important;
    }

    .main {
        margin: 0;
    }

    .content {
        width: 100%;
        padding: 0;
    }

    .hero,
    .card,
    .box,
    .chart-box,
    .status-panel,
    .detail-item {
        color: #172033 !important;
        background: #fff !important;
        box-shadow: none !important;
        border: 1px solid #ddd !important;
        break-inside: avoid;
    }

    .hero h1,
    .hero p,
    .card-value,
    .box-header h2,
    .detail-value,
    .weather-condition,
    .status-panel h3 {
        color: #172033 !important;
    }

    .card-label,
    .card-foot,
    .section-subtitle,
    .box-header p,
    .detail-label,
    .indicator-name,
    .status-row span:first-child,
    .weather-date {
        color: #555 !important;
    }

    .chart-container {
        break-inside: avoid;
    }
}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=4">
</head>

<body>

<!-- ================= NAVBAR HORIZONTAL ================= -->

<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- ================= KONTEN MONITORING ================= -->

<main class="main">
<div class="content">

    <!-- HERO -->

    <section class="hero">
        <div class="eyebrow">
            <span>●</span>
            BMKG · SISTEM MONITORING
        </div>

        <h1>Monitoring Cuaca Kemayoran</h1>

        <p>
            Pantau data observasi cuaca yang tersimpan pada sistem.
            Informasi suhu, kelembapan, curah hujan, dan angin ditampilkan
            berdasarkan data terbaru di dalam berkas JSON.
        </p>

        <div class="hero-meta">
            <span class="pill">
                <i class="fa-regular fa-calendar"></i>
                <?= e($tanggal) ?>
            </span>

            <span class="pill">
                <i class="fa-regular fa-clock"></i>
                <?= e($waktu) ?>
            </span>

            <span class="pill">
                <i class="fa-solid fa-database"></i>
                <?= number_format($totalData, 0, ',', '.') ?> data tersimpan
            </span>
        </div>
    </section>

    <!-- RINGKASAN OBSERVASI -->

    <h2 class="section-title">Ringkasan Observasi</h2>
    <p class="section-subtitle">
        Informasi utama dari pengamatan cuaca terbaru.
    </p>

    <section class="cards">

        <article class="card">
            <div class="card-top">
                <div class="card-label">Suhu Udara</div>
                <div class="card-icon">🌡️</div>
            </div>

            <div class="card-value">
                <?= e($suhu) ?>
                <span class="card-unit">°C</span>
            </div>

            <div class="card-foot">
                Suhu pada observasi terakhir
            </div>
        </article>

        <article class="card">
            <div class="card-top">
                <div class="card-label">Kelembapan</div>
                <div class="card-icon">💧</div>
            </div>

            <div class="card-value">
                <?= e($kelembapan) ?>
                <span class="card-unit">%</span>
            </div>

            <div class="card-foot">
                Status: <strong><?= e($statusKelembapan) ?></strong>
            </div>
        </article>

        <article class="card">
            <div class="card-top">
                <div class="card-label">Curah Hujan</div>
                <div class="card-icon">🌧️</div>
            </div>

            <div class="card-value">
                <?= e($curahHujan) ?>
                <span class="card-unit">mm</span>
            </div>

            <div class="card-foot">
                Intensitas: <strong><?= e($statusHujan) ?></strong>
            </div>
        </article>

        <article class="card">
            <div class="card-top">
                <div class="card-label">Kecepatan Angin</div>
                <div class="card-icon">💨</div>
            </div>

            <div class="card-value">
                <?= e($kecepatanAngin) ?>
                <span class="card-unit">km/jam</span>
            </div>

            <div class="card-foot">
                Status: <strong><?= e($statusAngin) ?></strong>
            </div>
        </article>

    </section>

    <!-- STATUS DAN DETAIL -->

    <section class="columns">

        <article class="box">
            <div class="box-header">
                <div>
                    <h2>Status Monitoring</h2>
                    <p>Ringkasan status sistem dan indikator cuaca.</p>
                </div>

                <span class="badge <?= e($statusClass) ?>">
                    <?= e($statusMonitoring) ?>
                </span>
            </div>

            <div class="status-panel">
                <div class="status-symbol">
                    <?= iconCuaca($kondisiCuaca) ?>
                </div>

                <div>
                    <h3><?= e($kondisiCuaca) ?></h3>
                    <p>
                        Observasi terakhir:
                        <?= e($tanggal) ?> · <?= e($waktu) ?>
                    </p>
                </div>
            </div>

            <div class="status-list">

                <div class="status-row">
                    <span>Status kondisi cuaca</span>
                    <span class="badge <?= e(warnaStatus($kondisiCuaca)) ?>">
                        <?= e(statusCuaca($kondisiCuaca)) ?>
                    </span>
                </div>

                <div class="status-row">
                    <span>Status angin</span>
                    <span class="badge <?= e($classAngin) ?>">
                        <?= e($statusAngin) ?>
                    </span>
                </div>

                <div class="status-row">
                    <span>Status kelembapan</span>
                    <span class="badge <?= $nilaiKelembapan >= 80 ? 'warning' : 'normal' ?>">
                        <?= e($statusKelembapan) ?>
                    </span>
                </div>

                <div class="status-row">
                    <span>Status curah hujan</span>
                    <span class="badge <?= $nilaiHujan > 20 ? 'danger' : ($nilaiHujan > 0 ? 'warning' : 'normal') ?>">
                        <?= e($statusHujan) ?>
                    </span>
                </div>

            </div>
        </article>

        <article class="box">
            <div class="box-header">
                <div>
                    <h2>Detail Observasi</h2>
                    <p>Rincian nilai dari data cuaca terbaru.</p>
                </div>
            </div>

            <div class="details">

                <div class="detail-item">
                    <div class="detail-label">TANGGAL OBSERVASI</div>
                    <div class="detail-value">
                        <i class="fa-regular fa-calendar"></i>
                        <?= e($tanggal) ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">WAKTU OBSERVASI</div>
                    <div class="detail-value">
                        <i class="fa-regular fa-clock"></i>
                        <?= e($waktu) ?>
                    </div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">SUHU UDARA</div>
                    <div class="detail-value">🌡️ <?= e($suhu) ?> °C</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">KELEMBAPAN</div>
                    <div class="detail-value">💧 <?= e($kelembapan) ?> %</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">CURAH HUJAN</div>
                    <div class="detail-value">🌧️ <?= e($curahHujan) ?> mm</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">KECEPATAN ANGIN</div>
                    <div class="detail-value">💨 <?= e($kecepatanAngin) ?> km/jam</div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">ARAH ANGIN</div>
                    <div class="detail-value">🧭 <?= e($arahAngin) ?></div>
                </div>

                <div class="detail-item">
                    <div class="detail-label">TOTAL DATA</div>
                    <div class="detail-value">
                        <i class="fa-solid fa-database"></i>
                        <?= number_format($totalData, 0, ',', '.') ?> data
                    </div>
                </div>

            </div>
        </article>

    </section>

    <!-- KONDISI CUACA TERBARU -->

    <section class="box" style="margin-top:18px">

        <div class="box-header">
            <div>
                <h2>Kondisi Cuaca Saat Ini</h2>
                <p>Ringkasan keadaan cuaca berdasarkan observasi terakhir.</p>
            </div>

            <span class="badge <?= e(warnaStatus($kondisiCuaca)) ?>">
                <?= e(statusCuaca($kondisiCuaca)) ?>
            </span>
        </div>

        <div class="weather-main">
            <div class="weather-icon">
                <?= iconCuaca($kondisiCuaca) ?>
            </div>

            <div class="weather-condition">
                <?= e($kondisiCuaca) ?>
            </div>

            <div class="weather-date">
                <?= e($tanggal) ?> · <?= e($waktu) ?>
            </div>

            <span class="weather-status <?= e(warnaStatus($kondisiCuaca)) ?>">
                <?= e(statusCuaca($kondisiCuaca)) ?>
            </span>
        </div>

        <div class="indicators">

            <div class="indicator">
                <div class="indicator-left">
                    <div class="indicator-icon">🌡️</div>
                    <div class="indicator-name">Suhu</div>
                </div>
                <div class="indicator-value"><?= e($suhu) ?> °C</div>
            </div>

            <div class="indicator">
                <div class="indicator-left">
                    <div class="indicator-icon">💧</div>
                    <div class="indicator-name">Kelembapan</div>
                </div>
                <div class="indicator-value"><?= e($kelembapan) ?> %</div>
            </div>

            <div class="indicator">
                <div class="indicator-left">
                    <div class="indicator-icon">🌧️</div>
                    <div class="indicator-name">Curah Hujan</div>
                </div>
                <div class="indicator-value"><?= e($curahHujan) ?> mm</div>
            </div>

            <div class="indicator">
                <div class="indicator-left">
                    <div class="indicator-icon">🧭</div>
                    <div class="indicator-name">Arah Angin</div>
                </div>
                <div class="indicator-value"><?= e($arahAngin) ?></div>
            </div>

            <div class="indicator">
                <div class="indicator-left">
                    <div class="indicator-icon">💨</div>
                    <div class="indicator-name">Kecepatan Angin</div>
                </div>
                <div class="indicator-value">
                    <?= e($kecepatanAngin) ?> km/jam
                    <span class="badge <?= e($classAngin) ?>">
                        <?= e($statusAngin) ?>
                    </span>
                </div>
            </div>

        </div>
    </section>

    <!-- GRAFIK SUHU DAN KELEMBAPAN -->

    <section class="chart-box">

        <div class="box-header">
            <div>
                <h2>Tren Monitoring Cuaca</h2>
                <p>
                    Grafik suhu dan kelembapan dari maksimal
                    10 observasi terakhir.
                </p>
            </div>
        </div>

        <div class="chart-container">
            <canvas id="weatherChart"></canvas>
        </div>

        <?php if (empty($chartData)): ?>
            <p style="margin-top:15px;color:#5c748c;font-size:12px;text-align:center">
                Belum ada data untuk ditampilkan dalam grafik.
                Periksa file data/cuaca.json.
            </p>
        <?php endif; ?>

    </section>

    <!-- FOOTER -->

    <footer class="footer">
        <strong>Dasbor Pemantauan Cuaca BMKG Kemayoran</strong><br>
        Sistem monitoring data observasi cuaca<br>
        © <?= date('Y') ?> BMKG Sistem Pemantauan
    </footer>

</div>
</main>

<script>

/* ================= GRAFIK MONITORING ================= */

const chartLabels = <?= json_encode(
    $chartLabel,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const chartSuhu = <?= json_encode($chartSuhu) ?>;
const chartKelembapan = <?= json_encode($chartKelembapan) ?>;

const canvas = document.getElementById('weatherChart');

if (canvas && typeof Chart !== 'undefined') {
    new Chart(canvas, {
        type: 'line',

        data: {
            labels: chartLabels,

            datasets: [
                {
                    label: 'Suhu °C',
                    data: chartSuhu,
                    borderColor: '#1573b8',
                    backgroundColor: 'rgba(53,169,255,.08)',
                    borderWidth: 3,
                    tension: .4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                },

                {
                    label: 'Kelembapan %',
                    data: chartKelembapan,
                    borderColor: '#0f9c8f',
                    backgroundColor: 'rgba(38,213,196,.06)',
                    borderWidth: 3,
                    tension: .4,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }
            ]
        },

        options: {
            responsive: true,
            maintainAspectRatio: false,

            interaction: {
                mode: 'index',
                intersect: false
            },

            plugins: {
                legend: {
                    position: 'top',

                    labels: {
                        usePointStyle: true,
                        padding: 18,
                        color: '#5c748c',

                        font: {
                            family: 'Inter',
                            size: 10,
                            weight: '600'
                        }
                    }
                },

                tooltip: {
                    backgroundColor: '#ffffff',
                    titleColor: '#16293f',
                    bodyColor: '#5c748c',
                    borderColor: 'rgba(151,181,215,.2)',
                    borderWidth: 1,
                    padding: 12
                }
            },

            scales: {
                x: {
                    grid: {
                        color: 'rgba(255,255,255,.035)'
                    },

                    ticks: {
                        color: '#5c748c',
                        font: {
                            size: 9
                        }
                    }
                },

                y: {
                    beginAtZero: false,

                    grid: {
                        color: 'rgba(255,255,255,.055)'
                    },

                    ticks: {
                        color: '#5c748c',
                        font: {
                            size: 9
                        }
                    }
                }
            }
        }
    });
}
</script>

</body>
</html>
