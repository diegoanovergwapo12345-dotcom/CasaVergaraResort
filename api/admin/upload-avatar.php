<?php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
    json_response(false, 'No photo was uploaded.', [], 422);
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(false, 'The photo failed to upload. Please try again.', [], 422);
}

$maxBytes = 2 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    json_response(false, 'Photo must be smaller than 2 MB.', [], 422);
}

$allowedMime = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']) ?: '';

if (!isset($allowedMime[$detectedMime])) {
    json_response(false, 'Photo must be a JPG, PNG, or WEBP image.', [], 422);
}

$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    json_response(false, 'That file does not look like a valid image.', [], 422);
}

$ext = $allowedMime[$detectedMime];
$userId = (int) $_SESSION['user_id'];
$fileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$avatarsDir = __DIR__ . '/../../uploads/avatars';

if (!is_dir($avatarsDir)) {
    mkdir($avatarsDir, 0755, true);
}

$destPath = $avatarsDir . '/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_response(false, 'Could not save the uploaded photo. Please try again.', [], 500);
}

$relativePath = 'uploads/avatars/' . $fileName;

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ?');
$stmt->execute([$userId]);
$previous = $stmt->fetchColumn();

$update = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE id = ?');
$update->execute([$relativePath, $userId]);

if ($previous && $previous !== $relativePath) {
    $previousAbsolute = __DIR__ . '/../../' . $previous;
    if (is_file($previousAbsolute)) {
        @unlink($previousAbsolute);
    }
}

$_SESSION['avatar_path'] = $relativePath;

json_response(true, 'Profile photo updated.', [
    'avatarPath' => $relativePath,
    'avatarUrl'  => SITE_URL . '/' . $relativePath,
]);
