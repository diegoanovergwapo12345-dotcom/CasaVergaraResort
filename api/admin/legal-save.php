<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$allowedTypes = ['room', 'event', 'general', 'privacy'];
$body = get_json_body();
$type = strtolower(input_str($body, 'bookingType'));
$version = input_str($body, 'version');
$content = (string) ($body['content'] ?? '');

if (!in_array($type, $allowedTypes, true)) {
    json_response(false, 'Invalid document type.', [], 422);
}
if ($version === '' || !preg_match('/^\d+(\.\d+)*$/', $version)) {
    json_response(false, 'Version must look like "1.0" or "2.3".', [], 422);
}
if (trim($content) === '') {
    json_response(false, 'Content cannot be empty.', [], 422);
}

$docMeta = [
    'privacy' => ['label' => 'Privacy Policy', 'url' => 'privacy.html'],
    'room'    => ['label' => 'Terms & Conditions — Room Bookings', 'url' => 'terms.html?type=room'],
    'event'   => ['label' => 'Terms & Conditions — Event Bookings', 'url' => 'terms.html?type=event'],
    'general' => ['label' => 'Terms & Conditions', 'url' => 'terms.html'],
];

$pdo = get_db_connection();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE terms_and_conditions SET is_active = 0 WHERE booking_type = ?')->execute([$type]);
    $insert = $pdo->prepare('INSERT INTO terms_and_conditions (booking_type, version, content, is_active) VALUES (?, ?, ?, 1)');
    $insert->execute([$type, $version, $content]);

    $meta = $docMeta[$type] ?? ['label' => ucfirst($type), 'url' => 'terms.html'];
    $notifTitle = $meta['label'] . ' updated';
    $notifMessage = "We've published v{$version} of our {$meta['label']}. Please review the changes.";
    $pdo->prepare(
        "INSERT INTO notifications (user_id, audience, title, message, link)
         SELECT id, 'customer', ?, ?, ? FROM users WHERE role = 'customer'"
    )->execute([$notifTitle, $notifMessage, $meta['url']]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    json_response(false, 'Could not save this document. Please try again.', [], 500);
}

json_response(true, 'Published. This is now the live version shown on the website.', [
    'bookingType' => $type,
    'version' => $version,
]);
