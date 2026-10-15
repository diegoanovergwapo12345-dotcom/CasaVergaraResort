<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();
$stmt = $pdo->query(
    'SELECT id, name, email, phone, subject, recipient_role, origin, sender_role, message, emailed_admin, admin_reply, replied_at, created_at
     FROM contact_messages
     ORDER BY created_at DESC'
);
$rows = $stmt->fetchAll();

$messages = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'subject' => $row['subject'],
        'recipientRole' => $row['recipient_role'] ?? 'admin',
        'origin' => $row['origin'] ?? 'guest',
        'senderRole' => $row['sender_role'] ?? null,
        'message' => $row['message'],
        'emailedAdmin' => (bool) $row['emailed_admin'],
        'adminReply' => $row['admin_reply'],
        'repliedAt' => $row['replied_at'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

json_response(true, 'OK', ['messages' => $messages]);
