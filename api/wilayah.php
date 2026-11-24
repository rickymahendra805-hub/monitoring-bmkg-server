<?php

/* =========================================================
   PENCARIAN WILAYAH ADM4 BMKG
   Monitoring Cuaca BMKG
========================================================= */

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   FUNGSI RESPONSE
========================================================= */

function responseJSON($success, $message, $data = [])
{
    echo json_encode(
        [
            "success" => $success,
            "message" => $message,
            "data" => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_PRETTY_PRINT
    );

    exit;
}


/* =========================================================
   KATA KUNCI PENCARIAN
========================================================= */

$q = $_GET["q"] ?? "";

$q = trim($q);


/* =========================================================
   VALIDASI
========================================================= */

if ($q === "") {

    responseJSON(
        false,
        "Masukkan nama kota atau wilayah."
    );
}


/* =========================================================
   DATA WILAYAH DASAR
========================================================= */

$wilayah = [

    [
        "nama" => "Jakarta",
        "provinsi" => "DKI Jakarta",
        "lat" => -6.1754,
        "lng" => 106.8272
    ],

    [
        "nama" => "Banda Aceh",
        "provinsi" => "Aceh",
        "lat" => 5.5483,
        "lng" => 95.3238
    ],

    [
        "nama" => "Medan",
        "provinsi" => "Sumatera Utara",
        "lat" => 3.5952,
        "lng" => 98.6722
    ],

    [
        "nama" => "Padang",
        "provinsi" => "Sumatera Barat",
        "lat" => -0.9471,
        "lng" => 100.4172
    ],

    [
        "nama" => "Pekanbaru",
        "provinsi" => "Riau",
        "lat" => 0.5071,
        "lng" => 101.4478
    ],

    [
        "nama" => "Jambi",
        "provinsi" => "Jambi",
        "lat" => -1.6101,
        "lng" => 103.6131
    ],

    [
        "nama" => "Palembang",
        "provinsi" => "Sumatera Selatan",
        "lat" => -2.9761,
        "lng" => 104.7754
    ],

    [
        "nama" => "Bengkulu",
        "provinsi" => "Bengkulu",
        "lat" => -3.7928,
        "lng" => 102.2608
    ],

    [
        "nama" => "Bandar Lampung",
        "provinsi" => "Lampung",
        "lat" => -5.4292,
        "lng" => 105.2610
    ],

    [
        "nama" => "Pangkalpinang",
        "provinsi" => "Kepulauan Bangka Belitung",
        "lat" => -2.1316,
        "lng" => 106.1169
    ],

    [
        "nama" => "Tanjungpinang",
        "provinsi" => "Kepulauan Riau",
        "lat" => 0.9168,
        "lng" => 104.4554
    ],

    [
        "nama" => "Serang",
        "provinsi" => "Banten",
        "lat" => -6.1200,
        "lng" => 106.1503
    ],

    [
        "nama" => "Bandung",
        "provinsi" => "Jawa Barat",
        "lat" => -6.9175,
        "lng" => 107.6191
    ],

    [
        "nama" => "Semarang",
        "provinsi" => "Jawa Tengah",
        "lat" => -6.9667,
        "lng" => 110.4167
    ],

    [
        "nama" => "Yogyakarta",
        "provinsi" => "DI Yogyakarta",
        "lat" => -7.7956,
        "lng" => 110.3695
    ],

    [
        "nama" => "Surabaya",
        "provinsi" => "Jawa Timur",
        "lat" => -7.2575,
        "lng" => 112.7521
    ],

    [
        "nama" => "Denpasar",
        "provinsi" => "Bali",
        "lat" => -8.6500,
        "lng" => 115.2167
    ],

    [
        "nama" => "Mataram",
        "provinsi" => "Nusa Tenggara Barat",
        "lat" => -8.5833,
        "lng" => 116.1167
    ],

    [
        "nama" => "Kupang",
        "provinsi" => "Nusa Tenggara Timur",
        "lat" => -10.1772,
        "lng" => 123.6070
    ],

    [
        "nama" => "Pontianak",
        "provinsi" => "Kalimantan Barat",
        "lat" => -0.0263,
        "lng" => 109.3425
    ],

    [
        "nama" => "Palangkaraya",
        "provinsi" => "Kalimantan Tengah",
        "lat" => -2.2096,
        "lng" => 113.9213
    ],

    [
        "nama" => "Banjarmasin",
        "provinsi" => "Kalimantan Selatan",
        "lat" => -3.3167,
        "lng" => 114.5900
    ],

    [
        "nama" => "Samarinda",
        "provinsi" => "Kalimantan Timur",
        "lat" => -0.5022,
        "lng" => 117.1536
    ],

    [
        "nama" => "Tanjung Selor",
        "provinsi" => "Kalimantan Utara",
        "lat" => 2.8375,
        "lng" => 117.3653
    ],

    [
        "nama" => "Makassar",
        "provinsi" => "Sulawesi Selatan",
        "lat" => -5.1477,
        "lng" => 119.4327
    ],

    [
        "nama" => "Manado",
        "provinsi" => "Sulawesi Utara",
        "lat" => 1.4748,
        "lng" => 124.8421
    ],

    [
        "nama" => "Palu",
        "provinsi" => "Sulawesi Tengah",
        "lat" => -0.9003,
        "lng" => 119.8779
    ],

    [
        "nama" => "Kendari",
        "provinsi" => "Sulawesi Tenggara",
        "lat" => -3.9985,
        "lng" => 122.5129
    ],

    [
        "nama" => "Gorontalo",
        "provinsi" => "Gorontalo",
        "lat" => 0.5435,
        "lng" => 123.0568
    ],

    [
        "nama" => "Mamuju",
        "provinsi" => "Sulawesi Barat",
        "lat" => -2.6748,
        "lng" => 118.8860
    ],

    [
        "nama" => "Ambon",
        "provinsi" => "Maluku",
        "lat" => -3.6954,
        "lng" => 128.1814
    ],

    [
        "nama" => "Ternate",
        "provinsi" => "Maluku Utara",
        "lat" => 0.7900,
        "lng" => 127.3842
    ],

    [
        "nama" => "Manokwari",
        "provinsi" => "Papua Barat",
        "lat" => -0.8615,
        "lng" => 134.0620
    ],

    [
        "nama" => "Sorong",
        "provinsi" => "Papua Barat Daya",
        "lat" => -0.8762,
        "lng" => 131.2558
    ],

    [
        "nama" => "Nabire",
        "provinsi" => "Papua Tengah",
        "lat" => -3.3667,
        "lng" => 135.4833
    ],

    [
        "nama" => "Wamena",
        "provinsi" => "Papua Pegunungan",
        "lat" => -4.0955,
        "lng" => 138.9453
    ],

    [
        "nama" => "Merauke",
        "provinsi" => "Papua Selatan",
        "lat" => -8.4932,
        "lng" => 140.4018
    ],

    [
        "nama" => "Jayapura",
        "provinsi" => "Papua",
        "lat" => -2.5916,
        "lng" => 140.6690
    ]

];


/* =========================================================
   CARI
========================================================= */

$hasil = [];


foreach ($wilayah as $item) {

    $nama =
        strtolower(
            $item["nama"]
        );

    $provinsi =
        strtolower(
            $item["provinsi"]
        );

    $keyword =
        strtolower($q);


    if (
        strpos($nama, $keyword) !== false ||
        strpos($provinsi, $keyword) !== false
    ) {

        $hasil[] = $item;

    }

}


/* =========================================================
   HASIL
========================================================= */

if (count($hasil) === 0) {

    responseJSON(
        false,
        "Wilayah tidak ditemukan.",
        []
    );

}


responseJSON(
    true,
    "Wilayah ditemukan.",
    $hasil
);

?>