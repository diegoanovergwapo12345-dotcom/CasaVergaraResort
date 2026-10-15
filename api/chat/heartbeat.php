<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);
$pdo = get_db_connection();
cvr_chat_touch_presence($pdo, (int) $session['user_id'], true);

json_response(true, 'OK', cvr_chat_presence_snapshot($pdo));
