<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();

require_once __DIR__ . '/../../config/team_defaults.php';
ensure_team_members_ready($pdo);

try {
    $rows = $pdo->query(
        'SELECT id, name, role, email, photo_url, bio, facts, sort_order, is_visible, updated_at
         FROM team_members
         ORDER BY sort_order ASC, id ASC'
    )->fetchAll();
} catch (\Throwable $e) {
    json_response(false, 'Please run database/migration_team_members.sql in phpMyAdmin first.', [], 500);
}

$members = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'name' => $row['name'],
        'role' => $row['role'],
        'email' => (string) $row['email'],
        'photo' => $row['photo_url'],
        'bio' => (string) $row['bio'],
        'facts' => (string) $row['facts'],
        'sortOrder' => (int) $row['sort_order'],
        'visible' => (bool) $row['is_visible'],
        'updatedAt' => $row['updated_at'],
    ];
}, $rows);

json_response(true, 'OK', ['members' => $members]);
