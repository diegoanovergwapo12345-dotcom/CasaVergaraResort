<?php
// Account preview for the guest in a live chat conversation. Staff / admin only.

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);
$conversationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($conversationId < 1) {
    json_response(false, 'Missing conversation.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$stmt = $pdo->prepare(
    "SELECT c.id AS conversation_id, c.topic, c.reservation_id AS chat_reservation_id, c.created_at AS chat_started_at,
            u.id AS user_id, u.first_name, u.last_name, u.email, u.phone, u.avatar_path,
            u.is_verified, u.status AS account_status, u.created_at AS member_since"
    . CVR_CHAT_CUSTOMER_PRESENCE_SELECT . "
     FROM live_chat_conversations c
     JOIN users u ON u.id = c.customer_id" . CVR_CHAT_CUSTOMER_PRESENCE_JOIN . "
     WHERE c.id = ?"
);
$stmt->execute([$conversationId]);
$row = $stmt->fetch();
if (!$row) {
    json_response(false, 'Conversation not found.', [], 404);
}

// Chat stats
$stats = $pdo->prepare(
    "SELECT COUNT(*) AS total,
            SUM(sender_role = 'customer') AS from_guest,
            MIN(created_at) AS first_at
     FROM live_chat_messages
     WHERE conversation_id = ? AND deleted_for_all = 0"
);
$stats->execute([$conversationId]);
$chat = $stats->fetch() ?: [];

// Bookings (table may not exist on older installs)
$reservations = [];
$reservationCount = 0;
try {
    $where = 'user_id = ? OR LOWER(email) = LOWER(?)';
    $params = [(int) $row['user_id'], (string) $row['email']];

    $count = $pdo->prepare('SELECT COUNT(*) FROM reservations WHERE ' . $where);
    $count->execute($params);
    $reservationCount = (int) $count->fetchColumn();

    $list = $pdo->prepare(
        'SELECT id, booking_type, room_name, check_in, check_out, preferred_date,
                reservation_status, payment_status, total_numeric, currency
         FROM reservations
         WHERE ' . $where . '
         ORDER BY updated_at DESC
         LIMIT 5'
    );
    $list->execute($params);
    foreach ($list->fetchAll() as $r) {
        $reservations[] = [
            'id' => $r['id'],
            'type' => $r['booking_type'],
            'name' => $r['room_name'],
            'checkIn' => $r['check_in'],
            'checkOut' => $r['check_out'],
            'date' => $r['preferred_date'],
            'status' => $r['reservation_status'],
            'paymentStatus' => $r['payment_status'],
            'total' => $r['total_numeric'] !== null ? (float) $r['total_numeric'] : null,
            'currency' => $r['currency'] ?: 'PHP',
        ];
    }
} catch (\Throwable $e) {
    error_log('chat/profile.php reservations: ' . $e->getMessage());
}

$seenAgo = $row['presence_ago'] !== null ? max(0, (int) $row['presence_ago']) : null;
$online = $seenAgo !== null && !empty($row['presence_available']) && $seenAgo <= CVR_CHAT_PRESENCE_SECONDS;

json_response(true, 'OK', [
    'profile' => [
        'conversationId' => (int) $row['conversation_id'],
        'userId' => (int) $row['user_id'],
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'avatarUrl' => cvr_chat_avatar_url($row['avatar_path']),
        'verified' => (bool) $row['is_verified'],
        'accountStatus' => $row['account_status'],
        'memberSince' => $row['member_since'],
        'online' => $online,
        'seenAgo' => $seenAgo,
        'chat' => [
            'startedAt' => $row['chat_started_at'],
            'topic' => $row['topic'],
            'reservationId' => $row['chat_reservation_id'],
            'messages' => (int) ($chat['total'] ?? 0),
            'fromGuest' => (int) ($chat['from_guest'] ?? 0),
        ],
        'reservationCount' => $reservationCount,
        'reservations' => $reservations,
    ],
]);
