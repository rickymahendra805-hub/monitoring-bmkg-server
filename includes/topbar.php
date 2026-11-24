<?php
/* =====================================================
   TOPBAR NAVIGASI BERSAMA
   Sama persis dengan navigasi di dashboard.php.
   Dipakai oleh semua halaman agar tampilan menu seragam.

   Syarat: halaman harus memuat:
   - assets/css/topbar.css  (di <head>)
   - Font Awesome 6
===================================================== */

$currentPage = isset($currentPage) && $currentPage !== ''
    ? $currentPage
    : basename($_SERVER['PHP_SELF']);

$halamanAtmosfer = [
    'inarcm.php',
    'inawms.php',
    'city.php',
    'galeri-produk.php'
];

$atmosferAktif = in_array($currentPage, $halamanAtmosfer, true);

$halamanProduk = [
    'prediksi.php',
    'gempa.php'
];

$produkAktif = in_array($currentPage, $halamanProduk, true);

$halamanLainnya = [
    'laporan.php'
];

$lainnyaAktif = in_array($currentPage, $halamanLainnya, true);
?>

<header class="topbar">

    <a class="brand" href="dashboard.php" aria-label="Dasbor BMKG">
        <div class="brand-icon">
            <img src="assets/img/logo-bmkg.png" alt="Logo BMKG" width="46" height="46">
        </div>

        <div>
            <h2>BMKG KEMAYORAN</h2>
            <p>Sistem Pemantauan Cuaca</p>
        </div>
    </a>

    <button
        class="mobile-toggle"
        id="mobileToggle"
        type="button"
        aria-label="Buka Menu"
        aria-expanded="false"
    >
        <i class="fa-solid fa-bars"></i>
    </button>

    <nav class="nav-area" id="navArea" aria-label="Navigasi utama">

        <a
            class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>"
            href="dashboard.php"
        >
            <i class="fa-solid fa-house"></i> Dasbor
        </a>

        <a
            class="nav-link <?= $currentPage === 'data_cuaca.php' ? 'active' : '' ?>"
            href="data_cuaca.php"
        >
            <i class="fa-solid fa-cloud-sun-rain"></i> Data Cuaca
        </a>

        <a
            class="nav-link <?= $currentPage === 'grafik.php' ? 'active' : '' ?>"
            href="grafik.php"
        >
            <i class="fa-solid fa-chart-line"></i> Grafik
        </a>

        <details class="nav-dropdown<?= $produkAktif ? ' aktif' : '' ?>">

            <summary>
                <i class="fa-solid fa-layer-group"></i>
                Produk BMKG
                <i class="fa-solid fa-chevron-down chevron"></i>
            </summary>

            <div class="dropdown-menu">


                <a
                    class="<?= $currentPage === 'prediksi.php' ? 'active' : '' ?>"
                    href="prediksi.php"
                >
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    Prakiraan Cuaca
                </a>


                <a
                    class="<?= $currentPage === 'gempa.php' ? 'active' : '' ?>"
                    href="gempa.php"
                >
                    <i class="fa-solid fa-house-crack"></i>
                    Gempa Bumi
                </a>

            </div>
        </details>

        <details class="nav-dropdown<?= $atmosferAktif ? ' aktif' : '' ?>">

            <summary>
                <i class="fa-solid fa-globe"></i>
                Atmosfer
                <i class="fa-solid fa-chevron-down chevron"></i>
            </summary>

            <div class="dropdown-menu">

                <a
                    class="<?= $currentPage === 'inarcm.php' ? 'active' : '' ?>"
                    href="inarcm.php"
                >
                    <i class="fa-solid fa-earth-asia"></i>
                    InaRCM
                </a>

                <a
                    class="<?= $currentPage === 'inawms.php' ? 'active' : '' ?>"
                    href="inawms.php"
                >
                    <i class="fa-solid fa-water"></i>
                    InaWMS
                </a>

                <a
                    class="<?= $currentPage === 'city.php' ? 'active' : '' ?>"
                    href="city.php"
                >
                    <i class="fa-solid fa-city"></i>
                    Pemantauan Kota
                </a>

            </div>
        </details>

        <details class="nav-dropdown<?= $lainnyaAktif ? ' aktif' : '' ?>">

            <summary>
                <i class="fa-solid fa-folder-open"></i>
                Lainnya
                <i class="fa-solid fa-chevron-down chevron"></i>
            </summary>

            <div class="dropdown-menu">

                <a
                    class="<?= $currentPage === 'laporan.php' ? 'active' : '' ?>"
                    href="laporan.php"
                >
                    <i class="fa-solid fa-file-lines"></i>
                    Laporan
                </a>

            </div>
        </details>

    </nav>
</header>

<script>
/* ================= MENU RESPONSIF ================= */

(function () {
    var mobileToggle = document.getElementById("mobileToggle");
    var navArea = document.getElementById("navArea");

    if (mobileToggle && navArea) {
        mobileToggle.addEventListener("click", function () {
            var isOpen = navArea.classList.toggle("show");

            mobileToggle.setAttribute(
                "aria-expanded",
                String(isOpen)
            );

            var icon = mobileToggle.querySelector("i");

            icon.className = isOpen
                ? "fa-solid fa-xmark"
                : "fa-solid fa-bars";
        });
    }

    /* ================= DROPDOWN NAVIGASI ================= */

    document.querySelectorAll(".nav-dropdown").forEach(function (dropdown) {
        dropdown.addEventListener("toggle", function () {
            if (dropdown.open) {
                document.querySelectorAll(".nav-dropdown").forEach(function (other) {
                    if (other !== dropdown) {
                        other.open = false;
                    }
                });
            }
        });
    });

    /* Tutup dropdown jika pengguna klik di luar menu */

    document.addEventListener("click", function (event) {
        document.querySelectorAll(".nav-dropdown[open]").forEach(function (dropdown) {
            if (!dropdown.contains(event.target)) {
                dropdown.open = false;
            }
        });
    });
})();
</script>
