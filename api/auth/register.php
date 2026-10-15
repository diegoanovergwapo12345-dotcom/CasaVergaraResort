<?php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body = get_json_body();

$firstName = input_str($body, 'firstName');
$lastName  = input_str($body, 'lastName');
$email     = strtolower(input_str($body, 'email'));
$phone     = input_str($body, 'phone');
$password  = (string) ($body['password'] ?? '');
$passwordConfirm = (string) ($body['passwordConfirm'] ?? '');
$agreedTerms = !empty($body['terms']);

$errors = [];
if ($firstName === '') $errors[] = 'First name is required.';
if ($lastName === '')  $errors[] = 'Last name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($phone === '' || !is_valid_phone($phone)) $errors[] = 'A valid contact number is required.';
if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
if ($password !== $passwordConfirm) $errors[] = 'Passwords do not match.';
if (!$agreedTerms) $errors[] = 'You must agree to the Terms & Conditions.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$deliverReason = null;
$deliverSuggestion = null;
if (!validate_email_deliverability($email, $deliverReason, $deliverSuggestion)) {
    json_response(false, $deliverReason, ['suggestion' => $deliverSuggestion], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT id, is_verified FROM users WHERE email = ?');
$stmt->execute([$email]);
$existing = $stmt->fetch();

if ($existing) {
    if ((int) $existing['is_verified'] === 1) {
        json_response(false, 'An account with this email already exists. Please sign in instead.', [], 409);
    }
}

if (count_accounts_with_phone($pdo, $phone, $email) >= MAX_ACCOUNTS_PER_PHONE) {
    json_response(false, 'This phone number is already in use. Please try a different phone number.', ['phoneTaken' => true], 409);
}

if (is_full_name_taken($pdo, $firstName, $lastName, $email)) {
    json_response(false, 'An account already exists under this first and last name. Please sign in, or contact us if this is a mistake.', ['nameTaken' => true], 409);
}

if ($existing) {

    $userId = $existing['id'];
    $token = generate_verification_code();
    $expires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_TTL_MINUTES . ' minutes'));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $update = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, phone=?, password_hash=?, verification_token=?, verification_expires=? WHERE id=?');
    $update->execute([$firstName, $lastName, $phone, $passwordHash, $token, $expires, $userId]);
} else {
    $token = generate_verification_code();
    $expires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_TTL_MINUTES . ' minutes'));
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $pdo->prepare('INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_verified, verification_token, verification_expires) VALUES (?, ?, ?, ?, ?, "customer", 0, ?, ?)');
    $insert->execute([$firstName, $lastName, $email, $phone, $passwordHash, $token, $expires]);
    $userId = $pdo->lastInsertId();
}

$bodyHtml = '
    <p>Hi ' . htmlspecialchars($firstName) . ',</p>
    <p>Thanks for creating a guest account with Casa Vergara. Enter the code below on the registration page to activate your account:</p>
    <p style="text-align:center; margin:28px 0;">
        <span style="display:inline-block; background:#f4f6f9; color:#0c3b5d; font-size:28px; font-weight:700; letter-spacing:8px; padding:14px 24px; border-radius:8px;">' . $token . '</span>
    </p>
    <p style="font-size:13px; color:#666;">This code expires in ' . VERIFICATION_CODE_TTL_MINUTES . ' minutes.</p>
    <p style="font-size:13px; color:#666;">If you didn\'t create this account, you can safely ignore this email.</p>
';

$mailError = null;
$sent = send_app_email($email, $firstName, 'Your Casa Vergara verification code', build_email_html('Verify your account', $bodyHtml), $mailError);

if (!$sent) {
    json_response(true, 'Account created, but we could not send the verification code right now. Please use "Resend code" to try again.', [
        'userId' => $userId,
        'email' => $email,
        'needsVerification' => true,
        'mailError' => $mailError,
    ], 201);
}

json_response(true, 'We sent a 6-digit code to ' . $email . '. Enter it below to verify your account.', [
    'userId' => $userId,
    'email' => $email,
    'needsVerification' => true,
], 201);
