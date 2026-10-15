<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_images.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin']);

$slotKey = trim((string) ($_POST['slot'] ?? ''));
if (!isset(SITE_IMAGE_SLOTS[$slotKey])) {
    json_response(false, 'Unknown photo slot.', [], 422);
}

if (empty($_FILES['photo']) || !is_array($_FILES['photo'])) {
    json_response(false, 'No file was uploaded.', [], 422);
}

$file = $_FILES['photo'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(false, 'The file failed to upload. Please try again.', [], 422);
}

if ($file['size'] > 5 * 1024 * 1024) {
    json_response(false, 'File must be smaller than 5 MB.', [], 422);
}

$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

$originalName = (string) ($file['name'] ?? '');
$nameParts = explode('.', $originalName);
$ext = strtolower((string) array_pop($nameParts));

if (!in_array($ext, $allowedExtensions, true)) {
    json_response(false, 'Please upload a JPG, PNG, WEBP, or GIF image.', [], 422);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']) ?: '';
if (!str_starts_with($detectedMime, 'image/')) {
    json_response(false, 'That file doesn\'t look like a valid image. Please upload a JPG, PNG, WEBP, or GIF.', [], 422);
}

$fileName = 'site_' . preg_replace('/[^a-z0-9]+/', '-', $slotKey) . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$dir = __DIR__ . '/../../uploads/site';

if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    json_response(false, 'Could not create the upload folder.', [], 500);
}

if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fileName)) {
    json_response(false, 'Could not save the uploaded file. Please try again.', [], 500);
}

$relativePath = 'uploads/site/' . $fileName;

$pdo = get_db_connection();
$adminId = (int) ($session['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO site_images (slot_key, image_path, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE image_path = VALUES(image_path), updated_by = VALUES(updated_by)'
    );
    $stmt->execute([$slotKey, $relativePath, $adminId]);
} catch (\Throwable $e) {
    error_log('site-image-upload.php: could not save site_images row (run database/migration_site_images.sql): ' . $e->getMessage());
    json_response(false, 'The photo uploaded, but could not be saved. Please run database/migration_site_images.sql.', [], 500);
}

json_response(true, 'Photo updated.', [
    'slotKey' => $slotKey,
    'imagePath' => $relativePath,
    'imageUrl' => SITE_URL . '/' . $relativePath,
]);
