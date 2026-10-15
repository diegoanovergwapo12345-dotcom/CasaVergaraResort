<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();

$id        = (int) ($body['id'] ?? 0);
$firstName = input_str($body, 'firstName');
$lastName  = input_str($body, 'lastName');
$email     = strtolower(input_str($body, 'email'));
$phone     = input_str($body, 'phone');
$password  = (string) ($body['password'] ?? '');
$role      = input_str($body, 'role', 'customer');
$status    = input_str($body, 'status', 'active');

$errors = [];
if ($firstName === '') $errors[] = 'First name is required.';
if ($lastName === '')  $errors[] = 'Last name is required.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if ($phone === '' || !is_valid_phone($phone)) $errors[] = 'A valid contact number is required.';
if (!in_array($role, ['customer', 'admin', 'event_manager'], true)) $errors[] = 'Invalid role.';
if (!in_array($status, ['active', 'suspended'], true)) $errors[] = 'Invalid status.';
if ($id <= 0 && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
if ($id > 0 && $password !== '' && strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();
$currentAdminId = (int) $_SESSION['user_id'];

$dupStmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
$dupStmt->execute([$email, $id]);
if ($dupStmt->fetch()) {
    json_response(false, 'Another account already uses this email address.', [], 409);
}

if ($id > 0) {

    $stmt = $pdo->prepare('SELECT id, role, status FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $target = $stmt->fetch();
    if (!$target) {
        json_response(false, 'That account could not be found.', [], 404);
    }

    if ($id === $currentAdminId && $role !== 'admin') {
        json_response(false, 'You cannot change your own role.', [], 422);
    }
    if ($id === $currentAdminId && $status !== 'active') {
        json_response(false, 'You cannot suspend your own account.', [], 422);
    }

    if ($target['role'] === 'admin' && ($role !== 'admin' || $status !== 'active')) {
        $activeAdmins = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
        if ($activeAdmins <= 1) {
            json_response(false, 'At least one active admin account must remain.', [], 422);
        }
    }

    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare(
            'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=?, password_hash=? WHERE id=?'
        );
        $update->execute([$firstName, $lastName, $email, $phone, $role, $status, $passwordHash, $id]);
    } else {
        $update = $pdo->prepare(
            'UPDATE users SET first_name=?, last_name=?, email=?, phone=?, role=?, status=? WHERE id=?'
        );
        $update->execute([$firstName, $lastName, $email, $phone, $role, $status, $id]);
    }

    json_response(true, 'Account updated.', ['id' => $id]);
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$insert = $pdo->prepare(
    'INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_verified, status) VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
);
$insert->execute([$firstName, $lastName, $email, $phone, $passwordHash, $role, $status]);

json_response(true, 'Account created.', ['id' => (int) $pdo->lastInsertId()], 201);
