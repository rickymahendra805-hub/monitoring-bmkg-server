<?php

/* =========================================================
   API BMKG
   MONITORING CUACA
   Tanpa Database
========================================================= */

header("Content-Type: application/json; charset=UTF-8");


/* =========================================================
   RESPONSE JSON
========================================================= */

function responseJSON(
    $success,
    $message,
    $data = null
) {

    echo json_encode(
        [
            "success" => $success,
            "message" => $message,
            "data" => $data
        ],
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}


/* =========================================================
   AMBIL ADM4
========================================================= */

$adm4 = $_GET["adm4"] ?? "";

$adm4 = trim($adm4);


if ($adm4 === "") {

    responseJSON(
        false,
        "Kode ADM4 belum diberikan."
    );

}


/* =========================================================
   VALIDASI ADM4
========================================================= */

if (
    !preg_match(
        '/^\d{2}\.\d{2}\.\d{2}\.\d{4}$/',
        $adm4
    )
) {

    responseJSON(
        false,
        "Format ADM4 tidak valid.",
        [
            "contoh" => "31.71.03.1001"
        ]
    );

}


/* =========================================================
   URL BMKG
========================================================= */

$url =
    "https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=" .
    urlencode($adm4);


/* =========================================================
   REQUEST OPTIONS
========================================================= */

$options = [

    "http" => [

        "method" => "GET",

        "header" =>
            "User-Agent: Monitoring-Cuaca-BMKG/1.0\r\n" .
            "Accept: application/json\r\n",

        "timeout" => 20

    ],

    "ssl" => [

        "verify_peer" => true,

        "verify_peer_name" => true

    ]

];


$context =
    stream_context_create($options);


/* =========================================================
   REQUEST KE BMKG
========================================================= */

$result =
    @file_get_contents(
        $url,
        false,
        $context
    );


/* =========================================================
   ERROR
========================================================= */

if ($result === false) {

    responseJSON(
        false,
        "Gagal mengambil data dari server BMKG.",
        [
            "adm4" => $adm4
        ]
    );

}


/* =========================================================
   DECODE
========================================================= */

$data =
    json_decode(
        $result,
        true
    );


if (!is_array($data)) {

    responseJSON(
        false,
        "Data BMKG bukan JSON yang valid."
    );

}


/* =========================================================
   RESPONSE
========================================================= */

responseJSON(
    true,
    "Data BMKG berhasil diambil.",
    $data
);

?>