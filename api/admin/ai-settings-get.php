<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

$row = $pdo->query('SELECT display_name, tagline, greeting, persona_instructions, updated_at FROM ai_assistant_settings WHERE id = 1')->fetch();

if (!$row) {

    $defaultGreeting = "Hi! I'm C.A.S.A. — your Client Assistance & Service Associate. Ask me anything about rooms, rates, bookings or payments.";
    $stmt = $pdo->prepare('INSERT INTO ai_assistant_settings (id, display_name, tagline, greeting) VALUES (1, ?, ?, ?)');
    $stmt->execute(['C.A.S.A.', 'Client Assistance & Service Associate', $defaultGreeting]);
    $row = $pdo->query('SELECT display_name, tagline, greeting, persona_instructions, updated_at FROM ai_assistant_settings WHERE id = 1')->fetch();
}

json_response(true, 'OK', [
    'settings' => [
        'displayName' => $row['display_name'],
        'tagline' => $row['tagline'],
        'greeting' => $row['greeting'],
        'personaInstructions' => $row['persona_instructions'] ?? '',
        'updatedAt' => $row['updated_at'],
    ],
]);
