<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$body = get_json_body();
$type = input_str($body, 'type');
$order = $body['order'] ?? null;

if (!in_array($type, ['room', 'event'], true)) {
    json_response(false, 'Package type must be "room" or "event".', [], 422);
}
if (!is_array($order) || !count($order)) {
    json_response(false, 'An order list is required.', [], 422);
}

$ids = array_map('intval', $order);
if (in_array(0, $ids, true) || count($ids) !== count(array_unique($ids))) {
    json_response(false, 'The order list must contain unique, valid package ids.', [], 422);
}

$table = $type === 'room' ? 'room_packages' : 'event_packages';
$pdo = get_db_connection();

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$check = $pdo->prepare("SELECT id FROM {$table} WHERE id IN ({$placeholders})");
$check->execute($ids);
$found = array_map('intval', array_column($check->fetchAll(), 'id'));
if (count($found) !== count($ids)) {
    json_response(false, 'One or more packages in that order no longer exist. Refresh and try again.', [], 409);
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("UPDATE {$table} SET sort_order = ? WHERE id = ?");
    foreach ($ids as $index => $id) {
        $stmt->execute([($index + 1) * 10, $id]);
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    error_log('packages-reorder.php: ' . $e->getMessage());
    json_response(false, 'Could not save the new order. Please try again.', [], 500);
}

json_response(true, 'Order updated.');
