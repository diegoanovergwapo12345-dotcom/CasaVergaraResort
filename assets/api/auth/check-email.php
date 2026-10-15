<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$body  = get_json_body();
$email = strtolower(input_str($body, 'email'));

$reason = null;
$suggestion = null;
if (!validate_email_deliverability($email, $reason, $suggestion)) {
    json_response(false, $reason, ['deliverable' => false, 'suggestion' => $suggestion], 200);
}

json_response(true, 'Email looks good.', ['deliverable' => true], 200);
