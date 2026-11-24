<?php
/* =========================================================
   GALERI PRODUK BMKG
   Otomatis membaca:
   - InaRCM
   - InAWMS MAP OLR
   - InAWMS MAP PWAT
   - InAWMS HOV OLR
   - InAWMS HOV PWAT
========================================================= */

$produkFilter = $_GET['produk'] ?? 'SEMUA';
$tanggalFilter = $_GET['tanggal'] ?? '';

$galeri = [];


/* =========================================================
   FUNGSI TAMBAH GAMBAR
========================================================= */

function tambahGambar(&$galeri, $file, $url, $produk, $jenis, $tanggal, $nama)
{
    if (!is_file($file)) {
        return;
    }

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));

    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'])) {
        return;
    }

    $galeri[] = [
        'file'     => $file,
        'url'      => $url,
        'produk'   => $produk,
        'jenis'    => $jenis,
        'tanggal'  => $tanggal,
        'nama'     => $nama
    ];
}


/* =========================================================
   1. INARCM
========================================================= */

$inarcmDir = __DIR__ . "/assets/inarcm/";
$inarcmUrl = "assets/inarcm/";

if (is_dir($inarcmDir)) {

    $bulanFolders = scandir($inarcmDir);

    foreach ($bulanFolders as $bulan) {

        if ($bulan === '.' || $bulan === '..') {
            continue;
        }

        $folder = $inarcmDir . $bulan;

        if (!is_dir($folder)) {
            continue;
        }

        $files = scandir($folder);

        foreach ($files as $file) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $folder . "/" . $file;

            tambahGambar(
                $galeri,
                $fullPath,
                $inarcmUrl . $bulan . "/" . rawurlencode($file),
                "InaRCM",
                "Prakiraan Bulanan",
                $bulan,
                $file
            );
        }
    }
}


/* =========================================================
   2. INAWMS - MAP OLR & PWAT
========================================================= */

$mapDir = __DIR__ . "/assets/map/";
$mapUrl = "assets/map/";

foreach (['OLR', 'PWAT'] as $produk) {

    $produkDir = $mapDir . $produk . "/";

    if (!is_dir($produkDir)) {
        continue;
    }

    $tanggalFolders = scandir($produkDir);

    foreach ($tanggalFolders as $tanggal) {

        if ($tanggal === '.' || $tanggal === '..') {
            continue;
        }

        $folder = $produkDir . $tanggal;

        if (!is_dir($folder)) {
            continue;
        }

        $files = scandir($folder);

        foreach ($files as $file) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $folder . "/" . $file;

            tambahGambar(
                $galeri,
                $fullPath,
                $mapUrl . $produk . "/" . $tanggal . "/" . rawurlencode($file),
                "InAWMS",
                "MAP - " . $produk,
                $tanggal,
                $file
            );
        }
    }
}


/* =========================================================
   3. INAWMS - HOV OLR & PWAT
========================================================= */

$hovDir = __DIR__ . "/assets/hovmoller/";
$hovUrl = "assets/hovmoller/";

foreach (['OLR', 'PWAT'] as $produk) {

    /*
       Struktur:
       assets/hovmoller/OLR/OLR/20261006/
       assets/hovmoller/PWAT/PWAT/20261006/
    */

    $produkDir = $hovDir . $produk . "/" . $produk . "/";

    if (!is_dir($produkDir)) {
        continue;
    }

    $tanggalFolders = scandir($produkDir);

    foreach ($tanggalFolders as $tanggal) {

        if ($tanggal === '.' || $tanggal === '..') {
            continue;
        }

        $folder = $produkDir . $tanggal;

        if (!is_dir($folder)) {
            continue;
        }

        $files = scandir($folder);

        foreach ($files as $file) {

            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $folder . "/" . $file;

            tambahGambar(
                $galeri,
                $fullPath,
                $hovUrl . $produk . "/" . $produk . "/" . $tanggal . "/" . rawurlencode($file),
                "InAWMS",
                "HOV - " . $produk,
                $tanggal,
                $file
            );
        }
    }
}


/* =========================================================
   FILTER
========================================================= */

$galeriFilter = [];

foreach ($galeri as $item) {

    if (
        $produkFilter !== 'SEMUA' &&
        $item['produk'] !== $produkFilter
    ) {
        continue;
    }

    if (
        $tanggalFilter !== '' &&
        $item['tanggal'] !== $tanggalFilter
    ) {
        continue;
    }

    $galeriFilter[] = $item;
}


/* =========================================================
   URUTKAN TERBARU
========================================================= */

usort($galeriFilter, function ($a, $b) {

    return strcmp($b['tanggal'], $a['tanggal']);

});


/* =========================================================
   HITUNG DATA
========================================================= */

$totalSemua = count($galeri);

$totalInawms = 0;
$totalInarcm = 0;

foreach ($galeri as $item) {

    if ($item['produk'] === 'InAWMS') {
        $totalInawms++;
    }

    if ($item['produk'] === 'InaRCM') {
        $totalInarcm++;
    }
}


/* =========================================================
   DAFTAR TANGGAL
========================================================= */

$tanggalList = [];

foreach ($galeri as $item) {

    if (!in_array($item['tanggal'], $tanggalList)) {
        $tanggalList[] = $item['tanggal'];
    }
}

rsort($tanggalList);

?>

<!DOCTYPE html>
<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Galeri Produk BMKG</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">


<style>

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
    width: 260px;
    height: 100vh;
    background: linear-gradient(180deg, #ffffff, #eef3f9);
    color: #16293f;
    padding: 25px 18px;
    overflow-y: auto;
}

.logo-area {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 5px 10px 30px;
}

.logo-bmkg {
    width: 45px;
    height: 45px;
    background: white;
    border-radius: 50%;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 800;
    color: #0756a3;
    font-size: 14px;
}

.logo-bmkg img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: contain;
}

.logo-text h2 {
    font-family: 'Poppins', sans-serif;
    font-size: 18px;
}

.logo-text span {
    font-size: 11px;
    opacity: .75;
}


.menu-title {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: .55;
    margin: 20px 12px 8px;
}

.menu {
    list-style: none;
}

.menu li {
    margin-bottom: 5px;
}

.menu a {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
    color: rgba(22,41,63,0.850);
    padding: 12px 14px;
    border-radius: 10px;
    font-size: 14px;
    transition: .2s;
}

.menu a:hover,
.menu a.active {
    background: rgba(23,52,86,0.083);
    color: #16293f;
}

.menu-icon {
    width: 22px;
    text-align: center;
}


/* =========================================================
   CONTENT
========================================================= */

.main {
    margin-left: 260px;
    padding: 30px;
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    font-family: 'Poppins', sans-serif;
    font-size: 27px;
    color: #142b4f;
}

.header p {
    color: #5c748c;
    margin-top: 5px;
}


/* =========================================================
   STAT
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 18px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border-radius: 16px;
    padding: 22px;
    box-shadow: 0 5px 20px rgba(16,42,67,0.015);
    border: 1px solid #edf1f6;
}

.stat-label {
    font-size: 13px;
    color: #5c748c;
}

.stat-number {
    font-family: 'Poppins', sans-serif;
    font-size: 30px;
    font-weight: 700;
    color: #0756a3;
    margin-top: 5px;
}


/* =========================================================
   FILTER
========================================================= */

.filter-box {
    background: white;
    padding: 20px;
    border-radius: 16px;
    box-shadow: 0 5px 20px rgba(16,42,67,0.015);
    margin-bottom: 25px;
}

.filter-form {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.filter-form select,
.filter-form button,
.reset-btn {
    padding: 11px 15px;
    border-radius: 9px;
    border: 1px solid #dce4ee;
    font-family: 'Inter', sans-serif;
}

.filter-form select {
    min-width: 180px;
    background: white;
}

.filter-form button {
    background: #0756a3;
    color: white;
    border: none;
    cursor: pointer;
    font-weight: 600;
}

.reset-btn {
    text-decoration: none;
    color: #526174;
    background: #f3f6fa;
    display: inline-flex;
    align-items: center;
}


/* =========================================================
   GALERI
========================================================= */

.gallery {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.card {
    background: white;
    border-radius: 17px;
    overflow: hidden;
    border: 1px solid #e7edf5;
    box-shadow: 0 5px 20px rgba(16,42,67,0.015);
    transition: .25s;
}

.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 30px rgba(16,42,67,0.029);
}

.image-wrapper {
    height: 220px;
    background: #edf2f7;
    overflow: hidden;
    cursor: pointer;
    position: relative;
}

.image-wrapper img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    transition: .3s;
}

.card:hover .image-wrapper img {
    transform: scale(1.04);
}

.card-body {
    padding: 17px;
}

.badges {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.badge {
    font-size: 10px;
    padding: 5px 8px;
    border-radius: 20px;
    background: #e9f3ff;
    color: #0756a3;
    font-weight: 700;
}

.badge-dark {
    background: #eef0f4;
    color: #566274;
}

.card-title {
    font-size: 14px;
    font-weight: 700;
    color: #1a2b43;
    word-break: break-word;
}

.card-date {
    font-size: 12px;
    color: #5c748c;
    margin-top: 7px;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {
    background: white;
    padding: 70px 20px;
    text-align: center;
    border-radius: 16px;
    color: #5c748c;
}

.empty-icon {
    font-size: 45px;
    margin-bottom: 12px;
}


/* =========================================================
   MODAL
========================================================= */

.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(255,255,255,0.94);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 30px;
}

.modal.show {
    display: flex;
}

.modal-content {
    max-width: 95%;
    max-height: 90vh;
    position: relative;
}

.modal-content img {
    max-width: 100%;
    max-height: 85vh;
    display: block;
    margin: auto;
    border-radius: 10px;
}

.close {
    position: absolute;
    right: -5px;
    top: -45px;
    color: #16293f;
    font-size: 32px;
    cursor: pointer;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 900px) {

    .sidebar {
        width: 220px;
    }

    .main {
        margin-left: 220px;
    }

    .stats {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {

    .sidebar {
        position: relative;
        width: 100%;
        height: auto;
    }

    .main {
        margin-left: 0;
        padding: 18px;
    }

    .stats {
        grid-template-columns: 1fr;
    }

    .header {
        display: block;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar">

    <div class="logo-area">

        <div class="logo-bmkg">
            <img src="assets/img/logo-bmkg.png" alt="Logo BMKG" width="45" height="45">
        </div>

        <div class="logo-text">
            <h2>BMKG</h2>
            <span>Sistem Pemantauan</span>
        </div>

    </div>


    <div class="menu-title">
        Monitoring
    </div>

    <ul class="menu">

        <li>
            <a href="dashboard.php">
                <span class="menu-icon">🏠</span>
                Dasbor
            </a>
        </li>

        <li>
            <a href="data_cuaca.php">
                <span class="menu-icon">🌦️</span>
                Data Cuaca
            </a>
        </li>

        <li>
            <a href="grafik.php">
                <span class="menu-icon">📊</span>
                Grafik
            </a>
        </li>

        <li>
            <a href="laporan.php">
                <span class="menu-icon">📄</span>
                Laporan
            </a>
        </li>

        <li>
            <a href="prediksi.php">
                <span class="menu-icon">🔮</span>
                Prakiraan Cuaca
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Produk BMKG
    </div>

    <ul class="menu">

        <li>
            <a href="inarcm.php">
                <span class="menu-icon">🌐</span>
                InaRCM
            </a>
        </li>

        <li>
            <a href="inawms.php">
                <span class="menu-icon">🌊</span>
                InAWMS
            </a>
        </li>

        <li>
            <a href="galeri-produk.php" class="active">
                <span class="menu-icon">🖼️</span>
                Galeri Produk
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Informasi BMKG
    </div>

    <ul class="menu">

        <li>
            <a href="info_bmkg.php">
                <span class="menu-icon">ℹ️</span>
                Informasi BMKG
            </a>
        </li>

    </ul>


    <div class="menu-title">
        Sistem
    </div>

    <ul class="menu">

        <li>
            <a href="logout.php">
                <span class="menu-icon">🚪</span>
                Keluar
            </a>
        </li>

    </ul>

</aside>


<!-- =====================================================
     MAIN
===================================================== -->

<main class="main">


    <div class="header">

        <div>

            <h1>Galeri Produk BMKG</h1>

            <p>
                Kumpulan visual produk InaRCM dan InAWMS
            </p>

        </div>

    </div>


    <!-- =================================================
         STATISTIK
    ================================================== -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-label">
                Total Produk
            </div>

            <div class="stat-number">
                <?= number_format($totalSemua) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Produk InAWMS
            </div>

            <div class="stat-number">
                <?= number_format($totalInawms) ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-label">
                Produk InaRCM
            </div>

            <div class="stat-number">
                <?= number_format($totalInarcm) ?>
            </div>

        </div>

    </div>


    <!-- =================================================
         FILTER
    ================================================== -->

    <div class="filter-box">

        <form method="GET" class="filter-form">

            <select name="produk">

                <option value="SEMUA"
                    <?= $produkFilter === 'SEMUA' ? 'selected' : '' ?>>
                    Semua Produk
                </option>

                <option value="InAWMS"
                    <?= $produkFilter === 'InAWMS' ? 'selected' : '' ?>>
                    InAWMS
                </option>

                <option value="InaRCM"
                    <?= $produkFilter === 'InaRCM' ? 'selected' : '' ?>>
                    InaRCM
                </option>

            </select>


            <select name="tanggal">

                <option value="">
                    Semua Tanggal
                </option>

                <?php foreach ($tanggalList as $tanggal): ?>

                    <option
                        value="<?= htmlspecialchars($tanggal) ?>"
                        <?= $tanggalFilter === $tanggal ? 'selected' : '' ?>
                    >

                        <?= htmlspecialchars($tanggal) ?>

                    </option>

                <?php endforeach; ?>

            </select>


            <button type="submit">
                🔍 Tampilkan
            </button>


            <a href="galeri-produk.php" class="reset-btn">
                Reset
            </a>

        </form>

    </div>


    <!-- =================================================
         GALERI
    ================================================== -->

    <?php if (count($galeriFilter) > 0): ?>

        <div class="gallery">

            <?php foreach ($galeriFilter as $item): ?>

                <div class="card">

                    <div
                        class="image-wrapper"
                        onclick="openImage(
                            '<?= htmlspecialchars($item['url'], ENT_QUOTES) ?>'
                        )"
                    >

                        <img
                            src="<?= htmlspecialchars($item['url']) ?>"
                            alt="<?= htmlspecialchars($item['nama']) ?>"
                            loading="lazy"
                            onerror="this.style.display='none';"
                        >

                    </div>


                    <div class="card-body">

                        <div class="badges">

                            <span class="badge">
                                <?= htmlspecialchars($item['produk']) ?>
                            </span>

                            <span class="badge badge-dark">
                                <?= htmlspecialchars($item['jenis']) ?>
                            </span>

                        </div>


                        <div class="card-title">

                            <?= htmlspecialchars($item['nama']) ?>

                        </div>


                        <div class="card-date">

                            📅 <?= htmlspecialchars($item['tanggal']) ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty">

            <div class="empty-icon">
                🖼️
            </div>

            <h3>
                Data produk belum ditemukan
            </h3>

            <p>
                Coba ubah filter atau pastikan file gambar
                sudah berada di folder produk BMKG.
            </p>

        </div>

    <?php endif; ?>


</main>


<!-- =====================================================
     MODAL GAMBAR
===================================================== -->

<div class="modal" id="imageModal" onclick="closeImage()">

    <div class="modal-content" onclick="event.stopPropagation()">

        <span class="close" onclick="closeImage()">
            ×
        </span>

        <img id="modalImage" src="" alt="Preview">

    </div>

</div>


<script>

function openImage(url)
{
    document.getElementById('modalImage').src = url;

    document.getElementById('imageModal')
        .classList.add('show');
}


function closeImage()
{
    document.getElementById('imageModal')
        .classList.remove('show');

    document.getElementById('modalImage').src = '';
}

</script>


</body>
</html>