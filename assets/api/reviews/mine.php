<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT id, rating, comment, location, review_type, reservation_id, booking_label, created_at
     FROM reviews WHERE user_id = ? ORDER BY created_at DESC, id DESC'
);
$stmt->execute([$session['user_id']]);
$rows = $stmt->fetchAll();

$reviews = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'rating' => (float) $row['rating'],
        'comment' => $row['comment'],
        'location' => $row['location'],
        'reviewType' => $row['review_type'] ?? 'general',
        'reservationId' => $row['reservation_id'],
        'bookingLabel' => $row['booking_label'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

json_response(true, 'OK', ['reviews' => $reviews]);
