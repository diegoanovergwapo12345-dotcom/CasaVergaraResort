<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);
$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$stmt = $pdo->query('SELECT COALESCE(SUM(staff_unread), 0) FROM live_chat_conversations');
$unread = (int) $stmt->fetchColumn();

json_response(true, 'OK', [
    'unreadCount' => $unread,
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
