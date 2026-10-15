<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$userId = (int) $session['user_id'];

// Accept the field as "file" (new) or "image" (older clients).
$file = $_FILES['file'] ?? ($_FILES['image'] ?? null);
if (!is_array($file)) {
    json_response(false, 'No file was uploaded. The file may be larger than the server allows.', [], 422);
}

if ($file['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'That file is too large. The limit is 25 MB.'
        : 'The file failed to upload. Please try again.';
    json_response(false, $msg, [], 422);
}
if ($file['size'] > CVR_CHAT_MAX_UPLOAD_BYTES) {
    json_response(false, 'Files must be smaller than 25 MB.', [], 422);
}
if ($file['size'] < 1) {
    json_response(false, 'That file is empty.', [], 422);
}

$originalName = cvr_chat_clean_file_name((string) ($file['name'] ?? 'file'));
$ext = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
if (cvr_chat_blocked_extension($ext)) {
    json_response(false, 'For security, that file type cannot be sent. Try zipping it first.', [], 422);
}

// Photos / GIFs are recognised from the real file contents (never from the name) and shown inline.
$imageTypes = [
    'image/gif'  => 'gif',
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file($file['tmp_name']);
$isImage = isset($imageTypes[$mime]) && @getimagesize($file['tmp_name']) !== false;

if ($isImage) {
    $dir = __DIR__ . '/../../uploads/chat';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = 'chat_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $imageTypes[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        json_response(false, 'Could not save the file. Please try again.', [], 500);
    }
    $relative = 'uploads/chat/' . $name;
    json_response(true, 'Uploaded.', [
        'kind' => 'image',
        'imageUrl' => $relative,
        'previewUrl' => cvr_chat_public_image_url($relative),
    ]);
}

// Any other file: stored in a private folder (direct web access is denied) with no
// original extension trusted for execution; it is served only by api/chat/file.php.
$dir = __DIR__ . '/../../uploads/chat/files';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
$htaccess = $dir . '/.htaccess';
if (!is_file($htaccess)) {
    @file_put_contents($htaccess, "# Private chat attachments - served only through api/chat/file.php\nOptions -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Order deny,allow\n  Deny from all\n</IfModule>\n");
}

$safeExt = preg_match('/^[a-z0-9]{1,10}$/', $ext) ? '.' . $ext : '';
$name = 'chatf_' . $userId . '_' . bin2hex(random_bytes(8)) . $safeExt;
if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
    json_response(false, 'Could not save the file. Please try again.', [], 500);
}

json_response(true, 'Uploaded.', [
    'kind' => 'file',
    'fileUrl' => 'uploads/chat/files/' . $name,
    'fileName' => $originalName,
    'fileSize' => (int) $file['size'],
]);
