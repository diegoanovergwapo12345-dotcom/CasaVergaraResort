<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(false, 'Invalid payment submission.', [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT id, file_path FROM payment_proofs WHERE id = ?');
$stmt->execute([$id]);
$proof = $stmt->fetch();

if (!$proof) {
    json_response(false, 'That payment submission no longer exists.', [], 404);
}

try {
    $pdo->prepare('DELETE FROM payment_proofs WHERE id = ?')->execute([$id]);
} catch (\Throwable $e) {
    error_log('admin/payments-delete.php: delete failed: ' . $e->getMessage());
    json_response(false, 'Could not delete this payment submission.', [], 500);
}

$relative = (string) ($proof['file_path'] ?? '');
if ($relative !== '' && strpos($relative, '..') === false) {
    $absolute = __DIR__ . '/../../' . $relative;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

json_response(true, 'Payment submission deleted.', ['id' => $id, 'deleted' => true]);
