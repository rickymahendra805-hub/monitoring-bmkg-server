```php
<?php
/*
|--------------------------------------------------------------------------
| GENERATOR CITY.JSON - BMKG KEMAYORAN
|--------------------------------------------------------------------------
| Membaca semua gambar di assets/city/ hingga subfolder.
| Membuat indeks metadata untuk seluruh gambar yang ditemukan.
| File city.json lama akan dicadangkan sebelum diperbarui.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Jakarta');

$projectDir = __DIR__;
$imageRoot  = $projectDir . '/assets/city';
$dataDir    = $projectDir . '/data';
$jsonFile   = $dataDir . '/city.json';

$extensions = [
    'png', 'jpg', 'jpeg', 'webp',
    'gif', 'bmp', 'tif', 'tiff'
];

function h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function readableName($value)
{
    return ucwords(
        str_replace(['_', '-', '.'], ' ', trim($value))
    );
}

function detectPeriod($text)
{
    // Contoh: 202608, 2026-08, 2026_08
    if (preg_match(
        '/(?<!\d)(20\d{2})[-_ ]?(0[1-9]|1[0-2])(?!\d)/',
        $text,
        $m
    )) {
        return $m[1] . '-' . $m[2];
    }

    return '';
}

function detectProduct($text)
{
    $text = strtolower($text);

    $patterns = [
        'OLR' => '/(^|[^a-z])olr([^a-z]|$)/',
        'PWAT' => '/pwat|precipitable[_ -]?water/',
        'MJO' => '/(^|[^a-z])mjo([^a-z]|$)/',
        'Kelvin' => '/kelvin/',
        'MRG/TD' => '/mrg|tropical[_ -]?disturbance/',
        'ER / Rossby' => '/(^|[^a-z])er([^a-z]|$)|rossby/',
        'Hovmoller' => '/hovm[oö]ller|hovmoller/',
        'n24' => '/(^|[^a-z])n24([^a-z]|$)/',
        'Curah Hujan' => '/precip|rainfall|curah[_ -]?hujan/',
        'Suhu' => '/temperature|(^|[^a-z])tas([^a-z]|$)|suhu/',
        'Kelembapan' => '/humidity|kelembapan/',
        'Angin' => '/wind|angin/'
    ];

    foreach ($patterns as $product => $pattern) {
        if (preg_match($pattern, $text)) {
            return $product;
        }
    }

    return 'Produk lainnya';
}

function detectRegion($relativePath)
{
    $path = str_replace('\\', '/', $relativePath);
    $parts = explode('/', $path);

    /*
     * Prioritaskan nama kota yang jelas terlihat pada
     * folder atau nama file.
     */
    $knownCities = [
        'Banda Aceh', 'Medan', 'Padang', 'Pekanbaru',
        'Tanjung Pinang', 'Jambi', 'Palembang', 'Bengkulu',
        'Bandar Lampung', 'Pangkal Pinang', 'Jakarta',
        'Serang', 'Bandung', 'Semarang', 'Yogyakarta',
        'Surabaya', 'Denpasar', 'Mataram', 'Kupang',
        'Pontianak', 'Palangkaraya', 'Banjarmasin',
        'Samarinda', 'Tanjung Selor', 'Tarakan',
        'Manado', 'Gorontalo', 'Palu', 'Mamuju',
        'Makassar', 'Kendari', 'Ambon', 'Ternate',
        'Sofifi', 'Manokwari', 'Sorong', 'Jayapura',
        'Nabire', 'Wamena', 'Timika', 'Merauke',
        'Biak', 'Saumlaki', 'Tual', 'Kaimana',
        'Fakfak', 'Bau Bau', 'Luwuk', 'Tahuna'
    ];

    $normalizedPath = strtolower(
        str_replace(['_', '-', '/', '.'], ' ', $path)
    );

    foreach ($knownCities as $city) {
        if (strpos(
            $normalizedPath,
            strtolower($city)
        ) !== false) {
            return $city;
        }
    }

    /*
     * Jika nama kota tidak masuk daftar di atas,
     * coba gunakan nama subfolder yang bukan nama produk,
     * folder umum, atau kode periode.
     */
    $ignoredFolders = [
        'city', 'images', 'image', 'gambar',
        'data', 'produk', 'products', 'output',
        'grafik', 'charts', 'maps', 'map',
        'olr', 'pwat', 'mjo', 'er', 'kelvin',
        'mrg', 'mrg-td', 'hovmoller',
        'forecast', 'initial', 'forecasting',
        'raw', 'original', 'corrected',
        'png', 'jpg', 'jpeg', 'webp'
    ];

    foreach (array_slice($parts, 0, -1) as $folder) {
        $folder = trim($folder);

        if ($folder === '') {
            continue;
        }

        if (preg_match('/^\d{4}$/', $folder)) {
            continue;
        }

        if (preg_match('/^20\d{2}(0[1-9]|1[0-2])$/', $folder)) {
            continue;
        }

        if (in_array(
            strtolower($folder),
            $ignoredFolders,
            true
        )) {
            continue;
        }

        return readableName($folder);
    }

    return 'Wilayah belum teridentifikasi';
}

/* Validasi lokasi sumber gambar. */

if (!is_dir($imageRoot)) {
    exit(
        'GAGAL: Folder assets/city tidak ditemukan. ' .
        'Periksa lokasi project.'
    );
}

if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0775, true) && !is_dir($dataDir)) {
        exit('GAGAL: Folder data tidak dapat dibuat.');
    }
}

/* Cari seluruh gambar, termasuk gambar di subfolder. */

$records = [];

try {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $imageRoot,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $extension = strtolower($file->getExtension());

        if (!in_array($extension, $extensions, true)) {
            continue;
        }

        $relative = substr(
            $file->getPathname(),
            strlen($imageRoot) + 1
        );

        $relative = str_replace('\\', '/', $relative);
        $filename = basename($relative);

        $period = detectPeriod($relative);
        $product = detectProduct($relative);
        $region = detectRegion($relative);

        $records[] = [
            'nama_kota' => $region,
            'provinsi' => '',
            'produk' => $product,
            'periode' => $period,
            'tanggal_inisialisasi' => '',
            'gambar' => $relative,
            'deskripsi' => '',
            'nama_file' => $filename
        ];
    }
} catch (Throwable $e) {
    exit(
        'GAGAL membaca folder gambar: ' .
        h($e->getMessage())
    );
}

/* Urutkan berdasarkan wilayah, periode, dan nama file. */

usort($records, function ($a, $b) {
    $regionCompare = strcasecmp(
        $a['nama_kota'],
        $b['nama_kota']
    );

    if ($regionCompare !== 0) {
        return $regionCompare;
    }

    $periodCompare = strcmp(
        $b['periode'],
        $a['periode']
    );

    if ($periodCompare !== 0) {
        return $periodCompare;
    }

    return strcasecmp($a['nama_file'], $b['nama_file']);
});

/* Susun berkas indeks JSON. */

$output = [
    'nama_sistem' => 'BMKG Kemayoran Pemantauan Kota',
    'versi' => 1,
    'dibuat_pada' => date('c'),
    'sumber_gambar' => 'assets/city/',
    'jumlah_gambar' => count($records),
    'data' => $records
];

$json = json_encode(
    $output,
    JSON_PRETTY_PRINT
    | JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    exit('GAGAL membuat JSON: ' . json_last_error_msg());
}

/*
 * Cadangkan JSON lama agar informasi sebelumnya
 * tidak hilang ketika generator dijalankan.
 */
if (is_file($jsonFile)) {
    $backupFile = $dataDir
        . '/city_backup_'
        . date('Ymd_His')
        . '.json';

    if (!copy($jsonFile, $backupFile)) {
        exit(
            'GAGAL membuat cadangan city.json lama. ' .
            'Berkas lama tidak diubah.'
        );
    }
}

/* Simpan indeks baru. */

if (file_put_contents($jsonFile, $json, LOCK_EX) === false) {
    exit('GAGAL menyimpan data/city.json.');
}

/* Tampilkan hasil. */

$regions = [];

foreach ($records as $record) {
    $regions[$record['nama_kota']] = true;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Generator City JSON - BMKG Kemayoran</title>

    <style>
        body {
            margin: 0;
            padding: 35px 18px;
            background: #070c16;
            color: #edf4ff;
            font-family: Arial, sans-serif;
        }

        .panel {
            max-width: 780px;
            margin: auto;
            padding: 28px;
            background: #101b2c;
            border: 1px solid #263851;
            border-radius: 18px;
        }

        h1 {
            font-size: 25px;
        }

        .ok {
            color: #55ddb0;
            font-weight: bold;
        }

        .muted {
            color: #9aacc4;
            line-height: 1.8;
        }

        code {
            color: #70dcf3;
            overflow-wrap: anywhere;
        }

        a {
            display: inline-block;
            margin: 12px 8px 0 0;
            padding: 11px 15px;
            background: #1678ec;
            color: white;
            text-decoration: none;
            border-radius: 9px;
        }

        .secondary {
            background: #192a42;
        }

        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
<div class="panel">
    <h1>City JSON Generator</h1>

    <p class="ok">Indeks JSON berhasil dibuat.</p>

    <p class="muted">
        Berkas:
        <code>data/city.json</code>
    </p>

    <p>Jumlah gambar yang ditemukan:</p>
    <h2><?= number_format(count($records), 0, ',', '.') ?> gambar</h2>

    <p>Jumlah label wilayah yang terdeteksi:</p>
    <h2><?= number_format(count($regions), 0, ',', '.') ?> wilayah/label</h2>

    <p class="muted">
        Indeks disusun dari seluruh gambar yang ditemukan pada
        <code>assets/city/</code>, termasuk subfolder.
        Nama wilayah dan produk merupakan hasil identifikasi awal
        dari nama folder serta nama file, sehingga masih perlu
        diperiksa jika labelnya belum sesuai.
    </p>

    <h3>Wilayah/label yang terdeteksi</h3>
    <ul>
        <?php foreach (array_keys($regions) as $region): ?>
            <li><?= h($region) ?></li>
        <?php endforeach; ?>
    </ul>

    <a href="city.php">Buka Pemantauan Kota</a>
    <a class="secondary" href="data/city.json" target="_blank">
        Lihat city.json
    </a>
</div>
</body>
</html>
```
