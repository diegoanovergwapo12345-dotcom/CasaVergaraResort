<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$qr = ['path' => null, 'label' => '', 'url' => null];

try {
    $qr = get_payment_qr(get_db_connection());
} catch (\Throwable $e) {
    error_log('payment-qr.php: ' . $e->getMessage());
}

json_response(true, 'OK', [
    'paymentQr' => [
        'url'        => $qr['url'],
        'label'      => $qr['label'],
        'configured' => $qr['url'] !== null,
    ],
]);
