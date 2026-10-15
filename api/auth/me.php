<?php
require_once __DIR__ . '/../../config/config.php';

if (empty($_SESSION['user_id'])) {
    json_response(false, 'Not logged in.', [], 401);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT phone, avatar_path, status, created_at FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$row = $stmt->fetch();
if (!$row) {

    session_destroy();
    json_response(false, 'Not logged in.', [], 401);
}

if ($row['status'] === 'suspended') {

    session_unset();
    session_destroy();
    json_response(false, 'This account has been suspended. Please contact Casa Vergara.', ['suspended' => true], 403);
}

$avatarPath = $row['avatar_path'];
$_SESSION['avatar_path'] = $avatarPath ?: null;
$_SESSION['phone'] = $row['phone'];

json_response(true, 'OK', [
    'user' => [
        'id' => $_SESSION['user_id'],
        'firstName' => $_SESSION['first_name'],
        'lastName' => $_SESSION['last_name'],
        'email' => $_SESSION['email'],
        'phone' => $row['phone'],
        'role' => $_SESSION['role'],
        'avatarUrl' => $avatarPath ? SITE_URL . '/' . $avatarPath : null,
        'joinedAt' => $row['created_at'],
    ],
]);
