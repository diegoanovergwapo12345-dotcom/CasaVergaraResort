<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$pdo = get_db_connection();

$roomRows = $pdo->query(
    'SELECT id, slug, title, tagline, description, price_per_night, capacity_min, capacity_max,
            area_sqm, bed_setup, inclusions_json, addons_json, image_path,
            standard_check_in, standard_check_out, availability_note, sort_order, is_active
     FROM room_packages ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$eventRows = $pdo->query(
    'SELECT id, slug, name, price, included_pax, max_pax, extra_pax_rate, is_custom, image_path, inclusions_json, motifs_json, addons_json, sort_order, is_active
     FROM event_packages ORDER BY sort_order ASC, id ASC'
)->fetchAll();

$rooms = array_map(static function (array $r): array {
    return [
        'id' => (int) $r['id'],
        'slug' => $r['slug'],
        'title' => $r['title'],
        'tagline' => $r['tagline'],
        'description' => $r['description'],
        'pricePerNight' => (float) $r['price_per_night'],
        'capacityMin' => (int) $r['capacity_min'],
        'capacityMax' => (int) $r['capacity_max'],
        'areaSqm' => $r['area_sqm'] !== null ? (int) $r['area_sqm'] : null,
        'bed' => $r['bed_setup'],
        'inclusions' => json_decode((string) $r['inclusions_json'], true) ?: [],
        'addOns' => json_decode((string) $r['addons_json'], true) ?: [],
        'image' => $r['image_path'],
        'standardCheckIn' => $r['standard_check_in'],
        'standardCheckOut' => $r['standard_check_out'],
        'availabilityNote' => $r['availability_note'],
        'sortOrder' => (int) $r['sort_order'],
        'isActive' => (bool) $r['is_active'],
    ];
}, $roomRows);

$events = array_map(static function (array $e): array {
    return [
        'id' => (int) $e['id'],
        'slug' => $e['slug'],
        'name' => $e['name'],
        'price' => (float) $e['price'],
        'includedPax' => (int) $e['included_pax'],
        'maxPax' => $e['max_pax'] !== null ? (int) $e['max_pax'] : null,
        'extraPaxRate' => $e['extra_pax_rate'] !== null ? (float) $e['extra_pax_rate'] : null,
        'isCustom' => (bool) $e['is_custom'],
        'image' => $e['image_path'],
        'inclusions' => json_decode((string) $e['inclusions_json'], true) ?: [],
        'motifs' => json_decode((string) $e['motifs_json'], true) ?: [],
        'addOns' => json_decode((string) $e['addons_json'], true) ?: [],
        'sortOrder' => (int) $e['sort_order'],
        'isActive' => (bool) $e['is_active'],
    ];
}, $eventRows);

json_response(true, 'OK', ['rooms' => $rooms, 'events' => $events]);
