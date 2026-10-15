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
                'That code is incorrect. You have entered ' . RESET_OTP_MAX_ATTEMPTS . ' wrong codes in a row, so for security this account cannot receive or use a password reset code for the next ' . RESET_OTP_BLOCK_HOURS . ' hours.',
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

cvr_clear_reset_code_attempts($pdo, $user);

json_response(true, 'Code verified. You can now set a new password.');
