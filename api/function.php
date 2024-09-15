<?php

require __DIR__ . '/../inc/dbcon.php';
require __DIR__ . '/../vendor/autoload.php';
require 'creds.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

function error422($message)
{
    $data = [
        'status' => 422,
        'message' => $message,
    ];
    header("HTTP/1.0 422 Unprocessable Entity");
    echo json_encode($data);
    exit();
}


//INSERT DONATION START
function insertDonation($userInput, $account_id)
{
    global $con;

    mysqli_begin_transaction($con);
    $donation_id = 'DONATE' . date('Y-d') . '-' . uniqid();
    $recipient_id = mysqli_real_escape_string($con, $userInput['recipient_id']);
    $itemLoop = $userInput['items'];

    if (empty(trim($recipient_id))) {
        return error422('Enter valid recipient');
    } elseif (empty($itemLoop)) {
        return error422('Enter valid items');
    } else {
        $query = "INSERT INTO 
            donation_tbl(
                donation_id, 
                account_id, 
                recipient_id) 
            VALUES(
                '$donation_id', 
                '$account_id', 
                '$recipient_id')";
        $result = mysqli_query($con, $query);


        if ($result) {
            try {
                $insertedItemCount = 0;
                foreach ($itemLoop as $item) {
                    $donate_id = $donation_id;
                    $donation_items_id = 'ITEM' . date('Y-d') . '-' . uniqid();
                    $donate_item = mysqli_real_escape_string($con, $item['item']);
                    $item_category_id = mysqli_real_escape_string($con, $item['item_category_id']);
                    $qty = mysqli_real_escape_string($con, $item['qty']);
                    $cost = mysqli_real_escape_string($con, $item['cost']);
                    $donor_signature = mysqli_real_escape_string($con, $item['donor_signature']);

                    $query2 = "INSERT INTO 
                        donation_items_tbl(
                            donation_items_id,
                            donation_id, 
                            item, 
                            item_category_id,
                            qty, 
                            cost, 
                            donor_signature) 
                        VALUES(
                            '$donation_items_id',
                            '$donate_id', 
                            '$donate_item', 
                            '$item_category_id', 
                            '$qty', 
                            '$cost',
                            '$donor_signature')";
                    $result2 = mysqli_query($con, $query2);


                    if ($result2) {
                        $insertedItemCount++;
                    } else {
                        throw new Exception('Failed to insert item');
                    }
                }

                mysqli_commit($con);
                if ($insertedItemCount > 0) {

                    $data = [
                        'status' => 201,
                        'message' => 'Donation Inserted ' . $insertedItemCount . ' items',
                    ];
                    header("HTTP/1.0 201 Updated");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 400,
                        'message' => 'No Donation were added',
                    ];
                    header("HTTP/1.0 500 Internal Server Error");
                    return json_encode($data);
                }
            } catch (Exception $e) {
                mysqli_rollback($con);
                $data = [
                    'status' => 500,
                    'message' => $e->getMessage(),
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT DONATION END

//INSERT VOLUNTEER START
function signVolunteer($userInput)
{
    global $myEmail, $myPassword, $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $account_id = 'VOLUN - ' . date('Y-d') . substr(uniqid(), -5);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);
        $section = mysqli_real_escape_string($con, $userInput['section']);
        $mail = new PHPMailer(true);

        $hashing = md5($password);

        if (empty(trim($last_name))) {
            return error422('Enter valid last name');
        } elseif (empty(trim($first_name))) {
            return error422('Enter valid first name');
        } elseif (empty(trim($middle_name))) {
            return error422('Enter valid middle name');
        } elseif (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } elseif (empty(trim($contact_info))) {
            return error422('Enter valid contact information');
        } elseif (empty(trim($dept_category_id))) {
            return error422('Enter valid dept category');
        } elseif (empty(trim($section))) {
            return error422('Enter valid section');
        } elseif (empty(trim($designation_id))) {
            return error422('Enter valid designation');
        } else {
            try {
                //Server settings
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                $mail->Username   = $myEmail;                     //SMTP username
                $mail->Password   = $myPassword;                               //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom($myEmail, 'Mailer');
                $mail->addAddress($email);
                $verificationCode = substr(number_format(time() * rand(), 0, '', ''), 0, 6);     //Add a recipient


                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = 'Verification code';
                $mail->Body    = 'Your verification code is: ' . $verificationCode;
                $mail->AltBody = 'Your verification code is: ' . $verificationCode;

                $mail->send();
                echo 'Message has been sent';

                //ADD DITO YUNG READ NG ACCOUNT TABLE TO SEE IF MAY GANUN NANG EMAIL

                $query = "INSERT INTO 
            account_tbl(
                account_id, 
                last_name, 
                first_name, 
                middle_name, 
                section,
                dept_category_id,
                designation_id, 
                email, 
                password, 
                contact_info, 
                acc_status_id,
                verification_code,
                created_at) 
            VALUES(
                '$account_id', 
                '$last_name',
                '$first_name', 
                '$middle_name',
                '$section',
                '$dept_category_id',
                '$designation_id', 
                '$email', 
                '$hashing', 
                '$contact_info', 
                 1,
                '$verificationCode',
                NOW())";
                $result = mysqli_query($con, $query);

                if ($result) {

                    $data = [
                        'status' => 201,
                        'message' => 'Volunteer Signup Success',
                    ];
                    header("HTTP/1.0 201 Inserted");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 422,
                        'message' => 'Unprocessable entity',
                    ];
                    header("HTTP/1.0 422 Unprocessable Entity");
                    return json_encode($data);
                }
            } catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    } else {
        return error422('Enter Email and Password');
    }
}
//INSERT VOLUNTEER END

//INSERT VOLUNTEER START
function signDonor($userInput)
{
    global $myEmail, $myPassword, $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $account_id = 'DONOR - ' . date('Y-d') . substr(uniqid(), -5);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);
        $section = mysqli_real_escape_string($con, $userInput['section']);
        $mail = new PHPMailer(true);

        $hashing = md5($password);

        if (empty(trim($last_name))) {
            return error422('Enter valid last name');
        } elseif (empty(trim($first_name))) {
            return error422('Enter valid first name');
        } elseif (empty(trim($middle_name))) {
            return error422('Enter valid middle name');
        } elseif (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } elseif (empty(trim($contact_info))) {
            return error422('Enter valid contact information');
        } elseif (empty(trim($dept_category_id))) {
            return error422('Enter valid dept category');
        } elseif (empty(trim($section))) {
            return error422('Enter valid section');
        } elseif (empty(trim($designation_id))) {
            return error422('Enter valid designation');
        } else {
            try {
                //Server settings
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                $mail->isSMTP();                                            //Send using SMTP
                $mail->Host       = 'smtp.gmail.com';                     //Set the SMTP server to send through
                $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
                $mail->Username   = $myEmail;                     //SMTP username
                $mail->Password   = $myPassword;                               //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
                $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom($myEmail, 'Mailer');
                $mail->addAddress($email);
                $verificationCode = substr(number_format(time() * rand(), 0, '', ''), 0, 6);     //Add a recipient


                //Content
                $mail->isHTML(true);                                  //Set email format to HTML
                $mail->Subject = 'Verification code';
                $mail->Body    = 'Your verification code is: ' . $verificationCode;
                $mail->AltBody = 'Your verification code is: ' . $verificationCode;

                $mail->send();
                echo 'Message has been sent';

                //ADD DITO YUNG READ NG ACCOUNT TABLE TO SEE IF MAY GANUN NANG EMAIL

                $query = "INSERT INTO 
            account_tbl(
                account_id, 
                last_name, 
                first_name, 
                middle_name, 
                section,
                dept_category_id,
                designation_id, 
                email, 
                password, 
                contact_info, 
                acc_status_id,
                verification_code,
                created_at) 
            VALUES(
                '$account_id', 
                '$last_name',
                '$first_name', 
                '$middle_name',
                '$section',
                '$dept_category_id',
                '$designation_id', 
                '$email', 
                '$hashing', 
                '$contact_info', 
                 1,
                '$verificationCode',
                NOW())";
                $result = mysqli_query($con, $query);

                if ($result) {

                    $data = [
                        'status' => 201,
                        'message' => 'Donor Signup Success',
                    ];
                    header("HTTP/1.0 201 Inserted");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 422,
                        'message' => 'Unprocessable entity',
                    ];
                    header("HTTP/1.0 422 Unprocessable Entity");
                    return json_encode($data);
                }
            } catch (Exception $e) {
                echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        }
    } else {
        return error422('Enter Email and Password');
    }
}
//INSERT VOLUNTEER END

//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT START
function loginVolunteerAcc($userInput)
{
    global $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);

        $hashing = md5($password);

        if (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } else {
            $query = "SELECT account_id, verified_at, expire FROM 
                    account_tbl 
                WHERE 
                    email = '$email' AND 
                    password = '$hashing' AND 
                    account_id LIKE 'VOLUN - %';";
            $result = mysqli_query($con, $query);


            if ($result) {
                if (mysqli_num_rows($result) == 1) {
                    $res = mysqli_fetch_assoc($result);
                    if ($res['verified_at'] != null) {
                        $session_expire = $res['session_expire'];

                        if (time() > $session_expire) {
                            $session_token = bin2hex(random_bytes(32));
                            $expire = time() + (365 * 24 * 60 * 60);

                            // Store session token in the database for the user
                            $account_id = $res['account_id'];
                            $update_token_query = "UPDATE account_tbl SET session_token='$session_token' WHERE account_id='$account_id'";
                            mysqli_query($con, $update_token_query);

                            // Set the session token as an HTTP-only, secure cookie
                            setcookie('session_token', $session_token, [
                                'expires' => $expire, // 1 year expiration
                                'path' => '/',
                                'httponly' => true,  // Prevent JavaScript access
                                'secure' => true,    // Use HTTPS
                                'samesite' => 'Strict', // CSRF protection
                            ]);


                            $data = [
                                'status' => 201,
                                'message' => 'Session Invalid, generated a new token: Logged In Successfully',
                                'data' => $res,
                            ];
                            header("HTTP/1.0 200 OK");
                            return json_encode($data);
                        } else {
                            $data = [
                                'status' => 201,
                                'message' => 'Session is still valid. Logged In Successfully',
                                'data' => $res,
                            ];
                            header("HTTP/1.0 200 OK");
                            return json_encode($data);
                        }
                    } else {
                        $data = [
                            'status' => 401,
                            'message' => 'Account not verified',
                        ];
                        header("HTTP/1.0 401 Unauthorized");
                        return json_encode($data);
                    }
                } else {
                    $data = [
                        'status' => 401,
                        'message' => 'Invalid Email or Password',
                    ];
                    header("HTTP/1.0 401 Unauthorized");
                    return json_encode($data);
                }
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    } else {
        return error422('Enter Email and Password');
    }
}
//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT END

//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT START
function loginDonorAcc($userInput)
{
    global $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);

        $hashing = md5($password);

        if (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } else {
            $query = "SELECT account_id, verified_at, session_expire FROM 
                    account_tbl 
                WHERE 
                    email = '$email' AND 
                    password = '$hashing' AND 
                    account_id LIKE 'DONOR - %';";
            $result = mysqli_query($con, $query);


            if ($result) {
                if (mysqli_num_rows($result) == 1) {
                    $res = mysqli_fetch_assoc($result);
                    if ($res['verified_at'] != null) {
                        $session_expire = $res['session_expire'];

                        if (time() > $session_expire) {
                            $session_token = bin2hex(random_bytes(32));
                            $expire = time() + (365 * 24 * 60 * 60);

                            // Store session token in the database for the user
                            $account_id = $res['account_id'];
                            $update_token_query = "UPDATE account_tbl SET session_token='$session_token', session_expire = '$expire' WHERE account_id='$account_id'";
                            mysqli_query($con, $update_token_query);

                            // Set the session token as an HTTP-only, secure cookie
                            setcookie('session_token', $session_token, [
                                'expires' => $expire, // 1 year expiration
                                'path' => '/',
                                'httponly' => true,  // Prevent JavaScript access
                                'secure' => true,    // Use HTTPS
                                'samesite' => 'Strict', // CSRF protection
                            ]);

                            $data = [
                                'status' => 201,
                                'message' => 'Session Invalid, generated a new token: Logged In Successfully',
                                'data' => $res,
                            ];
                            header("HTTP/1.0 200 OK");
                            return json_encode($data);
                        } else {
                            $data = [
                                'status' => 201,
                                'message' => 'Session is still valid. Logged In Successfully',
                                'data' => $res,
                            ];
                            header("HTTP/1.0 200 OK");
                            return json_encode($data);
                        }
                    } else {
                        $data = [
                            'status' => 401,
                            'message' => 'Account not verified',
                        ];
                        header("HTTP/1.0 401 Unauthorized");
                        return json_encode($data);
                    }
                } else {
                    $data = [
                        'status' => 401,
                        'message' => 'Invalid Email or Password',
                    ];
                    header("HTTP/1.0 401 Unauthorized");
                    return json_encode($data);
                }
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    } else {
        return error422('Enter Email and Password');
    }
}
//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT END

//INSERT EVENT START
function insertEvent($userInput)
{
    global $con;


    $event_id = 'EVENT' . date('Y-d') . '-' . uniqid();
    $name = mysqli_real_escape_string($con, $userInput['event_name']);
    $event_link = mysqli_real_escape_string($con, $userInput['event_link']);
    $description = mysqli_real_escape_string($con, $userInput['description']);
    $start_date = mysqli_real_escape_string($con, $userInput['start_date']);
    $end_date = mysqli_real_escape_string($con, $userInput['end_date']);
    $contribution_amount = mysqli_real_escape_string($con, $userInput['contribution_amount']);


    if (empty(trim($name))) {
        return error422('Enter valid name');
    } elseif (empty(trim($event_link))) {
        return error422('Enter valid event link');
    } elseif (empty(trim($description))) {
        return error422('Enter valid description');
    } elseif (empty(trim($start_date))) {
        return error422('Enter valid start date');
    } elseif (empty(trim($end_date))) {
        return error422('Enter valid end date');
    } elseif (empty(trim($contribution_amount))) {
        return error422('Enter valid contribution amount');
    } else {
        $query = "INSERT INTO 
            event_tbl(
                evenet_id, 
                event_name, 
                event_link, 
                description, 
                start_date, 
                end_date, 
                contrib_amount) 
            VALUES(
                '$event_id', 
                '$name', 
                '$event_link', 
                '$description', 
                '$start_date', 
                '$end_date', 
                '$contribution_amount')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Event Inserted',
            ];
            header("HTTP/1.0 201 Inserted");
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT EVENT END


//INSERT PHASE 2 START
function insertPhase2($userInput)
{

    global $con;

    $event_id = mysqli_real_escape_string($con, $userInput['event_id']);
    $volunteer_id = mysqli_real_escape_string($con, $userInput['volunteer_id']);
    $activity = mysqli_real_escape_string($con, $userInput['activity']);
    $time_in = mysqli_real_escape_string($con, $userInput['time_in']);
    $time_out = mysqli_real_escape_string($con, $userInput['time_out']);
    $signature = mysqli_real_escape_string($con, $userInput['signature']);
    $date = mysqli_real_escape_string($con, $userInput['date']);

    if (empty(trim($event_id))) {

        return error422('Enter event id');
    } elseif (empty(trim($volunteer_id))) {

        return error422('Enter volunteer id');
    } elseif (empty(trim($activity))) {

        return error422('Enter activity');
    } elseif (empty(trim($time_in))) {

        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {

        return error422('Enter time out');
    } elseif (empty(trim($signature))) {

        return error422('Enter signature');
    } elseif (empty(trim($date))) {

        return error422('Enter date');
    } else {

        $query = "INSERT INTO phase2_tbl (event_id, volunteer_id, activity, time_in, time_out,  signature, date) 
        VALUES ('$event_id','$volunteer_id','$activity','$time_in','$time_out','$signature','$date')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Phase 2 Inserted Successfully',
            ];
            header("HTTP/1.0 201 OK");
            return json_encode($data);
        } else {

            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT PHASE 2 END

//INSERT PHASE 3 START
function insertPhase3($userInput)
{
    global $con;

    $log_id = 'LOG' . date('Y') . '-' . uniqid();
    $event_id = mysqli_real_escape_string($con, $userInput['event_id']);
    $volunteer_id = mysqli_real_escape_string($con, $userInput['volunteer_id']);
    $time_in = mysqli_real_escape_string($con, $userInput['time_in']);
    $time_out = mysqli_real_escape_string($con, $userInput['time_out']);
    $signature = mysqli_real_escape_string($con, $userInput['signature']);
    $date = mysqli_real_escape_string($con, $userInput['date']);

    if (empty(trim($event_id))) {
        return error422('Enter event id');
    } elseif (empty(trim($volunteer_id))) {
        return error422('Enter volunteer id');
    } elseif (empty(trim($time_in))) {
        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {
        return error422('Enter time out');
    } elseif (empty(trim($signature))) {
        return error422('Enter signature');
    } elseif (empty(trim($date))) {
        return error422('Enter date');
    } else {
        $query = "INSERT INTO 
            phase3_tbl (
                log_id, 
                event_id, 
                volunteer_id, 
                time_in, 
                time_out, 
                signature, 
                date) 
            VALUES (
                '$log_id', 
                '$event_id',
                '$volunteer_id',
                '$time_in',
                '$time_out',
                '$signature',
                '$date')";
        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 201,
                'message' => 'Phase 3 Inserted Successfully',
            ];
            header("HTTP/1.0 201 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT PHASE 3 END

//DELETE DONOR START
function deleteAccount($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid donor ID');
    } else {
        $query = "UPDATE account_tbl 
            SET 
                session_token = NULL,
                session_expire = NULL,
                last_name = 'Deleted', 
                first_name = 'Deleted', 
                middle_name = 'Deleted', 
                section = 'Deleted',
                dept_category_id = 5,
                designation_id = 5, 
                email = 'Deleted', 
                password = 'Deleted', 
                contact_info = 'Deleted',
                total_hours = 0,
                acc_status_id = 2,
                updated_at = NOW()
            WHERE 
                account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 200,
                'message' => 'Donor deleted successfully',
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//DELETE DONOR END

//DELETE DONOR DONATION START
function deleteDonation($userParams)
{
    global $con;

    if (!isset($userParams['donation_items_id']) || !isset($userParams['donation_id'])) {
        return error422('required items in url not found');
    } elseif ($userParams['donation_items_id'] == null || $userParams['donation_id'] == null) {
        return error422('required items in url are null');
    } else {
        $donation_item_id = mysqli_real_escape_string($con, $userParams['donation_items_id']);
        $donation_id = mysqli_real_escape_string($con, $userParams['donation_id']);

        $query = "DELETE FROM donation_items_tbl WHERE donation_items_id = '$donation_item_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            $query2 = "SELECT * FROM donation_items_tbl WHERE donation_id = '$donation_id'";
            $result2 = mysqli_query($con, $query2);
            $donationData = mysqli_fetch_all($result2, MYSQLI_ASSOC);

            if (count($donationData) == 0) {
                $query3 = "DELETE FROM donation_tbl WHERE donation_id = '$donation_id'";
                $result3 = mysqli_query($con, $query3);

                if ($result3) {
                    $data = [
                        'status' => 200,
                        'message' => 'Donation deleted successfully',
                    ];
                    header("HTTP/1.0 200 OK");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 500,
                        'message' => 'Internal Server Error',
                    ];
                    header("HTTP/1.0 500 Internal Server Error");
                    return json_encode($data);
                }
            } else {
                $data = [
                    'status' => 200,
                    'message' => 'Items found in donation items',
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//DELETE DONOR DONATION END

// UPDATE DONATION ITEMS START
function updateDonationItems($userParams, $userInput)
{
    global $con;

    if (!isset($userParams['donation_items_id'])) {
        return error422('ID not found in url');
    } elseif ($userParams['donation_items_id'] == null) {
        return error422('ID is null');
    } else {

        $donation_item_id = mysqli_real_escape_string($con, $userParams['donation_items_id']);

        $item = mysqli_real_escape_string($con, $userInput['item']);
        $item_category_id = mysqli_real_escape_string($con, $userInput['item_category_id']);
        $qty = mysqli_real_escape_string($con, $userInput['qty']);
        $cost = mysqli_real_escape_string($con, $userInput['cost']);
        $donor_signature = mysqli_real_escape_string($con, $userInput['donor_signature']);

        if (empty(trim($item))) {
            return error422('Enter valid item');
        } elseif (empty(trim($item_category_id))) {
            return error422('Enter valid item category');
        } elseif (empty(trim($qty))) {
            return error422('Enter valid quantity');
        } elseif (empty(trim($cost))) {
            return error422('Enter valid cost');
        } elseif (empty(trim($donor_signature))) {
            return error422('Enter valid donor signature');
        }

        $query = "UPDATE donation_items_tbl 
            SET 
                item = '$item', 
                item_category_id = '$item_category_id', 
                qty = '$qty', 
                cost = '$cost', 
                donor_signature = '$donor_signature' 
            WHERE 
                donation_items_id = '$donation_item_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 200,
                'message' => 'Donation item updated successfully',
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// UPDATE DONATION ITEMS END


// UPDATE DONOR ACC START
function updateAccount($account_id, $userInput)
{
    global $con;

    if (!isset($userParams['account_id'])) {
        return error422('Account ID not found in URL');
    } elseif ($userParams['account_id'] == null) {
        return error422('account id is null');
    } else {

        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $section = mysqli_real_escape_string($con, $userInput['section']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);


        if (empty(trim($last_name))) {
            return error422('Enter valid last name');
        } elseif (empty(trim($first_name))) {
            return error422('Enter valid first name');
        } elseif (empty(trim($middle_name))) {
            return error422('Enter valid middle name');
        } elseif (empty(trim($section))) {
            return error422('Enter valid section');
        } elseif (empty(trim($dept_category_id))) {
            return error422('Enter valid department category');
        } elseif (empty(trim($designation_id))) {
            return error422('Enter valid designation id');
        } elseif (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } elseif (empty(trim($contact_info))) {
            return error422('Enter valid contact information');
        } else {
            $query = "UPDATE account_tbl 
                SET 
                    last_name = '$last_name', 
                    first_name = '$first_name', 
                    middle_name = '$middle_name', 
                    section = '$section',
                    dept_category_id = '$dept_category_id',
                    designation_id = '$designation_id', 
                    email = '$email', 
                    password = '$password', 
                    contact_info = '$contact_info' 
                    updated_at = NOW()
                WHERE 
                    account_id = '$account_id'";
            $result = mysqli_query($con, $query);

            if ($result) {
                $data = [
                    'status' => 200,
                    'message' => 'Donor updated successfully',
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    }
}
// UPDATE DONOR ACC END

// READ DONOR DONATION ON ACCOUNT START
function readDonationDonor($userParams)
{
    global $con;

    if (!isset($userParams['donor_id'])) {
        return error422('Donor ID not found in URL');
    } elseif ($userParams['donor_id'] == null) {
        return error422('Donor ID is null');
    } else {
        $donor_id = mysqli_real_escape_string($con, $userParams['donor_id']);

        $query = "SELECT 
                    donation_tbl.donation_id,
                    donation_status_tbl.status_name,
                    recipient_category_tbl.recipient_type,
                    event_tbl.event_name,   
                    donation_tbl.received_by
                FROM
                    donation_tbl
                    INNER JOIN donation_status_tbl ON donation_tbl.status_id = donation_status_tbl.status_id
                    INNER JOIN recipient_category_tbl ON donation_tbl.recipient_id = recipient_category_tbl.recipient_category_id
                    INNER JOIN event_tbl ON donation_tbl.event_id = event_tbl.evenet_id
                    WHERE donation_tbl.donor_id = '$donor_id';";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Donation Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Donation Found',
                ];
                header("HTTP/1.0 404 Not Found");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// READ DONOR DONATION ON ACCOUNT END

// READ DONOR DONATION ITEMS START
function readDonationItems($userParams)
{
    global $con;

    if (!isset($userParams['donation_id'])) {
        return error422('Donation ID not found in URL');
    } elseif ($userParams['donation_id'] == null) {
        return error422('Donation ID is null');
    } else {
        $donation_id = mysqli_real_escape_string($con, $userParams['donation_id']);

        $query = "SELECT 
            donation_items_tbl.item,
            item_category_tbl.category_name,
            donation_items_tbl.qty,
            donation_items_tbl.cost,
            donation_items_tbl.donor_signature
        FROM
            donation_items_tbl
        INNER JOIN item_category_tbl ON item_category_tbl.item_category_id = donation_items_tbl.item_category_id
        WHERE donation_items_tbl.donation_id = '$donation_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Donation Items Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Donation Items Found',
                ];
                header("HTTP/1.0 404 Not Found");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// READ DONOR DONATION ITEMS END

// READ VOLUNTEER PROFILE START
function readVolunteerProfile($userParams)
{
    global $con;

    if (!isset($userParams['account_id'])) {
        return error422('Account ID not found in URL');
    } elseif ($userParams['account_id'] == null) {
        return error422('Account ID is null');
    } else {
        $account_id = mysqli_real_escape_string($con, $userParams['account_id']);

        $query = "SELECT 
            account_tbl.last_name,
            account_tbl.first_name,
            account_tbl.middle_name,
            account_tbl.section,
            dept_category_tbl.category_name,
            designation_category_tbl.designation_name,
            account_tbl.email,
            account_tbl.password,
            account_tbl.contact_info
            account_tbl.total_hours
        FROM
            account_tbl
        INNER JOIN dept_category_tbl ON account_tbl.dept_category_id = dept_category_tbl.dept_category_id
        INNER JOIN designation_category_tbl ON account_tbl.designation_id = designation_category_tbl.designation_id 
        WHERE 
            account_tbl.account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) == 1) {
                $res = mysqli_fetch_assoc($result);

                $data = [
                    'status' => 200,
                    'message' => 'Volunteer Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    }
}
// READ VOLUNTEER PROFILE END

// READ DONOR PROFILE START
function readDonorProfile($userParams)
{
    global $con;

    if (!isset($userParams['account_id'])) {
        return error422('Account ID not found in URL');
    } elseif ($userParams['account_id'] == null) {
        return error422('Account ID is null');
    } else {
        $account_id = mysqli_real_escape_string($con, $userParams['account_id']);

        $query = "SELECT 
            account_tbl.last_name,
            account_tbl.first_name,
            account_tbl.middle_name,
            account_tbl.section,
            dept_category_tbl.category_name,
            designation_category_tbl.designation_name,
            account_tbl.email,
            account_tbl.password,
            account_tbl.contact_info
        FROM
            account_tbl
        INNER JOIN dept_category_tbl ON account_tbl.dept_category_id = dept_category_tbl.dept_category_id
        INNER JOIN designation_category_tbl ON account_tbl.designation_id = designation_category_tbl.designation_id 
        WHERE 
            account_tbl.account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) == 1) {
                $res = mysqli_fetch_assoc($result);

                $data = [
                    'status' => 200,
                    'message' => 'Volunteer Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Internal Server Error',
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    }
}
// READ DONOR PROFILE END

// READ PARTNERS START
function readPartners()
{
    global $con;

    $query = "SELECT partner_name FROM partners_tbl";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $data = [
                'status' => 200,
                'message' => 'Partners Fetched Successfully ',
                'data' => $res,
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Partners Found',
            ];
            header("HTTP/1.0 404 Not Found");
            return json_encode($data);
        }
    } else {
        $data = [
            'status' => 500,
            'message' => 'Internal Server Error',
        ];
        header("HTTP/1.0 500 Internal Server Error");
        return json_encode($data);
    }
}
// READ PARTNERS END

// READ EVENTS START
function readEvents()
{
    global $con;

    $query = "SELECT event_name, event_link, description, start_date, end_date FROM event_tbl";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $data = [
                'status' => 200,
                'message' => 'Events Fetched Successfully',
                'data' => $res,
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Events Found',
            ];
            header("HTTP/1.0 404 Not Found");
            return json_encode($data);
        }
    } else {
        $data = [
            'status' => 500,
            'message' => 'Internal Server Error',
        ];
        header("HTTP/1.0 500 Internal Server Error");
        return json_encode($data);
    }
}
// READ EVENTS END

// READ PHASE 2 START
function readPhase2Log($userParams)
{
    global $con;

    if (!isset($userParams['volunteer_id'])) {
        return error422('Volunteer ID not found in URL');
    } elseif ($userParams['volunteer_id'] == null) {
        return error422('Volunteer ID is null');
    } else {
        $account_id = mysqli_real_escape_string($con, $userParams['account_id']);

        $query = "SELECT 
            phase2_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase2_tbl.activity,
            phase2_tbl.time_in,
            phase2_tbl.time_out,
            phase2_tbl.signature,
            phase2_tbl.date
        FROM
            phase2_tbl
        INNER JOIN event_tbl ON phase2_tbl.event_id = event_tbl.evenet_id
        INNER JOIN account_tbl ON phase2_tbl.account_id = account_tbl.account_id
        WHERE 
            phase2_tbl.account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Phase 2 Fetched Successfully',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Phase 2 Found',
                ];
                header("HTTP/1.0 404 Not Found");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// READ PHASE 2 END

// READ PHASE 3 START
function readPhase3Log($userParams)
{
    global $con;

    if (!isset($userParams['account_id'])) {
        return error422('Account ID not found in URL');
    } elseif ($userParams['account_id'] == null) {
        return error422('Account ID is null');
    } else {
        $account_id = mysqli_real_escape_string($con, $userParams['account_id']);

        $query = "SELECT 
            phase3_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase3_tbl.time_in,
            phase3_tbl.time_out,
            phase3_tbl.signature,
            phase3_tbl.date
        FROM
            phase3_tbl
        INNER JOIN event_tbl ON phase3_tbl.event_id = event_tbl.evenet_id
        INNER JOIN volunteer_acc_tbl ON phase3_tbl.account_id = account_tbl.account_id
        WHERE 
            phase3_tbl.volunteer_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Phase 3 Fetched Successfully',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Phase 3 Found',
                ];
                header("HTTP/1.0 404 Not Found");
                return json_encode($data);
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// READ PHASE 3 END

// UPDATE DONATION ACCEPT START
function updateDonationAccept($userParams, $account_id)
{
    global $con;

    if (!isset($userParams['account_id']) || !isset($userParams['donation_id'])) {
        return error422('Volunteer ID is missing or Donation ID is missing');
    } elseif ($userParams['account_id'] == null || $userParams['donation_id'] == null) {
        return error422('Volunteer ID is null or Donation ID is null');
    } else {
        $donation_id = mysqli_real_escape_string($con, $userParams['donation_id']);

        $query = "UPDATE donation_tbl SET status_id = 3001, received_by = '$account_id' WHERE donation_id = '$donation_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 200,
                'message' => 'Donation Updated Successfully',
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
// UPDATE DONATION ACCEPT END
