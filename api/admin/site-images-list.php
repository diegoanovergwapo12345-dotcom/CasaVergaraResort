<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_images.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

$overrides = [];
try {
    foreach ($pdo->query('SELECT slot_key, image_path, updated_at FROM site_images') as $row) {
        $overrides[$row['slot_key']] = $row;
    }
} catch (\Throwable $e) {
    error_log('site-images-list.php: site_images table unavailable: ' . $e->getMessage());
}

$slots = [];
foreach (SITE_IMAGE_SLOTS as $slotKey => $meta) {
    $override = $overrides[$slotKey] ?? null;
    $slots[] = [
        'slotKey' => $slotKey,
        'label' => $meta['label'],
        'group' => $meta['group'],
        'page' => $meta['page'],
        'isCustom' => $override !== null,
        'imageUrl' => $override !== null
            ? (str_starts_with($override['image_path'], 'http') ? $override['image_path'] : SITE_URL . '/' . $override['image_path'])
            : site_image_default_url($slotKey),
        'updatedAt' => $override['updated_at'] ?? null,
    ];
}

json_response(true, 'OK', ['slots' => $slots]);
