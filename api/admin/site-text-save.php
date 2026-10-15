<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_text.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin']);

$body = get_json_body();
$textKey = input_str($body, 'key');
$value = trim((string) ($body['value'] ?? ''));

if (!isset(SITE_TEXT_FIELDS[$textKey])) {
    json_response(false, 'Unknown text field.', [], 422);
}

if ($value === '') {
    json_response(false, 'This field can\'t be left blank. Use Reset to restore the default wording instead.', [], 422);
}

if (mb_strlen($value) > 5000) {
    json_response(false, 'That text is too long (5000 characters max).', [], 422);
}

$pdo = get_db_connection();
$adminId = (int) ($session['user_id'] ?? 0);

try {
    $stmt = $pdo->prepare(
        'INSERT INTO site_text (text_key, text_value, updated_by) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE text_value = VALUES(text_value), updated_by = VALUES(updated_by)'
    );
    $stmt->execute([$textKey, $value, $adminId]);
} catch (\Throwable $e) {
    error_log('site-text-save.php: could not save site_text row (run database/migration_site_text.sql): ' . $e->getMessage());
    json_response(false, 'Could not save this text. Please run database/migration_site_text.sql.', [], 500);
}

json_response(true, 'Text updated.', [
    'textKey' => $textKey,
    'value' => $value,
]);
