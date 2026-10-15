<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();
$conversationId = (int) ($body['conversationId'] ?? 0);

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$isStaff = cvr_chat_is_staff_role($session['role'] ?? '');

if ($isStaff) {
    if ($conversationId < 1) {
        json_response(false, 'Missing conversation.', [], 422);
    }
    $stmt = $pdo->prepare('UPDATE live_chat_conversations SET staff_unread = 0 WHERE id = ?');
    $stmt->execute([$conversationId]);
    cvr_chat_mark_seen($pdo, $conversationId, 'staff');
} else {
    $conversation = cvr_chat_find_conversation($pdo, (int) $session['user_id']);
    if ($conversation) {
        $stmt = $pdo->prepare('UPDATE live_chat_conversations SET customer_unread = 0 WHERE id = ? AND customer_id = ?');
        $stmt->execute([(int) $conversation['id'], (int) $session['user_id']]);
        cvr_chat_mark_seen($pdo, (int) $conversation['id'], 'customer');
    }
}

json_response(true, 'OK');
