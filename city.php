<?php
/*
|--------------------------------------------------------------------------
| CITY MONITORING - BMKG KEMAYORAN
|--------------------------------------------------------------------------
| File       : city.php
| Folder     : assets/city/
| Metadata   : data/city.json (opsional, jika sudah dibuat)
|
| FITUR:
| - Tema Dark Premium dengan menu horizontal seperti dashboard
| - Membaca gambar secara otomatis sampai subfolder
| - Membaca metadata city.json jika tersedia
| - Filter wilayah, produk atmosfer, dan periode
| - Pencarian berdasarkan nama file dan informasi produk
| - Preview gambar dan modal pembesaran
| - Tidak menghapus, memindahkan, atau menimpa gambar sumber
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Jakarta');

/* =========================================================
   1. KONFIGURASI DIREKTORI
========================================================= */

$cityRoot = __DIR__ . DIRECTORY_SEPARATOR . 'assets'
          . DIRECTORY_SEPARATOR . 'city';

$cityUrlBase = 'assets/city/';

$jsonCandidates = [
    __DIR__ . DIRECTORY_SEPARATOR . 'data'
        . DIRECTORY_SEPARATOR . 'city.json',
    __DIR__ . DIRECTORY_SEPARATOR . 'city.json'
];

$cityJsonFile = '';

foreach ($jsonCandidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $cityJsonFile = $candidate;
        break;
    }
}

/* =========================================================
   2. FUNGSI UMUM
========================================================= */

function city_h($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function city_value($value)
{
    if (is_string($value) || is_numeric($value)) {
        return trim((string) $value);
    }

    return '';
}

function city_pick(array $data, array $keys)
{
    foreach ($keys as $key) {
        if (isset($data[$key])) {
            $value = city_value($data[$key]);

            if ($value !== '') {
                return $value;
            }
        }
    }

    return '';
}

function city_normalize_path($path)
{
    $path = urldecode(trim((string) $path));
    $path = str_replace('\\', '/', $path);
    $lower = strtolower($path);

    $assetPos = strpos($lower, 'assets/city/');

    if ($assetPos !== false) {
        $path = substr($path, $assetPos + strlen('assets/city/'));
    } elseif (strpos($lower, 'city/') === 0) {
        $path = substr($path, strlen('city/'));
    }

    $path = preg_replace('~^\./+~', '', $path);
    $path = ltrim($path, '/');

    return strtolower($path);
}

function city_build_image_url($relativePath)
{
    $parts = explode('/', str_replace('\\', '/', $relativePath));

    $encoded = [];

    foreach ($parts as $part) {
        $encoded[] = rawurlencode($part);
    }

    return 'assets/city/' . implode('/', $encoded);
}

/* =========================================================
   3. MEMBACA CITY.JSON
   Mendukung metadata yang menyimpan nama kota, produk,
   periode, gambar/file, serta keterangan.
========================================================= */

function city_json_walk(
    $node,
    &$records,
    $parentCity = '',
    $currentKey = ''
) {
    if (!is_array($node)) {
        return;
    }

    $localCity = city_pick($node, [
        'nama_kota',
        'kota',
        'city',
        'nama_wilayah',
        'wilayah',
        'region',
        'daerah',
        'lokasi',
        'location'
    ]);

    if ($localCity === '') {
        $localCity = $parentCity;
    }

    $imagePath = city_pick($node, [
        'gambar',
        'gambar_path',
        'nama_gambar',
        'nama_file',
        'file_gambar',
        'image',
        'image_path',
        'image_url',
        'filename',
        'file_name',
        'filepath',
        'file_path',
        'path',
        'src'
    ]);

    if (
        $imagePath === ''
        && preg_match('/\.(png|jpe?g|webp|gif|svg|bmp|tif|tiff)$/i', $currentKey)
    ) {
        $imagePath = $currentKey;
    }

    if (
        $imagePath !== ''
        && preg_match('/\.(png|jpe?g|webp|gif|svg|bmp|tif|tiff)(\?.*)?$/i', $imagePath)
    ) {
        $records[] = [
            'path' => $imagePath,
            'city' => $localCity,
            'product' => city_pick($node, [
                'produk',
                'jenis_produk',
                'product',
                'jenis',
                'variabel',
                'parameter'
            ]),
            'period' => city_pick($node, [
                'periode',
                'periode_awal',
                'tanggal_init',
                'tanggal_inisialisasi',
                'initial_date',
                'init_date',
                'tanggal',
                'date',
                'bulan'
            ]),
            'description' => city_pick($node, [
                'deskripsi',
                'keterangan',
                'description',
                'analisis',
                'caption',
                'judul',
                'title'
            ])
        ];
    }

    $genericKeys = [
        'data',
        'items',
        'list',
        'images',
        'gambar',
        'records',
        'wilayah',
        'kota',
        'city',
        'produk',
        'metadata'
    ];

    foreach ($node as $key => $value) {
        if (!is_array($value)) {
            continue;
        }

        $childCity = $localCity;

        if (
            $childCity === ''
            && is_string($key)
            && !in_array(strtolower($key), $genericKeys, true)
            && !preg_match('/\.(png|jpe?g|webp|gif|svg|bmp|tif|tiff)$/i', $key)
        ) {
            $childCity = ucwords(
                str_replace(['_', '-'], ' ', trim($key))
            );
        }

        city_json_walk(
            $value,
            $records,
            $childCity,
            (string) $key
        );
    }
}

function city_make_json_index($jsonFile)
{
    $index = [];

    if (
        $jsonFile === ''
        || !is_file($jsonFile)
        || !is_readable($jsonFile)
    ) {
        return $index;
    }

    $raw = file_get_contents($jsonFile);

    if ($raw === false || trim($raw) === '') {
        return $index;
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        return $index;
    }

    $records = [];

    city_json_walk($decoded, $records);

    foreach ($records as $record) {
        $normalized = city_normalize_path($record['path']);

        if ($normalized !== '') {
            $index[$normalized] = $record;
        }

        $basename = strtolower(
            basename(str_replace('\\', '/', $record['path']))
        );

        if ($basename !== '' && !isset($index[$basename])) {
            $index[$basename] = $record;
        }
    }

    return $index;
}

/* =========================================================
   4. DETEKSI PERIODE DARI METADATA / NAMA FILE
========================================================= */

function city_detect_period($text)
{
    $text = (string) $text;

    /*
     * Tanggal lengkap YYYYMMDD pada nama folder, contoh 20261008.
     * Diproses lebih dulu agar tidak tertelan pola YYYYMM.
     */
    if (
        preg_match(
            '/(?<!\d)(20\d{2})(0[1-9]|1[0-2])(0[1-9]|[12]\d|3[01])(?!\d)/',
            $text,
            $match
        )
    ) {
        return $match[1] . '-' . $match[2] . '-' . $match[3];
    }

    if (
        preg_match(
            '/(?<!\d)(20\d{2})[-_\/\. ]?(0[1-9]|1[0-2])(?!\d)/',
            $text,
            $match
        )
    ) {
        return $match[1] . '-' . $match[2];
    }

    $months = [
        'januari' => '01',
        'january' => '01',
        'februari' => '02',
        'february' => '02',
        'maret' => '03',
        'march' => '03',
        'april' => '04',
        'mei' => '05',
        'may' => '05',
        'juni' => '06',
        'june' => '06',
        'juli' => '07',
        'july' => '07',
        'agustus' => '08',
        'august' => '08',
        'september' => '09',
        'oktober' => '10',
        'october' => '10',
        'november' => '11',
        'desember' => '12',
        'december' => '12'
    ];

    foreach ($months as $monthName => $monthNumber) {
        if (
            preg_match(
                '/\b' . preg_quote($monthName, '/') . '\b/i',
                $text
            )
            && preg_match('/\b(20\d{2})\b/', $text, $yearMatch)
        ) {
            return $yearMatch[1] . '-' . $monthNumber;
        }
    }

    return '';
}

function city_period_label($period)
{
    $months = [
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

    /* Tanggal lengkap, contoh 2026-10-08 menjadi 08 Oktober 2026. */
    if (
        preg_match(
            '/^(20\d{2})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/',
            $period,
            $m
        )
    ) {
        return $m[3] . ' ' . $months[$m[2]] . ' ' . $m[1];
    }

    if (!preg_match('/^(20\d{2})-(0[1-9]|1[0-2])$/', $period, $m)) {
        return 'Periode tidak diketahui';
    }

    return $months[$m[2]] . ' ' . $m[1];
}

/* =========================================================
   5. DETEKSI PRODUK ATMOSFER
========================================================= */

function city_detect_product($text)
{
    $text = strtolower((string) $text);

    $patterns = [
        'Hovmoller' => '/hovm[oö]ller|hovmoller/',
        'MRG/TD' => '/mrg|mixed[_ -]?rossby|tropical[_ -]?disturbance/',
        'Kelvin' => '/kelvin/',
        'MJO' => '/(^|[^a-z])mjo([^a-z]|$)/',
        'ER / Rossby' => '/(^|[^a-z])(er|rossby)([^a-z]|$)/',
        'PWAT' => '/pwat|precipitable[_ -]?water/',
        'OLR' => '/(^|[^a-z])olr([^a-z]|$)/',
        'n24' => '/(^|[^a-z])n24([^a-z]|$)/',
        'Curah Hujan' => '/precipitation|precip|rainfall|curah[_ -]?hujan/',
        'Suhu' => '/temperature|(^|[^a-z])tas([^a-z]|$)|suhu/',
        'Kelembapan' => '/humidity|kelembapan/',
        'Angin' => '/wind|angin/'
    ];

    foreach ($patterns as $label => $pattern) {
        if (preg_match($pattern, $text)) {
            return $label;
        }
    }

    return 'Produk lainnya';
}

/* =========================================================
   6. DETEKSI WILAYAH
========================================================= */

function city_detect_region($relativePath, $metadataCity = '')
{
    if (trim($metadataCity) !== '') {
        return trim($metadataCity);
    }

    $path = str_replace('\\', '/', $relativePath);

    /*
     * Coba identifikasi nama kota yang muncul di path
     * atau nama file. Daftar ini hanya fallback.
     * Metadata city.json tetap lebih diutamakan.
     */
    $knownCities = [
        'Banda Aceh',
        'Jakarta',
        'Ambon',
        'Makassar',
        'Manado',
        'Sorong',
        'Jayapura',
        'Merauke',
        'Kupang',
        'Denpasar',
        'Surabaya',
        'Semarang',
        'Bandung',
        'Medan',
        'Padang',
        'Palembang',
        'Pontianak',
        'Palu',
        'Kendari',
        'Ternate',
        'Mataram',
        'Bengkulu',
        'Jambi',
        'Pekanbaru',
        'Yogyakarta',
        'Banjarmasin',
        'Samarinda',
        'Tarakan',
        'Manokwari',
        'Biak',
        'Timika',
        'Gorontalo',
        'Luwuk',
        'Tual',
        'Saumlaki',
        'Bau Bau',
        'Kaimana',
        'Fakfak',
        'Wamena',
        'Balikpapan',
        'Bandar Lampung',
        'Batam',
        'Bekasi',
        'Bitung',
        'Cirebon',
        'Depok',
        'Magelang',
        'Malang',
        'Palangka Raya',
        'Serang',
        'Sofifi',
        'Solo',
        'Tangerang',
        'Tanjung Pinang',
        'Tanjung Selor'
    ];

    $searchText = strtolower(
        str_replace(['_', '-', '/', '.'], ' ', $path)
    );

    foreach ($knownCities as $city) {
        $needle = strtolower($city);

        if (strpos($searchText, $needle) !== false) {
            return $city;
        }
    }

    /*
     * Jika gambar disusun per subfolder kota:
     * assets/city/Ambon/grafik.png
     */
    $parts = explode('/', $path);

    if (count($parts) > 1 && trim($parts[0]) !== '') {
        $firstFolder = trim($parts[0]);

        $genericFolders = [
            'images',
            'image',
            'gambar',
            'produk',
            'data',
            'output',
            'grafik',
            'charts'
        ];

        if (!in_array(strtolower($firstFolder), $genericFolders, true)) {
            return ucwords(
                str_replace(['_', '-'], ' ', $firstFolder)
            );
        }
    }

    return 'Wilayah belum teridentifikasi';
}

/* =========================================================
   7. SCAN GAMBAR ASSETS/CITY
========================================================= */

$jsonIndex = city_make_json_index($cityJsonFile);

$allImages = [];

$allowedExtensions = [
    'png',
    'jpg',
    'jpeg',
    'webp',
    'gif',
    'svg',
    'bmp',
    'tif',
    'tiff'
];

$folderAvailable = is_dir($cityRoot);

if ($folderAvailable && is_readable($cityRoot)) {
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $cityRoot,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }

            $extension = strtolower($fileInfo->getExtension());

            if (!in_array($extension, $allowedExtensions, true)) {
                continue;
            }

            $absolutePath = $fileInfo->getPathname();

            $relativePath = substr(
                $absolutePath,
                strlen($cityRoot) + 1
            );

            $relativePath = str_replace('\\', '/', $relativePath);

            $normalized = city_normalize_path($relativePath);
            $basename = strtolower(basename($relativePath));

            $meta = [];

            if (isset($jsonIndex[$normalized])) {
                $meta = $jsonIndex[$normalized];
            } elseif (isset($jsonIndex[$basename])) {
                $meta = $jsonIndex[$basename];
            } elseif (
                isset($jsonIndex['assets/city/' . $normalized])
            ) {
                $meta = $jsonIndex['assets/city/' . $normalized];
            }

            $filename = basename($relativePath);

            $combinedText = $relativePath . ' ' . $filename;

            $metadataProduct = isset($meta['product'])
                ? trim((string) $meta['product'])
                : '';

            $metadataPeriod = isset($meta['period'])
                ? trim((string) $meta['period'])
                : '';

            $metadataDescription = isset($meta['description'])
                ? trim((string) $meta['description'])
                : '';

            $metadataCity = isset($meta['city'])
                ? trim((string) $meta['city'])
                : '';

            $product = $metadataProduct !== ''
                ? $metadataProduct
                : city_detect_product($combinedText);

            $period = city_detect_period($metadataPeriod);

            if ($period === '') {
                $period = city_detect_period($combinedText);
            }

            $region = city_detect_region(
                $relativePath,
                $metadataCity
            );

            $description = $metadataDescription;

            if ($description === '') {
                $description =
                    'Keterangan khusus untuk gambar ini belum '
                    . 'tersedia di metadata. Identifikasi sementara '
                    . 'menggunakan nama berkas dan lokasi gambar.';
            }

            /* Rata-rata berjalan dari nama file, contoh -3days. */
            $meanDays = '';

            if (preg_match('/-(\d+)days/i', $filename, $meanMatch)) {
                $meanDays = $meanMatch[1];
            }

            $allImages[] = [
                'filename' => $filename,
                'relative' => $relativePath,
                'url' => city_build_image_url($relativePath),
                'region' => $region,
                'product' => $product,
                'period' => $period,
                'period_label' => city_period_label($period),
                'mean' => $meanDays,
                'description' => $description,
                'metadata_found' => !empty($meta),
                'search_text' => strtolower(
                    $filename . ' '
                    . $relativePath . ' '
                    . $region . ' '
                    . $product . ' '
                    . $period . ' '
                    . $description
                )
            ];
        }
    } catch (Throwable $e) {
        // Kesalahan pembacaan ditangani melalui status halaman.
    }
}

/* =========================================================
   8. URUTKAN GAMBAR BERDASARKAN PERIODE DAN NAMA
   strnatcasecmp agar urutan mean logis: 1, 3, 7, 10 hari
   (strcasecmp biasa membuat 10days tampil sebelum 1days).
========================================================= */

usort($allImages, function ($a, $b) {
    if ($a['period'] !== $b['period']) {
        return strcmp($b['period'], $a['period']);
    }

    return strnatcasecmp($a['filename'], $b['filename']);
});

/* =========================================================
   9. BENTUK PILIHAN FILTER DARI GAMBAR YANG DITEMUKAN
========================================================= */

$regionOptions = [];
$productOptions = [];
$periodOptions = [];

foreach ($allImages as $image) {
    if ($image['region'] !== '') {
        $regionOptions[$image['region']] = $image['region'];
    }

    if ($image['product'] !== '') {
        $productOptions[$image['product']] = $image['product'];
    }

    if ($image['period'] !== '') {
        $periodOptions[$image['period']] = $image['period'];
    }
}

uksort($regionOptions, 'strnatcasecmp');
uksort($productOptions, 'strnatcasecmp');
krsort($periodOptions);

/* Pilihan rata-rata (running mean) dari nama file, contoh 3days. */
$meanOptions = [];

foreach ($allImages as $image) {
    if (
        $image['mean'] !== ''
        && !isset($meanOptions[$image['mean']])
    ) {
        $meanOptions[$image['mean']] =
            'Rata-rata ' . $image['mean'] . ' Hari';
    }
}

ksort($meanOptions, SORT_NUMERIC);

/* =========================================================
   9b. KOORDINAT KOTA UNTUK PETA
   Sumber: data/koordinat_kota.json (wilayah.json + Nominatim OSM)
========================================================= */

function city_normalize_name($value)
{
    return preg_replace('/[^a-z0-9]/', '', strtolower(trim((string) $value)));
}

$koordinatKota = [];

$koordinatFile = __DIR__ . DIRECTORY_SEPARATOR . 'data'
    . DIRECTORY_SEPARATOR . 'koordinat_kota.json';

if (is_file($koordinatFile) && is_readable($koordinatFile)) {
    $koordinatDecoded = json_decode(
        (string) file_get_contents($koordinatFile),
        true
    );

    if (is_array($koordinatDecoded)) {
        foreach ($koordinatDecoded['kota'] ?? [] as $item) {
            if (
                !isset($item['nama'], $item['lat'], $item['lng'])
                || !is_numeric($item['lat'])
                || !is_numeric($item['lng'])
            ) {
                continue;
            }

            $koordinatKota[city_normalize_name($item['nama'])] = [
                'provinsi' => trim((string) ($item['provinsi'] ?? '')),
                'lat' => (float) $item['lat'],
                'lng' => (float) $item['lng']
            ];
        }
    }
}

$jumlahGambarKota = [];

foreach ($allImages as $image) {
    if ($image['region'] !== '') {
        $jumlahGambarKota[$image['region']] =
            ($jumlahGambarKota[$image['region']] ?? 0) + 1;
    }
}

$petaKota = [];
$kotaTanpaKoordinat = [];

foreach (array_keys($regionOptions) as $region) {
    $kunci = city_normalize_name($region);

    if (isset($koordinatKota[$kunci])) {
        $petaKota[] = [
            'nama' => $region,
            'provinsi' => $koordinatKota[$kunci]['provinsi'],
            'lat' => $koordinatKota[$kunci]['lat'],
            'lng' => $koordinatKota[$kunci]['lng'],
            'jumlah' => $jumlahGambarKota[$region] ?? 0
        ];
    } else {
        $kotaTanpaKoordinat[] = $region;
    }
}


/* =========================================================
   10. BACA FILTER DARI URL
========================================================= */

$regionSelected = trim(
    (string) ($_GET['wilayah'] ?? '')
);

$productSelected = trim(
    (string) ($_GET['produk'] ?? '')
);

$periodSelected = trim(
    (string) ($_GET['periode'] ?? '')
);

$keyword = trim(
    (string) ($_GET['q'] ?? '')
);

$rataSelected = trim(
    (string) ($_GET['rata'] ?? '')
);

$filterExecuted = isset($_GET['cari']);

$filteredImages = [];

if ($filterExecuted) {
    foreach ($allImages as $image) {
        if (
            $regionSelected !== ''
            && $image['region'] !== $regionSelected
        ) {
            continue;
        }

        if (
            $productSelected !== ''
            && $image['product'] !== $productSelected
        ) {
            continue;
        }

        if (
            $rataSelected !== ''
            && $image['mean'] !== $rataSelected
        ) {
            continue;
        }

        if (
            $periodSelected !== ''
            && $image['period'] !== $periodSelected
        ) {
            continue;
        }

        if (
            $keyword !== ''
            && strpos(
                $image['search_text'],
                strtolower($keyword)
            ) === false
        ) {
            continue;
        }

        $filteredImages[] = $image;
    }
}

/* =========================================================
   10B. PENGURUTAN HASIL, PERIODE TERBARU, CHIP FILTER,
        DAN PRASETEL PENCARIAN CEPAT
========================================================= */

$sortSelected = trim((string) ($_GET['urut'] ?? ''));

if ($sortSelected === 'mean') {
    usort($filteredImages, function ($a, $b) {
        if ($a['period'] !== $b['period']) {
            return strcmp($b['period'], $a['period']);
        }

        $meanA = $a['mean'] !== '' ? (int) $a['mean'] : PHP_INT_MAX;
        $meanB = $b['mean'] !== '' ? (int) $b['mean'] : PHP_INT_MAX;

        if ($meanA !== $meanB) {
            return $meanA <=> $meanB;
        }

        return strnatcasecmp($a['filename'], $b['filename']);
    });
} elseif ($sortSelected === 'nama') {
    usort($filteredImages, function ($a, $b) {
        return strnatcasecmp($a['filename'], $b['filename']);
    });
}

/* Periode (tanggal init) terbaru di antara hasil pencarian. */
$latestFilteredPeriod = '';

foreach ($filteredImages as $image) {
    if ($image['period'] > $latestFilteredPeriod) {
        $latestFilteredPeriod = $image['period'];
    }
}

/* URL filter: mempertahankan parameter lain, menghapus yang dihapus. */
function city_filter_url(array $removeKeys = [], array $overrides = [])
{
    $params = $_GET;

    foreach ($removeKeys as $key) {
        unset($params[$key]);
    }

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    $params['cari'] = '1';

    return 'city.php?' . http_build_query($params);
}

/* Chip filter aktif: tiap chip dapat dihapus satu per satu. */
$activeChips = [];

if ($regionSelected !== '') {
    $activeChips[] = [
        'label' => 'Wilayah: ' . $regionSelected,
        'url'   => city_filter_url(['wilayah'])
    ];
}

if ($productSelected !== '') {
    $activeChips[] = [
        'label' => 'Produk: ' . $productSelected,
        'url'   => city_filter_url(['produk'])
    ];
}

if ($periodSelected !== '') {
    $activeChips[] = [
        'label' => 'Init: ' . city_period_label($periodSelected),
        'url'   => city_filter_url(['periode'])
    ];
}

if ($rataSelected !== '') {
    $activeChips[] = [
        'label' => 'Mean ' . $rataSelected . ' Hari',
        'url'   => city_filter_url(['rata'])
    ];
}

if ($keyword !== '') {
    $activeChips[] = [
        'label' => 'Kata kunci: "' . $keyword . '"',
        'url'   => city_filter_url(['q'])
    ];
}

if ($sortSelected !== '') {
    $activeChips[] = [
        'label' => 'Urut: '
            . ($sortSelected === 'mean' ? 'Mean 1→10' : 'Nama A-Z'),
        'url'   => city_filter_url(['urut'])
    ];
}

/* Prasetel pencarian cepat untuk tampilan awal (belum ada filter). */
$quickPresets = [];

$presetCityNames = [
    'Jakarta',
    'Bandung',
    'Surabaya',
    'Medan',
    'Makassar',
    'Denpasar',
    'Semarang',
    'Palembang'
];

$presetProduct = '';

foreach (['OLR', 'PWAT'] as $candidateProduct) {
    if (isset($productOptions[$candidateProduct])) {
        $presetProduct = $candidateProduct;
        break;
    }
}

if ($presetProduct === '') {
    foreach ($productOptions as $candidateKey => $candidateValue) {
        $presetProduct = $candidateKey;
        break;
    }
}

$presetPeriod = '';

foreach ($periodOptions as $candidateKey => $candidateValue) {
    $presetPeriod = $candidateKey;
    break;
}

$presetMean = isset($meanOptions['3']) ? '3' : '';

foreach ($presetCityNames as $presetCity) {
    if (!isset($regionOptions[$presetCity])) {
        continue;
    }

    $quickPresets[] = [
        'city' => $presetCity,
        'url'  => city_filter_url([], [
            'wilayah' => $presetCity,
            'produk'  => $presetProduct,
            'periode' => $presetPeriod,
            'rata'    => $presetMean
        ])
    ];
}

/* Data ringkas untuk JavaScript (navigasi modal & perbandingan). */
$jsImages = [];

foreach ($filteredImages as $image) {
    $jsImages[] = [
        'url'       => $image['url'],
        'file'      => $image['filename'],
        'region'    => $image['region'],
        'product'   => $image['product'],
        'mean'      => $image['mean'],
        'period'    => $image['period_label'],
        'periodRaw' => $image['period'],
        'latest'    => (
            $latestFilteredPeriod !== ''
            && $image['period'] === $latestFilteredPeriod
        )
    ];
}

/* Dimensi panel perbandingan otomatis (kota, init, mean, produk). */
$autoRegions = [];
$autoPeriods = [];
$autoMeans = [];
$autoProducts = [];

foreach ($filteredImages as $image) {
    if ($image['region'] !== '') {
        $autoRegions[$image['region']] = true;
    }

    if ($image['period'] !== '') {
        $autoPeriods[$image['period']] = $image['period_label'];
    }

    if ($image['mean'] !== '') {
        $autoMeans[] = (int) $image['mean'];
    }

    if ($image['product'] !== '') {
        $autoProducts[$image['product']] = true;
    }
}

uksort($autoRegions, 'strnatcasecmp');
krsort($autoPeriods);

$autoMeans = array_values(array_unique($autoMeans));
sort($autoMeans);

$orderedProducts = [];

foreach (['OLR', 'PWAT'] as $preferredProduct) {
    if (isset($autoProducts[$preferredProduct])) {
        $orderedProducts[] = $preferredProduct;

        unset($autoProducts[$preferredProduct]);
    }
}

foreach (array_keys($autoProducts) as $otherProduct) {
    $orderedProducts[] = $otherProduct;
}

$autoProducts = $orderedProducts;

/* Kota awal pada panel: ikuti filter wilayah bila tersedia. */
$autoCityDefault = $regionSelected;

if ($autoCityDefault === '' || !isset($autoRegions[$autoCityDefault])) {
    $autoCityDefault = '';

    foreach ($autoRegions as $regionKey => $regionValue) {
        $autoCityDefault = $regionKey;

        break;
    }
}

/* =========================================================
   11. STATUS JSON
========================================================= */

$jsonStatus = 'Metadata JSON tidak ditemukan; sistem membaca folder gambar secara langsung.';

if ($cityJsonFile !== '') {
    $rawJsonCheck = @file_get_contents($cityJsonFile);
    $decodedCheck = $rawJsonCheck !== false
        ? json_decode($rawJsonCheck, true)
        : null;

    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedCheck)) {
        $jsonStatus = 'Metadata JSON berhasil dibaca. Informasi yang cocok dengan file gambar digunakan.';
    } else {
        $jsonStatus = 'File city.json ditemukan, tetapi isinya belum dapat dibaca sebagai JSON yang valid. Gambar tetap dibaca langsung dari folder.';
    }
}

$currentPage = basename(
    (string) ($_SERVER['PHP_SELF'] ?? 'city.php')
);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Pemantauan Kota | BMKG Kemayoran</title>

    <link rel="icon" type="image/png" href="assets/img/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        :root {
            --bg: #eef3f9;
            --surface: #ffffff;
            --surface-2: #f5f9fd;
            --surface-3: #f5f9fd;
            --border: #ced7e3;
            --text: #16293f;
            --muted: #5c748c;
            --blue: #0f5fbd;
            --cyan: #169eb6;
            --green: #27c49b;
            --red: #bd0f0f;
            --shadow: 0 18px 45px rgba(16,42,67,0.048);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            color: var(--text);
            background:
                radial-gradient(
                    circle at 12% 0%,
                    rgba(22, 120, 236, .11),
                    transparent 31%
                ),
                radial-gradient(
                    circle at 100% 15%,
                    rgba(39, 200, 229, .06),
                    transparent 28%
                ),
                var(--bg);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            line-height: 1.6;
            min-height: 100vh;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input,
        select {
            font: inherit;
        }

        button,
        select {
            cursor: pointer;
        }

        .nav-toggle {
            display: none;
            width: 42px;
            height: 42px;
            color: var(--text);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
        }

        .nav {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 5px;
        }

        .main {
            max-width: 1500px;
            margin: 0 auto;
            padding: 30px 28px 55px;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 9px;
            color: var(--muted);
            font-size: 11px;
            margin-bottom: 17px;
        }

        .breadcrumb .current {
            color: var(--cyan);
        }

        .hero {
            position: relative;
            overflow: hidden;
            padding: 28px;
            margin-bottom: 22px;
            border: 1px solid var(--border);
            border-radius: 20px;
            background:
                linear-gradient(
                    120deg,
                    rgba(22,120,236,.15),
                    rgba(39,200,229,.045) 50%,
                    rgba(255,255,255,0.94)
                ),
                var(--surface);
            box-shadow: var(--shadow);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 230px;
            height: 230px;
            right: -80px;
            top: -110px;
            border-radius: 50%;
            border: 1px solid rgba(39, 200, 229, .15);
            box-shadow:
                0 0 0 25px rgba(39, 200, 229, .025),
                0 0 0 50px rgba(39, 200, 229, .02);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 850px;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--cyan);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.4px;
            margin-bottom: 12px;
        }

        .hero h1 {
            margin: 0 0 12px;
            font-size: clamp(24px, 3vw, 35px);
            font-weight: 800;
            line-height: 1.2;
            letter-spacing: -.8px;
        }

        .hero p {
            max-width: 780px;
            margin: 0;
            color: #22364e;
            font-size: 13px;
            line-height: 1.85;
        }

        .section {
            margin-top: 22px;
            padding: 22px;
            border: 1px solid var(--border);
            border-radius: 18px;
            background: rgba(255,255,255,0.94);
            box-shadow: var(--shadow);
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 14px;
            margin-bottom: 20px;
        }

        .section-title {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: -.25px;
        }

        .section-desc {
            color: var(--muted);
            font-size: 11px;
            margin-top: 5px;
        }

        .section-icon {
            width: 39px;
            height: 39px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid rgba(39,200,229,.18);
            border-radius: 12px;
            color: var(--cyan);
            background: rgba(39,200,229,.07);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 15px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .field label {
            color: #33527a;
            font-size: 11px;
            font-weight: 700;
        }

        .input-control {
            width: 100%;
            min-height: 43px;
            padding: 10px 12px;
            color: var(--text);
            background: #eef3f9;
            border: 1px solid #ced7e3;
            outline: none;
            border-radius: 10px;
            font-size: 12px;
            transition: .2s ease;
        }

        .input-control:focus {
            border-color: var(--cyan);
            box-shadow: 0 0 0 3px rgba(39,200,229,.08);
        }

        .input-control option {
            color: #16293f;
            background: #ffffff;
        }

        .filter-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 19px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            min-height: 41px;
            padding: 10px 16px;
            color: #16293f;
            border: 1px solid transparent;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: .2s ease;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1678ec, #188fd8);
            box-shadow: 0 7px 20px rgba(22,120,236,.15);
        }

        .btn-primary:hover {
            filter: brightness(1.12);
            transform: translateY(-1px);
        }

        .btn-secondary {
            color: #22364e;
            background: #f5f9fd;
            border-color: #ced7e3;
        }

        .btn-secondary:hover {
            color: #16293f;
            border-color: #ced8e3;
        }

        .btn-compact {
            min-height: 34px;
            padding: 7px 11px;
            font-size: 11px;
        }

        .status-line {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-top: 16px;
            padding: 13px 15px;
            color: #22364e;
            background: rgba(255,255,255,0.94);
            border: 1px solid #ced7e3;
            border-radius: 11px;
            font-size: 11px;
        }

        .status-line i {
            color: var(--cyan);
            margin-top: 3px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
            margin-top: 22px;
        }

        .stat-card {
            display: flex;
            gap: 13px;
            align-items: center;
            padding: 17px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 15px;
        }

        .stat-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border-radius: 12px;
            color: var(--cyan);
            background: rgba(39,200,229,.08);
            border: 1px solid rgba(39,200,229,.15);
            font-size: 15px;
        }

        .stat-value {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.2;
        }

        .stat-label {
            color: var(--muted);
            font-size: 10px;
            margin-top: 5px;
        }

        .results-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 18px;
        }

        .result-count {
            color: var(--muted);
            font-size: 11px;
        }

        .result-count strong {
            color: var(--cyan);
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 17px;
        }

        .image-card {
            min-width: 0;
            overflow: hidden;
            background: #eef3f9;
            border: 1px solid #ced7e3;
            border-radius: 15px;
            transition: transform .2s ease, border-color .2s ease;
        }

        .image-card:hover {
            transform: translateY(-3px);
            border-color: #cedae3;
        }

        .image-stage {
            min-height: 185px;
            padding: 12px;
            display: flex;
            justify-content: center;
            align-items: center;
            background:
                linear-gradient(135deg, rgba(39,200,229,.025), transparent),
                #eef3f9;
            border-bottom: 1px solid #ced7e3;
        }

        .image-stage img {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 270px;
            height: auto;
            object-fit: contain;
            cursor: zoom-in;
        }

        .image-stage .img-fallback {
            color: var(--muted);
            text-align: center;
            font-size: 11px;
            padding: 25px;
        }

        .image-card-body {
            padding: 15px;
        }

        .badge-row {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 10px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            max-width: 100%;
            padding: 5px 8px;
            color: #117b9c;
            background: rgba(39,200,229,.08);
            border: 1px solid rgba(39,200,229,.15);
            border-radius: 7px;
            font-size: 9px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .badge.secondary {
            color: #22364e;
            background: #f5f9fd;
            border-color: #ced7e3;
        }

        .image-title {
            margin: 0 0 8px;
            color: #16293f;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .filename {
            color: #5c748c;
            font-size: 10px;
            line-height: 1.65;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        .image-description {
            margin: 12px 0 0;
            padding-top: 12px;
            border-top: 1px solid #ced7e3;
            color: #22364e;
            font-size: 11px;
            line-height: 1.75;
            overflow-wrap: anywhere;
        }

        .image-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 13px;
        }

        .image-actions .btn {
            flex: 1;
        }

        .empty-state {
            padding: 42px 22px;
            text-align: center;
            background: #eef3f9;
            border: 1px dashed #ced7e3;
            border-radius: 15px;
        }

        .empty-state-icon {
            width: 55px;
            height: 55px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            color: var(--cyan);
            background: rgba(39,200,229,.08);
            border: 1px solid rgba(39,200,229,.14);
            border-radius: 17px;
            font-size: 21px;
        }

        .empty-state h3 {
            margin: 0 0 7px;
            font-size: 15px;
        }

        .empty-state p {
            max-width: 600px;
            margin: 0 auto;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.8;
        }

        .note {
            display: flex;
            align-items: flex-start;
            gap: 11px;
            margin-top: 20px;
            padding: 15px;
            color: #22364e;
            background: rgba(22,120,236,.06);
            border: 1px solid rgba(39,200,229,.13);
            border-radius: 12px;
            font-size: 11px;
            line-height: 1.8;
        }

        .note i {
            margin-top: 4px;
            color: var(--cyan);
        }

        .footer {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 27px;
            padding: 19px 4px 0;
            border-top: 1px solid rgba(35,70,110,0.220);
            color: #5c748c;
            font-size: 10px;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 2000;
            padding: 24px;
            align-items: center;
            justify-content: center;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(9px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            position: relative;
            width: min(1100px, 100%);
            max-height: 94vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .modal-image {
            max-width: 100%;
            max-height: 83vh;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 15px 45px rgba(16,42,67,0.099);
        }

        .modal-caption {
            max-width: 100%;
            color: #16293f;
            font-size: 11px;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .modal-close {
            position: absolute;
            top: -12px;
            right: -5px;
            width: 42px;
            height: 42px;
            border: 1px solid #cfd7e2;
            border-radius: 50%;
            color: #16293f;
            background: #f5f9fd;
            font-size: 18px;
            cursor: pointer;
        }

        @media (max-width: 1180px) {

            .nav {
                gap: 2px;
            }

            .filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .image-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 800px) {

            .nav-toggle {
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .nav {
                display: none;
                width: 100%;
                padding-top: 9px;
                flex-direction: column;
                align-items: stretch;
                gap: 5px;
            }

            .nav.show {
                display: flex;
            }

            .main {
                padding: 20px 15px 38px;
            }

            .hero {
                padding: 23px 19px;
            }

            .section {
                padding: 17px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .stat-card {
                padding: 13px;
            }

            .stat-value {
                font-size: 20px;
            }
        }

        @media (max-width: 560px) {

            .hero h1 {
                font-size: 25px;
            }

            .filter-grid,
            .image-grid {
                grid-template-columns: 1fr;
            }

            .image-stage {
                min-height: 150px;
            }

            .image-stage img {
                max-height: 235px;
            }

            .filter-actions .btn {
                flex: 1;
            }

            .footer {
                flex-direction: column;
            }

            .modal {
                padding: 13px;
            }

            .modal-close {
                top: -10px;
                right: 0;
            }
        }

        /* =====================================================
           TAMBAHAN FITUR: RINGKASAN, CHIP, URUT, PRASETEL,
           PERBANDINGAN, NAVIGASI MODAL
        ====================================================== */

        .summary-panel {
            position: relative;
            overflow: hidden;
            margin-top: 22px;
            padding: 20px 22px;
            border: 1px solid rgba(39, 200, 229, .22);
            border-radius: 18px;
            background:
                linear-gradient(
                    120deg,
                    rgba(22, 120, 236, .14),
                    rgba(39, 200, 229, .05) 55%,
                    rgba(255,255,255,0.94)
                ),
                var(--surface);
            box-shadow: var(--shadow);
        }

        .summary-city {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
            font-size: clamp(20px, 2.4vw, 27px);
            font-weight: 800;
            letter-spacing: -.5px;
        }

        .summary-city i {
            width: 40px;
            height: 40px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--cyan);
            background: rgba(39, 200, 229, .1);
            border: 1px solid rgba(39, 200, 229, .22);
            border-radius: 12px;
            font-size: 16px;
        }

        .summary-meta {
            margin-top: 11px;
            color: var(--muted);
            font-size: 11.5px;
        }

        .summary-meta strong {
            color: #16293f;
        }

        .chip-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
            margin-top: 14px;
        }

        .chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 8px 6px 12px;
            color: #16293f;
            background: rgba(22, 120, 236, .12);
            border: 1px solid rgba(39, 200, 229, .2);
            border-radius: 999px;
            font-size: 10.5px;
            font-weight: 700;
        }

        .chip-muted {
            color: var(--muted);
            font-size: 10.5px;
            font-weight: 700;
            margin-right: 2px;
        }

        .chip-x {
            width: 18px;
            height: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #9c1111;
            background: rgba(189,15,15,0.120);
            border: 1px solid rgba(189,15,15,0.250);
            border-radius: 50%;
            font-size: 9px;
            transition: .2s ease;
        }

        .chip-x:hover {
            color: #16293f;
            background: rgba(189,15,15,0.400);
        }

        .badge.product-olr {
            color: #9c6311;
            background: rgba(189,115,15,0.100);
            border-color: rgba(189,115,15,0.300);
        }

        .badge.product-pwat {
            color: #116f9c;
            background: rgba(39, 168, 232, .12);
            border-color: rgba(39, 168, 232, .32);
        }

        .badge.mean-badge {
            color: #1a9462;
            background: rgba(39, 196, 155, .1);
            border-color: rgba(39, 196, 155, .28);
        }

        .badge.init-badge {
            color: #16293f;
            background: rgba(55,15,189,0.100);
            border-color: rgba(55,15,189,0.280);
        }

        .badge.newest-badge {
            color: #0a1524;
            background: linear-gradient(135deg, #27c8e5, #d7eae5);
            border-color: transparent;
        }

        .legend-row {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 22px;
            margin-top: 15px;
            padding: 12px 14px;
            background: rgba(16,42,67,0.248);
            border: 1px dashed #ced7e3;
            border-radius: 12px;
            color: #22364e;
            font-size: 10.5px;
            line-height: 1.75;
        }

        .legend-row b {
            color: #16293f;
        }

        .sort-group {
            display: inline-flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }

        .sort-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #5c748c;
            font-size: 10.5px;
            font-weight: 700;
            margin-right: 2px;
        }

        .sort-link {
            padding: 6px 11px;
            color: #22364e;
            background: #ffffff;
            border: 1px solid #ced7e3;
            border-radius: 8px;
            font-size: 10.5px;
            font-weight: 700;
            transition: .2s ease;
        }

        .sort-link:hover {
            color: #16293f;
            border-color: #ced8e3;
        }

        .sort-link.active {
            color: #16293f;
            background: rgba(22, 120, 236, .22);
            border-color: rgba(39, 200, 229, .38);
        }

        .btn-compare.active {
            color: #16293f;
            background: linear-gradient(135deg, #dad7ea, #1d9dc4);
            border-color: transparent;
        }

        .preset-row {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 18px;
        }

        .preset-label {
            color: var(--muted);
            font-size: 10.5px;
            font-weight: 700;
        }

        .preset-btn {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            color: #16293f;
            background: #ffffff;
            border: 1px solid #ced7e3;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            transition: .2s ease;
        }

        .preset-btn i {
            color: var(--cyan);
            font-size: 10px;
        }

        .preset-btn:hover {
            color: #16293f;
            border-color: rgba(39, 200, 229, .45);
            background: rgba(22, 120, 236, .16);
            transform: translateY(-1px);
        }

        .compare-tray {
            position: fixed;
            left: 50%;
            bottom: 18px;
            transform: translateX(-50%);
            z-index: 1500;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            max-width: min(94vw, 920px);
            padding: 10px 14px;
            background: rgba(255,255,255,0.94);
            border: 1px solid rgba(39, 200, 229, .3);
            border-radius: 16px;
            box-shadow: 0 16px 42px rgba(16,42,67,0.110);
            backdrop-filter: blur(12px);
        }

        .compare-tray[hidden] {
            display: none;
        }

        .compare-tray-label {
            color: #22364e;
            font-size: 10.5px;
            font-weight: 700;
            white-space: nowrap;
        }

        .compare-thumbs {
            display: flex;
            gap: 8px;
        }

        .compare-thumbs img {
            width: 48px;
            height: 42px;
            object-fit: cover;
            border: 1px solid #ced6e3;
            border-radius: 7px;
            cursor: pointer;
        }

        .compare-tray-actions {
            display: flex;
            gap: 8px;
        }

        .compare-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 1900;
            padding: 22px;
            overflow-y: auto;
            background: rgba(255,255,255,0.94);
            backdrop-filter: blur(9px);
        }

        .compare-modal.show {
            display: block;
        }

        .compare-inner {
            max-width: 1200px;
            margin: 0 auto;
        }

        .compare-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 14px;
        }

        .compare-title {
            color: #16293f;
            font-size: 15px;
            font-weight: 800;
        }

        .compare-title i {
            color: var(--cyan);
            margin-right: 8px;
        }

        .compare-subtitle {
            margin-top: 5px;
            color: #5c748c;
            font-size: 10.5px;
            overflow-wrap: anywhere;
        }

        .compare-head-actions {
            display: flex;
            gap: 8px;
        }

        .compare-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .compare-cell {
            padding: 10px;
            background: #eef3f9;
            border: 1px solid #ced7e3;
            border-radius: 13px;
        }

        .compare-cell img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 8px;
            cursor: zoom-in;
        }

        .compare-cell-label {
            margin-top: 9px;
            color: #22364e;
            font-size: 10.5px;
            line-height: 1.7;
            text-align: center;
            overflow-wrap: anywhere;
        }

        .modal-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #16293f;
            background: rgba(255,255,255,0.94);
            border: 1px solid #cfd7e2;
            border-radius: 50%;
            font-size: 15px;
            cursor: pointer;
            transition: .2s ease;
            z-index: 5;
        }

        .modal-nav:hover {
            background: rgba(22, 120, 236, .45);
            border-color: var(--cyan);
        }

        .modal-prev {
            left: -62px;
        }

        .modal-next {
            right: -62px;
        }

        .modal-counter {
            color: #5c748c;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: .6px;
        }

        /* --- Panel perbandingan otomatis --- */

        .auto-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 16px 26px;
            margin-bottom: 16px;
        }

        .auto-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .auto-label {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            color: #33527a;
            font-size: 11px;
            font-weight: 700;
        }

        .auto-label i {
            color: var(--cyan);
            font-size: 10px;
        }

        .auto-select {
            min-width: 210px;
            max-width: 100%;
        }

        .auto-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
        }

        .auto-pill {
            padding: 8px 13px;
            color: #22364e;
            background: #ffffff;
            border: 1px solid #ced7e3;
            border-radius: 9px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: .2s ease;
        }

        .auto-pill:hover {
            color: #16293f;
            border-color: #ced8e3;
        }

        .auto-pill.active {
            color: #fff;
            background:
                linear-gradient(
                    135deg,
                    rgba(22, 120, 236, .85),
                    rgba(29, 157, 196, .85)
                );
            border-color: transparent;
        }

        .auto-matrix-scroll {
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .auto-matrix {
            display: grid;
            gap: 10px;
            min-width: 640px;
        }

        .auto-row-label {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 12px;
            color: #16293f;
            background: rgba(22, 120, 236, .08);
            border: 1px solid rgba(39, 200, 229, .15);
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
        }

        .auto-row-label i {
            color: var(--cyan);
        }

        .auto-cell {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px;
            background: #eef3f9;
            border: 1px solid #ced7e3;
            border-radius: 12px;
            min-height: 150px;
            transition: .2s ease;
        }

        .auto-cell:hover {
            border-color: #cedae3;
        }

        .auto-cell img {
            display: block;
            width: auto;
            max-width: 100%;
            max-height: 260px;
            height: auto;
            cursor: zoom-in;
            border-radius: 6px;
        }

        .auto-cell-empty {
            color: #5c748c;
            font-size: 18px;
        }

        .auto-cell-cap {
            color: #5c748c;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: .06em;
            text-transform: uppercase;
        }

        .auto-cell-tag {
            padding: 3px 8px;
            color: #0a1524;
            background: linear-gradient(135deg, #27c8e5, #d7eae5);
            border-radius: 999px;
            font-size: 9px;
            font-weight: 800;
        }

        .auto-empty {
            padding: 22px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
            border: 1px dashed #ced7e3;
            border-radius: 12px;
        }

        @media (max-width: 1260px) {
            .modal-prev {
                left: 6px;
            }

            .modal-next {
                right: 6px;
            }
        }

        @media (max-width: 800px) {
            .compare-grid {
                grid-template-columns: 1fr;
            }
        }
        .peta-kota {
            height: 340px;
            margin-top: 18px;
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
            background: var(--surface-2);
        }

        .peta-kota .leaflet-popup-content-wrapper,
        .peta-kota .leaflet-popup-tip {
            background: var(--surface-3);
            color: var(--text);
        }

        .peta-kota .leaflet-popup-content {
            font-size: 12px;
            line-height: 1.6;
        }

        .peta-catatan {
            margin-top: 12px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.7;
        }
    </style>
    <link rel="stylesheet" href="assets/css/topbar.css?v=3">
</head>

<body>


<?php include __DIR__ . '/includes/topbar.php'; ?>

<!-- =====================================================
     KONTEN UTAMA
====================================================== -->

<main class="main">

    <div class="breadcrumb">
        <i class="fa-solid fa-house"></i>
        <span>Dasbor</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span>Atmosfer</span>
        <i class="fa-solid fa-chevron-right"></i>
        <span class="current">Pemantauan Kota</span>
    </div>

    <section class="hero">
        <div class="hero-content">

            <div class="eyebrow">
                <i class="fa-solid fa-satellite-dish"></i>
                BMKG Atmospheric Product
            </div>

            <h1>Pemantauan Atmosfer Kota</h1>

            <p>
                Halaman pemantauan grafik atmosfer berdasarkan wilayah.
                Pilih kota, produk, dan periode untuk mencari gambar yang
                tersedia di direktori City. Data gambar dibaca langsung
                dari folder aset project dan metadata digunakan apabila
                tersedia.
            </p>

        </div>
    </section>

    <!-- STATISTIK RINGKAS -->

    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-regular fa-images"></i>
            </div>

            <div>
                <div class="stat-value">
                    <?= number_format(count($allImages), 0, ',', '.') ?>
                </div>

                <div class="stat-label">
                    Total gambar yang terdeteksi di assets/city
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-location-dot"></i>
            </div>

            <div>
                <div class="stat-value">
                    <?= number_format(count($regionOptions), 0, ',', '.') ?>
                </div>

                <div class="stat-label">
                    Label wilayah yang teridentifikasi
                </div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="fa-solid fa-layer-group"></i>
            </div>

            <div>
                <div class="stat-value">
                    <?= number_format(count($productOptions), 0, ',', '.') ?>
                </div>

                <div class="stat-label">
                    Jenis produk yang terdeteksi
                </div>
            </div>
        </div>

    </div>

    <!-- FILTER -->

    <section class="section">

        <div class="section-heading">

            <div>
                <h2 class="section-title">
                    <i class="fa-solid fa-sliders"></i>
                    Filter Data City
                </h2>

                <div class="section-desc">
                    Tentukan filter untuk menampilkan gambar yang sesuai.
                </div>
            </div>

            <span class="section-icon">
                <i class="fa-solid fa-filter"></i>
            </span>

        </div>

        <form method="GET" action="city.php">

            <div class="filter-grid">

                <div class="field">
                    <label for="wilayah">Wilayah / Kota</label>

                    <select
                        name="wilayah"
                        id="wilayah"
                        class="input-control"
                    >
                        <option value="">Semua wilayah</option>

                        <?php foreach ($regionOptions as $region): ?>
                            <option
                                value="<?= city_h($region) ?>"
                                <?= $regionSelected === $region ? 'selected' : '' ?>
                            >
                                <?= city_h($region) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label
                        for="produk"
                        title="OLR = Outgoing Longwave Radiation (W/m2, proksi awan konvektif). PWAT = Precipitable Water (kg/m2, total uap air kolom)."
                    >Produk / Parameter</label>

                    <select
                        name="produk"
                        id="produk"
                        class="input-control"
                    >
                        <option value="">Semua produk</option>

                        <?php foreach ($productOptions as $product): ?>
                            <option
                                value="<?= city_h($product) ?>"
                                <?= $productSelected === $product ? 'selected' : '' ?>
                            >
                                <?= city_h($product) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label
                        for="periode"
                        title="Initial (init) = tanggal analisis NetCDF, yaitu titik awal perhitungan prakiraan."
                    >Periode (Tanggal Pencarian)</label>

                    <select
                        name="periode"
                        id="periode"
                        class="input-control"
                    >
                        <option value="">Semua periode</option>

                        <?php foreach ($periodOptions as $period): ?>
                            <option
                                value="<?= city_h($period) ?>"
                                <?= $periodSelected === $period ? 'selected' : '' ?>
                            >
                                <?= city_h(city_period_label($period)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="rata">Rata-rata (Running Mean)</label>

                    <select
                        name="rata"
                        id="rata"
                        class="input-control"
                    >
                        <option value="">Semua rata-rata</option>

                        <?php foreach ($meanOptions as $nilai => $labelMean): ?>
                            <option
                                value="<?= city_h($nilai) ?>"
                                <?= $rataSelected === $nilai ? 'selected' : '' ?>
                            >
                                <?= city_h($labelMean) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="filter-actions">

                <button
                    type="submit"
                    name="cari"
                    value="1"
                    class="btn btn-primary"
                >
                    <i class="fa-solid fa-magnifying-glass"></i>
                    Tampilkan Data
                </button>

                <a href="city.php" class="btn btn-secondary">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset Filter
                </a>

            </div>

        </form>

        <div class="status-line">
            <i class="fa-solid fa-circle-info"></i>

            <div>
                <strong>Status sumber data:</strong>
                <?= city_h($jsonStatus) ?>

                <br>

                <span>
                    Folder gambar yang dibaca:
                    <code>assets/city/</code>
                </span>

                <?php if (!$folderAvailable): ?>
                    <br>
                    <span style="color: #9c1111;">
                        Folder assets/city belum ditemukan.
                        Periksa nama folder dan lokasinya.
                    </span>
                <?php endif; ?>
            </div>
        </div>

    </section>

    <?php if ($petaKota): ?>

    <!-- PETA KOTA TERPANTAU -->

    <section class="section">

        <div class="section-heading">

            <div>
                <h2 class="section-title">
                    <i class="fa-solid fa-map-location-dot"></i>
                    Peta Kota Terpantau
                </h2>

                <div class="section-desc">
                    Klik titik untuk menampilkan gambar kota tersebut.
                    Titik berlingkar adalah kota yang sedang dipilih.
                </div>
            </div>

            <span class="section-icon">
                <i class="fa-solid fa-earth-asia"></i>
            </span>

        </div>

        <div id="petaKota" class="peta-kota" role="application"
             aria-label="Peta kota yang memiliki data pemantauan"></div>

        <?php if (!empty($kotaTanpaKoordinat)): ?>
            <div class="peta-catatan">
                Tanpa koordinat (tidak muncul di peta):
                <?= city_h(implode(', ', $kotaTanpaKoordinat)) ?>
            </div>
        <?php endif; ?>

    </section>

    <?php endif; ?>

    <?php if ($filterExecuted): ?>

    <!-- RINGKASAN HASIL PENCARIAN -->

    <section class="summary-panel">

        <h2 class="summary-city">
            <i class="fa-solid fa-city"></i>
            <span>
                <?= city_h($regionSelected !== '' ? $regionSelected : 'Semua Wilayah') ?>
            </span>
        </h2>

        <div class="summary-meta">

            <?php if ($productSelected !== ''): ?>
                <strong><?= city_h($productSelected) ?></strong>
                &middot;
            <?php endif; ?>

            <?php if ($periodSelected !== ''): ?>
                Init <strong><?= city_h(city_period_label($periodSelected)) ?></strong>
                &middot;
            <?php endif; ?>

            <?php if ($rataSelected !== ''): ?>
                Running mean <strong><?= city_h($rataSelected) ?> hari</strong>
                &middot;
            <?php endif; ?>

            <strong><?= number_format(count($filteredImages), 0, ',', '.') ?></strong>
            plot ditemukan

        </div>

        <?php if (!empty($activeChips)): ?>
            <div class="chip-row">

                <span class="chip-muted">Filter aktif:</span>

                <?php foreach ($activeChips as $chip): ?>
                    <span class="chip">
                        <?= city_h($chip['label']) ?>

                        <a
                            class="chip-x"
                            href="<?= city_h($chip['url']) ?>"
                            title="Hapus filter ini"
                        >
                            <i class="fa-solid fa-xmark"></i>
                        </a>
                    </span>
                <?php endforeach; ?>

            </div>
        <?php endif; ?>

        <div class="legend-row">
            <span>
                <b>OLR</b> &mdash; radiasi gelombang panjang puncak awan;
                nilai rendah umumnya menandai konveksi (awan bertumbuh) aktif.
            </span>

            <span>
                <b>PWAT</b> &mdash; precipitable water; total uap air di
                atmosfer sebagai indikator kelembapan udara.
            </span>
        </div>

    </section>

    <?php endif; ?>

    <?php if ($filterExecuted && count($filteredImages) > 1): ?>

    <!-- PERBANDINGAN OTOMATIS -->

    <section class="section" id="panelBandingkan">

        <div class="section-heading">

            <div>
                <h2 class="section-title">
                    <i class="fa-solid fa-table-cells-large"></i>
                    Perbandingan Otomatis
                </h2>

                <div class="section-desc">
                    Pilih kota dan tanggal init, lalu bandingkan produk
                    (OLR/PWAT) dan running mean secara berdampingan tanpa
                    memilih plot satu per satu. Klik plot untuk memperbesar.
                </div>
            </div>

            <span class="section-icon">
                <i class="fa-solid fa-code-compare"></i>
            </span>

        </div>

        <div class="auto-controls">

            <div class="auto-group">

                <label class="auto-label" for="autoCitySelect">
                    <i class="fa-solid fa-location-dot"></i>
                    Kota / Wilayah
                </label>

                <select
                    id="autoCitySelect"
                    class="input-control auto-select"
                >
                    <?php foreach ($autoRegions as $regionKey => $regionValue): ?>
                        <option
                            value="<?= city_h($regionKey) ?>"
                            <?= $regionKey === $autoCityDefault ? 'selected' : '' ?>
                        >
                            <?= city_h($regionKey) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

            </div>

            <div class="auto-group">

                <span class="auto-label">
                    <i class="fa-regular fa-calendar"></i>
                    Tanggal Init
                </span>

                <div class="auto-pills" id="autoInitPills">
                    <?php $autoPeriodIndex = 0; ?>

                    <?php foreach ($autoPeriods as $periodKey => $periodLabel): ?>

                        <button
                            type="button"
                            class="auto-pill<?= $autoPeriodIndex === 0 ? ' active' : '' ?>"
                            data-period="<?= city_h($periodKey) ?>"
                        >
                            <?= city_h($periodLabel) ?>
                        </button>

                        <?php $autoPeriodIndex++; ?>

                    <?php endforeach; ?>
                </div>

            </div>

        </div>

        <div class="auto-matrix-scroll">
            <div id="autoMatrixWrap"></div>
        </div>

    </section>

    <?php endif; ?>

    <!-- HASIL PENCARIAN -->

    <section class="section" id="hasil">

        <div class="section-heading">

            <div>
                <h2 class="section-title">
                    <i class="fa-solid fa-chart-area"></i>
                    Hasil Pemantauan City
                </h2>

                <div class="section-desc">
                    Gambar ditampilkan dari file yang ditemukan di direktori City.
                </div>
            </div>

            <span class="section-icon">
                <i class="fa-regular fa-images"></i>
            </span>

        </div>

        <?php if (!$filterExecuted): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                </div>

                <h3>Silakan pilih filter pemantauan</h3>

                <p>
                    Pilih wilayah, produk, atau periode terlebih dahulu,
                    lalu klik <strong>Tampilkan Data</strong>.
                    Gambar tidak ditampilkan sebelum pencarian dijalankan.
                </p>

                <?php if (!empty($quickPresets)): ?>

                    <div class="preset-row">

                        <span class="preset-label">
                            <i class="fa-solid fa-bolt"></i>
                            Coba cepat:
                        </span>

                        <?php foreach ($quickPresets as $preset): ?>
                            <a
                                class="preset-btn"
                                href="<?= city_h($preset['url']) ?>"
                            >
                                <i class="fa-solid fa-location-dot"></i>
                                <?= city_h($preset['city']) ?>
                            </a>
                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        <?php elseif (count($filteredImages) === 0): ?>

            <div class="empty-state">

                <div class="empty-state-icon">
                    <i class="fa-solid fa-folder-open"></i>
                </div>

                <h3>Data gambar tidak ditemukan</h3>

                <p>
                    Tidak ada gambar yang cocok dengan kombinasi filter
                    yang dipilih. Periksa kembali wilayah, produk,
                    periode, atau kata kunci. Sistem tidak mengabaikan
                    filter secara otomatis.
                </p>

                <div style="margin-top: 18px;">
                    <a href="city.php" class="btn btn-secondary btn-compact">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset Filter
                    </a>
                </div>

            </div>

        <?php else: ?>

            <div class="results-top">

                <div class="result-count">
                    Ditemukan
                    <strong>
                        <?= number_format(count($filteredImages), 0, ',', '.') ?>
                        gambar
                    </strong>
                    sesuai filter.
                </div>

                <div class="sort-group">

                    <span class="sort-label">
                        <i class="fa-solid fa-arrow-down-wide-short"></i>
                        Urut
                    </span>

                    <a
                        class="sort-link <?= $sortSelected === '' ? 'active' : '' ?>"
                        href="<?= city_h(city_filter_url(['urut'])) ?>"
                    >
                        Terbaru
                    </a>

                    <a
                        class="sort-link <?= $sortSelected === 'mean' ? 'active' : '' ?>"
                        href="<?= city_h(city_filter_url([], ['urut' => 'mean'])) ?>"
                    >
                        Mean 1&rarr;10
                    </a>

                    <a
                        class="sort-link <?= $sortSelected === 'nama' ? 'active' : '' ?>"
                        href="<?= city_h(city_filter_url([], ['urut' => 'nama'])) ?>"
                    >
                        Nama A&ndash;Z
                    </a>

                </div>

                <?php if (count($filteredImages) > 1): ?>
                    <a
                        href="#panelBandingkan"
                        class="btn btn-secondary btn-compact"
                    >
                        <i class="fa-solid fa-table-cells-large"></i>
                        Perbandingan
                    </a>
                <?php endif; ?>

                <a href="#hasil" class="btn btn-secondary btn-compact">
                    <i class="fa-solid fa-sliders"></i>
                    Ubah Filter
                </a>

            </div>

            <div class="image-grid">

                <?php foreach ($filteredImages as $index => $image): ?>

                    <article class="image-card">

                        <div class="image-stage">

                            <img
                                src="<?= city_h($image['url']) ?>"
                                alt="<?= city_h($image['filename']) ?>"
                                loading="lazy"
                                data-modal-src="<?= city_h($image['url']) ?>"
                                data-modal-title="<?= city_h($image['filename']) ?>"
                                data-modal-index="<?= $index ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"
                            >

                            <div
                                class="img-fallback"
                                style="display:none;"
                            >
                                <i class="fa-regular fa-image"></i>
                                <br>
                                Gambar tidak dapat dimuat.
                                <br>
                                Periksa kembali nama dan lokasi file.
                            </div>

                        </div>

                        <div class="image-card-body">

                            <div class="badge-row">

                                <span class="badge">
                                    <i class="fa-solid fa-location-dot"></i>
                                    <?= city_h($image['region']) ?>
                                </span>

                                <?php
                                $badgeProduct = $image['product'];

                                $badgeClass =
                                    $badgeProduct === 'OLR' ? 'product-olr' :
                                    ($badgeProduct === 'PWAT' ? 'product-pwat' : 'secondary');

                                $badgeIcon =
                                    $badgeProduct === 'PWAT' ? 'fa-droplet' : 'fa-cloud';
                                ?>

                                <span class="badge <?= $badgeClass ?>">
                                    <i class="fa-solid <?= $badgeIcon ?>"></i>
                                    <?= city_h($badgeProduct) ?>
                                </span>

                                <?php if ($image['mean'] !== ''): ?>
                                    <span class="badge mean-badge">
                                        <i class="fa-solid fa-wave-square"></i>
                                        Mean <?= city_h($image['mean']) ?> hr
                                    </span>
                                <?php endif; ?>

                                <?php if ($image['period'] !== ''): ?>
                                    <span class="badge init-badge">
                                        <i class="fa-regular fa-calendar"></i>
                                        Init <?= city_h($image['period']) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (
                                    $latestFilteredPeriod !== ''
                                    && $image['period'] === $latestFilteredPeriod
                                ): ?>
                                    <span class="badge newest-badge">
                                        <i class="fa-solid fa-bolt"></i>
                                        Terbaru
                                    </span>
                                <?php endif; ?>

                            </div>

                            <h3 class="image-title">
                                <?= city_h($image['filename']) ?>
                            </h3>

                            <div class="filename">
                                <i class="fa-regular fa-folder-open"></i>
                                <?= city_h($image['relative']) ?>
                            </div>

                            <div class="filename" style="margin-top: 6px;">
                                <i class="fa-regular fa-calendar"></i>
                                Periode:
                                <?= city_h($image['period_label']) ?>
                            </div>

                            <p class="image-description">
                                <?= city_h($image['description']) ?>
                            </p>

                            <div class="image-actions">

                                <?php if (count($filteredImages) > 1): ?>
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-compact btn-compare"
                                        data-compare-index="<?= $index ?>"
                                        onclick="toggleCityCompare(<?= $index ?>)"
                                    >
                                        <i class="fa-solid fa-table-cells-large"></i>
                                        Bandingkan
                                    </button>
                                <?php endif; ?>

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-compact"
                                    onclick="openCityImage(
                                        <?= json_encode($image['url'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                                        <?= json_encode($image['filename'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>,
                                        <?= $index ?>
                                    )"
                                >
                                    <i class="fa-solid fa-expand"></i>
                                    Perbesar
                                </button>

                                <a
                                    href="<?= city_h($image['url']) ?>"
                                    target="_blank"
                                    rel="noopener"
                                    class="btn btn-primary btn-compact"
                                >
                                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                    Buka Gambar
                                </a>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="note">
            <i class="fa-solid fa-circle-info"></i>

            <div>
                <strong>Catatan data:</strong>
                nama wilayah, produk, dan periode diidentifikasi dari
                metadata yang cocok atau dari struktur folder dan nama
                file. Jika label masih bertuliskan
                <em>Wilayah belum teridentifikasi</em> atau
                <em>Produk lainnya</em>, periksa format nama file dan
                struktur <code>data/city.json</code>. Halaman ini tidak
                membuat nilai prakiraan atau kesimpulan cuaca baru dari
                gambar saja.
            </div>
        </div>

    </section>

    <footer class="footer">
        <span>
            <i class="fa-solid fa-cloud-sun"></i>
            BMKG KEMAYORAN · Sistem Pemantauan Cuaca
        </span>

        <span>
            Pemantauan Atmosfer Kota
        </span>
    </footer>

</main>

<!-- =====================================================
     MODAL PREVIEW GAMBAR
====================================================== -->

<div class="modal" id="imageModal" aria-hidden="true">

    <div class="modal-content">

        <button
            type="button"
            class="modal-close"
            id="modalClose"
            aria-label="Tutup gambar"
        >
            <i class="fa-solid fa-xmark"></i>
        </button>

        <img
            src=""
            alt=""
            class="modal-image"
            id="modalImage"
        >

        <div class="modal-caption" id="modalCaption"></div>

        <div class="modal-counter" id="modalCounter"></div>

        <button
            type="button"
            class="modal-nav modal-prev"
            id="modalPrev"
            aria-label="Gambar sebelumnya"
        >
            <i class="fa-solid fa-chevron-left"></i>
        </button>

        <button
            type="button"
            class="modal-nav modal-next"
            id="modalNext"
            aria-label="Gambar berikutnya"
        >
            <i class="fa-solid fa-chevron-right"></i>
        </button>

    </div>

</div>

<!-- =====================================================
     MODAL PERBANDINGAN PLOT (BERDAMPINGAN)
====================================================== -->

<div class="compare-modal" id="compareModal" aria-hidden="true">

    <div class="compare-inner">

        <div class="compare-head">

            <div>
                <div class="compare-title">
                    <i class="fa-solid fa-table-cells-large"></i>
                    Perbandingan Plot
                </div>

                <div class="compare-subtitle" id="compareSubtitle"></div>
            </div>

            <div class="compare-head-actions">

                <button
                    type="button"
                    class="btn btn-secondary btn-compact"
                    id="compareClear"
                >
                    <i class="fa-solid fa-eraser"></i>
                    Kosongkan
                </button>

                <button
                    type="button"
                    class="btn btn-primary btn-compact"
                    id="compareClose"
                >
                    <i class="fa-solid fa-xmark"></i>
                    Tutup
                </button>

            </div>

        </div>

        <div class="compare-grid" id="compareGrid"></div>

    </div>

</div>

<!-- =====================================================
     TRAY PILIHAN PERBANDINGAN
====================================================== -->

<div class="compare-tray" id="compareTray" hidden>

    <span class="compare-tray-label" id="compareTrayLabel"></span>

    <div class="compare-thumbs" id="compareThumbs"></div>

    <div class="compare-tray-actions">

        <button
            type="button"
            class="btn btn-primary btn-compact"
            id="compareShow"
        >
            <i class="fa-solid fa-table-cells-large"></i>
            Lihat Berdampingan
        </button>

        <button
            type="button"
            class="btn btn-secondary btn-compact"
            id="compareReset"
        >
            <i class="fa-solid fa-rotate-left"></i>
            Reset
        </button>

    </div>

</div>

<script>
    /* =====================================================
       NAVIGASI MOBILE DAN DROPDOWN
    ===================================================== */

    const navToggle = document.getElementById('navToggle');
    const mainNav = document.getElementById('mainNav');

    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            const isOpen = mainNav.classList.toggle('show');

            navToggle.setAttribute(
                'aria-expanded',
                isOpen ? 'true' : 'false'
            );

            const icon = navToggle.querySelector('i');

            if (icon) {
                icon.className = isOpen
                    ? 'fa-solid fa-xmark'
                    : 'fa-solid fa-bars';
            }
        });
    }

    /* =====================================================
       MODAL GAMBAR
    ===================================================== */

    /* =====================================================
       DATA GAMBAR HASIL FILTER (MODAL + PERBANDINGAN)
    ===================================================== */

    const cityImages = <?= json_encode(
        $jsImages,
        JSON_HEX_TAG
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
        | JSON_HEX_AMP
        | JSON_UNESCAPED_UNICODE
    ) ?>;

    const MAX_COMPARE = 4;

    let cityModalIndex = -1;
    let cityCompare = [];

    const imageModal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    const modalCaption = document.getElementById('modalCaption');
    const modalClose = document.getElementById('modalClose');
    const modalCounter = document.getElementById('modalCounter');
    const modalPrev = document.getElementById('modalPrev');
    const modalNext = document.getElementById('modalNext');

    const compareTray = document.getElementById('compareTray');
    const compareModal = document.getElementById('compareModal');
    const compareThumbs = document.getElementById('compareThumbs');
    const compareTrayLabel = document.getElementById('compareTrayLabel');
    const compareGrid = document.getElementById('compareGrid');
    const compareSubtitle = document.getElementById('compareSubtitle');

    function cityCaption(item) {
        const parts = [];

        if (item.region) {
            parts.push(item.region);
        }

        if (item.product) {
            parts.push(item.product);
        }

        if (item.mean) {
            parts.push('Mean ' + item.mean + ' hari');
        }

        if (item.period) {
            parts.push(item.period);
        }

        return parts.join(' · ');
    }

    function cityApplyModal(index) {
        if (index < 0 || index >= cityImages.length) {
            return;
        }

        cityModalIndex = index;

        const item = cityImages[index];
        const caption = cityCaption(item);

        modalImage.src = item.url;
        modalImage.alt = item.file;

        modalCaption.textContent = caption !== ''
            ? item.file + ' — ' + caption
            : item.file;

        if (modalCounter) {
            modalCounter.textContent = cityImages.length > 1
                ? (index + 1) + ' / ' + cityImages.length
                    + (item.latest ? ' · init terbaru' : '')
                : '';
        }

        const showNav = cityImages.length > 1;

        if (modalPrev) {
            modalPrev.style.display = showNav ? '' : 'none';
        }

        if (modalNext) {
            modalNext.style.display = showNav ? '' : 'none';
        }
    }

    function cityOpenZoomAt(index) {
        if (!cityImages[index]) {
            return;
        }

        cityApplyModal(index);

        imageModal.classList.add('show');
        imageModal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
    }

    function openCityImage(src, title, index) {
        if (typeof index === 'number' && cityImages[index]) {
            cityOpenZoomAt(index);

            return;
        }

        cityModalIndex = -1;

        modalImage.src = src;
        modalImage.alt = title;
        modalCaption.textContent = title;

        if (modalCounter) {
            modalCounter.textContent = '';
        }

        if (modalPrev) {
            modalPrev.style.display = 'none';
        }

        if (modalNext) {
            modalNext.style.display = 'none';
        }

        imageModal.classList.add('show');
        imageModal.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
    }

    function closeCityImage() {
        imageModal.classList.remove('show');
        imageModal.setAttribute('aria-hidden', 'true');

        modalImage.src = '';
        modalImage.alt = '';
        modalCaption.textContent = '';

        if (modalCounter) {
            modalCounter.textContent = '';
        }

        document.body.style.overflow = '';
    }

    function cityShiftModal(step) {
        if (cityModalIndex < 0 || cityImages.length < 2) {
            return;
        }

        const target =
            (cityModalIndex + step + cityImages.length) % cityImages.length;

        cityApplyModal(target);
    }

    document.querySelectorAll('[data-modal-src]').forEach(function (img) {
        img.addEventListener('click', function () {
            const rawIndex = img.getAttribute('data-modal-index');

            openCityImage(
                img.getAttribute('data-modal-src'),
                img.getAttribute('data-modal-title') || img.alt,
                rawIndex !== null && rawIndex !== ''
                    ? parseInt(rawIndex, 10)
                    : undefined
            );
        });
    });

    if (modalClose) {
        modalClose.addEventListener('click', closeCityImage);
    }

    if (modalPrev) {
        modalPrev.addEventListener('click', function () {
            cityShiftModal(-1);
        });
    }

    if (modalNext) {
        modalNext.addEventListener('click', function () {
            cityShiftModal(1);
        });
    }

    if (imageModal) {
        imageModal.addEventListener('click', function (event) {
            if (event.target === imageModal) {
                closeCityImage();
            }
        });
    }

    /* =====================================================
       MODE PERBANDINGAN (PILIH MAKSIMAL 4 PLOT)
    ===================================================== */

    function renderCompareTray() {
        if (!compareTray) {
            return;
        }

        document.querySelectorAll('[data-compare-index]').forEach(
            function (button) {
                const index = parseInt(
                    button.getAttribute('data-compare-index'),
                    10
                );

                button.classList.toggle(
                    'active',
                    cityCompare.indexOf(index) !== -1
                );
            }
        );

        if (cityCompare.length === 0) {
            compareTray.hidden = true;

            return;
        }

        compareTray.hidden = false;

        if (compareTrayLabel) {
            compareTrayLabel.textContent =
                cityCompare.length + ' / ' + MAX_COMPARE + ' dipilih';
        }

        if (compareThumbs) {
            compareThumbs.innerHTML = '';

            cityCompare.forEach(function (index) {
                const item = cityImages[index];

                if (!item) {
                    return;
                }

                const thumb = document.createElement('img');

                thumb.src = item.url;
                thumb.alt = item.file;
                thumb.title = 'Klik untuk menghapus dari perbandingan';

                thumb.addEventListener('click', function () {
                    toggleCityCompare(index);
                });

                compareThumbs.appendChild(thumb);
            });
        }
    }

    function toggleCityCompare(index) {
        const position = cityCompare.indexOf(index);

        if (position !== -1) {
            cityCompare.splice(position, 1);
        } else {
            if (cityCompare.length >= MAX_COMPARE) {
                return;
            }

            cityCompare.push(index);
        }

        renderCompareTray();
    }

    function closeCompare() {
        if (!compareModal) {
            return;
        }

        compareModal.classList.remove('show');
        compareModal.setAttribute('aria-hidden', 'true');
    }

    function openCompare() {
        if (!compareModal || cityCompare.length === 0) {
            return;
        }

        if (compareSubtitle) {
            compareSubtitle.textContent = cityCompare.map(function (index) {
                const item = cityImages[index];

                return item.region + ' · ' + item.product
                    + (item.mean ? ' · mean ' + item.mean + ' hari' : '')
                    + ' · init ' + item.period;
            }).join('    |    ');
        }

        if (compareGrid) {
            compareGrid.innerHTML = '';

            cityCompare.forEach(function (index) {
                const item = cityImages[index];

                const cell = document.createElement('div');
                cell.className = 'compare-cell';

                const cellImage = document.createElement('img');

                cellImage.src = item.url;
                cellImage.alt = item.file;
                cellImage.title = item.file;

                cellImage.addEventListener('click', function () {
                    closeCompare();
                    cityOpenZoomAt(index);
                });

                const cellLabel = document.createElement('div');
                cellLabel.className = 'compare-cell-label';
                cellLabel.textContent =
                    cityCaption(item) + ' — ' + item.file;

                cell.appendChild(cellImage);
                cell.appendChild(cellLabel);

                compareGrid.appendChild(cell);
            });
        }

        compareModal.classList.add('show');
        compareModal.setAttribute('aria-hidden', 'false');
    }

    document.querySelectorAll('[data-compare-index]').forEach(
        function (button) {
            button.addEventListener('click', function () {
                toggleCityCompare(
                    parseInt(button.getAttribute('data-compare-index'), 10)
                );
            });
        }
    );

    const compareShow = document.getElementById('compareShow');
    const compareReset = document.getElementById('compareReset');
    const compareClear = document.getElementById('compareClear');
    const compareCloseBtn = document.getElementById('compareClose');

    if (compareShow) {
        compareShow.addEventListener('click', openCompare);
    }

    if (compareReset) {
        compareReset.addEventListener('click', function () {
            cityCompare = [];

            renderCompareTray();
        });
    }

    if (compareClear) {
        compareClear.addEventListener('click', function () {
            cityCompare = [];

            renderCompareTray();
            closeCompare();
        });
    }

    if (compareCloseBtn) {
        compareCloseBtn.addEventListener('click', closeCompare);
    }

    if (compareModal) {
        compareModal.addEventListener('click', function (event) {
            if (event.target === compareModal) {
                closeCompare();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        const zoomOpen = imageModal
            && imageModal.classList.contains('show');

        const compareOpen = compareModal
            && compareModal.classList.contains('show');

        if (event.key === 'Escape') {
            if (zoomOpen) {
                closeCityImage();
            } else if (compareOpen) {
                closeCompare();
            }
        }

        if (zoomOpen && event.key === 'ArrowLeft') {
            cityShiftModal(-1);
        }

        if (zoomOpen && event.key === 'ArrowRight') {
            cityShiftModal(1);
        }
    });

    /* =====================================================
       PERBANDINGAN OTOMATIS (MATRIKS PRODUK x MEAN)
    ===================================================== */

    const autoCitySelect = document.getElementById('autoCitySelect');
    const autoInitPills = document.getElementById('autoInitPills');
    const autoMatrixWrap = document.getElementById('autoMatrixWrap');

    let autoPeriod = '';

    if (autoInitPills) {
        const activePill = autoInitPills.querySelector('.auto-pill.active');

        autoPeriod = activePill
            ? (activePill.getAttribute('data-period') || '')
            : '';
    }

    /* Peta kunci gambar: kota|init|produk|mean -> indeks cityImages. */
    const autoIndex = {};

    cityImages.forEach(function (item, index) {
        if (
            item.mean === ''
            || !item.periodRaw
            || item.region === ''
        ) {
            return;
        }

        const key = item.region + '|' + item.periodRaw + '|'
            + item.product + '|' + item.mean;

        if (!(key in autoIndex)) {
            autoIndex[key] = index;
        }
    });

    function cityProductIcon(product) {
        return product === 'PWAT' ? 'fa-droplet' : 'fa-cloud';
    }

    function cityProductOrder(a, b) {
        const preferred = ['OLR', 'PWAT'];

        const ia = preferred.indexOf(a);
        const ib = preferred.indexOf(b);

        if (ia !== -1 || ib !== -1) {
            return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib);
        }

        return a.localeCompare(b);
    }

    function renderAutoMatrix() {
        if (!autoMatrixWrap || !autoCitySelect) {
            return;
        }

        const city = autoCitySelect.value;

        /* Union mean & produk untuk kota terpilih agar lebar kolom
           konsisten ketika ganti tanggal init. */
        const meanSet = [];
        const productSet = [];

        cityImages.forEach(function (item) {
            if (item.region !== city) {
                return;
            }

            if (item.mean !== '' && meanSet.indexOf(item.mean) === -1) {
                meanSet.push(item.mean);
            }

            if (
                item.product !== ''
                && productSet.indexOf(item.product) === -1
            ) {
                productSet.push(item.product);
            }
        });

        meanSet.sort(function (a, b) {
            return (parseInt(a, 10) || 0) - (parseInt(b, 10) || 0);
        });

        productSet.sort(cityProductOrder);

        if (meanSet.length === 0 || productSet.length === 0) {
            autoMatrixWrap.innerHTML = '';

            const emptyBox = document.createElement('div');
            emptyBox.className = 'auto-empty';
            emptyBox.textContent =
                'Perbandingan otomatis belum tersedia untuk kombinasi ini.';

            autoMatrixWrap.appendChild(emptyBox);

            return;
        }

        const matrix = document.createElement('div');
        matrix.className = 'auto-matrix';
        matrix.style.gridTemplateColumns =
            'minmax(150px, auto) repeat(' + meanSet.length + ', minmax(0, 1fr))';

        productSet.forEach(function (product) {
            const rowLabel = document.createElement('div');
            rowLabel.className = 'auto-row-label';

            const rowIcon = document.createElement('i');
            rowIcon.className = 'fa-solid ' + cityProductIcon(product);

            rowLabel.appendChild(rowIcon);
            rowLabel.appendChild(document.createTextNode(' ' + product));

            matrix.appendChild(rowLabel);

            meanSet.forEach(function (mean) {
                const key = city + '|' + autoPeriod + '|'
                    + product + '|' + mean;

                const index = typeof autoIndex[key] === 'number'
                    ? autoIndex[key]
                    : -1;

                const cellBox = document.createElement('div');
                cellBox.className = 'auto-cell';

                const cap = document.createElement('span');
                cap.className = 'auto-cell-cap';
                cap.textContent = 'Rata-rata ' + mean + ' hari';
                cellBox.appendChild(cap);

                if (index === -1) {
                    cellBox.classList.add('auto-cell-empty');
                    cellBox.textContent = '—';
                } else {
                    const item = cityImages[index];

                    const cellImg = document.createElement('img');

                    cellImg.src = item.url;
                    cellImg.alt = item.file;
                    cellImg.title = item.file;
                    cellImg.loading = 'lazy';

                    cellImg.addEventListener('click', function () {
                        cityOpenZoomAt(index);
                    });

                    cellBox.appendChild(cellImg);

                    if (item.latest) {
                        const cellTag = document.createElement('span');
                        cellTag.className = 'auto-cell-tag';
                        cellTag.textContent = 'init terbaru';

                        cellBox.appendChild(cellTag);
                    }
                }

                matrix.appendChild(cellBox);
            });
        });

        autoMatrixWrap.innerHTML = '';
        autoMatrixWrap.appendChild(matrix);
    }

    if (autoInitPills) {
        autoInitPills.querySelectorAll('.auto-pill').forEach(
            function (pill) {
                pill.addEventListener('click', function () {
                    autoInitPills.querySelectorAll('.auto-pill').forEach(
                        function (other) {
                            other.classList.remove('active');
                        }
                    );

                    pill.classList.add('active');

                    autoPeriod = pill.getAttribute('data-period') || '';

                    renderAutoMatrix();
                });
            }
        );
    }

    if (autoCitySelect) {
        autoCitySelect.addEventListener('change', renderAutoMatrix);
    }

    renderAutoMatrix();

    /* =====================================================
       SETELAH FILTER DIKIRIM, ARAHKAN KE BAGIAN HASIL
    ===================================================== */

    const query = new URLSearchParams(window.location.search);

    if (query.has('cari')) {
        /* Panel perbandingan datang lebih dulu daripada galeri gambar,
         * jadi arahkan ke panel itu bila ada agar grafik ikut terlihat. */
        const resultSection = document.getElementById('panelBandingkan')
            || document.getElementById('hasil');

        if (resultSection) {
            window.addEventListener('load', function () {
                resultSection.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        }
    }
</script>

<?php if ($petaKota): ?>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    (function () {
        const kota = <?= json_encode(
            $petaKota,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        const kotaDipilih = <?= json_encode(
            $regionSelected,
            JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        const peta = L.map('petaKota', {
            scrollWheelZoom: false,
            center: [-2.5, 118],
            zoom: 5
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '&copy; OpenStreetMap'
        }).addTo(peta);

        const selectWilayah = document.getElementById('wilayah');
        const tombolCari = document.querySelector('button[name="cari"]');

        function isiPopup(item) {
            const frag = document.createDocumentFragment();

            const judul = document.createElement('b');
            judul.textContent = item.nama;
            frag.appendChild(judul);
            frag.appendChild(document.createElement('br'));

            const ket = document.createElement('span');
            ket.textContent = (item.provinsi ? item.provinsi + ' · ' : '')
                + item.jumlah + ' gambar';
            frag.appendChild(ket);
            frag.appendChild(document.createElement('br'));

            const koordinat = document.createElement('small');
            koordinat.textContent =
                item.lat.toFixed(3) + '°, ' + item.lng.toFixed(3) + '°';
            frag.appendChild(koordinat);

            return frag;
        }

        let penandaDipilih = null;

        kota.forEach(function (item) {
            const terpilih = item.nama === kotaDipilih;

            const penanda = L.circleMarker([item.lat, item.lng], {
                radius: terpilih ? 11 : 7,
                color: terpilih ? '#0d94ad' : '#1678ec',
                weight: terpilih ? 3 : 1.5,
                fillColor: '#129267',
                fillOpacity: .8
            }).addTo(peta);

            penanda.bindPopup(isiPopup(item));

            penanda.on('click', function () {
                if (!selectWilayah || !tombolCari) {
                    return;
                }

                selectWilayah.value = item.nama;
                tombolCari.click();
            });

            if (terpilih) {
                penandaDipilih = penanda;
            }
        });

        if (penandaDipilih) {
            peta.setView(penandaDipilih.getLatLng(), 7);
            penandaDipilih.openPopup();
        }
    }());
</script>

<?php endif; ?>

</body>
</html>