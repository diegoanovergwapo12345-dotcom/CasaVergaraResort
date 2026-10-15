<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$pdo = get_db_connection();
try {
    $row = $pdo->query('SELECT display_name, tagline, greeting FROM ai_assistant_settings WHERE id = 1')->fetch();
} catch (\Throwable $e) {
    $row = null;
}

$defaults = [
    'displayName' => 'C.A.S.A.',
    'tagline' => 'Client Assistance & Service Associate',
    'greeting' => "Hi! I'm C.A.S.A. — your Client Assistance & Service Associate. Ask me anything about rooms, rates, bookings or payments.",
];

json_response(true, 'OK', [
    'settings' => $row ? [
        'displayName' => $row['display_name'],
        'tagline' => $row['tagline'],
        'greeting' => $row['greeting'],
    ] : $defaults,
]);
