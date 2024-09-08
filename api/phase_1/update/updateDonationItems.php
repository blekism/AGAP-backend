<?php

header('Access-Control-Allow-Origin:*');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

include('../../function.php');

$requestMethod = $_SERVER["REQUEST_METHOD"];

if ($requestMethod == "OPTIONS") {
    // Send a 200 OK response for preflight requests
    http_response_code(200);
    exit();
}

if ($requestMethod == 'PUT') {
    $inputData = json_decode(file_get_contents("php://input"), true);

    if (empty($inputData)) {
        $updateDonationItems = updateDonationItems($_GET, $_POST);
    } else {
        $updateDonationItems = updateDonationItems($_GET, $inputData);
    }
    echo $updateDonationItems;
    exit();
} else {
    $data = [
        'status' => 405,
        'message' => $requestMethod . ' Method Not Allowed',
    ];
    header("HTTP/1.0 405 Method Not Allowed");
    echo json_encode($data);
}
