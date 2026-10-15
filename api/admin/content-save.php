<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$key = input_str($body, 'key');
$textValue = isset($body['textValue']) ? (string) $body['textValue'] : '';

if ($key === '') {
    json_response(false, 'Missing content key.', [], 422);
}

$pdo = get_db_connection();

$existing = $pdo->prepare('SELECT id, content_type FROM site_content WHERE content_key = ?');
$existing->execute([$key]);
$row = $existing->fetch();

if (!$row) {
    json_response(false, 'That content field could not be found.', [], 404);
}

if ($row['content_type'] === 'image') {
    json_response(false, 'This is a photo field — upload a new photo instead of saving text.', [], 422);
}

if (mb_strlen($textValue) > 20000) {
    json_response(false, 'That text is too long.', [], 422);
}

$stmt = $pdo->prepare('UPDATE site_content SET text_value = ? WHERE content_key = ?');
$stmt->execute([$textValue, $key]);

json_response(true, 'Content updated.', ['key' => $key, 'textValue' => $textValue]);
