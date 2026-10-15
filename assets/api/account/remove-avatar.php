<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();

$userId = (int) $_SESSION['user_id'];
$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ?');
$stmt->execute([$userId]);
$previous = $stmt->fetchColumn();

$update = $pdo->prepare('UPDATE users SET avatar_path = NULL WHERE id = ?');
$update->execute([$userId]);

if ($previous) {
    $previousAbsolute = __DIR__ . '/../../' . $previous;
    if (is_file($previousAbsolute)) {
        @unlink($previousAbsolute);
    }
}

$_SESSION['avatar_path'] = null;

json_response(true, 'Profile photo removed.');
