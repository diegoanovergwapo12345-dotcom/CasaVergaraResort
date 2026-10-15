<?php
require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body = get_json_body();
$email = strtolower(input_str($body, 'email'));
$password = (string) ($body['password'] ?? '');

$remember = !empty($body['remember']);

if ($email === '' || $password === '') {
    json_response(false, 'Email and password are required.', [], 422);
}

$pdo = get_db_connection();

$lockoutColumnsAvailable = true;
try {
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, password_hash, avatar_path, role, is_verified, status, created_at, failed_login_attempts, lockout_until FROM users WHERE email = ?');
    $stmt->execute([$email]);
} catch (\Throwable $e) {
    $lockoutColumnsAvailable = false;
    $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, phone, password_hash, avatar_path, role, is_verified, status, created_at FROM users WHERE email = ?');
    $stmt->execute([$email]);
}
$user = $stmt->fetch();

if ($lockoutColumnsAvailable && $user && !empty($user['lockout_until']) && strtotime($user['lockout_until']) > time()) {
    $waitMinutes = max(1, (int) ceil((strtotime($user['lockout_until']) - time()) / 60));
    json_response(false,
        'Too many failed attempts. Please try again in ' . $waitMinutes . ' minute' . ($waitMinutes === 1 ? '' : 's') . ', or reset your password instead.',
        ['lockedOut' => true, 'retryAfterMinutes' => $waitMinutes, 'suggestReset' => true],
        429
    );
}

if (!$user || !password_verify($password, $user['password_hash'])) {

    if ($lockoutColumnsAvailable && $user) {
        $attempts = (int) $user['failed_login_attempts'] + 1;

        if ($attempts >= LOGIN_MAX_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
            $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = ? WHERE id = ?')
                ->execute([$lockUntil, $user['id']]);

            json_response(false,
                'Too many failed attempts. Your account is temporarily locked for ' . LOGIN_LOCKOUT_MINUTES . ' minutes. You can also reset your password below.',
                ['lockedOut' => true, 'retryAfterMinutes' => LOGIN_LOCKOUT_MINUTES, 'suggestReset' => true],
                429
            );
        }

        $pdo->prepare('UPDATE users SET failed_login_attempts = ? WHERE id = ?')->execute([$attempts, $user['id']]);

        if ($attempts >= LOGIN_MAX_ATTEMPTS - 1) {
            json_response(false, 'Incorrect email or password. One more failed attempt will temporarily lock this account — you can reset your password instead.', ['suggestReset' => true], 401);
        }
    }

    json_response(false, 'Incorrect email or password.', [], 401);
}

if ($lockoutColumnsAvailable && ((int) $user['failed_login_attempts'] !== 0 || !empty($user['lockout_until']))) {
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?')->execute([$user['id']]);
}

if ($user['status'] === 'suspended') {
    json_response(false, 'This account has been suspended. Please contact Casa Vergara.', ['suspended' => true], 403);
}

if (!$user['is_verified']) {
    json_response(false, 'Please verify your email address before signing in. Check your inbox for the 6-digit verification code.', ['unverified' => true], 403);
}

session_regenerate_id(true);
$_SESSION['user_id']    = $user['id'];
$_SESSION['first_name'] = $user['first_name'];
$_SESSION['last_name']  = $user['last_name'];
$_SESSION['email']      = $user['email'];
$_SESSION['phone']      = $user['phone'];
$_SESSION['role']       = $user['role'];
$_SESSION['avatar_path'] = $user['avatar_path'];

$pdo_remember_ok = false;
try {
    if ($remember) {
        cvr_remember_issue($pdo, (int) $user['id']);
    }
    $pdo_remember_ok = cvr_remember_available($pdo);
} catch (\Throwable $e) {

    error_log('login.php: remember-me failed: ' . $e->getMessage());
}

json_response(true, 'Welcome back, ' . $user['first_name'] . '!', [
    'user' => cvr_user_payload($user),
    'remembered' => $remember && $pdo_remember_ok,
]);
