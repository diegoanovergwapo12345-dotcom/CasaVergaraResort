<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_images.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$slotKey = input_str($body, 'slot');

if (!isset(SITE_IMAGE_SLOTS[$slotKey])) {
    json_response(false, 'Unknown photo slot.', [], 422);
}

$pdo = get_db_connection();

try {
    $pdo->prepare('DELETE FROM site_images WHERE slot_key = ?')->execute([$slotKey]);
} catch (\Throwable $e) {
    error_log('site-image-reset.php: could not delete site_images row: ' . $e->getMessage());
    json_response(false, 'Could not reset this photo. Please run database/migration_site_images.sql.', [], 500);
}

json_response(true, 'Photo reset to default.', [
    'slotKey' => $slotKey,
    'imageUrl' => site_image_default_url($slotKey),
]);
