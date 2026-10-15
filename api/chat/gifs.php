<?php
// GIF search proxy (keeps the Giphy API key on the server).

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/live_chat.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_login();
session_write_close();

if (!defined('CVR_GIPHY_API_KEY') || trim((string) CVR_GIPHY_API_KEY) === '') {
    json_response(false, 'GIF search isn\'t set up yet.', ['code' => 'not_configured']);
}

$q = trim((string) ($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 60);
$offset = max(0, min(200, (int) ($_GET['offset'] ?? 0)));
$limit = 24;

$params = [
    'api_key' => CVR_GIPHY_API_KEY,
    'limit' => $limit,
    'offset' => $offset,
    'rating' => 'g',
];
if ($q !== '') {
    $params['q'] = $q;
    $params['lang'] = 'en';
}
$url = 'https://api.giphy.com/v1/gifs/' . ($q !== '' ? 'search' : 'trending') . '?' . http_build_query($params);

$raw = false;
if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);
} else {
    $raw = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8]]));
}

$decoded = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($decoded) || !isset($decoded['data']) || !is_array($decoded['data'])) {
    json_response(false, 'Could not load GIFs right now.', ['code' => 'unavailable']);
}

$items = [];
foreach ($decoded['data'] as $gif) {
    $images = $gif['images'] ?? [];
    $full = $images['downsized']['url'] ?? ($images['original']['url'] ?? '');
    $preview = $images['fixed_width_small']['url'] ?? ($images['fixed_width']['url'] ?? $full);
    if (!cvr_chat_valid_gif_url((string) $full) || !cvr_chat_valid_gif_url((string) $preview)) {
        continue;
    }
    $items[] = [
        'id' => (string) ($gif['id'] ?? ''),
        'title' => mb_substr((string) ($gif['title'] ?? 'GIF'), 0, 120),
        'preview' => $preview,
        'url' => $full,
    ];
}

json_response(true, 'OK', [
    'gifs' => $items,
    'nextOffset' => $offset + count($decoded['data']),
    'hasMore' => count($decoded['data']) >= $limit,
]);
