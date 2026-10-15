<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login(); 
session_write_close();
@set_time_limit(25);
$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$afterId = isset($_GET['afterId']) ? (int) $_GET['afterId'] : 0;
$wait = isset($_GET['wait']) && $_GET['wait'] === '1';
$conversationId = isset($_GET['conversationId']) ? (int) $_GET['conversationId'] : 0;

$isStaff = cvr_chat_is_staff_role($session['role'] ?? '');
if ($isStaff) {
    cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);
    if ($conversationId < 1) {
        json_response(false, 'Missing conversation.', [], 422);
    }
    $stmt = $pdo->prepare('SELECT * FROM live_chat_conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    $conversation = $stmt->fetch();
    if (!$conversation) {
        json_response(false, 'Conversation not found.', [], 404);
    }
} else {
    cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);
    $conversation = cvr_chat_find_conversation($pdo, (int) $session['user_id']);
    if (!$conversation) {
        json_response(true, 'OK', [
            'conversationId' => 0,
            'messages' => [],
            'presence' => cvr_chat_presence_snapshot($pdo),
            'unread' => 0,
        ]);
    }
    $conversationId = (int) $conversation['id'];
}

$deadline = time() + ($wait ? 18 : 0);
$messages = [];
do {
    $messages = cvr_chat_fetch_messages(
        $pdo,
        $conversationId,
        $isStaff ? max($afterId, cvr_chat_staff_hidden_before($pdo, $conversationId, (int) $session['user_id'])) : max($afterId, (int) ($conversation['customer_cleared_id'] ?? 0)),
        200,
        (int) $session['user_id']
    );
    if ($messages || !$wait) {
        break;
    }
    usleep(700000);
} while (time() < $deadline);

json_response(true, 'OK', [
    'conversationId' => $conversationId,
    'messages' => $messages,
    'deletedIds' => cvr_chat_deleted_ids($pdo, $conversationId, $isStaff ? 0 : (int) ($conversation['customer_cleared_id'] ?? 0)),
    'seenUpTo' => cvr_chat_seen_state($pdo, $conversationId, $isStaff ? 'staff' : 'customer'),
    'presence' => cvr_chat_presence_snapshot($pdo),
    'unread' => $isStaff
        ? (int) ($conversation['staff_unread'] ?? 0)
        : (int) ($conversation['customer_unread'] ?? 0),
]);