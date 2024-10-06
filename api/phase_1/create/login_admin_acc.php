<?php

header ('Access-Control-Allow-Origin:*');
header ('Content-Type: application/json');
header ('Access-Control-Allow-Methods: POST, OPTIONS');
header ('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Request-With');

include ('../../function.php');
require '../../../inc/dbcon.php';

global $conn;

$requestMethod = $_SERVER["REQUEST_METHOD"];

if($requestMethod == 'OPTIONS'){
    http_response_code(200);
    exit();
}

if($requestMethod == 'POST'){
    //session_token
    $session_token = $_COOKIE['admin_session_token'] ?? '';

    $query = "SELECT admin_id, session_expire FROM admin_acc_tbl WHERE session_token = '$session_token'";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) == 1) {
        $res = mysqli_fetch_assoc($result);
        $admin_id = $res['admin_id'];
        $session_expire = $res['session_expire'];

        if (time() > $session_expire) {
            $invalidate_query = "UPDATE admin_acc_tbl SET session_token = NULL, session_expire = NULL WHERE admin_id = '$admin_id'";
            mysqli_query($con, $invalidate_query);

    //
            $inputData = json_decode(file_get_contents("php://input"), true);

            if(empty($inputData)){

                $loginAdminAcc = loginAdminAcc($_POST);

            } else {

                $loginAdminAcc = loginAdminAcc($inputData);

            }

            echo $loginAdminAcc;
            exit();

        } else { //proceed with login since session is still valid
            $inputData = json_decode(file_get_contents("php://input"), true);

            if(empty($inputData)){

                $loginAdminAcc = loginAdminAcc($_POST);
    
            } else {
    
                $loginAdminAcc = loginAdminAcc($inputData);
    
            }
    
            echo $loginAdminAcc;
            exit();
        }
    
        } else { //allows user to login after inital verification where session token is not found
            $inputData = json_decode(file_get_contents("php://input"), true);

            if(empty($inputData)){

                $loginAdminAcc = loginAdminAcc($_POST);

            } else {

                $loginAdminAcc = loginAdminAcc($inputData);

            }

            echo $loginAdminAcc;
            exit();
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