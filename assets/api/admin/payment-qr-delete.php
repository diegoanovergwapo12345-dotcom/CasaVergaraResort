<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

try {
    $previous = $pdo->query('SELECT payment_qr_path FROM resort_settings WHERE id = 1')->fetchColumn();
    $pdo->exec("UPDATE resort_settings SET payment_qr_path = NULL, payment_qr_label = '' WHERE id = 1");
} catch (\Throwable $e) {
    error_log('payment-qr-delete.php: ' . $e->getMessage());
    json_response(false, 'Could not remove the QR code. Please run database/migration_payment_qr.sql and try again.', [], 500);
}

if ($previous) {
    $previousAbsolute = __DIR__ . '/../../' . $previous;
    if (is_file($previousAbsolute)) {
        @unlink($previousAbsolute);
    }
}

json_response(true, 'Payment QR code removed.', ['paymentQr' => ['path' => null, 'url' => null, 'label' => '']]);
