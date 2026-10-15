<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$body = get_json_body();
$type = input_str($body, 'type');
$id   = (int) ($body['id'] ?? 0);

if (!in_array($type, ['room', 'event'], true)) {
    json_response(false, 'Package type must be "room" or "event".', [], 422);
}

$pdo = get_db_connection();

function cv_slugify(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');
    return $slug !== '' ? $slug : 'item-' . substr(md5((string) mt_rand()), 0, 6);
}

if ($type === 'room') {
    $title = input_str($body, 'title');
    if ($title === '') {
        json_response(false, 'Room title is required.', [], 422);
    }
    $slug = input_str($body, 'slug') ?: cv_slugify($title);
    $tagline = input_str($body, 'tagline');
    $description = input_str($body, 'description');
    $pricePerNight = (float) ($body['pricePerNight'] ?? 0);
    $capacityMin = max(1, (int) ($body['capacityMin'] ?? 1));
    $capacityMax = max($capacityMin, (int) ($body['capacityMax'] ?? $capacityMin));
    $areaSqm = isset($body['areaSqm']) && $body['areaSqm'] !== '' ? (int) $body['areaSqm'] : null;
    $bed = input_str($body, 'bed');
    $inclusions = is_array($body['inclusions'] ?? null) ? array_values(array_filter(array_map('trim', $body['inclusions']))) : [];
    $addOns = is_array($body['addOns'] ?? null) ? $body['addOns'] : [];
    $image = input_str($body, 'image', '') ?: '../images/booking-photo-1.png';
    $checkIn = input_str($body, 'standardCheckIn', '15:00');
    $checkOut = input_str($body, 'standardCheckOut', 'Anytime (flexible)');
    $availabilityNote = input_str($body, 'availabilityNote');
    $sortOrder = (int) ($body['sortOrder'] ?? 0);
    $isActive = !empty($body['isActive']) ? 1 : 0;

    if ($pricePerNight < 0) {
        json_response(false, 'Price per night cannot be negative.', [], 422);
    }

    $params = [
        $slug, $title, $tagline, $description, $pricePerNight, $capacityMin, $capacityMax,
        $areaSqm, $bed, json_encode($inclusions), json_encode($addOns), $image,
        $checkIn, $checkOut, $availabilityNote, $sortOrder, $isActive,
    ];

    if ($id > 0) {
        $exists = $pdo->prepare('SELECT id FROM room_packages WHERE id = ?');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            json_response(false, 'That room package could not be found.', [], 404);
        }
        $stmt = $pdo->prepare(
            'UPDATE room_packages SET slug=?, title=?, tagline=?, description=?, price_per_night=?, capacity_min=?, capacity_max=?,
             area_sqm=?, bed_setup=?, inclusions_json=?, addons_json=?, image_path=?, standard_check_in=?, standard_check_out=?,
             availability_note=?, sort_order=?, is_active=? WHERE id=?'
        );
        $stmt->execute([...$params, $id]);
        json_response(true, 'Room package updated.', ['id' => $id]);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO room_packages (slug, title, tagline, description, price_per_night, capacity_min, capacity_max,
         area_sqm, bed_setup, inclusions_json, addons_json, image_path, standard_check_in, standard_check_out,
         availability_note, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $stmt->execute($params);
    json_response(true, 'Room package added.', ['id' => (int) $pdo->lastInsertId()]);
}

$name = input_str($body, 'name');
if ($name === '') {
    json_response(false, 'Event package name is required.', [], 422);
}
$slug = input_str($body, 'slug') ?: cv_slugify($name);
$price = (float) ($body['price'] ?? 0);
$includedPax = max(0, (int) ($body['includedPax'] ?? 0));
$isCustom = !empty($body['isCustom']) ? 1 : 0;
$image = input_str($body, 'image');
$image = $image !== '' ? $image : null;
$inclusions = is_array($body['inclusions'] ?? null) ? array_values(array_filter(array_map('trim', $body['inclusions']))) : [];
$motifs = is_array($body['motifs'] ?? null) ? $body['motifs'] : [];
$addOns = is_array($body['addOns'] ?? null) ? $body['addOns'] : [];
$sortOrder = (int) ($body['sortOrder'] ?? 0);
$isActive = !empty($body['isActive']) ? 1 : 0;

if ($price < 0) {
    json_response(false, 'Price cannot be negative.', [], 422);
}

$params = [$slug, $name, $price, $includedPax, $isCustom, $image, json_encode($inclusions), json_encode($motifs), json_encode($addOns), $sortOrder, $isActive];

if ($id > 0) {
    $exists = $pdo->prepare('SELECT id FROM event_packages WHERE id = ?');
    $exists->execute([$id]);
    if (!$exists->fetch()) {
        json_response(false, 'That event package could not be found.', [], 404);
    }
    $stmt = $pdo->prepare(
        'UPDATE event_packages SET slug=?, name=?, price=?, included_pax=?, is_custom=?, image_path=?, inclusions_json=?, motifs_json=?, addons_json=?, sort_order=?, is_active=? WHERE id=?'
    );
    $stmt->execute([...$params, $id]);
    json_response(true, 'Event package updated.', ['id' => $id]);
}

$stmt = $pdo->prepare(
    'INSERT INTO event_packages (slug, name, price, included_pax, is_custom, image_path, inclusions_json, motifs_json, addons_json, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)'
);
$stmt->execute($params);
json_response(true, 'Event package added.', ['id' => (int) $pdo->lastInsertId()]);
