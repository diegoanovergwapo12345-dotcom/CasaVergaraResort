<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(false, 'Invalid notification.', [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
$stmt->execute([$id, $session['user_id']]);

json_response(true, 'OK');
