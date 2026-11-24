<?php
/* =========================================================
   DETEKSI LOKASI OTOMATIS
   Koordinat perangkat -> alamat (OpenStreetMap Nominatim)
   -> kode wilayah ADM4 (Wilayah.id) -> prakiraan BMKG.
========================================================= */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../includes/bmkg_data.php';

function lokasiJson($success, $message, $data = [])
{
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    exit;
}

function normalNama($teks)
{
    $teks = strtolower(trim((string)$teks));
    $teks = str_replace(['&', '/'], ' ', $teks);

    $frasa = [
        'daerah khusus ibukota', 'daerah istimewa', 'kota administrasi',
        'kabupaten administrasi', 'provinsi', 'kabupaten', 'kota',
        'kecamatan', 'kelurahan', 'desa', 'kab.'
    ];

    $teks = str_replace($frasa, ' ', $teks);
    $teks = preg_replace('/[^a-z0-9]+/', ' ', $teks);
    $teks = preg_replace('/\s+/', ' ', $teks);

    return trim($teks);
}

function miripNama($a, $b)
{
    $asliA = strtolower(trim((string)$a));
    $asliB = strtolower(trim((string)$b));

    if ($asliA !== '' && $asliA === $asliB) {
        return 110;
    }

    $a = normalNama($a);
    $b = normalNama($b);

    if ($a === '' || $b === '') {
        return 0;
    }

    if ($a === $b) {
        return 100;
    }

    if (strlen($a) >= 4 && strlen($b) >= 4) {
        if (strpos($a, $b) !== false || strpos($b, $a) !== false) {
            return 92;
        }
    }

    similar_text($a, $b, $persen);

    return (float)$persen;
}

function pilihWilayah($kandidat, $daftar)
{
    $kandidat = array_values(array_unique(array_filter(array_map(function ($v) {
        return trim((string)$v);
    }, (array)$kandidat), function ($v) {
        return $v !== '';
    })));

    $terbaik = null;
    $skorTerbaik = 0;

    foreach ($kandidat as $nama) {
        foreach ($daftar as $item) {
            $skor = miripNama($nama, $item['name'] ?? '');

            if ($skor > $skorTerbaik) {
                $skorTerbaik = $skor;
                $terbaik = $item;
            }
        }
    }

    return $skorTerbaik >= 82 ? $terbaik : null;
}

function ambilWilayah($path, $key)
{
    $url = 'https://wilayah.id/api/' . ltrim($path, '/');
    $respons = bmkg_ambil_json($url, 'wilayah_' . $key, 604800);

    if (!is_array($respons['data']) || !isset($respons['data']['data']) || !is_array($respons['data']['data'])) {
        return [];
    }

    return $respons['data']['data'];
}

$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lon = isset($_GET['lon']) ? (float)$_GET['lon'] : null;

if ($lat === null || $lon === null || $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    lokasiJson(false, 'Koordinat lokasi tidak valid.');
}

$latBulat = round($lat, 3);
$lonBulat = round($lon, 3);

$urlNominatim = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2'
    . '&lat=' . rawurlencode((string)$latBulat)
    . '&lon=' . rawurlencode((string)$lonBulat)
    . '&addressdetails=1&zoom=18&accept-language=id';

$responsAlamat = bmkg_ambil_json(
    $urlNominatim,
    'alamat_' . str_replace(['-', '.'], '_', $latBulat . '_' . $lonBulat),
    86400
);

if (!is_array($responsAlamat['data']) || empty($responsAlamat['data']['address'])) {
    lokasiJson(false, 'Alamat lokasi tidak dapat dibaca. Silakan pilih wilayah secara manual.', [
        'lat' => $lat,
        'lon' => $lon
    ]);
}

$alamat = $responsAlamat['data']['address'];
$negara = strtolower((string)($alamat['country_code'] ?? ''));

if ($negara !== '' && $negara !== 'id') {
    lokasiJson(false, 'Lokasi perangkat berada di luar Indonesia. Silakan pilih wilayah secara manual.');
}

$kandidatProvinsi = [
    $alamat['state'] ?? '',
    $alamat['province'] ?? '',
    $alamat['region'] ?? '',
    $alamat['city'] ?? ''
];

$provinsiList = ambilWilayah('provinces.json', 'provinces');
$provinsi = pilihWilayah($kandidatProvinsi, $provinsiList);

if ($provinsi === null) {
    lokasiJson(false, 'Provinsi lokasi tidak dapat dipetakan ke kode wilayah. Silakan pilih manual.', [
        'alamat' => $alamat
    ]);
}

$kodeProvinsi = (string)$provinsi['code'];
$namaProvinsi = (string)$provinsi['name'];
$regencyList = ambilWilayah('regencies/' . $kodeProvinsi . '.json', 'regencies_' . $kodeProvinsi);

/* Jakarta: Nominatim memakai city = DKI Jakarta dan city_district = kota administrasi. */
$kandidatKabupaten = [];

if (
    miripNama($alamat['city'] ?? '', $namaProvinsi) >= 82 ||
    miripNama($alamat['state'] ?? '', $alamat['city'] ?? '') >= 95
) {
    $kandidatKabupaten[] = $alamat['city_district'] ?? '';
}

function lengkapiNama($nama, $awalan)
{
    $nama = trim((string)$nama);

    if ($nama === '' || preg_match('/^(kota|kabupaten)\b/i', $nama)) {
        return $nama;
    }

    return $awalan . ' ' . $nama;
}

$kandidatKabupaten = array_merge($kandidatKabupaten, [
    lengkapiNama($alamat['city'] ?? '', 'Kota'),
    lengkapiNama($alamat['county'] ?? '', 'Kabupaten'),
    $alamat['city'] ?? '',
    $alamat['county'] ?? '',
    $alamat['city_district'] ?? '',
    $alamat['municipality'] ?? ''
]);

$kabupaten = pilihWilayah($kandidatKabupaten, $regencyList);

if ($kabupaten === null) {
    lokasiJson(false, 'Kabupaten/kota lokasi tidak dapat dipetakan. Silakan pilih manual.', [
        'alamat' => $alamat,
        'provinsi' => $namaProvinsi
    ]);
}

$kodeKabupaten = (string)$kabupaten['code'];
$namaKabupaten = (string)$kabupaten['name'];
$districtList = ambilWilayah('districts/' . $kodeKabupaten . '.json', 'districts_' . $kodeKabupaten);

$kandidatKecamatan = [
    $alamat['district'] ?? '',
    $alamat['city_district'] ?? '',
    $alamat['municipality'] ?? '',
    $alamat['town'] ?? '',
    $alamat['suburb'] ?? ''
];

$kecamatan = pilihWilayah($kandidatKecamatan, $districtList);
$perkiraan = false;

$desa = null;

if ($kecamatan !== null) {
    $kodeKecamatan = (string)$kecamatan['code'];
    $namaKecamatan = (string)$kecamatan['name'];
    $villageList = ambilWilayah('villages/' . $kodeKecamatan . '.json', 'villages_' . $kodeKecamatan);

    $kandidatDesa = array_values(array_filter([
        $alamat['village'] ?? '',
        $alamat['suburb'] ?? '',
        $alamat['quarter'] ?? '',
        $alamat['hamlet'] ?? '',
        $alamat['neighbourhood'] ?? '',
        $alamat['city_block'] ?? '',
        $alamat['town'] ?? ''
    ], function ($v) {
        return trim((string)$v) !== '' && !preg_match('/^(rw|rt)\s*\d+/i', trim((string)$v));
    }));

    $desa = pilihWilayah($kandidatDesa, $villageList);
}

/* Jika kecamatan/desa tidak cocok, coba cari desa di seluruh kecamatan kabupaten. */
if ($desa === null && !empty($districtList)) {
    $kandidatDesa = array_values(array_filter([
        $alamat['village'] ?? '',
        $alamat['suburb'] ?? '',
        $alamat['quarter'] ?? '',
        $alamat['town'] ?? '',
        $alamat['neighbourhood'] ?? ''
    ], function ($v) {
        return trim((string)$v) !== '' && !preg_match('/^(rw|rt)\s*\d+/i', trim((string)$v));
    }));

    $batas = 0;

    foreach ($districtList as $calonKecamatan) {
        if ($batas++ >= 40) {
            break;
        }

        $kodeCalon = (string)($calonKecamatan['code'] ?? '');

        if ($kodeCalon === '') {
            continue;
        }

        $villageList = ambilWilayah('villages/' . $kodeCalon . '.json', 'villages_' . $kodeCalon);
        $calonDesa = pilihWilayah($kandidatDesa, $villageList);

        if ($calonDesa !== null) {
            $kecamatan = $calonKecamatan;
            $desa = $calonDesa;
            $perkiraan = true;
            break;
        }
    }
}

/*
 * Jika nama kelurahan tidak ada di basis data Wilayah.id, gunakan
 * kecamatan/kelurahan pertama pada kabupaten/kota yang sama dan
 * tandai sebagai hasil perkiraan (tetap jujur ke pengguna).
 */
if ($kecamatan === null && !empty($districtList)) {
    $kecamatan = $districtList[0];
    $perkiraan = true;
}

if ($desa === null && $kecamatan !== null) {
    $kodeKecamatan = (string)$kecamatan['code'];
    $villageList = ambilWilayah('villages/' . $kodeKecamatan . '.json', 'villages_' . $kodeKecamatan);

    if (!empty($villageList)) {
        $desa = $villageList[0];
        $perkiraan = true;
    }
}

if ($kecamatan === null || $desa === null) {
    lokasiJson(false, 'Kecamatan/kelurahan lokasi tidak dapat dipetakan. Silakan pilih manual.', [
        'alamat' => $alamat,
        'provinsi' => $namaProvinsi,
        'kabupaten' => $namaKabupaten
    ]);
}

$adm4 = (string)$desa['code'];

if (!bmkg_adm4_valid($adm4)) {
    lokasiJson(false, 'Kode wilayah ADM4 tidak valid. Silakan pilih manual.');
}

lokasiJson(
    true,
    $perkiraan
        ? 'Lokasi dipetakan ke wilayah administrasi terdekat yang tersedia.'
        : 'Lokasi berhasil dikenali.',
    [
    'adm4' => $adm4,
    'perkiraan' => $perkiraan,
    'kode_provinsi' => $kodeProvinsi,
    'kode_kabupaten' => $kodeKabupaten,
    'kode_kecamatan' => (string)$kecamatan['code'],
    'provinsi' => $namaProvinsi,
    'kotkab' => $namaKabupaten,
    'kecamatan' => (string)$kecamatan['name'],
    'desa' => (string)$desa['name'],
    'lat' => $lat,
    'lon' => $lon,
    'alamat_lengkap' => (string)($responsAlamat['data']['display_name'] ?? ''),
    'sumber' => 'OpenStreetMap Nominatim + Wilayah.id'
]);
