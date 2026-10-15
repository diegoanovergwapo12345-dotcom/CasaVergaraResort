<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$pdo = get_db_connection();

try {
    $rows = $pdo->query(
        "SELECT id, booking_type, package_id, room_name, check_in, check_out, preferred_date,
                duration_type, scheduled_check_in, scheduled_check_out
         FROM reservations
         WHERE reservation_status NOT LIKE '%cancel%'
           AND reservation_status NOT LIKE '%declin%'"
    )->fetchAll();
} catch (\Throwable $e) {
    error_log('occupied-dates.php: query failed (has database/migration_reservations.sql / migration_duration_pricing.sql been run?): ' . $e->getMessage());

    json_response(true, 'OK', ['reservations' => []]);
}

$out = array_map(static function ($row) {
    return [
        'id'                => $row['id'],
        'bookingType'       => $row['booking_type'],
        'packageId'         => $row['package_id'],
        'roomName'          => $row['room_name'],
        'checkIn'           => $row['check_in'],
        'checkOut'          => $row['check_out'],
        'preferredDate'     => $row['preferred_date'],
        'durationKey'       => $row['duration_type'] ?? null,
        'scheduledCheckIn'  => $row['scheduled_check_in'] ?? null,
        'scheduledCheckOut' => $row['scheduled_check_out'] ?? null,
    ];
}, $rows);

json_response(true, 'OK', ['reservations' => $out]);
