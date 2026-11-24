<?php

header('Content-Type: application/json; charset=utf-8');

$adm4 = $_GET['adm4'] ?? '';

if ($adm4 === '') {

    http_response_code(400);

    echo json_encode([
        'status' => false,
        'message' => 'Kode wilayah adm4 belum diberikan'
    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| API RESMI BMKG
|--------------------------------------------------------------------------
*/

$url = 'https://api.bmkg.go.id/publik/prakiraan-cuaca?adm4=' . urlencode($adm4);


$ch = curl_init();

curl_setopt_array($ch, [

    CURLOPT_URL => $url,

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_FOLLOWLOCATION => true,

    CURLOPT_TIMEOUT => 20,

    CURLOPT_CONNECTTIMEOUT => 10,

    CURLOPT_SSL_VERIFYPEER => true,

    CURLOPT_USERAGENT => 'Monitoring BMKG - PHP'

]);


$response = curl_exec($ch);


if ($response === false) {

    $error = curl_error($ch);

    curl_close($ch);

    http_response_code(500);

    echo json_encode([

        'status' => false,

        'message' => 'Gagal menghubungi API BMKG',

        'error' => $error

    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);


if ($httpCode !== 200) {

    http_response_code($httpCode);

    echo json_encode([

        'status' => false,

        'message' => 'API BMKG mengembalikan HTTP ' . $httpCode

    ], JSON_UNESCAPED_UNICODE);

    exit;
}


$data = json_decode(
    $response,
    true
);


if (!is_array($data)) {

    http_response_code(500);

    echo json_encode([

        'status' => false,

        'message' => 'Format data dari BMKG tidak valid'

    ], JSON_UNESCAPED_UNICODE);

    exit;
}


/*
|--------------------------------------------------------------------------
| OUTPUT
|--------------------------------------------------------------------------
*/

echo json_encode([

    'status' => true,

    'source' => 'BMKG',

    'adm4' => $adm4,

    'data' => $data

], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);