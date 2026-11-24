<?php
/* =========================================================
   INAWMS - BMKG KEMAYORAN
   Pencarian: Tanggal Init, Jenis, Variabel, Sabuk Lintang

   Sumber gambar (HANYA DIBACA, tidak pernah diubah/dihapus):
   - assets/map/         -> Peta, Gelombang, Time Series
   - assets/hovmoller/   -> Hovmoller
   Tanggal init dibaca dari nama folder 8 digit (YYYYMMDD).
========================================================= */

date_default_timezone_set('Asia/Jakarta');

$assetsDir = __DIR__ . '/assets';

/* Cari folder 'map' dan 'hovmoller' tanpa peduli huruf besar/kecil (Linux sensitif). */
$sumberFolder = [];

if (is_dir($assetsDir)) {
    foreach (scandir($assetsDir) as $namaFolder) {
        if ($namaFolder === '.' || $namaFolder === '..') {
            continue;
        }

        if (
            is_dir($assetsDir . '/' . $namaFolder) &&
            in_array(strtolower($namaFolder), ['map', 'hovmoller'], true)
        ) {
            $sumberFolder[] = $namaFolder;
        }
    }
}

$allowedExtensions = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

$namaBulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
    '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
    '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
    '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

/*
 * URUTAN SABUK LINTANG (dari utara ke selatan).
 * Dropdown dan hasil pencarian mengikuti urutan ini, bukan acak.
 * Kode di kiri = bagian nama file, misal olr.cfs.n24.png -> 'n24'.
 * Ubah urutan atau label di sini jika perlu.
 */
$lintangDef = [
    'wide'     => 'Wide',
    'north'    => 'North',
    'north0'   => 'North 0',
    'north1'   => 'North 1',
    'north2'   => 'North 2',
    'n810'     => '8°N – 10°N',
    'n68'      => '6°N – 8°N',
    'n46'      => '4°N – 6°N',
    'n24'      => '2°N – 4°N',
    'n02'      => '0° – 2°N',
    'eqtr'     => 'Equatorial',
    'eqtrs2n2' => '2°S – 2°N',
    'eqtrs1n1' => '1°S – 1°N',
    's02'      => '0° – 2°S',
    's24'      => '2°S – 4°S',
    's46'      => '4°S – 6°S',
    's68'      => '6°S – 8°S',
    's810'     => '8°S – 10°S',
    'south'    => 'South',
    'south0'   => 'South 0',
    'south1'   => 'South 1',

    /*
     * Sabuk lintang versi bujur Indonesia (90 - 150 BT), dibuat oleh
     * proses_hovmoller.py dengan akhiran kode 'id'.
     */
    'wideid'     => 'Wide · Indonesia',
    'eqtrid'     => 'Equatorial · Indonesia',
    'eqtrs2n2id' => '2°S – 2°N · Indonesia'
];

$gelombangNama = [
    'er'     => 'ER',
    'kelvin' => 'Kelvin',
    'low'    => 'Low',
    'mjo'    => 'MJO',
    'mrgtd'  => 'MRG-TD'
];

$variabelUrut = ['OLR', 'PWAT', 'Lainnya'];
$jenisUrut = ['Hovmoller', 'Peta', 'Gelombang', 'Time Series'];

function h($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTanggalInit($ymd)
{
    global $namaBulan;

    if (!preg_match('/^(\d{4})(\d{2})(\d{2})$/', (string)$ymd, $m)) {
        return (string)$ymd;
    }

    return $m[3] . ' ' . ($namaBulan[$m[2]] ?? $m[2]) . ' ' . $m[1];
}

/*
 * Jarak waktu antara tanggal inisialisasi dan tanggal prakiraan,
 * dalam hari. Mengembalikan null bila salah satu tanggal tidak terbaca.
 */
function leadWms($init, $tanggal)
{
    if (
        !preg_match('/^\d{8}$/', (string)$init) ||
        !preg_match('/^\d{8}$/', (string)$tanggal)
    ) {
        return null;
    }

    $waktuInit = strtotime(substr($init, 0, 4) . '-' . substr($init, 4, 2) . '-' . substr($init, 6, 2));
    $waktuFc = strtotime(substr($tanggal, 0, 4) . '-' . substr($tanggal, 4, 2) . '-' . substr($tanggal, 6, 2));

    if ($waktuInit === false || $waktuFc === false) {
        return null;
    }

    return (int)round(($waktuFc - $waktuInit) / 86400);
}

/* Label lead time untuk kartu gambar. */
function leadLabelWms($init, $tanggal)
{
    $lead = leadWms($init, $tanggal);

    if ($lead === null) {
        return '';
    }

    if ($lead === 0) {
        return 'Initial (lead 0)';
    }

    if ($lead > 0) {
        return 'Lead +' . $lead . ' hari';
    }

    return 'Lead ' . $lead . ' hari';
}

function urlWms($relative)
{
    /*
     * Gunakan URL absolut dari direktori project, bukan URL relatif.
     * Ini mencegah browser mencari gambar dari alamat URL pencarian/query.
     * Nama folder/file tetap mengikuti nama aslinya di dalam assets.
     */
    $relative = trim(str_replace('\\', '/', (string)$relative), '/');
    $parts = array_map('rawurlencode', explode('/', $relative));

    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/inawms.php'));
    if ($scriptDir === '/' || $scriptDir === '.') {
        $scriptDir = '';
    }

    $url = rtrim($scriptDir, '/') . '/assets/' . implode('/', $parts);

    /* Render ulang menimpa gambar dengan nama yang sama, jadi waktu ubah
     * file ikut dipakai agar browser tidak menampilkan gambar lama. */
    $lokasi = __DIR__ . '/assets/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    $versi = is_file($lokasi) ? filemtime($lokasi) : false;

    return $versi === false ? $url : $url . '?t=' . $versi;
}

function variabelWms($relative)
{
    $nama = strtolower(basename($relative));

    if (strpos($nama, 'pwat') === 0) {
        return 'PWAT';
    }

    if (strpos($nama, 'olr') === 0) {
        return 'OLR';
    }

    $semua = strtolower(str_replace('\\', '/', $relative));

    if (strpos($semua, 'pwat') !== false) {
        return 'PWAT';
    }

    if (strpos($semua, 'olr') !== false || strpos($semua, 'orl') !== false) {
        return 'OLR';
    }

    return 'Lainnya';
}

function jenisWms($relative)
{
    global $gelombangNama;

    $p = strtolower(str_replace('\\', '/', $relative));

    if (strpos($p, 'hov') !== false) {
        return 'Hovmoller';
    }

    $nama = strtolower(pathinfo($p, PATHINFO_FILENAME));

    /* Varian wilayah memakai akhiran pada nama file, mis. mrgtd.indonesia.png. */
    $dasar = explode('.', $nama)[0];

    if (isset($gelombangNama[$nama]) || isset($gelombangNama[$dasar])) {
        return 'Gelombang';
    }

    if ($nama === 'timeseries') {
        return 'Time Series';
    }

    return 'Peta';
}

/*
 * Membaca tanggal init dari path. Mendukung:
 * YYYYMMDD, YYYYMMDDHH, YYYY-MM-DD, YYYY_MM_DD, YYYY.MM.DD (nama folder),
 * folder bersarang 2026/10/06, dan tanggal di dalam nama file.
 * Mengembalikan YYYYMMDD atau '' jika tidak terbaca.
 */
function tanggalWms($relative)
{
    $path = str_replace('\\', '/', $relative);
    $segmen = explode('/', $path);
    array_pop($segmen);
    $nama = pathinfo($path, PATHINFO_FILENAME);

    $valid = function ($y, $m, $d) {
        $y = (int)$y;
        return $y >= 2000 && $y <= 2100 && checkdate((int)$m, (int)$d, $y);
    };

    for ($i = count($segmen) - 1; $i >= 0; $i--) {
        if (
            preg_match('/^(\d{4})(\d{2})(\d{2})(?:\d{2,4})?$/', $segmen[$i], $m) &&
            $valid($m[1], $m[2], $m[3])
        ) {
            return $m[1] . $m[2] . $m[3];
        }

        if (
            preg_match('/^(\d{4})[-_.](\d{2})[-_.](\d{2})/', $segmen[$i], $m) &&
            $valid($m[1], $m[2], $m[3])
        ) {
            return $m[1] . $m[2] . $m[3];
        }
    }

    for ($i = 0; $i + 2 < count($segmen); $i++) {
        if (
            preg_match('/^\d{4}$/', $segmen[$i]) &&
            preg_match('/^\d{1,2}$/', $segmen[$i + 1]) &&
            preg_match('/^\d{1,2}$/', $segmen[$i + 2]) &&
            $valid($segmen[$i], $segmen[$i + 1], $segmen[$i + 2])
        ) {
            return sprintf('%04d%02d%02d', $segmen[$i], $segmen[$i + 1], $segmen[$i + 2]);
        }
    }

    if (
        preg_match('/(?<!\d)(20\d{2})[-_.]?(\d{2})[-_.]?(\d{2})(?!\d)/', $nama, $m) &&
        $valid($m[1], $m[2], $m[3])
    ) {
        return $m[1] . $m[2] . $m[3];
    }

    return '';
}

/*
 * Membaca tanggal inisialisasi (init) dari path: tanggal pada SEGMEN PERTAMA
 * yang berbentuk tanggal, dimulai dari akhir nama folder.
 * Struktur InaWMS: map/OLR/{init}/{tanggal_valid}/file.png, sehingga init
 * berbeda dengan tanggal valid (prakiraan). Bila tidak ada tanggal init
 * terpisah, hasilnya sama dengan tanggalWms().
 * Mengembalikan YYYYMMDD atau '' jika tidak terbaca.
 */
function tanggalInitWms($relative)
{
    $path = str_replace('\\', '/', $relative);
    $segmen = explode('/', $path);
    array_pop($segmen); // buang nama file

    $valid = function ($y, $m, $d) {
        $y = (int)$y;
        return $y >= 2000 && $y <= 2100 && checkdate((int)$m, (int)$d, $y);
    };

    /* Init adalah tanggal pada segmen TERLUAR (pertama dari depan),
     * sedangkan tanggal valid dibaca dari segmen terdalam (belakang). */
    for ($i = 0; $i < count($segmen); $i++) {
        if (
            preg_match('/^(\d{4})(\d{2})(\d{2})(?:\d{2,4})?$/', $segmen[$i], $m) &&
            $valid($m[1], $m[2], $m[3])
        ) {
            return $m[1] . $m[2] . $m[3];
        }

        if (
            preg_match('/^(\d{4})[-_.](\d{2})[-_.](\d{2})/', $segmen[$i], $m) &&
            $valid($m[1], $m[2], $m[3])
        ) {
            return $m[1] . $m[2] . $m[3];
        }
    }

    for ($i = 0; $i + 2 < count($segmen); $i++) {
        if (
            preg_match('/^\d{4}$/', $segmen[$i]) &&
            preg_match('/^\d{1,2}$/', $segmen[$i + 1]) &&
            preg_match('/^\d{1,2}$/', $segmen[$i + 2]) &&
            $valid($segmen[$i], $segmen[$i + 1], $segmen[$i + 2])
        ) {
            return sprintf('%04d%02d%02d', $segmen[$i], $segmen[$i + 1], $segmen[$i + 2]);
        }
    }

    return tanggalWms($relative);
}

/* Kode sabuk lintang hanya untuk Hovmoller: olr.cfs.n24.png -> n24 */
function lintangWms($relative)
{
    $nama = strtolower(pathinfo($relative, PATHINFO_FILENAME));

    if (preg_match('/\.cfs\.([a-z0-9]+)$/', $nama, $m)) {
        return $m[1];
    }

    $pos = strrpos($nama, '.');

    return $pos === false ? $nama : substr($nama, $pos + 1);
}

function judulWms($g)
{
    global $gelombangNama;

    $nama = pathinfo($g['file'], PATHINFO_FILENAME);

    /* Panel multi-tanggal diberi penanda agar tidak sama judulnya dengan
     * peta tunggalnya, kecuali cabang yang sudah memakai nama file. */
    $penanda = stripos($nama, 'panel') !== false ? ' · Panel' : '';

    if ($g['jenis'] === 'Hovmoller') {
        return $g['variabel'] . ' Hovmoller';
    }

    if ($g['jenis'] === 'Gelombang') {
        $dasar = explode('.', strtolower($nama))[0];

        return $g['variabel'] . ' · ' .
            ($gelombangNama[$dasar] ?? $nama) . $penanda;
    }

    if ($g['jenis'] === 'Time Series') {
        return $g['variabel'] . ' Time Series';
    }

    $sisa = preg_replace('/^(olr|pwat)[._-]?/i', '', $nama);
    $sisa = trim(ucwords(str_replace(['.', '_', '-'], ' ', $sisa)));
    $sisa = str_ireplace('Cfs', 'CFS', $sisa);

    $label = $g['variabel'] . ' · ' . ($sisa !== '' ? $sisa : 'Peta');

    /* Versi dunia tidak punya penanda wilayah pada nama file. */
    if (
        $g['wilayah'] === 'Dunia' &&
        stripos($sisa, 'indonesia') === false &&
        stripos($sisa, 'dunia') === false
    ) {
        $label .= ' · Dunia';
    }

    return $label;
}

/* SCAN REKURSIF: hanya membaca file, tidak mengubah apa pun. */
function scanWms($assetsDir, $sumberFolder, $allowedExtensions, $lintangDef)
{
    $hasil = [];
    $kodeLintang = array_keys($lintangDef);

    foreach ($sumberFolder as $sub) {
        $folder = $assetsDir . '/' . $sub;

        if (!is_dir($folder)) {
            continue;
        }

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
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

                $relative = str_replace(
                    '\\',
                    '/',
                    substr($info->getPathname(), strlen($assetsDir) + 1)
                );

                /* Gambar tanpa tanggal terbaca tetap dimasukkan (tanggal kosong). */
                $ymd = tanggalWms($relative);
                $init = tanggalInitWms($relative);

                $jenis = jenisWms($relative);
                $lintang = '';
                $lintangUrut = 0;
                $lintangLabel = '';

                if ($jenis === 'Hovmoller') {
                    $lintang = lintangWms($relative);
                    $idx = array_search($lintang, $kodeLintang, true);
                    $lintangUrut = ($idx === false) ? 1000 : (int)$idx;
                    $lintangLabel = $lintangDef[$lintang] ?? strtoupper($lintang);
                }

                /* Peta produk baru disimpan dalam dua wilayah: dunia
                 * (tanpa akhiran) dan Indonesia (akhiran .indonesia). */
                $wilayah = '';

                if ($jenis === 'Peta' || $jenis === 'Gelombang') {
                    $namaFile = strtolower(pathinfo($relative, PATHINFO_FILENAME));
                    $wilayah = strpos($namaFile, 'indonesia') !== false
                        ? 'Indonesia'
                        : 'Dunia';
                }

                $namaProduk = pathinfo($relative, PATHINFO_FILENAME);

                /* Keluarga "N-day" (mis. olr.cfs.all.indonesia.1 sampai .10)
                 * adalah satu produk yang sama dengan rentang prakiraan
                 * berbeda, jadi digabung dalam satu seri animasi. */
                $keluarga = $namaProduk;
                $nomor = null;

                if (preg_match('/^(.*)\.(\d+)$/', $namaProduk, $m)) {
                    $keluarga = $m[1];
                    $nomor = (int)$m[2];
                }

                $hasil[] = [
                    'file' => $relative,
                    'tanggal' => $ymd,
                    'init' => $init,
                    'variabel' => variabelWms($relative),
                    'jenis' => $jenis,
                    'wilayah' => $wilayah,
                    'produk' => $namaProduk,
                    'keluarga' => $keluarga,
                    'nomor' => $nomor,
                    'render' => strpos($relative, 'render/') !== false,
                    'lintang' => $lintang,
                    'lintang_label' => $lintangLabel,
                    'lintang_urut' => $lintangUrut
                ];
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    return $hasil;
}

$folderAda = is_dir($assetsDir . '/map') || is_dir($assetsDir . '/hovmoller');
$gambarSemua = $folderAda
    ? scanWms($assetsDir, $sumberFolder, $allowedExtensions, $lintangDef)
    : [];

/*
 * Urutan tetap: inisialisasi terbaru, tanggal valid terlama (analisis ->
 * prakiraan hari-hari berikutnya), variabel, jenis, sabuk lintang
 * (utara->selatan), nama file natural.
 */
usort($gambarSemua, function ($a, $b) use ($variabelUrut, $jenisUrut) {
    $initA = $a['init'] !== '' ? $a['init'] : $a['tanggal'];
    $initB = $b['init'] !== '' ? $b['init'] : $b['tanggal'];

    if ($initA !== $initB) {
        return strcmp($initB, $initA);
    }

    if ($a['tanggal'] !== $b['tanggal']) {
        return strcmp($a['tanggal'], $b['tanggal']);
    }

    $va = array_search($a['variabel'], $variabelUrut, true);
    $vb = array_search($b['variabel'], $variabelUrut, true);

    if ($va !== $vb) {
        return $va <=> $vb;
    }

    $ja = array_search($a['jenis'], $jenisUrut, true);
    $jb = array_search($b['jenis'], $jenisUrut, true);

    if ($ja !== $jb) {
        return $ja <=> $jb;
    }

    if ($a['lintang_urut'] !== $b['lintang_urut']) {
        return $a['lintang_urut'] <=> $b['lintang_urut'];
    }

    return strnatcmp($a['file'], $b['file']);
});

/* ================= PILIHAN FILTER (dari gambar yang ada) ================= */

/* Dropdown tanggal diisi dari tanggal inisialisasi (init), bukan tanggal valid,
 * agar memilih tanggal init menampilkan seluruh prakiraan hari berikutnya. */
$tanggalSemuaInit = array_values(array_unique(array_filter(array_column($gambarSemua, 'init'))));
rsort($tanggalSemuaInit);

$jumlahTanpaTanggal = count(array_filter($gambarSemua, function ($g) {
    return $g['tanggal'] === '';
}));

$variabelAda = array_unique(array_column($gambarSemua, 'variabel'));
$jenisAda = array_unique(array_column($gambarSemua, 'jenis'));
$wilayahAda = array_values(array_unique(array_filter(array_column($gambarSemua, 'wilayah'))));

$variabelPilihan = array_values(array_intersect($variabelUrut, $variabelAda));
$jenisPilihan = array_values(array_intersect($jenisUrut, $jenisAda));
$wilayahPilihan = array_values(array_intersect(['Dunia', 'Indonesia'], $wilayahAda));

/* Sabuk lintang yang ada, diurutkan sesuai $lintangDef */
$lintangPilihan = [];

foreach ($gambarSemua as $g) {
    if ($g['lintang'] !== '' && !isset($lintangPilihan[$g['lintang']])) {
        $lintangPilihan[$g['lintang']] = [
            'label' => $g['lintang_label'],
            'urut' => $g['lintang_urut']
        ];
    }
}

uasort($lintangPilihan, function ($a, $b) {
    return $a['urut'] <=> $b['urut'];
});

$tahunDipilih = trim((string)($_GET['tahun'] ?? ''));
$bulanDipilih = trim((string)($_GET['bulan'] ?? ''));
$tanggalDipilih = trim((string)($_GET['tanggal'] ?? ''));
$jenisDipilih = trim((string)($_GET['jenis'] ?? ''));
$variabelDipilih = trim((string)($_GET['variabel'] ?? ''));
$lintangDipilih = trim((string)($_GET['lintang'] ?? ''));
$wilayahDipilih = trim((string)($_GET['wilayah'] ?? ''));
$kataKunci = trim((string)($_GET['q'] ?? ''));

if (!in_array($jenisDipilih, $jenisPilihan, true)) {
    $jenisDipilih = '';
}

if (!in_array($variabelDipilih, $variabelPilihan, true)) {
    $variabelDipilih = '';
}

if (!isset($lintangPilihan[$lintangDipilih])) {
    $lintangDipilih = '';
}

if (!in_array($wilayahDipilih, $wilayahPilihan, true)) {
    $wilayahDipilih = '';
}

/* Sabuk lintang hanya berlaku untuk Hovmoller */
if ($jenisDipilih !== '' && $jenisDipilih !== 'Hovmoller') {
    $lintangDipilih = '';
}

/*
 * Daftar tanggal dibangun DARI GAMBAR YANG COCOK dengan Jenis/Variabel/
 * Sabuk Lintang/Wilayah terpilih. Sebelumnya daftar ini memakai gabungan
 * seluruh produk, sehingga tanggal yang tidak punya data untuk kombinasi
 * tertentu tetap ditawarkan dan hasilnya kosong.
 */
$filterProduk = [
    'tahun' => '',
    'bulan' => '',
    'tanggal' => '',
    'jenis' => $jenisDipilih,
    'variabel' => $variabelDipilih,
    'lintang' => $lintangDipilih,
    'wilayah' => $wilayahDipilih,
    'kata' => $kataKunci
];

$tanggalAda = [];

foreach ($gambarSemua as $g) {
    if (!cocokWms($g, $filterProduk)) {
        continue;
    }

    $initG = $g['init'] !== '' ? $g['init'] : $g['tanggal'];

    if ($initG !== '') {
        $tanggalAda[$initG] = true;
    }
}

$tanggalAda = array_keys($tanggalAda);
rsort($tanggalAda);

/*
 * Dropdown tanggal, bulan, dan tahun hanya dibuat dari tanggal yang benar-benar
 * berhasil dibaca dari file gambar. Tidak ada tanggal kalender buatan.
 */
$opsiTahun = [];
$opsiBulan = [];
$opsiTanggal = [];
foreach ($tanggalAda as $d) {
    $opsiTahun[substr($d, 0, 4)] = true;
    $opsiBulan[substr($d, 4, 2)] = true;
    $opsiTanggal[substr($d, 6, 2)] = true;
}
$opsiTahun = array_map('strval', array_keys($opsiTahun));
$opsiBulan = array_map(function ($v) { return str_pad((string)$v, 2, '0', STR_PAD_LEFT); }, array_keys($opsiBulan));
$opsiTanggal = array_map(function ($v) { return str_pad((string)$v, 2, '0', STR_PAD_LEFT); }, array_keys($opsiTanggal));
rsort($opsiTahun);
sort($opsiBulan);
sort($opsiTanggal);

if (!in_array($tahunDipilih, $opsiTahun, true)) {
    $tahunDipilih = '';
}
if (!in_array($bulanDipilih, $opsiBulan, true)) {
    $bulanDipilih = '';
}
if (!in_array($tanggalDipilih, $opsiTanggal, true)) {
    $tanggalDipilih = '';
}

/* Tanggal init yang benar-benar ada, dikelompokkan per bulan (untuk chip pilihan cepat) */
$tanggalPerBulan = [];

foreach ($tanggalAda as $d) {
    $tanggalPerBulan[substr($d, 0, 6)][] = $d;  /* kunci bisa jadi integer, selalu di-cast saat dipakai */
}

/* Gambar hanya ditampilkan setelah Cari diklik dan minimal 1 filter dipilih. */
$cariDijalankan = isset($_GET['cari']);
$adaFilter = (
    $tahunDipilih !== '' || $bulanDipilih !== '' || $tanggalDipilih !== '' ||
    $jenisDipilih !== '' || $variabelDipilih !== '' || $lintangDipilih !== '' ||
    $wilayahDipilih !== '' || $kataKunci !== ''
);
$tampilkanHasil = $cariDijalankan && $adaFilter;

$filter = [
    'tahun' => $tahunDipilih,
    'bulan' => $bulanDipilih,
    'tanggal' => $tanggalDipilih,
    'jenis' => $jenisDipilih,
    'variabel' => $variabelDipilih,
    'lintang' => $lintangDipilih,
    'wilayah' => $wilayahDipilih,
    'kata' => $kataKunci
];

function cocokWms($g, $f)
{
    /* Filter tanggal (tahun/bulan/hari) berlaku pada tanggal inisialisasi,
     * sehingga satu pilihan init menampilkan semua tanggal validnya. */
    $ymd = (string)($g['init'] !== '' ? $g['init'] : $g['tanggal']);

    if ($f['tahun'] !== '' && substr($ymd, 0, 4) !== $f['tahun']) {
        return false;
    }

    if ($f['bulan'] !== '' && substr($ymd, 4, 2) !== $f['bulan']) {
        return false;
    }

    if ($f['tanggal'] !== '' && substr($ymd, 6, 2) !== $f['tanggal']) {
        return false;
    }

    if ($f['jenis'] !== '' && $g['jenis'] !== $f['jenis']) {
        return false;
    }

    if ($f['variabel'] !== '' && $g['variabel'] !== $f['variabel']) {
        return false;
    }

    if (isset($f['lintang']) && $f['lintang'] !== '' && $g['lintang'] !== $f['lintang']) {
        return false;
    }

    if (isset($f['wilayah']) && $f['wilayah'] !== '' && $g['wilayah'] !== $f['wilayah']) {
        return false;
    }

    /*
     * Pencarian produk yang terarah:
     * pilihan dropdown utama difilter berdasarkan metadata produk, bukan hanya
     * kecocokan teks mentah. Kata lain tetap dicari pada nama file/path dan label.
     */
    if (isset($f['kata']) && trim($f['kata']) !== '') {
        $kataAsli = trim((string)$f['kata']);
        $kata = strtolower($kataAsli);
        $fileLower = strtolower(str_replace('\\', '/', (string)($g['file'] ?? '')));
        $namaLower = strtolower(pathinfo($fileLower, PATHINFO_FILENAME));
        $variabel = strtoupper((string)($g['variabel'] ?? ''));
        $jenis = strtolower((string)($g['jenis'] ?? ''));
        $lintang = strtolower((string)($g['lintang'] ?? ''));
        $cocokKhusus = null;

        if ($kata === 'olr') {
            $cocokKhusus = ($variabel === 'OLR' || strpos($fileLower, 'olr') !== false || strpos($fileLower, 'orl') !== false);
        } elseif ($kata === 'pwat') {
            $cocokKhusus = ($variabel === 'PWAT' || strpos($fileLower, 'pwat') !== false);
        } elseif ($kata === 'hovmoller' || $kata === 'hov') {
            $cocokKhusus = ($jenis === 'hovmoller' || strpos($fileLower, 'hovmoller') !== false || strpos($fileLower, 'hov') !== false);
        } elseif ($kata === 'peta') {
            $cocokKhusus = ($jenis === 'peta');
        } elseif ($kata === 'gelombang') {
            $cocokKhusus = ($jenis === 'gelombang');
        } elseif ($kata === 'time series' || $kata === 'timeseries') {
            $cocokKhusus = ($jenis === 'time series' || strpos($namaLower, 'timeseries') !== false || strpos($namaLower, 'time.series') !== false);
        } elseif (in_array($kata, ['n24','n02','n46','n68','n810','s02','s24','s46','s68','s810','eqtr','eqtrs2n2','eqtrs1n1','north','north0','north1','north2','south','south0','south1','wide'], true)) {
            $cocokKhusus = ($lintang === $kata || strpos($namaLower, $kata) !== false || strpos($fileLower, '/' . $kata . '.') !== false);
        } elseif (in_array($kata, ['mjo','er','kelvin','low','mrg-td','mrgtd'], true)) {
            $alias = ($kata === 'mrg-td') ? 'mrgtd' : $kata;
            $cocokKhusus = (strpos(str_replace(['-','_','.',' '], '', $fileLower), $alias) !== false);
        }

        if ($cocokKhusus !== null) {
            if (!$cocokKhusus) {
                return false;
            }
        } else {
            $teksCari = implode(' ', [
                $g['file'] ?? '', $g['variabel'] ?? '', $g['jenis'] ?? '',
                $g['tanggal'] ?? '', $g['lintang'] ?? '',
                $g['lintang_label'] ?? '', judulWms($g)
            ]);
            $teksCari = strtolower($teksCari);
            $kataNormal = preg_replace('/[\s._-]+/', '', $kata);
            $teksNormal = preg_replace('/[\s._-]+/', '', $teksCari);
            if (strpos($teksCari, $kata) === false && strpos($teksNormal, $kataNormal) === false) {
                return false;
            }
        }
    }

    return true;
}

function hitungWms($semua, $f)
{
    $n = 0;

    foreach ($semua as $g) {
        if (cocokWms($g, $f)) {
            $n++;
        }
    }

    return $n;
}

$hasil = !$tampilkanHasil ? [] : array_values(array_filter(
    $gambarSemua,
    function ($g) use ($filter) {
        return cocokWms($g, $filter);
    }
));

/*
 * Pencarian harus selalu akurat: tidak ada fallback yang mengabaikan tanggal,
 * jenis, variabel, sabuk lintang, atau kata kunci. Jika kombinasi tidak cocok,
 * hasil tetap kosong dan diagnostik di bawah menjelaskan jumlah per filter.
 */
$filterDilonggarkan = [];
$modeHasilAlternatif = false;

/* Diagnostik tetap dihitung dari filter asli, supaya angka tidak menyesatkan. */
$diagnosa = [];
$tanggalTersediaUntukPilihan = [];
if ($tampilkanHasil && empty($hasil)) {
    $kosong = ['tahun' => '', 'bulan' => '', 'tanggal' => '', 'jenis' => '', 'variabel' => '', 'lintang' => '', 'wilayah' => '', 'kata' => ''];
    if ($tahunDipilih !== '' || $bulanDipilih !== '' || $tanggalDipilih !== '') {
        $diagnosa['Tanggal inisialisasi'] = hitungWms($gambarSemua, array_merge($kosong, ['tahun' => $tahunDipilih, 'bulan' => $bulanDipilih, 'tanggal' => $tanggalDipilih]));
    }
    if ($jenisDipilih !== '') {
        $diagnosa['Jenis'] = hitungWms($gambarSemua, array_merge($kosong, ['jenis' => $jenisDipilih]));
    }
    if ($variabelDipilih !== '') {
        $diagnosa['Variabel'] = hitungWms($gambarSemua, array_merge($kosong, ['variabel' => $variabelDipilih]));
    }
    if ($lintangDipilih !== '') {
        $diagnosa['Sabuk lintang'] = hitungWms($gambarSemua, array_merge($kosong, ['lintang' => $lintangDipilih]));
    }
    if ($wilayahDipilih !== '') {
        $diagnosa['Wilayah peta'] = hitungWms($gambarSemua, array_merge($kosong, ['wilayah' => $wilayahDipilih]));
    }
    $tanpaTanggal = array_merge($filter, ['tahun' => '', 'bulan' => '', 'tanggal' => '']);
    foreach ($gambarSemua as $g) {
        $initG = $g['init'] !== '' ? $g['init'] : $g['tanggal'];
        if ($initG !== '' && cocokWms($g, $tanpaTanggal)) {
            $tanggalTersediaUntukPilihan[$initG] = true;
        }
    }
    $tanggalTersediaUntukPilihan = array_keys($tanggalTersediaUntukPilihan);
    rsort($tanggalTersediaUntukPilihan);
}

$currentPage = basename($_SERVER['PHP_SELF']);

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
<title>InaWMS | BMKG Kemayoran</title>

<link rel="icon" type="image/png" href="assets/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root {
    --bg:#eef3f9;
    --surface:#ffffff;
    --line:rgba(35,70,110,0.208);
    --text:#16293f;
    --muted:#5c748c;
    --blue:#0f61bd;
    --cyan:#10a6bc;
    --green:#24a873;
    --shadow:0 16px 40px rgba(16,42,67,0.048);
}

* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body {
    min-height:100vh;
    font-family:Inter,Arial,sans-serif;
    color:var(--text);
    background:
        radial-gradient(ellipse at 8% 0%,rgba(37,140,255,.13),transparent 32%),
        radial-gradient(ellipse at 95% 20%,rgba(62,216,239,.07),transparent 30%),
        var(--bg);
}
a { color:inherit; text-decoration:none; }
button,select { font:inherit; }

/* KONTEN */
.main { width:min(1500px,100%); margin:0 auto; padding:30px 4% 55px; }
.hero {
    display:flex; justify-content:space-between; align-items:center; gap:20px;
    padding:34px; border:1px solid rgba(15,101,189,0.200);
    border-radius:25px;
    background:radial-gradient(circle at 85% 20%,rgba(62,216,239,.18),transparent 25%),linear-gradient(120deg,#f5f9fd,#ffffff 62%,#f5f9fd);
    box-shadow:var(--shadow);
}
.eyebrow {
    display:flex; align-items:center; gap:8px; margin-bottom:13px;
    color:#11659c; font-size:11px; font-weight:700; letter-spacing:1.5px;
    text-transform:uppercase;
}
.live-dot { width:7px; height:7px; border-radius:50%; background:var(--green); }
.hero h1 { font-family:Poppins,sans-serif; font-size:clamp(23px,3vw,34px); }
.hero p { margin-top:10px; color:#5c748c; font-size:13px; line-height:1.8; }
.hero-chip {
    display:flex; align-items:center; gap:10px; padding:13px 16px;
    border:1px solid rgba(23,52,86,0.055); border-radius:14px;
    color:#16293f; background:rgba(23,52,86,0.030);
    font-size:12px; white-space:nowrap;
}
.hero-chip i { color:var(--cyan); font-size:18px; }

.section-heading {
    display:flex; align-items:center; justify-content:space-between;
    gap:15px; margin:30px 0 16px;
}
.section-heading h2 { font-family:Poppins,sans-serif; font-size:17px; }
.section-heading p { margin-top:5px; color:var(--muted); font-size:12px; }
.section-tag {
    padding:8px 11px; border:1px solid var(--line); border-radius:10px;
    color:#23598a; background:rgba(23,52,86,0.030); font-size:10px;
}
.panel {
    margin-top:25px; padding:25px;
    border:1px solid var(--line); border-radius:23px;
    background:linear-gradient(145deg,rgba(255,255,255,0.94),rgba(255,255,255,0.94));
    box-shadow:var(--shadow);
}
.panel-heading { display:flex; align-items:center; gap:12px; margin-bottom:21px; }
.panel-title-icon {
    width:43px; height:43px; display:grid; place-items:center;
    border:1px solid rgba(62,216,239,.14); border-radius:13px;
    color:var(--cyan); background:rgba(62,216,239,.08);
}
.panel-heading h2 { font-family:Poppins,sans-serif; font-size:16px; }
.panel-heading p { margin-top:4px; color:var(--muted); font-size:11px; }
.filter-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:16px; }
.field label { display:block; margin-bottom:8px; color:#5c748c; font-size:11px; font-weight:700; }
.field select {
    width:100%; min-height:46px; padding:12px;
    border:1px solid #ced8e3; border-radius:11px;
    outline:none; color:#16293f; background:#eef3f9; font-size:12px;
}
.field select:focus, .field input:focus { border-color:var(--blue); }
.field select option { background:#eef3f9; color:#16293f; }
.form-actions { display:flex; gap:10px; flex-wrap:wrap; margin-top:18px; }
.btn {
    min-height:43px; display:inline-flex; align-items:center; justify-content:center;
    gap:8px; padding:11px 16px; border:1px solid rgba(37,140,255,.25);
    border-radius:11px; color:#16293f; background:rgba(37,140,255,.14);
    font-size:12px; font-weight:700; cursor:pointer;
}
.btn-secondary { border-color:var(--line); background:rgba(23,52,86,0.030); color:#22364e; }
.result-info {
    display:flex; flex-wrap:wrap; justify-content:space-between;
    gap:12px; margin:20px 0; color:#5c748c; font-size:12px;
}
.result-info strong { color:#16293f; }
.image-grid {
    display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
    gap:18px; margin-top:6px;
}
.image-grid .image-card { margin-bottom:0; }
.date-field { margin-bottom:16px; }
.date-selects { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; }
.field .hint { color:#5c748c; font-weight:400; }
.field select:disabled { opacity:.45; cursor:not-allowed; }
.image-grid { grid-template-columns:repeat(auto-fill,minmax(340px,1fr)); }

.avail { margin-top:20px; padding-top:16px; border-top:1px solid var(--line); }
.avail-title { color:#5c748c; font-size:11px; font-weight:700; margin-bottom:10px; }
.avail-row { display:flex; flex-wrap:wrap; align-items:center; gap:8px; margin-top:8px; }
.avail-month { min-width:130px; color:#295a84; font-size:11px; font-weight:700; }
.chip {
    padding:5px 11px; border:1px solid var(--line); border-radius:999px;
    color:#22364e; background:rgba(23,52,86,0.030);
    font-size:11px; font-weight:700; text-decoration:none;
}
.chip:hover { border-color:rgba(37,140,255,.5); color:#16293f; }
.chip.active { border-color:rgba(37,140,255,.6); background:rgba(37,140,255,.22); color:#16293f; }

/* PANEL INITIAL DAN FORECAST */
.comparison-grid {
    display:grid; grid-template-columns:repeat(2,minmax(0,1fr));
    gap:18px; margin-top:24px; align-items:start;
}
.product-panel {
    min-width:0; overflow:hidden;
    border:1px solid var(--line); border-radius:20px;
    background:linear-gradient(145deg,#ffffff,#eef3f9);
    box-shadow:var(--shadow);
}
.product-header {
    display:flex; align-items:center; gap:12px; padding:18px;
    border-bottom:1px solid var(--line); background:rgba(23,52,86,0.030);
}
.product-icon {
    width:40px; height:40px; display:grid; place-items:center;
    border-radius:12px; color:#11619c; background:rgba(37,140,255,.12);
}
.initial-panel .product-icon { color:#1c915e; background:rgba(66,214,155,.1); }
.product-header h2 { font-family:Poppins,sans-serif; font-size:15px; }
.product-header p { margin-top:5px; color:var(--muted); font-size:11px; }
.product-body { padding:16px; }
.image-card {
    overflow:hidden; border:1px solid var(--line);
    border-radius:14px; background:#eef3f9; margin-bottom:15px;
}
.image-card:last-child { margin-bottom:0; }
.image-wrap {
    min-height:180px; max-height:420px;
    display:grid; place-items:center; overflow:hidden;
    background:#ffffff;
}
.image-wrap img {
    display:block; width:100%; max-height:420px;
    object-fit:contain; cursor:zoom-in;
}
.image-meta { padding:14px; }
.image-name { color:#16293f; font-size:12px; font-weight:700; line-height:1.6; overflow-wrap:anywhere; }
.image-period { margin-top:7px; color:#295a84; font-size:10px; }
.image-description { margin-top:10px; color:#5c748c; font-size:11px; line-height:1.8; }
.file-name { margin-top:8px; color:#5c748c; font-size:10px; line-height:1.6; overflow-wrap:anywhere; }
.tag-render { color:#9c6511; }
.empty {
    padding:25px 18px; border:1px dashed #ced8e3; border-radius:14px;
    color:#5c748c; background:rgba(23,52,86,0.030);
    text-align:center; font-size:12px; line-height:1.9;
}
.empty i { display:block; margin-bottom:10px; color:#11569c; font-size:24px; }
.notice {
    margin-top:20px; padding:15px;
    border:1px solid rgba(189,140,15,0.250); border-radius:12px;
    color:#9c6e11; background:rgba(189,140,15,0.060);
    font-size:12px; line-height:1.8;
}
.footer {
    display:flex; align-items:center; justify-content:space-between;
    gap:15px; margin-top:30px; padding-top:20px;
    border-top:1px solid var(--line); color:#5c748c; font-size:11px; line-height:1.7;
}
.footer strong { color:#5c748c; }

/* MODAL GAMBAR */
.modal {
    position:fixed; inset:0; z-index:3000; display:none;
    align-items:center; justify-content:center; padding:25px;
    background:rgba(255,255,255,0.94); backdrop-filter:blur(12px);
}
.modal.show { display:flex; }
.modal img { max-width:95%; max-height:86vh; object-fit:contain; border-radius:13px; }
.modal-close {
    position:absolute; top:20px; right:25px; width:45px; height:45px;
    display:grid; place-items:center; border:1px solid rgba(23,52,86,0.083);
    border-radius:50%; color:#16293f; background:rgba(23,52,86,0.044);
    font-size:24px; cursor:pointer;
}

@media(max-width:1250px) {
    .main { padding-left:2.5%; padding-right:2.5%; }
}
@media(max-width:700px) {
    .main { padding-top:18px; padding-bottom:35px; }
    .hero { align-items:flex-start; flex-direction:column; padding:23px 20px; }
    .hero-chip { padding:10px 12px; }
    .panel { padding:17px; }
    .filter-grid,.comparison-grid { grid-template-columns:1fr; }
    .image-wrap { min-height:150px; }
    .footer { align-items:flex-start; flex-direction:column; }
}

.image-load-error{min-height:210px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:9px;padding:22px;text-align:center;color:#9c5f11;background:rgba(189,113,15,0.060);}
.image-load-error i{font-size:27px;}
.image-load-error span{font-size:13px;font-weight:700;}
.image-load-error small{font-size:11px;color:#22364e;}
.player {
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 18px;
}

.player-head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.player-head h2 {
    font-size: 16px;
    margin: 0;
    margin-right: auto;
}

.player-head select {
    background: var(--bg);
    color: var(--text);
    border: 1px solid var(--line);
    border-radius: 10px;
    padding: 8px 10px;
    font-size: 13px;
    max-width: 460px;
}

.player-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.player-controls button {
    width: 38px;
    height: 34px;
    border-radius: 10px;
    border: 1px solid var(--line);
    background: var(--bg);
    color: var(--text);
    cursor: pointer;
}

.player-controls button:hover {
    border-color: #ced5e3;
    color: #16293f;
}

.player-stage {
    text-align: center;
}

/* Gambar animasi dibuat ringkas: tinggi dibatasi dan lebar mengikuti
   aspek rasio gambar, tidak lagi melebar penuh sampai memakan halaman. */
.player-stage img {
    max-width: 100%;
    width: auto;
    max-height: 42vh;
    border-radius: 10px;
    background: #fff;
    cursor: zoom-in;
}

.player-caption {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
    margin-top: 8px;
    font-size: 11.5px;
    color: var(--muted);
}

.player-caption span:not(:empty) {
    background: var(--bg);
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: 2px 9px;
}

.player-caption .caption-produk {
    border-color: rgba(15,75,189,0.450);
    color: #16293f;
}

@media (max-width: 720px) {
    .player-stage img {
        max-height: 34vh;
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
            <div class="eyebrow"><span class="live-dot"></span> Produk Analisis Atmosfer</div>
            <h1>Pemantauan InaWMS</h1>
            <p>Telusuri koleksi produk InaWMS berdasarkan nama produk, tanggal init, jenis, variabel, dan sabuk lintang.</p>
        </div>
        <div class="hero-chip">
            <i class="fa-solid fa-water"></i>
            <span>Atmospheric Wave Products<br><strong>InaWMS</strong></span>
        </div>
    </section>

    <?php if (!$folderAda): ?>
        <div class="notice">
            Folder <strong>assets/map/</strong> dan <strong>assets/hovmoller/</strong> tidak ditemukan.
            Pastikan folder <strong>assets</strong> sejajar dengan file inawms.php.
        </div>
    <?php elseif (empty($gambarSemua)): ?>
        <div class="notice">
            Folder ditemukan, tetapi tidak ada gambar dengan folder tanggal init (format YYYYMMDD).
            Contoh: <strong>assets/map/OLR/20261006/</strong>.
        </div>
    <?php endif; ?>

    <section>
        <div class="section-heading">
            <div>
                <h2>Pencarian Produk InaWMS</h2>
                <p>Atur parameter pencarian, lalu klik Cari. Hasil hanya menampilkan gambar yang sesuai dengan pilihan aktif.</p>
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
                    <h2>Tanggal Init dan Produk</h2>
                    <p>Cari dengan kata kunci saja juga bisa, atau gabungkan dengan filter.</p>
                </div>
            </div>

            <form method="get" action="inawms.php" id="formWms">
                <div class="field date-field">
                    <label for="q">Pilih nama atau isi gambar</label>
                    <select name="q" id="q" aria-label="Pilih kata kunci gambar"
                            style="width:100%;min-height:46px;padding:12px;border:1px solid #ced8e3;border-radius:11px;outline:none;color:#16293f;background:#eef3f9;font-size:12px;">
                        <option value="" <?= $kataKunci === '' ? 'selected' : '' ?>>Semua Gambar (tanpa kata kunci)</option>
                        <?php
                        $pilihanKataGambar = [
                            'OLR' => 'OLR — Peta radiasi gelombang panjang',
                            'PWAT' => 'PWAT — Kandungan uap air',
                            'Hovmoller' => 'Hovmoller — Diagram waktu dan lintang',
                            'n24' => 'n24 — Sabuk lintang 2°N–4°N',
                            'MJO' => 'MJO — Gelombang Madden-Julian',
                            'ER' => 'ER — Equatorial Rossby',
                            'Kelvin' => 'Kelvin — Gelombang Kelvin',
                            'MRG-TD' => 'MRG-TD — Gelombang atmosfer',
                            'Peta' => 'Peta — Produk peta atmosfer',
                            'Gelombang' => 'Gelombang — Produk gelombang atmosfer',
                            'Time Series' => 'Time Series — Deret waktu',
                            'north' => 'North — Sabuk lintang utara',
                            'south' => 'South — Sabuk lintang selatan',
                        ];
                        ?>
                        <?php foreach ($pilihanKataGambar as $nilaiKata => $labelKata): ?>
                            <option value="<?= h($nilaiKata) ?>" <?= strcasecmp($kataKunci, $nilaiKata) === 0 ? 'selected' : '' ?>>
                                <?= h($labelKata) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($kataKunci !== '' && !in_array(strtolower($kataKunci), array_map('strtolower', array_keys($pilihanKataGambar)), true)): ?>
                            <option value="<?= h($kataKunci) ?>" selected><?= h($kataKunci) ?> — kata kunci sebelumnya</option>
                        <?php endif; ?>
                    </select>
                    <small class="hint">Tinggal pilih OLR, PWAT, Hovmoller, n24, MJO, atau pilihan lain. Tidak perlu mengetik.</small>
                </div>
                <div class="field date-field">
                    <label for="tanggal">Tanggal Init</label>
                    <div class="date-selects">
                        <select name="tanggal" id="tanggal" aria-label="Tanggal">
                            <option value="">Semua Tanggal</option>
                            <?php foreach ($opsiTanggal as $d): ?>
                                <option value="<?= h($d) ?>" <?= $tanggalDipilih === $d ? 'selected' : '' ?>>
                                    <?= h($d) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="bulan" id="bulan" aria-label="Bulan">
                            <option value="">Semua Bulan</option>
                            <?php foreach ($opsiBulan as $b): ?>
                                <option value="<?= h($b) ?>" <?= $bulanDipilih === $b ? 'selected' : '' ?>>
                                    <?= h($namaBulan[$b] ?? $b) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <select name="tahun" id="tahun" aria-label="Tahun">
                            <option value="">Semua Tahun</option>
                            <?php foreach ($opsiTahun as $t): ?>
                                <option value="<?= h($t) ?>" <?= $tahunDipilih === $t ? 'selected' : '' ?>>
                                    <?= h($t) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filter-grid">
                    <div class="field">
                        <label for="jenis">Jenis</label>
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
                        <label for="lintang" title="Sabuk Lintang = jalur lintang (latitude band) yang dirata-ratakan pada diagram Hovmoller, misal 2°S - 4°S">
                            Sabuk Lintang <span class="hint">(khusus Hovmoller)</span>
                        </label>
                        <select name="lintang" id="lintang">
                            <option value="">Semua Sabuk Lintang</option>
                            <?php foreach ($lintangPilihan as $kode => $info): ?>
                                <option value="<?= h($kode) ?>" <?= $lintangDipilih === $kode ? 'selected' : '' ?>>
                                    <?= h($info['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="wilayah" title="Wilayah tampilan peta: Dunia (seluruh bujur, 40°LS-40°LU) atau Indonesia (94°-141°BT)">
                            Wilayah Peta <span class="hint">(khusus Peta/Gelombang)</span>
                        </label>
                        <select name="wilayah" id="wilayah">
                            <option value="">Semua Wilayah</option>
                            <?php foreach ($wilayahPilihan as $w): ?>
                                <option value="<?= h($w) ?>" <?= $wilayahDipilih === $w ? 'selected' : '' ?>>
                                    <?= h($w) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="btn" type="submit" name="cari" value="1">
                        <i class="fa-solid fa-magnifying-glass"></i> Cari
                    </button>
                    <a class="btn btn-secondary" href="inawms.php">
                        <i class="fa-solid fa-rotate-left"></i> Reset
                    </a>
                </div>
            </form>

            <?php if (!empty($tanggalPerBulan)): ?>
                <div class="avail">
                    <div class="avail-title">Tanggal init yang tersedia (klik untuk langsung mencari)</div>
                    <?php foreach ($tanggalPerBulan as $ym => $daftar): ?>
                        <div class="avail-row">
                            <span class="avail-month"><?= h(($namaBulan[substr((string)$ym, 4, 2)] ?? substr((string)$ym, 4, 2)) . ' ' . substr((string)$ym, 0, 4)) ?></span>
                            <?php foreach ($daftar as $d): ?>
                                <?php
                                $q = [
                                    'tahun' => substr($d, 0, 4),
                                    'bulan' => substr($d, 4, 2),
                                    'tanggal' => substr($d, 6, 2),
                                    'jenis' => $jenisDipilih,
                                    'variabel' => $variabelDipilih,
                                    'lintang' => $lintangDipilih,
                                    'wilayah' => $wilayahDipilih,
                                    'cari' => '1'
                                ];
                                $aktif = (
                                    $tahunDipilih === $q['tahun'] &&
                                    $bulanDipilih === $q['bulan'] &&
                                    $tanggalDipilih === $q['tanggal']
                                );
                                ?>
                                <a class="chip <?= $aktif ? 'active' : '' ?>" href="inawms.php?<?= h(http_build_query($q)) ?>">
                                    <?= h(substr($d, 6, 2)) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$tampilkanHasil): ?>
        <div class="empty" style="margin-top:24px">
            <i class="fa-solid fa-filter"></i>
            <?php if ($cariDijalankan && !$adaFilter): ?>
                Ketik kata kunci atau pilih minimal satu filter, lalu klik <strong>Cari</strong>.
            <?php else: ?>
                Ketik nama/jenis gambar (contoh: OLR, PWAT, Hovmoller, n24, atau tanggal), atau pilih filter lalu klik <strong>Cari</strong>.
            <?php endif; ?>
            <br>
            Koleksi yang berhasil dipindai: <strong><?= count($gambarSemua) ?> gambar</strong>.
            <?php if ($jumlahTanpaTanggal > 0): ?>
                <br><?= $jumlahTanpaTanggal ?> gambar tidak punya tanggal init yang terbaca dari nama folder/file,
                jadi hanya muncul jika filter tanggal dikosongkan.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="result-info">
            <span>Ditemukan <strong><?= count($hasil) ?></strong> gambar</span>
        </div>

        <?php if (!empty($hasil)): ?>
            <div class="notice" style="margin-bottom:14px">
                <strong><i class="fa-solid fa-circle-check"></i> Hasil pencarian sesuai kriteria.</strong>
                Sistem menemukan <?= count($hasil) ?> gambar yang cocok dengan seluruh filter yang dipilih, termasuk tanggal init.
            </div>

            <?php
            /*
             * Seri animasi: satu seri berisi SATU produk saja (variabel, jenis,
             * wilayah, produk pada nama file, sabuk lintang) sehingga peta dan
             * diagram Hovmoller tidak pernah tercampur dalam satu animasi.
             * Frame diurutkan per tanggal inisialisasi lalu per tanggal
             * prakiraan, jadi animasi berjalan maju mengikuti forecast.
             */
            $seriAnimasi = [];

            foreach ($hasil as $g) {
                if ($g['tanggal'] === '' || $g['init'] === '') {
                    continue;
                }

                $kunci = implode('|', [
                    $g['variabel'],
                    $g['jenis'],
                    $g['wilayah'],
                    $g['keluarga'],
                    $g['lintang'],
                    $g['render'] ? 'render' : 'resmi'
                ]);

                /* Label seri memakai nama keluarga tanpa akhiran angka,
                 * jadi "olr.cfs.all.indonesia.1" cukup "OLR · CFS All Indonesia". */
                $gSeri = $g;
                if ($g['nomor'] !== null) {
                    $posisi = strrpos($g['file'], '/');
                    $gSeri['file'] =
                        ($posisi === false ? '' : substr($g['file'], 0, $posisi + 1)) .
                        $g['keluarga'] . '.' . pathinfo($g['file'], PATHINFO_EXTENSION);
                }

                $labelSeri = judulWms($gSeri);

                /* judulWms tidak membawa wilayah untuk produk gelombang. */
                if (
                    $g['wilayah'] !== '' &&
                    stripos($labelSeri, $g['wilayah']) === false
                ) {
                    $labelSeri .= ' · ' . $g['wilayah'];
                }

                if ($g['lintang_label'] !== '') {
                    $labelSeri .= ' · ' . $g['lintang_label'];
                }

                if ($g['render']) {
                    $labelSeri .= ' · Hasil Render';
                }

                /* Frame tetap menunjukkan nomor rentang prakiraannya. */
                $label = $labelSeri . ($g['nomor'] === null ? '' : ' · ' . $g['nomor'] . '-day');

                /* Hovmoller hanya punya satu tanggal (inisial). Menampilkan
                 * "Forecast" yang sama dengan inisial hanya menyesatkan. */
                $hanyaInisial = ($g['jenis'] === 'Hovmoller');

                $seriAnimasi[$kunci]['label'] = $labelSeri;
                $seriAnimasi[$kunci]['frame'][] = [
                    'src' => urlWms($g['file']),
                    'produk' => $label,
                    'inisial' => formatTanggalInit($g['init']),
                    'forecast' => $hanyaInisial ? '' : formatTanggalInit($g['tanggal']),
                    'lead' => $hanyaInisial ? '' : leadLabelWms($g['init'], $g['tanggal']),
                    'urut' => $g['init'] . $g['tanggal'] . sprintf('%03d', $g['nomor'] ?? 0)
                ];
            }

            foreach (array_keys($seriAnimasi) as $kunci) {
                usort($seriAnimasi[$kunci]['frame'], function ($a, $b) {
                    return strcmp($a['urut'], $b['urut']);
                });

                if (count($seriAnimasi[$kunci]['frame']) < 2) {
                    unset($seriAnimasi[$kunci]);
                }
            }

            $dataAnimasi = json_encode($seriAnimasi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            ?>

            <?php if (!empty($seriAnimasi)): ?>
                <section class="player" id="animasiPanel">
                    <div class="player-head">
                        <h2><i class="fa-solid fa-film"></i> Animasi Forecast</h2>
                        <select id="animasiSeri" aria-label="Pilih seri animasi">
                            <?php foreach ($seriAnimasi as $kunci => $seri): ?>
                                <option value="<?= h($kunci) ?>"><?= h($seri['label']) ?> (<?= count($seri['frame']) ?> frame)</option>
                            <?php endforeach; ?>
                        </select>
                        <div class="player-controls">
                            <button type="button" id="animasiPrev" title="Frame sebelumnya"><i class="fa-solid fa-backward-step"></i></button>
                            <button type="button" id="animasiPlay" title="Putar / hentikan"><i class="fa-solid fa-play"></i></button>
                            <button type="button" id="animasiNext" title="Frame berikutnya"><i class="fa-solid fa-forward-step"></i></button>
                            <button type="button" id="animasiReset" title="Kembali ke frame pertama"><i class="fa-solid fa-rotate-left"></i></button>
                            <select id="animasiKecepatan" aria-label="Kecepatan animasi" title="Jeda antar frame">
                                <option value="3500">Perlahan</option>
                                <option value="2000" selected>Normal</option>
                                <option value="900">Cepat</option>
                            </select>
                        </div>
                    </div>

                    <div class="player-stage">
                        <img id="animasiImage" src="" alt="Animasi produk INA-OWS"
                             onclick="bukaModalAnimasi(this.src, this.alt)">
                        <div class="player-caption">
                            <span id="animasiProduk" class="caption-produk"></span>
                            <span id="animasiInisial"></span>
                            <span id="animasiForecast"></span>
                            <span id="animasiLead"></span>
                            <span id="animasiPosisi"></span>
                        </div>
                    </div>
                    <small class="hint">Satu seri = satu produk. Frame diurutkan per tanggal Initial, lalu per tanggal forecast; panel berakhiran angka (1&ndash;10 day) digabung dalam satu seri dan ditandai pada keterangan. Klik gambar untuk memperbesar.</small>
                    <small class="hint">
                        <strong>Cara membaca angkanya:</strong> angka pada bar warna (misalnya 40 dan &minus;20) adalah nilai
                        dalam satuan yang tertulis di samping bar tersebut, bukan suhu dan bukan curah hujan.
                        Untuk OLR dan peta gelombang satuannya W m<sup>&minus;2</sup>; nilai negatif menandakan konveksi
                        (awan dan hujan) lebih aktif dari rata-ratanya, nilai positif menandakan konveksi tertekan.
                        Untuk PWAT satuannya kg m<sup>&minus;2</sup>; nilai positif berarti atmosfer lebih lembap.
                        Skala warna dikunci pada rentang minimum&ndash;maksimum data satu variabel, jadi warna antar frame
                        animasi bisa dibandingkan langsung.
                    </small>
                </section>

                <script id="animasiData" type="application/json"><?= $dataAnimasi ?></script>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (empty($hasil)): ?>
            <div class="empty">
                <i class="fa-regular fa-image"></i>
                Tidak ditemukan gambar yang memenuhi seluruh kriteria pencarian. Sistem tidak akan mengganti atau mengabaikan filter secara otomatis agar hasil tetap akurat.
                <br>Silakan pilih tanggal yang tersedia, ubah salah satu kriteria, atau tekan <strong>Reset</strong> untuk memulai pencarian baru.
            </div>

            <div class="notice" style="margin-top:14px">
                <strong>Kenapa kosong?</strong> Jumlah gambar yang cocok untuk tiap filter secara terpisah:
                <ul style="margin:8px 0 0 18px">
                    <?php foreach ($diagnosa as $nama => $n): ?>
                        <li><?= h($nama) ?>: <strong><?= (int)$n ?></strong> gambar</li>
                    <?php endforeach; ?>
                </ul>
                <?php if (!empty($tanggalTersediaUntukPilihan)): ?>
                    <div style="margin-top:8px">
                        Tanggal init yang ada untuk kombinasi Jenis/Variabel/Sabuk Lintang ini:
                        <strong><?= h(implode(', ', array_map('formatTanggalInit', array_slice($tanggalTersediaUntukPilihan, 0, 30)))) ?></strong>
                    </div>
                <?php elseif ($jenisDipilih !== '' || $variabelDipilih !== '' || $lintangDipilih !== ''): ?>
                    <div style="margin-top:8px">
                        Tidak ada gambar untuk kombinasi Jenis/Variabel/Sabuk Lintang ini di tanggal mana pun.
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="image-grid">
                <?php foreach ($hasil as $g): ?>
                    <article class="image-card">
                        <div class="image-wrap">
                            <img
                                src="<?= h(urlWms($g['file'])) ?>"
                                alt="<?= h(judulWms($g)) ?>"
                                loading="lazy"
                                data-file="<?= h($g['file']) ?>"
                                onclick="openImageModal(this.src,this.alt)"
                                onerror="this.onerror=null;const card=this.closest('.image-card');const wrap=this.closest('.image-wrap');if(wrap){wrap.innerHTML='<div class=&quot;image-load-error&quot;><i class=&quot;fa-regular fa-image&quot;></i><span>Gambar gagal dimuat</span><small>Periksa alamat dan lokasi file.</small></div>';}if(card){const info=card.querySelector('.image-error');if(info){info.hidden=false;info.textContent='File: '+this.dataset.file+' — browser tidak berhasil memuat gambar dari URL yang dibuat.';} }"
                            >
                        </div>
                        <div class="image-meta">
                            <div class="image-name"><?= h(judulWms($g)) ?></div>
                            <div class="image-period">
                                <i class="fa-regular fa-calendar"></i>
                                <?php if ($g['init'] !== ''): ?>
                                    <span title="Tanggal model dimulai dari kondisi awal (initial condition)">
                                        Initial: <?= h(formatTanggalInit($g['init'])) ?>
                                    </span>
                                    <?php if ($g['tanggal'] !== '' && $g['tanggal'] !== $g['init']): ?>
                                        ·
                                        <span title="Tanggal yang diprakirakan oleh Initial ini">
                                            Forecast: <?= h(formatTanggalInit($g['tanggal'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php $leadLabel = leadLabelWms($g['init'], $g['tanggal']); ?>
                                    <?php if ($leadLabel !== ''): ?>
                                        · <span title="Jarak waktu prakiraan terhadap tanggal inisialisasi"><?= h($leadLabel) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Tanggal tidak terbaca
                                <?php endif; ?>
                                · <?= h($g['jenis']) ?>
                                <?php if ($g['wilayah'] !== ''): ?>
                                    · <span title="Wilayah peta yang ditampilkan"><?= h($g['wilayah']) ?></span>
                                <?php endif; ?>
                                <?php if ($g['lintang_label'] !== ''): ?>
                                    · <span title="Sabuk lintang (zona latitude) yang dirata-ratakan pada diagram Hovmoller"><?= h($g['lintang_label']) ?></span>
                                <?php endif; ?>
                                <?php if (!empty($g['render'])): ?>
                                    · <span class="tag-render" title="Diagram ini dibuat ulang oleh proses_hovmoller.py dari file NetCDF CFS, bukan gambar unduhan resmi InAWMS.">Hasil Render</span>
                                <?php endif; ?>
                            </div>
                            <div class="file-name"><?= h($g['file']) ?></div>
                            <div class="image-error notice" hidden>Gambar gagal dimuat. Periksa path file dan nama folder.</div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <footer class="footer">
        <div><strong>BMKG KEMAYORAN</strong><br>Sistem Pemantauan Produk Atmosfer</div>
        <div>InaWMS · Koleksi gambar lokal</div>
    </footer>
</main>

<div class="modal" id="imageModal" role="dialog" aria-modal="true"
     aria-label="Pratinjau gambar" onclick="closeImageModal()">
    <button class="modal-close" type="button" aria-label="Tutup gambar"
            onclick="closeImageModal()">
        <i class="fa-solid fa-xmark"></i>
    </button>
    <img id="modalImage" src="" alt="Pratinjau gambar">
</div>

<script>

function openImageModal(src, alt) {
    const modal = document.getElementById('imageModal');
    const image = document.getElementById('modalImage');

    image.src = src;
    image.alt = alt || 'Pratinjau gambar';
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeImageModal() {
    document.getElementById('imageModal').classList.remove('show');
    document.getElementById('modalImage').src = '';
    document.body.style.overflow = '';
}

document.getElementById('modalImage').addEventListener('click', function (event) {
    event.stopPropagation();
});
</script>

<script>
(function () {
    const selHari = document.getElementById('tanggal');
    const selBulan = document.getElementById('bulan');
    const selTahun = document.getElementById('tahun');
    const selJenis = document.getElementById('jenis');
    const selVariabel = document.getElementById('variabel');
    const selLintang = document.getElementById('lintang');
    const selWilayah = document.getElementById('wilayah');

    // Semua pilihan dibangun dari metadata gambar yang benar-benar ditemukan PHP.
    // Tidak membuat tanggal kalender sendiri dan tidak mengganti hasil pencarian.
    const gambarMetadata = <?= json_encode(array_map(function ($g) {
        return [
            'tanggal' => (string)$g['tanggal'],
            'init' => (string)($g['init'] ?? ''),
            'jenis' => (string)$g['jenis'],
            'variabel' => (string)$g['variabel'],
            'wilayah' => (string)($g['wilayah'] ?? ''),
            'lintang' => (string)$g['lintang'],
            'lintang_label' => (string)$g['lintang_label'],
            'lintang_urut' => (int)$g['lintang_urut']
        ];
    }, $gambarSemua), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const namaBulan = <?= json_encode($namaBulan, JSON_UNESCAPED_UNICODE) ?>;

    function isiSelect(select, placeholder, values, selectedValue, labeler) {
        select.innerHTML = '';
        const awal = document.createElement('option');
        awal.value = '';
        awal.textContent = placeholder;
        select.appendChild(awal);
        values.forEach(value => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = labeler ? labeler(value) : value;
            select.appendChild(option);
        });
        select.value = values.includes(selectedValue) ? selectedValue : '';
    }

    function initGambar(g) {
        return g.init || g.tanggal;
    }

    function gambarSesuaiProduk() {
        const jenis = selJenis.value;
        const variabel = selVariabel.value;
        const wilayah = selWilayah ? selWilayah.value : '';
        const lintang = selLintang.value;
        return gambarMetadata.filter(g => {
            if (!initGambar(g)) return false;
            if (jenis && g.jenis !== jenis) return false;
            if (variabel && g.variabel !== variabel) return false;
            if (wilayah && g.wilayah !== wilayah) return false;
            if (lintang && g.lintang !== lintang) return false;
            return true;
        });
    }

    function updateTanggal(resetTidakValid) {
        const tahunSebelum = selTahun.value;
        const bulanSebelum = selBulan.value;
        const hariSebelum = selHari.value;
        const gambarProduk = gambarSesuaiProduk();
        // Opsi tanggal dibangun dari tanggal inisialisasi agar satu pilihan
        // init menampilkan seluruh tanggal valid (prakiraan) di dalamnya.
        const semuaTanggal = [...new Set(gambarProduk.map(initGambar))].sort().reverse();

        const tahunTersedia = [...new Set(semuaTanggal.map(d => d.slice(0, 4)))].sort().reverse();
        const tanggalUntukTahun = semuaTanggal.filter(d => !tahunSebelum || d.slice(0, 4) === tahunSebelum);
        const bulanTersedia = [...new Set(tanggalUntukTahun.map(d => d.slice(4, 6)))].sort();
        const tanggalUntukBulan = semuaTanggal.filter(d =>
            (!tahunSebelum || d.slice(0, 4) === tahunSebelum) &&
            (!bulanSebelum || d.slice(4, 6) === bulanSebelum)
        );
        const hariTersedia = [...new Set(tanggalUntukBulan.map(d => d.slice(6, 8)))].sort();

        isiSelect(selTahun, 'Semua Tahun', tahunTersedia, tahunSebelum);
        // Hitung ulang bulan setelah opsi tahun tersedia.
        const tahunAktif = selTahun.value;
        const bulanAktifTersedia = [...new Set(semuaTanggal
            .filter(d => !tahunAktif || d.slice(0, 4) === tahunAktif)
            .map(d => d.slice(4, 6)))].sort();
        isiSelect(selBulan, 'Semua Bulan', bulanAktifTersedia, bulanSebelum, b => namaBulan[b] || b);

        const tahunFinal = selTahun.value;
        const bulanFinal = selBulan.value;
        const hariAktifTersedia = [...new Set(semuaTanggal.filter(d =>
            (!tahunFinal || d.slice(0, 4) === tahunFinal) &&
            (!bulanFinal || d.slice(4, 6) === bulanFinal)
        ).map(d => d.slice(6, 8)))].sort();
        isiSelect(selHari, 'Semua Tanggal', hariAktifTersedia, hariSebelum);

        // Jika kombinasi tanggal lama tidak tersedia pada produk yang dipilih, kosongkan.
        if (resetTidakValid) {
            if (bulanSebelum && !bulanAktifTersedia.includes(bulanSebelum)) selBulan.value = '';
            if (hariSebelum && !hariAktifTersedia.includes(hariSebelum)) selHari.value = '';
        }
    }

    function updateLintang() {
        const jenis = selJenis.value;
        const variabel = selVariabel.value;
        const tahun = selTahun.value;
        const bulan = selBulan.value;
        const hari = selHari.value;
        const wilayah = selWilayah ? selWilayah.value : '';
        const cocok = gambarMetadata.filter(g => {
            if (g.jenis !== 'Hovmoller' || !g.lintang || !initGambar(g)) return false;
            if (jenis && g.jenis !== jenis) return false;
            if (variabel && g.variabel !== variabel) return false;
            if (wilayah && g.wilayah !== wilayah) return false;
            if (tahun && initGambar(g).slice(0, 4) !== tahun) return false;
            if (bulan && initGambar(g).slice(4, 6) !== bulan) return false;
            if (hari && initGambar(g).slice(6, 8) !== hari) return false;
            return true;
        });
        const opsi = new Map();
        cocok.forEach(g => opsi.set(g.lintang, {
            label: g.lintang_label || g.lintang,
            urut: g.lintang_urut
        }));
        const values = [...opsi.keys()].sort((a, b) =>
            (opsi.get(a).urut - opsi.get(b).urut) || a.localeCompare(b)
        );
        const sebelumnya = selLintang.value;
        isiSelect(selLintang, 'Semua Sabuk Lintang', values, sebelumnya, k => opsi.get(k).label);
        const bukanHov = jenis !== '' && jenis !== 'Hovmoller';
        selLintang.disabled = bukanHov || values.length === 0;
        if (bukanHov) selLintang.value = '';
    }

    function updateWilayah() {
        if (!selWilayah) return;
        const jenis = selJenis.value;
        // Penanda wilayah hanya dikenal pada produk peta dan gelombang.
        const boleh = jenis === '' || jenis === 'Peta' || jenis === 'Gelombang';
        selWilayah.disabled = !boleh;
        if (!boleh) selWilayah.value = '';
    }

    function perbaruiSemua(resetTidakValid) {
        updateWilayah();
        updateTanggal(resetTidakValid);
        updateLintang();
    }

    [selTahun, selBulan, selHari].forEach(el => el.addEventListener('change', () => perbaruiSemua(true)));
    [selJenis, selVariabel].forEach(el => el.addEventListener('change', () => perbaruiSemua(true)));
    if (selWilayah) selWilayah.addEventListener('change', () => perbaruiSemua(true));

    perbaruiSemua(false);
})();
</script>

<script>
(function () {
    const sumber = document.getElementById('animasiData');
    const panel = document.getElementById('animasiPanel');

    if (!sumber || !panel) return;

    const seri = JSON.parse(sumber.textContent || '{}');
    const pilihSeri = document.getElementById('animasiSeri');
    const gambar = document.getElementById('animasiImage');
    const tombolPlay = document.getElementById('animasiPlay');
    const tombolPrev = document.getElementById('animasiPrev');
    const tombolNext = document.getElementById('animasiNext');
    const tombolReset = document.getElementById('animasiReset');
    const pilihKecepatan = document.getElementById('animasiKecepatan');
    const teksProduk = document.getElementById('animasiProduk');
    const teksInisial = document.getElementById('animasiInisial');
    const teksForecast = document.getElementById('animasiForecast');
    const teksLead = document.getElementById('animasiLead');
    const teksPosisi = document.getElementById('animasiPosisi');

    let indeks = 0;
    let timer = null;
    const sudahDipratinjau = {};

    /* Seluruh frame pada seri yang sedang dipilih disiapkan lebih dulu.
     * Tanpa ini browser men-decode gambar tepat saat src diganti, sehingga
     * animasi terlihat macet, apalagi ketika gambar sedang diperbesar. */
    function pratinjau() {
        frameAktif().forEach(function (frame) {
            if (sudahDipratinjau[frame.src]) {
                return;
            }

            const bayangan = new Image();
            bayangan.src = frame.src;
            sudahDipratinjau[frame.src] = bayangan;
        });
    }

    function frameAktif() {
        return seri[pilihSeri.value] ? seri[pilihSeri.value].frame : [];
    }

    function tampilkan() {
        const daftar = frameAktif();

        if (!daftar.length) return;

        indeks = Math.max(0, Math.min(indeks, daftar.length - 1));

        const frame = daftar[indeks];

        gambar.src = frame.src;
        gambar.alt = (frame.produk || 'Produk INA-OWS')
            + ', Initial ' + frame.inisial
            + (frame.forecast ? ', Forecast ' + frame.forecast : '');

        /* Label caption selalu mengikuti gambar pada frame ini.
           Kolom kosong otomatis disembunyikan oleh :not(:empty). */
        teksProduk.textContent = frame.produk || '';
        teksInisial.textContent = 'Initial: ' + frame.inisial;
        teksForecast.textContent = frame.forecast ? 'Forecast: ' + frame.forecast : '';
        teksLead.textContent = frame.lead || '';
        teksPosisi.textContent = (indeks + 1) + ' / ' + daftar.length;

        pratinjau();

        /* Bila gambar sedang diperbesar lewat modal, modal ikut berganti
         * frame supaya animasi tidak berhenti saat diperbesar. */
        const modal = document.getElementById('imageModal');
        const gambarModal = document.getElementById('modalImage');

        if (modal && gambarModal
            && modal.classList.contains('show')
            && gambarModal.dataset.seri === pilihSeri.value) {
            gambarModal.src = frame.src;
        }
    }

    window.bukaModalAnimasi = function (src, alt) {
        openImageModal(src, alt);

        const gambarModal = document.getElementById('modalImage');

        if (gambarModal) {
            gambarModal.dataset.seri = pilihSeri.value;
        }
    };

    function hentikan() {
        if (timer) clearInterval(timer);
        timer = null;
        tombolPlay.innerHTML = '<i class="fa-solid fa-play"></i>';
    }

    function putar() {
        hentikan();

        const daftar = frameAktif();

        if (daftar.length < 2) return;

        timer = setInterval(function () {
            indeks = (indeks + 1) % daftar.length;
            tampilkan();
        }, parseInt(pilihKecepatan.value, 10));

        tombolPlay.innerHTML = '<i class="fa-solid fa-pause"></i>';
    }

    pilihSeri.addEventListener('change', function () {
        indeks = 0;
        hentikan();
        tampilkan();
    });

    pilihKecepatan.addEventListener('change', function () {
        if (timer) putar();
    });

    tombolPlay.addEventListener('click', function () {
        timer ? hentikan() : putar();
    });

    tombolPrev.addEventListener('click', function () {
        const daftar = frameAktif();
        indeks = (indeks - 1 + daftar.length) % daftar.length;
        tampilkan();
    });

    tombolNext.addEventListener('click', function () {
        const daftar = frameAktif();
        indeks = (indeks + 1) % daftar.length;
        tampilkan();
    });

    tombolReset.addEventListener('click', function () {
        hentikan();
        indeks = 0;
        tampilkan();
    });

    tampilkan();
})();
</script>

</body>
</html>