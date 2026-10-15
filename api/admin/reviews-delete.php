<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(false, 'A valid review is required.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
$stmt->execute([$id]);

if ($stmt->rowCount() === 0) {
    json_response(false, 'That review could not be found.', [], 404);
}

json_response(true, 'Review deleted.');
