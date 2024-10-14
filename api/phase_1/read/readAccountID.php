<?php

header('Access-Control-Allow-Origin: http://localhost:5173');
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

include('../../function.php');
require '../../../inc/dbcon.php';
require '../../../vendor/autoload.php'; // Include Composer's autoloader

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\Key;

global $con;

$requestMethod = $_SERVER["REQUEST_METHOD"];

if ($requestMethod == "OPTIONS") {
    // Send a 200 OK response for preflight requests
    http_response_code(200);
    exit();
}

if ($requestMethod == 'GET') {
    $session_token = $_COOKIE['donor_token'] ?? '';


    try {
        $secret_key = 'mamamobading';
        $decoded = JWT::decode($session_token, new Key($secret_key, 'HS256'));

        $account_id = $decoded->sub;
        $expiration = $decoded->exp;

        if (time() > $expiration) {
            $data = [
                'status' => 401,
                'message' => 'Unauthorized',
            ];
            header("HTTP/1.0 401 Unauthorized");
            echo json_encode($data);
            exit();
        } else {
            $readAccountID = readAccountID($account_id);

            echo $readAccountID;
            exit();
        }
    } catch (ExpiredException $e) {
        $data = [
            'status' => 401,
            'message' => 'Unauthorized',
        ];
        header("HTTP/1.0 401 Unauthorized");
        echo json_encode($data);
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
