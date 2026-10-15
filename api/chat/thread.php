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
    "SELECT c.*, u.first_name, u.last_name, u.email, u.avatar_path" . CVR_CHAT_CUSTOMER_PRESENCE_SELECT . "
     FROM live_chat_conversations c
     JOIN users u ON u.id = c.customer_id" . CVR_CHAT_CUSTOMER_PRESENCE_JOIN . "
     WHERE c.id = ?"
);
$stmt->execute([$conversationId]);
$row = $stmt->fetch();
if (!$row) {
    json_response(false, 'Conversation not found.', [], 404);
}

$afterId = isset($_GET['afterId']) ? (int) $_GET['afterId'] : 0;
$messages = cvr_chat_fetch_messages($pdo, $conversationId, $afterId, 200, 'staff');
[$statuses, $sig] = cvr_chat_message_statuses($pdo, $conversationId, 'staff');

json_response(true, 'OK', [
    'conversation' => cvr_chat_format_conversation($row, $row),
    'messages' => $messages,
    'statuses' => $statuses,
    'sig' => $sig,
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
