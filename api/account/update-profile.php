<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();

$body = get_json_body();
$firstName = input_str($body, 'firstName');
$lastName  = input_str($body, 'lastName');
$phone     = input_str($body, 'phone');

$errors = [];
if ($firstName === '') $errors[] = 'First name is required.';
if ($lastName === '')  $errors[] = 'Last name is required.';
if ($phone === '' || !is_valid_phone($phone)) $errors[] = 'A valid contact number is required.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();
$userId = (int) $_SESSION['user_id'];

if (is_full_name_taken($pdo, $firstName, $lastName, (string) ($_SESSION['email'] ?? ''))) {
    json_response(false, 'An account already exists under this first and last name. Please choose a different name, or contact us if this is a mistake.', ['nameTaken' => true], 409);
}

$update = $pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?');
$update->execute([$firstName, $lastName, $phone, $userId]);

$_SESSION['first_name'] = $firstName;
$_SESSION['last_name']  = $lastName;
$_SESSION['phone']      = $phone;

json_response(true, 'Your profile has been updated.', [
    'user' => [
        'id' => $userId,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'phone' => $phone,
        'email' => $_SESSION['email'],
        'role' => $_SESSION['role'],
        'avatarUrl' => !empty($_SESSION['avatar_path']) ? SITE_URL . '/' . $_SESSION['avatar_path'] : null,
    ],
]);
