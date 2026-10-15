<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_text.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$textKey = input_str($body, 'key');

if (!isset(SITE_TEXT_FIELDS[$textKey])) {
    json_response(false, 'Unknown text field.', [], 422);
}

$pdo = get_db_connection();

try {
    $pdo->prepare('DELETE FROM site_text WHERE text_key = ?')->execute([$textKey]);
} catch (\Throwable $e) {
    error_log('site-text-reset.php: could not delete site_text row: ' . $e->getMessage());
    json_response(false, 'Could not reset this field. Please run database/migration_site_text.sql.', [], 500);
}

json_response(true, 'Text reset to default.', [
    'textKey' => $textKey,
    'value' => site_text_default($textKey),
]);
