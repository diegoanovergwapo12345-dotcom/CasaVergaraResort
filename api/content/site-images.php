<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/site_images.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

$pdo = get_db_connection();

json_response(true, 'OK', [
    'images' => get_site_images($pdo),
]);
