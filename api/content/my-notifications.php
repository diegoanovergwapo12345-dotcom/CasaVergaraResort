<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();

$audience = in_array($session['role'] ?? '', ['admin', 'event_manager'], true) ? 'staff' : 'customer';

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT id, title, message, link, is_read, created_at
     FROM notifications
     WHERE user_id = ? AND audience = ?
     ORDER BY created_at DESC
     LIMIT 200'
);
$stmt->execute([$session['user_id'], $audience]);
$rows = $stmt->fetchAll();

$notifications = array_map(static function ($row) {
    return [
        'id'        => (int) $row['id'],
        'title'     => $row['title'],
        'message'   => $row['message'],
        'link'      => $row['link'],
        'read'      => (bool) $row['is_read'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

json_response(true, 'OK', ['notifications' => $notifications]);
