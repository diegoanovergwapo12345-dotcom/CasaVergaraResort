<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

$row = $pdo->query('SELECT resort_name, email, phone, address, updated_at FROM resort_settings WHERE id = 1')->fetch();

if (!$row) {

    $pdo->exec("INSERT INTO resort_settings (id, resort_name, email, phone, address) VALUES (1, 'Casa Vergara Resort', 'info@casavergara.com', '0970 323 6544', 'Purok 3, Barangay Langkiwa, Biñan, Laguna')");
    $row = $pdo->query('SELECT resort_name, email, phone, address, updated_at FROM resort_settings WHERE id = 1')->fetch();
}

$qr = get_payment_qr($pdo);

json_response(true, 'OK', [
    'settings' => [
        'paymentQrUrl' => $qr['url'],
        'paymentQrLabel' => $qr['label'],
        'resortName' => $row['resort_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'address' => $row['address'],
        'updatedAt' => $row['updated_at'],
    ],
]);
