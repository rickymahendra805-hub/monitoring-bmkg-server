<?php
/* =========================================================
   SUMBER DATA RESMI BMKG
   - Prakiraan cuaca publik (api.bmkg.go.id)
   - Cache lokal agar tidak membebani API
   - Tidak memakai data dummy. Jika API gagal, halaman
     menampilkan status gagal secara jujur.
========================================================= */

if (!function_exists('bmkg_lokasi_default')) {
    date_default_timezone_set('Asia/Jakarta');

    function bmkg_lokasi_default()
    {
        return [
            'adm4' => '31.71.03.1001',
            'provinsi' => 'DKI Jakarta',
            'kotkab' => 'Kota Administrasi Jakarta Pusat',
            'kecamatan' => 'Kemayoran',
            'desa' => 'Kemayoran',
            'lat' => -6.1647214778,
            'lon' => 106.8453837867
        ];
    }

    function bmkg_cache_dir()
    {
        $dir = __DIR__ . '/../data/cache';

        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    function bmkg_baca_cache($key, $ttl)
    {
        $file = bmkg_cache_dir() . '/' . preg_replace('/[^a-z0-9._-]/i', '_', $key) . '.json';

        if (!is_file($file)) {
            return null;
        }

        $umur = time() - (int)filemtime($file);

        if ($umur > $ttl) {
            return null;
        }

        $isi = json_decode((string)@file_get_contents($file), true);

        if (!is_array($isi)) {
            return null;
        }

        return [
            'data' => $isi,
            'diambil' => (int)filemtime($file),
            'sumber' => 'cache'
        ];
    }

    function bmkg_baca_cache_lama($key)
    {
        $file = bmkg_cache_dir() . '/' . preg_replace('/[^a-z0-9._-]/i', '_', $key) . '.json';

        if (!is_file($file)) {
            return null;
        }

        $isi = json_decode((string)@file_get_contents($file), true);

        if (!is_array($isi)) {
            return null;
        }

        return [
            'data' => $isi,
            'diambil' => (int)filemtime($file),
            'sumber' => 'cache-lama'
        ];
    }

    function bmkg_simpan_cache($key, $data)
    {
        $file = bmkg_cache_dir() . '/' . preg_replace('/[^a-z0-9._-]/i', '_', $key) . '.json';
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            return false;
        }

        return @file_put_contents($file, $json, LOCK_EX) !== false;
    }

    /**
     * Ambil JSON dari URL dengan cache. Mengembalikan array:
     * data, diambil (timestamp), sumber (jaringan/cache/cache-lama), error.
     */
    function bmkg_ambil_json($url, $cacheKey, $ttl = 1800)
    {
        $cache = bmkg_baca_cache($cacheKey, $ttl);

        if ($cache !== null) {
            return $cache + ['error' => ''];
        }

        $body = false;
        $error = '';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_USERAGENT => 'Monitoring-BMKG-Kemayoran/2.0',
                CURLOPT_HTTPHEADER => ['Accept: application/json']
            ]);

            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($body === false) {
                $error = curl_error($ch);
            } elseif ($status < 200 || $status >= 300) {
                $error = 'HTTP ' . $status;
                $body = false;
            }

            curl_close($ch);
        } else {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 25,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: Monitoring-BMKG-Kemayoran/2.0\r\n"
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true
                ]
            ]);

            $body = @file_get_contents($url, false, $context);
        }

        if ($body !== false && $body !== '') {
            $json = json_decode($body, true);

            if (is_array($json)) {
                bmkg_simpan_cache($cacheKey, $json);

                return [
                    'data' => $json,
                    'diambil' => time(),
                    'sumber' => 'jaringan',
                    'error' => ''
                ];
            }

            $error = 'Respons API bukan JSON yang valid.';
        }

        $stale = bmkg_baca_cache_lama($cacheKey);

        if ($stale !== null) {
            return $stale + ['error' => $error];
        }

        return [
            'data' => null,
            'diambil' => 0,
            'sumber' => 'gagal',
            'error' => $error !== '' ? $error : 'Tidak dapat menghubungi API BMKG.'
        ];
    }

    function bmkg_adm4_valid($adm4)
    {
        return (bool)preg_match('/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/', (string)$adm4);
    }

    /**
     * Lokasi aktif: parameter URL, lalu cookie hasil deteksi otomatis,
     * lalu lokasi default Kemayoran.
     */
    function bmkg_lokasi_terpilih()
    {
        $default = bmkg_lokasi_default();
        $adm4 = trim((string)($_GET['adm4'] ?? ($_COOKIE['bmkg_adm4'] ?? '')));

        if (!bmkg_adm4_valid($adm4)) {
            return $default;
        }

        $cookieAdm4 = trim((string)($_COOKIE['bmkg_adm4'] ?? ''));
        $namaCocok = ($cookieAdm4 === $adm4);

        $lokasi = [
            'adm4' => $adm4,
            'provinsi' => $namaCocok ? trim((string)($_COOKIE['bmkg_provinsi'] ?? '')) : '',
            'kotkab' => $namaCocok ? trim((string)($_COOKIE['bmkg_kotkab'] ?? '')) : '',
            'kecamatan' => $namaCocok ? trim((string)($_COOKIE['bmkg_kecamatan'] ?? '')) : '',
            'desa' => $namaCocok ? trim((string)($_COOKIE['bmkg_desa'] ?? '')) : '',
            'lat' => $namaCocok ? (float)($_COOKIE['bmkg_lat'] ?? 0) : 0,
            'lon' => $namaCocok ? (float)($_COOKIE['bmkg_lon'] ?? 0) : 0
        ];

        return array_merge($default, array_filter($lokasi, function ($v) {
            return $v !== '' && $v !== 0 && $v !== 0.0;
        }));
    }

    /**
     * Ambil prakiraan resmi BMKG dan ubah menjadi baris data yang
     * kompatibel dengan tampilan lama (suhu, kelembapan, dst.).
     */
    function bmkg_muat_cuaca($adm4 = null, $ttl = 1800)
    {
        static $memo = [];

        $lokasi = bmkg_lokasi_terpilih();
        $adm4 = $adm4 ? trim((string)$adm4) : $lokasi['adm4'];

        if (!bmkg_adm4_valid($adm4)) {
            return [
                'records' => [],
                'lokasi' => $lokasi,
                'adm4' => $adm4,
                'sumber' => 'gagal',
                'diambil' => 0,
                'error' => 'Kode wilayah BMKG (adm4) tidak valid.'
            ];
        }

        $memoKey = $adm4 . '|' . $ttl;

        if (isset($memo[$memoKey])) {
            return $memo[$memoKey];
        }

        $url = 'https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=' . rawurlencode($adm4);
        $respons = bmkg_ambil_json($url, 'prakiraan_' . str_replace('.', '_', $adm4), $ttl);

        $records = [];
        $lokasiApi = [];

        if (is_array($respons['data'])) {
            $blokList = isset($respons['data']['data']) && is_array($respons['data']['data'])
                ? $respons['data']['data']
                : [];

            foreach ($blokList as $blok) {
                if (!is_array($blok)) {
                    continue;
                }

                if (!empty($blok['lokasi']) && is_array($blok['lokasi'])) {
                    $lokasiApi = $blok['lokasi'];
                }

                if (empty($blok['cuaca']) || !is_array($blok['cuaca'])) {
                    continue;
                }

                foreach ($blok['cuaca'] as $kelompok) {
                    if (!is_array($kelompok)) {
                        continue;
                    }

                    foreach ($kelompok as $item) {
                        if (!is_array($item)) {
                            continue;
                        }

                        $waktuLokal = (string)($item['local_datetime'] ?? '');
                        $timestamp = $waktuLokal !== '' ? strtotime($waktuLokal) : false;

                        if ($timestamp === false) {
                            continue;
                        }

                        $records[] = [
                            'wilayah' => (string)($lokasiApi['desa'] ?? $lokasi['desa']),
                            'provinsi' => (string)($lokasiApi['provinsi'] ?? $lokasi['provinsi']),
                            'kabupaten' => (string)($lokasiApi['kotkab'] ?? $lokasi['kotkab']),
                            'kecamatan' => (string)($lokasiApi['kecamatan'] ?? $lokasi['kecamatan']),
                            'tanggal' => date('Y-m-d', $timestamp),
                            'waktu' => date('H:i', $timestamp),
                            'suhu' => isset($item['t']) ? (float)$item['t'] : null,
                            'kelembapan' => isset($item['hu']) ? (float)$item['hu'] : null,
                            'curah_hujan' => isset($item['tp']) ? (float)$item['tp'] : 0.0,
                            'kecepatan_angin' => isset($item['ws']) ? (float)$item['ws'] : null,
                            'kondisi_cuaca' => (string)($item['weather_desc'] ?? 'Tidak diketahui'),
                            'arah_angin' => (string)($item['wd'] ?? '-'),
                            'arah_derajat' => isset($item['wd_deg']) && is_numeric($item['wd_deg'])
                                ? (float)$item['wd_deg']
                                : null,
                            'tutupan_awan' => isset($item['tcc']) ? (float)$item['tcc'] : null,
                            'jarak_pandang' => (string)($item['vs_text'] ?? ''),
                            'local_datetime' => $waktuLokal,
                            'utc_datetime' => (string)($item['utc_datetime'] ?? ''),
                            'analysis_date' => (string)($item['analysis_date'] ?? ''),
                            'adm4' => $adm4
                        ];
                    }
                }
            }
        }

        usort($records, function ($a, $b) {
            return strcmp($a['local_datetime'], $b['local_datetime']);
        });

        $unik = [];
        foreach ($records as $record) {
            $unik[$record['local_datetime']] = $record;
        }
        $records = array_values($unik);

        $lokasiFinal = $lokasi;

        if (!empty($lokasiApi)) {
            $lokasiFinal = [
                'adm4' => $adm4,
                'provinsi' => (string)($lokasiApi['provinsi'] ?? $lokasi['provinsi']),
                'kotkab' => (string)($lokasiApi['kotkab'] ?? $lokasi['kotkab']),
                'kecamatan' => (string)($lokasiApi['kecamatan'] ?? $lokasi['kecamatan']),
                'desa' => (string)($lokasiApi['desa'] ?? $lokasi['desa']),
                'lat' => (float)($lokasiApi['lat'] ?? $lokasi['lat']),
                'lon' => (float)($lokasiApi['lon'] ?? $lokasi['lon'])
            ];
        }

        $hasil = [
            'records' => $records,
            'lokasi' => $lokasiFinal,
            'adm4' => $adm4,
            'sumber' => $respons['sumber'],
            'diambil' => $respons['diambil'],
            'error' => $records ? '' : ($respons['error'] !== '' ? $respons['error'] : 'Prakiraan BMKG belum tersedia untuk wilayah ini.')
        ];

        $memo[$memoKey] = $hasil;

        return $hasil;
    }

    function bmkg_label_sumber($sumber)
    {
        $peta = [
            'jaringan' => 'API resmi BMKG',
            'cache' => 'API resmi BMKG (cache)',
            'cache-lama' => 'API resmi BMKG (cache terakhir)',
            'gagal' => 'API BMKG tidak tersedia'
        ];

        return $peta[$sumber] ?? 'Sumber tidak diketahui';
    }

    function bmkg_waktu_ambil($timestamp)
    {
        if (!$timestamp) {
            return '-';
        }

        return date('d/m/Y H:i', (int)$timestamp) . ' WIB';
    }

    function bmkg_ringkas_lokasi($lokasi)
    {
        $bagian = array_filter([
            $lokasi['desa'] ?? '',
            $lokasi['kecamatan'] ?? '',
            $lokasi['kotkab'] ?? '',
            $lokasi['provinsi'] ?? ''
        ]);

        $bagian = array_values(array_unique(array_map('trim', $bagian)));

        return implode(', ', $bagian);
    }
}
