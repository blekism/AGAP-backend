<?php

header('Access-Control-Allow-Origin:*');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

include('../../function.php');
require '../../../inc/dbcon.php';

global $con;

$requestMethod = $_SERVER["REQUEST_METHOD"];

if ($requestMethod == "OPTIONS") {
    // Send a 200 OK response for preflight requests
    http_response_code(200);
    exit();
}

if ($requestMethod == 'PUT') {
    // $session_token = $_COOKIE['volun_session_token'] ?? '';

    // $query = "SELECT account_id, session_expire FROM account_tbl WHERE session_token = '$session_token'";
    // $result = mysqli_query($con, $query);

    // if ($result && mysqli_num_rows($result) == 1) {
    //     $res = mysqli_fetch_assoc($result);
    //     $account_id = $res['account_id'];
    //     $session_expire = $res['session_expire'];

    //     if (time() > $session_expire) {
    //         $invalidate_query = "UPDATE account_tbl SET session_token = NULL, session_expire = NULL WHERE account_id = '$account_id'";
    //         mysqli_query($con, $invalidate_query);

    //         $data = [
    //             'status' => 401,
    //             'message' => 'Session Expired',
    //         ];
    //         header("HTTP/1.0 401 Unauthorized");
    //         echo json_encode($data);
    //     } else {
    $inputData = json_decode(file_get_contents("php://input"), true);

    if (empty($inputData)) {
        $updateAdmin = updateAdmin($_POST);
    } else {
        $updateAdmin = updateAdmin($inputData);
    }
    echo $updateAdmin;
    exit();
    //     }
    // } else {
    //     $data = [
    //         'status' => 401,
    //         'message' => 'Unauthorized',
    //     ];
    //     header("HTTP/1.0 401 Unauthorized");
    //     echo json_encode($data);
    // }
} else {
    $data = [
        'status' => 405,
        'message' => $requestMethod . ' Method Not Allowed',
    ];
    header("HTTP/1.0 405 Method Not Allowed");
    echo json_encode($data);
}
