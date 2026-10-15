<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();
$stmt = $pdo->query('SELECT id, content_key, content_type, section, label, text_value, image_path, sort_order, updated_at FROM site_content ORDER BY section ASC, sort_order ASC, id ASC');
$rows = $stmt->fetchAll();

$items = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'key' => $row['content_key'],
        'type' => $row['content_type'],
        'section' => $row['section'],
        'label' => $row['label'],
        'textValue' => $row['text_value'],
        'imagePath' => $row['image_path'],
        'imageUrl' => $row['image_path'] ? (SITE_URL . '/' . $row['image_path']) : null,
        'updatedAt' => $row['updated_at'],
    ];
}, $rows);

json_response(true, 'Site content loaded.', ['items' => $items]);
