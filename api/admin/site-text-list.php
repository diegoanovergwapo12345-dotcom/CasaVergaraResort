<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_text.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

$overrides = [];
try {
    foreach ($pdo->query('SELECT text_key, text_value, updated_at FROM site_text') as $row) {
        $overrides[$row['text_key']] = $row;
    }
} catch (\Throwable $e) {
    error_log('site-text-list.php: site_text table unavailable: ' . $e->getMessage());
}

$fields = [];
foreach (SITE_TEXT_FIELDS as $key => $meta) {
    $override = $overrides[$key] ?? null;
    $fields[] = [
        'textKey' => $key,
        'label' => $meta['label'],
        'group' => $meta['group'],
        'page' => $meta['page'],
        'type' => $meta['type'],
        'default' => $meta['default'],
        'isCustom' => $override !== null,
        'value' => $override['text_value'] ?? $meta['default'],
        'updatedAt' => $override['updated_at'] ?? null,
    ];
}

json_response(true, 'OK', ['fields' => $fields]);
