<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$phone = input_str($body, 'phone');
$email = strtolower(input_str($body, 'email'));

if ($phone === '' || !is_valid_phone($phone)) {
    json_response(false, 'Please enter a valid contact number.', ['available' => false], 200);
}

$pdo = get_db_connection();
$taken = count_accounts_with_phone($pdo, $phone, $email) >= MAX_ACCOUNTS_PER_PHONE;

if ($taken) {
    json_response(false, 'This phone number is already in use. Please try a different phone number.', ['available' => false], 200);
}

json_response(true, 'Phone number looks good.', ['available' => true], 200);
