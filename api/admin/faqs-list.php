<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();
$stmt = $pdo->query('SELECT id, question, answer, category, sort_order, is_active FROM faqs ORDER BY category ASC, sort_order ASC, id ASC');
$rows = $stmt->fetchAll();

$faqs = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'question' => $row['question'],
        'answer' => $row['answer'],
        'category' => $row['category'] ?: 'General',
        'sortOrder' => (int) $row['sort_order'],
        'isActive' => (bool) $row['is_active'],
    ];
}, $rows);

json_response(true, 'OK', ['faqs' => $faqs]);
