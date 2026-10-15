<?php

// Sends a chat attachment as a download. Only the guest in the conversation and staff may open it.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
session_write_close();

$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($messageId < 1) {
    http_response_code(404);
    exit('File not found.');
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$stmt = $pdo->prepare(
    'SELECT m.file_path, m.file_name, c.customer_id
     FROM live_chat_messages m
     JOIN live_chat_conversations c ON c.id = m.conversation_id
     WHERE m.id = ? AND m.file_path IS NOT NULL AND m.deleted_at IS NULL'
);
$stmt->execute([$messageId]);
$row = $stmt->fetch();
if (!$row) {
    http_response_code(404);
    exit('File not found.');
}

$isStaff = cvr_chat_is_staff_role($session['role'] ?? '');
if (!$isStaff && (int) $row['customer_id'] !== (int) $session['user_id']) {
    http_response_code(403);
    exit('You do not have access to this file.');
}

if (!preg_match('#^uploads/chat/files/chatf_\d+_[a-f0-9]{16}(\.[a-z0-9]{1,10})?$#i', (string) $row['file_path'])) {
    http_response_code(404);
    exit('File not found.');
}
$full = __DIR__ . '/../../' . $row['file_path'];
if (!is_file($full)) {
    http_response_code(404);
    exit('This file is no longer available.');
}

$name = cvr_chat_clean_file_name((string) ($row['file_name'] ?: 'file'));
$asciiName = preg_replace('/[^A-Za-z0-9._ -]+/', '_', $name);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . filesize($full));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
readfile($full);
exit;
