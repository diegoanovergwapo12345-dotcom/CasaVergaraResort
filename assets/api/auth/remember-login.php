<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));

if ($email === '') {
    json_response(false, 'Which saved account should we sign in?', [], 422);
}

$pdo = get_db_connection();

if (!cvr_remember_available($pdo)) {
    json_response(false, 'Saved sign-in is not set up on this site yet. Please sign in with your password.', ['needsPassword' => true], 400);
}

$match = null;
foreach (cvr_remember_lookup($pdo) as $row) {
    if (strcasecmp((string) $row['email'], $email) === 0) {
        $match = $row;
        break;
    }
}

if (!$match) {
    json_response(false, 'This account is no longer saved on this browser. Please enter your password.', ['needsPassword' => true], 401);
}

if ($match['status'] === 'suspended') {
    cvr_remember_forget($pdo, $match['email']);
    json_response(false, 'This account has been suspended. Please contact Casa Vergara.', ['suspended' => true], 403);
}

if ((int) $match['is_verified'] !== 1) {
    json_response(false, 'Please verify your email address before signing in.', ['unverified' => true], 403);
}

session_regenerate_id(true);
$_SESSION['user_id']     = $match['user_id'];
$_SESSION['first_name']  = $match['first_name'];
$_SESSION['last_name']   = $match['last_name'];
$_SESSION['email']       = $match['email'];
$_SESSION['phone']       = $match['phone'];
$_SESSION['role']        = $match['role'];
$_SESSION['avatar_path'] = $match['avatar_path'];

try {
    $pdo->prepare('UPDATE users SET failed_login_attempts = 0, lockout_until = NULL WHERE id = ?')
        ->execute([$match['user_id']]);
} catch (\Throwable $e) {

}

cvr_remember_rotate($pdo, $match);

json_response(true, 'Welcome back, ' . $match['first_name'] . '!', [
    'user' => cvr_user_payload([
        'id' => $match['user_id'],
        'first_name' => $match['first_name'],
        'last_name' => $match['last_name'],
        'email' => $match['email'],
        'phone' => $match['phone'],
        'role' => $match['role'],
        'avatar_path' => $match['avatar_path'],
        'created_at' => $match['created_at'],
    ]),
]);
