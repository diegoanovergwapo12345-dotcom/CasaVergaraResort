<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();
$text = trim((string) ($body['message'] ?? $body['body'] ?? ''));

$imageUrl = null;
$rawImage = trim((string) ($body['imageUrl'] ?? ''));
if ($rawImage !== '') {
    $imageUrl = cvr_chat_normalize_image_url($rawImage);
    if ($imageUrl === null) {
        json_response(false, 'That GIF or photo is not allowed.', [], 422);
    }
}

$file = null;
$rawFile = trim((string) ($body['fileUrl'] ?? ''));
if ($rawFile !== '') {
    $file = cvr_chat_normalize_file($rawFile, (string) ($body['fileName'] ?? ''), (int) $session['user_id']);
    if ($file === null) {
        json_response(false, 'That attachment is not allowed.', [], 422);
    }
}

if ($text === '' && $imageUrl === null && $file === null) {
    json_response(false, 'Please type a message first.', [], 422);
}
if (mb_strlen($text) > 2000) {
    json_response(false, 'Messages must be 2000 characters or fewer.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$isStaff = cvr_chat_is_staff_role($session['role'] ?? '');

if ($isStaff) {
    cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);
    $conversationId = (int) ($body['conversationId'] ?? 0);
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
    $conversation = cvr_chat_get_or_create_conversation($pdo, (int) $session['user_id']);
}

$message = cvr_chat_post_message($pdo, $conversation, $session, $text, null, null, $imageUrl, $file);

$stmt = $pdo->prepare('SELECT * FROM live_chat_conversations WHERE id = ?');
$stmt->execute([(int) $conversation['id']]);
$conversation = $stmt->fetch();

json_response(true, 'Sent.', [
    'message' => $message,
    'conversation' => cvr_chat_format_conversation($conversation),
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
