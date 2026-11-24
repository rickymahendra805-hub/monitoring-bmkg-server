<?php
/* =========================================================
   GRAFIK & ANALISIS CUACA BMKG KEMAYORAN
   Navbar mengikuti dashboard.php
   Menu Pemantauan Kota ditambahkan di dropdown Atmosfer
   Data lama dipertahankan dari data/cuaca.json
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
$currentPage = basename($_SERVER['PHP_SELF']);

/* ================= ICON CUACA ================= */

function iconCuaca($kondisi)
{
    $kondisi = strtolower(trim((string)$kondisi));

    if (strpos($kondisi, 'petir') !== false) return "⛈️";
    if (strpos($kondisi, 'hujan lebat') !== false) return "🌧️";
    if (strpos($kondisi, 'hujan sedang') !== false) return "🌦️";
    if (strpos($kondisi, 'hujan ringan') !== false) return "🌦️";
    if (strpos($kondisi, 'hujan') !== false) return "🌧️";
    if (strpos($kondisi, 'berawan') !== false) return "🌤️";
    if (strpos($kondisi, 'mendung') !== false) return "☁️";
    if (strpos($kondisi, 'cerah') !== false) return "☀️";

    return "🌤️";
}

function menuAktif($halaman, $currentPage)
{
    return $halaman === $currentPage ? "active" : "";
}

/* ================= FORMAT TANGGAL ================= */

function tanggalGrafik($tanggal, $waktu = '')
{
    $bulan = [
        1 => 'Jan', 2 => 'Feb', 3 => 'Mar',
        4 => 'Apr', 5 => 'Mei', 6 => 'Jun',
        7 => 'Jul', 8 => 'Agu', 9 => 'Sep',
        10 => 'Okt', 11 => 'Nov', 12 => 'Des'
    ];

    $tanggal = trim((string)$tanggal);
    $waktu = trim((string)$waktu);

    $timestamp = strtotime($tanggal);

    if ($timestamp !== false) {
        $hari = date('d', $timestamp);
        $bulanAngka = (int)date('n', $timestamp);
        $tahun = date('Y', $timestamp);

        $hasil = $hari . ' ' . $bulan[$bulanAngka] . ' ' . $tahun;

        if ($waktu !== '') {
            $waktuTimestamp = strtotime($waktu);
            $hasil .= ' ' . (
                $waktuTimestamp !== false
                ? date('H:i', $waktuTimestamp)
                : $waktu
            );
        }

        return $hasil;
    }

    return trim($tanggal . ' ' . $waktu);
}

/* ================= STATISTIK DAN DATA GRAFIK ================= */

$suhuData = [];
$kelembapanData = [];
$hujanData = [];
$labels = [];

$totalSuhu = 0;
$totalKelembapan = 0;
$totalAngin = 0;
$totalHujan = 0;

$maxSuhu = null;
$minSuhu = null;
$maxAngin = null;
$maxHujan = null;

foreach ($data as $item) {
    if (!is_array($item)) {
        continue;
    }

    $suhu = (float)($item['suhu'] ?? 0);
    $kelembapan = (float)($item['kelembapan'] ?? 0);
    $hujan = (float)($item['curah_hujan'] ?? 0);
    $angin = (float)($item['kecepatan_angin'] ?? 0);

    $suhuData[] = $suhu;
    $kelembapanData[] = $kelembapan;
    $hujanData[] = $hujan;

    $labels[] = tanggalGrafik(
        $item['tanggal'] ?? '',
        $item['waktu'] ?? ''
    );

    $totalSuhu += $suhu;
    $totalKelembapan += $kelembapan;
    $totalAngin += $angin;
    $totalHujan += $hujan;

    if ($maxSuhu === null || $suhu > $maxSuhu) {
        $maxSuhu = $suhu;
    }

    if ($minSuhu === null || $suhu < $minSuhu) {
        $minSuhu = $suhu;
    }

    if ($maxAngin === null || $angin > $maxAngin) {
        $maxAngin = $angin;
    }

    if ($maxHujan === null || $hujan > $maxHujan) {
        $maxHujan = $hujan;
    }
}

$rataSuhu = $totalData ? $totalSuhu / $totalData : 0;
$rataKelembapan = $totalData ? $totalKelembapan / $totalData : 0;
$rataAngin = $totalData ? $totalAngin / $totalData : 0;

/* ================= MAWAR ANGIN (WINDROSE) =================
   Arah pada mawar angin adalah arah DATANGNYA angin, sama seperti
   kode wd pada API BMKG. Derajat (wd_deg) dipakai supaya sectornya
   halus, bukan huruf arah yang bisa satu huruf mewakili rentang luas. */

$arahWindrose = [
    'N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE',
    'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW'
];

$namaArahWindrose = [
    'N' => 'Utara',
    'NNE' => 'Utara - Timur Laut',
    'NE' => 'Timur Laut',
    'ENE' => 'Timur - Timur Laut',
    'E' => 'Timur',
    'ESE' => 'Timur - Tenggara',
    'SE' => 'Tenggara',
    'SSE' => 'Selatan - Tenggara',
    'S' => 'Selatan',
    'SSW' => 'Selatan - Barat Daya',
    'SW' => 'Barat Daya',
    'WSW' => 'Barat - Barat Daya',
    'W' => 'Barat',
    'WNW' => 'Barat - Barat Laut',
    'NW' => 'Barat Laut',
    'NNW' => 'Utara - Barat Laut'
];

$batasWindrose = [0, 5, 10, 15, 20];

$jumlahWindrose = array_fill_keys($arahWindrose, 0);
$totalKecepatanWindrose = array_fill_keys($arahWindrose, 0.0);
$binWindrose = [];

foreach ($arahWindrose as $arah) {
    $binWindrose[$arah] = array_fill(0, count($batasWindrose), 0);
}

$totalWindrose = 0;

foreach ($data as $item) {
    if (!is_array($item)) {
        continue;
    }

    $derajat = $item['arah_derajat'] ?? null;
    $kecepatan = $item['kecepatan_angin'] ?? null;

    if (!is_numeric($derajat) || !is_numeric($kecepatan)) {
        continue;
    }

    $derajat = (float)$derajat;
    $kecepatan = (float)$kecepatan;

    $indeksArah = (int)floor((fmod($derajat + 11.25, 360)) / 22.5) % 16;
    $arah = $arahWindrose[$indeksArah];

    $indeksBin = count($batasWindrose) - 1;
    foreach ($batasWindrose as $urutan => $batas) {
        if ($urutan === 0) {
            continue;
        }

        if ($kecepatan < $batas) {
            $indeksBin = $urutan - 1;
            break;
        }
    }

    $jumlahWindrose[$arah]++;
    $totalKecepatanWindrose[$arah] += $kecepatan;
    $binWindrose[$arah][$indeksBin]++;
    $totalWindrose++;
}

$rataArahWindrose = [];
foreach ($arahWindrose as $arah) {
    $rataArahWindrose[$arah] = $jumlahWindrose[$arah] > 0
        ? round($totalKecepatanWindrose[$arah] / $jumlahWindrose[$arah], 1)
        : 0;
}

$labelBinWindrose = [];
foreach ($batasWindrose as $urutan => $batas) {
    if ($urutan === 0) {
        $labelBinWindrose[$urutan] = '< ' . $batasWindrose[1] . ' km/jam';
        continue;
    }

    if ($urutan === count($batasWindrose) - 1) {
        $labelBinWindrose[$urutan] = '>= ' . $batas . ' km/jam';
        continue;
    }

    $labelBinWindrose[$urutan] = $batas . '-' . $batasWindrose[$urutan + 1] . ' km/jam';
}

$windrose = [
    'arah' => $arahWindrose,
    'jumlah' => array_values($jumlahWindrose),
    'rata' => array_values($rataArahWindrose),
    'bin' => array_values($binWindrose),
    'labelBin' => array_values($labelBinWindrose),
    'total' => $totalWindrose
];

/* Periode dan resolusi waktu yang benar-benar dipakai mawar angin.
   Interval dihitung dari selisih antar slot, bukan ditulis manual,
   supaya keterangan di halaman tidak bisa berbeda dari datanya. */

$slotWindrose = [];

foreach ($data as $item) {
    $titikWaktu = strtotime((string)($item['local_datetime'] ?? ''));

    if ($titikWaktu !== false) {
        $slotWindrose[] = $titikWaktu;
    }
}

sort($slotWindrose);
$slotWindrose = array_values(array_unique($slotWindrose));

$intervalWindrose = null;

if (count($slotWindrose) > 1) {
    $hitungSelisih = [];

    for ($i = 1; $i < count($slotWindrose); $i++) {
        $jam = (int)round(($slotWindrose[$i] - $slotWindrose[$i - 1]) / 3600);

        if ($jam > 0) {
            $hitungSelisih[$jam] = ($hitungSelisih[$jam] ?? 0) + 1;
        }
    }

    if ($hitungSelisih) {
        arsort($hitungSelisih);
        $intervalWindrose = (int)key($hitungSelisih);
    }
}

/* Label zona waktu diambil dari data sendiri: selisih local_datetime
   terhadap utc_datetime pada API BMKG, jadi tidak perlu menulis "UTC+7"
   secara manual. */

$selisihZona = null;

foreach ($data as $item) {
    $waktuLokal = (string)($item['local_datetime'] ?? '');
    $waktuUtc = (string)($item['utc_datetime'] ?? '');

    if ($waktuLokal !== '' && $waktuUtc !== '') {
        $selisihZona = (int)round((strtotime($waktuLokal) - strtotime($waktuUtc)) / 3600);
        break;
    }
}

$labelZona = $selisihZona !== null
    ? 'WIB (UTC' . ($selisihZona >= 0 ? '+' : '') . $selisihZona . ')'
    : 'waktu lokal';

$totalJamWindrose = count($slotWindrose) * ($intervalWindrose ?? 0);

$periodeWindrose = count($slotWindrose) > 1
    ? tanggalGrafik(date('Y-m-d', $slotWindrose[0]), date('H:i', $slotWindrose[0]))
        . ' s.d. '
        . tanggalGrafik(date('Y-m-d', end($slotWindrose)), date('H:i', end($slotWindrose)))
        . ' ' . $labelZona
    : '';

/* Ringkasan yang ditulis ke sisi mawar angin. Semuanya diturunkan dari
   slot yang sama dengan grafik, tanpa angka manual, jadi teksnya ikut
   berubah sendiri saat data BMKG berganti hari. */

$jumlahPerHari = [];
$totalKecepatanSemua = 0.0;

foreach ($data as $item) {
    if (!is_array($item)) {
        continue;
    }

    $derajat = $item['arah_derajat'] ?? null;
    $kecepatan = $item['kecepatan_angin'] ?? null;
    $tanggalItem = trim((string)($item['tanggal'] ?? ''));

    if (!is_numeric($derajat) || !is_numeric($kecepatan) || $tanggalItem === '') {
        continue;
    }

    $arahItem = $arahWindrose[(int)floor(fmod((float)$derajat + 11.25, 360) / 22.5) % 16];
    $kecepatan = (float)$kecepatan;

    if (!isset($jumlahPerHari[$tanggalItem])) {
        $jumlahPerHari[$tanggalItem] = [
            'slot' => 0,
            'arah' => [],
            'totalKecepatan' => 0.0,
            'maks' => null,
            'maksWaktu' => '',
            'maksArah' => ''
        ];
    }

    $jumlahPerHari[$tanggalItem]['slot']++;
    $jumlahPerHari[$tanggalItem]['arah'][$arahItem] =
        ($jumlahPerHari[$tanggalItem]['arah'][$arahItem] ?? 0) + 1;
    $jumlahPerHari[$tanggalItem]['totalKecepatan'] += $kecepatan;

    if ($jumlahPerHari[$tanggalItem]['maks'] === null
        || $kecepatan > $jumlahPerHari[$tanggalItem]['maks']) {
        $jumlahPerHari[$tanggalItem]['maks'] = $kecepatan;
        $jumlahPerHari[$tanggalItem]['maksWaktu'] = tanggalGrafik(
            $tanggalItem,
            $item['waktu'] ?? ''
        );
        $jumlahPerHari[$tanggalItem]['maksArah'] = $arahItem;
    }

    $totalKecepatanSemua += $kecepatan;
}

ksort($jumlahPerHari);

/* Arah terbanyak, rata-rata, dan puncak kecepatan per tanggal, plus
   slot paling kencang sepanjang periode. */

$maksPeriode = ['nilai' => null, 'waktu' => '', 'arah' => ''];

foreach ($jumlahPerHari as $tanggalHari => &$isiHari) {
    arsort($isiHari['arah']);
    $kodeHari = (string)key($isiHari['arah']);

    $isiHari['arahDominan'] = $kodeHari;
    $isiHari['slotDominan'] = $isiHari['arah'][$kodeHari];
    $isiHari['rata'] = $isiHari['slot'] > 0
        ? $isiHari['totalKecepatan'] / $isiHari['slot']
        : 0;

    if ($isiHari['maks'] !== null
        && ($maksPeriode['nilai'] === null || $isiHari['maks'] > $maksPeriode['nilai'])) {
        $maksPeriode = [
            'nilai' => $isiHari['maks'],
            'waktu' => $isiHari['maksWaktu'],
            'arah' => $isiHari['maksArah']
        ];
    }
}

unset($isiHari);

$peringkatArah = $jumlahWindrose;
arsort($peringkatArah);
$arahDominan = (string)key($peringkatArah);

$slotTenang = 0;
$slotKencang = 0;
$batasKencang = $batasWindrose[count($batasWindrose) - 2];

foreach ($binWindrose as $binArah) {
    $slotTenang += $binArah[0];

    for ($i = count($batasWindrose) - 2; $i < count($binArah); $i++) {
        $slotKencang += $binArah[$i];
    }
}

$rataSemuaWindrose = $totalWindrose > 0 ? $totalKecepatanSemua / $totalWindrose : 0;

function persenWindrose($jumlah, $total)
{
    return $total > 0 ? round($jumlah / $total * 100, 1) : 0.0;
}

$hariIniWindrose = date('Y-m-d');
$jumlahHariWindrose = count($jumlahPerHari);

/* ================= DATA TERBARU ================= */

$suhuTerbaru = (float)($terbaru['suhu'] ?? 0);
$kelembapanTerbaru = (float)($terbaru['kelembapan'] ?? 0);
$hujanTerbaru = (float)($terbaru['curah_hujan'] ?? 0);
$anginTerbaru = (float)($terbaru['kecepatan_angin'] ?? 0);
$kondisiTerbaru = $terbaru['kondisi_cuaca'] ?? 'Belum ada data';
$arahAnginTerbaru = $terbaru['arah_angin'] ?? '-';

/* ================= INDIKASI CUACA SEDERHANA =================
   Kelas hujan dibandingkan terhadap akumulasi curah hujan satu tanggal,
   bukan nilai satu slot. Angkanya: 5 dan 20 mm per hari adalah ambang
   yang kita pilih sendiri, bukan kutipan kelas resmi. Halaman
   peringatan dini curah hujan harian BMKG hanya memuat tingkat
   Waspada, Siaga, dan Awas tanpa mencantumkan rentang mm di halaman
   yang sama, jadi tidak ada angka resmi yang bisa ditunjuk dari sana. */

$ambangHujanSedang = 5.0;
$ambangHujanLebat = 20.0;

$tanggalAcuan = date('Y-m-d');
$akumulasiHarian = [];

foreach ($data as $item) {
    if (!is_array($item)) {
        continue;
    }

    $kunciTanggal = trim((string)($item['tanggal'] ?? ''));

    if ($kunciTanggal === '') {
        continue;
    }

    if (!isset($akumulasiHarian[$kunciTanggal])) {
        $akumulasiHarian[$kunciTanggal] = ['slot' => 0, 'hujan' => 0.0];
    }

    $akumulasiHarian[$kunciTanggal]['slot']++;
    $akumulasiHarian[$kunciTanggal]['hujan'] += (float)($item['curah_hujan'] ?? 0);
}

if (!isset($akumulasiHarian[$tanggalAcuan])) {
    $tanggalAcuan = $akumulasiHarian ? (string)key($akumulasiHarian) : '';
}

$slotAcuan = $tanggalAcuan !== '' ? $akumulasiHarian[$tanggalAcuan]['slot'] : 0;
$hujanAcuan = $tanggalAcuan !== '' ? $akumulasiHarian[$tanggalAcuan]['hujan'] : 0.0;

$slotSehari = $intervalWindrose ? (int)round(24 / $intervalWindrose) : 0;
$hariPenuh = $slotSehari > 0 && $slotAcuan >= $slotSehari;

if ($tanggalAcuan === '') {
    $dasarIndikasi = 'Belum ada slot prakiraan yang bisa diakumulasi.';
} else {
    $dasarIndikasi = 'Akumulasi ' . $slotAcuan . ' slot pada '
        . tanggalGrafik($tanggalAcuan, '') . ' = '
        . number_format($hujanAcuan, 1, ',', '.') . ' mm'
        . ($hariPenuh
            ? ' (24 jam penuh).'
            : ' (belum 24 jam penuh; satu hari butuh ' . $slotSehari . ' slot).');
}

if (empty($terbaru)) {
    $prediksiJudul = 'Belum ada data cuaca';
    $prediksiIcon = '🌤️';
    $prediksiClass = 'info';
} elseif ($hujanAcuan > $ambangHujanLebat) {
    $prediksiJudul = 'Potensi Hujan Lebat';
    $prediksiIcon = '🌧️';
    $prediksiClass = 'danger';
} elseif ($hujanAcuan > $ambangHujanSedang) {
    $prediksiJudul = 'Potensi Hujan';
    $prediksiIcon = '🌦️';
    $prediksiClass = 'warning';
} elseif (stripos((string)$kondisiTerbaru, 'cerah') !== false) {
    $prediksiJudul = 'Cenderung Cerah';
    $prediksiIcon = '☀️';
    $prediksiClass = 'success';
} elseif (
    stripos((string)$kondisiTerbaru, 'berawan') !== false ||
    stripos((string)$kondisiTerbaru, 'mendung') !== false
) {
    $prediksiJudul = 'Cenderung Berawan';
    $prediksiIcon = '☁️';
    $prediksiClass = 'info';
} else {
    $prediksiJudul = (string)$kondisiTerbaru;
    $prediksiIcon = iconCuaca($kondisiTerbaru);
    $prediksiClass = 'info';
}

$hujanLebat = number_format($ambangHujanLebat, 1, ',', '.');
$hujanSedang = number_format($ambangHujanSedang, 1, ',', '.');

if (empty($terbaru)) {
    $indikasiJelas = $dasarIndikasi;
} elseif ($hujanAcuan > $ambangHujanLebat) {
    $indikasiJelas = $dasarIndikasi
        . ' Angka itu melebihi batas ' . $hujanLebat . ' mm sehingga judulnya "Hujan Lebat".';
} elseif ($hujanAcuan > $ambangHujanSedang) {
    $indikasiJelas = $dasarIndikasi
        . ' Angka itu berada di antara ' . $hujanSedang . ' dan ' . $hujanLebat
        . ' mm sehingga judulnya "Potensi Hujan".';
} else {
    $indikasiJelas = $dasarIndikasi
        . ' Karena tidak melewati ' . $hujanSedang . ' mm, judul diambil dari kolom'
        . ' weather_desc pada slot terakhir, yaitu "' . trim((string)$kondisiTerbaru) . '".';
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Grafik Cuaca | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
:root {
    --bg: #eef3f9;
    --surface: #ffffff;
    --line: rgba(35,70,110,0.208);
    --text: #16293f;
    --muted: #5c748c;
    --blue: #0f61bd;
    --cyan: #10a6bc;
    --green: #24a873;
    --radius: 20px;
    --shadow: 0 16px 40px rgba(16,42,67,0.048);
}

* { margin: 0; padding: 0; box-sizing: border-box; }
html { scroll-behavior: smooth; }

body {
    min-height: 100vh;
    font-family: 'Inter', Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(ellipse at 8% 0%, rgba(37,140,255,.13), transparent 32%),
        radial-gradient(ellipse at 95% 20%, rgba(62,216,239,.07), transparent 30%),
        var(--bg);
}

a { color: inherit; text-decoration: none; }
button { font: inherit; }
button, a { -webkit-tap-highlight-color: transparent; }

/* ================ KONTEN UTAMA ================ */

.main {
    width: min(1600px,100%);
    margin: 0 auto;
}

.content { padding: 30px 4% 45px; }

.hero {
    position: relative;
    overflow: hidden;
    padding: 32px;
    margin-bottom: 25px;
    border: 1px solid rgba(15,101,189,0.200);
    border-radius: 25px;
    background:
        radial-gradient(circle at 85% 20%,rgba(62,216,239,.15),transparent 25%),
        linear-gradient(120deg,#f5f9fd,#ffffff 62%,#f5f9fd);
    box-shadow: var(--shadow);
}

.eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;
    color: #11659c;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 12px rgba(66,214,155,.7);
}

.hero h2 {
    font-family: 'Poppins',sans-serif;
    font-size: clamp(23px,3vw,32px);
    line-height: 1.4;
}

.hero p {
    margin-top: 10px;
    max-width: 850px;
    color: #5c748c;
    font-size: 13px;
    line-height: 1.8;
}

.section-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin: 28px 0 16px;
}

.section-heading h2,
.section-title {
    font-family: 'Poppins',sans-serif;
    font-size: 20px;
    font-weight: 700;
}

.section-heading p,
.section-subtitle {
    margin-top: 6px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
}

.section-tag {
    padding: 8px 11px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: #23598a;
    background: rgba(23,52,86,0.030);
    font-size: 10px;
    white-space: nowrap;
}

/* ================ STATISTIK ================ */

.stats {
    display: grid;
    grid-template-columns: repeat(5,minmax(0,1fr));
    gap: 16px;
    margin-bottom: 25px;
}

.stat-card {
    min-width: 0;
    padding: 21px;
    border: 1px solid var(--line);
    border-radius: var(--radius);
    background: linear-gradient(145deg,#ffffff,#eef3f9);
    box-shadow: var(--shadow);
    transition: .25s;
}

.stat-card:hover {
    transform: translateY(-4px);
    border-color: rgba(15,114,189,0.350);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.stat-title {
    color: #5c748c;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: .5px;
}

.stat-icon {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border: 1px solid rgba(15,105,189,0.170);
    border-radius: 13px;
    color: #115f9c;
    background: rgba(37,140,255,.12);
    font-size: 19px;
}

.stat-value {
    margin-top: 7px;
    color: #16293f;
    font-family: 'Poppins',sans-serif;
    font-size: clamp(19px,1.8vw,26px);
    font-weight: 700;
    overflow-wrap: anywhere;
}

.stat-small {
    margin-top: 8px;
    color: #5c748c;
    font-size: 11px;
    line-height: 1.6;
}

/* ================ PANEL DAN GRAFIK ================ */

.chart-grid {
    display: grid;
    grid-template-columns: repeat(2,minmax(0,1fr));
    gap: 20px;
    margin-bottom: 22px;
}

.chart-box,
.analysis-box,
.prediction-box {
    min-width: 0;
    padding: 25px;
    margin-bottom: 22px;
    border: 1px solid var(--line);
    border-radius: 23px;
    background: linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
    box-shadow: var(--shadow);
}

.chart-grid .chart-box { margin-bottom: 0; }
.chart-grid .chart-box:last-child { grid-column: 1 / -1; }

.chart-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 17px;
}

.chart-title h3 {
    font-family: 'Poppins',sans-serif;
    font-size: 16px;
    color: #16293f;
}

.chart-title p {
    margin-top: 5px;
    color: #5c748c;
    font-size: 11px;
    line-height: 1.7;
}

.chart-icon {
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    flex-shrink: 0;
    border: 1px solid rgba(15,127,189,0.130);
    border-radius: 13px;
    color: var(--cyan);
    background: rgba(45,165,235,.11);
    font-size: 20px;
}

.chart-icon-lokasi {
    cursor: pointer;
    font: inherit;
    transition: background .2s, border-color .2s;
}

.chart-icon-lokasi:hover {
    background: rgba(45,165,235,.22);
    border-color: rgba(15,127,189,0.450);
}

.chart-container {
    position: relative;
    width: 100%;
    height: 310px;
}

/* ================ MAWAR ANGIN ================ */

.windrose-wrap {
    position: relative;
    width: 100%;
    height: 440px;
}

.windrose-wrap canvas {
    width: 100%;
    height: 100%;
    display: block;
}

.windrose-legend {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px;
    margin-top: 6px;
}

.windrose-chip {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 4px 12px;
    border-radius: 999px;
    border: 1px solid rgba(35,70,110,0.220);
    background: rgba(255,255,255,0.94);
    color: #16293f;
    font-size: 11.5px;
    font-weight: 600;
}

.windrose-chip i {
    width: 11px;
    height: 11px;
    border-radius: 3px;
    display: inline-block;
}

.windrose-tip {
    position: absolute;
    pointer-events: none;
    background: #eef3f9;
    border: 1px solid rgba(18,122,186,0.350);
    border-radius: 10px;
    padding: 9px 12px;
    color: #16293f;
    font-size: 11.5px;
    line-height: 1.55;
    white-space: nowrap;
    z-index: 3;
}

.windrose-baca {
    margin-top: 14px;
    padding: 14px 16px;
    border: 1px solid rgba(35,70,110,0.180);
    border-radius: 14px;
    background: rgba(240,246,252,0.75);
}

.windrose-baca h4 {
    margin: 0 0 6px;
    color: #16293f;
    font-size: 13px;
    font-weight: 700;
}

.windrose-baca p {
    margin: 0 0 10px;
    color: #33506e;
    font-size: 11.8px;
    line-height: 1.65;
}

.windrose-arah {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(155px, 1fr));
    gap: 6px 10px;
    margin: 0 0 12px;
    padding: 0;
    list-style: none;
}

.windrose-arah li {
    display: flex;
    align-items: baseline;
    gap: 6px;
    padding: 4px 8px;
    border-radius: 8px;
    background: rgba(255,255,255,0.85);
    font-size: 11.3px;
    color: #33506e;
}

.windrose-arah b {
    color: #16293f;
    font-size: 11.8px;
    min-width: 28px;
}

.windrose-arah em {
    margin-left: auto;
    color: #127ab8;
    font-style: normal;
    font-weight: 600;
    white-space: nowrap;
}

.windrose-sumber {
    margin: 0;
    padding: 10px 12px;
    border-radius: 10px;
    background: rgba(240,247,253,0.9);
    border-left: 3px solid #127ab8;
}

.windrose-sumber h5 {
    margin: 0 0 6px;
    color: #16293f;
    font-size: 11.5px;
    font-weight: 700;
    letter-spacing: .01em;
}

.windrose-sumber ol {
    margin: 0;
    padding-left: 18px;
    color: #33506e;
    font-size: 11.2px;
    line-height: 1.6;
}

.windrose-sumber li { margin-bottom: 4px; }
.windrose-sumber li:last-child { margin-bottom: 0; }

.windrose-sumber a {
    color: #0d6ba8;
    font-weight: 600;
    text-decoration: none;
    word-break: break-all;
}

.windrose-sumber a:hover { text-decoration: underline; }

.windrose-sumber small {
    display: block;
    margin-top: 8px;
    color: #5a7692;
    font-size: 10.4px;
    line-height: 1.5;
}

.windrose-periode {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;
    margin: 0 0 14px;
    padding: 8px 12px;
    border: 1px solid #d6e6f6;
    border-radius: 10px;
    background: #f2f7fd;
    color: #24486b;
    font-size: 12px;
    line-height: 1.4;
}

.windrose-periode b { color: #0f61bd; }

.windrose-hitung {
    margin: 0 0 6px;
    padding-left: 20px;
    color: #33506e;
    font-size: 11.6px;
    line-height: 1.6;
}

.windrose-hitung li { margin-bottom: 4px; }
.windrose-hitung code { color: #0d6ba8; }

.windrose-grid {
    display: grid;
    grid-template-columns: minmax(0,1.15fr) minmax(280px,0.85fr);
    gap: 22px;
    align-items: start;
}

.windrose-jelas {
    padding: 14px 16px;
    border: 1px solid #d6e6f6;
    border-radius: 14px;
    background: #f7fbff;
    color: #33506e;
    font-size: 12px;
    line-height: 1.65;
}

.windrose-jelas h4 {
    margin: 0 0 6px;
    color: #16293f;
    font-size: 13.5px;
}

.windrose-jelas h5 {
    margin: 14px 0 7px;
    color: #16293f;
    font-size: 11px;
    letter-spacing: .05em;
    text-transform: uppercase;
}

.windrose-jelas p { margin: 0 0 9px; }
.windrose-jelas b { color: #0f61bd; }

.windrose-jelas-waktu {
    color: #5a7692;
    font-size: 11px;
}

.windrose-jelas-hari {
    padding: 9px 11px;
    border-left: 3px solid #127ab8;
    border-radius: 0 10px 10px 0;
    background: #eef6fd;
}

.windrose-harian {
    display: grid;
    gap: 6px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.windrose-harian li {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2px 10px;
    padding: 8px 10px;
    border: 1px solid #e2ecf7;
    border-radius: 10px;
    background: #ffffff;
}

.windrose-harian li.aktif {
    border-color: #9fcbec;
    background: #f2f9ff;
}

.windrose-harian b { font-size: 11.6px; }

.windrose-harian span {
    color: #24486b;
    font-weight: 600;
    text-align: right;
}

.windrose-harian em {
    grid-column: 1 / -1;
    color: #5a7692;
    font-size: 10.6px;
    font-style: normal;
}

.windrose-jelas-catatan {
    margin: 10px 0 0;
    color: #5a7692;
    font-size: 10.6px;
    line-height: 1.5;
}

/* ================ ANALISIS TREN ================ */

.section-title { color: #16293f; }
.section-subtitle { margin-bottom: 22px; }

.analysis-grid {
    display: grid;
    grid-template-columns: repeat(4,minmax(0,1fr));
    gap: 15px;
}

.analysis-card {
    min-width: 0;
    padding: 20px;
    border: 1px solid rgba(89,170,222,.11);
    border-radius: 17px;
    background: rgba(255,255,255,0.94);
}

.analysis-card h4 {
    margin-bottom: 10px;
    color: #5c748c;
    font-size: 10px;
    letter-spacing: .5px;
}

.analysis-value {
    color: #16293f;
    font-family: 'Poppins',sans-serif;
    font-size: clamp(18px,2vw,24px);
    font-weight: 800;
    overflow-wrap: anywhere;
}

/* ================ INDIKASI CUACA ================ */

.prediction-grid {
    display: grid;
    grid-template-columns: 1.2fr 1fr 1fr;
    gap: 16px;
}

.prediction-main,
.prediction-item {
    min-width: 0;
    padding: 23px;
    border: 1px solid rgba(71,179,242,.15);
    border-radius: 19px;
    background: rgba(255,255,255,0.94);
}

.prediction-main {
    background:
        radial-gradient(circle at 100% 0%,rgba(32,167,255,.12),transparent 40%),
        linear-gradient(135deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
}

.prediction-main .big-icon {
    margin-bottom: 9px;
    font-size: 38px;
}

.prediction-main h3 {
    margin-bottom: 8px;
    font-family: 'Poppins',sans-serif;
    font-size: 19px;
}

.prediction-main p {
    color: #5c748c;
    font-size: 12px;
    line-height: 1.8;
}

.prediction-item h4 {
    margin-bottom: 12px;
    color: #5c748c;
    font-size: 10px;
    letter-spacing: .5px;
}

.prediction-item strong {
    color: #16293f;
    font-family: 'Poppins',sans-serif;
    font-size: 17px;
    overflow-wrap: anywhere;
}

.prediction-catatan {
    margin: 14px 0 0;
    color: #5a7692;
    font-size: 10.6px;
    line-height: 1.6;
}

/* ================ KOSONG ================ */

.empty {
    padding: 30px 20px;
    border: 1px dashed #ced8e3;
    border-radius: 15px;
    color: #5c748c;
    background: rgba(23,52,86,0.030);
    text-align: center;
    font-size: 12px;
    line-height: 1.9;
}

/* ================ FOOTER ================ */

.footer {
    display: flex;
    justify-content: space-between;
    gap: 15px;
    padding: 22px 0;
    border-top: 1px solid var(--line);
    color: #5c748c;
    font-size: 11px;
    line-height: 1.8;
}

/* ================ RESPONSIVE ================ */

@media (max-width:1250px) {
    .content { padding-left: 2.5%; padding-right: 2.5%; }
    .stats { grid-template-columns: repeat(3,minmax(0,1fr)); }
}

@media (max-width:1000px) {
    .chart-grid { grid-template-columns: 1fr; }
    .prediction-grid { grid-template-columns: 1fr; }
    .analysis-grid { grid-template-columns: repeat(2,minmax(0,1fr)); }
    .windrose-grid { grid-template-columns: 1fr; }
}

@media (max-width:650px) {
    .content { padding: 18px 4% 30px; }
    .hero { padding: 23px 20px; border-radius: 20px; }
    .stats { grid-template-columns: repeat(2,minmax(0,1fr)); gap: 10px; }
    .stat-card { padding: 15px; border-radius: 16px; }
    .stat-value { font-size: 20px; }
    .stat-title { font-size: 9px; line-height: 1.6; }
    .chart-box, .analysis-box, .prediction-box { padding: 17px; border-radius: 19px; }
    .chart-container { height: 270px; }
    .windrose-wrap { height: 330px; }
    .chart-title h3 { font-size: 14px; }
    .footer { flex-direction: column; }
    .section-tag { display: none; }
}

@media (max-width:380px) {
    .stats, .analysis-grid { grid-template-columns: 1fr; }
}

@media print {
    body { color: #172033 !important; background: white !important; }
    .content { padding: 0; }
    .hero, .stat-card, .chart-box, .analysis-box,
    .prediction-box {
        color: #172033 !important;
        background: white !important;
        box-shadow: none !important;
        break-inside: avoid;
    }
}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=3">
</head>

<body>

<!-- ================ NAVBAR DASHBOARD ================ -->

<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- ================ KONTEN GRAFIK ================ -->

<main class="main">
<section class="content">

    <div class="hero">
        <div class="eyebrow">
            <span class="live-dot"></span>
            Pusat Analisis Cuaca
        </div>
        <h2>Grafik &amp; Analisis Cuaca</h2>
        <p>
            BMKG Kemayoran · Visualisasi suhu, kelembapan, curah hujan, dan mawar angin
            berdasarkan prakiraan resmi BMKG interval <?= $intervalWindrose ?> jam untuk
            <?= htmlspecialchars(bmkg_ringkas_lokasi($lokasiAktif), ENT_QUOTES, 'UTF-8') ?>.
            Periode data: <?= $periodeWindrose ?>.
            Sumber: <?= htmlspecialchars(bmkg_label_sumber($sumberCuaca), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="section-heading">
        <div>
            <h2>Ringkasan Statistik</h2>
            <p>
                Rata-rata = jumlah nilai <?= $totalData ?> slot prakiraan &divide;
                <?= $totalData ?> (aritmetik sederhana, tiap slot berbobot sama).
                Tidak ada rata-rata harian dan tidak ada penimbang; maksimum/minimum
                diambil dari nilai slot tunggal, bukan dari rata-rata.
            </p>
        </div>
        <span class="section-tag">
            <i class="fa-solid fa-database"></i>
            <?= number_format($totalData) ?> DATA TERCATAT
        </span>
    </div>

    <div class="stats">
        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-title">TOTAL DATA</div>
                <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
            </div>
            <div class="stat-value"><?= number_format($totalData) ?></div>
            <div class="stat-small">Data prakiraan cuaca BMKG</div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-title">RATA-RATA SUHU</div>
                <div class="stat-icon"><i class="fa-solid fa-temperature-half"></i></div>
            </div>
            <div class="stat-value"><?= number_format($rataSuhu, 1) ?>°C</div>
            <div class="stat-small">Temperatur rata-rata</div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-title">KELEMBAPAN</div>
                <div class="stat-icon"><i class="fa-solid fa-droplet"></i></div>
            </div>
            <div class="stat-value"><?= number_format($rataKelembapan, 1) ?>%</div>
            <div class="stat-small">Rata-rata kelembapan udara</div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-title">CURAH HUJAN</div>
                <div class="stat-icon"><i class="fa-solid fa-cloud-rain"></i></div>
            </div>
            <div class="stat-value"><?= number_format($totalHujan, 1) ?></div>
            <div class="stat-small">Total akumulasi dalam mm</div>
        </article>

        <article class="stat-card">
            <div class="stat-top">
                <div class="stat-title">ANGIN</div>
                <div class="stat-icon"><i class="fa-solid fa-wind"></i></div>
            </div>
            <div class="stat-value"><?= number_format($rataAngin, 1) ?></div>
            <div class="stat-small">Rata-rata km/jam</div>
        </article>
    </div>

    <!-- GRAFIK UTAMA -->

    <div class="section-heading">
        <div>
            <h2>Visualisasi Data Cuaca</h2>
            <p>
                Satu titik grafik = satu slot prakiraan berjarak
                <?= $intervalWindrose ?> jam. Periode: <?= $periodeWindrose ?>.
                Jam pada sumbu memakai <?= $labelZona ?>, diambil dari kolom
                <code>local_datetime</code>, bukan <code>utc_datetime</code>.
                <?php $tanggalPertama = array_key_first($jumlahPerHari); ?>
                <?php if ($tanggalPertama !== null && $intervalWindrose): ?>
                    Total <?= count($slotWindrose) ?> slot &times; <?= $intervalWindrose ?> jam
                    = <?= $totalJamWindrose ?> jam
                    (&plusmn; <?= number_format($totalJamWindrose / 24, 1, ',', '.') ?> hari),
                    bukan <?= number_format(ceil($totalJamWindrose / 24), 0, ',', '.') ?> &times; 24 jam
                    penuh: tanggal <?= tanggalGrafik($tanggalPertama, '') ?> baru berisi
                    <?= $jumlahPerHari[$tanggalPertama]['slot'] ?> slot karena prakiraan mulai
                    berlaku pukul <?= $jumlahPerHari[$tanggalPertama]['slot'] > 0 ? date('H:i', $slotWindrose[0]) : '-' ?>,
                    bukan sejak tengah malam.
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div class="chart-grid">

        <article class="chart-box">
            <div class="chart-header">
                <div class="chart-title">
                    <h3>Perubahan Suhu</h3>
                    <p>Perubahan temperatur tiap slot prakiraan <?= $intervalWindrose ?> jam (&deg;C).</p>
                </div>
                <div class="chart-icon"><i class="fa-solid fa-temperature-half"></i></div>
            </div>
            <div class="chart-container"><canvas id="suhuChart"></canvas></div>
        </article>

        <article class="chart-box">
            <div class="chart-header">
                <div class="chart-title">
                    <h3>Kelembapan Udara</h3>
                    <p>Persentase kelembapan pada tiap slot prakiraan <?= $intervalWindrose ?> jam.</p>
                </div>
                <div class="chart-icon"><i class="fa-solid fa-droplet"></i></div>
            </div>
            <div class="chart-container"><canvas id="kelembapanChart"></canvas></div>
        </article>

        <article class="chart-box">
            <div class="chart-header">
                <div class="chart-title">
                    <h3>Curah Hujan</h3>
                    <p>
                        Curah hujan tiap slot prakiraan <?= $intervalWindrose ?> jam.
                        Nilai <code>tp</code> = akumulasi selama <?= $intervalWindrose ?> jam,
                        bukan per jam, satuannya mm.
                        <?= $slotSehari ?> slot = 1 hari (24 jam).
                    </p>
                </div>
                <div class="chart-icon"><i class="fa-solid fa-cloud-rain"></i></div>
            </div>
            <div class="chart-container"><canvas id="hujanChart"></canvas></div>
        </article>

    </div>

    <!-- ANALISIS TREN -->

    <section class="analysis-box">
        <h2 class="section-title">Analisis Tren Cuaca</h2>
        <p class="section-subtitle">
            Ringkasan nilai maksimum dan minimum dari <?= count($data) ?> slot
            prakiraan berinterval <?= $intervalWindrose ?> jam.
        </p>

        <div class="analysis-grid">
            <article class="analysis-card">
                <h4>SUHU TERTINGGI</h4>
                <div class="analysis-value"><?= number_format($maxSuhu ?? 0, 1) ?>°C</div>
            </article>

            <article class="analysis-card">
                <h4>SUHU TERENDAH</h4>
                <div class="analysis-value"><?= number_format($minSuhu ?? 0, 1) ?>°C</div>
            </article>

            <article class="analysis-card">
                <h4>ANGIN TERKUAT</h4>
                <div class="analysis-value"><?= number_format($maxAngin ?? 0, 1) ?> km/jam</div>
            </article>

            <article class="analysis-card">
                <h4>HUJAN TERTINGGI</h4>
                <div class="analysis-value"><?= number_format($maxHujan ?? 0, 1) ?> mm</div>
            </article>
        </div>
    </section>

    <!-- GRAFIK GABUNGAN -->

    <section class="chart-box">
        <div class="chart-header">
            <div class="chart-title">
                <h3>Perbandingan Suhu &amp; Kelembapan</h3>
                <p>Perbandingan temperatur dan kelembapan udara antar slot prakiraan.</p>
            </div>
            <div class="chart-icon"><i class="fa-solid fa-chart-line"></i></div>
        </div>
        <div class="chart-container"><canvas id="gabunganChart"></canvas></div>
    </section>

    <!-- MAWAR ANGIN -->

    <section class="chart-box">
        <div class="chart-header">
            <div class="chart-title">
                <h3>Mawar Angin (Windrose)</h3>
                <p>Arah dan kecepatan angin dalam satu gambar. Arah yang tertulis adalah arah DATANGNYA angin, misalnya sektor S berarti angin bertiup dari selatan ke utara.</p>
            </div>
            <button type="button" class="chart-icon chart-icon-lokasi" data-deteksi-lokasi
                title="Gunakan lokasi saya" aria-label="Muat windrose untuk kota terdekat dari lokasi saya">
                <i class="fa-solid fa-location-crosshairs"></i>
            </button>
        </div>

        <?php if ($periodeWindrose !== ''): ?>
            <p class="windrose-periode">
                <i class="fa-regular fa-calendar-days"></i>
                Periode data: <b><?= $periodeWindrose ?></b>
                &middot; interval <b><?= $intervalWindrose ?> jam</b>
                &middot; <b><?= count($slotWindrose) ?> slot</b> prakiraan
            </p>
        <?php endif; ?>

        <?php if ($totalWindrose > 0): ?>
            <div class="windrose-grid">
                <div class="windrose-gambar">
                    <div class="windrose-wrap">
                        <canvas id="windroseChart"></canvas>
                        <div class="windrose-tip" id="windroseTip" hidden></div>
                    </div>
                    <div class="windrose-legend" id="windroseLegend"></div>
                </div>

                <aside class="windrose-jelas">
                    <h4>Penjelasan kondisi angin</h4>
                    <p class="windrose-jelas-waktu">
                        Tulisan ini dihitung ulang tiap halaman dibuka, jadi isinya
                        mengikuti data BMKG hari itu. Periode: <?= $periodeWindrose ?>.
                    </p>

                    <p>
                        <b>Angin terbanyak</b> datang dari
                        <b><?= $namaArahWindrose[$arahDominan] ?> (<?= $arahDominan ?>)</b>,
                        <?= number_format($peringkatArah[$arahDominan], 0, ',', '.') ?> dari
                        <?= $totalWindrose ?> slot
                        (<?= number_format(persenWindrose($peringkatArah[$arahDominan], $totalWindrose), 1, ',', '.') ?>%),
                        dengan rata-rata kecepatan
                        <?= number_format($rataArahWindrose[$arahDominan], 1, ',', '.') ?> km/jam.
                        <?php
                        $arahDua = array_slice(array_keys($peringkatArah), 1, 1);
                        $kodeDua = $arahDua ? (string)$arahDua[0] : '';
                        ?>
                        <?php if ($kodeDua !== '' && $peringkatArah[$kodeDua] > 0): ?>
                            <?php if ($peringkatArah[$kodeDua] === $peringkatArah[$arahDominan]): ?>
                                Frekuensi yang sama persis juga terjadi pada
                                <?= $namaArahWindrose[$kodeDua] ?> (<?= $kodeDua ?>).
                            <?php else: ?>
                                Posisi kedua <?= $namaArahWindrose[$kodeDua] ?> (<?= $kodeDua ?>)
                                sebanyak <?= number_format(persenWindrose($peringkatArah[$kodeDua], $totalWindrose), 1, ',', '.') ?>%.
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>

                    <p>
                        <b>Kecepatan rata-rata</b> sepanjang periode
                        <?= number_format($rataSemuaWindrose, 1, ',', '.') ?> km/jam.
                        Angin lemah (&lt; <?= $batasWindrose[1] ?> km/jam) terjadi pada
                        <?= number_format(persenWindrose($slotTenang, $totalWindrose), 1, ',', '.') ?>%
                        slot, sedangkan angin &ge; <?= $batasKencang ?> km/jam pada
                        <?= number_format(persenWindrose($slotKencang, $totalWindrose), 1, ',', '.') ?>%
                        slot.
                        <?php if ($maksPeriode['nilai'] !== null): ?>
                            Puncaknya <?= number_format($maksPeriode['nilai'], 1, ',', '.') ?> km/jam
                            dari arah <?= $namaArahWindrose[$maksPeriode['arah']] ?>
                            (<?= $maksPeriode['arah'] ?>) pada <?= $maksPeriode['waktu'] ?>.
                        <?php endif; ?>
                    </p>

                    <?php if (isset($jumlahPerHari[$hariIniWindrose])):
                        $hariIni = $jumlahPerHari[$hariIniWindrose]; ?>
                        <p class="windrose-jelas-hari">
                            <b>Hari ini (<?= tanggalGrafik($hariIniWindrose, '') ?>)</b>:
                            <?= $hariIni['slot'] ?> slot prakiraan, angin terbanyak dari
                            <?= $namaArahWindrose[$hariIni['arahDominan']] ?>
                            (<?= $hariIni['arahDominan'] ?>)
                            <?= number_format(persenWindrose($hariIni['slotDominan'], $hariIni['slot']), 1, ',', '.') ?>%,
                            rata-rata <?= number_format($hariIni['rata'], 1, ',', '.') ?> km/jam,
                            tertinggi <?= number_format($hariIni['maks'], 1, ',', '.') ?> km/jam
                            pada <?= $hariIni['maksWaktu'] ?>.
                        </p>
                    <?php endif; ?>

                    <h5>Rincian per tanggal</h5>
                    <ul class="windrose-harian">
                        <?php foreach ($jumlahPerHari as $tanggalHari => $isiHari): ?>
                            <li class="<?= $tanggalHari === $hariIniWindrose ? 'aktif' : '' ?>">
                                <b><?= tanggalGrafik($tanggalHari, '') ?><?= $tanggalHari === $hariIniWindrose ? ' &middot; hari ini' : '' ?></b>
                                <span>
                                    <?= $namaArahWindrose[$isiHari['arahDominan']] ?>
                                    (<?= $isiHari['arahDominan'] ?>)
                                    <?= number_format(persenWindrose($isiHari['slotDominan'], $isiHari['slot']), 1, ',', '.') ?>%
                                </span>
                                <em>
                                    <?= $isiHari['slot'] ?> slot &middot;
                                    rata <?= number_format($isiHari['rata'], 1, ',', '.') ?> &middot;
                                    maks <?= number_format($isiHari['maks'], 1, ',', '.') ?> km/jam
                                </em>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="windrose-jelas-catatan">
                        Persentase dihitung dari <?= $totalWindrose ?> slot prakiraan
                        berinterval <?= $intervalWindrose ?> jam, jadi angkanya adalah
                        frekuensi kemunculan arah, bukan lama waktu observasi alat.
                    </p>
                </aside>
            </div>

            <p class="panel-desc" style="margin-top:12px">
                Panjang tiap kelopak = persentase waktu angin datang dari arah tersebut.
                Datanya <?= count($slotWindrose) ?> slot prakiraan BMKG yang diambil apa
                adanya dari API dengan interval <?= $intervalWindrose ?> jam (bukan per jam,
                dan bukan rata-rata harian). Warna berbeda menunjukkan rentang kecepatan.
                Arah diambil dari kolom derajat pada respons API BMKG sehingga 16 sektor
                masing-masing selebar 22,5&deg;.
            </p>

            <div class="windrose-baca">
                <h4>Cara membaca mawar angin</h4>

                <p>
                    <b>1. Huruf di tepi lingkaran = arah DATANGNYA angin.</b>
                    Singkatannya mengikuti bahasa Inggris dan posisinya diukur dalam derajat
                    (0&deg; = Utara, makin besar searah jarum jam). Angka di bawah adalah
                    derajat tengah sektor; tiap sektor mencakup ±11,25&deg; dari angka itu.
                </p>

                <ul class="windrose-arah">
                    <?php foreach ($arahWindrose as $i => $kode): ?>
                        <li>
                            <b><?= $kode ?></b>
                            <span><?= $namaArahWindrose[$kode] ?></span>
                            <em><?= number_format($i * 22.5, 1, ',', '.') ?>&deg;</em>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p>
                    Contoh: kelopak <b>NNW</b> berarti angin datang dari Utara-Barat Laut
                    (326,25&deg; s.d. 348,75&deg;) dan bergerak menuju Selatan-Tenggara.
                </p>

                <p>
                    <b>2. Angka persen pada lingkaran</b> bukan nilai kecepatan, tapi
                    <i>frekuensi</i>: persentase dari <?= count($data) ?> slot
                    prakiraan (interval <?= $intervalWindrose ?> jam) yang arahnya masuk
                    sektor tersebut. Kelopak yang ujungnya berhenti
                    di lingkaran 15% berarti angin dari arah itu terjadi sekitar 15% dari
                    <?= $intervalWindrose ?> &times; <?= count($slotWindrose) ?> jam
                    (<?= number_format(count($slotWindrose) * $intervalWindrose / 24, 1, ',', '.') ?> hari)
                    periode ini. Lingkaran bersifat menumpuk, jadi warna di dalam satu kelopak
                    membagi persentase itu menurut kelas kecepatan (&lt; <?= $batasWindrose[1] ?>,
                    <?= $batasWindrose[1] ?>-<?= $batasWindrose[2] ?>, dan seterusnya km/jam).
                    Total seluruh kelopak = 100%.
                </p>

                <p>
                    <b>3. Cara menghitungnya</b> (semua langkah ada di file
                    <code>grafik.php</code>, bagian <code>MAWAR ANGIN (WINDROSE)</code>):
                </p>
                <ol class="windrose-hitung">
                    <li>
                        Ambil <?php echo count($slotWindrose); ?> slot dari
                        <code>bmkg_muat_cuaca()</code>. Tiap slot = satu waktu prakiraan
                        berisi <code>ws</code> (kecepatan, km/jam) dan <code>wd_deg</code>
                        (arah datang, derajat). Nilai per slot dipakai apa adanya, tanpa
                        dirata-ratakan lebih dulu.
                    </li>
                    <li>
                        Golongkan arah ke 16 sektor:
                        <code>indeks = floor(((wd_deg + 11,25) mod 360) / 22,5)</code>,
                        sehingga tiap sektor selebar 22,5&deg; berpusat di kelipatan 22,5&deg;.
                    </li>
                    <li>
                        Masukkan slot ke kelas kecepatan pada variabel
                        <code>$batasWindrose = [<?= implode(', ', $batasWindrose) ?>]</code>:
                        &lt;<?= $batasWindrose[1] ?>, <?= $batasWindrose[1] ?>-<?= $batasWindrose[2] ?>,
                        <?= $batasWindrose[2] ?>-<?= $batasWindrose[3] ?>,
                        <?= $batasWindrose[3] ?>-<?= $batasWindrose[4] ?>,
                        &ge;<?= $batasWindrose[4] ?> km/jam.
                    </li>
                    <li>
                        Hitung frekuensi: <code>jumlah[(sektor, kelas)] &divide; total slot &times; 100%</code>
                        dengan total slot <?= $totalWindrose ?>. Nilai inilah yang menjadi panjang kelopak.
                    </li>
                    <li>
                        Rata-rata kecepatan per sektor (muncul saat kursor diarahkan ke kelopak) =
                        <code>total ws sektor &divide; jumlah slot sektor</code>.
                    </li>
                </ol>

                <?php $adm4Windrose = $hasilCuaca['adm4'] ?? ($lokasiAktif['adm4'] ?? '31.71.03.1001'); ?>
                <div class="windrose-sumber">
                    <h5>Sumber penjelasan</h5>
                    <ol>
                        <li>
                            <b>Angka pada grafik</b> (arah <code>wd_deg</code>, kecepatan <code>ws</code> km/jam):
                            API resmi BMKG, prakiraan cuaca per kecamatan &mdash;
                            <a href="https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=<?= htmlspecialchars($adm4Windrose, ENT_QUOTES, 'UTF-8') ?>"
                               target="_blank" rel="noopener">api.bmkg.go.id/publik/prakiraan-cuaca?adm4=<?= htmlspecialchars($adm4Windrose, ENT_QUOTES, 'UTF-8') ?></a>
                            (kode wilayah <?= htmlspecialchars($adm4Windrose, ENT_QUOTES, 'UTF-8') ?> = <?= htmlspecialchars($lokasiAktif['kecamatan'] ?? 'Kemayoran', ENT_QUOTES, 'UTF-8') ?>).
                        </li>
                        <li>
                            <b>Kaidah "arah angin = arah DATANGNYA"</b>: WMO melalui katalog variabel OSCAR &mdash;
                            <a href="https://space.oscar.wmo.int/variables/view/wind_direction_near_surface"
                               target="_blank" rel="noopener">space.oscar.wmo.int &rsaquo; Wind direction (near surface)</a>,
                            dan NOAA/NWS National Water Model &mdash;
                            <a href="https://www.weather.gov/hfo/windbarbinfo" target="_blank" rel="noopener">weather.gov/hfo/windbarbinfo</a>.
                        </li>
                        <li>
                            <b>Pembagian 16 sektor &times; 22,5&deg; (= 360&deg; &divide; 16)</b> dan aturan batasannya:
                            WindRose User's Guide &mdash;
                            <a href="https://windrose.gr/WindRose_Users_Guide.pdf" target="_blank" rel="noopener">windrose.gr/WindRose_Users_Guide.pdf</a>,
                            serta standar sektor IEC 61400-12-1 &mdash;
                            <a href="https://help.emd.dk/knowledgebase/content/Guides/Quick_Guide_IEC_61400-12-1_Terrain_and_sector_check_4.0.pdf"
                               target="_blank" rel="noopener">ringkasan sektor IEC 61400-12-1 (PDF)</a>.
                        </li>
                        <li>
                            <b>Contoh pemakaian istilah yang sama oleh BMKG</b> ("angin bertiup dari arah ..."):
                            <a href="https://staklim-sumsel.bmkg.go.id/analisis-arah-dan-kecepatan-angin-bulan-april-2022/"
                               target="_blank" rel="noopener">Staklim Sumsel &rsaquo; Analisis Arah dan Kecepatan Angin</a>.
                        </li>
                    </ol>
                    <small>
                        Catatan: mawar angin ini memakai <b>prakiraan</b> BMKG interval
                        <?= $intervalWindrose ?> jam (<?= count($slotWindrose) ?> slot,
                        <?= $periodeWindrose ?>), bukan hasil observasi alat di stasiun.
                        Istilah kecepatan &lt; <?= $batasWindrose[1] ?>,
                        <?= $batasWindrose[1] ?>-<?= $batasWindrose[2] ?> km/jam, dst. adalah kelas yang dipilih sendiri
                        untuk mewarnai kelopak, bukan kelas baku resimen angin.
                    </small>
                </div>
            </div>
        <?php else: ?>
            <p class="panel-desc">
                Mawar angin belum bisa dibuat karena data arah angin BMKG belum terbaca.
            </p>
        <?php endif; ?>
    </section>

    <!-- INDIKASI CUACA -->

    <section class="prediction-box">
        <h2 class="section-title">Indikasi Kondisi Cuaca</h2>
        <p class="section-subtitle">
            Indikasi sederhana dari akumulasi slot prakiraan, bukan hasil alat observasi.
        </p>

        <div class="prediction-grid">
            <article class="prediction-main">
                <div class="big-icon"><?= $prediksiIcon ?></div>
                <h3><?= htmlspecialchars($prediksiJudul, ENT_QUOTES, 'UTF-8') ?></h3>
                <p>
                    <?= htmlspecialchars($indikasiJelas, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </article>

            <article class="prediction-item">
                <h4>KONDISI TERBARU</h4>
                <strong>
                    <?= iconCuaca($kondisiTerbaru) ?>
                    <?= htmlspecialchars((string)$kondisiTerbaru, ENT_QUOTES, 'UTF-8') ?>
                </strong>
            </article>

            <article class="prediction-item">
                <h4>ARAH ANGIN</h4>
                <strong><?= htmlspecialchars((string)$arahAnginTerbaru, ENT_QUOTES, 'UTF-8') ?></strong>
            </article>
        </div>

        <p class="prediction-catatan">
            Batas <?= $hujanSedang ?> mm dan <?= $hujanLebat ?> mm per hari adalah ambang
            yang kita pilih sendiri untuk penyajian grafik ini, bukan kutipan kelas resmi
            BMKG. Halaman peringatan dini curah hujan harian BMKG hanya memuat tingkat
            Waspada, Siaga, dan Awas tanpa mencantumkan rentang mm di halaman yang sama.
            Tanggal acuan <?= tanggalGrafik($tanggalAcuan, '') ?> baru
            <?= $hariPenuh ? 'mengisi 24 jam penuh' : 'terisi ' . $slotAcuan . ' dari ' . $slotSehari . ' slot' ?>
            yang dibutuhkan untuk satu hari.
        </p>
    </section>

    <footer class="footer">
        <div>
            <strong>BMKG KEMAYORAN</strong><br>
            Sistem Pemantauan Data Cuaca
        </div>
        <div>Grafik &amp; Analisis · Sumber: prakiraan resmi BMKG · <?= date('Y') ?></div>
    </footer>

</section>
</main>

<script>

/* ================ DATA DARI PHP ================ */

const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const suhuData = <?= json_encode($suhuData) ?>;
const kelembapanData = <?= json_encode($kelembapanData) ?>;
const hujanData = <?= json_encode($hujanData) ?>;
const windrose = <?= json_encode($windrose, JSON_UNESCAPED_UNICODE) ?>;

/* ================ KONFIGURASI CHART ================ */

const dateTickSettings = {
    autoSkip: true,
    maxTicksLimit: 6,
    maxRotation: 0,
    minRotation: 0,
    color: '#5c748c',
    padding: 8,
    font: {
        size: 10,
        family: 'Inter'
    }
};

const tooltipSettings = {
    backgroundColor: '#eef3f9',
    titleColor: '#ffffff',
    bodyColor: '#16293f',
    borderColor: 'rgba(86,181,239,0.25)',
    borderWidth: 1,
    padding: 12,
    cornerRadius: 10,
    displayColors: true
};

const legendSettings = {
    display: true,
    labels: {
        usePointStyle: true,
        padding: 18,
        color: '#5c748c',
        font: {
            family: 'Inter',
            size: 11
        }
    }
};

const gridX = { color: 'rgba(115,153,183,0.08)' };
const gridY = { color: 'rgba(115,153,183,0.10)' };

function buatGrafik(id, jenis, datasets, opsiTambahan = {}) {
    const canvas = document.getElementById(id);
    if (!canvas || typeof Chart === 'undefined') return;

    new Chart(canvas, {
        type: jenis,
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                intersect: false,
                mode: 'index'
            },
            plugins: {
                legend: legendSettings,
                tooltip: {
                    ...tooltipSettings,
                    callbacks: {
                        title: function (items) {
                            if (!items.length) return '';
                            return labels[items[0].dataIndex] || '';
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: gridX,
                    ticks: dateTickSettings
                },
                y: {
                    beginAtZero: false,
                    grid: gridY,
                    ticks: {
                        color: '#5c748c',
                        font: { size: 10, family: 'Inter' }
                    }
                }
            },
            ...opsiTambahan
        }
    });
}

/* ================ GRAFIK SUHU ================ */

buatGrafik('suhuChart', 'line', [{
    label: 'Suhu (°C)',
    data: suhuData,
    borderColor: '#d2452f',
    backgroundColor: 'rgba(255,128,104,0.10)',
    borderWidth: 3,
    tension: 0.4,
    fill: true,
    pointRadius: 3,
    pointHoverRadius: 7,
    pointBackgroundColor: '#d2452f',
    pointBorderColor: '#eef3f9',
    pointBorderWidth: 2,
    spanGaps: true
}]);

/* ================ GRAFIK KELEMBAPAN ================ */

buatGrafik('kelembapanChart', 'line', [{
    label: 'Kelembapan (%)',
    data: kelembapanData,
    borderColor: '#1573b8',
    backgroundColor: 'rgba(78,181,240,0.10)',
    borderWidth: 3,
    tension: 0.4,
    fill: true,
    pointRadius: 3,
    pointHoverRadius: 7,
    pointBackgroundColor: '#1573b8',
    pointBorderColor: '#eef3f9',
    pointBorderWidth: 2,
    spanGaps: true
}], {
    scales: {
        x: { grid: gridX, ticks: dateTickSettings },
        y: {
            beginAtZero: true,
            max: 100,
            grid: gridY,
            ticks: {
                color: '#5c748c',
                font: { size: 10, family: 'Inter' },
                callback: function (value) { return value + '%'; }
            }
        }
    }
});

/* ================ GRAFIK CURAH HUJAN ================ */

buatGrafik('hujanChart', 'bar', [{
    label: 'Curah Hujan (mm)',
    data: hujanData,
    backgroundColor: 'rgba(55,166,232,0.72)',
    borderColor: '#1573b8',
    borderWidth: 1,
    borderRadius: 7,
    borderSkipped: false
}], {
    scales: {
        x: { grid: gridX, ticks: dateTickSettings },
        y: {
            beginAtZero: true,
            grid: gridY,
            ticks: {
                color: '#5c748c',
                font: { size: 10, family: 'Inter' }
            }
        }
    }
});

/* ================ MAWAR ANGIN ================ */

(function () {

    const canvas = document.getElementById('windroseChart');

    if (!canvas || !windrose || !windrose.total) {
        return;
    }

    const isi = [
        'rgba(95,174,255,0.55)',
        'rgba(76,217,160,0.55)',
        'rgba(255,204,77,0.55)',
        'rgba(255,138,92,0.55)',
        'rgba(155,122,255,0.55)'
    ];

    const garis = [
        '#1573b8',
        '#129267',
        '#c99a12',
        '#d2603a',
        '#6a48d6'
    ];

    /* Bingkai tiap sektor digambar sendiri (bukan radar Chart.js) supaya
     * bentuknya benar-benar mawar angin: 16 irisan dengan kelas kecepatan
     * yang ditumpuk dari pusat ke luar. */

    const RAD = Math.PI / 180;
    const lebarSudut = 22.5 * RAD;

    function persenPerSektor() {
        return windrose.bin.map(sektor =>
            sektor.map(nilai => nilai / windrose.total * 100));
    }

    function skalaRings(maks) {
        const langkah = maks <= 6 ? 2 : (maks <= 12 ? 3 : (maks <= 25 ? 5 : 10));
        return { langkah: langkah, puncak: Math.max(langkah, Math.ceil(maks / langkah) * langkah) };
    }

    function gambar() {
        const dpr = window.devicePixelRatio || 1;
        const lebar = canvas.clientWidth;
        const tinggi = canvas.clientHeight;

        if (!lebar || !tinggi) return;

        canvas.width = Math.round(lebar * dpr);
        canvas.height = Math.round(tinggi * dpr);

        const ctx = canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, lebar, tinggi);

        const cx = lebar / 2;
        const cy = tinggi / 2;
        const radius = Math.min(lebar, tinggi) / 2 - 46;

        const persen = persenPerSektor();
        const totalSektor = persen.map(sektor => sektor.reduce((a, b) => a + b, 0));
        const maks = Math.max.apply(null, totalSektor.concat([1]));
        const rings = skalaRings(maks);
        const skala = radius / rings.puncak;

        ctx.font = '11px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        for (let nilai = rings.langkah; nilai <= rings.puncak; nilai += rings.langkah) {
            ctx.beginPath();
            ctx.arc(cx, cy, nilai * skala, 0, Math.PI * 2);
            ctx.strokeStyle = 'rgba(115,153,183,0.20)';
            ctx.lineWidth = 1;
            ctx.stroke();
        }

        for (let i = 0; i < 16; i++) {
            const sudut = (i * 22.5 - 90 - 11.25) * RAD;
            ctx.beginPath();
            ctx.moveTo(cx, cy);
            ctx.lineTo(cx + Math.cos(sudut) * radius, cy + Math.sin(sudut) * radius);
            ctx.strokeStyle = 'rgba(115,153,183,0.10)';
            ctx.stroke();
        }

        persen.forEach((sektor, i) => {
            let tumpuk = 0;
            const sudutAwal = (i * 22.5 - 90 - 11.25) * RAD;

            sektor.forEach((nilai, j) => {
                if (nilai <= 0) return;

                const r1 = tumpuk * skala;
                const r2 = (tumpuk + nilai) * skala;

                ctx.beginPath();
                ctx.arc(cx, cy, r2, sudutAwal, sudutAwal + lebarSudut);
                ctx.arc(cx, cy, r1, sudutAwal + lebarSudut, sudutAwal, true);
                ctx.closePath();
                ctx.fillStyle = isi[j % isi.length];
                ctx.fill();
                ctx.strokeStyle = garis[j % garis.length];
                ctx.lineWidth = 1;
                ctx.stroke();

                tumpuk += nilai;
            });
        });

        ctx.font = '600 11px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.lineJoin = 'round';

        for (let nilai = rings.langkah; nilai <= rings.puncak; nilai += rings.langkah) {
            ctx.lineWidth = 4;
            ctx.strokeStyle = 'rgba(250,253,255,0.95)';
            ctx.strokeText(nilai + '%', cx, cy - nilai * skala - 8);
            ctx.fillStyle = '#1d3a58';
            ctx.fillText(nilai + '%', cx, cy - nilai * skala - 8);
        }

        ctx.font = '600 12px Inter, sans-serif';
        ctx.fillStyle = '#16293f';

        windrose.arah.forEach((arah, i) => {
            const sudut = (i * 22.5 - 90) * RAD;
            const x = cx + Math.cos(sudut) * (radius + 12);
            const y = cy + Math.sin(sudut) * (radius + 12);

            ctx.textAlign = Math.abs(Math.cos(sudut)) < 0.35
                ? 'center'
                : (Math.cos(sudut) > 0 ? 'left' : 'right');
            ctx.textBaseline = Math.abs(Math.sin(sudut)) < 0.35
                ? 'middle'
                : (Math.sin(sudut) > 0 ? 'top' : 'bottom');

            ctx.fillText(arah, x, y);
        });
    }

    function legenda() {
        const wadah = document.getElementById('windroseLegend');
        if (!wadah) return;

        wadah.innerHTML = '';

        windrose.labelBin.forEach((label, i) => {
            const chip = document.createElement('span');
            chip.className = 'windrose-chip';

            const titik = document.createElement('i');
            titik.style.background = garis[i % garis.length];
            titik.style.borderColor = garis[i % garis.length];

            chip.appendChild(titik);
            chip.appendChild(document.createTextNode(label));
            wadah.appendChild(chip);
        });
    }

    /* Tooltip: arahkan kursor ke salah satu irisan untuk melihat
     * jumlah slot, persentase, dan rata-rata kecepatannya. */
    function pantau(event) {
        const tip = document.getElementById('windroseTip');
        if (!tip) return;

        const kotak = canvas.getBoundingClientRect();
        const x = event.clientX - kotak.left - kotak.width / 2;
        const y = event.clientY - kotak.top - kotak.height / 2;
        const jarak = Math.sqrt(x * x + y * y);
        const radius = Math.min(kotak.width, kotak.height) / 2 - 46;

        if (jarak > radius) {
            tip.hidden = true;
            return;
        }

        let derajat = Math.atan2(x, -y) * 180 / Math.PI;
        if (derajat < 0) derajat += 360;

        const indeks = Math.floor(((derajat + 11.25) % 360) / 22.5) % 16;
        const persen = persenPerSektor()[indeks];
        const total = persen.reduce((a, b) => a + b, 0);

        if (total <= 0) {
            tip.hidden = true;
            return;
        }

        const slot = windrose.bin[indeks].reduce((a, b) => a + b, 0);

        tip.hidden = false;
        tip.style.left = (event.clientX - kotak.left + 14) + 'px';
        tip.style.top = (event.clientY - kotak.top + 14) + 'px';
        tip.innerHTML = '<strong>Angin dari ' + windrose.arah[indeks] + '</strong><br>' +
            slot + ' slot (' + (Math.round(total * 10) / 10) + '%)<br>' +
            'rata-rata ' + windrose.rata[indeks] + ' km/jam';
    }

    gambar();
    legenda();

    canvas.addEventListener('mousemove', pantau);
    canvas.addEventListener('mouseleave', function () {
        const tip = document.getElementById('windroseTip');
        if (tip) tip.hidden = true;
    });

    window.addEventListener('resize', gambar);

})();

/* ================ GRAFIK GABUNGAN ================ */

buatGrafik('gabunganChart', 'line', [
    {
        label: 'Suhu (°C)',
        data: suhuData,
        borderColor: '#d2452f',
        backgroundColor: 'rgba(255,128,104,0.08)',
        borderWidth: 3,
        tension: 0.4,
        fill: false,
        pointRadius: 3,
        pointBackgroundColor: '#d2452f',
        pointBorderColor: '#eef3f9',
        pointBorderWidth: 2,
        spanGaps: true
    },
    {
        label: 'Kelembapan (%)',
        data: kelembapanData,
        borderColor: '#1573b8',
        backgroundColor: 'rgba(78,181,240,0.08)',
        borderWidth: 3,
        tension: 0.4,
        fill: false,
        pointRadius: 3,
        pointBackgroundColor: '#1573b8',
        pointBorderColor: '#eef3f9',
        pointBorderWidth: 2,
        spanGaps: true
    }
]);
</script>

<script src="assets/lokasi-otomatis.js" data-auto="0"></script>

</body>
</html>
