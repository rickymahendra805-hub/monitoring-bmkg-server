<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <!-- LOGO -->
    <div class="logo">

        <div class="logo-icon">
            BMKG
        </div>

        <div class="logo-text">
            <h2>Pemantauan BMKG</h2>
            <span>Atmospheric Information</span>
        </div>

    </div>


    <!-- MENU -->
    <nav class="menu">

        <!-- =========================
             MONITORING
        ========================== -->

        <div class="menu-title">
            Monitoring
        </div>

        <a href="dashboard.php"
           class="<?= $currentPage == 'dashboard.php' ? 'active' : '' ?>">

            <span class="menu-icon">⌂</span>
            <span>Dasbor</span>

        </a>


        <a href="data_cuaca.php"
           class="<?= $currentPage == 'data_cuaca.php' ? 'active' : '' ?>">

            <span class="menu-icon">☁</span>
            <span>Data Cuaca</span>

        </a>


        <a href="grafik.php"
           class="<?= $currentPage == 'grafik.php' ? 'active' : '' ?>">

            <span class="menu-icon">▥</span>
            <span>Grafik</span>

        </a>


        <a href="laporan.php"
           class="<?= $currentPage == 'laporan.php' ? 'active' : '' ?>">

            <span class="menu-icon">▤</span>
            <span>Laporan</span>

        </a>


        <a href="prediksi.php"
           class="<?= $currentPage == 'prediksi.php' ? 'active' : '' ?>">

            <span class="menu-icon">◉</span>
            <span>Prakiraan Cuaca</span>

        </a>


        <!-- =========================
             PRODUK BMKG
        ========================== -->

        <div class="menu-title">
            Produk BMKG
        </div>


        <!-- INA RCM -->

        <a href="inarcm.php"
           class="<?= $currentPage == 'inarcm.php' ? 'active' : '' ?>">

            <span class="menu-icon">◈</span>
            <span>InaRCM</span>

        </a>


        <!-- INAWMS -->

        <a href="inawms.php"
           class="<?= $currentPage == 'inawms.php' ? 'active' : '' ?>">

            <span class="menu-icon">◒</span>
            <span>InAWMS</span>

        </a>


        <!-- =========================
             INFORMASI BMKG
        ========================== -->

        <div class="menu-title">
            Informasi BMKG
        </div>


        <a href="info_bmkg.php"
           class="<?= $currentPage == 'info_bmkg.php' ? 'active' : '' ?>">

            <span class="menu-icon">ℹ</span>
            <span>Informasi BMKG</span>

        </a>


        <!-- =========================
             AKUN
        ========================== -->

        <div class="menu-title">
            Sistem
        </div>


        <a href="logout.php">

            <span class="menu-icon">⇥</span>
            <span>Keluar</span>

        </a>

    </nav>

</aside>