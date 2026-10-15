<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$displayName = input_str($body, 'displayName');
$tagline = input_str($body, 'tagline');
$greeting = input_str($body, 'greeting');
$personaInstructions = input_str($body, 'personaInstructions');

$errors = [];
if ($displayName === '') $errors[] = 'Assistant name is required.';
if (mb_strlen($displayName) > 50) $errors[] = 'Assistant name must be 50 characters or fewer.';
if (mb_strlen($tagline) > 150) $errors[] = 'Tagline must be 150 characters or fewer.';
if ($greeting === '') $errors[] = 'Greeting message is required.';
if (mb_strlen($greeting) > 500) $errors[] = 'Greeting message must be 500 characters or fewer.';
if (mb_strlen($personaInstructions) > 4000) $errors[] = 'Extra instructions must be 4000 characters or fewer.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare(
    'INSERT INTO ai_assistant_settings (id, display_name, tagline, greeting, persona_instructions) VALUES (1, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), tagline = VALUES(tagline), greeting = VALUES(greeting), persona_instructions = VALUES(persona_instructions)'
);
$stmt->execute([$displayName, $tagline, $greeting, $personaInstructions === '' ? null : $personaInstructions]);

json_response(true, 'Assistant settings saved.', [
    'settings' => [
        'displayName' => $displayName,
        'tagline' => $tagline,
        'greeting' => $greeting,
        'personaInstructions' => $personaInstructions,
    ],
]);
