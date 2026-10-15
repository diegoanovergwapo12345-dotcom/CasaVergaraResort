<?php
require_once __DIR__ . '/../../config/config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';

function redirect_to_login(string $status): void
{
    header('Location: ' . SITE_URL . '/pages/login.html?verify=' . urlencode($status));
    exit;
}

if ($token === '') {
    redirect_to_login('missing');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, is_verified, verification_expires, first_name, last_name, email FROM users WHERE verification_token = ?');
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    redirect_to_login('invalid');
}

if ((int) $user['is_verified'] === 1) {
    redirect_to_login('already');
}

if ($user['verification_expires'] && strtotime($user['verification_expires']) < time()) {
    redirect_to_login('expired');
}

$update = $pdo->prepare('UPDATE users SET is_verified = 1, verification_token = NULL, verification_expires = NULL WHERE id = ?');
$update->execute([$user['id']]);

try {
    $guestName = trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['email'];
    $pdo->prepare(
        "INSERT INTO notifications (user_id, audience, title, message, link)
         SELECT id, 'staff', ?, ?, ? FROM users WHERE role IN ('admin', 'event_manager')"
    )->execute([
        'New guest account',
        $guestName . ' (' . $user['email'] . ') just verified their account.',
        'users.html',
    ]);
} catch (\Throwable $e) {
    error_log('verify.php: failed to create admin/staff notification: ' . $e->getMessage());
}

redirect_to_login('success');
