<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(false, 'A valid review is required.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $session['user_id']]);

if ($stmt->rowCount() === 0) {
    json_response(false, 'You don\'t have a review to delete.', [], 404);
}

json_response(true, 'Your review has been removed.');
