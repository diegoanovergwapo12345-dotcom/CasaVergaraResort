<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();

$pdo = get_db_connection();

try {
    $stmt = $pdo->prepare(
        'SELECT payload_json FROM reservations
         WHERE user_id = ? OR LOWER(email) = LOWER(?)
         ORDER BY updated_at DESC'
    );
    $stmt->execute([$session['user_id'], $session['email'] ?? '']);
    $rows = $stmt->fetchAll();
} catch (\Throwable $e) {
    error_log('my-reservations.php: query failed (has database/migration_reservations.sql been run?): ' . $e->getMessage());
    json_response(true, 'OK', ['reservations' => []]);
}

$reservations = [];
foreach ($rows as $row) {
    $decoded = json_decode($row['payload_json'], true);
    if (is_array($decoded)) {
        $reservations[] = $decoded;
    }
}

json_response(true, 'OK', ['reservations' => $reservations]);
