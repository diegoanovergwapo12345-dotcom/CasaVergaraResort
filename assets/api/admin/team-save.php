<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);
$name = input_str($body, 'name');
$role = input_str($body, 'role');
$email = strtolower(trim(input_str($body, 'email')));
$photo = input_str($body, 'photo');
$bio = input_str($body, 'bio');
$facts = input_str($body, 'facts');
$sortOrder = (int) ($body['sortOrder'] ?? 0);
$visible = !empty($body['visible']) ? 1 : 0;

$errors = [];
if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Please enter a name (150 characters or fewer).';
if ($role === '' || mb_strlen($role) > 150) $errors[] = 'Please enter a role/title (150 characters or fewer).';
if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 190)) {
    $errors[] = 'Please enter a valid email address for the "Get in touch" button (or leave it blank).';
}
if ($photo !== '' && !preg_match('#^(https?://|\.\./|/|uploads/)#i', $photo)) {
    $errors[] = 'Photo must be a valid image URL or site path.';
}
if (mb_strlen($photo) > 500) $errors[] = 'Photo URL is too long.';
if (mb_strlen($bio) > 2000) $errors[] = 'Bio must be 2000 characters or fewer.';
if (mb_strlen($facts) > 1000) $errors[] = 'Highlights must be 1000 characters or fewer.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$factLines = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $facts))));
$facts = implode("\n", array_slice($factLines, 0, 6));

$pdo = get_db_connection();
$editorId = (int) ($session['user_id'] ?? 0) ?: null;

try {
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'UPDATE team_members
             SET name = ?, role = ?, email = ?, photo_url = ?, bio = ?, facts = ?, sort_order = ?, is_visible = ?, updated_by = ?
             WHERE id = ?'
        );
        $stmt->execute([$name, $role, $email, $photo, $bio, $facts, $sortOrder, $visible, $editorId, $id]);
        if ($stmt->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT id FROM team_members WHERE id = ?');
            $exists->execute([$id]);
            if (!$exists->fetchColumn()) {
                json_response(false, 'That team profile no longer exists.', [], 404);
            }
        }
    } else {
        if ($sortOrder === 0) {
            $sortOrder = (int) $pdo->query('SELECT COALESCE(MAX(sort_order), 0) + 1 FROM team_members')->fetchColumn();
        }
        $stmt = $pdo->prepare(
            'INSERT INTO team_members (name, role, email, photo_url, bio, facts, sort_order, is_visible, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$name, $role, $email, $photo, $bio, $facts, $sortOrder, $visible, $editorId]);
        $id = (int) $pdo->lastInsertId();
    }
} catch (\Throwable $e) {
    error_log('admin/team-save.php: ' . $e->getMessage());
    json_response(false, 'Could not save the profile. Has database/migration_team_members.sql been run?', [], 500);
}

json_response(true, 'Team profile saved.', ['id' => $id]);
