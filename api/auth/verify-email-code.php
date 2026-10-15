<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));
$code  = input_str($body, 'code');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'A valid email address is required.', [], 422);
}
if (!preg_match('/^\d{6}$/', $code)) {
    json_response(false, 'Enter the 6-digit code from your email.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, is_verified, verification_token, verification_expires, first_name, last_name, email FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    json_response(false, 'We could not find an account for that email.', [], 404);
}

if ((int) $user['is_verified'] === 1) {
    json_response(true, 'Your email is already verified. You can sign in now.', ['alreadyVerified' => true]);
}

if ($user['verification_token'] === null || !hash_equals((string) $user['verification_token'], $code)) {
    json_response(false, 'That code is incorrect. Please check your email and try again.', [], 401);
}

if (!$user['verification_expires'] || strtotime($user['verification_expires']) < time()) {
    json_response(false, 'That code has expired. Please request a new one.', [], 401);
}

$update = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
$update->execute([$user['id']]);

try {
    $guestName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['email'];
    $pdo->prepare(
        "INSERT INTO notifications (user_id, audience, title, message, link)
         SELECT id, 'staff', ?, ?, ? FROM users WHERE role IN ('admin', 'event_manager')"
    )->execute([
        'New guest account',
        $guestName . ' (' . $user['email'] . ') just verified their account.',
        'users.html',
    ]);
} catch (\Throwable $e) {
    error_log('verify-email-code.php: failed to create admin/staff notification: ' . $e->getMessage());
}

json_response(true, 'Email verified! You can now sign in.');
