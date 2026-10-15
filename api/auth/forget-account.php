<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));

if ($email === '') {
    json_response(false, 'Which saved account should we remove?', [], 422);
}

$pdo = get_db_connection();
cvr_remember_forget($pdo, $email);

json_response(true, 'Removed from this browser.');
