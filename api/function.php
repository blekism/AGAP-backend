<?php

require __DIR__ . '/../inc/dbcon.php';

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
function insertDonation($userInput, $userParams)
{
    global $con;

    mysqli_begin_transaction($con);

    $donor_id = mysqli_real_escape_string($con, $userParams['donor_id']);
    $donation_id = 'DONATE' . date('Y-d') . '-' . uniqid();
    $recipient_id = mysqli_real_escape_string($con, $userInput['recipient_id']);
    $event_id = mysqli_real_escape_string($con, $userInput['event_id']);
    $itemLoop = $userInput['items'];

    if (empty(trim($recipient_id))) {
        return error422('Enter valid recipient');
    } elseif (empty(trim($event_id))) {
        return error422('Enter valid event');
    } elseif (empty($itemLoop)) {
        return error422('Enter valid items');
    } else {
        $query = "INSERT INTO donation_tbl(donation_id, donor_id, recipient_id, event_id) VALUES('$donation_id', '$donor_id', '$recipient_id', '$event_id')";
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
    global $con;

    $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
    $email = mysqli_real_escape_string($con, $userInput['email']);
    $password = mysqli_real_escape_string($con, $userInput['password']);
    $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);
    $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
    $section = mysqli_real_escape_string($con, $userInput['section']);
    $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);

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
        $query = "INSERT INTO 
            volunteer_signup(
                last_name, 
                first_name, 
                middle_name, 
                email, 
                password, 
                contact_info, 
                dept_category_id, 
                section, 
                designation_id,) 
            VALUES(
                '$last_name', 
                '$first_name', 
                '$middle_name', 
                '$email', 
                '$hashing', 
                '$contact_info', 
                '$dept_category_id', 
                '$section', 
                '$designation_id')";
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
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT VOLUNTEER END

//INSERT VOLUNTEER START
function signDonor($userInput)
{
    global $con;

    $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
    $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
    $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
    $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
    $email = mysqli_real_escape_string($con, $userInput['email']);
    $password = mysqli_real_escape_string($con, $userInput['password']);
    $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);


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
    } else {
        $query = "INSERT INTO 
             donor_signup(
                last_name, 
                first_name, 
                middle_name, 
                dept_category_id
                email, 
                password, 
                contact_info, 
                ) 
            VALUES(
                '$last_name', 
                '$first_name', 
                '$middle_name',
                '$dept_category_id'
                '$email', 
                '$hashing', 
                '$contact_info')";
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
                'status' => 500,
                'message' => 'Internal Server Error',
            ];
            header("HTTP/1.0 500 Internal Server Error");
            return json_encode($data);
        }
    }
}
//INSERT VOLUNTEER END

//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT START
function insDelVolunAcc($userParams)
{
    global $con;


    $singup_id = mysqli_real_escape_string($con, $userParams['signup_id']);

    if (empty(trim($singup_id))) {
        return error422('No VolunteerID found in url');
    } else {
        $query1 = "SELECT * FROM volunteer_signup WHERE signup_id = '$singup_id' ";
        $result1 = mysqli_query($con, $query1);
        $volunteerData = mysqli_fetch_assoc($result1);

        if (!$volunteerData) {
            $data = [
                'status' => 404,
                'message' => 'Volunteer not found',
            ];
            header("HTTP/1.0 404 Not FOund");
            return json_encode($data);
        } else {
            $volunteer_idtemp = mysqli_real_escape_string($con, $volunteerData['signup_id']);
            $last_name = mysqli_real_escape_string($con, $volunteerData['last_name']);
            $first_name = mysqli_real_escape_string($con, $volunteerData['first_name']);
            $middle_name = mysqli_real_escape_string($con, $volunteerData['middle_name']);
            $dept_category_id = mysqli_real_escape_string($con, $volunteerData['dept_category_id']);
            $email = mysqli_real_escape_string($con, $volunteerData['email']);
            $password = mysqli_real_escape_string($con, $volunteerData['password']);
            $contact_info = mysqli_real_escape_string($con, $volunteerData['contact_info']);
            $section = mysqli_real_escape_string($con, $volunteerData['section']);
            $designation_id = mysqli_real_escape_string($con, $volunteerData['designation_id']);

            $volunteer_id = 'VOLUN' . date('Y-d') . ' - ' . $volunteer_idtemp;

            $query2 = "INSERT INTO 
            volunteer_acc_tbl(
                volunteer_id, 
                last_name, 
                first_name, 
                middle_name, 
                email, 
                password, 
                contact_info, 
                dept_category_id, 
                section, 
                designnation_id) 
            VALUES(
                '$volunteer_id', 
                '$last_name', 
                '$first_name', 
                '$middle_name', 
                '$dept_category_id', 
                '$email', 
                '$password', 
                '$contact_info', 
                '$section', 
                '$designation_id')";
            $result2 = mysqli_query($con, $query2);

            if ($result2) {
                $query3 = "DELETE FROM volunteer_signup WHERE signup_id = $singup_id";
                $result3 = mysqli_query($con, $query3);

                if ($result3) {
                    $data = [
                        'status' => 201,
                        'message' => 'User moved to accounts from signup successfully',
                    ];
                    header("HTTP/1.0 201 Moved");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 500,
                        'message' => 'Failed to delte user from signup table'
                    ];
                    header("HTTP/1.0 500 Internal Server Error");
                    return json_encode($data);
                }
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Failed to delte user from signup table'
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
    }
}
//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT END

//INSERT & DELETE VOLUNTEER SIGNUP TO ACCOUNT START
function insDelDonorAcc($userParams)
{
    global $con;


    $singup_id = mysqli_real_escape_string($con, $userParams['signup_id']);

    if (empty(trim($singup_id))) {
        return error422('No VolunteerID found in url');
    } else {
        $query1 = "SELECT * FROM donor_signup WHERE signup_id = '$singup_id' ";
        $result1 = mysqli_query($con, $query1);
        $donorData = mysqli_fetch_assoc($result1);

        if (!$donorData) {
            $data = [
                'status' => 404,
                'message' => 'Volunteer not found',
            ];
            header("HTTP/1.0 404 Not FOund");
            return json_encode($data);
        } else {
            $donor_idtemp = mysqli_real_escape_string($con, $donorData['signup_id']);
            $last_name = mysqli_real_escape_string($con, $donorData['last_name']);
            $first_name = mysqli_real_escape_string($con, $donorData['first_name']);
            $middle_name = mysqli_real_escape_string($con, $donorData['middle_name']);
            $dept_category_id = mysqli_real_escape_string($con, $donorData['dept_category_id']);
            $email = mysqli_real_escape_string($con, $donorData['email']);
            $password = mysqli_real_escape_string($con, $donorData['password']);
            $contact_info = mysqli_real_escape_string($con, $donorData['contact_info']);

            $donor_id = 'DONOR' . date('Y-d') . ' - ' . $donor_idtemp;


            $query2 = "INSERT INTO 
            donors_acc_tbl(
                volunteer_id, 
                last_name, 
                first_name, 
                middle_name, 
                email, 
                password, 
                contact_info, 
                dept_category_id, 
                section, 
                designnation_id) 
            VALUES(
                '$donor_id', 
                '$last_name', 
                '$first_name', 
                '$middle_name', 
                '$dept_category_id', 
                '$email', 
                '$password', 
                '$contact_info')";
            $result2 = mysqli_query($con, $query2);

            if ($result2) {
                $query3 = "DELETE FROM donor_signup WHERE signup_id = $singup_id";
                $result3 = mysqli_query($con, $query3);

                if ($result3) {
                    $data = [
                        'status' => 201,
                        'message' => 'User moved to accounts from signup successfully',
                    ];
                    header("HTTP/1.0 201 Moved");
                    return json_encode($data);
                } else {
                    $data = [
                        'status' => 500,
                        'message' => 'Failed to delte user from signup table'
                    ];
                    header("HTTP/1.0 500 Internal Server Error");
                    return json_encode($data);
                }
            } else {
                $data = [
                    'status' => 500,
                    'message' => 'Failed to delte user from signup table'
                ];
                header("HTTP/1.0 500 Internal Server Error");
                return json_encode($data);
            }
        }
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
function deleteDonor($userParams)
{
    global $con;

    $donor_id = mysqli_real_escape_string($con, $userParams);

    if (empty(trim($donor_id))) {
        return error422('Enter valid donor ID');
    } else {
        $query = "UPDATE donors_acc_tbl 
            SET 
                last_name = 'Deleted', 
                first_name = 'Deleted', 
                middle_name = 'Deleted', 
                dept_category_id = 5, 
                email = 'Deleted', 
                password = 'Deleted', 
                contact_info = 'Deleted', 
                acc_status_id = 2 
            WHERE 
                donor_id = '$donor_id'";
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

//DELETE VOLUNTEER START
function deleteVolunteer($userParams)
{
    global $con;

    $volunteer_id = mysqli_real_escape_string($con, $userParams);

    if (empty(trim($volunteer_id))) {
        return error422('Enter valid volunteer ID');
    } else {
        $query = "UPDATE 
            volunteer_acc_tbl 
        SET 
            last_name = 'Deleted', 
            first_name = 'Deleted',
            middle_name = 'Deleted',
            email = 'Deleted',
            password = 'Deleted',
            contact_info = 'Deleted',
            total_hours = 0,
            dept_category_id = 'Deleted',
            section = 'Deleted',
            designation_id = 'Deleted',
            acc_status_id = 2
        WHERE volunteer_id = '$volunteer_id'";
        $result = mysqli_query($con, $query);

        if ($result) {
            $data = [
                'status' => 200,
                'message' => 'Volunteer deleted successfully',
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
//DELETE VOLUNTEER END

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
                        'status' => 404,
                        'message' => 'More Donation of id ' . $donation_id . ' found',
                    ];
                    header("HTTP/1.0 404 Not Found");
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
function updateDonorAcc($userParams, $userInput)
{
    global $con;

    if (!isset($userParams['donor_id'])) {
        return error422('Donor ID not found in URL');
    } elseif ($userParams['donor_id'] == null) {
        return error422('Donor ID is null');
    } else {
        $donor_id = mysqli_real_escape_string($con, $userParams['donor_id']);

        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);

        if (empty(trim($last_name))) {
            return error422('Enter valid last name');
        } elseif (empty(trim($first_name))) {
            return error422('Enter valid first name');
        } elseif (empty(trim($middle_name))) {
            return error422('Enter valid middle name');
        } elseif (empty(trim($dept_category_id))) {
            return error422('Enter valid department category');
        } elseif (empty(trim($email))) {
            return error422('Enter valid email');
        } elseif (empty(trim($password))) {
            return error422('Enter valid password');
        } elseif (empty(trim($contact_info))) {
            return error422('Enter valid contact information');
        } else {
            $query = "UPDATE donors_acc_tbl 
                SET 
                    last_name = '$last_name', 
                    first_name = '$first_name', 
                    middle_name = '$middle_name', 
                    dept_category_id = '$dept_category_id', 
                    email = '$email', 
                    password = '$password', 
                    contact_info = '$contact_info' 
                WHERE 
                    donor_id = '$donor_id'";
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

// UPDATE VOLUNTEER ACC START
function updateVolunteerAcc($userParams, $userInput)
{
    global $con;

    if (!isset($userParams['volunteer_id'])) {
        return error422('Volunteer ID not found in URL');
    } elseif ($userParams['volunteer_id'] == null) {
        return error422('Volunteer ID is null');
    } else {
        $volunteer_id = mysqli_real_escape_string($con, $userParams['volunteer_id']);

        $last_name = mysqli_real_escape_string($con, $userInput['last_name']);
        $first_name = mysqli_real_escape_string($con, $userInput['first_name']);
        $middle_name = mysqli_real_escape_string($con, $userInput['middle_name']);
        $email = mysqli_real_escape_string($con, $userInput['email']);
        $password = mysqli_real_escape_string($con, $userInput['password']);
        $contact_info = mysqli_real_escape_string($con, $userInput['contact_info']);
        $dept_category_id = mysqli_real_escape_string($con, $userInput['dept_category_id']);
        $section = mysqli_real_escape_string($con, $userInput['section']);
        $designation_id = mysqli_real_escape_string($con, $userInput['designation_id']);

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
            return error422('Enter valid department category');
        } elseif (empty(trim($section))) {
            return error422('Enter valid section');
        } elseif (empty(trim($designation_id))) {
            return error422('Enter valid designation');
        } else {
            $query = "UPDATE volunteer_acc_tbl 
                SET 
                    last_name = '$last_name', 
                    first_name = '$first_name', 
                    middle_name = '$middle_name', 
                    email = '$email', 
                    password = '$password', 
                    contact_info = '$contact_info', 
                    dept_category_id = '$dept_category_id', 
                    section = '$section', 
                    designation_id = '$designation_id' 
                WHERE 
                    volunteer_id = '$volunteer_id'";
            $result = mysqli_query($con, $query);

            if ($result) {
                $data = [
                    'status' => 200,
                    'message' => 'Volunteer updated successfully',
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
// UPDATE VOLUNTEER ACC END

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

    if (!isset($userParams['volunteer_id'])) {
        return error422('Volunteer ID not found in URL');
    } elseif ($userParams['volunteer_id'] == null) {
        return error422('Volunteer ID is null');
    } else {
        $volunteer_id = mysqli_real_escape_string($con, $userParams['volunteer_id']);

        $query = "SELECT 
            volunteer_acc_tbl.last_name,
            volunteer_acc_tbl.first_name,
            volunteer_acc_tbl.middle_name,
            volunteer_acc_tbl.email,
            volunteer_acc_tbl.password,
            volunteer_acc_tbl.contact_info,
            volunteer_acc_tbl.total_hours,
            dept_category_tbl.category_name,
            volunteer_acc_tbl.section,
            designation_category_tbl.designation_name
        FROM
            volunteer_acc_tbl
        INNER JOIN dept_category_tbl ON volunteer_acc_tbl.dept_category_id = dept_category_tbl.dept_category_id
        INNER JOIN designation_category_tbl ON volunteer_acc_tbl.designation_id = designation_category_tbl.designation_id 
        WHERE 
            volunteer_acc_tbl.volunteer_id = '$volunteer_id'";
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

    if (!isset($userParams['donor_id'])) {
        return error422('Donor ID not found in URL');
    } elseif ($userParams['donor_id'] == null) {
        return error422('Donor ID is null');
    } else {
        $donor_id = mysqli_real_escape_string($con, $userParams['donor_id']);

        $query = "SELECT 
            donors_acc_tbl.last_name,
            donors_acc_tbl.first_name,
            donors_acc_tbl.middle_name,
            dept_category_tbl.category_name,
            donors_acc_tbl.email,
            donors_acc_tbl.password,
            donors_acc_tbl.contact_info
        FROM
            donors_acc_tbl
        INNER JOIN dept_category_tbl ON donors_acc_tbl.dept_category_id = dept_category_tbl.dept_category_id
        WHERE 
            donors_acc_tbl.donor_id = '$donor_id'";
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
        $volunteer_id = mysqli_real_escape_string($con, $userParams['volunteer_id']);

        $query = "SELECT 
            phase2_tbl.log_id, 
            event_tbl.event_name,
            volunteer_acc_tbl.last_name,
            volunteer_acc_tbl.first_name,
            phase2_tbl.activity,
            phase2_tbl.time_in,
            phase2_tbl.time_out,
            phase2_tbl.signature,
            phase2_tbl.date
        FROM
            phase2_tbl
        INNER JOIN event_tbl ON phase2_tbl.event_id = event_tbl.evenet_id
        INNER JOIN volunteer_acc_tbl ON phase2_tbl.volunteer_id = volunteer_acc_tbl.volunteer_id
        WHERE 
            phase2_tbl.volunteer_id = '$volunteer_id'";
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

    if (!isset($userParams['volunteer_id'])) {
        return error422('Volunteer ID not found in URL');
    } elseif ($userParams['volunteer_id'] == null) {
        return error422('Volunteer ID is null');
    } else {
        $volunteer_id = mysqli_real_escape_string($con, $userParams['volunteer_id']);

        $query = "SELECT 
            phase3_tbl.log_id, 
            event_tbl.event_name,
            volunteer_acc_tbl.last_name,
            volunteer_acc_tbl.first_name,
            phase3_tbl.time_in,
            phase3_tbl.time_out,
            phase3_tbl.signature,
            phase3_tbl.date
        FROM
            phase3_tbl
        INNER JOIN event_tbl ON phase3_tbl.event_id = event_tbl.evenet_id
        INNER JOIN volunteer_acc_tbl ON phase3_tbl.volunteer_id = volunteer_acc_tbl.volunteer_id
        WHERE 
            phase3_tbl.volunteer_id = '$volunteer_id'";
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
function updateDonationAccept($userParams)
{
    global $con;

    if (!isset($userParams['volunteer_id']) || !isset($userParams['donation_id'])) {
        return error422('Volunteer ID is missing or Donation ID is missing');
    } elseif ($userParams['volunteer_id'] == null || $userParams['donation_id'] == null) {
        return error422('Volunteer ID is null or Donation ID is null');
    } else {
        $volunteer_id = mysqli_real_escape_string($con, $userParams['volunteer_id']);
        $donation_id = mysqli_real_escape_string($con, $userParams['donation_id']);

        $query = "UPDATE donation_tbl SET status_id = 3001, received_by = '$volunteer_id' WHERE donation_id = '$donation_id'";
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
