<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

$search = trim((string) ($_GET['search'] ?? ''));
$role = trim((string) ($_GET['role'] ?? 'all'));
$status = trim((string) ($_GET['status'] ?? 'all'));

$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}

if (in_array($role, ['customer', 'admin', 'event_manager'], true)) {
    $where[] = 'role = ?';
    $params[] = $role;
}

if (in_array($status, ['active', 'suspended'], true)) {
    $where[] = 'status = ?';
    $params[] = $status;
}

$sql = 'SELECT id, first_name, last_name, email, phone, avatar_path, role, is_verified, status, created_at
        FROM users';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$counts = $pdo->query("SELECT
        SUM(role = 'customer') AS customers,
        SUM(role = 'admin') AS admins,
        SUM(role = 'event_manager') AS event_managers,
        SUM(status = 'active') AS active,
        SUM(status = 'suspended') AS suspended,
        COUNT(*) AS total
    FROM users")->fetch();

$users = array_map(function ($row) {
    return [
        'id' => (int) $row['id'],
        'firstName' => $row['first_name'],
        'lastName' => $row['last_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'avatarUrl' => $row['avatar_path'] ? SITE_URL . '/' . $row['avatar_path'] : null,
        'role' => $row['role'],
        'isVerified' => (bool) $row['is_verified'],
        'status' => $row['status'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

json_response(true, 'OK', [
    'users' => $users,
    'counts' => [
        'total' => (int) ($counts['total'] ?? 0),
        'customers' => (int) ($counts['customers'] ?? 0),
        'admins' => (int) ($counts['admins'] ?? 0),
        'eventManagers' => (int) ($counts['event_managers'] ?? 0),
        'active' => (int) ($counts['active'] ?? 0),
        'suspended' => (int) ($counts['suspended'] ?? 0),
    ],
    'serverTime' => date('c'),
]);
