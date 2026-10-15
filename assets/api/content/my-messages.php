<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$email = strtolower((string) $session['email']);

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    'SELECT id, subject, message, admin_reply, replied_at, customer_read, created_at
     FROM contact_messages
     WHERE LOWER(email) = ? AND admin_reply IS NOT NULL AND customer_deleted = 0
     ORDER BY replied_at DESC'
);
$stmt->execute([$email]);
$rows = $stmt->fetchAll();

$messages = array_map(static function ($row) {
    return [
        'id'          => (int) $row['id'],
        'subject'     => $row['subject'],
        'message'     => $row['message'],
        'adminReply'  => $row['admin_reply'],
        'repliedAt'   => $row['replied_at'],
        'createdAt'   => $row['created_at'],
        'read'        => (bool) $row['customer_read'],
    ];
}, $rows);

$unreadCount = count(array_filter($messages, static function ($m) {
    return !$m['read'];
}));

json_response(true, 'OK', [
    'messages'    => $messages,
    'unreadCount' => $unreadCount,
]);
