<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$types = [
    'privacy' => 'Privacy Policy',
    'room' => 'Terms & Conditions — Room Bookings',
    'event' => 'Terms & Conditions — Event Bookings',
];

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT version, created_at FROM terms_and_conditions WHERE booking_type = ? AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1'
);

$documents = [];
foreach ($types as $type => $label) {
    $stmt->execute([$type]);
    $row = $stmt->fetch();
    if (!$row) {
        continue;
    }
    $documents[] = [
        'type' => $type,
        'label' => $label,
        'version' => $row['version'],
        'updatedAt' => $row['created_at'],
    ];
}

json_response(true, 'OK', ['documents' => $documents]);
