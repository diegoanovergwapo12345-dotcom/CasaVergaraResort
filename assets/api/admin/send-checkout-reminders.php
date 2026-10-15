<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/checkout_reminders.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();
$summary = run_checkout_reminders($pdo);

$message = $summary['checked'] === 0
    ? 'No upcoming check-outs needed a reminder right now.'
    : sprintf('Checked %d reservation(s): %d reminder(s) sent, %d failed.', $summary['checked'], $summary['sent'], $summary['failed']);

json_response(true, $message, ['summary' => $summary]);
