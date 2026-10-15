<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);
$question = input_str($body, 'question');
$answer = input_str($body, 'answer');
$category = input_str($body, 'category', 'General');
$sortOrder = (int) ($body['sortOrder'] ?? 0);
$isActive = !empty($body['isActive']) ? 1 : 0;

if ($question === '') {
    json_response(false, 'Question is required.', [], 422);
}
if (mb_strlen($question) > 255) {
    json_response(false, 'Question must be 255 characters or fewer.', [], 422);
}
if ($answer === '') {
    json_response(false, 'Answer is required.', [], 422);
}
if ($category === '') {
    $category = 'General';
}

$pdo = get_db_connection();

if ($id > 0) {
    $exists = $pdo->prepare('SELECT id FROM faqs WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) {
        json_response(false, 'That FAQ could not be found.', [], 404);
    }
    $stmt = $pdo->prepare(
        'UPDATE faqs SET question = ?, answer = ?, category = ?, sort_order = ?, is_active = ? WHERE id = ?'
    );
    $stmt->execute([$question, $answer, $category, $sortOrder, $isActive, $id]);
    json_response(true, 'FAQ updated.', ['id' => $id]);
}

$stmt = $pdo->prepare(
    'INSERT INTO faqs (question, answer, category, sort_order, is_active) VALUES (?, ?, ?, ?, ?)'
);
$stmt->execute([$question, $answer, $category, $sortOrder, $isActive]);

json_response(true, 'FAQ added.', ['id' => (int) $pdo->lastInsertId()]);
