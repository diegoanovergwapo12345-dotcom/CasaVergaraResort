<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);
$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);
cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);

$stmt = $pdo->query(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_path" . CVR_CHAT_CUSTOMER_PRESENCE_SELECT . "
     FROM live_chat_conversations c
     JOIN users u ON u.id = c.customer_id" . CVR_CHAT_CUSTOMER_PRESENCE_JOIN . "
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC"
);
$rows = $stmt->fetchAll();

$conversations = [];
$totalUnread = 0;
foreach ($rows as $row) {
    $formatted = cvr_chat_format_conversation($row, $row);
    $conversations[] = $formatted;
    $totalUnread += (int) $formatted['staffUnread'];
}

json_response(true, 'OK', [
    'conversations' => $conversations,
    'unreadCount' => $totalUnread,
    'presence' => cvr_chat_presence_snapshot($pdo),
    'topics' => cvr_chat_topics_payload(),
]);
