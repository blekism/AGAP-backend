<?php

require __DIR__ . '/../inc/dbcon.php';
require __DIR__ . '/../vendor/autoload.php';
// require 'creds.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use Google\Cloud\Storage\StorageClient;

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

function sendMail($verificationCode, $email)
{
    global $myEmail, $myPassword;
    //gawing function ang send ng email to shorten code
    $mail = new PHPMailer(true);
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
        $mail->addAddress($email);     //Add a recipient

        //Content
        $mail->isHTML(true);                                  //Set email format to HTML
        $mail->Subject = 'Verification code';
        $mail->Body    = 'Your verification code is: ' . $verificationCode;
        $mail->AltBody = 'Your verification code is: ' . $verificationCode;

        $mail->send();
        echo 'Message has been sent';
    } catch (Exception $e) {
        echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
    }
}

function generateSignedUrl($userInput)
{
    $bucketName = ($userInput['bucketName']);
    $fileName = ($userInput['fileName']);

    $storage = new StorageClient(['keyFilePath' => __DIR__ . '/agap-system-e1fa5e729bad.json']);

    $bucket = $storage->bucket($bucketName);
    $object = $bucket->object($fileName);

    $url = $object->signedUrl(new \DateTime('tomorrow'));

    return json_encode($url);
}


function deductFromStock($userInput)
{
    global $con;

    $event_id = mysqli_real_escape_string($con, $userInput['evenet_id']);

    $queryGetContrib = "SELECT contrib_amount FROM event_tbl WHERE evenet_id = '$event_id'";
    $resultGetContrib = mysqli_query($con, $queryGetContrib);
    $currentContrib = 0;

    if ($resultGetContrib) {
        $row = mysqli_fetch_assoc($resultGetContrib);
        $currentContrib = $row['contrib_amount'];
    } else {
        return error422('Error fetching current contribution amount');
    }

    if (!isset($userInput['items'])) {
        return error422('required items not found');
    } elseif ($userInput['items'] == null) {
        return error422('required items are null');
    } else {
        foreach ($userInput['items'] as $item => $amount) {
            $item_to_deduct = mysqli_real_escape_string($con, $amount);
            $itemType = mysqli_real_escape_string($con, $item);

            if (empty(trim($item_to_deduct))) {
                return error422('Enter valid item amount');
            } elseif (empty(trim($itemType))) {
                return error422('Enter valid item id');
            } else {

                $query = "SELECT SUM(in_stock) as total_stock
                       FROM donation_items_tbl
                       WHERE item = '$itemType' AND in_stock > 0";
                $result = mysqli_query($con, $query);
                $row = mysqli_fetch_assoc($result);
                $total_stock = $row['total_stock'];

                // Check if total available stock is sufficient
                if ($total_stock < $item_to_deduct) {
                    return error422('Not enough stock to deduct the requested amount');
                }



                $query1 = "SELECT 
                donation_tbl.donation_id,
                donation_items_tbl.donation_items_id,
                donation_items_tbl.item, 
                donation_items_tbl.in_stock,
                donation_tbl.received_date,
                donation_items_tbl.cost
            FROM 
                donation_items_tbl
            INNER JOIN 
                donation_tbl ON  donation_items_tbl.donation_id = donation_tbl.donation_id
            WHERE 
                donation_items_tbl.item = '$itemType' AND 
                donation_items_tbl.in_stock > 0 
            ORDER BY donation_tbl.received_date ASC";
                $result1 = mysqli_query($con, $query1);

                if (!$result1) {
                    return error422('Error fetching donation items');
                } else {
                    while ($donationItems = mysqli_fetch_assoc($result1)) {
                        $in_stock = $donationItems['in_stock'];
                        $cost = $donationItems['cost']; // purpose is to add to contribution amount of the event
                        $donation_items_id = $donationItems['donation_items_id'];

                        if ($item_to_deduct > 0) {
                            if ($in_stock >= $item_to_deduct) {
                                $new_stock = $in_stock - $item_to_deduct;
                                $query2 = "UPDATE 
                                    donation_items_tbl 
                                SET 
                                    in_stock = '$new_stock' 
                                WHERE 
                                    donation_items_id = '$donation_items_id'";
                                $result2 = mysqli_query($con, $query2);

                                if ($result2) {
                                    // Calculate the percentage of stock used
                                    $percentage_used = $item_to_deduct / $in_stock;
                                    // Calculate the contribution amount
                                    $contribution_amount = $cost * $percentage_used;

                                    $currentContrib += $contribution_amount;



                                    $item_to_deduct = 0;


                                    //query here para sa pagupdate ng contribution event including yung event id
                                } else {
                                    return error422('Error updating stock');
                                }
                            } else {

                                $percentage_used = $in_stock / $item_to_deduct;
                                $contribution_amount = $cost * $percentage_used;

                                $currentContrib += $contribution_amount;

                                $item_to_deduct = $item_to_deduct - $in_stock;

                                $query3 = "UPDATE donation_items_tbl SET in_stock = 0 WHERE donation_items_id = '$donation_items_id'";
                                $result3 = mysqli_query($con, $query3);

                                if (!$result3) {
                                    return error422('Error updating stock');
                                }


                                //query here para sa pagupdate ng contribution event including yung event id

                            }
                        }
                    }
                    $query4 = "UPDATE event_tbl SET contrib_amount = '$currentContrib' WHERE evenet_id = '$event_id'";
                    $result4 = mysqli_query($con, $query4);

                    if (!$result4) {
                        return error422('Error updating contribution amount');
                    }
                }
            }
        }

        $data = [
            'status' => 200,
            'message' => 'Stock Deducted Successfully',
        ];
        header("HTTP/1.0 200 OK");
        return json_encode($data);
    }
}

function readAllDonations($userInput)
{
    global $con;

    $statusid = mysqli_real_escape_string($con, $userInput['status_id']);

    if (empty(trim($statusid))) {
        return error422('Enter valid status id');
    } else {
        $query = "SELECT 
        donation_tbl.donation_id,
        donation_tbl.total_cost,
        donor_account.last_name AS donor_lastName, 
        donation_status_tbl.status_name,
        recipient_category_tbl.recipient_type, 
        reciever_account.last_name AS receiver_lastName, 
        donation_tbl.received_date
        FROM donation_tbl
        INNER JOIN account_tbl AS donor_account ON donation_tbl.account_id = donor_account.account_id
        LEFT JOIN account_tbl AS reciever_account ON donation_tbl.received_by = reciever_account.account_id
        INNER JOIN donation_status_tbl ON donation_tbl.status_id = donation_status_tbl.status_id
        INNER JOIN recipient_category_tbl ON donation_tbl.recipient_id = recipient_category_tbl.recipient_category_id
        WHERE donation_tbl.status_id = '$statusid'";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Donations Fetched Successfully yay',
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
}

function readAllDonationsVolunteer($userInput)
{
    global $con;

    $query = "SELECT 
        donation_tbl.donation_id,
        donor_account.last_name AS donor_lastName, 
        donation_status_tbl.status_name,
        recipient_category_tbl.recipient_type, 
        reciever_account.last_name AS receiver_lastName, 
        donation_tbl.received_date
        FROM donation_tbl
        INNER JOIN account_tbl AS donor_account ON donation_tbl.account_id = donor_account.account_id
        LEFT JOIN account_tbl AS reciever_account ON donation_tbl.received_by = reciever_account.account_id
        INNER JOIN donation_status_tbl ON donation_tbl.status_id = donation_status_tbl.status_id
        INNER JOIN recipient_category_tbl ON donation_tbl.recipient_id = recipient_category_tbl.recipient_category_id";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $data = [
                'status' => 200,
                'message' => 'Donations Fetched Successfully',
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


function readDonationReceivedByVolunteer($userInput)
{
    global $con;

    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = "SELECT 
        donation_tbl.donation_id,
        donor_account.last_name AS donor_lastName, 
        donation_status_tbl.status_name,
        recipient_category_tbl.recipient_type, 
        reciever_account.last_name AS receiver_lastName, 
        donation_tbl.received_date
        FROM donation_tbl
        INNER JOIN account_tbl AS donor_account ON donation_tbl.account_id = donor_account.account_id
        LEFT JOIN account_tbl AS reciever_account ON donation_tbl.received_by = reciever_account.account_id
        INNER JOIN donation_status_tbl ON donation_tbl.status_id = donation_status_tbl.status_id
        INNER JOIN recipient_category_tbl ON donation_tbl.recipient_id = recipient_category_tbl.recipient_category_id
        WHERE donation_tbl.received_by = '$account_id';";

        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Donations Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Donations Found',
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

function readSpeicifcCategory($category_id)
{
    global $con;

    if (!isset($category_id['category_id'])) {
        return error422('Enter valid category id');
    } else {
        $clean_category_id = mysqli_real_escape_string($con, $category_id['category_id']);
        $query = "SELECT donation_items_tbl.item, SUM(donation_items_tbl.in_stock) AS total_Stock
            FROM donation_items_tbl
            WHERE donation_items_tbl.item_category_id = '$clean_category_id' AND donation_items_tbl.in_stock > 0
            GROUP BY 
            donation_items_tbl.item;";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_all($result, MYSQLI_ASSOC);
                $data = [
                    'status' => 200,
                    'message' => 'Category Items Fetched Successfully',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Items Found',
                ];
                header("HTTP/1.0 404 Not Found");
                return json_encode($data);
            }
        }
    }
}

function insertDonation($userInput)
{
    global $con;

    mysqli_begin_transaction($con);
    $donation_id = 'DONATE' . date('Y-d') . '-' . uniqid();
    $recipient_id = mysqli_real_escape_string($con, $userInput['recipient_id']);
    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);
    $total_cost = mysqli_real_escape_string($con, $userInput['total_cost']);
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
                total_cost,
                recipient_id) 
            VALUES(
                '$donation_id', 
                '$account_id', 
                '$total_cost',
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
                    $in_stock = $qty;
                    $cost = mysqli_real_escape_string($con, $item['cost']);

                    $query2 = "INSERT INTO 
                        donation_items_tbl(
                            donation_items_id,
                            donation_id, 
                            item, 
                            item_category_id,
                            qty,
                            in_stock, 
                            cost) 
                        VALUES(
                            '$donation_items_id',
                            '$donate_id', 
                            '$donate_item', 
                            '$item_category_id', 
                            '$qty', 
                            '$in_stock',
                            '$cost')";
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

function signVolunteer($userInput)
{
    global $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $account_id = 'USER - ' . date('Y-d') . substr(uniqid(), -5);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);
        $section = mysqli_real_escape_string($con, $userInput['section']);

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

            $verificationCode = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
            $verification_expiry = time() + 600;
            sendMail($verificationCode, $email);

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
                verification_expiry,
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
                '$verification_expiry',
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
        }
    } else {
        return error422('Enter Email and Password');
    }
}

function signDonor($userInput)
{
    global $con;

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $account_id = 'USER - ' . date('Y-d') . substr(uniqid(), -5);
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

            $verificationCode = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
            $verification_expiry = time() + 600;
            sendMail($verificationCode, $email);
            //ADD DITO YUNG READ NG ACCOUNT TABLE TO SEE IF MAY GANUN NANG EMAIL

            $query = "INSERT INTO 
            account_tbl(
                account_id,
                is_volunteer, 
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
                verification_expiry,
                created_at) 
            VALUES(
                '$account_id', 
                'donor',
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
                '$verification_expiry',
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
        }
    } else {
        return error422('Enter Email and Password');
    }
}

function updateVerification($userInput)
{
    global $con;

    if (empty(trim($userInput['verification_code']))) {
        return error422('Enter valid verification code');
    } else {
        $verificationCode = mysqli_real_escape_string($con, $userInput['verification_code']);

        $query2 = "SELECT email, verification_expiry FROM account_tbl WHERE verification_code = '$verificationCode'";
        $result2 = mysqli_query($con, $query2);

        if ($result2 && mysqli_num_rows($result2) == 1) {
            $res = mysqli_fetch_assoc($result2);
            $expire = $res['verification_expiry'];
            $email = $res['email'];
            if (time() > $expire) { //if expired, lalabas link sa baba to resend verification code using the user's email address lang ulit 
                $newCode = substr(number_format(time() * rand(), 0, '', ''), 0, 6);
                $newExpiry = time() + 600;

                $query3 = "UPDATE account_tbl SET verification_code = '$newCode', verification_expiry = '$newExpiry' WHERE verification_code = '$verificationCode'";
                $result3 = mysqli_query($con, $query3);

                sendMail($newCode, $email);

                if ($result3) {
                    $data = [
                        'status' => 401,
                        'message' => 'Verification code expired, new code sent to email',
                    ];
                    header("HTTP/1.0 401 Unauthorized");
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
                $query = "UPDATE account_tbl SET verified_at = NOW(), verification_code = NULL, verification_expiry = NULL WHERE verification_code = '$verificationCode'";
                $result = mysqli_query($con, $query);

                if ($result) {
                    $data = [
                        'status' => 200,
                        'message' => 'Account verification successful',
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
}

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
            $query = "SELECT account_id, verified_at, session_expire FROM 
                    account_tbl 
                WHERE 
                    email = '$email' AND 
                    password = '$hashing' AND 
                    account_id LIKE 'USER - %';";
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
                            setcookie('volun_session_token', $session_token, [
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

function loginDonorAcc($userInput)
{
    global $con;
    $secret_key = "mamamobading";

    if (isset($userInput['email']) && isset($userInput['password'])) {
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);

        $hashing = md5($password);

        if (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } else {
            $query = "SELECT account_id, verified_at FROM 
                    account_tbl 
                WHERE 
                    email = '$email' AND 
                    password = '$hashing';";
            $result = mysqli_query($con, $query);


            if ($result) {
                if (mysqli_num_rows($result) == 1) {
                    $res = mysqli_fetch_assoc($result);
                    if ($res['verified_at'] != null) {
                        $account_id = $res['account_id'];

                        // Generate JWT token
                        $issued_at = time();
                        $expiration_time = $issued_at + (60 * 60);  // Token valid for 1 hour
                        $payload = [
                            'iss' => 'localhost',  // Issuer
                            'iat' => $issued_at,        // Issued at
                            'exp' => $expiration_time,  // Expiration time
                            'sub' => $account_id       // Subject (user's account ID)
                        ];

                        $jwt = JWT::encode($payload, $secret_key, 'HS256');

                        setcookie('donor_token', $jwt, [
                            'expires' => $expiration_time,  // Expiration time of the cookie
                            'path' => '/',                  // Available in the whole domain
                            'httponly' => true,             // Cannot be accessed via JavaScript
                            'secure' => true,               // Only sent over HTTPS
                            'samesite' => 'Strict'          // Prevent CSRF
                        ]);

                        $data = [
                            'status' => 200,
                            'message' => 'Login successful',
                            'token' => $jwt  // Optionally return JWT to client if needed
                        ];
                        header("HTTP/1.0 200 OK");
                        return json_encode($data);
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

function insertEvent($userInput)
{
    global $con;

    $event_id = 'EVENT' . date('Y-d') . '-' . uniqid();
    $name = mysqli_real_escape_string($con, $userInput['event_name']);
    $event_link = mysqli_real_escape_string($con, $userInput['event_link']);
    $description = mysqli_real_escape_string($con, $userInput['description']);
    $start_date = mysqli_real_escape_string($con, $userInput['start_date']);
    $end_date = mysqli_real_escape_string($con, $userInput['end_date']);
    $contribution_amount = mysqli_real_escape_string($con, $userInput['contrib_amount']);


    if (empty(trim($name))) {
        return error422('Enter valid name');
    } elseif (empty(trim($event_link))) {
        return error422('Enter valid event link');
    } elseif (empty(trim($description))) {
        return error422('Enter valid description');
    } elseif (empty(trim($start_date))) {
        return error422('Enter valid start date');
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


function insertPhase2($userInput)
{

    global $con;

    $log_id = 'LOG-PHASE2' . date('Y-d') . '-' . uniqid();
    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);
    $event_id = mysqli_real_escape_string($con, $userInput['event_id']);
    $activity = mysqli_real_escape_string($con, $userInput['activity']);
    $time_in = mysqli_real_escape_string($con, $userInput['time_in']);
    $time_out = mysqli_real_escape_string($con, $userInput['time_out']);

    if (empty(trim($event_id))) {

        return error422('Enter event id');
    } elseif (empty(trim($activity))) {

        return error422('Enter activity');
    } elseif (empty(trim($time_in))) {

        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {

        return error422('Enter time out');
    } else {

        $query = "INSERT INTO phase2_tbl (log_id, event_id, account_id, activity, time_in, time_out, date) 
        VALUES ('$log_id','$event_id','$account_id','$activity','$time_in','$time_out', CURDATE())";
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

function insertPhase3($userInput)
{
    global $con;

    $log_id = 'LOG-PHASE3' . date('Y-d') . '-' . uniqid();
    $event_id = mysqli_real_escape_string($con, $userInput['evenet_id']);
    $time_in = mysqli_real_escape_string($con, $userInput['start_time']);
    $time_out = mysqli_real_escape_string($con, $userInput['end_time']);
    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);

    if (empty(trim($event_id))) {
        return error422('Enter event id');
    } elseif (empty(trim($time_in))) {
        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {
        return error422('Enter time out');
    } elseif (empty(trim($account_id))) {
        return error422('Enter account id');
    } else {
        $query = "INSERT INTO 
            phase3_tbl (
                log_id, 
                event_id, 
                account_id, 
                time_in, 
                time_out, 
                date) 
            VALUES (
                '$log_id', 
                '$event_id',
                '$account_id',
                '$time_in',
                '$time_out',
                CURDATE())";
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


function updateAccount($account_id, $userInput)
{
    global $con;

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

// READ DONOR DONATION ON ACCOUNT START
// READ DONOR DONATION ON ACCOUNT START
function readDonationDonor($account_id)
{
    global $con;

    $query = "SELECT 
                    donation_tbl.donation_id,
                    donation_status_tbl.status_name,
                    recipient_category_tbl.recipient_type,
                    donation_tbl.received_by,
                    donation_tbl.received_date
                FROM
                    donation_tbl
                    INNER JOIN donation_status_tbl ON donation_tbl.status_id = donation_status_tbl.status_id
                    INNER JOIN recipient_category_tbl ON donation_tbl.recipient_id = recipient_category_tbl.recipient_category_id
                    WHERE donation_tbl.account_id = '$account_id';";
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

function readDonationItems($userInput)
{
    global $con;

    if (!isset($userInput['donation_id'])) {
        return error422('Donation ID not found in URL');
    } elseif ($userInput['donation_id'] == null) {
        return error422('Donation ID is null');
    } else {
        $donation_id = mysqli_real_escape_string($con, $userInput['donation_id']);

        $query = "SELECT
            donation_tbl.donation_id, 
            donation_items_tbl.item,
            item_category_tbl.category_name,
            donation_items_tbl.qty,
            donation_items_tbl.in_stock,
            donation_items_tbl.cost
        FROM
            donation_items_tbl
        INNER JOIN item_category_tbl ON item_category_tbl.item_category_id = donation_items_tbl.item_category_id
        INNER JOIN donation_tbl ON donation_tbl.donation_id = donation_items_tbl.donation_id
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

function readVolunteerProfile($account_id)
{
    global $con;

    $query = "SELECT 
            account_tbl.last_name,
            account_tbl.first_name,
            account_tbl.middle_name,
            account_tbl.section,
            dept_category_tbl.category_name,
            designation_category_tbl.designation_name,
            account_tbl.email,
            account_tbl.password,
            account_tbl.contact_info,
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

function readDonorProfile($account_id)
{
    global $con;


    $query = "SELECT
            account_tbl.account_id, 
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
                'message' => 'Donor Fetched Successfully ',
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

function readAccountID($account_id)
{
    global $con;

    $query = "SELECT account_id FROM account_tbl WHERE account_id = '$account_id'";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) == 1) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Account ID Fetched Successfully ',
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

function readEvents()
{
    global $con;

    $query = "SELECT * FROM event_tbl";
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

function readPhase2Log()
{
    global $con;

    $query = "SELECT 
            phase2_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase2_tbl.activity,
            phase2_tbl.time_in,
            phase2_tbl.time_out,
            phase2_tbl.date
        FROM
            phase2_tbl
        INNER JOIN event_tbl ON phase2_tbl.event_id = event_tbl.evenet_id
        INNER JOIN account_tbl ON phase2_tbl.account_id = account_tbl.account_id";
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

function readPhase3Log()
{
    global $con;
    $query = "SELECT 
            phase3_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase3_tbl.time_in,
            phase3_tbl.time_out,
            phase3_tbl.date
        FROM
            phase3_tbl
        INNER JOIN event_tbl ON phase3_tbl.event_id = event_tbl.evenet_id
        INNER JOIN account_tbl ON phase3_tbl.account_id = account_tbl.account_id";
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

function updateDonationAccept($userInput, $account_id)
{
    global $con;

    $donation_id = mysqli_real_escape_string($con, $userInput['donation_id']);

    if (empty(trim($donation_id))) {
        return error422('Enter valid donation id');
    } else {
        $query = "UPDATE donation_tbl SET status_id = 3001, received_by = '$account_id', received_date = CURDATE() WHERE donation_id = '$donation_id'";
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

function updateDeclineDonation($userInput)
{
    global $con;

    $donation_id = mysqli_real_escape_string($con, $userInput['donation_id']);

    $query = "UPDATE donation_tbl SET status_id = 3004 WHERE donation_id = '$donation_id'";
    $result = mysqli_query($con, $query);

    if ($result) {
        $data = [
            'status' => 200,
            'message' => 'Donation Declined Successfully',
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

function updateDeclineVolunteer($userInput)
{
    global $con;

    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);

    $query = "UPDATE account_tbl SET is_volunteer = 'declined' WHERE account_id = '$account_id'";
    $result = mysqli_query($con, $query);

    if ($result) {
        $data = [
            'status' => 200,
            'message' => 'Volunteer Declined Successfully',
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


function loginAdminAcc($adminAccInput)
{

    global $con;

    if (isset($adminAccInput['email']) && isset($adminAccInput['password'])) {
        $email = mysqli_real_escape_string($con, $adminAccInput['email']);
        $password = mysqli_real_escape_string($con, $adminAccInput['password']);

        $hashing = md5($password);

        if (empty(trim($email))) {

            return error422('Enter valid email');
        } elseif (empty(trim($password))) {

            return error422('Enter valid password');
        } else {

            $query = "SELECT admin_id, session_expire FROM 
                    admin_acc_tbl 
                WHERE 
                    email = '$email' AND 
                    password = '$hashing';";
            $result = mysqli_query($con, $query);

            if ($result) {
                if (mysqli_num_rows($result) == 1) {
                    $res = mysqli_fetch_assoc($result);

                    $session_expire = $res['session_expire'];

                    if (time() > $session_expire) {
                        $session_token = bin2hex(random_bytes(32));
                        $expire = time() + (365 * 24 * 60 * 60);

                        // Store session token in the database for the user
                        $admin_id = $res['admin_id'];
                        $update_token_query = "UPDATE admin_acc_tbl SET session_token='$session_token', session_expire = '$expire' WHERE admin_id='$admin_id'";
                        mysqli_query($con, $update_token_query);

                        // Set the session token as an HTTP-only, secure cookie
                        setcookie('admin_session_token', $session_token, [
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


function insertAdminAcc($adminAccInput)
{

    global $con;

    if (isset($adminAccInput['email']) && isset($adminAccInput['password'])) {
        $admin_id = 'ADMIN' . date('Y') . ' - ' . uniqid();
        $last_name = mysqli_real_escape_string($con, $adminAccInput['last_name']);
        $first_name = mysqli_real_escape_string($con, $adminAccInput['first_name']);
        $middle_name = mysqli_real_escape_string($con, $adminAccInput['middle_name']);
        $email = mysqli_real_escape_string($con, $adminAccInput['email']);
        $password = mysqli_real_escape_string($con, $adminAccInput['password']);
        $contact_info = mysqli_real_escape_string($con, $adminAccInput['contact_info']);

        $hashing = md5($password);

        if (empty(trim($last_name))) {

            return error422('Enter your last name');
        } elseif (empty(trim($first_name))) {

            return error422('Enter your first name');
        } elseif (empty(trim($middle_name))) {

            return error422('Enter your middle name');
        } elseif (empty(trim($email))) {

            return error422('Enter your email');
        } elseif (empty(trim($password))) {

            return error422('Enter your password');
        } elseif (empty(trim($contact_info))) {

            return error422('Enter your contact info');
        } else {

            $query = "INSERT INTO admin_acc_tbl (admin_id, last_name, first_name, middle_name, email, password,  contact_info) 
            VALUES ('$admin_id','$last_name','$first_name','$middle_name','$email','$hashing','$contact_info')";
            $result = mysqli_query($con, $query);

            if ($result) {

                $data = [
                    'status' => 201,
                    'message' => 'Admin Account Inserted Successfully',
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
    } else {
        return error422('Enter Email and Password');
    }
}

function updateAdminAcc($adminAccInput, $adminParams)
{

    global $con;

    if (!isset($adminParams['admin_id'])) {

        return error422('Admin id not found in URL');
    } elseif ($adminParams['admin_id'] == null) {

        return error422('Enter the Admin id');
    }

    $admin_id = mysqli_real_escape_string($con, $adminParams['admin_id']);
    $last_name = mysqli_real_escape_string($con, $adminAccInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $adminAccInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $adminAccInput['middle_name']);
    $email = mysqli_real_escape_string($con, $adminAccInput['email']);
    $password = mysqli_real_escape_string($con, $adminAccInput['password']);
    $contact_info = mysqli_real_escape_string($con, $adminAccInput['contact_info']);

    if (empty(trim($last_name))) {

        return error422('Enter your last name');
    } elseif (empty(trim($first_name))) {

        return error422('Enter your first name');
    } elseif (empty(trim($middle_name))) {

        return error422('Enter your middle name');
    } elseif (empty(trim($email))) {

        return error422('Enter your email');
    } elseif (empty(trim($password))) {

        return error422('Enter your password');
    } elseif (empty(trim($contact_info))) {

        return error422('Enter your contact info');
    } else {

        $query = "UPDATE admin_acc_tbl SET last_name='$last_name', first_name='$first_name',  
        middle_name='$middle_name', email='$email', password='$password', contact_info='$contact_info' 
        WHERE admin_id ='$admin_id' LIMIT 1";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Admin Account Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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
function deleteAdminAcc($adminParams)
{

    global $con;

    if (!isset($adminParams['admin_id'])) {

        return error422('Admin id not found in URL');
    } elseif ($adminParams['admin_id'] == null) {

        return error422('Enter the Admin id');
    }

    $admin_id = mysqli_real_escape_string($con, $adminParams['admin_id']);

    $query = "DELETE FROM admin_acc_tbl WHERE admin_id='$admin_id' LIMIT 1";
    $result = mysqli_query($con, $query);

    if ($result) {

        $data = [
            'status' => 200,
            'message' => 'Admin Deleted Successfully',
        ];
        header("HTTP/1.0 200 Deleted");
        return json_encode($data);
    } else {

        $data = [
            'status' => 404,
            'message' => 'Admin Not Found',
        ];
        header("HTTP/1.0 404 Not Found");
        return json_encode($data);
    }
}


function insertDeptCategory($deptCategoryInput)
{

    global $con;

    $category_name = mysqli_real_escape_string($con, $deptCategoryInput['category_name']);

    if (empty(trim($category_name))) {

        return error422('Enter Department Category Name');
    } else {

        $query = "INSERT INTO dept_category_tbl (category_name) VALUES ('$category_name')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Department Category Inserted Successfully',
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

function getDonation($donationParams)
{

    global $con;

    if ($donationParams['donation_id'] == null) {

        return error422('Enter Donation id');
    }

    $donation_id = mysqli_real_escape_string($con, $donationParams['donation_id']);

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
    WHERE donation_tbl.donation_id='$donation_id' LIMIT 1;";

    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Donation Fetched Successfully',
                'data' => $res
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

function getDonationItemsList()
{

    global $con;

    $query = "SELECT 
        donation_items_tbl.item,
        item_category_tbl.category_name,
        donation_items_tbl.qty,
        donation_items_tbl.cost,
        donation_items_tbl.donor_signature
    FROM
    donation_items_tbl
    INNER JOIN item_category_tbl ON item_category_tbl.item_category_id = donation_items_tbl.item_category_id;";

    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Donation Items List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donation Item Found',
            ];
            header("HTTP/1.0 404 No Donation Found");
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

function getDonationItem($donationItemsParams)
{

    global $con;

    if ($donationItemsParams['donation_items_id'] == null) {

        return error422('Enter Donation id');
    }

    $donation_items_id = mysqli_real_escape_string($con, $donationItemsParams['donation_items_id']);

    $query = "SELECT 
        donation_items_tbl.item,
        item_category_tbl.category_name,
        donation_items_tbl.qty,
        donation_items_tbl.cost,
        donation_items_tbl.donor_signature
    FROM
    donation_items_tbl
    INNER JOIN item_category_tbl ON item_category_tbl.item_category_id = donation_items_tbl.item_category_id
    WHERE donation_items_tbl.donation_items_id='$donation_items_id' LIMIT 1;";

    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Donation Item Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donation Item Found',
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


function getDonorList()
{

    global $con;

    $query = "SELECT 
        account_tbl.account_id, 
        account_tbl.is_volunteer,
        account_tbl.last_name,
        account_tbl.first_name,
        account_tbl.middle_name,
        account_tbl.section,
        dept_category_tbl.category_name,
        designation_category_tbl.designation_name,
        account_tbl.email,
        account_tbl.contact_info,
        account_tbl.verified_at
    FROM
    account_tbl
    INNER JOIN dept_category_tbl ON account_tbl.dept_category_id = dept_category_tbl.dept_category_id 
    INNER JOIN designation_category_tbl ON account_tbl.designation_id = designation_category_tbl.designation_id
    WHERE 
    account_tbl.is_volunteer IN ('donor', 'volunteer', 'volunteer_apply') AND 
    verified_at IS NOT NULL AND 
    acc_status_id = '1';";

    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Donor List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donor Found',
            ];
            header("HTTP/1.0 404 No Donor Found");
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

function getDonor($userInput)
{

    global $con;

    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);

    $query = "SELECT 
        account_tbl.account_id, 
        account_tbl.is_volunteer,
        account_tbl.last_name,
        account_tbl.first_name,
        account_tbl.middle_name,
        account_tbl.section,
        account_tbl.dept_category_id,
        account_tbl.designation_id,
        account_tbl.email,
        account_tbl.contact_info
    FROM
    account_tbl
    WHERE account_tbl.is_volunteer IN ('donor', 'volunteer', 'volunteer_apply') AND account_tbl.account_id = '$account_id' LIMIT 1;";
    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Donor Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donor Found',
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

function updateDonorAcc($donorAccInput)
{

    global $con;

    $account_id = mysqli_real_escape_string($con, $donorAccInput['account_id']);
    $is_volunteer = mysqli_real_escape_string($con, $donorAccInput['is_volunteer']);
    $last_name = mysqli_real_escape_string($con, $donorAccInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $donorAccInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $donorAccInput['middle_name']);
    $section = mysqli_real_escape_string($con, $donorAccInput['section']);
    $dept_category_id = mysqli_real_escape_string($con, $donorAccInput['dept_category_id']);
    $designation_id = mysqli_real_escape_string($con, $donorAccInput['designation_id']);
    $email = mysqli_real_escape_string($con, $donorAccInput['email']);
    $contact_info = mysqli_real_escape_string($con, $donorAccInput['contact_info']);

    if (empty(trim($last_name))) {

        return error422('Enter your last name');
    } elseif (empty(trim($first_name))) {

        return error422('Enter your first name');
    } elseif (empty(trim($middle_name))) {

        return error422('Enter your middle name');
    } elseif (empty(trim($dept_category_id))) {

        return error422('Enter department category id');
    } elseif (empty(trim($designation_id))) {

        return error422('Enter designation id');
    } elseif (empty(trim($email))) {

        return error422('Enter your email');
    } elseif (empty(trim($contact_info))) {
        return error422('Enter your contact info');
    } elseif (empty(trim($section))) {
        return error422('Enter your section');
    } elseif (empty(trim($is_volunteer))) {
        return error422('Enter your is_volunteer');
    } else {

        $query = "UPDATE 
            account_tbl 
                SET 
            is_volunteer='$is_volunteer',    
            last_name='$last_name', 
            first_name='$first_name',  
            middle_name='$middle_name',
            section='$section', 
            dept_category_id='$dept_category_id', 
            designation_id='$designation_id', 
            email='$email', 
            contact_info='$contact_info'
                WHERE  
            account_tbl.account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Donor Account Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function deleteDonorAcc($donorAccParams)
{

    global $con;

    if (!isset($donorAccParams['account_id'])) {

        return error422('Account id not found in URL');
    } elseif ($donorAccParams['account_id'] == null) {

        return error422('Enter the Account id');
    }

    $account_id = mysqli_real_escape_string($con, $donorAccParams['account_id']);

    $query = "DELETE FROM account_tbl WHERE account_id LIKE 'DONOR - %' AND account_id='$account_id' LIMIT 1";

    $result = mysqli_query($con, $query);

    if ($result) {

        $data = [
            'status' => 200,
            'message' => 'Donor Deleted Successfully',
        ];
        header("HTTP/1.0 200 Deleted");
        return json_encode($data);
    } else {

        $data = [
            'status' => 404,
            'message' => 'Donor Not Found',
        ];
        header("HTTP/1.0 404 Not Found");
        return json_encode($data);
    }
}

function getEventList()
{

    global $con;

    $query = "SELECT * FROM event_tbl";
    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Event List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Event Found',
            ];
            header("HTTP/1.0 404 No Event Found");
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

function getEvent($eventInput)
{
    global $con;

    $event_id = mysqli_real_escape_string($con, $eventInput['event_id']);

    if (empty(trim($event_id))) {

        return error422('Enter Event id');
    } else {

        $query = "SELECT * FROM event_tbl WHERE evenet_id='$event_id' LIMIT 1";
        $result = mysqli_query($con, $query);

        if ($result) {

            if (mysqli_num_rows($result) == 1) {

                $res = mysqli_fetch_assoc($result);

                $data = [
                    'status' => 200,
                    'message' => 'Event Fetched Successfully',
                    'data' => $res
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Event Found',
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

function updateEvent($eventInput)
{

    global $con;


    $event_id = mysqli_real_escape_string($con, $eventInput['evenet_id']);
    $event_name = mysqli_real_escape_string($con, $eventInput['event_name']);
    $event_link = mysqli_real_escape_string($con, $eventInput['event_link']);
    $description = mysqli_real_escape_string($con, $eventInput['description']);
    $start_date = mysqli_real_escape_string($con, $eventInput['start_date']);
    $end_date = mysqli_real_escape_string($con, $eventInput['end_date']);
    $contribution_amount = mysqli_real_escape_string($con, $eventInput['contrib_amount']);

    if (empty(trim($event_name))) {

        return error422('Enter valid event name');
    } elseif (empty(trim($event_link))) {

        return error422('Enter valid event link');
    } elseif (empty(trim($description))) {

        return error422('Enter valid description');
    } elseif (empty(trim($start_date))) {

        return error422('Enter valid start date');
    } elseif (empty($end_date)) {
        $end_date = null;
    } elseif (empty(trim($contribution_amount))) {

        return error422('Enter valid contribution amount');
    } else {

        $query = "UPDATE event_tbl SET event_name='$event_name', event_link='$event_link',  
        description='$description', start_date='$start_date', end_date='$end_date', contrib_amount='$contribution_amount' 
        WHERE evenet_id ='$event_id' LIMIT 1";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Event Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function insertItemCategory($itemCategoryInput)
{

    global $con;

    $category_name = mysqli_real_escape_string($con, $itemCategoryInput['category_name']);

    if (empty(trim($category_name))) {

        return error422('Enter Partner Name');
    } else {

        $query = "INSERT INTO item_category_tbl (category_name) VALUES ('$category_name')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Item Category Name Inserted Successfully',
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

function getPartnerList()
{

    global $con;

    $query = "SELECT * FROM partners_tbl";
    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Partner List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Partner Found',
            ];
            header("HTTP/1.0 404 No Partner Found");
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

function getPartner($partnerParams)
{

    global $con;

    if ($partnerParams['partner_id'] == null) {

        return error422('Enter Partner id');
    }

    $partner_id = mysqli_real_escape_string($con, $partnerParams['partner_id']);

    $query = "SELECT * FROM partners_tbl WHERE partner_id='$partner_id' LIMIT 1";
    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Partner Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Partner Found',
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

function insertPartner($partnerInput)
{

    global $con;

    $partner_id = 'PARTNER' . date('Y') . ' - ' . uniqid();
    $partner_name = mysqli_real_escape_string($con, $partnerInput['partner_name']);

    if (empty(trim($partner_name))) {

        return error422('Enter Partner Name');
    } else {

        $query = "INSERT INTO partners_tbl (partner_id, partner_name) VALUES ('$partner_id','$partner_name')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Partner Name Inserted Successfully',
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

function updatePartner($partnerInput, $partnerParams)
{

    global $con;

    if (!isset($partnerParams['partner_id'])) {

        return error422('Partner id not found in URL');
    } elseif ($partnerParams['partner_id'] == null) {

        return error422('Enter the Partner id');
    }

    $partner_id = mysqli_real_escape_string($con, $partnerParams['partner_id']);
    $partner_name = mysqli_real_escape_string($con, $partnerInput['partner_name']);

    if (empty(trim($partner_name))) {

        return error422('Enter partner name');
    } else {

        $query = "UPDATE partners_tbl SET partner_name='$partner_name' WHERE partner_id ='$partner_id' LIMIT 1";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Partners Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function deletePartner($partnerParams)
{

    global $con;

    if (!isset($partnerParams['partner_id'])) {

        return error422('Partner id not found in URL');
    } elseif ($partnerParams['partner_id'] == null) {

        return error422('Enter the Partner id');
    }

    $partner_id = mysqli_real_escape_string($con, $partnerParams['partner_id']);

    $query = "DELETE FROM partners_tbl WHERE partner_id='$partner_id' LIMIT 1";
    $result = mysqli_query($con, $query);

    if ($result) {

        $data = [
            'status' => 200,
            'message' => 'Partner Deleted Successfully',
        ];
        header("HTTP/1.0 200 Deleted");
        return json_encode($data);
    } else {

        $data = [
            'status' => 404,
            'message' => 'Partner Not Found',
        ];
        header("HTTP/1.0 404 Not Found");
        return json_encode($data);
    }
}

function getPhase2List()
{

    global $con;

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
    INNER JOIN account_tbl ON phase2_tbl.account_id = account_tbl.account_id;";

    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Phase 2 List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Log Found',
            ];
            header("HTTP/1.0 404 No Log Found");
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

function getPhase2($phase2Params)
{

    global $con;

    if ($phase2Params['log_id'] == null) {

        return error422('Enter log id');
    }

    $log_id = mysqli_real_escape_string($con, $phase2Params['log_id']);

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
    WHERE phase2_tbl.log_id='$log_id' LIMIT 1;";

    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Phase 2 log Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Phase2 log Found',
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

function updatePhase2($phase2Input, $phase2Params)
{

    global $con;

    if (!isset($phase2Params['log_id'])) {

        return error422('Log id not found in URL');
    } elseif ($phase2Params['log_id'] == null) {

        return error422('Enter the Phase 2 Log id');
    }

    $log_id = mysqli_real_escape_string($con, $phase2Params['log_id']);
    $activity = mysqli_real_escape_string($con, $phase2Input['activity']);
    $time_in = mysqli_real_escape_string($con, $phase2Input['time_in']);
    $time_out = mysqli_real_escape_string($con, $phase2Input['time_out']);
    $signature = mysqli_real_escape_string($con, $phase2Input['signature']);
    $date = mysqli_real_escape_string($con, $phase2Input['date']);

    if (empty(trim($activity))) {

        return error422('Enter activity');
    } elseif (empty(trim($time_in))) {

        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {

        return error422('Enter time_out');
    } elseif (empty(trim($signature))) {

        return error422('Enter signature');
    } elseif (empty(trim($date))) {

        return error422('Enter date');
    } else {

        $query = "UPDATE phase2_tbl SET activity='$activity', time_in='$time_in',  
        time_out='$time_out', signature='$signature', date='$date'
        WHERE log_id ='$log_id' LIMIT 1";

        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Log Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function getPhase3List()
{

    global $con;

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
    INNER JOIN account_tbl ON phase3_tbl.account_id = account_tbl.account_id;";

    $query_run = mysqli_query($con, $query);

    if ($query_run) {

        if (mysqli_num_rows($query_run) > 0) {

            $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Phase 3 List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Log Found',
            ];
            header("HTTP/1.0 404 No Log Found");
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

function getPhase3($phase3Params)
{

    global $con;

    if ($phase3Params['log_id'] == null) {

        return error422('Enter log id');
    }

    $log_id = mysqli_real_escape_string($con, $phase3Params['log_id']);

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
    INNER JOIN account_tbl ON phase3_tbl.account_id = account_tbl.account_id
    WHERE phase3_tbl.log_id='$log_id' LIMIT 1;";

    $result = mysqli_query($con, $query);

    if ($result) {

        if (mysqli_num_rows($result) == 1) {

            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Phase 3 log Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Phase3 log Found',
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

function updatePhase3($phase3Input, $phase3Params)
{

    global $con;

    if (!isset($phase3Params['log_id'])) {

        return error422('Log id not found in URL');
    } elseif ($phase3Params['log_id'] == null) {

        return error422('Enter the Phase 3 Log id');
    }

    $log_id = mysqli_real_escape_string($con, $phase3Params['log_id']);
    $time_in = mysqli_real_escape_string($con, $phase3Input['time_in']);
    $time_out = mysqli_real_escape_string($con, $phase3Input['time_out']);
    $signature = mysqli_real_escape_string($con, $phase3Input['signature']);
    $date = mysqli_real_escape_string($con, $phase3Input['date']);

    if (empty(trim($time_in))) {

        return error422('Enter time in');
    } elseif (empty(trim($time_out))) {

        return error422('Enter time_out');
    } elseif (empty(trim($signature))) {

        return error422('Enter signature');
    } elseif (empty(trim($date))) {

        return error422('Enter date');
    } else {

        $query = "UPDATE phase3_tbl SET time_in='$time_in',  
        time_out='$time_out', signature='$signature', date='$date'
        WHERE log_id ='$log_id' LIMIT 1";

        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Log Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function getVolunteerList($userInput)
{

    global $con;

    $account_level = mysqli_real_escape_string($con, $userInput['is_volunteer']);

    if (empty(trim($account_level))) {
        return error422('Enter account level');
    } else {
        $query = "SELECT 
        account_tbl.account_id, 
        account_tbl.is_volunteer,
        account_tbl.last_name,
        account_tbl.first_name,
        account_tbl.middle_name,
        account_tbl.section,
        dept_category_tbl.category_name,
        designation_category_tbl.designation_name,
        account_tbl.email,
        account_tbl.contact_info,
        account_tbl.total_hours
    FROM
    account_tbl
    INNER JOIN dept_category_tbl ON account_tbl.dept_category_id = dept_category_tbl.dept_category_id
    INNER JOIN designation_category_tbl ON account_tbl.designation_id = designation_category_tbl.designation_id
    WHERE account_tbl.is_volunteer = '$account_level' AND verified_at IS NOT NULL AND acc_status_id = '1';";

        $query_run = mysqli_query($con, $query);

        if ($query_run) {

            if (mysqli_num_rows($query_run) > 0) {

                $res = mysqli_fetch_all($query_run, MYSQLI_ASSOC);

                $data = [
                    'status' => 200,
                    'message' => 'Volunteer List Fetched Successfully',
                    'data' => $res
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Volunteer Found',
                ];
                header("HTTP/1.0 404 No Volunteer Found");
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

function getVolunteer($userInput)
{

    global $con;

    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);
    $is_volunteer = mysqli_real_escape_string($con, $userInput['is_volunteer']);

    if (empty(trim($account_id))) {
        return error422('Enter account id');
    } elseif (empty(trim($is_volunteer))) {
        return error422('Enter account level');
    } else {
        $query = "SELECT 
        account_tbl.account_id, 
        account_tbl.is_volunteer,
        account_tbl.last_name,
        account_tbl.first_name,
        account_tbl.middle_name,
        account_tbl.section,
        account_tbl.dept_category_id,
        account_tbl.designation_id,
        account_tbl.email,
        account_tbl.contact_info,
        account_tbl.total_hours
    FROM
    account_tbl
    WHERE account_tbl.is_volunteer = '$is_volunteer' AND account_tbl.account_id = '$account_id'LIMIT 1;";
        $result = mysqli_query($con, $query);

        if ($result) {

            if (mysqli_num_rows($result) == 1) {

                $res = mysqli_fetch_assoc($result);

                $data = [
                    'status' => 200,
                    'message' => 'Volunteer Fetched Successfully',
                    'data' => $res
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Volunteer Found',
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

function updateVolunteerAcc($volunteerAccInput)
{

    global $con;

    $account_id = mysqli_real_escape_string($con, $volunteerAccInput['account_id']);
    $is_volunteer = mysqli_real_escape_string($con, $volunteerAccInput['is_volunteer']);
    $last_name = mysqli_real_escape_string($con, $volunteerAccInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $volunteerAccInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $volunteerAccInput['middle_name']);
    $section = mysqli_real_escape_string($con, $volunteerAccInput['section']);
    $dept_category_id = mysqli_real_escape_string($con, $volunteerAccInput['dept_category_id']);
    $designation_id = mysqli_real_escape_string($con, $volunteerAccInput['designation_id']);
    $email = mysqli_real_escape_string($con, $volunteerAccInput['email']);
    $contact_info = mysqli_real_escape_string($con, $volunteerAccInput['contact_info']);
    $total_hours = mysqli_real_escape_string($con, $volunteerAccInput['total_hours']);

    if (empty(trim($last_name))) {

        return error422('Enter your last name');
    } elseif (empty(trim($first_name))) {

        return error422('Enter your first name');
    } elseif (empty(trim($middle_name))) {

        return error422('Enter your middle name');
    } elseif (empty(trim($email))) {

        return error422('Enter your email');
    } elseif (empty(trim($contact_info))) {

        return error422('Enter your contact info');
    } elseif (empty(trim($dept_category_id))) {

        return error422('Enter department category id');
    } elseif (empty(trim($section))) {

        return error422('Enter section');
    } elseif (empty(trim($designation_id))) {

        return error422('Enter designation id');
    } else {

        $query = "UPDATE account_tbl SET
            is_volunteer='$is_volunteer', 
            last_name='$last_name', 
            first_name='$first_name',  
            middle_name='$middle_name', 
            email='$email', 
            contact_info='$contact_info', 
            total_hours='$total_hours', 
            dept_category_id='$dept_category_id', 
            section='$section', 
            designation_id='$designation_id' 
        WHERE account_tbl.account_id = '$account_id'";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 200,
                'message' => 'Volunteer Account Updated Successfully',
            ];
            header("HTTP/1.0 200 Success");
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

function deleteVolunteerAcc($userInput)
{

    global $con;

    $account_id = mysqli_real_escape_string($con, $userInput['account_id']);

    $query = "UPDATE 
        account_tbl 
            SET 
        acc_status_id = '2',
        last_name = 'Deleted',
        first_name = 'Deleted',
        middle_name = 'Deleted',
        section = 'Deleted',
        email = 'Deleted',
        password = 'Deleted',
        contact_info = 'Deleted',
        updated_at = CURDATE()
        WHERE account_id='$account_id'";

    $result = mysqli_query($con, $query);

    if ($result) {

        $data = [
            'status' => 200,
            'message' => 'Volunteer Deleted Successfully',
        ];
        header("HTTP/1.0 200 Deleted");
        return json_encode($data);
    } else {

        $data = [
            'status' => 404,
            'message' => 'Volunteer Not Found',
        ];
        header("HTTP/1.0 404 Not Found");
        return json_encode($data);
    }
}


function insertEventAnnouncement($userInput)
{
    global $con;


    $announcement_id = 'ANNOUNCE' . date('Y-d') . ' - ' . uniqid();
    $title = mysqli_real_escape_string($con, $userInput['title']);
    $description = mysqli_real_escape_string($con, $userInput['description']);
    $image = mysqli_real_escape_string($con, $userInput['image']);

    if (empty(trim($title))) {
        return error422('Enter title');
    } elseif (empty(trim($description))) {
        return error422('Enter description');
    } elseif (empty(trim($image))) {
        return error422('Enter image');
    } else {
        $query = "INSERT INTO 
            event_announcement_tbl (
                announcement_id, 
                title, 
                description, 
                image) 
            VALUES (
                '$announcement_id', 
                '$title', 
                '$description', 
                '$image')";
        $result = mysqli_query($con, $query);

        if ($result) {

            $data = [
                'status' => 201,
                'message' => 'Event Announcement Inserted Successfully',
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

function getEventAnnouncementList()
{
    global $con;

    $query = "SELECT * FROM  event_announcement_tbl";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_all($result, MYSQLI_ASSOC);

            $data = [
                'status' => 200,
                'message' => 'Event Announcement List Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Event Announcement Found',
            ];
            header("HTTP/1.0 404 No Event Announcement Found");
            return json_encode($data);
        }
    }
}

function readTotalDonation()
{
    global $con;

    $query = "SELECT COUNT(*) as total_donations FROM donation_tbl WHERE status_id != 3000";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Total Donation Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donation Found',
            ];
            header("HTTP/1.0 404 No Donation Found");
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

function readTotalEvents()
{
    global $con;

    $query = "SELECT COUNT(*) as total_events FROM event_tbl WHERE event_status != 'upcoming'";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Total Events Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Events Found',
            ];
            header("HTTP/1.0 404 No Events Found");
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

function readTotalVolunteers()
{
    global $con;

    $query = "SELECT COUNT(*) AS total_volunteers FROM account_tbl WHERE is_volunteer = 'volunteer' AND acc_status_id = 1";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Total Volunteers Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Volunteers Found',
            ];
            header("HTTP/1.0 404 No Volunteers Found");
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

function readTotalDonors()
{
    global $con;

    $query = "SELECT COUNT(*) AS total_donors FROM account_tbl WHERE is_volunteer = 'donor' AND acc_status_id = 1";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Total Donors Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Donors Found',
            ];
            header("HTTP/1.0 404 No Donors Found");
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

function readTotalCost()
{
    global $con;

    $query = "SELECT SUM(donation_items_tbl.cost) as total_cost FROM donation_items_tbl
                    INNER JOIN donation_tbl ON donation_tbl.donation_id = donation_items_tbl.donation_id
                    WHERE donation_tbl.status_id != 3000";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);

            $data = [
                'status' => 200,
                'message' => 'Total Cost Fetched Successfully',
                'data' => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Cost Data Found',
            ];
            header("HTTP/1.0 404 No Cost Data Found");
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

function readTotalDonationsAcceptedByVolunteer($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = " SELECT COUNT(*) as total_donations
        FROM donation_tbl
        WHERE donation_tbl.received_by = '$account_id';";

        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_assoc($result);
                $data = [
                    'status' => 200,
                    'message' => 'Donations Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Donations Found',
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

function readTotalCompletedTasks($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = " SELECT COUNT(*) as total_completed_task
        FROM phase3_tbl
        WHERE phase3_tbl.account_id = '$account_id';";

        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_assoc($result);
                $data = [
                    'status' => 200,
                    'message' => 'Task Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Task Found',
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

function readYourTotalDonations($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = " SELECT COUNT(*) as your_total_donations
        FROM donation_tbl
        WHERE donation_tbl.account_id = '$account_id';";

        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_assoc($result);
                $data = [
                    'status' => 200,
                    'message' => 'Task Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Task Found',
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

function readTotalHours($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = " SELECT total_hours as your_total_hours
        FROM account_tbl
        WHERE account_tbl.account_id = '$account_id';";

        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) > 0) {
                $res = mysqli_fetch_assoc($result);
                $data = [
                    'status' => 200,
                    'message' => 'Task Fetched Successfully ',
                    'data' => $res,
                ];
                header("HTTP/1.0 200 OK");
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Task Found',
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


function readVolunteerPhase2Log($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {
        $query = "SELECT 
            phase2_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase2_tbl.activity,
            phase2_tbl.time_in,
            phase2_tbl.time_out,
            phase2_tbl.date
        FROM
            phase2_tbl
        INNER JOIN event_tbl ON phase2_tbl.event_id = event_tbl.evenet_id
        INNER JOIN account_tbl ON phase2_tbl.account_id = account_tbl.account_id
        WHERE phase2_tbl.account_id = '$account_id'";

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

function readVolunteerPhase3Log($account_id)
{
    global $con;

    if (empty(trim($account_id))) {
        return error422('Enter valid account id');
    } else {

        $query = "SELECT 
            phase3_tbl.log_id, 
            event_tbl.event_name,
            account_tbl.last_name,
            account_tbl.first_name,
            phase3_tbl.time_in,
            phase3_tbl.time_out,
            phase3_tbl.date
        FROM
            phase3_tbl
        INNER JOIN event_tbl ON phase3_tbl.event_id = event_tbl.evenet_id
        INNER JOIN account_tbl ON phase3_tbl.account_id = account_tbl.account_id
        WHERE 
            phase3_tbl.account_id = '$account_id'";
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


function readTotalVolunteerHours()
{
    global $con;

    $query = "SELECT SUM(total_hours) as total_hours FROM account_tbl WHERE is_volunteer = 'volunteer' AND acc_status_id = 1";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_assoc($result);
            $data = [
                'status' => 200,
                'message' => 'Total Volunteer Hours Fetched Successfully',
                'data' => $res
            ];
            header('HTTP/1.0 200 OK');
            return json_encode($data);
        } else {
            $data = [
                'status' => 404,
                'message' => 'No Volunteer Hours Found',
            ];
            header('HTTP/1.0 404 Not Found');
            return json_encode($data);
        }
    } else {
        $data = [
            'status' => 500,
            'message' => 'Internal Server Error',
        ];
        header('HTTP/1.0 500 Internal Server Error');
        return json_encode($data);
    }
}



function insertNewAdmin($userInput)
{
    global $con;


    $admin_id = 'ADMIN - ' . date('Y-d') . substr(uniqid(), -5);
    $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
    $email = mysqli_real_escape_string($con, $userInput['email']);
    $password = mysqli_real_escape_string($con, $userInput['password']);
    $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);

    $hashingo = md5($password);

    if (empty(trim($last_name))) {
        return error422('Enter last name');
    } elseif (empty(trim($first_name))) {
        return error422('Enter first name');
    } elseif (empty(trim($middle_name))) {
        return error422('Enter middle name');
    } elseif (empty(trim($email))) {
        return error422('Enter email');
    } elseif (empty(trim($password))) {
        return error422('Enter password');
    } elseif (empty(trim($contact_info))) {
        return error422('Enter contact info');
    } else {

        $query = "INSERT INTO 
        admin_acc_tbl (
            admin_id, 
            last_name, 
            first_name, 
            middle_name, 
            email, 
            password, 
            contact_info) 
        VALUES (
            '$admin_id', 
            '$last_name', 
            '$first_name', 
            '$middle_name', 
            '$email', 
            '$hashingo', 
            '$contact_info')";

        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 201,
                'message' => 'Admin Inserted Successfully',
            ];
            header("HTTP/1.0 201 OK");
            return json_encode($data);
        } else {
            $data = [
                "status" => 500,
                "message" => "Internal Server Error",
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}



function getAdminList()
{
    global $con;

    $query = "SELECT * FROM admin_acc_tbl";
    $result = mysqli_query($con, $query);

    if ($result) {
        if (mysqli_num_rows($result) > 0) {
            $res = mysqli_fetch_all($result, MYSQLI_ASSOC);

            $data = [
                "status" => 201,
                "message" => "Admin List Fetched Successfully",
                "data" => $res
            ];
            header("HTTP/1.0 200 OK");
            return json_encode($data);
        } else {
            $data = [
                "status" => 404,
                "message" => "No Admin Found",
            ];
            header("HTTP/1.0 404 Not Found");
            return json_encode($data);
        }
    } else {
        $data = [
            "status" => 500,
            "message" => "Internal Server Error",
        ];
        header("HTTP/1.0 500 Internal Server Error");
        return json_encode($data);
    }
}


function getSingleAdmin($userInput)
{
    global $con;

    $admin_id = mysqli_real_escape_string($con, $userInput['admin_id']);

    if (empty(trim($admin_id))) {
        return error422('Enter admin id');
    } else {
        $query = "SELECT * FROM admin_acc_tbl WHERE admin_id = '$admin_id' LIMIT 1";
        $result = mysqli_query($con, $query);

        if ($result) {
            if (mysqli_num_rows($result) == 1) {
                $res = mysqli_fetch_assoc($result);

                $data = [
                    'status' => 200,
                    'message' => 'Admin Fetched Successfully',
                    'data' => $res
                ];
                header('HTTP/1.0 200 OK');
                return json_encode($data);
            } else {
                $data = [
                    'status' => 404,
                    'message' => 'No Admin Found',
                ];
            }
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header('HTTP/1.0 500 Internal Server Error');
            return json_encode($data);
        }
    }
}

function updateAdmin($userInput)
{
    global $con;


    $admin_id = mysqli_real_escape_string($con, $userInput['admin_id']);
    $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
    $email = mysqli_real_escape_string($con, $userInput['email']);
    $password = mysqli_real_escape_string($con, $userInput['password']);
    $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);

    if (empty(trim($admin_id))) {
        return error422('Enter admin id');
    } elseif (empty(trim($last_name))) {
        return error422('Enter last name');
    } elseif (empty(trim($first_name))) {
        return error422('Enter first name');
    } elseif (empty(trim($middle_name))) {
        return error422('Enter middle name');
    } elseif (empty(trim($email))) {
        return error422('Enter email');
    } elseif (empty(trim($password))) {
        return error422('Enter password');
    } elseif (empty(trim($contact_info))) {
        return error422('Enter contact info');
    } else {
        $query = "UPDATE admin_acc_tbl SET
            last_name='$last_name', 
            first_name='$first_name',  
            middle_name='$middle_name', 
            email='$email', 
            password='$password', 
            contact_info='$contact_info' 
        WHERE admin_acc_tbl.admin_id = '$admin_id'";

        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 200,
                'message' => 'Admin Updated Successfully',
            ];
            header('HTTP/1.0 200 OK');
            return json_encode($data);
        } else {
            $data = [
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header('HTTP/1.0 500 Internal Server Error');
            return json_encode($data);
        }
    }
}
