<?php

// Delete a specific chat, with a choice of scope:
//   scope = "me"       -> only removes it from the caller's own view
//                          guest : clears their chat window (the resort keeps its copy)
//                          staff : hides it from that staff member's inbox only; it comes back
//                                  if the guest sends a new message
//   scope = "everyone" -> permanently deletes the whole conversation (messages and files)
//                          for the guest and all staff. Cannot be undone.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();
$scope = (string) ($body['scope'] ?? '');
if (!in_array($scope, ['me', 'everyone'], true)) {
    json_response(false, 'Please choose who to delete this chat for.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

if (cvr_chat_is_staff_role($session['role'] ?? '')) {
    $conversationId = (int) ($body['conversationId'] ?? 0);
    if ($conversationId < 1) {
        json_response(false, 'Missing conversation.', [], 422);
    }
    $stmt = $pdo->prepare('SELECT id FROM live_chat_conversations WHERE id = ?');
    $stmt->execute([$conversationId]);
    if (!$stmt->fetchColumn()) {
        json_response(false, 'Conversation not found.', [], 404);
    }
    if ($scope === 'everyone') {
        cvr_chat_delete_conversation($pdo, $conversationId);
        json_response(true, 'Conversation deleted for everyone.');
    }
    cvr_chat_staff_hide_for_me($pdo, $conversationId, (int) $session['user_id']);
    json_response(true, 'Conversation deleted for you.');
}

$conversation = cvr_chat_find_conversation($pdo, (int) $session['user_id']);
if ($conversation) {
    if ($scope === 'everyone') {
        cvr_chat_delete_conversation($pdo, (int) $conversation['id']);
        json_response(true, 'Chat deleted for everyone.');
    }
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM live_chat_messages WHERE conversation_id = ?');
    $stmt->execute([(int) $conversation['id']]);
    $maxId = (int) $stmt->fetchColumn();
    $pdo->prepare('UPDATE live_chat_conversations SET customer_cleared_id = ?, customer_unread = 0 WHERE id = ?')
        ->execute([$maxId, (int) $conversation['id']]);
}
json_response(true, 'Chat deleted for you.');
