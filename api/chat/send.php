<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();

// A file upload arrives as multipart/form-data; text, stickers and GIFs arrive as JSON.
$isUpload = !empty($_FILES['file']);
$body = $isUpload ? $_POST : get_json_body();

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
    $conversation = cvr_chat_get_or_create_conversation($pdo, (int) $session['user_id']);
    if (!$conversation) {
        json_response(false, 'Could not start the conversation.', [], 500);
    }
}

$type = $isUpload ? 'file' : (string) ($body['type'] ?? 'text');
$text = '';
$attachment = ['type' => 'text'];

switch ($type) {
    case 'file':
    case 'image':
        if (!$isUpload) {
            json_response(false, 'Please choose a file to send.', [], 422);
        }
        $attachment = cvr_chat_store_upload($_FILES['file'], (int) $conversation['id']);
        break;

    case 'sticker':
        $sticker = trim((string) ($body['sticker'] ?? ''));
        if (!cvr_chat_valid_sticker($sticker)) {
            json_response(false, 'That sticker isn\'t available.', [], 422);
        }
        $text = $sticker;
        $attachment = ['type' => 'sticker'];
        break;

    case 'gif':
        $url = trim((string) ($body['url'] ?? ''));
        if (!cvr_chat_valid_gif_url($url)) {
            json_response(false, 'That GIF can\'t be sent.', [], 422);
        }
        $title = mb_substr(trim((string) ($body['title'] ?? 'GIF')), 0, 120);
        $attachment = ['type' => 'gif', 'path' => $url, 'name' => $title !== '' ? $title : 'GIF'];
        break;

    case 'text':
    default:
        $text = trim((string) ($body['message'] ?? $body['body'] ?? ''));
        if ($text === '') {
            json_response(false, 'Please type a message first.', [], 422);
        }
        if (mb_strlen($text) > 2000) {
            json_response(false, 'Messages must be 2000 characters or fewer.', [], 422);
        }
        $attachment = ['type' => 'text'];
        break;
}

$message = cvr_chat_post_message($pdo, $conversation, $session, $text, null, null, $attachment);

$stmt = $pdo->prepare('SELECT * FROM live_chat_conversations WHERE id = ?');
$stmt->execute([(int) $conversation['id']]);
$conversation = $stmt->fetch();

json_response(true, 'Sent.', [
    'message' => $message,
    'conversation' => cvr_chat_format_conversation($conversation),
    'presence' => cvr_chat_presence_snapshot($pdo),
]);
