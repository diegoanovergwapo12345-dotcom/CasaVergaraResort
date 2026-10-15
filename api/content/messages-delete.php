<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$email = strtolower((string) $session['email']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(false, 'Invalid message.', [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('UPDATE contact_messages SET customer_deleted = 1 WHERE id = ? AND LOWER(email) = ?');
$stmt->execute([$id, $email]);

json_response(true, 'OK');
