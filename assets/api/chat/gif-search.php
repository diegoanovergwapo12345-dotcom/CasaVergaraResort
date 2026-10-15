<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();
session_write_close();

$apiKey = defined('CVR_GIPHY_API_KEY') ? trim((string) CVR_GIPHY_API_KEY) : '';
if ($apiKey === '') {
    json_response(true, 'GIF search is not set up.', ['configured' => false, 'results' => []]);
}

$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 60) {
    $q = mb_substr($q, 0, 60);
}

$endpoint = $q === '' ? 'trending' : 'search';
$params = [
    'api_key' => $apiKey,
    'limit' => 24,
    'rating' => 'g',
];
if ($q !== '') {
    $params['q'] = $q;
    $params['lang'] = 'en';
}
$url = 'https://api.giphy.com/v1/gifs/' . $endpoint . '?' . http_build_query($params);

$raw = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
} elseif (ini_get('allow_url_fopen')) {
    $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
}

$data = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($data) || !isset($data['data']) || !is_array($data['data'])) {
    json_response(false, 'Could not reach the GIF service right now.', ['configured' => true, 'results' => []], 502);
}

$strip = static function (?string $u): string {
    $u = (string) $u;
    $pos = strpos($u, '?');
    return $pos === false ? $u : substr($u, 0, $pos);
};

$results = [];
foreach ($data['data'] as $item) {
    $send = $strip($item['images']['fixed_width']['url'] ?? '');
    $preview = $strip($item['images']['fixed_width_small']['url'] ?? ($item['images']['fixed_width']['url'] ?? ''));
    if ($send === '' || cvr_chat_normalize_image_url($send) === null) {
        continue;
    }
    $results[] = [
        'id' => (string) ($item['id'] ?? ''),
        'title' => (string) ($item['title'] ?? ''),
        'previewUrl' => $preview !== '' ? $preview : $send,
        'url' => $send,
    ];
}

json_response(true, 'OK', ['configured' => true, 'results' => $results]);
