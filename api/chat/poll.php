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
$clientSig = isset($_GET['sig']) ? (string) $_GET['sig'] : null;

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
    cvr_chat_touch_customer_presence($pdo, (int) $session['user_id'], true);
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

$viewer = $isStaff ? 'staff' : 'customer';
$deadline = time() + ($wait ? 18 : 0);
$messages = [];
$statuses = [];
$sig = '';
do {
    $messages = cvr_chat_fetch_messages($pdo, $conversationId, $afterId, 200, $viewer);
    [$statuses, $sig] = cvr_chat_message_statuses($pdo, $conversationId, $viewer);
    // Wake up for new messages, or when a message was seen / deleted.
    if ($messages || !$wait || ($clientSig !== null && $clientSig !== $sig)) {
        break;
    }
    usleep(700000);
} while (time() < $deadline);

json_response(true, 'OK', [
    'conversationId' => $conversationId,
    'messages' => $messages,
    'statuses' => $statuses,
    'sig' => $sig,
    'presence' => cvr_chat_presence_snapshot($pdo),
    'unread' => $isStaff
        ? (int) ($conversation['staff_unread'] ?? 0)
        : (int) ($conversation['customer_unread'] ?? 0),
]);