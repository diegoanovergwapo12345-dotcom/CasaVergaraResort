<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'A valid email address is required.', [], 422);
}

$genericMessage = "If an account exists for that email, we've sent a 6-digit code to it.";

$pdo = get_db_connection();
$otpLockoutAvailable = cvr_reset_otp_columns_available($pdo);
$columns = $otpLockoutAvailable
    ? 'id, first_name, status, reset_code_attempts, reset_block_until'
    : 'id, first_name, status';
$stmt = $pdo->prepare('SELECT ' . $columns . ' FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || $user['status'] === 'suspended') {
    json_response(true, $genericMessage);
}

$secondsLeft = $otpLockoutAvailable ? cvr_reset_block_seconds_left($user) : 0;
if ($secondsLeft > 0) {
    json_response(false,
        'For security, this account is temporarily blocked from receiving password reset codes because too many incorrect codes were entered. Please try again in ' . cvr_format_wait($secondsLeft) . '.',
        ['otpBlocked' => true, 'retrySeconds' => $secondsLeft],
        429
    );
}

$code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+' . RESET_CODE_TTL_MINUTES . ' minutes'));

$update = $pdo->prepare('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?');
$update->execute([$code, $expires, $user['id']]);

$bodyHtml = '
    <p>Hi ' . htmlspecialchars($user['first_name']) . ',</p>
    <p>We received a request to reset your Casa Vergara account password. Use the code below to continue:</p>
    <p style="text-align:center; margin:28px 0;">
        <span style="display:inline-block; background:#f4f6f9; color:#0c3b5d; font-size:28px; font-weight:700; letter-spacing:8px; padding:14px 24px; border-radius:8px;">' . $code . '</span>
    </p>
    <p style="font-size:13px; color:#666;">This code expires in ' . RESET_CODE_TTL_MINUTES . ' minutes.</p>
    <p style="font-size:13px; color:#666;">If you didn\'t request this, you can safely ignore this email.</p>
';

$mailError = null;
$sent = send_app_email($email, $user['first_name'], 'Your Casa Vergara password reset code', build_email_html('Reset your password', $bodyHtml), $mailError);

if (!$sent) {
    json_response(false, 'We could not send the reset code right now. Please try again in a moment.', ['mailError' => $mailError], 500);
}

json_response(true, $genericMessage);
