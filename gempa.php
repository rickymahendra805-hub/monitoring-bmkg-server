
<?php
/* =========================================================
   BMKG KEMAYORAN - MONITORING GEMPA BUMI
   File: gempa.php
   Fitur:
   - Gempa terbaru BMKG
   - 15 gempa M 5.0+
   - 15 gempa dirasakan
   - Auto-refresh data setiap 60 detik
   - Refresh manual tanpa reload seluruh halaman
   - Tema dark premium, menu dashboard, peta lokasi
   - Menu Pemantauan Kota di dropdown Atmosfer
   - Tanpa database dan tanpa mengubah data/cuaca.json
========================================================= */

date_default_timezone_set('Asia/Jakarta');

$API = [
    'terbaru' => 'https://data.bmkg.go.id/DataMKG/TEWS/autogempa.json',
    'm5'      => 'https://data.bmkg.go.id/DataMKG/TEWS/gempaterkini.json',
    'dirasa'  => 'https://data.bmkg.go.id/DataMKG/TEWS/gempadirasakan.json'
];

function ambilBMKG($url)
{
    $hasil = false;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Monitoring-BMKG-Kemayoran/1.0'
        ]);

        $hasil = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($hasil === false || $status < 200 || $status >= 300) {
            $hasil = false;
        }
    }

    if ($hasil === false && ini_get('allow_url_fopen')) {
        $context = stream_context_create([
            'http' => [
                'timeout' => 15,
                'header' => "User-Agent: Monitoring-BMKG-Kemayoran/1.0\r\n"
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true
            ]
        ]);

        $hasil = @file_get_contents($url, false, $context);
    }

    if (!is_string($hasil) || $hasil === '') {
        return null;
    }

    $json = json_decode($hasil, true);

    return is_array($json) ? $json : null;
}

function daftarGempa($json)
{
    $data = $json['Infogempa']['gempa'] ?? [];

    if (!is_array($data)) {
        return [];
    }

    if (isset($data['Magnitude'])) {
        return [$data];
    }

    return array_values(array_filter($data, 'is_array'));
}

function paketDataBMKG()
{
    global $API;

    $terbaruJSON = ambilBMKG($API['terbaru']);
    $m5JSON      = ambilBMKG($API['m5']);
    $dirasaJSON  = ambilBMKG($API['dirasa']);

    $terbaru = daftarGempa($terbaruJSON);
    $m5      = daftarGempa($m5JSON);
    $dirasa  = daftarGempa($dirasaJSON);

    return [
        'terbaru' => $terbaru[0] ?? null,
        'm5' => $m5,
        'dirasa' => $dirasa,
        'waktuAkses' => date('d-m-Y H:i:s') . ' WIB',
        'berhasil' => !empty($terbaru) || !empty($m5) || !empty($dirasa)
    ];
}

/* Endpoint AJAX untuk memperbarui data tanpa reload halaman */
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    $hasil = paketDataBMKG();

    echo json_encode(
        $hasil,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

/* Lokasi pengguna dari cookie hasil deteksi otomatis (jika ada). */
$lokasiPengguna = null;
$latPengguna = filter_input(INPUT_COOKIE, 'bmkg_lat', FILTER_VALIDATE_FLOAT);
$lonPengguna = filter_input(INPUT_COOKIE, 'bmkg_lon', FILTER_VALIDATE_FLOAT);

if ($latPengguna !== null && $lonPengguna !== null &&
    $latPengguna !== false && $lonPengguna !== false &&
    abs($latPengguna) <= 90 && abs($lonPengguna) <= 180) {
    $namaLokasi = trim((string)($_COOKIE['bmkg_desa'] ?? ''));
    if ($namaLokasi === '') {
        $namaLokasi = trim((string)($_COOKIE['bmkg_kecamatan'] ?? ''));
    }

    $lokasiPengguna = [
        'lat' => $latPengguna,
        'lon' => $lonPengguna,
        'nama' => $namaLokasi,
        'perkiraan' => !empty($_COOKIE['bmkg_perkiraan'])
    ];
}

$lokasiJSON = json_encode(
    $lokasiPengguna,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT |
    JSON_HEX_AMP | JSON_UNESCAPED_UNICODE |
    JSON_INVALID_UTF8_SUBSTITUTE
);

/* Data awal halaman */
$dataAwal = paketDataBMKG();

$initialJSON = json_encode(
    $dataAwal,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT |
    JSON_HEX_AMP | JSON_UNESCAPED_UNICODE |
    JSON_INVALID_UTF8_SUBSTITUTE
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#eef3f9">
<title>Gempa Bumi | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<style>
:root {
    --bg:#eef3f9;
    --bg-soft:#eef3f9;
    --surface:#ffffff;
    --surface-hover:#f5f9fd;
    --line:rgba(35,70,110,0.208);
    --text:#16293f;
    --muted:#5c748c;
    --blue:#0f61bd;
    --cyan:#10a6bc;
    --green:#24a873;
    --red:#bd0f29;
    --orange:#bd710f;
    --shadow:0 18px 45px rgba(16,42,67,0.044);
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    color:var(--text);
    font-family:Inter,sans-serif;
    background:
      radial-gradient(ellipse at 12% 0%,rgba(37,140,255,.11),transparent 34%),
      radial-gradient(ellipse at 92% 8%,rgba(62,216,239,.06),transparent 28%),
      var(--bg);
    min-height:100vh;
}
a{color:inherit;text-decoration:none}
button{font:inherit;cursor:pointer}
.nav-menu{display:flex;align-items:center;justify-content:flex-end;flex-wrap:wrap;gap:5px}
.mobile-menu{
    display:none;width:42px;height:42px;border:1px solid var(--line);
    border-radius:11px;color:#16293f;background:var(--surface)
}
.container{width:min(1440px,92%);margin:auto;padding:32px 0 55px}
.breadcrumb{display:flex;align-items:center;gap:9px;margin-bottom:20px;color:var(--muted);font-size:12px}
.breadcrumb i{font-size:10px}
.hero{
    position:relative;overflow:hidden;
    display:grid;grid-template-columns:1.35fr .65fr;gap:30px;align-items:center;
    padding:34px;border:1px solid rgba(15,102,189,0.200);border-radius:23px;
    background:radial-gradient(circle at 90% 20%,rgba(62,216,239,.15),transparent 30%),
               linear-gradient(125deg,#ffffff,#eef3f9 70%);
    box-shadow:var(--shadow)
}
.eyebrow{display:inline-flex;align-items:center;gap:9px;color:var(--cyan);font-size:11px;font-weight:800;letter-spacing:1.4px}
.live-dot{width:8px;height:8px;border-radius:50%;background:var(--green);box-shadow:0 0 0 5px rgba(66,214,155,.1)}
.hero h1{margin:17px 0 12px;font-size:clamp(28px,4vw,43px);letter-spacing:-1.5px;line-height:1.13}
.hero h1 span{color:var(--cyan)}
.hero p{max-width:660px;color:var(--muted);font-size:13px;line-height:1.9}
.hero-visual{display:flex;justify-content:center;align-items:center;min-height:180px}
.seismic-icon{
    width:155px;height:155px;display:grid;place-items:center;border-radius:50%;
    color:var(--cyan);font-size:72px;border:1px solid rgba(62,216,239,.2);
    background:radial-gradient(circle,rgba(37,140,255,.16),rgba(37,140,255,.015) 70%);
    box-shadow:0 0 0 18px rgba(62,216,239,.025),0 0 0 38px rgba(62,216,239,.018)
}
.hero-links{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.btn-primary,.btn-secondary{
    display:inline-flex;align-items:center;justify-content:center;gap:9px;
    padding:11px 15px;border:1px solid var(--line);border-radius:10px;
    font-size:12px;font-weight:700;transition:.2s
}
.btn-primary{color:white;border-color:transparent;background:linear-gradient(135deg,#1678ed,#148fda)}
.btn-primary:hover{filter:brightness(1.12);transform:translateY(-1px)}
.btn-secondary{color:#16293f;background:rgba(23,52,86,0.030)}
.btn-secondary:hover{background:var(--surface-hover);border-color:rgba(62,216,239,.25)}
.update-row{
    display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;
    margin:24px 0 18px;padding:14px 17px;border:1px solid var(--line);
    border-radius:13px;background:rgba(255,255,255,0.94);color:var(--muted);font-size:12px
}
.update-row strong{color:var(--text)}
.update-status{display:inline-flex;align-items:center;gap:8px}
.status-dot{width:8px;height:8px;border-radius:50%;background:var(--green)}
.status-dot.error{background:var(--red)}
.status-dot.loading{background:var(--orange);animation:pulse 1s infinite}
@keyframes pulse{50%{opacity:.35}}
.section-title{display:flex;justify-content:space-between;align-items:flex-end;gap:15px;margin:30px 0 17px}
.section-title h2{margin:0;font-size:20px;letter-spacing:-.5px}
.section-title p{margin:7px 0 0;color:var(--muted);font-size:12px;line-height:1.7}
.section-count{padding:7px 10px;border:1px solid var(--line);border-radius:9px;color:var(--cyan);background:rgba(62,216,239,.05);font-size:11px;font-weight:700;white-space:nowrap}
.panel,.quake-card,.stat-card,.empty-state{
    border:1px solid var(--line);border-radius:17px;
    background:linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
    box-shadow:var(--shadow)
}
.latest-layout{display:grid;grid-template-columns:minmax(0,1fr) 300px;gap:18px}
.panel{padding:23px}
.panel-top{display:flex;align-items:center;justify-content:space-between;gap:15px;margin-bottom:20px}
.panel-top h3,.info-panel h3{margin:0;font-size:15px}
.panel-top p{margin:7px 0 0;color:var(--muted);font-size:12px}
.latest-mag{color:var(--orange);font-size:43px;font-weight:800;letter-spacing:-2px;white-space:nowrap}
.latest-mag small{display:block;margin-top:3px;color:var(--muted);font-size:10px;font-weight:600;letter-spacing:.5px}
.latest-location{margin-bottom:20px;font-size:15px;font-weight:700;line-height:1.7}
.latest-meta{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.meta-cell{padding:13px;border:1px solid var(--line);border-radius:11px;background:rgba(23,52,86,0.030)}
.meta-cell span{display:block;margin-bottom:7px;color:var(--muted);font-size:10px}
.meta-cell strong{font-size:12px;line-height:1.7;overflow-wrap:anywhere}
.info-panel{padding:21px;border:1px solid var(--line);border-radius:17px;background:linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94))}
.info-item{display:flex;gap:12px;margin:15px 0;color:var(--muted);font-size:12px;line-height:1.75}
.info-item i{margin-top:3px;color:var(--cyan)}
.info-item strong{color:var(--text)}
.status-note{margin-top:17px;padding:13px;border:1px solid rgba(62,216,239,.15);border-radius:11px;color:#255d88;background:rgba(62,216,239,.045);font-size:11px;line-height:1.8}
.tabs{display:flex;flex-wrap:wrap;gap:8px;margin:23px 0 18px}
.tab-button{padding:11px 15px;color:var(--muted);background:var(--surface);border:1px solid var(--line);border-radius:10px;font-size:12px;font-weight:700}
.tab-button.active,.tab-button:hover{color:#16293f;border-color:rgba(37,140,255,.35);background:rgba(37,140,255,.14)}
.tab-panel{display:none}
.tab-panel.active{display:block}
.quake-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:15px}
.quake-card{padding:18px;min-width:0;transition:.2s}
.quake-card:hover{border-color:rgba(62,216,239,.25);transform:translateY(-2px)}
.quake-card-head{display:flex;justify-content:space-between;align-items:center;gap:12px;padding-bottom:15px;border-bottom:1px solid var(--line)}
.quake-number{display:flex;align-items:center;gap:11px;min-width:0}
.quake-index{width:34px;height:34px;flex:0 0 34px;display:grid;place-items:center;border-radius:10px;color:var(--cyan);background:rgba(62,216,239,.08);font-size:12px;font-weight:800}
.quake-date{font-size:12px;font-weight:700}
.quake-time{margin-top:5px;color:var(--muted);font-size:10px}
.quake-time i{margin-right:4px}
.magnitude{padding:8px 10px;border-radius:9px;font-size:12px;font-weight:800;white-space:nowrap}
.mag-besar{color:#9c1122;background:rgba(189,15,41,0.130)}
.mag-kuat{color:#9c6011;background:rgba(189,113,15,0.120)}
.mag-sedang{color:#117c9c;background:rgba(62,216,239,.10)}
.mag-rendah{color:#1f8f57;background:rgba(66,214,155,.10)}
.quake-location{display:flex;gap:12px;align-items:flex-start;margin:17px 0}
.location-icon{display:grid;place-items:center;flex:0 0 35px;height:35px;border-radius:10px;color:var(--cyan);background:rgba(62,216,239,.08)}
.field-label{margin-bottom:6px;color:var(--muted);font-size:9px;font-weight:800;letter-spacing:1px}
.location-name{font-size:12px;font-weight:600;line-height:1.75;overflow-wrap:anywhere}
.quake-details{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px;margin-bottom:14px}
.detail-box{min-width:0;padding:10px;border:1px solid var(--line);border-radius:9px;background:rgba(23,52,86,0.030)}
.detail-box span{display:block;margin-bottom:7px;color:var(--muted);font-size:9px}
.detail-box strong{display:block;font-size:10px;overflow-wrap:anywhere;line-height:1.6}
.quake-status{display:flex;align-items:flex-start;gap:10px;padding:12px;border-radius:10px;font-size:11px;line-height:1.7}
.quake-status>i{margin-top:3px}
.quake-status p{margin:4px 0 0}
.status-aman{color:#1c925b;border:1px solid rgba(66,214,155,.14);background:rgba(66,214,155,.055)}
.status-info{color:#16293f;border:1px solid rgba(62,216,239,.14);background:rgba(62,216,239,.045)}
.felt-info{margin-top:13px;padding:12px;border:1px solid var(--line);border-radius:10px;background:rgba(23,52,86,0.030)}
.felt-info p{margin:0;color:#16293f;font-size:11px;line-height:1.8;overflow-wrap:anywhere}
.quake-actions{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px}
.quake-actions .btn-secondary{padding:9px 10px;font-size:10px}
.stats-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
.stat-card{display:flex;align-items:center;gap:13px;padding:17px}
.stat-icon{display:grid;place-items:center;flex:0 0 43px;height:43px;border-radius:13px;color:var(--cyan);background:rgba(62,216,239,.08)}
.stat-card span{display:block;margin-bottom:5px;color:var(--muted);font-size:10px}
.stat-card strong{font-size:19px}
.empty-state{padding:28px;color:var(--muted);font-size:12px;line-height:1.8}
.empty-state i{display:block;margin-bottom:12px;color:var(--orange);font-size:25px}
.disclaimer{margin-top:18px;padding:14px 16px;border:1px solid rgba(189,113,15,0.140);border-radius:11px;color:#22364e;background:rgba(189,113,15,0.035);font-size:11px;line-height:1.8}
.footer{display:flex;flex-wrap:wrap;justify-content:space-between;gap:12px;margin-top:35px;padding-top:20px;border-top:1px solid var(--line);color:var(--muted);font-size:11px;line-height:1.8}
.footer a{color:var(--cyan)}
@media(max-width:1180px){
    .nav-menu{gap:2px}
}
@media(max-width:900px){
    .mobile-menu{display:grid;place-items:center;margin-left:auto}
    .nav-menu{display:none;width:100%;align-items:stretch;flex-direction:column;padding-top:8px}
    .nav-menu.show{display:flex}
    .hero{grid-template-columns:1fr}
    .hero-visual{display:none}
    .latest-layout{grid-template-columns:1fr}
}
@media(max-width:650px){
    .container{width:94%;padding-top:22px}
    .hero{padding:23px;border-radius:17px}
    .hero h1{font-size:30px}
    .quake-grid,.stats-row{grid-template-columns:1fr}
    .panel{padding:17px}
    .latest-mag{font-size:34px}
    .section-title h2{font-size:17px}
    .quake-card{padding:14px}
    .quake-details{gap:5px}
    .detail-box{padding:8px}
    .update-row{align-items:flex-start;flex-direction:column}
}
@media print{.hero-links,.tabs,.quake-actions,.mobile-menu{display:none!important}
    body{color:#111;background:#fff}
    .container{width:100%}
    .hero,.panel,.info-panel,.map-card,.quake-card,.stat-card{color:#111;background:#fff;box-shadow:none;border:1px solid #ddd;break-inside:avoid}
    .tab-panel{display:block!important}
    .quake-grid{grid-template-columns:1fr 1fr}
}

/* Mode tampilan: lokal (sekitar lokasi) atau nasional. */
.mode-switch {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.mode-button {
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--muted);
    padding: 11px 18px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: .2s ease;
}

.mode-button:hover {
    color: var(--text);
    border-color: rgba(62, 216, 239, .4);
}

.mode-button.active {
    background: rgba(62, 216, 239, .14);
    border-color: rgba(62, 216, 239, .55);
    color: var(--text);
}

.radius-bar {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 14px;
    font-size: 13px;
    color: var(--muted);
}

.radius-bar select {
    background: var(--surface);
    color: var(--text);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 8px 12px;
    font-size: 13px;
}

.radius-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.radius-note {
    margin-left: auto;
    font-size: 12px;
}

.nearest-layout {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

#nearestMap {
    height: 420px;
    border-radius: 16px;
    border: 1px solid var(--line);
    overflow: hidden;
    background: var(--surface);
}

.nearest-item {
    display: flex;
    gap: 14px;
    align-items: flex-start;
    padding: 12px 0;
    border-top: 1px solid var(--line);
}

.nearest-item:first-of-type {
    border-top: none;
}

.nearest-distance {
    min-width: 92px;
    text-align: right;
    font-weight: 800;
    color: var(--cyan);
}

.nearest-distance small {
    display: block;
    font-size: 10px;
    letter-spacing: 1px;
    color: var(--muted);
    font-weight: 600;
}

.nearest-body {
    flex: 1;
    min-width: 0;
}

.nearest-body strong {
    display: block;
    font-size: 14px;
    margin-bottom: 4px;
}

.nearest-body p {
    margin: 0;
    font-size: 12px;
    color: var(--muted);
}

@media (max-width: 1000px) {
    .nearest-layout {
        grid-template-columns: 1fr;
    }

    #nearestMap {
        height: 320px;
    }
}

/* Peta pada blok "Informasi Gempa Terbaru". */
.latest-main {
    display: grid;
    gap: 16px;
    min-width: 0;
}

.map-card {
    padding: 18px;
    border: 1px solid var(--line);
    border-radius: 17px;
    background: linear-gradient(145deg, rgba(255,255,255,0.94), rgba(255,255,255,0.94));
    box-shadow: var(--shadow);
}

.map-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 13px;
}

.map-head h3 {
    margin: 0;
    font-size: 15px;
}

.map-note {
    padding: 6px 9px;
    border: 1px solid var(--line);
    border-radius: 9px;
    color: var(--cyan);
    background: rgba(62, 216, 239, .05);
    font-size: 10px;
    font-weight: 700;
    white-space: nowrap;
}

#latestMap {
    height: 380px;
    border-radius: 14px;
    border: 1px solid var(--line);
    overflow: hidden;
    background: var(--surface);
}

.map-hint {
    margin: 11px 0 0;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.75;
}

.terbaru-label {
    padding: 3px 7px;
    border: 1px solid rgba(189,49,15,0.500);
    border-radius: 8px;
    color: #ffd7c9;
    background: rgba(189,49,15,0.900);
    font-size: 10px;
    font-weight: 800;
    white-space: nowrap;
    box-shadow: 0 2px 8px rgba(16,42,67,0.099);
}

.terbaru-label::before {
    display: none;
}

@keyframes gempaDenyut {
    0%, 100% { stroke-opacity: 1; }
    50%      { stroke-opacity: .15; }
}

.denyut-terbaru {
    animation: gempaDenyut 1.8s ease-in-out infinite;
}

@media (max-width: 900px) {
    #latestMap {
        height: 300px;
    }
}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=3">
</head>

<body>

<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="container">
    <div class="breadcrumb">
        <i class="fa-solid fa-house"></i><span>Dasbor</span>
        <i class="fa-solid fa-chevron-right"></i><span>Produk BMKG</span>
        <i class="fa-solid fa-chevron-right"></i><strong>Gempa Bumi</strong>
    </div>

    <section class="hero">
        <div>
            <div class="eyebrow"><span class="live-dot"></span> DATA RESMI BMKG</div>
            <h1>Pemantauan <span>Gempa Bumi</span></h1>
            <p>
                Pantau informasi gempa bumi terbaru di Indonesia dari data terbuka
                BMKG. Halaman ini menampilkan gempa terbaru, daftar gempa
                magnitudo 5,0 ke atas, serta kejadian gempa yang dirasakan masyarakat.
                Data diperiksa otomatis setiap 60 detik.
            </p>
            <div class="hero-links">
                <a class="btn-primary" href="#gempa-terbaru"><i class="fa-solid fa-bolt"></i> Gempa Terbaru</a>
                <a class="btn-secondary" href="https://data.bmkg.go.id/gempabumi/" target="_blank" rel="noopener noreferrer">
                    <i class="fa-solid fa-arrow-up-right-from-square"></i> Portal Data BMKG
                </a>
                <button class="btn-secondary" id="refreshButton" type="button">
                    <i class="fa-solid fa-rotate"></i> Perbarui Data
                </button>
                <button class="btn-secondary" type="button" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Cetak Halaman
                </button>
            </div>
        </div>
        <div class="hero-visual">
            <div class="seismic-icon"><i class="fa-solid fa-house-crack"></i></div>
        </div>
    </section>

    <div class="update-row">
        <div class="update-status">
            <span class="status-dot" id="connectionDot"></span>
            <span id="connectionText">Menghubungkan ke data BMKG...</span>
        </div>
        <div><i class="fa-regular fa-clock"></i> Pemeriksaan terakhir: <strong id="lastUpdate">-</strong></div>
        <div><i class="fa-solid fa-rotate"></i> Auto-refresh: <strong>60 detik</strong></div>
    </div>

    <section id="gempa-terbaru">
        <div class="section-title">
            <div>
                <h2>Informasi Gempa Terbaru</h2>
                <p>Ringkasan kejadian gempa terbaru yang dipublikasikan oleh BMKG.</p>
            </div>
            <span class="section-count" id="latestBadge">MEMUAT DATA</span>
        </div>

        <div class="latest-layout">
            <div class="latest-main">
                <div class="panel" id="latestPanel">
                    <div class="empty-state">Sedang mengambil data gempa dari BMKG...</div>
                </div>

                <div class="map-card" id="latestMapCard">
                    <div class="map-head">
                        <h3><i class="fa-solid fa-map-location-dot" style="color:var(--cyan);margin-right:8px"></i> Peta Wilayah Gempa Terbaru</h3>
                        <span class="map-note" id="latestMapNote">MENUNGGU DATA</span>
                    </div>
                    <div id="latestMap" aria-label="Peta episenter gempa terbaru BMKG"></div>
                    <p class="map-hint">
                        Penanda besar berdenyut adalah kejadian paling baru. Titik-titik lain adalah seluruh gempa dari
                        daftar terbaru BMKG, dan batas peta dibuat supaya semuanya terlihat sekaligus — perbesar skala
                        peta untuk melihat wilayah masing-masing titik.
                    </p>
                </div>
            </div>

            <aside class="info-panel">
                <h3><i class="fa-solid fa-book-open" style="color:var(--cyan);margin-right:8px"></i> Panduan Informasi</h3>
                <div class="info-item">
                    <i class="fa-solid fa-chart-simple"></i>
                    <div><strong>Magnitudo</strong><br>Menggambarkan ukuran gempa, tetapi tidak secara langsung menentukan tingkat kerusakan di setiap lokasi.</div>
                </div>
                <div class="info-item">
                    <i class="fa-solid fa-arrow-down"></i>
                    <div><strong>Kedalaman</strong><br>Jarak vertikal pusat gempa dari permukaan bumi, dinyatakan dalam kilometer.</div>
                </div>
                <div class="info-item">
                    <i class="fa-solid fa-house-crack"></i>
                    <div><strong>Gempa dirasakan</strong><br>Menampilkan laporan wilayah yang merasakan guncangan jika informasi tersedia.</div>
                </div>
                <div class="info-item">
                    <i class="fa-solid fa-water"></i>
                    <div><strong>Potensi tsunami</strong><br>Ikuti pengumuman resmi dan instruksi pihak berwenang. Jangan menyimpulkan sendiri dari magnitudo saja.</div>
                </div>
                <div class="status-note">
                    <i class="fa-solid fa-circle-info"></i>
                    Data awal dapat berubah setelah analisis lebih lanjut. Untuk keputusan keselamatan, periksa pengumuman resmi BMKG.
                </div>
            </aside>
        </div>
    </section>

    <section id="gempa-terdekat">
        <div class="section-title">
            <div>
                <h2>Mode Tampilan</h2>
                <p>Pilih tampilan gempa yang dirasakan di sekitar lokasi Anda, atau seluruh kejadian terbaru di Indonesia.</p>
            </div>
        </div>

        <div class="mode-switch" role="group" aria-label="Mode tampilan gempa">
            <button type="button" class="mode-button active" id="modeLokalButton" data-mode="lokal">
                <i class="fa-solid fa-location-crosshairs"></i> Gempa di Sekitar Saya
            </button>
            <button type="button" class="mode-button" id="modeNasionalButton" data-mode="nasional">
                <i class="fa-solid fa-earth-asia"></i> Gempa Terbaru Seluruh Indonesia
            </button>
        </div>

        <div id="modeLokal">
            <div class="radius-bar">
                <label for="radiusKm" title="Hanya gempa dengan jarak episenter kurang dari nilai ini yang ditampilkan">
                    Radius
                </label>
                <select id="radiusKm">
                    <option value="100">100 km</option>
                    <option value="250" selected>250 km</option>
                    <option value="500">500 km</option>
                    <option value="1000">1000 km</option>
                </select>

                <label class="radius-toggle" for="hanyaDirasakan" title="Hanya tampilkan gempa yang dilaporkan dirasakan di suatu wilayah">
                    <input type="checkbox" id="hanyaDirasakan">
                    Hanya yang dirasakan
                </label>

                <span class="radius-note" id="nearestSummary"></span>
            </div>

            <div class="nearest-layout">
                <div class="panel" id="nearestPanel">
                    <div class="empty-state">Sedang memeriksa lokasi Anda...</div>
                </div>
                <div id="nearestMap" aria-label="Peta episenter gempa di sekitar lokasi Anda"></div>
            </div>
        </div>
    </section>

    <section>
        <div class="section-title">
            <div>
                <h2>Ringkasan Data Gempa</h2>
                <p>Jumlah entri yang berhasil diterima dari tiga sumber data terbuka BMKG.</p>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-bolt"></i></div>
                <div><span>Gempa terbaru</span><strong id="countLatest">0</strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                <div><span>Gempa M 5,0+</span><strong id="countM5">0</strong></div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fa-solid fa-house-crack"></i></div>
                <div><span>Gempa dirasakan</span><strong id="countFelt">0</strong></div>
            </div>
        </div>
    </section>

    <section id="modeNasional">
        <div class="section-title">
            <div>
                <h2>Daftar Kejadian Gempa BMKG</h2>
                <p><span id="periodeNasional"></span>Seluruh kejadian terbaru yang dirilis BMKG untuk wilayah Indonesia, tanpa filter jarak.
                Daftar diurutkan dari kejadian paling baru.</p>
            </div>
        </div>

        <div class="tabs" role="tablist">
            <button class="tab-button active" type="button" data-tab="tab-terbaru">
                <i class="fa-solid fa-clock-rotate-left"></i> Terbaru (<span id="tabCountSemua">0</span>)
            </button>
            <button class="tab-button" type="button" data-tab="tab-m5">
                <i class="fa-solid fa-chart-line"></i> Gempa M 5,0+ (<span id="tabCountM5">0</span>)
            </button>
            <button class="tab-button" type="button" data-tab="tab-dirasa">
                <i class="fa-solid fa-house-crack"></i> Gempa Dirasakan (<span id="tabCountFelt">0</span>)
            </button>
        </div>

        <div class="tab-panel active" id="tab-terbaru">
            <div class="quake-grid" id="listSemua"></div>
        </div>
        <div class="tab-panel" id="tab-m5">
            <div class="quake-grid" id="listM5"></div>
        </div>
        <div class="tab-panel" id="tab-dirasa">
            <div class="quake-grid" id="listFelt"></div>
        </div>
    </section>

    <div class="disclaimer">
        <strong><i class="fa-solid fa-shield-halved"></i> Catatan sumber dan keselamatan</strong><br>
        Sumber data: Badan Meteorologi, Klimatologi, dan Geofisika (BMKG),
        melalui Data Gempabumi Terbuka. Waktu pemeriksaan halaman bukan waktu
        kejadian gempa atau waktu pembaruan server BMKG. Data diperiksa ulang
        setiap 60 detik selama halaman terbuka, tetapi pembaruan bergantung pada
        ketersediaan server dan publikasi BMKG. Ikuti pengumuman resmi BMKG dan
        arahan otoritas setempat untuk informasi keselamatan.
    </div>

    <footer class="footer">
        <div><strong>BMKG KEMAYORAN</strong><br>Dasbor Pemantauan Cuaca dan Gempa Bumi</div>
        <div>
            Sumber:
            <a href="https://data.bmkg.go.id/gempabumi/" target="_blank" rel="noopener noreferrer">Data Gempabumi Terbuka BMKG</a><br>
            <a href="https://www.bmkg.go.id/gempabumi/gempabumi-realtime" target="_blank" rel="noopener noreferrer">Informasi Gempa Bumi Real-time BMKG</a>
        </div>
    </footer>
</main>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<script>
/* Data awal dari PHP; pembaruan berikutnya menggunakan AJAX. */
let gempaData = <?= $initialJSON ?: '{}' ?>;

/* Lokasi pengguna dari cookie hasil deteksi otomatis (null jika belum ada). */
const lokasiPengguna = <?= $lokasiJSON ?: 'null' ?>;

/* Mengubah format koordinat BMKG ("4.32 LS", "95.81 BT") menjadi derajat desimal. */
function parseKoordinat(lintang, bujur) {
    const bersih = (nilai) => String(nilai || '').replace(',', '.').trim();
    const keDerajat = (nilai, negatifJika) => {
        const teks = bersih(nilai).toUpperCase();
        const angka = parseFloat(teks);
        if (Number.isNaN(angka)) return null;
        return teks.includes(negatifJika) ? -angka : angka;
    };

    const lat = keDerajat(lintang, 'LS');
    const lon = keDerajat(bujur, 'BB');
    if (lat === null || lon === null) return null;

    return { lat, lon };
}

/* Jarak garis lurus dua titik di permukaan bumi (rumus haversine), dalam km. */
function jarakKm(lat1, lon1, lat2, lon2) {
    const rad = Math.PI / 180;
    const dLat = (lat2 - lat1) * rad;
    const dLon = (lon2 - lon1) * rad;
    const a = Math.sin(dLat / 2) ** 2 +
        Math.cos(lat1 * rad) * Math.cos(lat2 * rad) * Math.sin(dLon / 2) ** 2;
    return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

/* Semua kejadian dari tiga sumber BMKG, tanpa duplikat. */
function semuaGempa(data) {
    const semua = [];

    if (data && data.terbaru) semua.push(data.terbaru);
    if (data && Array.isArray(data.m5)) semua.push(...data.m5);
    if (data && Array.isArray(data.dirasa)) semua.push(...data.dirasa);

    const sudah = new Set();

    return semua.filter(gempa => {
        const kunci = [
            field(gempa, 'Tanggal', ''),
            field(gempa, 'Jam', ''),
            field(gempa, 'Coordinates', ''),
            field(gempa, 'Magnitude', '')
        ].join('|');

        if (sudah.has(kunci)) return false;

        sudah.add(kunci);
        return true;
    });
}

/* Waktu kejadian sebagai angka epoch, untuk mengurutkan daftar. */
function waktuGempa(gempa) {
    const teks = String(
        field(gempa, 'DateTime', '') ||
        (field(gempa, 'Tanggal', '') + ' ' + field(gempa, 'Jam', ''))
    ).trim();

    const waktu = Date.parse(teks.replace('WIB', '+07:00'));

    return Number.isNaN(waktu) ? 0 : waktu;
}

/* True jika BMKG mencatat gempa ini dirasakan di suatu wilayah. */
function gempaDirasakan(gempa) {
    const dirasakan = String(field(gempa, 'Dirasakan', '')).trim();

    return dirasakan !== '' && dirasakan !== '-';
}

/*
 * Daftar gempa di sekitar lokasi pengguna, terurut dari yang terdekat,
 * dibatasi radius (km) dan optionally hanya yang dilaporkan dirasakan.
 */
function gempaDekat(data, radiusKm, hanyaDirasakan) {
    if (!lokasiPengguna) return [];

    return semuaGempa(data)
        .map(gempa => {
            const koord = parseKoordinat(field(gempa, 'Lintang'), field(gempa, 'Bujur'));

            if (!koord) return null;

            return {
                gempa,
                koord,
                jarak: jarakKm(lokasiPengguna.lat, lokasiPengguna.lon, koord.lat, koord.lon)
            };
        })
        .filter(Boolean)
        .filter(item => item.jarak <= radiusKm)
        .filter(item => !hanyaDirasakan || gempaDirasakan(item.gempa))
        .sort((a, b) => a.jarak - b.jarak);
}

/*
 * Kejadian terdekat tanpa mempedulikan radius, dipakai untuk menjelaskan
 * panel kosong: "tidak ada dalam radius X" baru masuk akal kalau pembaca
 * tahu berapa jauh sebenarnya episenter paling dekat.
 */
function gempaTerdekat(data) {
    if (!lokasiPengguna) return null;

    const kandidat = semuaGempa(data)
        .map(gempa => {
            const koord = parseKoordinat(field(gempa, 'Lintang'), field(gempa, 'Bujur'));

            return koord
                ? { gempa, koord, jarak: jarakKm(lokasiPengguna.lat, lokasiPengguna.lon, koord.lat, koord.lon) }
                : null;
        })
        .filter(Boolean)
        .sort((a, b) => a.jarak - b.jarak);

    return kandidat.length ? kandidat[0] : null;
}

function angkaJarak(km) {
    return km < 100
        ? km.toFixed(1).replace('.', ',')
        : Math.round(km).toLocaleString('id-ID');
}

function tanggalPendek(waktu) {
    if (!waktu) return '';

    return new Date(waktu).toLocaleDateString('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric',
        timeZone: 'Asia/Jakarta'
    });
}

/* Rentang tanggal kejadian yang sedang ditampilkan, dihitung dari datanya. */
function keteranganPeriode(gempa) {
    const waktu = gempa.map(waktuGempa).filter(Boolean);

    if (!waktu.length) return 'Periode kejadian belum terbaca dari data BMKG.';

    const terlama = Math.min(...waktu);
    const terbaru = Math.max(...waktu);

    if (terlama === terbaru) return 'Periode kejadian: ' + tanggalPendek(terbaru) + '.';

    return 'Periode kejadian ' + tanggalPendek(terlama) + ' sampai ' + tanggalPendek(terbaru) + '.';
}

function renderNearest(data) {
    const panel = document.getElementById('nearestPanel');
    if (!panel) return;

    const ringkasan = document.getElementById('nearestSummary');
    const pilihRadius = document.getElementById('radiusKm');
    const kotakRasa = document.getElementById('hanyaDirasakan');
    const radius = pilihRadius ? parseFloat(pilihRadius.value) : 250;
    const hanyaRasa = kotakRasa ? kotakRasa.checked : false;
    const labelRadius = radius.toLocaleString('id-ID');

    if (!lokasiPengguna) {
        panel.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-location-crosshairs"></i>
                <strong>Lokasi Anda belum diketahui.</strong>
                <p>Aktifkan deteksi lokasi agar sistem dapat menampilkan gempa terdekat dari posisi Anda.</p>
                <button type="button" class="btn-primary" data-deteksi-lokasi>
                    <i class="fa-solid fa-location-crosshairs"></i> Gunakan Lokasi Saya
                </button>
            </div>`;
        if (ringkasan) ringkasan.textContent = '';
        sembunyikanPetaDekat();
        return;
    }

    const daftar = gempaDekat(data, radius, hanyaRasa);
    const terdekat = daftar.length ? null : gempaTerdekat(data);

    if (ringkasan) {
        ringkasan.textContent = (daftar.length
            ? daftar.length + ' kejadian'
            : 'Belum ada kejadian') +
            ' dalam radius ' + labelRadius + ' km' +
            (hanyaRasa ? ' yang dilaporkan dirasakan' : '') +
            (daftar.length
                ? ' · ' + keteranganPeriode(daftar.map(item => item.gempa))
                : (terdekat ? ' · episenter terdekat ' + angkaJarak(terdekat.jarak) + ' km dari Anda' : ''));
    }

    gambarPetaDekat(daftar, radius);

    if (!daftar.length) {
        const jumlahUmpan = semuaGempa(data).length;
        const radiusTerjauh = pilihRadius
            ? Math.max.apply(null, [].slice.call(pilihRadius.options).map(function (opsi) {
                return parseFloat(opsi.value) || 0;
            }))
            : radius;

        panel.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-shield-halved"></i>
                <strong>Tidak ada gempa dalam radius ${labelRadius} km.</strong>
                <p>${terdekat
                    ? `Umpan BMKG saat ini memuat ${jumlahUmpan} kejadian dan yang paling dekat berjarak ${esc(angkaJarak(terdekat.jarak))} km dari lokasi Anda
                       (M ${esc(magnitudeText(field(terdekat.gempa, 'Magnitude')))} · ${esc(field(terdekat.gempa, 'Wilayah'))}).
                       ${hanyaRasa && terdekat.jarak <= radius
                            ? 'Kejadian sedekat itu sebenarnya ada, tetapi belum ada yang dilaporkan dirasakan — matikan "Hanya yang dirasakan" untuk melihatnya.'
                            : (terdekat.jarak > radiusTerjauh
                                ? 'Jarak itu sudah melebihi radius terjauh yang tersedia di sini, jadi buka tab "Gempa Terbaru Seluruh Indonesia" untuk melihatnya.'
                                : 'Pilih radius yang lebih besar dari jarak itu, atau buka tab "Gempa Terbaru Seluruh Indonesia" untuk melihat daftar nasional.')}`
                    : 'Umpan data BMKG saat ini tidak memuat episenter yang bisa dihitung jaraknya. Buka tab "Gempa Terbaru Seluruh Indonesia" atau coba beberapa menit lagi.'}</p>
            </div>`;
        return;
    }

    panel.innerHTML = `
        <div class="panel-top">
            <div>
                <h3><i class="fa-solid fa-location-dot" style="color:var(--cyan);margin-right:8px"></i> Gempa di Sekitar Lokasi Anda</h3>
                <p>Jarak garis lurus dari ${esc(lokasiPengguna.nama || 'koordinat perangkat Anda')}${lokasiPengguna.perkiraan ? ' (perkiraan)' : ''} ke episenter.
                ${esc(keteranganPeriode(daftar.map(item => item.gempa)))} Daftar mengikuti isi umpan data BMKG saat pemeriksaan terakhir, diurutkan dari yang terdekat.</p>
            </div>
            <div class="latest-mag">${daftar.length}<small style="letter-spacing:1px">KEJADIAN</small></div>
        </div>` +

        daftar.slice(0, 8).map(item => {
            const gempa = item.gempa;
            const peta = mapLink(gempa);
            const dirasakan = gempaDirasakan(gempa)
                ? 'Dirasakan: ' + esc(field(gempa, 'Dirasakan', ''))
                : esc(field(gempa, 'Potensi', 'Informasi potensi belum tersedia'));

            return `
            <div class="nearest-item">
                <div class="nearest-distance">${angkaJarak(item.jarak)}<small>KM DARI ANDA</small></div>
                <div class="nearest-body">
                    <strong>${esc(field(gempa, 'Wilayah'))}</strong>
                    <p>M ${magnitudeText(field(gempa, 'Magnitude'))} &middot; Kedalaman ${esc(field(gempa, 'Kedalaman'))} &middot; ${esc(field(gempa, 'Tanggal'))} ${esc(field(gempa, 'Jam'))}</p>
                    <p>${dirasakan}</p>
                    ${peta ? `<p><a href="${esc(peta)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map-location-dot"></i> Buka lokasi gempa</a></p>` : ''}
                </div>
            </div>`;
        }).join('') +
        `<div class="status-note">
            <i class="fa-solid fa-location-crosshairs"></i>
            Jarak dihitung garis lurus dari lokasi perangkat Anda ke episenter. Gempa yang episenternya jauh
            tetap dapat terasa bila magnitudonya besar dan kedalamannya dangkal.
            Kedalaman adalah jarak episenter ke permukaan tanah; gempa berkedalaman kurang dari 60 km tergolong dangkal.
        </div>`;
}

/* Peta episenter di sekitar pengguna (Leaflet, dimuat hanya bila tersedia). */
let petaDekat = null;
let layerDekat = null;

function sembunyikanPetaDekat() {
    const el = document.getElementById('nearestMap');
    if (el) el.style.display = 'none';
}

function siapkanPetaDekat() {
    const el = document.getElementById('nearestMap');

    if (!el || typeof L === 'undefined') {
        if (el) el.style.display = 'none';
        return null;
    }

    el.style.display = '';

    if (!petaDekat) {
        petaDekat = L.map(el, {
            center: [lokasiPengguna.lat, lokasiPengguna.lon],
            zoom: 6,
            scrollWheelZoom: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(petaDekat);

        layerDekat = L.layerGroup().addTo(petaDekat);
    }

    return petaDekat;
}

function gambarPetaDekat(daftar, radius) {
    const peta = siapkanPetaDekat();

    if (!peta || !layerDekat) return;

    layerDekat.clearLayers();

    const lingkaranRadius = L.circle([lokasiPengguna.lat, lokasiPengguna.lon], {
        radius: radius * 1000,
        color: '#0d94ad',
        weight: 1,
        fillColor: '#0d94ad',
        fillOpacity: 0.06
    }).bindPopup('Radius ' + radius.toLocaleString('id-ID') + ' km dari lokasi Anda').addTo(layerDekat);

    L.circleMarker([lokasiPengguna.lat, lokasiPengguna.lon], {
        radius: 7,
        color: '#f5f9fd',
        fillColor: '#0d94ad',
        fillOpacity: 1,
        weight: 2
    }).bindPopup('Lokasi Anda' + (lokasiPengguna.nama ? ': ' + lokasiPengguna.nama : '')).addTo(layerDekat);

    daftar.slice(0, 30).forEach(item => {
        const magnitude = parseFloat(String(field(item.gempa, 'Magnitude', '0')).replace(',', '.')) || 0;

        L.circleMarker([item.koord.lat, item.koord.lon], {
            radius: 4 + Math.min(12, Math.max(0, magnitude) * 1.4),
            color: '#dbe5f0',
            fillColor: magnitude >= 5 ? '#e2451b' : '#d98a00',
            fillOpacity: 0.85,
            weight: 1
        })
            .bindPopup(
                popupGempa(item.gempa) +
                '<br><strong>' + angkaJarak(item.jarak) + ' km dari Anda</strong>'
            )
            .addTo(layerDekat);
    });

    /* Batas tampilan selalu menyertakan lingkaran radius, jadi lingkaran tidak
     * hilang saat peta menyesuaikan diri dengan episenter. */
    const batas = lingkaranRadius.getBounds();

    daftar.slice(0, 30).forEach(item => batas.extend([item.koord.lat, item.koord.lon]));

    /* Fit dilakukan setelah ukuran peta pasti: fitBounds tanpa animasi dan
     * hanya sekali supaya lingkarannya tidak terpotong maupun terlalu jauh. */
    setTimeout(function () {
        peta.invalidateSize();
        peta.fitBounds(batas, { padding: [28, 28], maxZoom: 9, animate: false });
    }, 120);
}

/*
 * Peta pada blok "Informasi Gempa Terbaru": episenter kejadian paling baru
 * ditonjolkan, gempa lain dari daftar BMKG ikut ditandai sebagai konteks
 * wilayah. Peta ini ikut tergambar ulang setiap auto-refresh 60 detik,
 * jadi gempa baru langsung muncul tanpa reload halaman.
 */
let petaTerbaru = null;
let layerTerbaru = null;
let kunciPetaTerbaru = '';

function koordinatGempa(gempa) {
    const koord = parseKoordinat(field(gempa, 'Lintang'), field(gempa, 'Bujur'));

    if (koord) return koord;

    const bagian = String(field(gempa, 'Coordinates', '')).split(',');
    const lat = Number((bagian[0] || '').trim());
    const lon = Number((bagian[1] || '').trim());

    return Number.isFinite(lat) && Number.isFinite(lon) ? { lat, lon } : null;
}

function magnitudGempa(gempa) {
    return parseFloat(String(field(gempa, 'Magnitude', '0')).replace(',', '.')) || 0;
}

function popupGempa(gempa) {
    const koord = koordinatGempa(gempa);
    const potensi = gempaDirasakan(gempa)
        ? 'Dirasakan: ' + esc(field(gempa, 'Dirasakan', ''))
        : 'Potensi: ' + esc(field(gempa, 'Potensi', 'Belum tersedia'));

    return '<strong>M ' + magnitudeText(field(gempa, 'Magnitude')) + ' &middot; ' +
        esc(field(gempa, 'Wilayah')) + '</strong><br>' +
        'Kedalaman ' + esc(field(gempa, 'Kedalaman')) + '<br>' +
        'Waktu ' + esc(field(gempa, 'Tanggal')) + ' ' + esc(field(gempa, 'Jam')) + '<br>' +
        (koord ? koord.lat.toFixed(2) + '°, ' + koord.lon.toFixed(2) + '°<br>' : '') +
        potensi;
}

function siapkanPetaTerbaru() {
    const el = document.getElementById('latestMap');
    const kartu = document.getElementById('latestMapCard');

    /* Leaflet dimuat dari CDN; bila tidak tersedia, kartu peta disembunyikan. */
    if (!el || typeof L === 'undefined') {
        if (kartu) kartu.style.display = 'none';
        return null;
    }

    if (!petaTerbaru) {
        petaTerbaru = L.map(el, {
            center: [-2.5, 118],
            zoom: 5,
            scrollWheelZoom: false
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(petaTerbaru);

        layerTerbaru = L.layerGroup().addTo(petaTerbaru);
    }

    return petaTerbaru;
}

function gambarPetaTerbaru(data) {
    const peta = siapkanPetaTerbaru();
    const catatan = document.getElementById('latestMapNote');

    if (!peta || !layerTerbaru) return;

    layerTerbaru.clearLayers();

    const terbaru = data && data.terbaru ? data.terbaru : null;
    const daftar = semuaGempa(data)
        .sort(function (a, b) { return waktuGempa(b) - waktuGempa(a); })
        .slice(0, 25);

    if (!terbaru) {
        peta.setView([-2.5, 118], 5);
        catatan.textContent = 'DATA TIDAK TERSEDIA';
        return;
    }

    const koord = koordinatGempa(terbaru);

    if (!koord) {
        peta.setView([-2.5, 118], 5);
        catatan.textContent = 'KOORDINAT BELUM TERBACA';
        return;
    }

    daftar.forEach(gempa => {
        if (gempa === terbaru) return;

        const k = koordinatGempa(gempa);

        if (!k) return;

        const mag = magnitudGempa(gempa);

        L.circleMarker([k.lat, k.lon], {
            radius: 4 + Math.min(9, mag * 1.1),
            color: '#dbe5f0',
            fillColor: mag >= 5 ? '#e2451b' : '#d98a00',
            fillOpacity: 0.5,
            weight: 1
        })
            .bindPopup(popupGempa(gempa))
            .addTo(layerTerbaru);
    });

    const mag = magnitudGempa(terbaru);

    L.circleMarker([koord.lat, koord.lon], {
        radius: 12,
        color: '#e2451b',
        fillColor: '#e2451b',
        fillOpacity: 0.12,
        weight: 2,
        className: 'denyut-terbaru'
    }).addTo(layerTerbaru);

    L.circleMarker([koord.lat, koord.lon], {
        radius: 5 + Math.min(14, mag * 1.6),
        color: '#f5f9fd',
        fillColor: mag >= 5 ? '#e2451b' : '#d98a00',
        fillOpacity: 1,
        weight: 2
    })
        .bindPopup(popupGempa(terbaru))
        .bindTooltip('TERBARU · M ' + magnitudeText(field(terbaru, 'Magnitude')), {
            permanent: true,
            direction: 'right',
            offset: [10, 0],
            className: 'terbaru-label'
        })
        .addTo(layerTerbaru);

    /* Batas peta hanya digeser bila kejadian terbarunya berganti, supaya
     * posisi yang sedang diatur manual pengguna tidak melompat setiap menit. */
    const kunci = [
        field(terbaru, 'Tanggal', ''),
        field(terbaru, 'Jam', ''),
        field(terbaru, 'Coordinates', ''),
        field(terbaru, 'Magnitude', '')
    ].join('|');

    if (kunci !== kunciPetaTerbaru) {
        kunciPetaTerbaru = kunci;

        /* Semua titik yang barusan ditandai ikut masuk batas. Kalau hanya
         * lingkungan gempa terbaru yang dipakai, satu kejadian yang sendirian
         * (misalnya di lepas pantai barat Sumatra) membuat peta zoom ke area
         * kosong dan seluruh gempa lain justru tidak terlihat. */
        const titik = [[koord.lat, koord.lon]];

        daftar.forEach(gempa => {
            if (gempa === terbaru) return;

            const k = koordinatGempa(gempa);

            if (k) titik.push([k.lat, k.lon]);
        });

        if (titik.length > 1) {
            peta.fitBounds(titik, { padding: [30, 30], maxZoom: 5 });
        } else {
            peta.setView([koord.lat, koord.lon], 5);
        }
    }

    catatan.textContent = 'TERBARU ' + String(field(terbaru, 'Wilayah')) +
        ' · ' + daftar.length + ' gempa di peta';
    setTimeout(function () { peta.invalidateSize(); }, 120);
}

let sedangMemuat = false;
let timerAutoRefresh = null;

const endpointData = window.location.pathname + '?ajax=1';

function esc(value) {
    return String(value ?? '-').replace(/[&<>"']/g, function (char) {
        return {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        }[char];
    });
}

function field(gempa, key, fallback = '-') {
    if (!gempa || gempa[key] === undefined || gempa[key] === null || gempa[key] === '') {
        return fallback;
    }
    return gempa[key];
}

function magnitudeClass(value) {
    const m = parseFloat(value);
    if (!Number.isFinite(m)) return 'mag-rendah';
    if (m >= 6) return 'mag-besar';
    if (m >= 5) return 'mag-kuat';
    if (m >= 4) return 'mag-sedang';
    return 'mag-rendah';
}

function magnitudeText(value) {
    const n = Number(value);
    return Number.isFinite(n) ? n.toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1
    }) : esc(value);
}

function mapLink(gempa) {
    let coords = field(gempa, 'Coordinates', '');

    if (coords) {
        const parts = String(coords).split(',');

        if (parts.length >= 2) {
            const lat = Number(parts[0].trim());
            const lon = Number(parts[1].trim());

            if (Number.isFinite(lat) && Number.isFinite(lon)) {
                return 'https://www.google.com/maps?q=' +
                    encodeURIComponent(lat + ',' + lon);
            }
        }
    }

    // Jika koordinat numerik tidak tersedia, gunakan pencarian lokasi.
    const lintang = field(gempa, 'Lintang', '');
    const bujur = field(gempa, 'Bujur', '');

    if (lintang && bujur) {
        return 'https://www.google.com/maps/search/?api=1&query=' +
            encodeURIComponent(lintang + ' ' + bujur);
    }

    return '';
}

function shakemapLink(gempa) {
    const raw = field(gempa, 'Shakemap', '');

    if (!raw) return '';

    const filename = String(raw).split('/').pop();

    if (!filename || filename === '-') return '';

    return 'https://static.bmkg.go.id/' + encodeURIComponent(filename);
}

function renderLatest(gempa) {
    const panel = document.getElementById('latestPanel');

    if (!gempa) {
        panel.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong>Data gempa terbaru belum tersedia.</strong>
                <p>Periksa koneksi internet atau coba perbarui data beberapa saat lagi.</p>
            </div>`;

        document.getElementById('latestBadge').textContent = 'DATA TIDAK TERSEDIA';
        return;
    }

    const lokasi = field(gempa, 'Wilayah');
    const tanggal = field(gempa, 'Tanggal');
    const jam = field(gempa, 'Jam');
    const mag = field(gempa, 'Magnitude');
    const kedalaman = field(gempa, 'Kedalaman');
    const lintang = field(gempa, 'Lintang');
    const bujur = field(gempa, 'Bujur');
    const potensi = field(gempa, 'Potensi', 'Informasi potensi belum tersedia');
    const peta = mapLink(gempa);
    const shakemap = shakemapLink(gempa);

    panel.innerHTML = `
        <div class="panel-top">
            <div>
                <h3><i class="fa-solid fa-tower-broadcast" style="color:var(--cyan);margin-right:8px"></i> Ringkasan Kejadian Terbaru</h3>
                <p>${esc(tanggal)} · ${esc(jam)}</p>
            </div>
            <div class="latest-mag">M ${magnitudeText(mag)}<small>MAGNITUDO</small></div>
        </div>

        <div class="latest-location">
            <i class="fa-solid fa-location-dot" style="color:var(--cyan);margin-right:8px"></i>${esc(lokasi)}
        </div>

        <div class="latest-meta">
            <div class="meta-cell"><span><i class="fa-solid fa-arrow-down"></i> Kedalaman</span><strong>${esc(kedalaman)}</strong></div>
            <div class="meta-cell"><span><i class="fa-solid fa-compass"></i> Koordinat</span><strong>${esc(lintang)}, ${esc(bujur)}</strong></div>
            <div class="meta-cell"><span><i class="fa-regular fa-calendar"></i> Tanggal</span><strong>${esc(tanggal)}</strong></div>
            <div class="meta-cell"><span><i class="fa-regular fa-clock"></i> Waktu</span><strong>${esc(jam)}</strong></div>
            <div class="meta-cell" style="grid-column:1/-1"><span><i class="fa-solid fa-circle-info"></i> Potensi</span><strong>${esc(potensi)}</strong></div>
        </div>

        <div class="hero-links">
            ${peta ? `<a class="btn-primary" href="${esc(peta)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map-location-dot"></i> Buka Lokasi Gempa</a>` : ''}
            ${shakemap ? `<a class="btn-secondary" href="${esc(shakemap)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map"></i> Lihat Shakemap BMKG</a>` : ''}
        </div>`;

    document.getElementById('latestBadge').textContent = 'DATA TERSEDIA';
}

function renderCard(gempa, index) {
    const mag = field(gempa, 'Magnitude');
    const potensi = field(gempa, 'Potensi', 'Informasi potensi tidak tersedia');
    const dirasakan = field(gempa, 'Dirasakan', '');
    const peta = mapLink(gempa);
    const shakemap = shakemapLink(gempa);
    const aman = String(potensi).toLowerCase().includes('tidak berpotensi tsunami');

    return `
    <article class="quake-card">
        <div class="quake-card-head">
            <div class="quake-number">
                <span class="quake-index">${index}</span>
                <div>
                    <div class="quake-date">${esc(field(gempa, 'Tanggal'))}</div>
                    <div class="quake-time"><i class="fa-regular fa-clock"></i>${esc(field(gempa, 'Jam'))}</div>
                </div>
            </div>
            <span class="magnitude ${magnitudeClass(mag)}">M ${magnitudeText(mag)}</span>
        </div>

        <div class="quake-location">
            <span class="location-icon"><i class="fa-solid fa-location-dot"></i></span>
            <div>
                <div class="field-label">LOKASI GEMPA</div>
                <div class="location-name">${esc(field(gempa, 'Wilayah'))}</div>
            </div>
        </div>

        <div class="quake-details">
            <div class="detail-box"><span><i class="fa-solid fa-arrow-down"></i> Kedalaman</span><strong>${esc(field(gempa, 'Kedalaman'))}</strong></div>
            <div class="detail-box"><span><i class="fa-solid fa-compass"></i> Lintang</span><strong>${esc(field(gempa, 'Lintang'))}</strong></div>
            <div class="detail-box"><span><i class="fa-solid fa-globe"></i> Bujur</span><strong>${esc(field(gempa, 'Bujur'))}</strong></div>
        </div>

        <div class="quake-status ${aman ? 'status-aman' : 'status-info'}">
            <i class="fa-solid fa-circle-info"></i>
            <div><strong>Informasi potensi</strong><p>${esc(potensi)}</p></div>
        </div>

        ${dirasakan && dirasakan !== '-' ? `
            <div class="felt-info">
                <div class="field-label"><i class="fa-solid fa-house-crack"></i> WILAYAH YANG MERASAKAN</div>
                <p>${esc(dirasakan)}</p>
            </div>` : ''}

        <div class="quake-actions">
            ${peta ? `<a class="btn-secondary" href="${esc(peta)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map-location-dot"></i> Lihat Lokasi</a>` : ''}
            ${shakemap ? `<a class="btn-secondary" href="${esc(shakemap)}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-map"></i> Shakemap BMKG</a>` : ''}
        </div>
    </article>`;
}

function renderList(targetId, data, emptyMessage) {
    const target = document.getElementById(targetId);

    if (!Array.isArray(data) || data.length === 0) {
        target.innerHTML = `
            <div class="empty-state" style="grid-column:1/-1">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <strong>${esc(emptyMessage)}</strong>
                <p>Data belum diterima. Periksa koneksi internet atau coba lagi nanti.</p>
            </div>`;
        return;
    }

    target.innerHTML = data.map((gempa, index) =>
        renderCard(gempa, index + 1)
    ).join('');
}

function tampilkanData(data) {
    gempaData = data;

    renderLatest(data.terbaru);
    gambarPetaTerbaru(data);
    renderNearest(data);

    const terbaru = semuaGempa(data).sort(function (a, b) {
        return waktuGempa(b) - waktuGempa(a);
    });

    renderList('listSemua', terbaru, 'Daftar gempa terbaru belum tersedia.');
    renderList('listM5', data.m5, 'Daftar gempa M 5,0+ belum tersedia.');
    renderList('listFelt', data.dirasa, 'Daftar gempa dirasakan belum tersedia.');

    const periode = document.getElementById('periodeNasional');

    if (periode) {
        periode.textContent = terbaru.length ? keteranganPeriode(terbaru) + ' ' : '';
    }

    document.getElementById('countLatest').textContent = data.terbaru ? '1' : '0';
    document.getElementById('countM5').textContent = Array.isArray(data.m5) ? data.m5.length : 0;
    document.getElementById('countFelt').textContent = Array.isArray(data.dirasa) ? data.dirasa.length : 0;
    document.getElementById('tabCountSemua').textContent = terbaru.length;
    document.getElementById('tabCountM5').textContent = Array.isArray(data.m5) ? data.m5.length : 0;
    document.getElementById('tabCountFelt').textContent = Array.isArray(data.dirasa) ? data.dirasa.length : 0;

    if (data.waktuAkses) {
        document.getElementById('lastUpdate').textContent = data.waktuAkses;
    }
}

function setConnection(status, message) {
    const dot = document.getElementById('connectionDot');
    const text = document.getElementById('connectionText');

    dot.classList.remove('error', 'loading');

    if (status === 'loading') {
        dot.classList.add('loading');
    } else if (status === 'error') {
        dot.classList.add('error');
    }

    text.textContent = message;
}

async function refreshGempa(manual = false) {
    if (sedangMemuat) return;

    sedangMemuat = true;

    const button = document.getElementById('refreshButton');

    if (manual) {
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Mengambil Data...';
        button.disabled = true;
    }

    setConnection('loading', 'Sedang memeriksa data resmi BMKG...');

    try {
        const separator = endpointData.includes('?') ? '&' : '?';

        const response = await fetch(
            endpointData + separator + '_=' + Date.now(),
            {
                method: 'GET',
                cache: 'no-store',
                headers: {'Accept': 'application/json'}
            }
        );

        if (!response.ok) {
            throw new Error('Server lokal mengembalikan status ' + response.status);
        }

        const data = await response.json();

        if (!data || typeof data !== 'object') {
            throw new Error('Format data tidak valid');
        }

        if (!data.berhasil) {
            throw new Error('Data BMKG belum dapat diterima');
        }

        tampilkanData(data);
        setConnection('ok', 'Terhubung · data berhasil diperiksa');

    } catch (error) {
        console.error('Pembaruan data gempa gagal:', error);

        setConnection('error', 'Pembaruan gagal · data terakhir tetap ditampilkan');

        // Jangan hapus data terakhir yang berhasil ditampilkan.
        if (!gempaData || !gempaData.berhasil) {
            document.getElementById('latestPanel').innerHTML = `
                <div class="empty-state">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <strong>Data gempa belum dapat dimuat.</strong>
                    <p>Periksa koneksi internet, cURL/allow_url_fopen PHP, atau coba lagi nanti.</p>
                </div>`;
        }

    } finally {
        sedangMemuat = false;

        if (manual) {
            button.disabled = false;
            button.innerHTML = '<i class="fa-solid fa-rotate"></i> Perbarui Data';
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Tampilkan data awal yang sudah diambil PHP.
    tampilkanData(gempaData);

    setConnection(
        gempaData && gempaData.berhasil ? 'ok' : 'error',
        gempaData && gempaData.berhasil
            ? 'Data awal berhasil dimuat'
            : 'Data awal belum tersedia'
    );

    document.querySelectorAll('.tab-button').forEach(function (button) {
        button.addEventListener('click', function () {
            document.querySelectorAll('.tab-button').forEach(function (tab) {
                tab.classList.remove('active');
            });

            document.querySelectorAll('.tab-panel').forEach(function (panel) {
                panel.classList.remove('active');
            });

            button.classList.add('active');
            document.getElementById(button.dataset.tab).classList.add('active');
        });
    });

    /*
     * Mode tampilan: gempa di sekitar lokasi perangkat (dengan radius)
     * atau seluruh kejadian terbaru di Indonesia.
     */
    const tombolMode = document.querySelectorAll('.mode-button');

    function terapkanMode(mode) {
        const lokal = document.getElementById('modeLokal');
        const nasional = document.getElementById('modeNasional');

        tombolMode.forEach(function (tombol) {
            tombol.classList.toggle('active', tombol.dataset.mode === mode);
        });

        if (lokal) lokal.style.display = mode === 'lokal' ? '' : 'none';
        if (nasional) nasional.style.display = mode === 'nasional' ? '' : 'none';

        try {
            window.localStorage.setItem('bmkg_mode_gempa', mode);
        } catch (e) {
            /* Storage bisa diblokir browser; abaikan. */
        }

        if (mode === 'lokal') {
            renderNearest(gempaData);
        }
    }

    tombolMode.forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            terapkanMode(tombol.dataset.mode);
        });
    });

    ['radiusKm', 'hanyaDirasakan'].forEach(function (id) {
        const el = document.getElementById(id);
        if (el) el.addEventListener('change', function () { renderNearest(gempaData); });
    });

    let modeTersimpan = 'lokal';
    try {
        modeTersimpan = window.localStorage.getItem('bmkg_mode_gempa') || 'lokal';
    } catch (e) {
        /* Pakai mode default. */
    }

    terapkanMode(modeTersimpan === 'nasional' ? 'nasional' : 'lokal');

    document.getElementById('refreshButton').addEventListener('click', function () {
        refreshGempa(true);
    });

    /* Tombol "Gunakan Lokasi Saya" dibuat ulang oleh renderNearest(), jadi
     * penanganannya memakai delegasi agar selalu aktif. */
    document.addEventListener('click', function (event) {
        const tombol = event.target.closest('[data-deteksi-lokasi]');

        if (!tombol) return;

        event.preventDefault();

        if (window.LokasiOtomatis) {
            window.LokasiOtomatis.deteksi({ reload: true });
        }
    });

    // Cek ulang data setiap 60 detik selama halaman tetap terbuka.
    timerAutoRefresh = window.setInterval(function () {
        refreshGempa(false);
    }, 60000);

    // Jika halaman kembali aktif setelah pindah tab, cek ulang juga.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            refreshGempa(false);
        }
    });
});
</script>
<script src="assets/lokasi-otomatis.js" data-auto="1"></script>
</body>
</html>