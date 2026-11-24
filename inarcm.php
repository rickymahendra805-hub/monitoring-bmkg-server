
<?php
/* =========================================================
   INARCM - BMKG KEMAYORAN
   - Menu horizontal mengikuti dashboard.php
   - Variabel: Suhu dan Curah Hujan
   - Filter jenis produk dan periode
   - Animasi Initial dan rangkaian prakiraan
   - Gambar dibaca rekursif tanpa mengubah file asli
   - Periode pilihan Agustus 2026 - Oktober 2026
========================================================= */

date_default_timezone_set('Asia/Jakarta');

$assetsDir = __DIR__ . '/assets';
$rootDir = $assetsDir . '/inarcm';
$rootUrl = 'assets/inarcm/';
$folderAda = false;

$folderKandidat = [
    $assetsDir . '/inarcm',
    $assetsDir . '/inaRCM',
    $assetsDir . '/InaRCM',
    $assetsDir . '/INA RCM',
    $assetsDir . '/ina_rcm',
    $assetsDir . '/ina-rcm'
];

foreach ($folderKandidat as $kandidat) {
    if (is_dir($kandidat) && is_readable($kandidat)) {
        $rootDir = $kandidat;
        $folderAda = true;
        break;
    }
}

if (!$folderAda && is_dir($assetsDir) && is_readable($assetsDir)) {
    try {
        $iteratorFolder = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $assetsDir,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iteratorFolder as $entryFolder) {
            if (!$entryFolder->isDir()) {
                continue;
            }

            $namaFolder = strtolower(
                preg_replace('/[^a-z0-9]/i', '', $entryFolder->getFilename())
            );

            if ($namaFolder === 'inarcm') {
                $rootDir = $entryFolder->getPathname();
                $folderAda = true;
                break;
            }
        }
    } catch (Throwable $e) {
        // Tampilkan status folder di halaman.
    }
}

if ($folderAda) {
    $relativeRootUrl = str_replace(
        '\\',
        '/',
        substr($rootDir, strlen(__DIR__) + 1)
    );

    $rootUrl = rtrim($relativeRootUrl, '/') . '/';
}

$allowedExtensions = [
    'jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp', 'avif'
];

function h($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function namaBulanIna($periode)
{
    static $bulan = [
        '01' => 'Januari',
        '02' => 'Februari',
        '03' => 'Maret',
        '04' => 'April',
        '05' => 'Mei',
        '06' => 'Juni',
        '07' => 'Juli',
        '08' => 'Agustus',
        '09' => 'September',
        '10' => 'Oktober',
        '11' => 'November',
        '12' => 'Desember'
    ];

    $periode = (string)$periode;

    if (!preg_match('/^(20\d{2})(0[1-9]|1[0-2])$/', $periode, $m)) {
        return $periode;
    }

    return $bulan[$m[2]] . ' ' . $m[1];
}

/*
 * Menghitung periode (YYYYMM) yang berlaku setelah menambah
 * sejumlah bulan pada periode awal. Dipakai untuk menamai
 * bulan valid setiap frame prakiraan InaRCM.
 */
function periodeBulanBerikutnya($periode, $jumlahBulan)
{
    if (!preg_match('/^(20\d{2})(0[1-9]|1[0-2])$/', (string)$periode, $m)) {
        return '';
    }

    try {
        $tanggal = DateTime::createFromFormat(
            '!Y-m',
            $m[1] . '-' . $m[2]
        );

        if ($tanggal === false) {
            return '';
        }

        $tanggal->modify('+' . (int)$jumlahBulan . ' months');

        return $tanggal->format('Ym');
    } catch (Throwable $e) {
        return '';
    }
}

/*
 * Frame InaRCM dinamai ..._{init}(n).png, dengan n = bulan ke-n
 * setelah bulan inisialisasi. Mengembalikan n atau null bila tidak terbaca.
 */
function indeksFrameIna($file)
{
    if (preg_match('/\((\d+)\)/', basename((string)$file), $m)) {
        return (int)$m[1];
    }

    return null;
}

function urlIna($file)
{
    global $rootUrl;

    $parts = explode('/', str_replace('\\', '/', $file));

    return rtrim($rootUrl, '/') . '/' .
        implode('/', array_map('rawurlencode', $parts));
}

/*
 * Mengambil periode YYYYMM dari nama file atau folder.
 * Folder YYYYMM diprioritaskan untuk periode initial.
 */
function periodeDariPath($path)
{
    $p = str_replace('\\', '/', (string)$path);

    if (preg_match(
        '/(?<!\d)(20\d{2})(0[1-9]|1[0-2])(?!\d)/',
        $p,
        $m
    )) {
        return $m[1] . $m[2];
    }

    if (preg_match(
        '/(?<!\d)(20\d{2})[-_. ](0[1-9]|1[0-2])(?!\d)/',
        $p,
        $m
    )) {
        return $m[1] . $m[2];
    }

    $bulanMap = [
        'januari' => '01', 'january' => '01', 'jan' => '01',
        'februari' => '02', 'february' => '02', 'feb' => '02',
        'maret' => '03', 'march' => '03', 'mar' => '03',
        'april' => '04', 'apr' => '04',
        'mei' => '05', 'may' => '05',
        'juni' => '06', 'june' => '06', 'jun' => '06',
        'juli' => '07', 'july' => '07', 'jul' => '07',
        'agustus' => '08', 'august' => '08', 'agu' => '08', 'aug' => '08',
        'september' => '09', 'sept' => '09', 'sep' => '09',
        'oktober' => '10', 'october' => '10', 'okt' => '10', 'oct' => '10',
        'november' => '11', 'nov' => '11',
        'desember' => '12', 'december' => '12', 'des' => '12', 'dec' => '12'
    ];

    $lower = strtolower($p);

    $bulanRegex =
        '(januari|january|jan|februari|february|feb|maret|march|mar|april|apr|mei|may|juni|june|jun|juli|july|jul|agustus|august|agu|aug|september|sept|sep|oktober|october|okt|oct|november|nov|desember|december|des|dec)';

    if (preg_match(
        '/' . $bulanRegex . '[\s_.-]+(20\d{2})/i',
        $lower,
        $m
    )) {
        return $m[2] . ($bulanMap[strtolower($m[1])] ?? '');
    }

    if (preg_match(
        '/(20\d{2})[\s_.-]+' . $bulanRegex . '/i',
        $lower,
        $m
    )) {
        return $m[1] . ($bulanMap[strtolower($m[2])] ?? '');
    }

    return '';
}

function tipeDariPath($path)
{
    $p = strtolower(str_replace('\\', '/', (string)$path));

    /*
     * Penanda paling andal adalah indeks bulan pada nama file:
     * ..._{init}(0).png adalah kondisi awal, (1), (2), dst adalah prakiraan.
     */
    $indeks = indeksFrameIna($path);

    if ($indeks !== null) {
        return $indeks === 0 ? 'initial' : 'forecast';
    }

    if (preg_match(
        '/initial|initialization|inisial|inisiasi|(^|[^a-z])init([^a-z]|$)|analysis|analisis|(^|[^a-z])anl([^a-z]|$)/i',
        $p
    )) {
        return 'initial';
    }

    if (preg_match(
        '/forecast|forecasting|fcst|forcst|prakiraan|prediksi|(^|[^a-z])for([^a-z]|$)/i',
        $p
    )) {
        return 'forecast';
    }

    return 'lainnya';
}

/*
 * Hanya dua variabel yang ditampilkan pada filter.
 * TAS = suhu, PREC = curah hujan.
 */
function variabelDariPath($path)
{
    $p = strtolower(str_replace('\\', '/', (string)$path));
    $nama = strtolower(basename($p));

    if (
        preg_match('/(^|[^a-z])tas([^a-z]|$)/', $nama) ||
        preg_match('/temperature|(^|[^a-z])temp([^a-z]|$)|suhu|(^|[^a-z])t2m([^a-z]|$)/', $p)
    ) {
        return 'Suhu Udara';
    }

    if (
        preg_match('/(^|[^a-z])prec([^a-z]|$)/', $nama) ||
        preg_match('/precipitation|precip|rainfall|curah.?hujan|(^|[^a-z])prcp([^a-z]|$)/', $p)
    ) {
        return 'Curah Hujan';
    }

    return 'Lainnya';
}

function jenisDariPath($path)
{
    $p = strtolower(str_replace('\\', '/', (string)$path));

    if (preg_match(
        '/koreksi|corrected|correction|bias.?correct|hasil.?koreksi/',
        $p
    )) {
        return 'Hasil Koreksi';
    }

    if (preg_match(
        '/percent|persen|(^|[^a-z])pct([^a-z]|$)/',
        $p
    )) {
        return 'Persen Anomali';
    }

    if (preg_match(
        '/anomaly|anomali|(^|[^a-z])anom([^a-z]|$)/',
        $p
    )) {
        return 'Anomali';
    }

    /*
     * File tanpa penanda corrected, anomaly, atau percent
     * masuk kategori Lainnya.
     */
    return 'Lainnya';
}

function deskripsiIna($file, $variabel, $jenis)
{
    $dasar = [
        'Curah Hujan' =>
            'Produk ini menampilkan parameter curah hujan atau presipitasi. Periksa legenda, satuan, cakupan wilayah, dan periode valid pada gambar asli sebelum menafsirkan intensitas hujan.',

        'Suhu Udara' =>
            'Produk ini menampilkan parameter suhu udara untuk periode yang dianalisis. Periksa legenda warna, satuan, wilayah, dan waktu valid yang tercantum pada gambar.'
    ];

    $teks = $dasar[$variabel] ??
        'Produk InaRCM. Periksa nama berkas, legenda, satuan, wilayah, dan waktu valid pada gambar asli.';

    if ($jenis === 'Hasil Koreksi') {
        $teks .= ' File teridentifikasi sebagai produk hasil koreksi.';
    } elseif ($jenis === 'Anomali') {
        $teks .= ' File teridentifikasi sebagai produk anomali.';
    } elseif ($jenis === 'Persen Anomali') {
        $teks .= ' File teridentifikasi sebagai persen anomali.';
    }

    return $teks;
}

function scanIna($folder, $root, $allowedExtensions)
{
    $hasil = [];

    if (!is_dir($folder) || !is_readable($folder)) {
        return $hasil;
    }

    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $folder,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $info) {
            if (!$info->isFile()) {
                continue;
            }

            $ext = strtolower($info->getExtension());

            if (!in_array($ext, $allowedExtensions, true)) {
                continue;
            }

            $full = $info->getPathname();

            $relative = str_replace(
                '\\',
                '/',
                substr($full, strlen($root) + 1)
            );

            $namaFile = pathinfo(
                basename($relative),
                PATHINFO_FILENAME
            );

            $namaTampilan = ucwords(
                str_replace(['_', '-', '.'], ' ', $namaFile)
            );

            /*
             * Periode folder dipakai sebagai initial bila folder
             * memang memiliki nama YYYYMM.
             */
            $folderRelatif = dirname($relative);
            $periodeFolder = periodeDariPath($folderRelatif);
            $periodeNamaFile = periodeDariPath(basename($relative));

            $periode = $periodeNamaFile !== ''
                ? $periodeNamaFile
                : $periodeFolder;

            $variabel = variabelDariPath($relative);
            $jenis = jenisDariPath($relative);
            $tipe = tipeDariPath($relative);

            $hasil[] = [
                'file' => $relative,
                'nama' => $namaTampilan,
                'periode' => $periode,
                'periode_folder' => $periodeFolder,
                'periode_file' => $periodeNamaFile,
                'variabel' => $variabel,
                'jenis' => $jenis,
                'tipe' => $tipe,
                'deskripsi' => deskripsiIna(
                    $relative,
                    $variabel,
                    $jenis
                )
            ];
        }
    } catch (Throwable $e) {
        return $hasil;
    }

    usort($hasil, function ($a, $b) {
        $cmp = strcmp($a['periode'], $b['periode']);

        return $cmp !== 0
            ? $cmp
            : strnatcasecmp($a['file'], $b['file']);
    });

    return $hasil;
}

$folderAda = is_dir($rootDir) && is_readable($rootDir);

$gambarSemua = $folderAda
    ? scanIna($rootDir, $rootDir, $allowedExtensions)
    : [];

/* Filter variabel dibatasi menjadi Suhu Udara dan Curah Hujan. */
$variabelPilihan = ['Suhu Udara', 'Curah Hujan'];
$jenisPilihan = ['Hasil Koreksi', 'Anomali', 'Persen Anomali'];

/* Periode diambil dari folder aset yang benar-benar ada. */
$periodePilihan = [];

foreach ($gambarSemua as $gambar) {
    $periode = $gambar['periode_folder'] !== ''
        ? $gambar['periode_folder']
        : $gambar['periode'];

    if ($periode !== '' && !in_array($periode, $periodePilihan, true)) {
        $periodePilihan[] = $periode;
    }
}

sort($periodePilihan);

$variabelDipilih = trim((string)($_GET['variabel'] ?? ''));
$jenisDipilih = trim((string)($_GET['jenis'] ?? ''));

$periodeDipilih = trim(
    (string)($_GET['periode'] ?? ($_GET['initial'] ?? ''))
);

$filterDijalankan = isset($_GET['cari']);

if (!in_array($variabelDipilih, $variabelPilihan, true)) {
    $variabelDipilih = '';
}

if (!in_array($jenisDipilih, $jenisPilihan, true)) {
    $jenisDipilih = '';
}

if (!in_array($periodeDipilih, $periodePilihan, true)) {
    $periodeDipilih = '';
}

/* Filter variabel dan jenis digunakan secara ketat. */
$gambarFilter = [];

foreach ($gambarSemua as $gambar) {
    if (!in_array($gambar['variabel'], $variabelPilihan, true)) {
        continue;
    }

    if (
        $variabelDipilih !== '' &&
        $gambar['variabel'] !== $variabelDipilih
    ) {
        continue;
    }

    if (
        $jenisDipilih !== '' &&
        $gambar['jenis'] !== $jenisDipilih
    ) {
        continue;
    }

    $gambarFilter[] = $gambar;
}

/*
 * Ambil initial berdasarkan folder periode.
 * File yang tidak memiliki penanda initial tidak akan
 * diklaim sebagai Initial.
 */
$hasilInitial = [];

if ($filterDijalankan && $periodeDipilih !== '') {
    foreach ($gambarFilter as $gambar) {
        if ($gambar['tipe'] !== 'initial') {
            continue;
        }

        $periodeInitial = $gambar['periode_folder'] !== ''
            ? $gambar['periode_folder']
            : $gambar['periode'];

        if ($periodeInitial !== $periodeDipilih) {
            continue;
        }

        $hasilInitial[] = $gambar;
    }
}

/*
 * Satu timeline:
 * - Initial periode terpilih jika nama file/folder menandainya.
 * - Dilanjutkan gambar berikutnya berdasarkan urutan periode.
 * - Tidak menghapus atau mengubah gambar sumber.
 */
$animasiForecast = [];

if ($filterDijalankan && $periodeDipilih !== '') {
    foreach ($gambarFilter as $gambar) {
        if ($gambar['tipe'] !== 'initial') {
            continue;
        }

        $periodeInitial = $gambar['periode_folder'] !== ''
            ? $gambar['periode_folder']
            : $gambar['periode'];

        if ($periodeInitial !== $periodeDipilih) {
            continue;
        }

        $animasiForecast[] = [
            'src' => urlIna($gambar['file']),
            'nama' => $gambar['nama'],
            'periode' => $periodeInitial,
            'bulan' => namaBulanIna($periodeInitial),
            'deskripsi' => $gambar['deskripsi'],
            'file' => $gambar['file'],
            'inisial' => namaBulanIna($periodeInitial),
            'lead' => 'Kondisi awal',
            'label' => 'Data Awal'
        ];
    }

    $framesForecast = [];

    foreach ($gambarFilter as $gambar) {
        if ($gambar['periode'] === '') {
            continue;
        }

        if ($gambar['tipe'] === 'initial') {
            continue;
        }

        /*
         * Frame prakiraan hanya diambil dari run (folder init) yang sama
         * dengan periode terpilih bila folder initnya terbaca, agar timeline
         * tidak mencampur prakiraan dari run lain.
         */
        $initFrame = $gambar['periode_folder'] !== ''
            ? $gambar['periode_folder']
            : '';

        if ($initFrame !== '' && $initFrame !== $periodeDipilih) {
            continue;
        }

        if ($initFrame === '' && strcmp($gambar['periode'], $periodeDipilih) < 0) {
            continue;
        }

        /*
         * Bulan valid frame dihitung dari indeks (n) pada nama file:
         * ..._{init}(n).png berarti n bulan setelah bulan init.
         */
        $indeks = indeksFrameIna($gambar['file']);

        $periodeValid = $indeks !== null
            ? periodeBulanBerikutnya($periodeDipilih, $indeks)
            : '';

        if ($periodeValid === '') {
            $periodeValid = $gambar['periode'];
        }

        $framesForecast[] = [
            'src' => urlIna($gambar['file']),
            'nama' => $gambar['nama'],
            'periode' => $periodeValid,
            'bulan' => namaBulanIna($periodeValid),
            'deskripsi' => $gambar['deskripsi'],
            'file' => $gambar['file'],
            'inisial' => namaBulanIna($periodeDipilih),
            'lead' => $indeks === null
                ? ''
                : ($indeks === 0 ? 'Kondisi awal' : 'Forecast bulan ke-' . $indeks),
            'label' => $indeks === 0
                ? 'Data Awal'
                : 'Forecast',
            'indeks' => $indeks
        ];
    }

    usort($framesForecast, function ($a, $b) {
        /* Frame tanpa indeks diurutkan paling akhir, lalu periode, lalu nama file. */
        $ia = $a['indeks'] === null ? PHP_INT_MAX : $a['indeks'];
        $ib = $b['indeks'] === null ? PHP_INT_MAX : $b['indeks'];

        if ($ia !== $ib) {
            return $ia <=> $ib;
        }

        $cmp = strcmp($a['periode'], $b['periode']);

        return $cmp !== 0
            ? $cmp
            : strnatcasecmp($a['file'], $b['file']);
    });

    $sudahAda = [];

    foreach ($animasiForecast as $frame) {
        $sudahAda[$frame['file']] = true;
    }

    foreach ($framesForecast as $frame) {
        if (isset($sudahAda[$frame['file']])) {
            continue;
        }

        $sudahAda[$frame['file']] = true;
        $animasiForecast[] = $frame;
    }
}

$animasiForecastJson = json_encode(
    $animasiForecast,
    JSON_UNESCAPED_UNICODE |
    JSON_UNESCAPED_SLASHES |
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
);

if ($animasiForecastJson === false) {
    $animasiForecastJson = '[]';
}

$currentPage = basename($_SERVER['PHP_SELF'] ?? 'inarcm.php');

function menuAktifInaRCM($halaman, $currentPage)
{
    return $halaman === $currentPage ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>InaRCM | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --bg: #eef3f9;
    --surface: #ffffff;
    --surface2: #eef3f9;
    --line: rgba(35,70,110,0.220);
    --text: #16293f;
    --muted: #5c748c;
    --blue: #0f61bd;
    --cyan: #10a6bc;
    --green: #24a873;
    --shadow: 0 16px 40px rgba(16,42,67,0.048);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html { scroll-behavior: smooth; }

body {
    min-height: 100vh;
    font-family: Inter, Arial, sans-serif;
    color: var(--text);
    background:
        radial-gradient(ellipse at 8% 0%,rgba(37,140,255,.13),transparent 32%),
        radial-gradient(ellipse at 95% 20%,rgba(62,216,239,.07),transparent 30%),
        var(--bg);
}

a { color: inherit; text-decoration: none; }
button, select { font: inherit; }

.main {
    width: min(1500px,100%);
    margin: auto;
    padding: 30px 4% 55px;
}

.hero {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 34px;
    border: 1px solid rgba(15,101,189,0.200);
    border-radius: 25px;
    background:
        radial-gradient(circle at 85% 20%,rgba(62,216,239,.18),transparent 25%),
        linear-gradient(120deg,#f5f9fd,#ffffff 62%,#f5f9fd);
    box-shadow: var(--shadow);
}

.eyebrow {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 13px;
    color: #11659c;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1.5px;
    text-transform: uppercase;
}

.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--green);
    box-shadow: 0 0 12px rgba(66,214,155,.7);
}

.hero h1 {
    font-family: Poppins, sans-serif;
    font-size: clamp(23px,3vw,34px);
    line-height: 1.35;
}

.hero p {
    max-width: 800px;
    margin-top: 10px;
    color: #5c748c;
    font-size: 13px;
    line-height: 1.8;
}

.hero-chip {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 13px 16px;
    border: 1px solid rgba(23,52,86,0.055);
    border-radius: 14px;
    color: #16293f;
    background: rgba(23,52,86,0.030);
    font-size: 12px;
    white-space: nowrap;
}

.hero-chip i { color: var(--cyan); font-size: 18px; }

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin: 30px 0 16px;
}

.section-heading h2 {
    font-family: Poppins, sans-serif;
    font-size: 17px;
}

.section-heading p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
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

.panel {
    margin-top: 25px;
    padding: 25px;
    border: 1px solid var(--line);
    border-radius: 23px;
    background: linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
    box-shadow: var(--shadow);
}

.panel-heading {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 21px;
}

.panel-title-icon {
    width: 43px;
    height: 43px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(62,216,239,.14);
    border-radius: 13px;
    color: var(--cyan);
    background: rgba(62,216,239,.08);
    font-size: 17px;
}

.panel-heading h2 {
    font-family: Poppins, sans-serif;
    font-size: 16px;
}

.panel-heading p {
    margin-top: 4px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.7;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(3,minmax(0,1fr));
    gap: 16px;
}

.field label {
    display: block;
    margin-bottom: 8px;
    color: #5c748c;
    font-size: 11px;
    font-weight: 700;
}

.field select {
    width: 100%;
    min-height: 46px;
    padding: 12px;
    border: 1px solid #ced8e3;
    border-radius: 11px;
    outline: none;
    color: #16293f;
    background: #eef3f9;
    font-size: 12px;
}

.field select:focus { border-color: var(--blue); }

.field select option {
    background: #eef3f9;
    color: #16293f;
}

.form-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 18px;
}

.btn {
    min-height: 43px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 16px;
    border: 1px solid rgba(37,140,255,.25);
    border-radius: 11px;
    color: #16293f;
    background: rgba(37,140,255,.14);
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.btn:hover { background: rgba(37,140,255,.25); }

.btn-secondary {
    border-color: var(--line);
    background: rgba(23,52,86,0.030);
    color: #22364e;
}

.result-info {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 20px;
    margin: 20px 0;
    color: #5c748c;
    font-size: 12px;
}

.result-info strong { color: #16293f; }

.product-panel {
    margin-top: 20px;
    overflow: hidden;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: linear-gradient(145deg,#ffffff,#eef3f9);
    box-shadow: var(--shadow);
}

.product-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 18px;
    border-bottom: 1px solid var(--line);
    background: rgba(23,52,86,0.030);
}

.product-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: #11619c;
    background: rgba(37,140,255,.12);
}

.product-header h2 {
    font-family: Poppins, sans-serif;
    font-size: 15px;
}

.product-header p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 11px;
    line-height: 1.7;
}

.product-body { padding: 18px; }

.forecast-animation {
    padding: 16px;
    border: 1px solid rgba(62,216,239,.22);
    border-radius: 16px;
    background: linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
}

.animation-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
}

.animation-kicker {
    color: #118b9c;
    font-size: 9px;
    font-weight: 800;
    letter-spacing: 1.4px;
    text-transform: uppercase;
}

.animation-title {
    margin-top: 5px;
    color: #16293f;
    font-family: Poppins, sans-serif;
    font-size: 18px;
}

.animation-counter {
    padding: 7px 9px;
    border: 1px solid rgba(62,216,239,.18);
    border-radius: 9px;
    color: #118c9c;
    background: rgba(62,216,239,.07);
    font-size: 10px;
    white-space: nowrap;
}

/* Gambar animasi ditampilkan sebesar mungkin agar teks di dalam peta terbaca. */
.animation-frame {
    position: relative;
    display: grid;
    place-items: center;
    min-height: 180px;
    max-height: 78vh;
    overflow: hidden;
    border: 1px solid rgba(23,52,86,0.044);
    border-radius: 12px;
    background: #eef3f9;
}

.animation-frame img {
    display: block;
    width: 100%;
    max-width: 1180px;
    height: auto;
    max-height: 74vh;
    object-fit: contain;
    cursor: zoom-in;
    animation: fade .35s ease;
}

/* Keterangan inisial dan prakiraan menempel pada gambar. */
.animation-badge {
    position: absolute;
    left: 10px;
    top: 10px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4px;
    z-index: 2;
    pointer-events: none;
}

.animation-badge[hidden] {
    display: none;
}

.animation-badge span {
    background: rgba(255,255,255,0.94);
    border: 1px solid rgba(23,52,86,0.088);
    border-radius: 8px;
    padding: 4px 9px;
    font-size: 11px;
    font-weight: 600;
    color: #16293f;
    white-space: nowrap;
    backdrop-filter: blur(3px);
}

.animation-no-image {
    padding: 35px 12px;
    color: #5c748c;
    text-align: center;
    font-size: 12px;
    line-height: 1.8;
}

.animation-no-image i {
    font-size: 27px;
    color: #11569c;
    margin-bottom: 12px;
}

.animation-details { padding: 12px 2px 2px; }

.animation-file-title {
    color: #16293f;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.7;
    overflow-wrap: anywhere;
}

.animation-description {
    margin-top: 6px;
    color: #5c748c;
    font-size: 11px;
    line-height: 1.8;
}

.file-name {
    margin-top: 8px;
    color: #5c748c;
    font-size: 10px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.animation-track {
    height: 4px;
    margin: 12px 0 13px;
    overflow: hidden;
    border-radius: 99px;
    background: #eaf2fa;
}

.animation-track-fill {
    width: 0;
    height: 100%;
    border-radius: 99px;
    background: linear-gradient(90deg,#258cff,#3ed8ef);
    transition: width .2s linear;
}

.animation-controls {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    flex-wrap: wrap;
}

.animation-buttons {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}

.animation-btn {
    min-width: 37px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 11px;
    border: 1px solid rgba(35,70,110,0.220);
    border-radius: 10px;
    color: #16293f;
    background: rgba(23,52,86,0.030);
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
}

.animation-btn:hover,
.animation-btn.is-playing {
    border-color: rgba(62,216,239,.35);
    color: #16293f;
    background: rgba(37,140,255,.18);
}

.animation-btn:disabled {
    opacity: .4;
    cursor: not-allowed;
}

.animation-speed {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #5c748c;
    font-size: 10px;
}

.animation-speed select {
    max-width: 150px;
    padding: 8px;
    border: 1px solid #ced8e3;
    border-radius: 9px;
    color: #16293f;
    background: #eef3f9;
    font-size: 10px;
}

.empty {
    padding: 25px 18px;
    border: 1px dashed #ced8e3;
    border-radius: 14px;
    color: #5c748c;
    background: rgba(23,52,86,0.030);
    text-align: center;
    font-size: 12px;
    line-height: 1.9;
}

.empty i {
    display: block;
    margin-bottom: 10px;
    color: #11569c;
    font-size: 24px;
}

.footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid var(--line);
    color: #5c748c;
    font-size: 11px;
    line-height: 1.7;
}

.footer strong { color: #5c748c; }

.modal {
    position: fixed;
    inset: 0;
    z-index: 3000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 25px;
    background: rgba(255,255,255,0.94);
    backdrop-filter: blur(12px);
}

.modal.show { display: flex; }

.modal img {
    max-width: 95%;
    max-height: 86vh;
    object-fit: contain;
    border: 1px solid rgba(23,52,86,0.083);
    border-radius: 13px;
}

.modal-caption {
    position: absolute;
    left: 50%;
    bottom: 22px;
    transform: translateX(-50%);
    max-width: 92vw;
    padding: 8px 14px;
    border: 1px solid rgba(23,52,86,0.088);
    border-radius: 10px;
    background: rgba(255,255,255,0.94);
    color: #16293f;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 25px;
    width: 45px;
    height: 45px;
    display: grid;
    place-items: center;
    border: 1px solid rgba(23,52,86,0.083);
    border-radius: 50%;
    color: #16293f;
    background: rgba(23,52,86,0.044);
    font-size: 24px;
    cursor: pointer;
}

@keyframes fade {
    from { opacity: .4; transform: scale(.995); }
    to { opacity: 1; transform: scale(1); }
}

@media (max-width: 1250px) {
    .main { padding-left: 2.5%; padding-right: 2.5%; }
}

@media (max-width: 1000px) {

    .filter-grid { grid-template-columns: 1fr 1fr; }
}

@media (max-width: 700px) {
    .main { padding-top: 18px; padding-bottom: 35px; }

    .hero {
        align-items: flex-start;
        flex-direction: column;
        padding: 23px 20px;
    }

    .hero-chip { padding: 10px 12px; }
    .panel { padding: 17px; }
    .filter-grid { grid-template-columns: 1fr; }

    .animation-frame {
        min-height: 150px;
        max-height: 62vh;
    }

    .animation-frame img {
        height: auto;
        max-height: 58vh;
    }

    .footer {
        align-items: flex-start;
        flex-direction: column;
    }
}

@media (prefers-reduced-motion: reduce) {
    *, *::before, *::after {
        scroll-behavior: auto !important;
        animation: none !important;
        transition: none !important;
    }
}
</style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=3">
</head>

<body>
<?php include __DIR__ . '/includes/topbar.php'; ?>

<main class="main">
    <section class="hero">
        <div>
            <div class="eyebrow">
                <span class="live-dot"></span> Produk Analisis Atmosfer
            </div>
            <h1>Pemantauan InaRCM</h1>
            <p>
                Pilih variabel Suhu Udara atau Curah Hujan, jenis produk, dan periode awal.
                Gambar yang cocok akan ditampilkan dalam satu timeline animasi.
                Semua gambar sumber tetap berada di folder asalnya.
            </p>
        </div>

        <div class="hero-chip">
            <i class="fa-solid fa-earth-asia"></i>
            <span>Atmospheric Products<br><strong>InaRCM</strong></span>
        </div>
    </section>

    <section>
        <div class="section-heading">
            <div>
                <h2>Pencarian Produk InaRCM</h2>
                <p>Periode pilihan Agustus 2026 sampai Oktober 2026.</p>
            </div>
            <span class="section-tag">
                <i class="fa-solid fa-images"></i>
                <?= count($gambarSemua) ?> GAMBAR TERBACA
            </span>
        </div>

        <div class="panel">
            <div class="panel-heading">
                <div class="panel-title-icon"><i class="fa-solid fa-sliders"></i></div>
                <div>
                    <h2>Filter Produk dan Periode</h2>
                    <p>Pilih parameter yang ingin ditampilkan, lalu jalankan pencarian.</p>
                </div>
            </div>

            <form method="get" action="inarcm.php">
                <div class="filter-grid">
                    <div class="field">
                        <label for="variabel">Variabel</label>
                        <select name="variabel" id="variabel">
                            <option value="">Semua Variabel</option>
                            <?php foreach ($variabelPilihan as $v): ?>
                                <option value="<?= h($v) ?>" <?= $variabelDipilih === $v ? 'selected' : '' ?>>
                                    <?= h($v) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="jenis">Jenis Produk</label>
                        <select name="jenis" id="jenis">
                            <option value="">Semua Jenis</option>
                            <?php foreach ($jenisPilihan as $j): ?>
                                <option value="<?= h($j) ?>" <?= $jenisDipilih === $j ? 'selected' : '' ?>>
                                    <?= h($j) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="periode">Periode Initial (Bulan &amp; Tahun)</label>
                        <select name="periode" id="periode" required>
                            <option value="">Pilih Bulan dan Tahun</option>
                            <?php foreach ($periodePilihan as $b): ?>
                                <option value="<?= h($b) ?>" <?= $periodeDipilih === $b ? 'selected' : '' ?>>
                                    <?= h(namaBulanIna($b)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit" name="cari" value="1">
                        <i class="fa-solid fa-magnifying-glass"></i> Tampilkan Animasi
                    </button>

                    <a class="btn btn-secondary" href="inarcm.php">
                        <i class="fa-solid fa-rotate-left"></i> Reset Pilihan
                    </a>
                </div>
            </form>
        </div>
    </section>

    <?php if (!$folderAda): ?>
        <div class="empty" style="margin-top:24px">
            <i class="fa-solid fa-folder-open"></i>
            <strong>Folder InaRCM belum ditemukan.</strong><br>
            Pastikan gambar tetap berada di <strong>assets/inarcm/</strong>.
        </div>

    <?php elseif (empty($gambarSemua)): ?>
        <div class="empty" style="margin-top:24px">
            <i class="fa-regular fa-image"></i>
            Folder ditemukan, tetapi belum ada gambar dengan ekstensi yang didukung.
        </div>

    <?php elseif (!$filterDijalankan): ?>
        <div class="empty" style="margin-top:24px">
            <i class="fa-solid fa-filter"></i>
            Pilih variabel, jenis produk, dan periode awal, lalu klik
            <strong>Tampilkan Animasi</strong>.
        </div>

    <?php else: ?>
        <div class="result-info">
            <span>Periode awal: <strong><?= h(namaBulanIna($periodeDipilih)) ?></strong></span>
            <span>Initial terdeteksi: <strong><?= count($hasilInitial) ?></strong></span>
            <span>Jumlah frame animasi: <strong><?= count($animasiForecast) ?></strong></span>
            <span>Total file terindeks: <strong><?= count($gambarSemua) ?></strong></span>
        </div>

        <section class="product-panel">
            <div class="product-header">
                <div class="product-icon"><i class="fa-solid fa-clapperboard"></i></div>
                <div>
                    <h2>Timeline InaRCM — Initial &amp; Forecast</h2>
                    <p>Frame ditampilkan berurutan berdasarkan periode yang terdeteksi pada file.</p>
                </div>
            </div>

            <div class="product-body">
                <section class="forecast-animation" aria-label="Pemutar animasi produk InaRCM">
                    <div class="animation-top">
                        <div>
                            <div class="animation-kicker">
                                <i class="fa-solid fa-film"></i> Animasi Produk Bulanan
                            </div>
                            <div class="animation-title" id="animationMonthLabel">
                                Menyiapkan periode...
                            </div>
                        </div>
                        <div class="animation-counter" id="animationCounter">0 / 0</div>
                    </div>

                    <div class="animation-frame" id="animationFrame">
                        <div class="animation-badge" id="animationBadge" hidden>
                            <span id="animationBadgeInisial"></span>
                            <span id="animationBadgeForecast"></span>
                            <span id="animationBadgeLead"></span>
                        </div>

                        <div class="animation-no-image" id="animationEmpty">
                            <i class="fa-regular fa-images"></i><br>
                            Belum ada gambar yang cocok untuk filter ini.
                        </div>

                        <img id="animationImage"
                             src=""
                             alt="Animasi produk InaRCM"
                             style="display:none"
                             onclick="openImageModal(this.src,this.alt)">
                    </div>

                    <div class="animation-details">
                        <div class="animation-file-title" id="animationImageName">
                            Gambar akan tampil di sini
                        </div>
                        <div class="animation-description" id="animationDescription">
                            Deskripsi produk akan tampil di sini.
                        </div>
                        <div class="file-name" id="animationFileName"></div>
                    </div>

                    <div class="animation-track">
                        <div class="animation-track-fill" id="animationProgress"></div>
                    </div>

                    <div class="animation-controls">
                        <div class="animation-buttons">
                            <button class="animation-btn" type="button" id="animationPrev"
                                    title="Frame sebelumnya" aria-label="Frame sebelumnya">
                                <i class="fa-solid fa-backward-step"></i>
                            </button>

                            <button class="animation-btn" type="button" id="animationPlay"
                                    aria-label="Putar animasi">
                                <i class="fa-solid fa-play"></i> Putar
                            </button>

                            <button class="animation-btn" type="button" id="animationNext"
                                    title="Frame berikutnya" aria-label="Frame berikutnya">
                                <i class="fa-solid fa-forward-step"></i>
                            </button>

                            <button class="animation-btn" type="button" id="animationReset"
                                    title="Kembali ke awal" aria-label="Kembali ke awal">
                                <i class="fa-solid fa-rotate-left"></i> Awal
                            </button>
                        </div>

                        <label class="animation-speed" for="animationSpeed">
                            Kecepatan
                            <select id="animationSpeed">
                                <option value="3500">Lambat · 3,5 dtk</option>
                                <option value="2000" selected>Normal · 2 dtk</option>
                                <option value="1000">Cepat · 1 dtk</option>
                            </select>
                        </label>
                    </div>
                </section>

                <div id="animationStatus" class="empty" style="display:none;margin-top:14px"></div>
            </div>
        </section>
    <?php endif; ?>

    <footer class="footer">
        <div>
            <strong>BMKG KEMAYORAN</strong><br>
            Sistem Pemantauan Produk Atmosfer
        </div>
        <div>
            InaRCM · Data gambar lokal · <?= count($gambarSemua) ?> file gambar terindeks
        </div>
    </footer>
</main>

<div class="modal" id="imageModal" role="dialog" aria-modal="true"
     aria-label="Pratinjau gambar" onclick="closeImageModal()">
    <button class="modal-close" type="button" aria-label="Tutup gambar"
            onclick="closeImageModal()">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="modalImage" src="" alt="Pratinjau gambar">
    <div class="modal-caption" id="modalCaption"></div>
</div>

<script>
const forecastFrames = <?= $animasiForecastJson ?>;

let animationIndex = 0;
let animationTimer = null;

const animationImage = document.getElementById('animationImage');
const animationEmpty = document.getElementById('animationEmpty');
const animationMonthLabel = document.getElementById('animationMonthLabel');
const animationCounter = document.getElementById('animationCounter');
const animationImageName = document.getElementById('animationImageName');
const animationDescription = document.getElementById('animationDescription');
const animationFileName = document.getElementById('animationFileName');
const animationProgress = document.getElementById('animationProgress');
const animationPlay = document.getElementById('animationPlay');
const animationSpeed = document.getElementById('animationSpeed');
const animationPrev = document.getElementById('animationPrev');
const animationNext = document.getElementById('animationNext');
const animationReset = document.getElementById('animationReset');
const imageModal = document.getElementById('imageModal');
const modalImage = document.getElementById('modalImage');
const modalCaption = document.getElementById('modalCaption');

function captionFrame(frame) {
    if (!frame) {
        return 'Pratinjau gambar';
    }

    return 'Initial: ' + (frame.inisial || '-')
        + ' · Forecast: ' + (frame.bulan || '-')
        + (frame.lead ? ' · ' + frame.lead : '');
}

function renderModalFrame(frame, alt) {
    if (!imageModal || !imageModal.classList.contains('show')) {
        return;
    }

    if (modalImage) {
        modalImage.src = frame.src;
        modalImage.alt = alt || 'Pratinjau gambar';
    }

    if (modalCaption) {
        modalCaption.textContent = captionFrame(frame);
    }
}

function renderForecastFrame(index) {
    if (!Array.isArray(forecastFrames) || forecastFrames.length === 0) {
        animationImage.style.display = 'none';
        animationEmpty.style.display = 'block';
        animationMonthLabel.textContent = 'Data animasi belum tersedia';
        animationCounter.textContent = '0 / 0';
        animationImageName.textContent = 'Tidak ada gambar yang cocok dengan filter';
        animationDescription.textContent =
            'Periksa variabel, jenis produk, periode, serta nama file gambar.';
        animationFileName.textContent = '';
        animationProgress.style.width = '0%';

        animationPlay.disabled = true;
        animationPrev.disabled = true;
        animationNext.disabled = true;
        animationReset.disabled = true;
        return;
    }

    animationIndex = (index + forecastFrames.length) % forecastFrames.length;

    const frame = forecastFrames[animationIndex];

    animationEmpty.style.display = 'none';
    animationImage.style.display = 'block';
    animationImage.src = frame.src;
    animationImage.alt =
        (frame.label || 'Produk') + ' InaRCM ' +
        (frame.bulan || '') + ' - ' + (frame.nama || '');

    animationMonthLabel.textContent =
        (frame.bulan || 'Periode tidak diketahui') +
        ' · ' + (frame.label || 'Produk');

    const badge = document.getElementById('animationBadge');
    const badgeInisial = document.getElementById('animationBadgeInisial');
    const badgeForecast = document.getElementById('animationBadgeForecast');
    const badgeLead = document.getElementById('animationBadgeLead');

    if (badge) {
        badge.hidden = false;
        badgeInisial.textContent = 'Initial: ' + (frame.inisial || '-');
        badgeForecast.textContent = 'Forecast: ' + (frame.bulan || '-');
        badgeLead.textContent = frame.lead || '';
        badgeLead.style.display = frame.lead ? '' : 'none';
    }

    animationCounter.textContent =
        (animationIndex + 1) + ' / ' + forecastFrames.length;

    animationImageName.textContent = frame.nama || 'Produk InaRCM';
    animationDescription.textContent =
        frame.deskripsi || 'Produk InaRCM berdasarkan gambar yang tersedia.';

    animationFileName.textContent = frame.file || '';

    animationProgress.style.width =
        (((animationIndex + 1) / forecastFrames.length) * 100) + '%';

    renderModalFrame(frame, animationImage.alt);
}

function stopForecastAnimation() {
    if (animationTimer !== null) {
        window.clearInterval(animationTimer);
    }

    animationTimer = null;
    animationPlay.classList.remove('is-playing');
    animationPlay.innerHTML = '<i class="fa-solid fa-play"></i> Putar';
    animationPlay.setAttribute('aria-label', 'Putar animasi');
}

function startForecastAnimation() {
    if (!forecastFrames.length) {
        return;
    }

    stopForecastAnimation();

    if (forecastFrames.length < 2) {
        renderForecastFrame(0);
        return;
    }

    animationPlay.classList.add('is-playing');
    animationPlay.innerHTML = '<i class="fa-solid fa-pause"></i> Jeda';
    animationPlay.setAttribute('aria-label', 'Jeda animasi');

    animationTimer = window.setInterval(function() {
        renderForecastFrame(animationIndex + 1);
    }, Number(animationSpeed.value) || 2000);
}

if (Array.isArray(forecastFrames) && forecastFrames.length > 0) {
    renderForecastFrame(0);
    startForecastAnimation();
} else if (animationImage) {
    renderForecastFrame(0);
}

if (animationPlay) {
    animationPlay.addEventListener('click', function() {
        if (animationTimer !== null) {
            stopForecastAnimation();
        } else {
            startForecastAnimation();
        }
    });
}

if (animationPrev) {
    animationPrev.addEventListener('click', function() {
        stopForecastAnimation();
        renderForecastFrame(animationIndex - 1);
    });
}

if (animationNext) {
    animationNext.addEventListener('click', function() {
        stopForecastAnimation();
        renderForecastFrame(animationIndex + 1);
    });
}

if (animationReset) {
    animationReset.addEventListener('click', function() {
        stopForecastAnimation();
        renderForecastFrame(0);
    });
}

if (animationSpeed) {
    animationSpeed.addEventListener('change', function() {
        if (animationTimer !== null) {
            startForecastAnimation();
        }
    });
}

function openImageModal(src, alt) {
    const frame = Array.isArray(forecastFrames)
        ? forecastFrames[animationIndex]
        : null;

    if (modalCaption) {
        modalCaption.textContent = frame
            ? captionFrame(frame)
            : (alt || 'Pratinjau gambar');
    }

    if (modalImage) {
        /* Gambar sumber InaRCM berukuran tetap (sekitar 992x542 px).
           Membatasi lebar pada ukuran asli mencegah gambar dibesarkan
           sehingga terlihat buram. */
        modalImage.onload = function () {
            if (modalImage.naturalWidth > 0) {
                modalImage.style.maxWidth =
                    'min(95vw, ' + modalImage.naturalWidth + 'px)';
            }
        };

        modalImage.src = src;
        modalImage.alt = alt || 'Pratinjau gambar';
    }

    if (imageModal) {
        imageModal.classList.add('show');
    }

    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    if (imageModal) {
        imageModal.classList.remove('show');
    }

    if (modalCaption) {
        modalCaption.textContent = '';
    }

    if (modalImage) {
        modalImage.src = '';
    }

    document.body.style.overflow = '';
}

if (modalImage) {
    modalImage.addEventListener('click', function(event) {
        event.stopPropagation();
    });
}
</script>
</body>
</html>