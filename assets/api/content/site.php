<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$pdo = get_db_connection();
$stmt = $pdo->query('SELECT content_key, content_type, text_value, image_path FROM site_content');
$rows = $stmt->fetchAll();

$content = [];
foreach ($rows as $row) {
    if ($row['content_type'] === 'image') {
        $content[$row['content_key']] = $row['image_path'] ? (SITE_URL . '/' . $row['image_path']) : null;
    } else {
        $content[$row['content_key']] = $row['text_value'];
    }
}

json_response(true, 'Site content loaded.', ['content' => $content]);
