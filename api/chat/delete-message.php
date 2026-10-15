<?php

// Messenger-style removal of ONE message.
//   scope = "me"       -> "Remove for you": hidden from the caller's view only.
//   scope = "everyone" -> "Unsend": only the person who sent it. The message is replaced by
//                         "<name> unsent a message" for everyone and its photo / file is deleted.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();
$messageId = (int) ($body['messageId'] ?? 0);
$scope = (string) ($body['scope'] ?? '');
if ($messageId < 1 || !in_array($scope, ['me', 'everyone'], true)) {
    json_response(false, 'Invalid request.', [], 422);
}

$userId = (int) $session['user_id'];
$isStaff = cvr_chat_is_staff_role($session['role'] ?? '');

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$stmt = $pdo->prepare(
    'SELECT m.*, c.customer_id
     FROM live_chat_messages m
     JOIN live_chat_conversations c ON c.id = m.conversation_id
     WHERE m.id = ?'
);
$stmt->execute([$messageId]);
$msg = $stmt->fetch();
if (!$msg) {
    json_response(false, 'Message not found.', [], 404);
}
if (!$isStaff && (int) $msg['customer_id'] !== $userId) {
    json_response(false, 'You do not have access to this message.', [], 403);
}

if ($scope === 'me') {
    $pdo->prepare('INSERT IGNORE INTO live_chat_hidden_messages (message_id, user_id) VALUES (?, ?)')
        ->execute([$messageId, $userId]);
    json_response(true, 'Message removed for you.');
}

if ((int) $msg['sender_id'] !== $userId) {
    json_response(false, 'You can only unsend your own messages.', [], 403);
}

if (empty($msg['deleted_at'])) {
    cvr_chat_unlink_attachment($msg['image_url'] ?? null);
    cvr_chat_unlink_attachment($msg['file_path'] ?? null);
    $pdo->prepare(
        "UPDATE live_chat_messages
         SET body = '', image_url = NULL, file_path = NULL, file_name = NULL, file_size = NULL, deleted_at = NOW()
         WHERE id = ?"
    )->execute([$messageId]);

    $latest = $pdo->prepare('SELECT MAX(id) FROM live_chat_messages WHERE conversation_id = ?');
    $latest->execute([(int) $msg['conversation_id']]);
    if ((int) $latest->fetchColumn() === $messageId) {
        $pdo->prepare("UPDATE live_chat_conversations SET last_message_preview = 'Message unsent' WHERE id = ?")
            ->execute([(int) $msg['conversation_id']]);
    }
}

json_response(true, 'Message unsent.');
