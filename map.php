<?php
/* =========================================================
   MAP MONITORING CUACA BMKG
   OLR & PWAT
   Project: monitoring-bmkg
========================================================= */


/* =========================================================
   KONFIGURASI
========================================================= */

$mapFolder = __DIR__ . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'map';

$mapUrl = 'assets/map';


/* =========================================================
   FUNGSI MENCARI SEMUA GAMBAR
========================================================= */

function getMapImages($folder, $urlBase)
{
    $result = [];

    if (!is_dir($folder)) {
        return $result;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $folder,
            FilesystemIterator::SKIP_DOTS
        )
    );

    foreach ($iterator as $file) {

        if (!$file->isFile()) {
            continue;
        }

        $ext = strtolower($file->getExtension());

        if (!in_array($ext, [
            'jpg',
            'jpeg',
            'png',
            'webp',
            'gif'
        ])) {
            continue;
        }

        $fullPath = $file->getPathname();

        /*
         * Ambil path relatif dari folder assets/map
         */
        $relative = substr(
            $fullPath,
            strlen($folder) + 1
        );

        /*
         * Windows \ menjadi /
         */
        $relative = str_replace(
            DIRECTORY_SEPARATOR,
            '/',
            $relative
        );

        /*
         * Encode setiap bagian path
         */
        $parts = explode('/', $relative);

        $encodedParts = [];

        foreach ($parts as $part) {
            $encodedParts[] = rawurlencode($part);
        }

        $url = $urlBase . '/' . implode(
            '/',
            $encodedParts
        );

        /*
         * Path filesystem untuk memastikan file benar-benar ada
         */
        $result[] = [
            'url' => $url,
            'path' => $fullPath,
            'name' => $file->getFilename(),
            'folder' => $file->getPath()
        ];
    }

    /*
     * Urutkan berdasarkan URL
     */
    usort(
        $result,
        function ($a, $b) {
            return strnatcasecmp(
                $a['url'],
                $b['url']
            );
        }
    );

    return $result;
}


/* =========================================================
   AMBIL OLR
========================================================= */

$olrFolder =
    $mapFolder .
    DIRECTORY_SEPARATOR .
    'OLR';

$olrImages =
    getMapImages(
        $olrFolder,
        $mapUrl . '/OLR'
    );


/* =========================================================
   AMBIL PWAT
========================================================= */

$pwatFolder =
    $mapFolder .
    DIRECTORY_SEPARATOR .
    'PWAT';

$pwatImages =
    getMapImages(
        $pwatFolder,
        $mapUrl . '/PWAT'
    );


/* =========================================================
   PILIH TAB
========================================================= */

$jenis = $_GET['jenis'] ?? 'olr';

if ($jenis !== 'olr' && $jenis !== 'pwat') {
    $jenis = 'olr';
}


if ($jenis === 'olr') {

    $images = $olrImages;

} else {

    $images = $pwatImages;

}


/* =========================================================
   TOTAL
========================================================= */

$totalOLR = count($olrImages);

$totalPWAT = count($pwatImages);

$totalMap =
    $totalOLR +
    $totalPWAT;


/* =========================================================
   FORMAT NAMA FILE
========================================================= */

function formatName($name)
{
    $name = pathinfo(
        $name,
        PATHINFO_FILENAME
    );

    $name = str_replace(
        [
            '_',
            '-',
            '.'
        ],
        ' ',
        $name
    );

    return strtoupper($name);
}


/* =========================================================
   AMBIL TANGGAL DARI PATH
========================================================= */

function getTanggal($path)
{
    if (
        preg_match(
            '/20\d{6}/',
            $path,
            $match
        )
    ) {

        $tanggal = $match[0];

        $tahun =
            substr($tanggal, 0, 4);

        $bulan =
            substr($tanggal, 4, 2);

        $hari =
            substr($tanggal, 6, 2);

        return
            $hari .
            '-' .
            $bulan .
            '-' .
            $tahun;
    }

    return '';
}

?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Peta Pemantauan Cuaca | BMKG Kemayoran
</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap"
    rel="stylesheet"
>


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

    background:
        linear-gradient(
            135deg,
            #eef6ff,
            #f8fbff,
            #ffffff
        );

    color: #172033;

    min-height: 100vh;
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

    background:
        linear-gradient(
            180deg,
            #ffffff,
            #eef3f9
        );

    color: #16293f;

    padding: 28px 18px;

    z-index: 1000;

    box-shadow:
        5px 0 20px rgba(16,42,67,0.018);
}


.brand {

    display: flex;

    align-items: center;

    gap: 12px;

    padding:
        0 12px 30px;
}


.brand-icon {

    width: 46px;
    height: 46px;

    border-radius: 50%;

    overflow: hidden;

    background:
        #ffffff;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 24px;
}


.brand-icon img {

    width: 100%;
    height: 100%;

    display: block;
    object-fit: contain;
}


.brand-text h2 {

    font-family: 'Poppins';

    font-size: 17px;

    font-weight: 800;
}


.brand-text p {

    font-size: 11px;

    opacity: .75;

    margin-top: 3px;
}


.menu-title {

    font-size: 10px;

    letter-spacing: 1.5px;

    text-transform: uppercase;

    opacity: .55;

    padding:
        0 14px 10px;
}


.menu {

    display: flex;

    flex-direction: column;

    gap: 6px;
}


.menu a {

    color:
        rgba(22,41,63,0.850);

    text-decoration: none;

    padding: 13px 14px;

    border-radius: 12px;

    display: flex;

    align-items: center;

    gap: 12px;

    font-size: 14px;

    transition: .2s;
}


.menu a:hover {

    background:
        rgba(23,52,86,0.066);

    color: #16293f;
}


.menu a.active {

    background: white;

    color: #0066b3;

    font-weight: 700;
}


.menu-icon {

    width: 25px;

    text-align: center;
}


.logout {

    position: absolute;

    left: 18px;
    right: 18px;

    bottom: 25px;
}


.logout a {

    display: flex;

    align-items: center;

    gap: 10px;

    color: #16293f;

    text-decoration: none;

    background:
        rgba(23,52,86,0.055);

    padding: 13px;

    border-radius: 12px;
}


/* =========================================================
   MAIN
========================================================= */

.main {

    margin-left: 260px;

    padding: 30px;
}


/* =========================================================
   HEADER
========================================================= */

.top-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}


.page-title h1 {

    font-family: 'Poppins';

    font-size: 28px;

    font-weight: 800;

    color: #102a43;
}


.page-title p {

    color: #5c748c;

    font-size: 13px;

    margin-top: 5px;
}


.badge {

    background: #e7f3ff;

    color: #0066b3;

    padding: 9px 14px;

    border-radius: 20px;

    font-size: 12px;

    font-weight: 700;
}


/* =========================================================
   STATISTICS
========================================================= */

.stats {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 18px;

    margin-bottom: 25px;
}


.stat-card {

    background: white;

    border:
        1px solid #e7eef6;

    border-radius: 18px;

    padding: 20px;

    display: flex;

    align-items: center;

    gap: 15px;

    box-shadow:
        0 8px 25px
        rgba(16,42,67,0.013);
}


.stat-icon {

    width: 50px;
    height: 50px;

    border-radius: 14px;

    background: #eaf5ff;

    display: flex;

    align-items: center;
    justify-content: center;

    font-size: 23px;
}


.stat-card h3 {

    font-size: 22px;

    font-weight: 800;

    color: #102a43;
}


.stat-card p {

    font-size: 12px;

    color: #5c748c;

    margin-top: 2px;
}


/* =========================================================
   CONTAINER
========================================================= */

.map-container {

    background: white;

    border:
        1px solid #e6edf5;

    border-radius: 22px;

    padding: 24px;

    box-shadow:
        0 10px 35px
        rgba(16,42,67,0.015);
}


.map-header {

    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 22px;
}


.map-header h2 {

    font-family: 'Poppins';

    font-size: 19px;

    color: #102a43;
}


.map-header p {

    font-size: 12px;

    color: #5c748c;

    margin-top: 4px;
}


/* =========================================================
   TABS
========================================================= */

.tabs {

    display: flex;

    gap: 7px;

    padding: 5px;

    background: #f1f6fa;

    border-radius: 12px;
}


.tabs a {

    text-decoration: none;

    padding:
        10px 20px;

    border-radius: 9px;

    font-size: 13px;

    font-weight: 700;

    color: #5c748c;
}


.tabs a.active {

    background: #0066b3;

    color: white;
}


/* =========================================================
   GALERI
========================================================= */

.gallery {

    display: grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap: 20px;
}


.map-card {

    background: white;

    border:
        1px solid #e1e9f0;

    border-radius: 16px;

    overflow: hidden;

    cursor: pointer;

    transition: .25s;
}


.map-card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 15px 30px
        rgba(16,42,67,0.033);
}


/* =========================================================
   IMAGE
========================================================= */

.map-image {

    display: block;

    width: 100%;

    height: 240px;

    object-fit: contain;

    background: #edf3f7;

    transition: .25s;
}


.map-card:hover .map-image {

    transform: scale(1.02);
}


/* =========================================================
   INFO
========================================================= */

.map-info {

    padding: 14px;

}


.map-info h3 {

    font-size: 13px;

    font-weight: 700;

    color: #20364d;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;
}


.map-info p {

    margin-top: 5px;

    font-size: 11px;

    color: #5c748c;
}


/* =========================================================
   EMPTY
========================================================= */

.empty {

    text-align: center;

    padding: 70px 20px;

    color: #5c748c;
}


.empty-icon {

    font-size: 55px;

    margin-bottom: 15px;
}


.empty h3 {

    color: #34495e;

    margin-bottom: 7px;
}


/* =========================================================
   ERROR PATH
========================================================= */

.debug-box {

    margin-top: 20px;

    padding: 15px;

    border-radius: 12px;

    background: #fff8e6;

    border: 1px solid #e3dece;

    font-size: 12px;

    color: #765d16;

    overflow-x: auto;
}


/* =========================================================
   MODAL
========================================================= */

.modal {

    position: fixed;

    inset: 0;

    display: none;

    align-items: center;

    justify-content: center;

    padding: 30px;

    background:
        rgba(255,255,255,0.94);

    z-index: 9999;
}


.modal.show {

    display: flex;
}


.modal-image {

    max-width: 95vw;

    max-height: 88vh;

    object-fit: contain;

    background: white;

    border-radius: 10px;
}


.close {

    position: fixed;

    right: 25px;

    top: 20px;

    width: 45px;

    height: 45px;

    border: 0;

    border-radius: 50%;

    background:
        rgba(23,52,86,0.099);

    color: #16293f;

    font-size: 28px;

    cursor: pointer;

    z-index: 10001;
}


.modal-title {

    position: fixed;

    bottom: 20px;

    left: 50%;

    transform:
        translateX(-50%);

    background:
        rgba(255,255,255,0.94);

    color: #16293f;

    padding:
        9px 18px;

    border-radius: 20px;

    font-size: 12px;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media(max-width:1100px) {

    .gallery {

        grid-template-columns:
            repeat(2, 1fr);
    }

}


@media(max-width:850px) {

    .sidebar {

        width: 75px;

        padding:
            20px 10px;
    }

    .brand-text,
    .menu-title,
    .menu a span,
    .logout span {

        display: none;
    }

    .menu a {

        justify-content: center;
    }

    .main {

        margin-left: 75px;

        padding: 20px;
    }

}


@media(max-width:650px) {

    .stats {

        grid-template-columns: 1fr;
    }

    .gallery {

        grid-template-columns: 1fr;
    }

    .map-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;
    }

    .tabs {

        width: 100%;
    }

    .tabs a {

        flex: 1;

        text-align: center;
    }

}

</style>

</head>


<body>


<!-- =====================================================
     SIDEBAR
====================================================== -->

<aside class="sidebar">


    <div class="brand">

        <div class="brand-icon">
            <img src="assets/img/logo-bmkg.png" alt="Logo BMKG" width="45" height="45">
        </div>

        <div class="brand-text">

            <h2>
                BMKG Kemayoran
            </h2>

            <p>
                Monitoring Cuaca
            </p>

        </div>

    </div>


    <div class="menu-title">
        MENU UTAMA
    </div>


    <nav class="menu">


        <a href="dashboard.php">

            <div class="menu-icon">
                📊
            </div>

            <span>
                Dasbor
            </span>

        </a>


        <a href="data_cuaca.php">

            <div class="menu-icon">
                🌤️
            </div>

            <span>
                Data Cuaca
            </span>

        </a>


        <a href="grafik.php">

            <div class="menu-icon">
                📈
            </div>

            <span>
                Grafik
            </span>

        </a>


        <a
            href="map.php?jenis=olr"
            class="active"
        >

            <div class="menu-icon">
                🗺️
            </div>

            <span>
                Peta Pemantauan
            </span>

        </a>


        <a href="prediksi.php">

            <div class="menu-icon">
                🔮
            </div>

            <span>
                Prediksi
            </span>

        </a>


        <a href="laporan.php">

            <div class="menu-icon">
                📄
            </div>

            <span>
                Laporan
            </span>

        </a>


    </nav>


    <div class="logout">

        <a href="logout.php">

            🚪

            <span>
                Keluar
            </span>

        </a>

    </div>

</aside>


<!-- =====================================================
     MAIN
====================================================== -->

<main class="main">


    <div class="top-header">

        <div class="page-title">

            <h1>
                Peta Pemantauan Cuaca
            </h1>

            <p>
                Visualisasi peta atmosfer
                BMKG Kemayoran
            </p>

        </div>


        <div class="badge">

            🗺️ Peta Pemantauan

        </div>

    </div>


    <!-- =================================================
         STATISTIK
    ================================================== -->

    <section class="stats">


        <div class="stat-card">

            <div class="stat-icon">
                🗺️
            </div>

            <div>

                <h3>
                    <?= $totalMap ?>
                </h3>

                <p>
                    Total Map
                </p>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                ☁️
            </div>

            <div>

                <h3>
                    <?= $totalOLR ?>
                </h3>

                <p>
                    Map OLR
                </p>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">
                💧
            </div>

            <div>

                <h3>
                    <?= $totalPWAT ?>
                </h3>

                <p>
                    Map PWAT
                </p>

            </div>

        </div>


    </section>


    <!-- =================================================
         MAP
    ================================================== -->

    <section class="map-container">


        <div class="map-header">


            <div>

                <h2>

                    <?php if ($jenis === 'olr'): ?>

                        ☁️ Outgoing Longwave Radiation (OLR)

                    <?php else: ?>

                        💧 Precipitable Water (PWAT)

                    <?php endif; ?>

                </h2>


                <p>
                    Klik gambar untuk melihat ukuran penuh
                </p>

            </div>


            <div class="tabs">

                <a
                    href="map.php?jenis=olr"
                    class="<?= $jenis === 'olr' ? 'active' : '' ?>"
                >
                    ☁️ OLR
                </a>


                <a
                    href="map.php?jenis=pwat"
                    class="<?= $jenis === 'pwat' ? 'active' : '' ?>"
                >
                    💧 PWAT
                </a>

            </div>


        </div>


        <!-- =================================================
             GALERI
        ================================================== -->

        <?php if (!empty($images)): ?>


            <div class="gallery">


                <?php foreach ($images as $item): ?>


                    <?php

                    $nama =
                        formatName(
                            $item['name']
                        );

                    $tanggal =
                        getTanggal(
                            $item['path']
                        );

                    ?>


                    <div
                        class="map-card"
                        onclick='openMap(
                            <?= json_encode($item["url"]) ?>,
                            <?= json_encode($nama) ?>
                        )'
                    >


                        <img
                            src="<?= htmlspecialchars($item['url']) ?>"
                            class="map-image"
                            alt="<?= htmlspecialchars($nama) ?>"
                            loading="lazy"
                            onerror="showImageError(this)"
                        >


                        <div class="map-info">


                            <h3>
                                <?= htmlspecialchars($nama) ?>
                            </h3>


                            <?php if ($tanggal): ?>

                                <p>
                                    📅
                                    <?= htmlspecialchars($tanggal) ?>
                                </p>

                            <?php else: ?>

                                <p>
                                    🗺️ Map BMKG
                                </p>

                            <?php endif; ?>


                        </div>


                    </div>


                <?php endforeach; ?>


            </div>


            <!-- =================================================
                 DEBUG
            ================================================== -->

            <div class="debug-box">

                <strong>
                    Folder aktif:
                </strong>

                <?= htmlspecialchars($mapFolder) ?>

                <br>

                <strong>
                    Jumlah <?= strtoupper($jenis) ?>:
                </strong>

                <?= count($images) ?>

            </div>


        <?php else: ?>


            <div class="empty">

                <div class="empty-icon">
                    🗺️
                </div>

                <h3>
                    Map belum ditemukan
                </h3>

                <p>
                    Tidak ada gambar
                    <?= strtoupper($jenis) ?>
                    yang ditemukan.
                </p>


                <div class="debug-box">

                    <strong>
                        PHP sedang mencari folder:
                    </strong>

                    <br><br>

                    <?= htmlspecialchars(
                        $jenis === 'olr'
                        ? $olrFolder
                        : $pwatFolder
                    ) ?>

                </div>

            </div>


        <?php endif; ?>


    </section>


</main>


<!-- =====================================================
     MODAL
====================================================== -->

<div
    class="modal"
    id="modal"
    onclick="closeModal(event)"
>


    <button
        class="close"
        onclick="forceClose()"
    >
        ×
    </button>


    <img
        src=""
        id="modalImage"
        class="modal-image"
        alt="Map BMKG"
    >


    <div
        class="modal-title"
        id="modalTitle"
    ></div>


</div>


<script>

/* =========================================================
   OPEN IMAGE
========================================================= */

function openMap(
    image,
    title
) {

    const modal =
        document.getElementById(
            'modal'
        );

    const img =
        document.getElementById(
            'modalImage'
        );

    const titleBox =
        document.getElementById(
            'modalTitle'
        );


    img.src = image;

    titleBox.textContent =
        title;


    modal.classList.add(
        'show'
    );


    document.body.style.overflow =
        'hidden';

}


/* =========================================================
   CLOSE
========================================================= */

function closeModal(event)
{

    if (
        event.target.id ===
        'modal'
    ) {

        forceClose();

    }

}


function forceClose()
{

    const modal =
        document.getElementById(
            'modal'
        );

    modal.classList.remove(
        'show'
    );


    document.body.style.overflow =
        '';

}


/* =========================================================
   ESC
========================================================= */

document.addEventListener(
    'keydown',
    function(event)
    {

        if (
            event.key ===
            'Escape'
        ) {

            forceClose();

        }

    }
);


/* =========================================================
   IMAGE ERROR
========================================================= */

function showImageError(img)
{

    img.style.display =
        'none';


    const card =
        img.closest(
            '.map-card'
        );


    if (card) {

        const error =
            document.createElement(
                'div'
            );


        error.style.height =
            '240px';

        error.style.display =
            'flex';

        error.style.alignItems =
            'center';

        error.style.justifyContent =
            'center';

        error.style.background =
            '#f8eeee';

        error.style.color =
            '#b42318';

        error.style.fontSize =
            '13px';

        error.innerHTML =
            '❌ Gambar tidak dapat dimuat';


        card.insertBefore(
            error,
            card.firstChild
        );

    }

}

</script>


</body>

</html>