<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) {
    json_response(false, 'No photo was uploaded.', [], 422);
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(false, 'The photo failed to upload. Please try again.', [], 422);
}

if ($file['size'] > 3 * 1024 * 1024) {
    json_response(false, 'Photo must be smaller than 3 MB.', [], 422);
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

$ext = $allowedMime[$detectedMime];
$fileName = 'team_' . bin2hex(random_bytes(8)) . '.' . $ext;
$teamDir = __DIR__ . '/../../uploads/team';

if (!is_dir($teamDir) && !mkdir($teamDir, 0755, true) && !is_dir($teamDir)) {
    json_response(false, 'Could not create the upload folder.', [], 500);
}

if (!move_uploaded_file($file['tmp_name'], $teamDir . '/' . $fileName)) {
    json_response(false, 'Could not save the uploaded photo. Please try again.', [], 500);
}

$relativePath = 'uploads/team/' . $fileName;

json_response(true, 'Photo uploaded.', [
    'photoPath' => $relativePath,
    'photoUrl'  => SITE_URL . '/' . $relativePath,
]);
