<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$allowedTypes = ['room', 'event', 'general', 'privacy'];
$type = strtolower(trim((string) ($_GET['type'] ?? '')));
if (!in_array($type, $allowedTypes, true)) {
    json_response(false, 'Invalid document type.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, version, content, created_at FROM terms_and_conditions WHERE booking_type = ? AND is_active = 1 ORDER BY created_at DESC, id DESC LIMIT 1');
$stmt->execute([$type]);
$row = $stmt->fetch();

json_response(true, 'OK', [
    'bookingType' => $type,
    'version' => $row['version'] ?? '1.0',
    'content' => $row['content'] ?? '',
    'effectiveDate' => $row['created_at'] ?? null,
    'exists' => (bool) $row,
]);
