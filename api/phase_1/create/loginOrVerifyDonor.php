<?php

header('Access-Control-Allow-Origin:*');
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');
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

if ($requestMethod == 'POST') {
    $session_token = $_COOKIE['session_token'] ?? '';

    $query = "SELECT account_id, session_expire FROM account_tbl WHERE session_token = '$session_token'";
    $result = mysqli_query($con, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $res = mysqli_fetch_assoc($result);
        $account_id = $res['account_id'];
        $session_expire = $res['session_expire'];

        if (time() > $session_expire) { //prompt user to login again if session has expired
            $invalidate_query = "UPDATE account_tbl SET session_token = NULL, session_expire = NULL WHERE account_id = '$account_id'";
            mysqli_query($con, $invalidate_query);

            $inputData = json_decode(file_get_contents("php://input"), true);

            if (empty($inputData)) {
                $loginDonorAcc = loginDonorAcc($_POST);
            } else {
                $loginDonorAcc = loginDonorAcc($inputData);
            }
            echo $loginDonorAcc;
            exit();
        } else { //proceed with login since session is still valid
            $inputData = json_decode(file_get_contents("php://input"), true);

            if (empty($inputData)) {
                $loginDonorAcc = loginDonorAcc($_POST);
            } else {
                $loginDonorAcc = loginDonorAcc($inputData);
            }
            echo $loginDonorAcc;
            exit();
        }
    } else { //allows user to login after inital verification where session token is not found
        $inputData = json_decode(file_get_contents("php://input"), true);

        if (empty($inputData)) {
            $loginDonorAcc = loginDonorAcc($_POST);
        } else {
            $loginDonorAcc = loginDonorAcc($inputData);
        }
        echo $loginDonorAcc;
        exit();
    }
} else {
    $data = [
        'status' => 405,
        'message' => $requestMethod . ' Method Not Allowed',
    ];
    header("HTTP/1.0 405 Method Not Allowed");
    echo json_encode($data);
}
