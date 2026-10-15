<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));
$code  = input_str($body, 'code');
$newPassword     = (string) ($body['newPassword'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (!preg_match('/^\d{6}$/', $code)) $errors[] = 'A valid 6-digit code is required.';
if (strlen($newPassword) < 6) $errors[] = 'New password must be at least 6 characters.';
if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();
$otpLockoutAvailable = cvr_reset_otp_columns_available($pdo);
$columns = $otpLockoutAvailable
    ? 'id, reset_token, reset_expires, reset_code_attempts, reset_block_until'
    : 'id, reset_token, reset_expires';
$stmt = $pdo->prepare('SELECT ' . $columns . ' FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

$secondsLeft = ($otpLockoutAvailable && $user) ? cvr_reset_block_seconds_left($user) : 0;
if ($secondsLeft > 0) {
    json_response(false,
        'Too many incorrect codes were entered for this account. For security, password reset is locked for another ' . cvr_format_wait($secondsLeft) . '.',
        ['otpBlocked' => true, 'retrySeconds' => $secondsLeft],
        429
    );
}

if (!$user || $user['reset_token'] === null || !hash_equals((string) $user['reset_token'], $code)) {
    if ($user) {
        $result = cvr_register_wrong_reset_code($pdo, $user);
        if ($result['blocked']) {
            json_response(false,
                'That code is incorrect. After ' . RESET_OTP_MAX_ATTEMPTS . ' wrong codes in a row, this account cannot receive or use a password reset code for the next ' . RESET_OTP_BLOCK_HOURS . ' hours.',
                ['otpBlocked' => true, 'retrySeconds' => $result['secondsLeft']],
                429
            );
        }
        json_response(false,
            'That code is incorrect. You have ' . $result['attemptsLeft'] . ' attempt' . ($result['attemptsLeft'] === 1 ? '' : 's') . ' left before password reset is locked for ' . RESET_OTP_BLOCK_HOURS . ' hours.',
            ['attemptsLeft' => $result['attemptsLeft']],
            401
        );
    }
    json_response(false, 'That code is incorrect. Please check your email and try again.', [], 401);
}

if (!$user['reset_expires'] || strtotime($user['reset_expires']) < time()) {
    json_response(false, 'That code has expired. Please request a new one.', [], 401);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$resetSql = $otpLockoutAvailable
    ? 'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL, reset_code_attempts = 0, reset_block_until = NULL WHERE id = ?'
    : 'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?';
$update = $pdo->prepare($resetSql);
$update->execute([$newHash, $user['id']]);

json_response(true, 'Your password has been reset. You can now sign in with your new password.');
