<?php

require_once __DIR__ . '/../../config/config.php';

$pdo = get_db_connection();

$accounts = [];
foreach (cvr_remember_lookup($pdo) as $row) {
    $accounts[] = [
        'email' => $row['email'],
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'avatarUrl' => !empty($row['avatar_path']) ? SITE_URL . '/' . $row['avatar_path'] : null,
        'role' => $row['role'],
    ];
}

json_response(true, 'Saved accounts on this browser.', [
    'accounts' => $accounts,
    'available' => cvr_remember_available($pdo),
]);
