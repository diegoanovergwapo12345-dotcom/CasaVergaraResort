<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body      = get_json_body();
$firstName = input_str($body, 'firstName');
$lastName  = input_str($body, 'lastName');
$email     = strtolower(input_str($body, 'email'));

if ($firstName === '' || $lastName === '') {
    json_response(false, 'Enter both a first and last name.', ['available' => false], 200);
}

$pdo = get_db_connection();
$taken = is_full_name_taken($pdo, $firstName, $lastName, $email);

if ($taken) {
    json_response(false, 'An account already exists under this first and last name.', ['available' => false], 200);
}

json_response(true, 'Name looks good.', ['available' => true], 200);
