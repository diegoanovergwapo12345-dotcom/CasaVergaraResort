<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();

if (empty($_FILES['avatar']) || !is_array($_FILES['avatar'])) {
    json_response(false, 'No file was uploaded.', [], 422);
}

$file = $_FILES['avatar'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    json_response(false, 'The file failed to upload. Please try again.', [], 422);
}

$maxBytes = 5 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    json_response(false, 'File must be smaller than 5 MB.', [], 422);
}

$originalName = (string) ($file['name'] ?? '');
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$ext = preg_replace('/[^a-z0-9]/', '', $ext);
if ($ext === '' || strlen($ext) > 10) {
    $ext = 'bin';
}
$dangerous = ['php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'phps',
              'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'bat', 'cmd', 'com', 'htaccess', 'htm', 'html', 'shtml', 'svgz'];
if (in_array($ext, $dangerous, true)) {
    $ext = 'bin';
}

$userId = (int) $_SESSION['user_id'];
$fileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$avatarsDir = __DIR__ . '/../../uploads/avatars';

if (!is_dir($avatarsDir)) {
    mkdir($avatarsDir, 0755, true);
}

$destPath = $avatarsDir . '/' . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    json_response(false, 'Could not save the uploaded file. Please try again.', [], 500);
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

json_response(true, 'Profile file updated.', [
    'avatarPath' => $relativePath,
    'avatarUrl'  => SITE_URL . '/' . $relativePath,
]);
