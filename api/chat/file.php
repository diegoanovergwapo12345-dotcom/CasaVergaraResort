<?php
// Streams a chat attachment, but only to the guest who owns the conversation or to staff.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
session_write_close();

$messageId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($messageId < 1) {
    json_response(false, 'Missing file.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$stmt = $pdo->prepare(
    'SELECT m.msg_type, m.attachment_path, m.attachment_name, m.attachment_mime, m.deleted_for_all,
            m.deleted_for_customer, m.deleted_for_staff, c.customer_id
     FROM live_chat_messages m
     JOIN live_chat_conversations c ON c.id = m.conversation_id
     WHERE m.id = ?'
);
$stmt->execute([$messageId]);
$row = $stmt->fetch();

$allowed = $row
    && in_array($row['msg_type'], ['image', 'file'], true)
    && (cvr_chat_is_staff_role($session['role'] ?? '') || (int) $row['customer_id'] === (int) $session['user_id']);

if ($allowed) {
    $viewerIsStaff = cvr_chat_is_staff_role($session['role'] ?? '');
    if (!empty($row['deleted_for_all'])
        || ($viewerIsStaff ? !empty($row['deleted_for_staff']) : !empty($row['deleted_for_customer']))) {
        $allowed = false;
    }
}

if (!$allowed) {
    http_response_code(404);
    exit('Not found.');
}

$base = realpath(__DIR__ . '/../../' . CVR_CHAT_UPLOAD_DIR);
$path = realpath(__DIR__ . '/../../' . $row['attachment_path']);
if (!$base || !$path || strpos($path, $base) !== 0 || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

$mime = (string) $row['attachment_mime'];
$inlineOk = in_array($mime, array_values(CVR_CHAT_IMAGE_EXT), true) && empty($_GET['download']);
$name = str_replace(['"', "\r", "\n"], '', (string) $row['attachment_name']);

header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=86400');
header('Content-Disposition: ' . ($inlineOk ? 'inline' : 'attachment') . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode($name));
readfile($path);
exit;
