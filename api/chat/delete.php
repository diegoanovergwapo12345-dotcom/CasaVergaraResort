<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_login();
$body = get_json_body();

$messageId = (int) ($body['messageId'] ?? 0);
$scope = (string) ($body['scope'] ?? 'me');   // 'me' | 'everyone'

if ($messageId < 1) {
    json_response(false, 'Missing message.', [], 422);
}

$pdo = get_db_connection();
cvr_chat_ensure_tables($pdo);

$message = cvr_chat_delete_message($pdo, $session, $messageId, $scope);

json_response(true, $scope === 'everyone' ? 'Message deleted for everyone.' : 'Message deleted for you.', [
    'message' => $message,
    'scope' => $scope,
]);
