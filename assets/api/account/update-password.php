<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();

$body = get_json_body();
$currentPassword = (string) ($body['currentPassword'] ?? '');
$newPassword     = (string) ($body['newPassword'] ?? '');
$confirmPassword = (string) ($body['confirmPassword'] ?? '');

$errors = [];
if ($currentPassword === '') $errors[] = 'Your current password is required.';
if (strlen($newPassword) < 6) $errors[] = 'New password must be at least 6 characters.';
if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';
if ($newPassword !== '' && $newPassword === $currentPassword) $errors[] = 'New password must be different from your current password.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || !password_verify($currentPassword, $user['password_hash'])) {
    json_response(false, 'Your current password is incorrect.', [], 401);
}

$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
$update->execute([$newHash, $_SESSION['user_id']]);

json_response(true, 'Your password has been updated.');
