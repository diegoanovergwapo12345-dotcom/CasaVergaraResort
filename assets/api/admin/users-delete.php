<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$userId = (int) ($body['userId'] ?? 0);

if ($userId <= 0) {
    json_response(false, 'A valid user is required.', [], 422);
}

if ($userId === (int) $_SESSION['user_id']) {
    json_response(false, 'You cannot delete your own account.', [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch();
if (!$target) {
    json_response(false, 'That account could not be found.', [], 404);
}

if ($target['role'] === 'admin') {
    $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($activeAdmins <= 1) {
        json_response(false, 'At least one admin account must remain.', [], 422);
    }
}

$countStmt = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE user_id = ?');
$countStmt->execute([$userId]);
$bookingCount = (int) $countStmt->fetchColumn();

if ($bookingCount > 0) {
    json_response(false, 'This account has ' . $bookingCount . ' reservation record(s) and cannot be deleted. Suspend it instead to preserve reservation history.', [], 422);
}

$avatarStmt = $pdo->prepare('SELECT avatar_path FROM users WHERE id = ?');
$avatarStmt->execute([$userId]);
$avatarPath = $avatarStmt->fetchColumn();

$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);

if ($avatarPath) {
    $absolute = __DIR__ . '/../../' . $avatarPath;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

json_response(true, 'Account deleted.');
