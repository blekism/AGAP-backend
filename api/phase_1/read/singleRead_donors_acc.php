<?php

header ('Access-Control-Allow-Origin:*');
header ('Content-Type: application/json');
header ('Access-Control-Allow-Methods: GET, OPTIONS');
header ('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

include ('../../function.php');

$requestMethod = $_SERVER["REQUEST_METHOD"];

if($requestMethod == 'OPTIONS'){
    http_response_code(200);
    exit();
}

if($requestMethod == 'GET'){

    if(isset($_GET['account_id'])){

        $donor = getDonor($_GET);
        echo $donor;

    } else {

        $data = [
            'status' => 404,
            'message' => $requestMethod. ' Method Not Allowed',
        ];
        header("HTTP/1.0 404 Method Not Allowed");
        echo json_encode($data);

    }

} else {
    $data = [
        'status' => 405,
        'message' => $requestMethod. ' Method Not Allowed',
    ];
    header("HTTP/1.0 405 Method Not Allowed");
    echo json_encode($data);
}
?>