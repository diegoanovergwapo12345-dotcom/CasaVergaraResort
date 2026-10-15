<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();

try {
    $rows = $pdo->query('SELECT payload_json FROM reservations ORDER BY updated_at DESC')->fetchAll();
} catch (\Throwable $e) {
    error_log('admin/reservations-list.php: query failed (has database/migration_reservations.sql been run?): ' . $e->getMessage());
    json_response(false, 'Could not load reservations. Has database/migration_reservations.sql been run?', [], 500);
}

$reservations = [];
foreach ($rows as $row) {
    $decoded = json_decode($row['payload_json'], true);
    if (is_array($decoded)) {
        $reservations[] = $decoded;
    }
}

json_response(true, 'OK', ['reservations' => $reservations]);
