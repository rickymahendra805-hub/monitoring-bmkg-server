<?php
/* =========================================================
   INFO BMKG
   - Cuaca
   - Iklim
   - Gempa
   - Informasi BMKG
========================================================= */

require_once __DIR__ . '/includes/bmkg_data.php';

$hasilCuaca = bmkg_muat_cuaca();
$data = $hasilCuaca['records'];
$lokasiAktif = $hasilCuaca['lokasi'];
$sumberCuaca = $hasilCuaca['sumber'];
$galatCuaca = $hasilCuaca['error'];


/* =========================================================
   PRAKIRAAN TERDEKAT
========================================================= */

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


/* =========================================================
   DATA CUACA
========================================================= */

$suhu = $terbaru['suhu'] ?? '-';
$kelembapan = $terbaru['kelembapan'] ?? '-';
$kondisi = $terbaru['kondisi_cuaca'] ?? 'Belum tersedia';
$angin = $terbaru['kecepatan_angin'] ?? '-';
$arahAngin = $terbaru['arah_angin'] ?? '-';
$curahHujan = $terbaru['curah_hujan'] ?? '-';
$tanggal = $terbaru['tanggal'] ?? '-';
$waktu = $terbaru['waktu'] ?? '-';


/* =========================================================
   ICON CUACA
========================================================= */

function iconCuaca($kondisi)
{
    $kondisi = strtolower($kondisi);

    if (strpos($kondisi, 'petir') !== false) {
        return '⛈️';
    }

    if (strpos($kondisi, 'hujan lebat') !== false) {
        return '🌧️';
    }

    if (strpos($kondisi, 'hujan') !== false) {
        return '🌦️';
    }

    if (strpos($kondisi, 'mendung') !== false) {
        return '☁️';
    }

    if (strpos($kondisi, 'berawan') !== false) {
        return '🌥️';
    }

    if (strpos($kondisi, 'cerah') !== false) {
        return '☀️';
    }

    return '🌤️';
}

$icon = iconCuaca($kondisi);

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport"
   content="width=device-width, initial-scale=1.0">

<title>Informasi BMKG | Pemantauan BMKG</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<!-- =====================================================
     GOOGLE FONT
===================================================== -->

<link rel="preconnect"
      href="https://fonts.googleapis.com">

<link rel="preconnect"
      href="https://fonts.gstatic.com"
      crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
      rel="stylesheet">

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: #f4f7fb;
    color: #172033;
}


/* =========================================================
   SIDEBAR
========================================================= */

.sidebar {

    position: fixed;

    left: 0;
    top: 0;

    width: 255px;
    height: 100vh;

    background:
        linear-gradient(
            180deg,
            #ffffff 0%,
            #f2f7fc 48%,
            #eaf2fa 100%
        );

    color: #16293f;

    padding: 18px 13px;

    overflow-y: auto;

    z-index: 1000;

    box-shadow:
        5px 0 20px
        rgba(16,42,67,0.022);
}


/* Scrollbar */

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(23,52,86,0.030);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(23,52,86,0.120);
    border-radius: 10px;
}


/* =========================================================
   LOGO
========================================================= */

.logo-area {

    display: flex;

    align-items: center;

    gap: 11px;

    padding: 8px 9px 20px;

    margin-bottom: 5px;

    border-bottom:
        1px solid
        rgba(23,52,86,0.077);
}


.logo-bmkg {

    width: 44px;
    height: 44px;

    flex-shrink: 0;

    background: white;

    color: #005baa;

    border-radius: 50%;

    overflow: hidden;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 11px;

    font-weight: 800;

    letter-spacing: .3px;

    box-shadow:
        0 5px 15px
        rgba(16,42,67,0.018);
}


.logo-bmkg img {

    width: 100%;

    height: 100%;

    display: block;

    object-fit: contain;
}


.logo-text h2 {

    font-family: 'Poppins', sans-serif;

    font-size: 15px;

    line-height: 1.3;

    color: #16293f;
}


.logo-text span {

    display: block;

    margin-top: 3px;

    font-size: 9px;

    color:
        rgba(22,41,63,0.850);
}


/* =========================================================
   MENU TITLE
========================================================= */

.menu-title {

    display: flex;

    align-items: center;

    gap: 8px;

    margin:
        21px 9px 8px;

    font-size: 9px;

    text-transform: uppercase;

    letter-spacing: 1.3px;

    font-weight: 800;

    color:
        rgba(22,41,63,0.850);
}


.menu-title::before {

    content: "";

    width: 3px;

    height: 11px;

    background:
        rgba(255,255,255,0.450);

    border-radius: 5px;
}


/* =========================================================
   MENU
========================================================= */

.menu {

    list-style: none;
}


.menu li {

    margin-bottom: 2px;
}


.menu a {

    position: relative;

    display: flex;

    align-items: center;

    gap: 11px;

    min-height: 40px;

    padding: 9px 11px;

    border-radius: 9px;

    text-decoration: none;

    color:
        rgba(22,41,63,0.850);

    font-size: 12px;

    font-weight: 500;

    transition:
        background .2s ease,
        color .2s ease,
        transform .2s ease;
}


.menu a:hover {

    background:
        rgba(23,52,86,0.055);

    color: #16293f;

    transform: translateX(2px);
}


.menu a.active {

    background: white;

    color: #005baa;

    font-weight: 700;

    box-shadow:
        0 5px 15px
        rgba(16,42,67,0.022);
}


.menu a.active::before {

    content: "";

    position: absolute;

    left: 0;

    top: 8px;

    bottom: 8px;

    width: 3px;

    background: #00a8ff;

    border-radius:
        0 4px 4px 0;
}


/* =========================================================
   MENU ICON
========================================================= */

.menu-icon {

    width: 25px;

    min-width: 25px;

    height: 25px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 7px;

    background:
        rgba(23,52,86,0.044);

    font-size: 14px;
}


.menu a.active .menu-icon {

    background:
        rgba(0,91,170,.10);

    color: #005baa;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 255px;

    padding: 30px;
}


/* =========================================================
   HEADER
========================================================= */

.header {

    margin-bottom: 25px;
}


.header h1 {

    font-family: 'Poppins', sans-serif;

    font-size: 28px;

    color: #142b4f;
}


.header p {

    color: #5c748c;

    margin-top: 6px;

    line-height: 1.6;

    font-size: 13px;
}


/* =========================================================
   HERO CUACA
========================================================= */

.weather-hero {

    background:
        linear-gradient(
            135deg,
            #0756a3,
            #0a76d5
        );

    color: white;

    border-radius: 22px;

    padding: 30px;

    position: relative;

    overflow: hidden;

    box-shadow:
        0 15px 35px
        rgba(7,86,163,.20);

    margin-bottom: 25px;
}


.weather-hero::after {

    content: "";

    position: absolute;

    width: 250px;
    height: 250px;

    right: -80px;
    top: -90px;

    border-radius: 50%;

    background:
        rgba(23,52,86,0.044);
}


.weather-top {

    display: flex;

    justify-content: space-between;

    align-items: center;

    position: relative;

    z-index: 2;
}


.location {

    font-size: 14px;

    opacity: .85;
}


.location h2 {

    font-family: 'Poppins', sans-serif;

    font-size: 24px;

    margin-top: 5px;
}


.weather-icon {

    font-size: 65px;
}


.weather-main {

    display: flex;

    align-items: center;

    gap: 25px;

    margin-top: 20px;

    position: relative;

    z-index: 2;
}


.temperature {

    font-family: 'Poppins', sans-serif;

    font-size: 60px;

    font-weight: 700;
}


.condition {

    font-size: 18px;

    font-weight: 600;
}


.update {

    font-size: 12px;

    opacity: .75;

    margin-top: 7px;
}


/* =========================================================
   WEATHER STATS
========================================================= */

.weather-stats {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 15px;

    margin-top: 25px;

    position: relative;

    z-index: 2;
}


.weather-stat {

    background:
        rgba(23,52,86,0.066);

    border:
        1px solid
        rgba(23,52,86,0.066);

    border-radius: 14px;

    padding: 15px;
}


.weather-stat-label {

    font-size: 11px;

    opacity: .75;
}


.weather-stat-value {

    font-size: 17px;

    font-weight: 700;

    margin-top: 5px;
}


/* =========================================================
   INFO CARDS
========================================================= */

.section-title {

    font-family: 'Poppins', sans-serif;

    color: #17355e;

    font-size: 20px;

    margin-bottom: 15px;
}


.info-grid {

    display: grid;

    grid-template-columns:
        repeat(4, 1fr);

    gap: 18px;

    margin-bottom: 30px;
}


.info-card {

    background: white;

    border-radius: 18px;

    padding: 22px;

    border:
        1px solid #e7edf5;

    box-shadow:
        0 5px 20px
        rgba(16,42,67,0.015);

    transition: .25s;
}


.info-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 12px 30px
        rgba(16,42,67,0.026);
}


.info-icon {

    width: 52px;

    height: 52px;

    border-radius: 14px;

    background: #eaf4ff;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 27px;

    margin-bottom: 15px;
}


.info-card h3 {

    font-family: 'Poppins', sans-serif;

    font-size: 17px;

    color: #17355e;

    margin-bottom: 8px;
}


.info-card p {

    color: #5c748c;

    font-size: 13px;

    line-height: 1.6;

    margin-bottom: 15px;
}


.info-button {

    display: inline-block;

    padding: 9px 13px;

    background: #0756a3;

    color: white;

    border-radius: 8px;

    text-decoration: none;

    font-size: 12px;

    font-weight: 600;
}


.info-button:hover {

    background: #dbe5f0;
}


/* =========================================================
   INFORMASI PENTING
========================================================= */

.notice {

    background: white;

    border-radius: 18px;

    padding: 25px;

    border:
        1px solid #e7edf5;

    box-shadow:
        0 5px 20px
        rgba(16,42,67,0.015);

    margin-bottom: 25px;
}


.notice h2 {

    font-family: 'Poppins', sans-serif;

    color: #17355e;

    margin-bottom: 12px;

    font-size: 20px;
}


.notice p {

    color: #5c748c;

    line-height: 1.7;

    font-size: 14px;
}


.source {

    margin-top: 15px;

    padding: 12px 15px;

    background: #f1f7ff;

    border-radius: 10px;

    color: #0756a3;

    font-size: 12px;
}


/* =========================================================
   QUICK MENU
========================================================= */

.quick {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;
}


.quick a {

    text-decoration: none;

    background: white;

    border-radius: 16px;

    padding: 20px;

    border:
        1px solid #e7edf5;

    color: #17355e;

    transition: .2s;
}


.quick a:hover {

    transform:
        translateY(-3px);

    border-color: #ced9e3;
}


.quick-title {

    font-weight: 700;

    margin-bottom: 5px;
}


.quick-desc {

    color: #5c748c;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .info-grid {

        grid-template-columns:
            repeat(2,1fr);
    }

    .weather-stats {

        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:800px) {

    .sidebar {

        width: 230px;
    }

    .main {

        margin-left: 230px;

        padding: 22px;
    }

}


@media(max-width:700px) {

    .sidebar {

        position: relative;

        width: 100%;

        height: auto;

        max-height: none;
    }

    .main {

        margin-left: 0;

        padding: 18px;
    }

    .info-grid,
    .quick {

        grid-template-columns: 1fr;
    }

    .weather-stats {

        grid-template-columns: 1fr;
    }

    .weather-top {

        display: block;
    }

    .weather-icon {

        margin-top: 15px;
    }

    .weather-main {

        display: block;
    }

    .temperature {

        font-size: 50px;
    }

}

</style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

<!-- =====================================================
     LOGO
===================================================== -->

<div class="logo-area">

<div class="logo-bmkg">
    <img src="assets/img/logo-bmkg.png" alt="Logo BMKG" width="45" height="45">
</div>

<div class="logo-text">

    <h2>
        Pemantauan BMKG
    </h2>

    <span>
        Atmospheric Information
    </span>

</div>

</div>

<nav>

<!-- =====================================================
     MONITORING
===================================================== -->

<div class="menu-title">
    Monitoring
</div>

<ul class="menu">

<li>

<a href="dashboard.php">

<span class="menu-icon">⌂</span>

<span>Dasbor</span>

</a>

</li>

<li>

<a href="data_cuaca.php">

<span class="menu-icon">☁</span>

<span>Data Cuaca</span>

</a>

</li>

<li>

<a href="monitoring.php">

<span class="menu-icon">◉</span>

<span>Monitoring</span>

</a>

</li>

<li>

<a href="grafik.php">

<span class="menu-icon">▥</span>

<span>Grafik</span>

</a>

</li>

<li>


</li>

<li>

<a href="prediksi.php">

<span class="menu-icon">◌</span>

<span>Prakiraan Cuaca</span>

</a>

</li>

<li>

<a href="laporan.php">

<span class="menu-icon">▤</span>

<span>Laporan</span>

</a>

</li>

</ul>

<!-- =====================================================
     PRODUK BMKG
===================================================== -->

<div class="menu-title">
    Produk BMKG
</div>

<ul class="menu">

<li>

<a href="inarcm.php">

<span class="menu-icon">◈</span>

<span>InaRCM</span>

</a>

</li>

<li>

<a href="inawms.php">

<span class="menu-icon">◒</span>

<span>InAWMS</span>

</a>

</li>

<li>

<a href="galeri-produk.php">

<span class="menu-icon">▧</span>

<span>Galeri Produk</span>

</a>

</li>

</ul>

<!-- =====================================================
     INFORMASI BMKG
===================================================== -->

<div class="menu-title">
    Informasi BMKG
</div>

<ul class="menu">

<li>

<a href="info_bmkg.php"
class="active">

<span class="menu-icon">ⓘ</span>

<span>Informasi BMKG</span>

</a>

</li>

<li>

<a href="gempa.php">

<span class="menu-icon">◉</span>

<span>Gempa Terkini</span>

</a>

</li>

<li>


</li>

</ul>

<!-- =====================================================
     SISTEM
===================================================== -->

<div class="menu-title">
    Sistem
</div>

<ul class="menu">

<li>

<a href="logout.php">

<span class="menu-icon">⇥</span>

<span>Keluar</span>

</a>

</li>

</ul>

</nav>

</aside>

<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">

<div class="header">

<h1>
    Informasi BMKG
</h1>

<p>
    Pusat informasi cuaca, iklim, gempa bumi,
    dan informasi meteorologi Indonesia.
</p>

</div>

<!-- =====================================================
     CUACA TERKINI
===================================================== -->

<div class="weather-hero">

<div class="weather-top">

<div class="location">

Monitoring Cuaca

<h2>
    BMKG Kemayoran
</h2>

</div>

<div class="weather-icon">

<?= $icon ?>

</div>

</div>

<div class="weather-main">

<div class="temperature">

<?= htmlspecialchars($suhu) ?>

<?php if ($suhu !== '-'): ?>
    °C
<?php endif; ?>

</div>

<div>

<div class="condition">

    <?= htmlspecialchars($kondisi) ?>

</div>


<div class="update">

    Pembaruan:
    <?= htmlspecialchars($tanggal) ?>
    <?= htmlspecialchars($waktu) ?>

</div>

</div>

</div>

<div class="weather-stats">

<div class="weather-stat">

<div class="weather-stat-label">
    Kelembapan
</div>

<div class="weather-stat-value">

    <?= htmlspecialchars($kelembapan) ?>

    <?php if ($kelembapan !== '-'): ?>
        %
    <?php endif; ?>

</div>

</div>

<div class="weather-stat">

<div class="weather-stat-label">
    Kecepatan Angin
</div>

<div class="weather-stat-value">

    <?= htmlspecialchars($angin) ?>

</div>

</div>

<div class="weather-stat">

<div class="weather-stat-label">
    Arah Angin
</div>

<div class="weather-stat-value">

    <?= htmlspecialchars($arahAngin) ?>

</div>

</div>

<div class="weather-stat">

<div class="weather-stat-label">
    Curah Hujan
</div>

<div class="weather-stat-value">

    <?= htmlspecialchars($curahHujan) ?>

</div>

</div>

</div>

</div>

<!-- =====================================================
     KATEGORI INFORMASI
===================================================== -->

<h2 class="section-title">

Informasi Utama BMKG

</h2>

<div class="info-grid">

<!-- CUACA -->

<div class="info-card">

<div class="info-icon">
    🌦️
</div>

<h3>
    Cuaca
</h3>

<p>
    Informasi kondisi cuaca dan pemantauan
    parameter meteorologi seperti suhu,
    kelembapan, hujan, dan angin.
</p>

<a
    href="data_cuaca.php"
    class="info-button"
>
    Lihat Data
</a>

</div>

<!-- IKLIM -->

<div class="info-card">

<div class="info-icon">
    🌡️
</div>

<h3>
    Iklim
</h3>

<p>
    Informasi terkait kondisi iklim,
    anomali suhu, curah hujan,
    dan produk prakiraan iklim.
</p>

<a
    href="inarcm.php"
    class="info-button"
>
    Produk Iklim
</a>

</div>

<!-- GEMPA -->

<div class="info-card">

<div class="info-icon">
    🌋
</div>

<h3>
    Gempa Bumi
</h3>

<p>
    Informasi kejadian gempa bumi,
    lokasi, waktu kejadian,
    magnitudo, dan kedalaman.
</p>

<a
    href="gempa.php"
    class="info-button"
>
    Informasi Gempa
</a>

</div>

<!-- INFORMASI -->

<div class="info-card">

<div class="info-icon">
    📡
</div>

<h3>
    Informasi BMKG
</h3>

<p>
    Pusat informasi mengenai layanan
    meteorologi, klimatologi, geofisika,
    dan produk BMKG.
</p>

<a
    href="galeri-produk.php"
    class="info-button"
>
    Lihat Produk
</a>

</div>

</div>

<!-- =====================================================
     INFORMASI TENTANG SISTEM
===================================================== -->

<div class="notice">

<h2>
    Tentang Sistem Pemantauan BMKG
</h2>

<p>
    Sistem ini merupakan dashboard monitoring
    yang mengintegrasikan data cuaca dengan
    berbagai produk meteorologi dan klimatologi.
    Sistem menyediakan pemantauan data cuaca,
    grafik, laporan, prediksi, produk InAWMS,
    produk InaRCM, serta galeri produk BMKG.
</p>


<div class="source">

    📌
    Sumber data internal sistem:

    <strong>
        Data Monitoring Cuaca BMKG Kemayoran
    </strong>

</div>

</div>

<!-- =====================================================
     MENU CEPAT
===================================================== -->

<h2 class="section-title">

Akses Cepat

</h2>

<div class="quick">

<a href="dashboard.php">

<div class="quick-title">
    🏠 Dashboard
</div>

<div class="quick-desc">
    Kembali ke halaman utama monitoring.
</div>

</a>

<a href="inawms.php">

<div class="quick-title">
    🌊 InAWMS
</div>

<div class="quick-desc">
    Lihat produk gelombang atmosfer.
</div>

</a>

<a href="inarcm.php">

<div class="quick-title">
    🌐 InaRCM
</div>

<div class="quick-desc">
    Lihat produk prakiraan bulanan.
</div>

</a>

<a href="galeri-produk.php">

<div class="quick-title">
    🖼️ Galeri Produk
</div>

<div class="quick-desc">
    Lihat seluruh produk visual BMKG.
</div>

</a>

<a href="grafik.php">

<div class="quick-title">
    📊 Grafik Cuaca
</div>

<div class="quick-desc">
    Analisis perkembangan parameter cuaca.
</div>

</a>

<a href="prediksi.php">

<div class="quick-title">
    🔮 Prakiraan Cuaca
</div>

<div class="quick-desc">
    Melihat hasil prediksi cuaca.
</div>

</a>

</div>

</main>

</body>

</html>
