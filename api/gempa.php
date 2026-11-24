<?php

header('Content-Type: application/json; charset=utf-8');

$url = "https://data.bmkg.go.id/DataMKG/TEWS/autogempa.json";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

$response = curl_exec($ch);

if ($response === false) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Gagal mengambil data gempa BMKG"
    ]);

    curl_close($ch);
    exit;
}

curl_close($ch);

$data = json_decode($response, true);

if (!is_array($data)) {

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "message" => "Data BMKG tidak valid"
    ]);

    exit;
}

echo json_encode([
    "status" => true,
    "source" => "BMKG",
    "data" => $data
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);