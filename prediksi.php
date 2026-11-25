

<?php

/* ============================================================

   PREDIKSI CUACA BMKG - MONITORING BMKG KEMAYORAN

   File: prediksi.php



   Daftar wilayah : Wilayah.id

   Prakiraan cuaca: API resmi BMKG (kode adm4)

   Database       : Tidak digunakan

\============================================================ */



date_default_timezone_set('Asia/Jakarta');



/* ======================== FUNGSI UMUM ======================== */



function h($value) {

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');

}



function jsonKeluar($data, $status = 200) {

    http_response_code($status);

    header('Content-Type: application/json; charset=utf-8');

    header('Cache-Control: no-store');

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    exit;

}



function ambilJSON($url) {

    $hasil = false;

    $status = 0;



    if (function_exists('curl_init')) {

        $ch = curl_init($url);

        curl_setopt_array($ch, [

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_CONNECTTIMEOUT => 10,

            CURLOPT_TIMEOUT => 25,

            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_MAXREDIRS => 3,

            CURLOPT_SSL_VERIFYPEER => true,

            CURLOPT_SSL_VERIFYHOST => 2,

            CURLOPT_USERAGENT => 'MonitoringBMKG/1.0',

            CURLOPT_HTTPHEADER => ['Accept: application/json']

        ]);



        $hasil = curl_exec($ch);

        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

    } else {

        $context = stream_context_create([

            'http' => [

                'timeout' => 25,

                'ignore_errors' => true,

                'header' => "Accept: application/json\r\nUser-Agent: MonitoringBMKG/1.0\r\n"

            ],

            'ssl' => [

                'verify_peer' => true,

                'verify_peer_name' => true

            ]

        ]);



        $hasil = @file_get_contents($url, false, $context);



        if (isset($http_response_header)) {

            foreach ($http_response_header as $header) {

                if (preg_match('/^HTTP\/\S+\s+(\d+)/i', $header, $match)) {

                    $status = (int)$match[1];

                }

            }

        }

    }



    if ($hasil === false || $hasil === '' || ($status && ($status < 200 || $status >= 300))) {

        return null;

    }



    $json = json_decode($hasil, true);

    return is_array($json) ? $json : null;

}



/* ============================================================

   ENDPOINT PROXY WILAYAH

   Browser mengakses PHP sendiri, PHP meneruskan ke Wilayah.id.

\============================================================ */



if (isset($_GET['ajax'])) {

    $route = trim((string)$_GET['ajax'], '/');



    $polaValid = [

            '/^provinces\.json$/',

            '/^regencies\/\d{2}\.json$/',

            '/^districts\/\d{2}\.\d{2}\.json$/',

            '/^villages\/\d{2}\.\d{2}\.\d{2}\.json$/'

    ];



    $valid = false;

    foreach ($polaValid as $pola) {

        if (preg_match($pola, $route)) {

            $valid = true;

            break;

        }

    }



    if (!$valid) {

        jsonKeluar([

            'error' => true,

            'message' => 'Permintaan daftar wilayah tidak valid.'

        ], 400);

    }



    $hasil = ambilJSON('https://wilayah.id/api/' . $route);



    if (!$hasil || !isset($hasil['data']) || !is_array($hasil['data'])) {

        jsonKeluar([

            'error' => true,

            'message' => 'Tidak bisa mengambil daftar wilayah. Periksa koneksi internet dan PHP Laragon.'

        ], 502);

    }



    jsonKeluar($hasil);

}



/* ======================== DATA FORM ======================== */



$adm4 = trim((string)($_GET['adm4'] ?? ''));

if ($adm4 === '') {
    $adm4 = trim((string)($_COOKIE['bmkg_adm4'] ?? ''));
}

$namaProvinsi = trim((string)($_GET['nama_provinsi'] ?? ($_COOKIE['bmkg_provinsi'] ?? '')));

$namaKabupaten = trim((string)($_GET['nama_kabupaten'] ?? ($_COOKIE['bmkg_kotkab'] ?? '')));

$namaKecamatan = trim((string)($_GET['nama_kecamatan'] ?? ($_COOKIE['bmkg_kecamatan'] ?? '')));

$namaDesa = trim((string)($_GET['nama_desa'] ?? ($_COOKIE['bmkg_desa'] ?? '')));

$kodeProvinsi = trim((string)($_GET['provinsi'] ?? ($_COOKIE['bmkg_kode_provinsi'] ?? '')));

$kodeKabupaten = trim((string)($_GET['kabupaten'] ?? ($_COOKIE['bmkg_kode_kabupaten'] ?? '')));

$kodeKecamatan = trim((string)($_GET['kecamatan'] ?? ($_COOKIE['bmkg_kode_kecamatan'] ?? '')));



$dataCuaca = [];

$lokasiBMKG = [];

$error = '';

$waktuAnalisis = '';



if ($adm4 !== '' && !preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', $adm4)) {

    $error = 'Kode kelurahan/desa tidak valid. Silakan pilih ulang wilayah.';

    $adm4 = '';

}



/* ======================== API RESMI BMKG ======================== */



if ($adm4 !== '') {

    $urlBMKG = 'https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4='

        . rawurlencode($adm4);



    $respons = ambilJSON($urlBMKG);



    if (!$respons) {

        $error = 'Server gagal mengambil data BMKG. Periksa koneksi internet, '

            . 'cURL atau konfigurasi PHP Laragon, lalu coba kembali.';

    } elseif (empty($respons['data']) || !is_array($respons['data'])) {

        $error = 'Prakiraan cuaca belum tersedia untuk wilayah ini.';

    } else {

        foreach ($respons['data'] as $blok) {

            if (!empty($blok['lokasi']) && is_array($blok['lokasi'])) {

                $lokasiBMKG = $blok['lokasi'];

            }



            if (!empty($blok['cuaca']) && is_array($blok['cuaca'])) {

                foreach ($blok['cuaca'] as $kelompok) {

                    if (!is_array($kelompok)) {

                        continue;

                    }



                    foreach ($kelompok as $item) {

                        if (is_array($item)) {

                            $dataCuaca[] = $item;

                        }

                    }

                }

            }

        }



        if (isset($dataCuaca[0]['analysis_date'])) {

            $waktuAnalisis = $dataCuaca[0]['analysis_date'];

        }



        if (!$dataCuaca) {

            $error = 'Respons BMKG diterima, tetapi data prakiraan belum tersedia.';

        }

    }

}



/* ======================== IKON DAN FORMAT ======================== */



function ikonCuaca($kondisi) {

    $k = strtolower((string)$kondisi);



    if (strpos($k, 'petir') !== false || strpos($k, 'thunder') !== false) return '⛈️';

    if (strpos($k, 'hujan') !== false || strpos($k, 'rain') !== false) return '🌧️';

    if (strpos($k, 'kabut') !== false || strpos($k, 'fog') !== false) return '🌫️';

    if (strpos($k, 'berawan') !== false || strpos($k, 'cloud') !== false || strpos($k, 'mendung') !== false) return '☁️';

    if (strpos($k, 'cerah') !== false || strpos($k, 'clear') !== false || strpos($k, 'sunny') !== false) return '☀️';



    return '🌤️';

}



function formatWaktu($waktu) {

    if (!$waktu) return '-';



    try {

        return (new DateTime($waktu))->format('d-m-Y H:i');

    } catch (Exception $e) {

        return (string)$waktu;

    }

}



function tampilAngka($nilai, $satuan = '') {

    if ($nilai === null || $nilai === '') return '-';

    return h($nilai) . $satuan;

}



function rataRata($array) {

    return count($array) ? round(array_sum($array) / count($array), 1) : null;

}



/* ======================== STATISTIK PRAKIRAAN ======================== */



$suhu = [];

$kelembapan = [];

$angin = [];

$hujan = 0;



foreach ($dataCuaca as $item) {

    if (isset($item['t']) && is_numeric($item['t'])) $suhu[] = (float)$item['t'];

    if (isset($item['hu']) && is_numeric($item['hu'])) $kelembapan[] = (float)$item['hu'];

    if (isset($item['ws']) && is_numeric($item['ws'])) $angin[] = (float)$item['ws'];



    $kondisi = strtolower((string)($item['weather_desc'] ?? ''));



    if (strpos($kondisi, 'hujan') !== false || strpos($kondisi, 'petir') !== false) {

        $hujan++;

    }

}



$rataSuhu = rataRata($suhu);

$rataKelembapan = rataRata($kelembapan);

$rataAngin = rataRata($angin);



$judulWilayah = implode(' • ', array_filter([

    $namaDesa,

    $namaKecamatan,

    $namaKabupaten,

    $namaProvinsi

]));



$namaTampilan = $namaDesa ?: ($lokasiBMKG['desa'] ?? 'Wilayah terpilih');

$rincianPopup = $judulWilayah !== '' ? $judulWilayah : implode(' • ', array_filter([
    (string)($lokasiBMKG['kecamatan'] ?? ''),
    (string)($lokasiBMKG['kotkab'] ?? ''),
    (string)($lokasiBMKG['provinsi'] ?? '')
], static function ($bagian) use ($namaTampilan) {
    return $bagian !== '' && strcasecmp($bagian, $namaTampilan) !== 0;
}));

/* Isi popup dipindah ke PHP agar bisa di-escape: nilainya datang dari
 * API BMKG dan cookie, bukan dari kode ini. */
$isiPopupPeta = '<b>' . htmlspecialchars($namaTampilan, ENT_QUOTES, 'UTF-8') . '</b>'
    . ($rincianPopup !== '' ? '<br>' . htmlspecialchars($rincianPopup, ENT_QUOTES, 'UTF-8') : '');



/* ======================== KOORDINAT PETA ======================== */

$latLokasi = (float)($lokasiBMKG['lat'] ?? ($_COOKIE['bmkg_lat'] ?? 0));

$lonLokasi = (float)($lokasiBMKG['lon'] ?? ($_COOKIE['bmkg_lon'] ?? 0));

$adaKoordinat = $latLokasi >= -11 && $latLokasi <= 41

    && $lonLokasi >= 92 && $lonLokasi <= 141

    && ($latLokasi != 0 || $lonLokasi != 0);



?>

<!DOCTYPE html>

<html lang="id">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Prakiraan Cuaca | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.css">



<style>

:root{

 --bg:#eef3f9;--panel:#ffffff;--panel2:#eef3f9;--border:#ced7e3;

 --text:#16293f;--muted:#5c748c;--blue:#0f61bd;--cyan:#10a6bc;--green:#24a873;

}

*{box-sizing:border-box}

body{margin:0;background:radial-gradient(ellipse at top right,#f5f9fd,transparent 38%),var(--bg);color:var(--text);font-family:Inter,Arial,sans-serif;min-height:100vh}

a{color:inherit;text-decoration:none}

.container{width:min(1500px,100%);margin:0 auto;padding:30px 4% 55px}

.eyebrow{color:var(--cyan);font-size:11px;font-weight:800;letter-spacing:2px;text-transform:uppercase}

h1,h2,h3{font-family:Poppins,sans-serif;margin-top:0}

h1{font-size:clamp(26px,3vw,39px);margin:9px 0}

.subtitle{color:var(--muted);font-size:14px;line-height:1.7;max-width:850px}

.panel{background:linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));border:1px solid var(--border);border-radius:18px;padding:22px;margin-top:22px;box-shadow:0 15px 40px #0002}

.panel-title{font-size:18px;margin:0 0 7px}

.panel-desc{font-size:12px;color:var(--muted);line-height:1.7;margin-bottom:18px}

.form-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}

.field label{display:block;color:#16293f;font-size:12px;font-weight:700;margin-bottom:8px}

select,.btn{width:100%;min-height:45px;border-radius:10px;border:1px solid #ced7e3;background:#eef3f9;color:var(--text);padding:11px 12px;font:inherit;font-size:13px}

select:disabled{opacity:.55;cursor:not-allowed}

select:focus{outline:2px solid #258cff66;border-color:var(--blue)}

.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:17px}

.btn{width:auto;display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;font-weight:700;padding:12px 17px}

.btn-primary{background:linear-gradient(110deg,#1769d2,#258cff);border-color:#ced8e3}

.btn-secondary{background:#f5f9fd}

.btn:disabled{opacity:.5;cursor:not-allowed}

.helper{color:var(--muted);font-size:11px;line-height:1.7;margin-top:12px;overflow-wrap:anywhere}

.alert{padding:14px 16px;border-radius:12px;border:1px solid #e3d2ce;background:#ffffff;color:#16293f;font-size:13px;margin-top:18px;line-height:1.7}

.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:15px;margin-top:20px}

.stat{padding:18px;border:1px solid var(--border);border-radius:15px;background:var(--panel)}

.stat-top{display:flex;align-items:center;justify-content:space-between;color:var(--muted);font-size:12px;gap:8px}

.stat-icon{width:36px;height:36px;display:grid;place-items:center;border-radius:11px;background:#eaf2fa;color:var(--cyan)}

.stat-value{font-family:Poppins,sans-serif;font-size:27px;font-weight:800;margin-top:15px}

.stat-note{font-size:11px;color:var(--muted);margin-top:5px}

.forecast-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}

.forecast{padding:17px;border-radius:15px;border:1px solid var(--border);background:linear-gradient(150deg,#f5f9fd,#ffffff);min-width:0}

.forecast-time{color:var(--muted);font-size:11px}

.weather-icon{font-size:36px;margin:17px 0 8px}

.weather-name{font-size:13px;font-weight:800;min-height:38px;line-height:1.5}

.temp{font-family:Poppins,sans-serif;font-size:30px;font-weight:800;margin:8px 0}

.weather-detail{display:grid;gap:9px;margin-top:14px;padding-top:13px;border-top:1px solid var(--border)}

.detail-row{display:flex;justify-content:space-between;gap:8px;font-size:11px}

.detail-row span:first-child{color:var(--muted)}

.detail-row b{text-align:right;overflow-wrap:anywhere}

.section-head{display:flex;align-items:flex-end;justify-content:space-between;gap:15px;flex-wrap:wrap}

.pill{display:inline-flex;gap:7px;align-items:center;border-radius:30px;padding:8px 12px;background:#ffffff;border:1px solid #cee3df;color:#179669;font-size:11px;font-weight:700}

.empty{text-align:center;padding:40px 15px;color:var(--muted)}

.empty i{font-size:32px;color:var(--cyan);margin-bottom:15px}

footer{border-top:1px solid var(--border);color:var(--muted);font-size:11px;line-height:1.8;padding:22px;text-align:center}

footer a{color:var(--cyan)}

.lokasi-wrap{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:16px;align-items:stretch;margin-top:16px}

.lokasi-map{height:280px;min-height:280px;border-radius:14px;border:1px solid var(--border);overflow:hidden;background:#eef3f9}

.lokasi-penanda{width:19px;height:19px;border-radius:50%;background:#0f61bd;border:3px solid #0d94ad;box-shadow:0 1px 5px rgba(0,0,0,.45);cursor:pointer}

.lokasi-map .maplibregl-ctrl-group{border-radius:9px;box-shadow:0 1px 4px rgba(22,41,63,.18)}

.lokasi-map .maplibregl-ctrl-attrib{font-size:10px;background:rgba(255,255,255,.82)}

.lokasi-map .maplibregl-popup-content{padding:9px 12px;border-radius:11px;font-size:12px;line-height:1.6;box-shadow:0 3px 12px rgba(22,41,63,.2)}

.lokasi-info{display:grid;gap:9px;align-content:start;font-size:12px}

.lokasi-info .baris{display:flex;justify-content:space-between;gap:10px;padding:11px 13px;border:1px solid var(--border);border-radius:12px;background:var(--panel)}

.lokasi-info .baris span{color:var(--muted)}

.lokasi-info .baris b{text-align:right;overflow-wrap:anywhere}

@media(max-width:900px){.lokasi-wrap{grid-template-columns:1fr}}

@media(max-width:1050px){.form-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.forecast-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}}

@media(max-width:700px){.container{padding:23px 13px}.panel{padding:16px}.forecast-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.form-grid{grid-template-columns:1fr}}

@media(max-width:400px){.forecast-grid,.stats{grid-template-columns:1fr}}

</style>

    <link rel="stylesheet" href="assets/css/topbar.css?v=4">
</head>

<body>



<?php include __DIR__ . '/includes/topbar.php'; ?>



<main class="container">

 <div class="eyebrow"><i class="fa-solid fa-satellite-dish"></i> Data prakiraan resmi</div>

 <h1>Prediksi & Prakiraan Cuaca</h1>

 <p class="subtitle">Pilih wilayah Indonesia sampai tingkat kelurahan/desa. Data prakiraan diambil dari API BMKG sesuai kode wilayah yang dipilih.</p>



 <section class="panel">

  <h2 class="panel-title"><i class="fa-solid fa-map-location-dot" style="color:var(--cyan)"></i> Cari Wilayah</h2>

  <div class="panel-desc">Klik dropdown untuk memilih wilayah. Tidak perlu mengetik nama wilayah. Pilihan berikutnya akan dimuat otomatis.</div>



  <form id="wilayahForm" method="get" action="prediksi.php">

   <input type="hidden" name="adm4" id="adm4" value="<?= h($adm4) ?>">

   <input type="hidden" name="nama_provinsi" id="nama_provinsi" value="<?= h($namaProvinsi) ?>">

   <input type="hidden" name="nama_kabupaten" id="nama_kabupaten" value="<?= h($namaKabupaten) ?>">

   <input type="hidden" name="nama_kecamatan" id="nama_kecamatan" value="<?= h($namaKecamatan) ?>">

   <input type="hidden" name="nama_desa" id="nama_desa" value="<?= h($namaDesa) ?>">

   <input type="hidden" name="provinsi" id="kode_provinsi" value="<?= h($kodeProvinsi) ?>">

   <input type="hidden" name="kabupaten" id="kode_kabupaten" value="<?= h($kodeKabupaten) ?>">

   <input type="hidden" name="kecamatan" id="kode_kecamatan" value="<?= h($kodeKecamatan) ?>">



   <div class="form-grid">

    <div class="field"><label for="provinsi">1. Provinsi</label><select id="provinsi" required><option value="">Memuat provinsi...</option></select></div>

    <div class="field"><label for="kabupaten">2. Kabupaten / Kota</label><select id="kabupaten" disabled><option value="">Pilih provinsi dahulu</option></select></div>

    <div class="field"><label for="kecamatan">3. Kecamatan</label><select id="kecamatan" disabled><option value="">Pilih kabupaten/kota dahulu</option></select></div>

    <div class="field"><label for="desa">4. Kelurahan / Desa</label><select id="desa" disabled><option value="">Pilih kecamatan dahulu</option></select></div>

   </div>



   <div class="actions">

    <button type="submit" class="btn btn-primary" id="lihatCuaca" disabled><i class="fa-solid fa-cloud-sun"></i> Tampilkan Data BMKG</button>

    <button type="button" class="btn btn-secondary" id="resetWilayah"><i class="fa-solid fa-rotate-left"></i> Pilih Ulang</button>

    <button type="button" class="btn btn-secondary" data-deteksi-lokasi><i class="fa-solid fa-location-crosshairs"></i> Gunakan Lokasi Saya</button>

   </div>

   <div class="helper" id="statusWilayah" role="status">Menyiapkan daftar wilayah...</div>

  </form>

 </section>



 <?php if ($error !== ''): ?>

  <div class="alert"><i class="fa-solid fa-triangle-exclamation"></i> <?= h($error) ?></div>

 <?php endif; ?>



 <?php if ($adm4 !== '' && !$error && count($dataCuaca)): ?>

  <section class="panel">

   <div class="section-head">

    <div><div class="eyebrow">Wilayah terpilih</div><h2 class="panel-title" style="margin-top:8px"><?= h($namaTampilan) ?></h2><div class="panel-desc" style="margin:0"><?= h($judulWilayah) ?></div></div>

    <span class="pill"><i class="fa-solid fa-circle-check"></i> Data BMKG diterima</span>

   </div>

   <div class="helper">Kode wilayah: <?= h($adm4) ?><?php if ($waktuAnalisis): ?> · Analisis: <?= h($waktuAnalisis) ?> UTC<?php endif; ?></div>

   <?php if ($adaKoordinat): ?>

    <div class="lokasi-wrap">

     <div id="petaLokasi" class="lokasi-map" role="region" aria-label="Peta lokasi <?= h($namaTampilan) ?>"></div>

     <div class="lokasi-info">

      <div class="baris"><span>Kelurahan / Desa</span><b><?= h($namaTampilan) ?></b></div>

      <div class="baris"><span>Kecamatan</span><b><?= h($namaKecamatan ?: ($lokasiBMKG['kecamatan'] ?? '-')) ?></b></div>

      <div class="baris"><span>Koordinat</span><b><?= number_format($latLokasi, 4) ?>°, <?= number_format($lonLokasi, 4) ?>°</b></div>

      <div class="baris"><span>Kode wilayah</span><b><?= h($adm4) ?></b></div>

     </div>

    </div>

    <div class="helper">Titik peta menunjukkan letak kelurahan/desa terpilih. Sumber koordinat: blok <code>lokasi</code> pada API BMKG.</div>

   <?php else: ?>

    <div class="helper">Koordinat wilayah ini belum tersedia dari API BMKG, sehingga peta lokasi tidak dapat ditampilkan.</div>

   <?php endif; ?>

  </section>



  <div class="stats">

   <div class="stat"><div class="stat-top"><span>Rata-rata Suhu</span><span class="stat-icon"><i class="fa-solid fa-temperature-half"></i></span></div><div class="stat-value"><?= $rataSuhu !== null ? h($rataSuhu).'°C' : '-' ?></div><div class="stat-note">Dari data prakiraan yang diterima</div></div>

   <div class="stat"><div class="stat-top"><span>Rata-rata Kelembapan</span><span class="stat-icon"><i class="fa-solid fa-droplet"></i></span></div><div class="stat-value"><?= $rataKelembapan !== null ? h($rataKelembapan).'%' : '-' ?></div><div class="stat-note">Kelembapan udara</div></div>

   <div class="stat"><div class="stat-top"><span>Rata-rata Kecepatan Angin</span><span class="stat-icon"><i class="fa-solid fa-wind"></i></span></div><div class="stat-value"><?= $rataAngin !== null ? h($rataAngin).' km/j' : '-' ?></div><div class="stat-note">Kecepatan angin prakiraan</div></div>

   <div class="stat"><div class="stat-top"><span>Periode Hujan / Petir</span><span class="stat-icon"><i class="fa-solid fa-cloud-showers-heavy"></i></span></div><div class="stat-value"><?= (int)$hujan ?></div><div class="stat-note">Periode prakiraan yang terindikasi hujan atau petir</div></div>

  </div>



  <section class="panel">

   <div class="section-head"><div><h2 class="panel-title">Prakiraan Cuaca Wilayah</h2><div class="panel-desc">Waktu dan parameter mengikuti respons BMKG.</div></div><span class="pill"><i class="fa-solid fa-calendar-days"></i> <?= count($dataCuaca) ?> data</span></div>

   <div class="forecast-grid">

    <?php foreach ($dataCuaca as $item):

      $kondisi = $item['weather_desc'] ?? $item['weather_desc_en'] ?? 'Tidak diketahui';

      $waktu = $item['local_datetime'] ?? $item['utc_datetime'] ?? '';

    ?>

     <article class="forecast">

      <div class="forecast-time"><i class="fa-regular fa-clock"></i> <?= h(formatWaktu($waktu)) ?></div>

      <div class="weather-icon"><?= ikonCuaca($kondisi) ?></div>

      <div class="weather-name"><?= h($kondisi) ?></div>

      <div class="temp"><?= tampilAngka($item['t'] ?? null, '°C') ?></div>

      <div class="weather-detail">

       <div class="detail-row"><span><i class="fa-solid fa-droplet"></i> Kelembapan</span><b><?= tampilAngka($item['hu'] ?? null, '%') ?></b></div>

       <div class="detail-row"><span><i class="fa-solid fa-wind"></i> Kecepatan angin</span><b><?= tampilAngka($item['ws'] ?? null, ' km/j') ?></b></div>

       <div class="detail-row"><span><i class="fa-regular fa-compass"></i> Arah angin</span><b><?= tampilAngka($item['wd'] ?? null) ?></b></div>

       <div class="detail-row"><span><i class="fa-solid fa-cloud"></i> Tutupan awan</span><b><?= tampilAngka($item['tcc'] ?? null, '%') ?></b></div>

       <div class="detail-row"><span><i class="fa-solid fa-eye"></i> Jarak pandang</span><b><?= tampilAngka($item['vs_text'] ?? null) ?></b></div>

      </div>

     </article>

    <?php endforeach; ?>

   </div>

   <div class="helper">Ini merupakan data prakiraan, bukan hasil pengukuran observasi langsung. Kondisi aktual dapat berbeda.</div>

  </section>



 <?php elseif ($adm4 === '' && !$error): ?>

  <section class="panel empty"><div><i class="fa-solid fa-map-location-dot"></i></div><h2 class="panel-title">Pilih Wilayah untuk Melihat Cuaca</h2><p>Pilih provinsi, kabupaten/kota, kecamatan, dan kelurahan/desa.</p><p>Tekan <b>Tampilkan Data BMKG</b> setelah wilayah dipilih.</p></section>

 <?php endif; ?>



 <section class="panel">

  <h2 class="panel-title"><i class="fa-solid fa-circle-info" style="color:var(--cyan)"></i> Sumber Data</h2>

  <div class="panel-desc" style="margin-bottom:0">Prakiraan cuaca bersumber dari BMKG. Daftar dan kode wilayah menggunakan Wilayah.id. Data hanya akan tampil jika API menyediakan prakiraan untuk kode wilayah yang dipilih.</div>

  <div class="actions">

   <a class="btn btn-secondary" href="https://data.bmkg.go.id/prakiraan-cuaca/" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Portal Prakiraan BMKG</a>

   <a class="btn btn-secondary" href="https://wilayah.id/" target="_blank" rel="noopener"><i class="fa-solid fa-map"></i> Referensi Wilayah</a>

  </div>

 </section>

</main>



<footer>

 <b>BMKG KEMAYORAN</b> · Sistem Monitoring Cuaca<br>

 Sumber prakiraan: <a href="https://data.bmkg.go.id/prakiraan-cuaca/" target="_blank" rel="noopener">BMKG</a>

 · Referensi wilayah: <a href="https://wilayah.id/" target="_blank" rel="noopener">Wilayah.id</a>

</footer>



<script>

/* ============================================================

   PEMUAT DAFTAR WILAYAH

   Semua permintaan dilewatkan melalui PHP lokal.

\============================================================ */



const el = {

    prov: document.getElementById('provinsi'),

    kab: document.getElementById('kabupaten'),

    kec: document.getElementById('kecamatan'),

    desa: document.getElementById('desa'),

    status: document.getElementById('statusWilayah'),

    submit: document.getElementById('lihatCuaca'),

    form: document.getElementById('wilayahForm')

};



const awal = {

    prov: <?= json_encode($kodeProvinsi) ?>,

    kab: <?= json_encode($kodeKabupaten) ?>,

    kec: <?= json_encode($kodeKecamatan) ?>,

    desa: <?= json_encode($adm4) ?>

};



let generasi = 0;



function status(teks, gagal = false) {

    el.status.textContent = teks;

    el.status.style.color = gagal ? '#d63549' : '#5c748c';

}



function resetSelect(select, teks) {

    select.replaceChildren();

    const option = document.createElement('option');

    option.value = '';

    option.textContent = teks;

    select.appendChild(option);

    select.disabled = true;

}



function isiSelect(select, daftar, placeholder, selected = '') {

    select.replaceChildren();



    const awalOption = document.createElement('option');

    awalOption.value = '';

    awalOption.textContent = placeholder;

    select.appendChild(awalOption);



    (daftar || []).forEach(item => {

        const option = document.createElement('option');

        option.value = String(item.code);

        option.textContent = String(item.name);

        if (String(item.code) === String(selected)) {

            option.selected = true;

        }

        select.appendChild(option);

    });



    select.disabled = !daftar || daftar.length === 0;

}



async function ambilWilayah(path) {

    // Buat URL ke file PHP ini, tanpa membawa parameter pencarian sebelumnya.

    const url = new URL(window.location.href);

    url.search = '';

    url.hash = '';

    url.searchParams.set('ajax', path);



    let response;



    try {

        response = await fetch(url.toString(), {

            method: 'GET',

            headers: { 'Accept': 'application/json' },

            cache: 'no-store'

        });

    } catch (error) {

        throw new Error('Tidak bisa menghubungi PHP lokal. Pastikan Laragon aktif dan buka halaman melalui localhost.');

    }



    let json;

    try {

        json = await response.json();

    } catch (error) {

        throw new Error('Respons server bukan JSON. Periksa apakah ada error PHP di halaman prediksi.php.');

    }



    if (!response.ok) {

        throw new Error(json.message || 'Server gagal memuat daftar wilayah.');

    }



    if (!json || !Array.isArray(json.data)) {

        throw new Error('Format data wilayah tidak sesuai.');

    }



    return json.data;

}



function simpanNamaWilayah() {

    document.getElementById('nama_provinsi').value =

        el.prov.selectedOptions[0]?.textContent || '';

    document.getElementById('nama_kabupaten').value =

        el.kab.selectedOptions[0]?.textContent || '';

    document.getElementById('nama_kecamatan').value =

        el.kec.selectedOptions[0]?.textContent || '';

    document.getElementById('nama_desa').value =

        el.desa.selectedOptions[0]?.textContent || '';


    // Simpan juga kode wilayah agar tetap terisi dari cookie lokasi otomatis.

    document.getElementById('kode_provinsi').value = el.prov.value || '';

    document.getElementById('kode_kabupaten').value = el.kab.value || '';

    document.getElementById('kode_kecamatan').value = el.kec.value || '';

}



function kosongkanPilihanLanjutan(level) {

    if (level <= 1) resetSelect(el.kab, 'Pilih provinsi dahulu');

    if (level <= 2) resetSelect(el.kec, 'Pilih kabupaten/kota dahulu');

    if (level <= 3) resetSelect(el.desa, 'Pilih kecamatan dahulu');



    document.getElementById('adm4').value = '';

    el.submit.disabled = true;

}



async function muatProvinsi() {

    const nomor = ++generasi;

    resetSelect(el.prov, 'Memuat daftar provinsi...');

    status('Menghubungkan ke daftar provinsi Indonesia...');



    try {

        const daftar = await ambilWilayah('provinces.json');

        if (nomor !== generasi) return;



        isiSelect(el.prov, daftar, 'Pilih provinsi', awal.prov);



        if (!daftar.length) {

            throw new Error('Daftar provinsi kosong.');

        }



        status('Daftar provinsi berhasil dimuat. Silakan pilih provinsi.');



        if (awal.prov) {

            await muatKabupaten(awal.prov, awal.kab);

        }

    } catch (error) {

        resetSelect(el.prov, 'Gagal memuat provinsi');

        status(error.message, true);

    }

}



async function muatKabupaten(kodeProv, terpilih = '') {

    kosongkanPilihanLanjutan(1);

    if (!kodeProv) {

        status('Pilih provinsi untuk melihat kabupaten/kota.');

        return;

    }



    resetSelect(el.kab, 'Memuat kabupaten/kota...');

    status('Memuat semua kabupaten/kota untuk provinsi terpilih...');



    try {

        const daftar = await ambilWilayah('regencies/' + kodeProv + '.json');

        isiSelect(el.kab, daftar, 'Pilih kabupaten/kota', terpilih);

        status('Kabupaten/kota tersedia. Pilih wilayah yang diinginkan.');



        if (terpilih) {

            await muatKecamatan(terpilih, awal.kec);

        }

    } catch (error) {

        resetSelect(el.kab, 'Gagal memuat kabupaten/kota');

        status(error.message, true);

    }

}



async function muatKecamatan(kodeKab, terpilih = '') {

    kosongkanPilihanLanjutan(2);

    if (!kodeKab) {

        status('Pilih kabupaten/kota untuk melihat kecamatan.');

        return;

    }



    resetSelect(el.kec, 'Memuat kecamatan...');

    status('Memuat daftar kecamatan...');



    try {

        const daftar = await ambilWilayah('districts/' + kodeKab + '.json');

        isiSelect(el.kec, daftar, 'Pilih kecamatan', terpilih);

        status('Daftar kecamatan tersedia. Pilih kecamatan.');



        if (terpilih) {

            await muatDesa(terpilih, awal.desa);

        }

    } catch (error) {

        resetSelect(el.kec, 'Gagal memuat kecamatan');

        status(error.message, true);

    }

}



async function muatDesa(kodeKec, terpilih = '') {

    kosongkanPilihanLanjutan(3);

    if (!kodeKec) {

        status('Pilih kecamatan untuk melihat kelurahan/desa.');

        return;

    }



    resetSelect(el.desa, 'Memuat kelurahan/desa...');

    status('Memuat seluruh kelurahan/desa di kecamatan terpilih...');



    try {

        const daftar = await ambilWilayah('villages/' + kodeKec + '.json');

        isiSelect(el.desa, daftar, 'Pilih kelurahan/desa', terpilih);



        if (terpilih) {

            document.getElementById('adm4').value = terpilih;

            el.submit.disabled = false;

            simpanNamaWilayah();

            status('Wilayah siap. Tekan Tampilkan Data BMKG.');

        } else {

            status('Daftar kelurahan/desa tersedia. Pilih salah satunya.');

        }

    } catch (error) {

        resetSelect(el.desa, 'Gagal memuat kelurahan/desa');

        status(error.message, true);

    }

}



el.prov.addEventListener('change', () => {

    document.getElementById('nama_provinsi').value =

        el.prov.selectedOptions[0]?.textContent || '';

    document.getElementById('kode_provinsi').value = el.prov.value;

    muatKabupaten(el.prov.value);

});



el.kab.addEventListener('change', () => {

    document.getElementById('nama_kabupaten').value =

        el.kab.selectedOptions[0]?.textContent || '';

    document.getElementById('kode_kabupaten').value = el.kab.value;

    muatKecamatan(el.kab.value);

});



el.kec.addEventListener('change', () => {

    document.getElementById('nama_kecamatan').value =

        el.kec.selectedOptions[0]?.textContent || '';

    document.getElementById('kode_kecamatan').value = el.kec.value;

    muatDesa(el.kec.value);

});



el.desa.addEventListener('change', () => {

    document.getElementById('adm4').value = el.desa.value;

    document.getElementById('nama_desa').value =

        el.desa.selectedOptions[0]?.textContent || '';



    el.submit.disabled = !el.desa.value;

    if (el.desa.value) {

        simpanNamaWilayah();

        status('Kelurahan/desa sudah dipilih. Tekan Tampilkan Data BMKG.');

    } else {

        status('Pilih kelurahan/desa untuk melanjutkan.');

    }

});



el.form.addEventListener('submit', event => {

    if (!el.desa.value) {

        event.preventDefault();

        status('Pilih kelurahan/desa terlebih dahulu.', true);

        return;

    }



    document.getElementById('adm4').value = el.desa.value;

    simpanNamaWilayah();

    el.submit.disabled = true;

    el.submit.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengambil data BMKG...';

});



document.getElementById('resetWilayah').addEventListener('click', () => {

    window.location.href = window.location.pathname;

});



muatProvinsi();

</script>

<?php if ($adm4 !== '' && !$error && count($dataCuaca) && $adaKoordinat): ?>

<script src="https://unpkg.com/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>

<script>

(function () {

    var koordinat = <?= json_encode([(float)$lonLokasi, (float)$latLokasi]) ?>;

    /* Peta vektor: label, jalan dan garis pantai digambar ulang pada
     * resolusi layar (devicePixelRatio), bukan diperbesar seperti tile
     * gambar, jadi tetap tajam saat zoom. Zoom 11 di MapLibre sama
     * lapangannya dengan zoom 12 di Leaflet karena tile vektor 512 px. */
    var peta = new maplibregl.Map({
        container: 'petaLokasi',
        style: 'https://tiles.openfreemap.org/styles/bright',
        center: koordinat,
        zoom: 11,
        minZoom: 4,
        maxZoom: 16,
        dragRotate: false,
        pitchWithRotate: false,
        attributionControl: false
    });

    peta.on('load', function () {
        peta.scrollZoom.disable();
    });

    peta.addControl(
        new maplibregl.AttributionControl({
            compact: true,
            customAttribution: '&copy; OpenStreetMap contributors &copy; OpenFreeMap'
        }),
        'bottom-right'
    );

    peta.addControl(
        new maplibregl.NavigationControl({ showCompass: false }),
        'top-right'
    );

    peta.addControl(
        new maplibregl.ScaleControl({ maxWidth: 80, unit: 'metric' }),
        'bottom-left'
    );

    var penanda = document.createElement('div');
    penanda.className = 'lokasi-penanda';

    new maplibregl.Marker({ element: penanda, anchor: 'center' })
        .setLngLat(koordinat)
        .setPopup(
            new maplibregl.Popup({ offset: 18, closeButton: false })
                .setHTML(
                    <?= json_encode(
                        $isiPopupPeta,
                        JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    ) ?>
                )
        )
        .addTo(peta)
        .togglePopup();

}());

</script>

<?php endif; ?>

<script src="assets/lokasi-otomatis.js" data-auto="0"></script>

</body>

</html>
    