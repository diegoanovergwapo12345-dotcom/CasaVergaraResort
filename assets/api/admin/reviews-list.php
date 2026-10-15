<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();
$stmt = $pdo->query(
    "SELECT r.id, r.rating, r.comment, r.location, r.status, r.review_type, r.booking_label, r.created_at,
            u.first_name, u.last_name, u.email
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     ORDER BY r.created_at DESC"
);
$rows = $stmt->fetchAll();

$reviews = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'rating' => (float) $row['rating'],
        'comment' => $row['comment'],
        'location' => $row['location'],
        'status' => $row['status'],
        'reviewType' => $row['review_type'] ?? 'general',
        'bookingLabel' => $row['booking_label'],
        'guestName' => trim($row['first_name'] . ' ' . $row['last_name']),
        'guestEmail' => $row['email'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

json_response(true, 'OK', ['reviews' => $reviews]);
