<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);
$status = input_str($body, 'status');

if ($id <= 0) {
    json_response(false, 'A valid review is required.', [], 422);
}
if (!in_array($status, ['visible', 'hidden'], true)) {
    json_response(false, 'Status must be "visible" or "hidden".', [], 422);
}

$pdo = get_db_connection();

$exists = $pdo->prepare('SELECT id FROM reviews WHERE id = ?');
$exists->execute([$id]);
if (!$exists->fetch()) {
    json_response(false, 'That review could not be found.', [], 404);
}

$stmt = $pdo->prepare('UPDATE reviews SET status = ? WHERE id = ?');
$stmt->execute([$status, $id]);

json_response(true, 'Review updated.');
