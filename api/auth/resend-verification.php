<?php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body = get_json_body();
$email = strtolower(input_str($body, 'email'));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'A valid email address is required.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, first_name, is_verified FROM users WHERE email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();

$genericMessage = 'If an unverified account exists for that email, a new 6-digit code has been sent.';

if (!$user || (int) $user['is_verified'] === 1) {
    json_response(true, $genericMessage);
}

$token = generate_verification_code();
$expires = date('Y-m-d H:i:s', strtotime('+' . VERIFICATION_CODE_TTL_MINUTES . ' minutes'));

$update = $pdo->prepare('UPDATE users SET verification_token=?, verification_expires=? WHERE id=?');
$update->execute([$token, $expires, $user['id']]);

$bodyHtml = '
    <p>Hi ' . htmlspecialchars($user['first_name']) . ',</p>
    <p>Here is your new verification code:</p>
    <p style="text-align:center; margin:28px 0;">
        <span style="display:inline-block; background:#f4f6f9; color:#0c3b5d; font-size:28px; font-weight:700; letter-spacing:8px; padding:14px 24px; border-radius:8px;">' . $token . '</span>
    </p>
    <p style="font-size:13px; color:#666;">This code expires in ' . VERIFICATION_CODE_TTL_MINUTES . ' minutes.</p>
';
send_app_email($email, $user['first_name'], 'Your new Casa Vergara verification code', build_email_html('Verify your account', $bodyHtml));

json_response(true, $genericMessage);
