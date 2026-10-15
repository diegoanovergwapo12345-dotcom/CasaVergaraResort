<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$resortName = input_str($body, 'resortName');
$email = input_str($body, 'email');
$phone = input_str($body, 'phone');
$address = input_str($body, 'address');

$hasQrLabel = array_key_exists('paymentQrLabel', $body);
$paymentQrLabel = input_str($body, 'paymentQrLabel');

$errors = [];
if ($resortName === '') $errors[] = 'Resort name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($phone === '') $errors[] = 'Phone number is required.';
if ($address === '') $errors[] = 'Address is required.';
if (mb_strlen($paymentQrLabel) > 150) $errors[] = 'The payment QR caption must be 150 characters or fewer.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare(
    'INSERT INTO resort_settings (id, resort_name, email, phone, address) VALUES (1, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE resort_name = VALUES(resort_name), email = VALUES(email), phone = VALUES(phone), address = VALUES(address)'
);
$stmt->execute([$resortName, $email, $phone, $address]);

if ($hasQrLabel) {
    try {
        $pdo->prepare('UPDATE resort_settings SET payment_qr_label = ? WHERE id = 1')->execute([$paymentQrLabel]);
    } catch (\Throwable $e) {
        error_log('settings-save.php: could not save payment_qr_label (run database/migration_payment_qr.sql): ' . $e->getMessage());
        json_response(false, 'Resort info saved, but the QR caption could not be saved. Please run database/migration_payment_qr.sql.', [], 500);
    }
}

json_response(true, 'Resort information saved.', [
    'settings' => [
        'resortName' => $resortName,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
    ],
]);
