<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$key = trim((string) ($_POST['key'] ?? ''));
if ($key === '') {
    json_response(false, 'Missing content key.', [], 422);
}

if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) {
    json_response(false, 'No photo was uploaded.', [], 422);
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(false, 'The photo failed to upload. Please try again.', [], 422);
}

if ($file['size'] > 5 * 1024 * 1024) {
    json_response(false, 'Photo must be smaller than 5 MB.', [], 422);
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

if (@getimagesize($file['tmp_name']) === false) {
    json_response(false, 'That file does not look like a valid image.', [], 422);
}

$pdo = get_db_connection();

$existing = $pdo->prepare('SELECT id, content_type, image_path FROM site_content WHERE content_key = ?');
$existing->execute([$key]);
$row = $existing->fetch();

if (!$row) {
    json_response(false, 'That content field could not be found.', [], 404);
}

if ($row['content_type'] !== 'image') {
    json_response(false, 'This is a text field — edit the text instead of uploading a photo.', [], 422);
}

$ext = $allowedMime[$detectedMime];
$fileName = 'content_' . bin2hex(random_bytes(8)) . '.' . $ext;
$contentDir = __DIR__ . '/../../uploads/site-content';

if (!is_dir($contentDir) && !mkdir($contentDir, 0755, true) && !is_dir($contentDir)) {
    json_response(false, 'Could not create the upload folder.', [], 500);
}

if (!move_uploaded_file($file['tmp_name'], $contentDir . '/' . $fileName)) {
    json_response(false, 'Could not save the uploaded photo. Please try again.', [], 500);
}

$relativePath = 'uploads/site-content/' . $fileName;
$previous = $row['image_path'];

$stmt = $pdo->prepare('UPDATE site_content SET image_path = ? WHERE content_key = ?');
$stmt->execute([$relativePath, $key]);

if ($previous && $previous !== $relativePath && strpos($previous, 'uploads/site-content/') === 0) {
    $previousAbsolute = __DIR__ . '/../../' . $previous;
    if (is_file($previousAbsolute)) {
        @unlink($previousAbsolute);
    }
}

json_response(true, 'Photo updated.', [
    'key' => $key,
    'imagePath' => $relativePath,
    'imageUrl' => SITE_URL . '/' . $relativePath,
]);
