<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
if (cvr_chat_is_staff_role($session['role'] ?? '')) {
    json_response(false, 'Staff accounts use the Live Chat inbox in the dashboard.', [], 403);
}

$pdo = get_db_connection();
$conversation = null;
$messages = [];
try {
    cvr_chat_ensure_tables($pdo);
    cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);
    $conversation = cvr_chat_find_conversation($pdo, (int) $session['user_id']);
    $messages = $conversation ? cvr_chat_fetch_messages($pdo, (int) $conversation['id'], (int) ($conversation['customer_cleared_id'] ?? 0), 200, (int) $session['user_id']) : [];
} catch (\Throwable $e) {
    error_log('chat/conversation.php: ' . $e->getMessage());
    json_response(false, 'Could not load chat right now.', [], 500);
}

$customer = [
    'first_name' => $session['first_name'] ?? '',
    'last_name' => $session['last_name'] ?? '',
    'email' => $session['email'] ?? '',
];

json_response(true, 'OK', [
    'conversation' => $conversation ? cvr_chat_format_conversation($conversation, $customer) : null,
    'messages' => $messages,
    'deletedIds' => $conversation ? cvr_chat_deleted_ids($pdo, (int) $conversation['id'], (int) ($conversation['customer_cleared_id'] ?? 0)) : [],
    'seenUpTo' => $conversation ? cvr_chat_seen_state($pdo, (int) $conversation['id'], 'customer') : ['id' => 0, 'at' => null],
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
