<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);
$conversationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($conversationId < 1) {
    json_response(false, 'Missing conversation.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);
cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);

$stmt = $pdo->prepare(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_path
     FROM live_chat_conversations c
     JOIN users u ON u.id = c.customer_id
     WHERE c.id = ?"
);
$stmt->execute([$conversationId]);
$row = $stmt->fetch();
if (!$row) {
    json_response(false, 'Conversation not found.', [], 404);
}

$afterId = isset($_GET['afterId']) ? (int) $_GET['afterId'] : 0;
$hiddenBefore = cvr_chat_staff_hidden_before($pdo, $conversationId, (int) $session['user_id']);
$messages = cvr_chat_fetch_messages($pdo, $conversationId, max($afterId, $hiddenBefore), 200, (int) $session['user_id']);

$formattedConv = cvr_chat_format_conversation($row, $row);
$online = cvr_chat_online_customers($pdo);
$formattedConv['customerOnline'] = isset($online[(int) $row['customer_id']]);
$formattedConv['customerLastSeen'] = cvr_chat_last_seen($pdo, (int) $row['customer_id']);

json_response(true, 'OK', [
    'conversation' => $formattedConv,
    'messages' => $messages,
    'deletedIds' => cvr_chat_deleted_ids($pdo, $conversationId, $hiddenBefore),
    'seenUpTo' => cvr_chat_seen_state($pdo, $conversationId, 'staff'),
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
