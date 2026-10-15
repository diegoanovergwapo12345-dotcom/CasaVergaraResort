<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$userId = (int) ($body['userId'] ?? 0);
$action = input_str($body, 'action');
$newRole = input_str($body, 'role');

if ($userId <= 0) {
    json_response(false, 'A valid user is required.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ?');
$stmt->execute([$userId]);
$target = $stmt->fetch();

if (!$target) {
    json_response(false, 'That account could not be found.', [], 404);
}

$currentAdminId = (int) $_SESSION['user_id'];

if ($action === 'suspend') {
    if ($userId === $currentAdminId) {
        json_response(false, 'You cannot suspend your own account.', [], 422);
    }
    if ($target['role'] === 'admin') {
        $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
        if ($activeAdmins <= 1) {
            json_response(false, 'At least one active admin account must remain.', [], 422);
        }
    }
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['suspended', $userId]);
    json_response(true, 'Account suspended.');
} elseif ($action === 'activate') {
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute(['active', $userId]);
    json_response(true, 'Account reactivated.');
} elseif ($action === 'setRole') {
    if (!in_array($newRole, ['customer', 'admin', 'event_manager'], true)) {
        json_response(false, 'Invalid role.', [], 422);
    }
    if ($userId === $currentAdminId && $newRole !== 'admin') {
        json_response(false, 'You cannot change your own role.', [], 422);
    }
    if ($target['role'] === 'admin' && $newRole !== 'admin') {
        $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
        if ($activeAdmins <= 1) {
            json_response(false, 'At least one active admin account must remain.', [], 422);
        }
    }
    $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
    json_response(true, 'Role updated.');
} else {
    json_response(false, 'Unknown action.', [], 422);
}
