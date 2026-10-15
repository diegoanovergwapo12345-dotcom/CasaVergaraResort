<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

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

$blockedExtensions = [
    'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh',
    'ps1', 'psm1', 'sh', 'bash', 'php', 'php3', 'php4', 'php5', 'php7', 'phtml', 'phar',
    'cgi', 'pl', 'py', 'jar', 'apk', 'app', 'dmg', 'html', 'htm', 'svg', 'swf', 'reg',
    'lnk', 'hta', 'asp', 'aspx', 'jsp',
];

$originalName = (string) ($file['name'] ?? '');
$nameParts = explode('.', $originalName);
$lastExt = strtolower((string) array_pop($nameParts)) ?: 'bin';
$allExtParts = array_map('strtolower', array_slice($nameParts, 1));
$allExtParts[] = $lastExt;

foreach ($allExtParts as $part) {
    if (in_array($part, $blockedExtensions, true)) {
        json_response(false, 'That file type isn\'t allowed for security reasons. Please upload it as a photo, PDF, or document file instead.', [], 422);
    }
}

$ext = preg_replace('/[^a-z0-9]/', '', $lastExt) ?: 'bin';

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detectedMime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

$fileName = 'pkg_' . bin2hex(random_bytes(8)) . '.' . $ext;
$dir = __DIR__ . '/../../uploads/packages';

if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    json_response(false, 'Could not create the upload folder.', [], 500);
}

if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $fileName)) {
    json_response(false, 'Could not save the uploaded file. Please try again.', [], 500);
}

$relativePath = 'uploads/packages/' . $fileName;

json_response(true, 'File uploaded.', [
    'photoPath' => $relativePath,
    'photoUrl'  => SITE_URL . '/' . $relativePath,
    'mimeType'  => $detectedMime,
]);
