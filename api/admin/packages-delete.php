<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$type = input_str($body, 'type');
$id   = (int) ($body['id'] ?? 0);

if (!in_array($type, ['room', 'event'], true)) {
    json_response(false, 'Package type must be "room" or "event".', [], 422);
}
if ($id <= 0) {
    json_response(false, 'A valid package is required.', [], 422);
}

$pdo = get_db_connection();
$table = $type === 'room' ? 'room_packages' : 'event_packages';

$stmt = $pdo->prepare("DELETE FROM {$table} WHERE id = ?");
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    json_response(false, 'That package could not be found.', [], 404);
}

json_response(true, 'Package deleted.');
