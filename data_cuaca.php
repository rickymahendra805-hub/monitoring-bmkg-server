<?php
/* =========================================================
   DATA CUACA BMKG KEMAYORAN
   Tema mengikuti dashboard.php
   Penambahan: Menu Pemantauan Kota
   Data dan fitur lama tetap dipertahankan
========================================================= */

require_once __DIR__ . '/includes/bmkg_data.php';

$hasilCuaca = bmkg_muat_cuaca();
$data = $hasilCuaca['records'];
$lokasiAktif = $hasilCuaca['lokasi'];
$sumberCuaca = $hasilCuaca['sumber'];
$waktuAmbilCuaca = $hasilCuaca['diambil'];
$galatCuaca = $hasilCuaca['error'];

if (!function_exists('h')) {
    function h($nilai) {
        if (is_array($nilai) || is_object($nilai)) {
            $nilai = json_encode($nilai, JSON_UNESCAPED_UNICODE);
        }
        return htmlspecialchars((string)($nilai ?? '-'), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('angkaCuaca')) {
    function angkaCuaca($nilai, $desimal = 1) {
        if ($nilai === null || $nilai === '' || !is_numeric($nilai)) {
            return '-';
        }
        return number_format((float)$nilai, $desimal, ',', '.');
    }
}

if (!function_exists('nilaiAngka')) {
    function nilaiAngka($baris, $kolom) {
        return isset($baris[$kolom]) && is_numeric($baris[$kolom])
            ? (float)$baris[$kolom]
            : null;
    }
}

if (!function_exists('rataCuaca')) {
    function rataCuaca($data, $kolom) {
        $angka = [];
        foreach ($data as $baris) {
            if (!is_array($baris)) continue;
            $n = nilaiAngka($baris, $kolom);
            if ($n !== null) $angka[] = $n;
        }
        return count($angka) ? array_sum($angka) / count($angka) : null;
    }
}

if (!function_exists('totalCuaca')) {
    function totalCuaca($data, $kolom) {
        $angka = [];
        foreach ($data as $baris) {
            if (!is_array($baris)) continue;
            $n = nilaiAngka($baris, $kolom);
            if ($n !== null) $angka[] = $n;
        }
        return count($angka) ? array_sum($angka) : null;
    }
}

if (!function_exists('ekstremCuaca')) {
    function ekstremCuaca($data, $kolom, $mode = 'max') {
        $angka = [];
        foreach ($data as $baris) {
            if (!is_array($baris)) continue;
            $n = nilaiAngka($baris, $kolom);
            if ($n !== null) $angka[] = $n;
        }
        if (!$angka) return null;
        return $mode === 'min' ? min($angka) : max($angka);
    }
}

if (!function_exists('ikonCuaca')) {
    function ikonCuaca($kondisi) {
        $k = strtolower((string)$kondisi);
        if (strpos($k, 'petir') !== false || strpos($k, 'badai') !== false) return '⛈️';
        if (strpos($k, 'hujan') !== false) return '🌧️';
        if (strpos($k, 'mendung') !== false || strpos($k, 'berawan') !== false) return '☁️';
        if (strpos($k, 'cerah') !== false) return '☀️';
        return '🌤️';
    }
}

/* ================= MENU AKTIF ================= */
$currentPage = basename($_SERVER['PHP_SELF']);

if (!function_exists('menuAktif')) {
    function menuAktif($halaman, $currentPage) {
        return $halaman === $currentPage ? 'active' : '';
    }
}

$halamanAtmosfer = ['inarcm.php', 'inawms.php', 'city.php', 'galeri-produk.php'];
$atmosferAktif = in_array($currentPage, $halamanAtmosfer, true);
$halamanProduk = ['prediksi.php', 'gempa.php'];
$produkAktif = in_array($currentPage, $halamanProduk, true);
$halamanLainnya = ['info_bmkg.php', 'laporan.php', 'logout.php'];
$lainnyaAktif = in_array($currentPage, $halamanLainnya, true);

/* ================= PENCARIAN ================= */
$search = trim((string)($_GET['search'] ?? ''));
$dataTampil = [];

foreach ($data as $baris) {
    if (!is_array($baris)) continue;

    if ($search !== '') {
        $cocok = false;
        foreach ($baris as $nilai) {
            if (is_array($nilai) || is_object($nilai)) {
                $nilai = json_encode($nilai, JSON_UNESCAPED_UNICODE);
            }
            if (stripos((string)$nilai, $search) !== false) {
                $cocok = true;
                break;
            }
        }
        if (!$cocok) continue;
    }

    $dataTampil[] = $baris;
}

$dataTampil = array_reverse($dataTampil);
$totalData = count($data);
$totalHasil = count($dataTampil);
$perHalaman = 15;
$totalHalaman = max(1, (int)ceil($totalHasil / $perHalaman));
$halaman = max(1, min((int)($_GET['page'] ?? 1), $totalHalaman));
$mulai = ($halaman - 1) * $perHalaman;
$dataHalaman = array_slice($dataTampil, $mulai, $perHalaman);

/* ================= STATISTIK ================= */
$rataSuhu = rataCuaca($data, 'suhu');
$rataKelembapan = rataCuaca($data, 'kelembapan');
$rataAngin = rataCuaca($data, 'kecepatan_angin');
$totalHujan = totalCuaca($data, 'curah_hujan');
$suhuMaks = ekstremCuaca($data, 'suhu', 'max');
$suhuMin = ekstremCuaca($data, 'suhu', 'min');
$dataTerbaru = count($data) ? end($data) : [];
if (!is_array($dataTerbaru)) $dataTerbaru = [];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Cuaca | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
:root{--bg:#eef3f9;--bg-soft:#eef3f9;--surface:#ffffff;--surface-hover:#f5f9fd;--line:rgba(35,70,110,0.208);--text:#16293f;--muted:#5c748c;--blue:#0f61bd;--cyan:#10a6bc;--green:#24a873;--radius:20px;--shadow:0 16px 40px rgba(16,42,67,0.048)}
*{margin:0;padding:0;box-sizing:border-box}html{scroll-behavior:smooth}body{min-height:100vh;font-family:'Inter',Arial,sans-serif;color:var(--text);background:radial-gradient(ellipse at 8% 0%,rgba(37,140,255,.13),transparent 32%),radial-gradient(ellipse at 95% 20%,rgba(62,216,239,.07),transparent 30%),var(--bg)}
a{color:inherit;text-decoration:none}button,input{font:inherit}button,a{-webkit-tap-highlight-color:transparent}
/* ================= KONTEN ================= */
.main{width:min(1500px,100%);margin:0 auto;padding:30px 4% 55px}.page-hero{position:relative;overflow:hidden;display:flex;justify-content:space-between;align-items:center;gap:20px;padding:34px;border:1px solid rgba(15,101,189,0.200);border-radius:25px;background:radial-gradient(circle at 85% 20%,rgba(62,216,239,.18),transparent 25%),linear-gradient(120deg,#f5f9fd,#ffffff 62%,#f5f9fd);box-shadow:var(--shadow)}.page-hero:after{content:"";position:absolute;width:250px;height:250px;right:-85px;bottom:-170px;border:1px solid rgba(15,124,189,0.220);border-radius:50%;box-shadow:0 0 0 30px rgba(15,124,189,0.035),0 0 0 65px rgba(15,124,189,0.025);pointer-events:none}.eyebrow{display:inline-flex;align-items:center;gap:8px;margin-bottom:13px;color:#11659c;font-size:11px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase}.live-dot{width:7px;height:7px;display:inline-block;flex-shrink:0;border-radius:50%;background:var(--green);box-shadow:0 0 12px rgba(66,214,155,.7)}.page-hero h1{font-family:'Poppins',sans-serif;font-size:clamp(23px,3vw,34px);line-height:1.35;letter-spacing:-.7px}.page-hero p{margin-top:10px;color:#5c748c;font-size:13px;line-height:1.8}.hero-chip{position:relative;z-index:1;display:flex;align-items:center;gap:10px;padding:13px 16px;border:1px solid rgba(23,52,86,0.055);border-radius:14px;color:#16293f;background:rgba(23,52,86,0.030);font-size:12px;white-space:nowrap}.hero-chip i{color:var(--cyan);font-size:18px}
/* ================= HEADING DAN STATISTIK ================= */
.section-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;margin:30px 0 16px}.section-heading h2{font-family:'Poppins',sans-serif;font-size:17px;font-weight:700}.section-heading p{margin-top:5px;color:var(--muted);font-size:12px}.section-tag{padding:8px 11px;border:1px solid var(--line);border-radius:10px;color:#23598a;background:rgba(23,52,86,0.030);font-size:10px;white-space:nowrap}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.stat-card{position:relative;overflow:hidden;min-width:0;padding:22px;border:1px solid var(--line);border-radius:var(--radius);background:linear-gradient(145deg,#ffffff,#eef3f9);box-shadow:var(--shadow);transition:transform .25s,border-color .25s}.stat-card:hover{transform:translateY(-4px);border-color:rgba(15,114,189,0.350)}.stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px}.stat-icon{width:46px;height:46px;display:grid;place-items:center;border:1px solid rgba(15,105,189,0.170);border-radius:14px;color:#115f9c;background:rgba(37,140,255,.12);font-size:19px}.stat-label{margin-top:20px;color:#5c748c;font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;line-height:1.6}.stat-value{margin-top:8px;color:#16293f;font-family:'Poppins',sans-serif;font-size:clamp(20px,2vw,28px);font-weight:700;overflow-wrap:anywhere}.stat-value span{color:#5c748c;font-family:'Inter',sans-serif;font-size:12px;font-weight:500}.stat-foot{margin-top:12px;color:#5c748c;font-size:11px;line-height:1.6}
/* ================= PANEL TABEL ================= */
.panel{margin-top:25px;border:1px solid var(--line);border-radius:23px;background:linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));box-shadow:var(--shadow);overflow:hidden}.panel-heading{display:flex;align-items:center;justify-content:space-between;gap:15px;flex-wrap:wrap;padding:22px 25px;border-bottom:1px solid var(--line)}.panel-title{display:flex;align-items:center;gap:12px}.panel-title-icon{width:43px;height:43px;display:grid;place-items:center;flex-shrink:0;border:1px solid rgba(62,216,239,.14);border-radius:13px;color:var(--cyan);background:rgba(62,216,239,.08);font-size:17px}.panel-title h2{font-family:'Poppins',sans-serif;font-size:16px}.panel-title p{margin-top:4px;color:var(--muted);font-size:11px;line-height:1.6}.status-pill{display:inline-flex;align-items:center;gap:7px;padding:9px 12px;border:1px solid rgba(66,214,155,.17);border-radius:30px;color:#1b935d;background:rgba(66,214,155,.07);font-size:10px;font-weight:700;white-space:nowrap}
.search-form{display:flex;gap:8px;width:min(100%,430px)}.search-input{min-width:0;width:100%;padding:12px 14px;border:1px solid #ced7e3;border-radius:11px;outline:none;color:var(--text);background:#eef3f9;font-size:12px}.search-input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(37,140,255,.1)}.search-input::placeholder{color:#5c748c}.btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:11px 14px;border:1px solid transparent;border-radius:11px;color:#16293f;background:var(--blue);font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;transition:.2s}.btn:hover{filter:brightness(1.12)}.btn-secondary{border-color:#ced8e3;background:#eaf2fa}
/* ================= TABEL DATA ================= */
.table-scroll{width:100%;overflow-x:auto}table{width:100%;min-width:1050px;border-collapse:collapse;text-align:left}thead{background:#f5f9fd}th{padding:15px;color:#22364e;font-size:10px;letter-spacing:.5px;text-transform:uppercase;white-space:nowrap;border-bottom:1px solid var(--line)}td{padding:15px;color:#16293f;font-size:12px;white-space:nowrap;border-bottom:1px solid rgba(35,70,110,0.144)}tbody tr{transition:background .2s}tbody tr:hover{background:rgba(39,140,255,.06)}tbody tr:last-child td{border-bottom:0}.row-number{color:#5c748c}.date-main{color:#16293f;font-weight:600}.weather-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border:1px solid rgba(37,140,255,.15);border-radius:9px;color:#16293f;background:rgba(37,140,255,.09)}.empty-state{padding:45px 18px;color:var(--muted);text-align:center;white-space:normal}.empty-state strong{display:block;margin-bottom:8px;color:var(--text);font-size:15px}.empty-state p{font-size:12px;line-height:1.8}
/* ================= PAGINATION ================= */
.table-footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:17px 25px;border-top:1px solid var(--line)}.result-info{color:var(--muted);font-size:11px;line-height:1.7}.pagination{display:flex;flex-wrap:wrap;gap:5px}.pagination a,.pagination span{min-width:34px;height:34px;padding:0 10px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #ced7e3;border-radius:9px;color:#22364e;background:#f5f9fd;font-size:11px;text-decoration:none;transition:.2s}.pagination a:hover,.pagination .current{border-color:var(--blue);color:#fff;background:#176fc5}
/* ================= RINGKASAN ================= */
.info-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;margin-top:25px}.info-card{min-width:0;padding:20px;border:1px solid var(--line);border-radius:17px;background:linear-gradient(145deg,#ffffff,#eef3f9);box-shadow:var(--shadow)}.info-card span{display:block;margin-bottom:11px;color:var(--muted);font-size:11px;line-height:1.7}.info-card strong{color:#16293f;font-family:'Poppins',sans-serif;font-size:14px;line-height:1.8;overflow-wrap:anywhere}.footer{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:15px;margin-top:30px;padding-top:20px;border-top:1px solid var(--line);color:#5c748c;font-size:11px;line-height:1.8}.footer strong{color:#5c748c}
/* ================= RESPONSIVE ================= */
@media(max-width:1250px){.main{padding-left:2.5%;padding-right:2.5%}}
@media(max-width:1000px){.stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.info-grid{grid-template-columns:1fr}}
@media(max-width:650px){.main{padding-top:18px;padding-bottom:35px}.page-hero{align-items:flex-start;flex-direction:column;padding:23px 20px;border-radius:20px}.hero-chip{padding:10px 12px}.section-heading{align-items:flex-start}.section-tag{display:none}.stats-grid{gap:10px}.stat-card{padding:15px;border-radius:16px}.stat-icon{width:39px;height:39px;font-size:16px}.stat-label{margin-top:14px;font-size:9px}.stat-value{font-size:20px}.stat-foot{font-size:10px}.panel{border-radius:19px}.panel-heading{padding:17px}.panel-title h2{font-size:14px}.status-pill{padding:8px;font-size:9px}.search-form{width:100%}.search-input{flex:1}.btn{padding:10px}.table-footer{align-items:flex-start;flex-direction:column;padding:15px 17px}.info-grid{gap:10px}.info-card{padding:16px}.footer{align-items:flex-start;flex-direction:column}}
@media(max-width:360px){.stats-grid{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation:none!important;transition:none!important}}
@media print{body{color:#172033!important;background:#fff!important}.search-form,.pagination{display:none!important}.main{width:100%;padding:0}.page-hero,.stat-card,.panel,.info-card{color:#172033!important;background:#fff!important;box-shadow:none!important;border:1px solid #ddd!important;break-inside:avoid}.page-hero h1,.page-hero p,.stat-value,.section-heading h2,.info-card strong{color:#172033!important}.table-scroll{overflow:visible}table{min-width:0}th,td{padding:7px;font-size:9px;color:#172033!important}}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=4">
</head>
<body>

<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main">
    <!-- ================= HERO ================= -->
    <section class="page-hero">
        <div>
            <div class="eyebrow"><span class="live-dot"></span> Monitoring Cuaca / Data</div>
            <h1>Data Cuaca BMKG</h1>
            <p>BMKG Kemayoran · Ringkasan dan pemantauan data pengamatan cuaca.<br>Cari, periksa, dan pantau data yang tersimpan dalam sistem.</p>
        </div>
    </section>

    <!-- ================= STATISTIK ================= -->
    <section aria-label="Statistik data cuaca">
        <div class="section-heading">
            <div>
                <h2>Ringkasan Statistik</h2>
                <p>
                    Data prakiraan resmi BMKG untuk
                    <strong><?= h(bmkg_ringkas_lokasi($lokasiAktif)) ?></strong>.
                    Sumber: <?= h(bmkg_label_sumber($sumberCuaca)) ?>.
                </p>
            </div>
            <span class="section-tag"><i class="fa-solid fa-database"></i> <?= number_format($totalData,0,',','.') ?> DATA TERCATAT</span>
        </div>

        <div class="stats-grid">
            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fa-solid fa-database"></i></div>
                    <i class="fa-solid fa-chart-column" style="color:#115c9c"></i>
                </div>
                <div class="stat-label">Total Data Cuaca</div>
                <div class="stat-value"><?= number_format($totalData,0,',','.') ?> <span>record</span></div>
                <div class="stat-foot">Jumlah data yang tersimpan</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fa-solid fa-temperature-half"></i></div>
                    <i class="fa-solid fa-arrow-trend-up" style="color:#115c9c"></i>
                </div>
                <div class="stat-label">Rata-rata Suhu</div>
                <div class="stat-value"><?= angkaCuaca($rataSuhu) ?> <span>°C</span></div>
                <div class="stat-foot">Minimum <?= angkaCuaca($suhuMin) ?> °C · Maksimum <?= angkaCuaca($suhuMaks) ?> °C</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fa-solid fa-droplet"></i></div>
                    <i class="fa-solid fa-water" style="color:#12879c"></i>
                </div>
                <div class="stat-label">Rata-rata Kelembapan</div>
                <div class="stat-value"><?= angkaCuaca($rataKelembapan) ?> <span>%</span></div>
                <div class="stat-foot">Kelembapan relatif udara</div>
            </article>

            <article class="stat-card">
                <div class="stat-top">
                    <div class="stat-icon"><i class="fa-solid fa-wind"></i></div>
                    <i class="fa-solid fa-arrow-right-long" style="color:#113a9c"></i>
                </div>
                <div class="stat-label">Rata-rata Kecepatan Angin</div>
                <div class="stat-value"><?= angkaCuaca($rataAngin) ?> <span>m/s</span></div>
                <div class="stat-foot">Kecepatan angin rata-rata</div>
            </article>
        </div>
    </section>

    <!-- ================= TABEL DATA ================= -->
    <section class="panel">
        <div class="panel-heading">
            <div class="panel-title">
                <div class="panel-title-icon"><i class="fa-solid fa-table-list"></i></div>
                <div>
                    <h2>Tabel Data Cuaca</h2>
                    <p>Data terbaru ditampilkan terlebih dahulu.</p>
                </div>
            </div>

            <form method="GET" action="data_cuaca.php" class="search-form">
                <input type="search" name="search" class="search-input" placeholder="Cari tanggal, suhu, kondisi..." value="<?= h($search) ?>" aria-label="Cari data cuaca">
                <button type="submit" class="btn"><i class="fa-solid fa-magnifying-glass"></i> Cari</button>
                <?php if ($search !== ''): ?>
                    <a href="data_cuaca.php" class="btn btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>No.</th><th>Tanggal</th><th>Waktu</th><th>Suhu</th><th>Kelembapan</th><th>Curah Hujan</th><th>Kecepatan Angin</th><th>Arah Angin</th><th>Kondisi Cuaca</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($dataHalaman): ?>
                    <?php foreach ($dataHalaman as $i => $baris): ?>
                        <tr>
                            <td class="row-number"><?= $mulai + $i + 1 ?></td>
                            <td><span class="date-main"><?= h($baris['tanggal'] ?? '-') ?></span></td>
                            <td><?= h($baris['waktu'] ?? '-') ?></td>
                            <td><?= angkaCuaca($baris['suhu'] ?? null) ?> °C</td>
                            <td><?= angkaCuaca($baris['kelembapan'] ?? null) ?> %</td>
                            <td><?= angkaCuaca($baris['curah_hujan'] ?? null) ?> mm</td>
                            <td><?= angkaCuaca($baris['kecepatan_angin'] ?? null) ?> m/s</td>
                            <td><?= h($baris['arah_angin'] ?? '-') ?></td>
                            <td><span class="weather-badge"><?= ikonCuaca($baris['kondisi_cuaca'] ?? '') ?> <?= h($baris['kondisi_cuaca'] ?? '-') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <strong><i class="fa-solid fa-cloud"></i> Data tidak ditemukan</strong>
                                <p>
                                <?php if ($search !== ''): ?>
                                    Tidak ada data yang cocok dengan pencarian "<?= h($search) ?>".
                                <?php else: ?>
                                    Belum ada data. API BMKG belum mengembalikan prakiraan untuk wilayah ini.
                                <?php endif; ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="table-footer">
            <div class="result-info">
                Menampilkan <?= $totalHasil ? $mulai + 1 : 0 ?>–<?= min($mulai + $perHalaman,$totalHasil) ?> dari <?= number_format($totalHasil,0,',','.') ?> data
            </div>

            <?php if ($totalHalaman > 1): ?>
                <div class="pagination">
                    <?php
                    $urlHalaman = function($nomor) use ($search) {
                        return '?' . http_build_query(['search' => $search, 'page' => $nomor]);
                    };
                    ?>
                    <?php if ($halaman > 1): ?>
                        <a href="<?= h($urlHalaman($halaman - 1)) ?>" aria-label="Halaman sebelumnya"><i class="fa-solid fa-chevron-left"></i></a>
                    <?php endif; ?>

                    <?php
                    $awal = max(1, $halaman - 2);
                    $akhir = min($totalHalaman, $halaman + 2);
                    ?>
                    <?php for ($p = $awal; $p <= $akhir; $p++): ?>
                        <?php if ($p === $halaman): ?>
                            <span class="current"><?= $p ?></span>
                        <?php else: ?>
                            <a href="<?= h($urlHalaman($p)) ?>"><?= $p ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($halaman < $totalHalaman): ?>
                        <a href="<?= h($urlHalaman($halaman + 1)) ?>" aria-label="Halaman berikutnya"><i class="fa-solid fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- ================= RINGKASAN DATA ================= -->
    <section class="info-grid">
        <article class="info-card">
            <span><i class="fa-solid fa-cloud-rain"></i> Total Curah Hujan</span>
            <strong><?= angkaCuaca($totalHujan) ?> mm</strong>
        </article>
        <article class="info-card">
            <span><i class="fa-regular fa-clock"></i> Data Terakhir</span>
            <strong><?= h($dataTerbaru['tanggal'] ?? '-') ?> <?= h($dataTerbaru['waktu'] ?? '') ?></strong>
        </article>
        <article class="info-card">
            <span><i class="fa-solid fa-cloud-sun"></i> Kondisi Cuaca Terakhir</span>
            <strong><?= ikonCuaca($dataTerbaru['kondisi_cuaca'] ?? '') ?> <?= h($dataTerbaru['kondisi_cuaca'] ?? '-') ?></strong>
        </article>
    </section>

    <footer class="footer">
        <div><strong>BMKG KEMAYORAN</strong><br>Sistem Pemantauan Data Cuaca</div>
        <div>Sumber: prakiraan resmi BMKG · © <?= date('Y') ?> BMKG Kemayoran</div>
    </footer>
</main>

</body>
</html>
