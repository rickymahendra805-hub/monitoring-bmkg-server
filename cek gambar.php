<?php

$dataFile = __DIR__ . "/data/cuaca.json";
$wilayahFile = __DIR__ . "/data/wilayah.json";

$data = [];
$wilayah = [];

if (file_exists($dataFile)) {
    $json = file_get_contents($dataFile);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        $data = [];
    }
}

if (file_exists($wilayahFile)) {
    $jsonWilayah = file_get_contents($wilayahFile);
    $wilayah = json_decode($jsonWilayah, true);

    if (!is_array($wilayah)) {
        $wilayah = [];
    }
}

/*
|--------------------------------------------------------------------------
| Ambil data terakhir berdasarkan wilayah
|--------------------------------------------------------------------------
*/

$dataWilayah = [];

foreach ($data as $row) {

    if (!isset($row['wilayah'])) {
        continue;
    }

    $dataWilayah[$row['wilayah']] = $row;
}

$search = $_GET['search'] ?? '';

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Monitoring Wilayah - BMKG</title>

    <link rel="stylesheet"
          href="assets/css/style.css">

</head>

<body>

<div class="app">

    <!-- SIDEBAR -->

    <aside class="sidebar">

        <div class="brand">

            <h2>BMKG</h2>

            <span>Monitoring Cuaca</span>

        </div>

        <nav>

            <a href="dashboard.php">
                🏠 Dashboard
            </a>

            <a href="data_cuaca.php">
                🌦️ Data Cuaca
            </a>

            <a href="grafik.php">
                📊 Grafik
            </a>

            <a href="monitoring.php" class="active">
                📡 Monitoring
            </a>

            <a href="prediksi.php">
                🔮 Prediksi
            </a>

            <a href="laporan.php">
                📄 Laporan
            </a>

        </nav>

        <div class="sidebar-bottom">

            <a href="logout.php">
                🚪 Keluar
            </a>

        </div>

    </aside>


    <!-- MAIN -->

    <main class="main">

        <header class="topbar">

            <strong>Monitoring Wilayah Indonesia</strong>

            <div id="clock"></div>

        </header>


        <!-- HERO -->

        <section class="hero">

            <div>

                <span>
                    BADAN METEOROLOGI, KLIMATOLOGI, DAN GEOFISIKA
                </span>

                <h1>
                    Monitoring Cuaca Indonesia
                </h1>

                <p>
                    Monitoring kondisi cuaca berdasarkan
                    wilayah yang tersedia pada sistem.
                </p>

            </div>

        </section>


        <!-- SEARCH -->

        <section class="panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Daftar Wilayah
                    </h2>

                    <p>
                        Terdapat <?= count($wilayah) ?> provinsi
                        yang terdaftar.
                    </p>

                </div>

            </div>


            <form method="GET" class="search">

                <input
                    type="text"
                    name="search"
                    placeholder="Cari provinsi..."
                    value="<?= htmlspecialchars($search) ?>"
                >

                <button type="submit" class="btn">
                    🔎 Cari
                </button>

                <a href="monitoring.php" class="btn">
                    Reset
                </a>

            </form>

        </section>


        <!-- REGION -->

        <section class="region-grid">

            <?php

            $jumlahTampil = 0;

            foreach ($wilayah as $provinsi):

                $nama = $provinsi['nama'];

                if (
                    $search !== '' &&
                    stripos($nama, $search) === false
                ) {
                    continue;
                }

                $jumlahTampil++;

                $cuaca = $dataWilayah[$nama] ?? null;

            ?>

                <div class="panel">

                    <div style="display:flex; justify-content:space-between; align-items:center; gap:10px;">

                        <div>

                            <small>
                                <?= htmlspecialchars($provinsi['pulau']) ?>
                            </small>

                            <h2>
                                <?= htmlspecialchars($nama) ?>
                            </h2>

                        </div>

                        <?php if ($cuaca): ?>

                            <span>
                                🟢
                            </span>

                        <?php else: ?>

                            <span>
                                ⚪
                            </span>

                        <?php endif; ?>

                    </div>


                    <?php if ($cuaca): ?>

                        <div style="margin-top:20px;">

                            <h1>
                                <?= htmlspecialchars($cuaca['suhu']) ?> °C
                            </h1>

                            <p>
                                <?= htmlspecialchars($cuaca['kondisi_cuaca']) ?>
                            </p>

                        </div>


                        <div class="mini-grid">

                            <div>

                                <span>
                                    Kelembapan
                                </span>

                                <strong>
                                    <?= htmlspecialchars($cuaca['kelembapan']) ?>%
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Hujan
                                </span>

                                <strong>
                                    <?= htmlspecialchars($cuaca['curah_hujan']) ?> mm
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Angin
                                </span>

                                <strong>
                                    <?= htmlspecialchars($cuaca['kecepatan_angin']) ?> km/jam
                                </strong>

                            </div>


                            <div>

                                <span>
                                    Arah
                                </span>

                                <strong>
                                    <?= htmlspecialchars($cuaca['arah_angin']) ?>
                                </strong>

                            </div>

                        </div>

                    <?php else: ?>

                        <div style="margin-top:20px;">

                            <h3>
                                Data belum tersedia
                            </h3>

                            <p>
                                Belum ada data cuaca untuk wilayah ini.
                            </p>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>


            <?php if ($jumlahTampil === 0): ?>

                <div class="panel">

                    <h2>
                        Wilayah tidak ditemukan
                    </h2>

                    <p>
                        Silakan coba nama provinsi lainnya.
                    </p>

                </div>

            <?php endif; ?>

        </section>


        <footer>

            Monitoring BMKG &copy; <?= date('Y') ?>

        </footer>

    </main>

</div>


<script src="assets/js/app.js"></script>

</body>

</html>