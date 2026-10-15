<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$body = get_json_body();
$id = input_str($body, 'id');
if ($id === '') {
    json_response(false, 'Missing reservation id.', [], 422);
}

$pdo = get_db_connection();

try {
    $pdo->prepare('DELETE FROM reservations WHERE id = ?')->execute([$id]);
    json_response(true, 'Reservation deleted.', ['id' => $id]);
} catch (\Throwable $e) {
    error_log('admin/reservations-delete.php: delete failed: ' . $e->getMessage());
    json_response(false, 'Could not delete this reservation.', [], 500);
}
