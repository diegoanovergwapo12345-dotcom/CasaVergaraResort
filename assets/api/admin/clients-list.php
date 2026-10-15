<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();
$search = trim((string) ($_GET['search'] ?? ''));

$sql = "SELECT id, first_name, last_name, email, phone
        FROM users
        WHERE role = 'customer' AND status = 'active'";
$params = [];

if ($search !== '') {
    $sql .= ' AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}

$sql .= ' ORDER BY first_name ASC, last_name ASC LIMIT 300';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$clients = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'email' => $row['email'],
        'phone' => $row['phone'],
    ];
}, $stmt->fetchAll());

json_response(true, 'OK', ['clients' => $clients]);
