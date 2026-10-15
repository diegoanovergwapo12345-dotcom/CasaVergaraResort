<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

// Called when a tab closes (navigator.sendBeacon). Staff go "away"; guests go offline.
$session = require_login();
$pdo = get_db_connection();

if (cvr_chat_is_staff_role($session['role'] ?? '')) {
    cvr_chat_touch_presence($pdo, (int) $session['user_id'], false);
} else {
    cvr_chat_touch_customer_presence($pdo, (int) $session['user_id'], false);
}

json_response(true, 'OK');
