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

$stmt = $pdo->prepare(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_path,
            (SELECT MAX(m.id) FROM live_chat_messages m WHERE m.conversation_id = c.id) AS last_msg_id,
            h.hidden_before_id
     FROM live_chat_conversations c
     JOIN users u ON u.id = c.customer_id
     LEFT JOIN live_chat_staff_hidden h ON h.conversation_id = c.id AND h.user_id = ?
     ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.id DESC"
);
$stmt->execute([(int) $session['user_id']]);
$rows = $stmt->fetchAll();

$online = cvr_chat_online_customers($pdo);
$conversations = [];
$totalUnread = 0;
foreach ($rows as $row) {
    // "Deleted for me": stay hidden until the guest writes something new.
    if ($row['hidden_before_id'] !== null && (int) $row['last_msg_id'] <= (int) $row['hidden_before_id']) {
        continue;
    }
    $formatted = cvr_chat_format_conversation($row, $row);
    $cid = (int) $row['customer_id'];
    $formatted['customerOnline'] = isset($online[$cid]);
    $conversations[] = $formatted;
    $totalUnread += (int) $formatted['staffUnread'];
}

json_response(true, 'OK', [
    'conversations' => $conversations,
    'unreadCount' => $totalUnread,
    'onlineGuests' => count($online),
    'presence' => cvr_chat_presence_snapshot($pdo),
    'topics' => cvr_chat_topics_payload(),
]);
