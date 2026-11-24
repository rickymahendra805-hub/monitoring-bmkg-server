    <?php

    /* =========================================================
    LAPORAN MONITORING CUACA BMKG
    TANPA DATABASE
    VERSI MODERN + ANIMASI
    ========================================================= */

    require_once __DIR__ . '/includes/bmkg_data.php';

    $hasilCuaca = bmkg_muat_cuaca();
    $data = $hasilCuaca['records'];
    $lokasiAktif = $hasilCuaca['lokasi'];
    $sumberCuaca = $hasilCuaca['sumber'];
    $waktuAmbilCuaca = $hasilCuaca['diambil'];
    $galatCuaca = $hasilCuaca['error'];


    /* =========================================================
    FILTER TANGGAL
    ========================================================= */

    $filterTanggal = $_GET["tanggal"] ?? "";

    $dataFilter = [];


    foreach ($data as $item) {

        $tanggal = $item["tanggal"] ?? "";

        if (
            $filterTanggal === "" ||
            $tanggal === $filterTanggal
        ) {

            $dataFilter[] = $item;

        }

    }


    /* =========================================================
    URUTKAN DATA TERBARU
    ========================================================= */

    usort(
        $dataFilter,
        function ($a, $b) {

            $tanggalA =
                ($a["tanggal"] ?? "") .
                " " .
                ($a["waktu"] ?? "");

            $tanggalB =
                ($b["tanggal"] ?? "") .
                " " .
                ($b["waktu"] ?? "");

            return strcmp(
                $tanggalB,
                $tanggalA
            );

        }
    );


    /* =========================================================
    STATISTIK
    ========================================================= */

    $totalData = count($dataFilter);

    $totalSuhu = 0;
    $totalKelembapan = 0;
    $totalAngin = 0;
    $totalHujan = 0;

    $jumlahSuhu = 0;
    $jumlahKelembapan = 0;
    $jumlahAngin = 0;

    $suhuMax = null;
    $suhuMin = null;


    /* =========================================================
    HITUNG STATISTIK
    ========================================================= */

    foreach ($dataFilter as $item) {

        /* SUHU */

        if (
            isset($item["suhu"]) &&
            is_numeric($item["suhu"])
        ) {

            $suhu = floatval(
                $item["suhu"]
            );

            $totalSuhu += $suhu;

            $jumlahSuhu++;


            if (
                $suhuMax === null ||
                $suhu > $suhuMax
            ) {

                $suhuMax = $suhu;

            }


            if (
                $suhuMin === null ||
                $suhu < $suhuMin
            ) {

                $suhuMin = $suhu;

            }

        }


        /* KELEMBAPAN */

        if (
            isset($item["kelembapan"]) &&
            is_numeric($item["kelembapan"])
        ) {

            $totalKelembapan +=
                floatval(
                    $item["kelembapan"]
                );

            $jumlahKelembapan++;

        }


        /* ANGIN */

        if (
            isset($item["kecepatan_angin"]) &&
            is_numeric($item["kecepatan_angin"])
        ) {

            $totalAngin +=
                floatval(
                    $item["kecepatan_angin"]
                );

            $jumlahAngin++;

        }


        /* HUJAN */

        if (
            isset($item["curah_hujan"]) &&
            is_numeric($item["curah_hujan"])
        ) {

            $totalHujan +=
                floatval(
                    $item["curah_hujan"]
                );

        }

    }


    /* =========================================================
    RATA-RATA
    ========================================================= */

    $rataSuhu =
        $jumlahSuhu > 0
        ? $totalSuhu / $jumlahSuhu
        : 0;


    $rataKelembapan =
        $jumlahKelembapan > 0
        ? $totalKelembapan / $jumlahKelembapan
        : 0;


    $rataAngin =
        $jumlahAngin > 0
        ? $totalAngin / $jumlahAngin
        : 0;


    /* =========================================================
    DAFTAR TANGGAL
    ========================================================= */

    $daftarTanggal = [];


    foreach ($data as $item) {

        if (
            isset($item["tanggal"]) &&
            $item["tanggal"] !== ""
        ) {

            $daftarTanggal[] =
                $item["tanggal"];

        }

    }


    $daftarTanggal =
        array_unique(
            $daftarTanggal
        );


    rsort($daftarTanggal);


    /* =========================================================
    KONDISI CUACA TERBANYAK
    ========================================================= */

    $kondisiCount = [];


    foreach ($dataFilter as $item) {

        $kondisi =
            $item["kondisi_cuaca"]
            ?? "Tidak diketahui";


        if (
            !isset(
                $kondisiCount[$kondisi]
            )
        ) {

            $kondisiCount[$kondisi] = 0;

        }


        $kondisiCount[$kondisi]++;

    }


    $kondisiDominan = "-";


    if (
        count($kondisiCount) > 0
    ) {

        arsort($kondisiCount);

        $kondisiDominan =
            array_key_first(
                $kondisiCount
            );

    }


    /* =========================================================
    TANGGAL LAPORAN
    ========================================================= */

    $tanggalLaporan =
        date(
            "d-m-Y H:i:s"
        );


    /* =========================================================
    EXPORT EXCEL
    TETAP MENGIKUTI FILTER
    ========================================================= */

    if (
        isset($_GET["export"]) &&
        $_GET["export"] === "excel"
    ) {

        $namaTanggal =
            $filterTanggal !== ""
            ? $filterTanggal
            : date("Y-m-d");


        $namaFile =
            "laporan_monitoring_cuaca_" .
            $namaTanggal .
            ".csv";


        header(
            "Content-Type: text/csv; charset=UTF-8"
        );


        header(
            'Content-Disposition: attachment; filename="' .
            $namaFile .
            '"'
        );


        echo "\xEF\xBB\xBF";


        $output =
            fopen(
                "php://output",
                "w"
            );


        fputcsv(
            $output,
            [
                "No",
                "Tanggal",
                "Waktu",
                "Suhu (°C)",
                "Kelembapan (%)",
                "Curah Hujan (mm)",
                "Kecepatan Angin (km/jam)",
                "Arah Angin",
                "Kondisi Cuaca"
            ],
            ";"
        );


        $noExcel = 1;


        foreach (
            $dataFilter
            as $item
        ) {

            fputcsv(
                $output,
                [
                    $noExcel++,
                    $item["tanggal"] ?? "-",
                    $item["waktu"] ?? "-",
                    $item["suhu"] ?? "-",
                    $item["kelembapan"] ?? "-",
                    $item["curah_hujan"] ?? "-",
                    $item["kecepatan_angin"] ?? "-",
                    $item["arah_angin"] ?? "-",
                    $item["kondisi_cuaca"] ?? "-"
                ],
                ";"
            );

        }


        fclose($output);

        exit;
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
        Laporan Pemantauan Cuaca | BMKG Kemayoran
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

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="assets/css/topbar.css?v=3"
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


    html {
        scroll-behavior: smooth;
    }


    body {
        font-family: "Inter", sans-serif;

        background:
            radial-gradient(ellipse at 8% 0%, rgba(37,140,255,.13), transparent 32%),
            radial-gradient(ellipse at 95% 20%, rgba(62,216,239,.07), transparent 30%),
            #eef3f9;

        color: #16293f;

        min-height: 100vh;
    }


    @keyframes backgroundMove {

        0% {
            background-position: 0% 50%;
        }

        50% {
            background-position: 100% 50%;
        }

        100% {
            background-position: 0% 50%;
        }

    }


    /* =========================================================
    MAIN
    ========================================================= */

    .main {

        width: min(1500px, 100%);

        margin: 0 auto;

        padding: 25px 4% 55px;

        animation:
            pageEnter .7s ease;

    }


    @keyframes pageEnter {

        from {

            opacity: 0;

            transform:
                translateY(15px);

        }

        to {

            opacity: 1;

            transform:
                translateY(0);

        }

    }


    /* =========================================================
    PAGE HEAD
    ========================================================= */

    .page-head {

        display: flex;

        align-items: center;

        justify-content: space-between;

        margin-bottom: 22px;

        animation:
            slideDown .7s ease;

    }


    @keyframes slideDown {

        from {

            opacity: 0;

            transform:
                translateY(-20px);

        }

        to {

            opacity: 1;

            transform:
                translateY(0);

        }

    }


    .title h1 {

        font-family: "Poppins";

        font-size: 25px;

    }


    .title p {

        font-size: 12px;

        color: #5c748c;

        margin-top: 4px;

    }


    .clock {

        background: linear-gradient(145deg, #ffffff, #eef3f9);

        border: 1px solid rgba(35,70,110,0.208);

        padding: 10px 15px;

        border-radius: 10px;

        font-size: 12px;

        font-weight: 600;

        color: #115d9c;

        box-shadow:
            0 4px 20px
            rgba(16,42,67,0.048);

        transition:
            transform .3s ease,
            box-shadow .3s ease;

    }


    .clock:hover {

        transform:
            translateY(-3px);

        box-shadow:
            0 8px 25px
            rgba(37,140,255,.25);

    }


    /* =========================================================
    FILTER
    ========================================================= */

    .filter-card {

        background: linear-gradient(145deg, #ffffff, #eef3f9);

        border: 1px solid rgba(35,70,110,0.208);

        border-radius: 15px;

        padding: 20px;

        margin-bottom: 20px;

        box-shadow:
            0 5px 25px
            rgba(16,42,67,0.013);

        animation:
            fadeUp .7s ease;

    }


    .filter-title {

        font-family: "Poppins";

        font-size: 14px;

        margin-bottom: 14px;

    }


    .filter-form {

        display: flex;

        gap: 10px;

        flex-wrap: wrap;

        align-items: end;

    }


    .form-group {

        display: flex;

        flex-direction: column;

        gap: 6px;

    }


    .form-group label {

        font-size: 10px;

        color: #5c748c;

        font-weight: 600;

    }


    .form-group select {

        min-width: 220px;

        padding: 11px 13px;

        background: #ffffff;

        color: #16293f;

        border:
            1px solid rgba(35,70,110,0.220);

        border-radius: 9px;

        outline: none;

        font-family: inherit;

        font-size: 12px;

        transition:
            all .25s ease;

    }


    .form-group select:hover {

        border-color: #ced8e3;

    }


    .form-group select:focus {

        border-color: #cedae3;

        box-shadow:
            0 0 0 4px
            rgba(37,140,255,.12);

    }


    /* =========================================================
    BUTTON
    ========================================================= */

    .btn {

        border: none;

        padding: 11px 17px;

        border-radius: 9px;

        cursor: pointer;

        text-decoration: none;

        font-family: inherit;

        font-size: 12px;

        font-weight: 600;

        display: inline-flex;

        align-items: center;

        justify-content: center;

        gap: 7px;

        transition:
            all .25s ease;

        position: relative;

        overflow: hidden;

    }


    .btn:hover {

        transform:
            translateY(-3px);

    }


    .btn-primary {

        background: #258cff;

        color: #16293f;

        box-shadow:
            0 5px 15px
            rgba(37,140,255,.25);

    }


    .btn-primary:hover {

        background: #1a6fd4;

    }


    .btn-light {

        background: rgba(23,52,86,0.039);

        color: #16293f;

    }


    .btn-light:hover {

        background: rgba(23,52,86,0.077);

    }


    .btn-print {

        background: #dbe5f0;

        color: #16293f;

    }


    .btn-print:hover {

        background: #eaf2fa;

    }


    .btn-excel {

        background: #dbe5f0;

        color: #16293f;

    }


    .btn-excel:hover {

        background: #eaf2fa;

    }


    /* =========================================================
    REPORT HEADER
    ========================================================= */

    .report-header {

        background:
            radial-gradient(circle at 90% 0%, rgba(62,216,239,.17), transparent 28%),
            linear-gradient(115deg, #eaf2fa, #dbe5f0 55%, #f5f9fd);

        background-size:
            200% 200%;

        animation:
            gradientMove 7s ease infinite;

        color: #16293f;

        border-radius: 18px;

        padding: 25px;

        margin-bottom: 20px;

        position: relative;

        overflow: hidden;

        box-shadow:
            0 10px 35px
            rgba(16,42,67,0.055);

    }


    @keyframes gradientMove {

        0% {
            background-position:
                0% 50%;
        }

        50% {
            background-position:
                100% 50%;
        }

        100% {
            background-position:
                0% 50%;
        }

    }


    .report-header::before {

        content: "";

        position: absolute;

        width: 300px;

        height: 300px;

        border-radius: 50%;

        background:
            rgba(23,52,86,0.039);

        right: -100px;

        top: -150px;

    }


    .report-header::after {

        content: "";

        position: absolute;

        width: 180px;

        height: 180px;

        border-radius: 50%;

        background:
            rgba(23,52,86,0.030);

        left: -70px;

        bottom: -100px;

    }


    .report-header-top {

        display: flex;

        justify-content: space-between;

        align-items: center;

        gap: 20px;

        position: relative;

        z-index: 2;

    }


    .report-title h2 {

        font-family: "Poppins";

        font-size: 22px;

    }


    .report-title p {

        font-size: 11px;

        opacity: .8;

        margin-top: 4px;

    }


    .report-info {

        text-align: right;

        font-size: 11px;

        opacity: .85;

    }


    /* =========================================================
    STATISTICS
    ========================================================= */

    .stats {

        display: grid;

        grid-template-columns:
            repeat(5,1fr);

        gap: 12px;

        margin-bottom: 20px;

    }


    .stat {

        background: linear-gradient(145deg, #ffffff, #eef3f9);

        border: 1px solid rgba(35,70,110,0.208);

        padding: 17px;

        border-radius: 13px;

        box-shadow:
            0 5px 20px
            rgba(16,42,67,0.011);

        position: relative;

        overflow: hidden;

        transition:
            all .3s ease;

        animation:
            statEnter .7s ease both;

    }


    .stat:nth-child(1) {
        animation-delay: .1s;
    }


    .stat:nth-child(2) {
        animation-delay: .2s;
    }


    .stat:nth-child(3) {
        animation-delay: .3s;
    }


    .stat:nth-child(4) {
        animation-delay: .4s;
    }


    .stat:nth-child(5) {
        animation-delay: .5s;
    }


    @keyframes statEnter {

        from {

            opacity: 0;

            transform:
                translateY(25px)
                scale(.95);

        }

        to {

            opacity: 1;

            transform:
                translateY(0)
                scale(1);

        }

    }


    .stat::before {

        content: "";

        position: absolute;

        left: 0;

        top: 0;

        width: 4px;

        height: 100%;

        background:
            linear-gradient(
                180deg,
                #258cff,
                #3ed8ef
            );

    }


    .stat:hover {

        transform:
            translateY(-7px);

        box-shadow:
            0 15px 35px
            rgba(37,140,255,.2);

    }


    .stat-label {

        font-size: 10px;

        color: #5c748c;

    }


    .stat-value {

        font-family: "Poppins";

        font-size: 21px;

        font-weight: 700;

        margin-top: 5px;

        color: #16293f;

    }


    .stat-unit {

        font-size: 9px;

        color: #5c748c;

    }


    /* =========================================================
    TABLE
    ========================================================= */

    .table-card {

        background: linear-gradient(145deg, rgba(255,255,255,0.94), rgba(255,255,255,0.94));

        border: 1px solid rgba(35,70,110,0.208);

        border-radius: 15px;

        padding: 20px;

        box-shadow:
            0 5px 25px
            rgba(16,42,67,0.011);

        animation:
            fadeUp .9s ease;

    }


    @keyframes fadeUp {

        from {

            opacity: 0;

            transform:
                translateY(20px);

        }

        to {

            opacity: 1;

            transform:
                translateY(0);

        }

    }


    .table-header {

        display: flex;

        justify-content: space-between;

        align-items: center;

        margin-bottom: 18px;

    }


    .table-header h3 {

        font-family: "Poppins";

        font-size: 15px;

    }


    .table-header span {

        font-size: 10px;

        color: #5c748c;

    }


    .table-wrapper {

        overflow-x: auto;

    }


    table {

        width: 100%;

        border-collapse: collapse;

        min-width: 900px;

    }


    thead {

        background:
            rgba(37,140,255,.10);

    }


    th {

        text-align: left;

        padding: 13px 12px;

        font-size: 10px;

        color: #5c748c;

        text-transform: uppercase;

        letter-spacing: .3px;

    }


    td {

        padding: 13px 12px;

        border-bottom:
            1px solid rgba(35,70,110,0.208);

        font-size: 11px;

        transition:
            all .2s ease;

    }


    tbody tr {

        animation:
            rowEnter .5s ease both;

    }


    @keyframes rowEnter {

        from {

            opacity: 0;

            transform:
                translateX(-10px);

        }

        to {

            opacity: 1;

            transform:
                translateX(0);

        }

    }


    tbody tr:hover {

        background:
            rgba(37,140,255,.07);

    }


    tbody tr:hover td {

        color: #115d9c;

    }


    .badge {

        display: inline-flex;

        padding: 5px 9px;

        border-radius: 20px;

        background: rgba(37,140,255,.12);

        color: #115d9c;

        font-size: 10px;

        font-weight: 600;

    }


    .badge:hover {

        transform:
            scale(1.08);

        background:
            rgba(37,140,255,.22);

    }


    .number {

        font-weight: 600;

        color: #115f9c;

    }


    /* =========================================================
    EMPTY
    ========================================================= */

    .empty {

        text-align: center;

        padding: 50px 20px;

        color: #5c748c;

    }


    .empty-icon {

        font-size: 45px;

        margin-bottom: 10px;

        animation:
            emptyFloat 2.5s ease-in-out infinite;

    }


    @keyframes emptyFloat {

        0%,
        100% {

            transform:
                translateY(0);

        }

        50% {

            transform:
                translateY(-8px);

        }

    }


    .empty strong {

        display: block;

        color: #334155;

        margin-bottom: 5px;

    }


    /* =========================================================
    FOOTER
    ========================================================= */

    .report-footer {

        margin-top: 20px;

        text-align: center;

        font-size: 10px;

        color: #5c748c;

        padding-bottom: 15px;

    }


    /* =========================================================
    LOADING
    ========================================================= */

    .page-loader {

        position: fixed;

        inset: 0;

        background:
            #eef3f9;

        z-index: 9999;

        display: flex;

        align-items: center;

        justify-content: center;

        flex-direction: column;

        color: #16293f;

        transition:
            opacity .5s ease,
            visibility .5s ease;

    }


    .page-loader.hide {

        opacity: 0;

        visibility: hidden;

    }


    .loader-icon {

        width: 55px;

        height: 55px;

        border:
            4px solid
            rgba(23,52,86,0.120);

        border-top-color:
            #dbe5f0;

        border-radius: 50%;

        animation:
            spin 1s linear infinite;

    }


    @keyframes spin {

        to {

            transform:
                rotate(360deg);

        }

    }


    .loader-text {

        margin-top: 15px;

        font-family: "Poppins";

        font-size: 13px;

    }


    /* =========================================================
    RESPONSIVE
    ========================================================= */

    @media(max-width:1100px) {

        .stats {

            grid-template-columns:
                repeat(3,1fr);

        }

    }


    @media(max-width:800px) {

        .main {

            padding: 15px 2.5%;

        }


        .stats {

            grid-template-columns:
                repeat(2,1fr);

        }


        .report-header-top {

            flex-direction:
                column;

            align-items:
                flex-start;

        }


        .report-info {

            text-align:
                left;

        }


        .filter-form {

            flex-direction:
                column;

            align-items:
                stretch;

        }


        .form-group select {

            width: 100%;

            min-width: 0;

        }


        .btn {

            width: 100%;

        }

    }


    @media(max-width:500px) {

        .main {

            padding: 10px;

        }


        .stats {

            grid-template-columns:
                1fr;

        }


        .title h1 {

            font-size: 20px;

        }


        .clock {

            font-size: 10px;

            padding:
                8px 10px;

        }


        .report-header {

            padding: 18px;

        }


        .report-title h2 {

            font-size: 17px;

        }

    }


    /* =========================================================
    PRINT
    ========================================================= */

    @media print {

        @page {

            size: A4 landscape;

            margin: 10mm;

        }


        body {

            background: white;

            animation: none;

        }


        .page-loader,
        .topbar,
        .page-head,
        .filter-card,
        .btn {

            display: none !important;

        }


        .main {

            margin: 0;

            padding: 0;

        }


        .report-header {

            color: #000;

            background: white;

            animation: none;

            border:
                2px solid #ced9e3;

            margin-bottom: 12px;

            box-shadow: none;

        }


        .report-title h2 {

            color: #0057a8;

        }


        .report-title p,
        .report-info {

            color: #334155;

        }


        .stats {

            grid-template-columns:
                repeat(5,1fr);

        }


        .stat,
        .table-card {

            box-shadow: none;

            border:
                1px solid #ddd;

            animation: none;

        }


        table {

            min-width: 0;

        }


        th,
        td {

            font-size: 8px;

            padding: 7px;

        }


        tbody tr {

            animation: none;

        }


        .report-footer {

            color: #555;

            margin-top: 10px;

        }

    }

    </style>

    </head>


    <body>


    <!-- =========================================================
        LOADING
    ========================================================= -->

    <div
        class="page-loader"
        id="pageLoader"
    >

        <div class="loader-icon"></div>

        <div class="loader-text">
            Memuat Laporan BMKG...
        </div>

    </div>


    <!-- =========================================================
        SIDEBAR
    ========================================================= -->

    <?php include __DIR__ . "/includes/topbar.php"; ?>


    <!-- =========================================================
        MAIN
    ========================================================= -->

    <main class="main">


        <!-- PAGE HEAD -->

        <div class="page-head">

            <div class="title">

                <h1>
                    Laporan Pemantauan
                </h1>

                <p>
                    Rekapitulasi prakiraan resmi BMKG untuk
                    <?= htmlspecialchars(bmkg_ringkas_lokasi($lokasiAktif), ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars(bmkg_label_sumber($sumberCuaca), ENT_QUOTES, 'UTF-8') ?>
                </p>

            </div>


            <div class="clock">

                🕐

                <span id="clock">
                    Memuat waktu...
                </span>

            </div>

        </div>


        <!-- FILTER -->

        <div class="filter-card">

            <div class="filter-title">

                🔎 Filter Laporan

            </div>


            <form
                method="GET"
                class="filter-form"
            >


                <div class="form-group">

                    <label>
                        Pilih Tanggal
                    </label>


                    <select
                        name="tanggal"
                    >

                        <option value="">
                            Semua Tanggal
                        </option>


                        <?php foreach (
                            $daftarTanggal
                            as $tanggal
                        ): ?>

                            <option
                                value="<?= htmlspecialchars(
                                    $tanggal
                                ) ?>"

                                <?= $filterTanggal === $tanggal
                                    ? "selected"
                                    : "" ?>
                            >

                                <?= htmlspecialchars(
                                    $tanggal
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    🔍 Tampilkan

                </button>


                <a
                    href="laporan.php"
                    class="btn btn-light"
                >

                    ↻ Reset

                </a>


                <button
                    type="button"
                    onclick="window.print()"
                    class="btn btn-print"
                >

                    🖨️ Cetak / PDF

                </button>


                <a
                    href="laporan.php?<?= http_build_query([
                        'tanggal' => $filterTanggal,
                        'export' => 'excel'
                    ]) ?>"
                    class="btn btn-excel"
                >

                    📊 Excel

                </a>


            </form>

        </div>


        <!-- REPORT HEADER -->

        <div class="report-header">

            <div class="report-header-top">


                <div class="report-title">

                    <h2>
                        LAPORAN MONITORING CUACA
                    </h2>

                    <p>
                        Sistem Informasi Monitoring Cuaca
                        BMKG Kemayoran
                    </p>

                </div>


                <div class="report-info">

                    <div>

                        Dibuat:
                        <?= $tanggalLaporan ?>

                    </div>


                    <div style="margin-top:5px">

                        Periode:

                        <?= $filterTanggal !== ""
                            ? htmlspecialchars(
                                $filterTanggal
                            )
                            : "Semua Data" ?>

                    </div>

                </div>


            </div>

        </div>


        <!-- STATISTIK -->

        <section class="stats">


            <div class="stat">

                <div class="stat-label">
                    Total Data
                </div>

                <div class="stat-value">

                    <?= $totalData ?>

                </div>

                <div class="stat-unit">
                    record
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Suhu Rata-rata
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $rataSuhu,
                        1
                    ) ?>

                </div>

                <div class="stat-unit">
                    °C
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Suhu Maksimum
                </div>

                <div class="stat-value">

                    <?= $suhuMax !== null
                        ? number_format(
                            $suhuMax,
                            1
                        )
                        : "-"
                    ?>

                </div>

                <div class="stat-unit">
                    °C
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Kelembapan
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $rataKelembapan,
                        1
                    ) ?>

                </div>

                <div class="stat-unit">
                    %
                </div>

            </div>


            <div class="stat">

                <div class="stat-label">
                    Curah Hujan
                </div>

                <div class="stat-value">

                    <?= number_format(
                        $totalHujan,
                        1
                    ) ?>

                </div>

                <div class="stat-unit">
                    mm
                </div>

            </div>


        </section>


        <!-- TABLE -->

        <div class="table-card">


            <div class="table-header">

                <h3>
                    Detail Data Cuaca
                </h3>


                <span>

                    <?= $totalData ?>
                    data ditemukan

                </span>

            </div>


            <?php if ($totalData > 0): ?>


                <div class="table-wrapper">


                    <table>


                        <thead>

                        <tr>

                            <th>
                                No
                            </th>

                            <th>
                                Tanggal
                            </th>

                            <th>
                                Waktu
                            </th>

                            <th>
                                Suhu
                            </th>

                            <th>
                                Kelembapan
                            </th>

                            <th>
                                Curah Hujan
                            </th>

                            <th>
                                Kecepatan Angin
                            </th>

                            <th>
                                Arah Angin
                            </th>

                            <th>
                                Kondisi Cuaca
                            </th>

                        </tr>

                        </thead>


                        <tbody>


                        <?php

                        $no = 1;

                        foreach (
                            $dataFilter
                            as $item
                        ):

                        ?>


                        <tr>


                            <td class="number">

                                <?= $no++ ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["tanggal"]
                                    ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["waktu"]
                                    ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $item["suhu"]
                                        ?? "-"
                                    ) ?>

                                    °C

                                </strong>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["kelembapan"]
                                    ?? "-"
                                ) ?>

                                %

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["curah_hujan"]
                                    ?? "-"
                                ) ?>

                                mm

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["kecepatan_angin"]
                                    ?? "-"
                                ) ?>

                                km/jam

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $item["arah_angin"]
                                    ?? "-"
                                ) ?>

                            </td>


                            <td>

                                <span class="badge">

                                    <?= htmlspecialchars(
                                        $item["kondisi_cuaca"]
                                        ?? "-"
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                        <?php endforeach; ?>


                        </tbody>


                    </table>


                </div>


            <?php else: ?>


                <div class="empty">

                    <div class="empty-icon">
                        📄
                    </div>

                    <strong>
                        Data laporan belum tersedia
                    </strong>

                    <span>
                        Belum ada data cuaca
                        sesuai filter yang dipilih.
                    </span>

                </div>


            <?php endif; ?>


        </div>


        <!-- FOOTER -->

        <div class="report-footer">

            Sistem Monitoring Cuaca BMKG
            • Data bersumber dari data monitoring
            yang tersedia pada sistem.

        </div>


    </main>


    <script>

    /* =========================================================
    LOADING PAGE
    ========================================================= */

    window.addEventListener(
        "load",
        function() {

            setTimeout(
                function() {

                    const loader =
                        document.getElementById(
                            "pageLoader"
                        );

                    if (loader) {

                        loader.classList.add(
                            "hide"
                        );

                    }

                },
                500
            );

        }
    );


    /* =========================================================
    JAM REALTIME
    ========================================================= */

    function updateClock()
    {

        const now =
            new Date();


        const tanggal =
            now.toLocaleDateString(
                "id-ID",
                {
                    weekday: "long",
                    day: "2-digit",
                    month: "long",
                    year: "numeric"
                }
            );


        const waktu =
            now.toLocaleTimeString(
                "id-ID",
                {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit"
                }
            );


        const clock =
            document.getElementById(
                "clock"
            );


        if (clock) {

            clock.innerHTML =
                tanggal +
                " • " +
                waktu +
                " WIB";

        }

    }


    updateClock();


    setInterval(
        updateClock,
        1000
    );


    /* =========================================================
    EFEK BUTTON KLIK
    ========================================================= */

    document
        .querySelectorAll(".btn")
        .forEach(
            function(button) {

                button.addEventListener(
                    "click",
                    function() {

                        this.style.transform =
                            "scale(.96)";

                        setTimeout(
                            () => {

                                this.style.transform =
                                    "";

                            },
                            120
                        );

                    }
                );

            }
        );


    /* =========================================================
    ANIMASI ANGKA STATISTIK
    ========================================================= */

    function animateNumber(element)
    {

        const text =
            element.textContent.trim();


        const number =
            parseFloat(
                text.replace(
                    ",",
                    "."
                )
            );


        if (
            isNaN(number) ||
            number <= 0
        ) {

            return;

        }


        const hasDecimal =
            text.includes(".") ||
            text.includes(",");


        let start = 0;

        const duration = 900;

        const startTime =
            performance.now();


        function update(currentTime)
        {

            const progress =
                Math.min(
                    (
                        currentTime -
                        startTime
                    ) /
                    duration,
                    1
                );


            const ease =
                1 -
                Math.pow(
                    1 - progress,
                    3
                );


            const current =
                start +
                (
                    number -
                    start
                ) *
                ease;


            element.textContent =
                hasDecimal
                ? current.toFixed(1)
                : Math.floor(current);


            if (progress < 1) {

                requestAnimationFrame(
                    update
                );

            }

        }


        requestAnimationFrame(
            update
        );

    }


    document
        .querySelectorAll(".stat-value")
        .forEach(
            function(element) {

                animateNumber(element);

            }
        );

    </script>


    </body>

    </html>
