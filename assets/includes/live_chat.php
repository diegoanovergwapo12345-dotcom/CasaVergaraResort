<?php

const CVR_CHAT_PRESENCE_SECONDS = 45;
const CVR_CHAT_MAX_UPLOAD_BYTES = 25 * 1024 * 1024; // 25 MB per file
const CVR_CHAT_UNAVAILABLE_COPY = "Send us a message for reservation inquiries and concerns. Our team will respond as soon as available.";

// GIFs picked from GIPHY are only accepted from GIPHY's own image hosts.
const CVR_CHAT_GIF_HOST_PATTERN = '/^(media[0-9]*|i)\.giphy\.com$/i';

const CVR_CHAT_TOPICS = [
    'room_availability' => 'Room availability',
    'event_bookings' => 'Event bookings',
    'reservation_details' => 'Reservation details',
    'requested_changes' => 'Requested changes',
    'payment_verification' => 'Payment verification',
    'other' => 'Other inquiry',
];

function cvr_chat_ensure_tables(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    $statements = [
        "CREATE TABLE IF NOT EXISTS live_chat_conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            reservation_id VARCHAR(40) DEFAULT NULL,
            topic VARCHAR(80) DEFAULT NULL,
            last_message_at DATETIME DEFAULT NULL,
            last_message_preview VARCHAR(255) DEFAULT NULL,
            last_sender_role ENUM('customer', 'staff') DEFAULT NULL,
            customer_unread INT NOT NULL DEFAULT 0,
            staff_unread INT NOT NULL DEFAULT 0,
            customer_cleared_id INT NOT NULL DEFAULT 0,
            status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_chat_customer (customer_id),
            INDEX idx_chat_last (last_message_at)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS live_chat_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            conversation_id INT NOT NULL,
            sender_id INT NOT NULL,
            sender_role ENUM('customer', 'staff') NOT NULL,
            sender_label VARCHAR(191) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            image_url VARCHAR(500) DEFAULT NULL,
            file_path VARCHAR(500) DEFAULT NULL,
            file_name VARCHAR(255) DEFAULT NULL,
            file_size INT UNSIGNED DEFAULT NULL,
            topic VARCHAR(80) DEFAULT NULL,
            reservation_id VARCHAR(40) DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_msg_conv (conversation_id, id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS live_chat_hidden_messages (
            message_id INT NOT NULL,
            user_id INT NOT NULL,
            PRIMARY KEY (message_id, user_id),
            INDEX idx_hidden_user (user_id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS live_chat_staff_hidden (
            conversation_id INT NOT NULL,
            user_id INT NOT NULL,
            hidden_before_id INT NOT NULL DEFAULT 0,
            PRIMARY KEY (conversation_id, user_id)
        ) ENGINE=InnoDB",
        "CREATE TABLE IF NOT EXISTS live_chat_presence (
            user_id INT PRIMARY KEY,
            last_seen_at DATETIME NOT NULL,
            is_available TINYINT(1) NOT NULL DEFAULT 1
        ) ENGINE=InnoDB",
    ];

    try {
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
        // Existing installs: add the column that holds a GIF / photo URL.
        $hasImageCol = $pdo->query("SHOW COLUMNS FROM live_chat_messages LIKE 'image_url'")->fetch();
        if (!$hasImageCol) {
            $pdo->exec('ALTER TABLE live_chat_messages ADD COLUMN image_url VARCHAR(500) DEFAULT NULL AFTER body');
        }
        foreach ([
            'file_path' => 'VARCHAR(500) DEFAULT NULL',
            'file_name' => 'VARCHAR(255) DEFAULT NULL',
            'file_size' => 'INT UNSIGNED DEFAULT NULL',
        ] as $col => $def) {
            $has = $pdo->query("SHOW COLUMNS FROM live_chat_messages LIKE '" . $col . "'")->fetch();
            if (!$has) {
                $pdo->exec('ALTER TABLE live_chat_messages ADD COLUMN ' . $col . ' ' . $def . ' AFTER image_url');
            }
        }
        $hasCleared = $pdo->query("SHOW COLUMNS FROM live_chat_conversations LIKE 'customer_cleared_id'")->fetch();
        if (!$hasCleared) {
            $pdo->exec('ALTER TABLE live_chat_conversations ADD COLUMN customer_cleared_id INT NOT NULL DEFAULT 0');
        }
        $hasReadAt = $pdo->query("SHOW COLUMNS FROM live_chat_messages LIKE 'read_at'")->fetch();
        if (!$hasReadAt) {
            $pdo->exec('ALTER TABLE live_chat_messages ADD COLUMN read_at DATETIME DEFAULT NULL');
            // Messages that already existed were read long ago; only new ones start as "Sent".
            $pdo->exec('UPDATE live_chat_messages SET read_at = created_at WHERE read_at IS NULL');
        }
        $hasDeleted = $pdo->query("SHOW COLUMNS FROM live_chat_messages LIKE 'deleted_at'")->fetch();
        if (!$hasDeleted) {
            $pdo->exec('ALTER TABLE live_chat_messages ADD COLUMN deleted_at DATETIME DEFAULT NULL');
        }
        $ready = true;
    } catch (\Throwable $e) {
        error_log('cvr_chat_ensure_tables: ' . $e->getMessage());
        throw $e;
    }
}

function cvr_chat_is_staff_role(?string $role): bool
{
    return in_array((string) $role, ['admin', 'event_manager'], true);
}

function cvr_chat_sender_label(array $session): string
{
    $first = trim((string) ($session['first_name'] ?? ''));
    $last = trim((string) ($session['last_name'] ?? ''));
    $name = trim($first . ' ' . $last);
    if ($name === '') {
        $name = (string) ($session['email'] ?? 'Guest');
    }

    return $name;
}

function cvr_chat_presence_snapshot(PDO $pdo): array
{
    cvr_chat_ensure_tables($pdo);

    $stmt = $pdo->prepare(
        "SELECT p.user_id, p.last_seen_at, u.first_name, u.last_name, u.role
         FROM live_chat_presence p
         JOIN users u ON u.id = p.user_id
         WHERE p.is_available = 1
           AND p.last_seen_at >= (NOW() - INTERVAL " . (int) CVR_CHAT_PRESENCE_SECONDS . " SECOND)
           AND u.role IN ('admin', 'event_manager')
           AND u.status = 'active'"
    );
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $agents = [];
    foreach ($rows as $row) {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $agents[] = [
            'id' => (int) $row['user_id'],
            'name' => $name !== '' ? $name : 'Resort team',
            'role' => 'team',
        ];
    }

    return [
        'staffOnline' => count($agents) > 0,
        'onlineCount' => count($agents),
        'agents' => $agents,
        'unavailableMessage' => CVR_CHAT_UNAVAILABLE_COPY,
    ];
}

function cvr_chat_touch_presence(PDO $pdo, int $userId, bool $available = true): void
{
    cvr_chat_ensure_tables($pdo);
    $stmt = $pdo->prepare(
        'INSERT INTO live_chat_presence (user_id, last_seen_at, is_available)
         VALUES (?, NOW(), ?)
         ON DUPLICATE KEY UPDATE last_seen_at = NOW(), is_available = VALUES(is_available)'
    );
    $stmt->execute([$userId, $available ? 1 : 0]);
}

/**
 * Which guests are online right now? Returns [customer_id => last_seen_at] for every
 * non-staff user whose browser pinged the chat within the presence window.
 */
function cvr_chat_online_customers(PDO $pdo): array
{
    cvr_chat_ensure_tables($pdo);
    $stmt = $pdo->query(
        "SELECT p.user_id, p.last_seen_at
         FROM live_chat_presence p
         JOIN users u ON u.id = p.user_id
         WHERE p.is_available = 1
           AND p.last_seen_at >= (NOW() - INTERVAL " . (int) CVR_CHAT_PRESENCE_SECONDS . " SECOND)
           AND u.role NOT IN ('admin', 'event_manager')"
    );
    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[(int) $row['user_id']] = $row['last_seen_at'];
    }
    return $out;
}

/** last_seen_at for one user (any role), or null if they never opened the chat. */
function cvr_chat_last_seen(PDO $pdo, int $userId): ?string
{
    $stmt = $pdo->prepare('SELECT last_seen_at FROM live_chat_presence WHERE user_id = ?');
    $stmt->execute([$userId]);
    $v = $stmt->fetchColumn();
    return $v ?: null;
}

function cvr_chat_find_conversation(PDO $pdo, int $customerId): ?array
{
    cvr_chat_ensure_tables($pdo);
    $stmt = $pdo->prepare('SELECT * FROM live_chat_conversations WHERE customer_id = ?');
    $stmt->execute([$customerId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cvr_chat_get_or_create_conversation(PDO $pdo, int $customerId): array
{
    $row = cvr_chat_find_conversation($pdo, $customerId);
    if ($row) {
        return $row;
    }

    try {
        $ins = $pdo->prepare('INSERT INTO live_chat_conversations (customer_id, status) VALUES (?, "open")');
        $ins->execute([$customerId]);
    } catch (\Throwable $e) {
        
    }
    $row = cvr_chat_find_conversation($pdo, $customerId);
    return $row ?: [];
}

/**
 * Validates an image/GIF reference sent by the browser and returns the value to store,
 * or null when it is not allowed. Accepted:
 *   - a file we saved ourselves via api/chat/upload.php (uploads/chat/...)
 *   - an https GIPHY image URL
 */
function cvr_chat_normalize_image_url(string $raw): ?string
{
    $raw = trim($raw);
    if ($raw === '' || strlen($raw) > 500) {
        return null;
    }

    if (preg_match('#^uploads/chat/chat_\d+_[a-f0-9]{12}\.(gif|jpe?g|png|webp)$#i', $raw)) {
        return is_file(__DIR__ . '/../' . $raw) ? $raw : null;
    }

    $parts = parse_url($raw);
    if (!$parts || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
        return null;
    }
    if (!preg_match(CVR_CHAT_GIF_HOST_PATTERN, $parts['host'])) {
        return null;
    }
    $path = $parts['path'] ?? '';
    if (!preg_match('#\.(gif|webp|png|jpe?g)$#i', $path)) {
        return null;
    }

    return $raw;
}

/**
 * Extensions that can run code on the server or in a browser. Everything else is accepted
 * (up to 25 MB) and is only ever served back as a forced download, never executed.
 */
function cvr_chat_blocked_extension(string $ext): bool
{
    return (bool) preg_match(
        '/^(php\d?|phtml|phar|pht|phps|cgi|pl|py|sh|bash|asp|aspx|jsp|exe|msi|dll|bat|cmd|com|scr|vbs|jar|htaccess|htpasswd|ini)$/i',
        $ext
    );
}

function cvr_chat_clean_file_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F<>:"\\\\|?*]+/u', '', $name);
    $name = trim((string) $name, ". \t");
    if ($name === '') {
        $name = 'file';
    }
    if (mb_strlen($name) > 120) {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 100) . ($ext !== '' ? '.' . $ext : '');
    }
    return $name;
}

/**
 * Validates a file reference coming back from upload.php. The stored name embeds the uploader's
 * user id, so one person cannot attach another person's upload.
 * Returns ['path' => ..., 'name' => ..., 'size' => ...] or null.
 */
function cvr_chat_normalize_file(string $rawPath, string $rawName, int $userId): ?array
{
    $rawPath = trim($rawPath);
    if (!preg_match('#^uploads/chat/files/chatf_(\d+)_[a-f0-9]{16}(\.[a-z0-9]{1,10})?$#i', $rawPath, $m)) {
        return null;
    }
    if ((int) $m[1] !== $userId) {
        return null;
    }
    $full = __DIR__ . '/../' . $rawPath;
    if (!is_file($full)) {
        return null;
    }
    return [
        'path' => $rawPath,
        'name' => cvr_chat_clean_file_name($rawName),
        'size' => (int) filesize($full),
    ];
}

function cvr_chat_file_url(array $row): ?string
{
    if (empty($row['file_path'])) {
        return null;
    }
    return SITE_URL . '/api/chat/file.php?id=' . (int) $row['id'];
}

function cvr_chat_public_image_url(?string $stored): ?string
{
    if (!$stored) {
        return null;
    }
    if (strpos($stored, 'uploads/chat/') === 0) {
        return SITE_URL . '/' . $stored;
    }
    return $stored;
}

function cvr_chat_format_message(array $row): array
{
    $unsent = !empty($row['deleted_at']);
    if ($unsent) {
        $row['body'] = '';
        $row['image_url'] = null;
        $row['file_path'] = null;
        $row['file_name'] = null;
        $row['file_size'] = null;
    }
    return [
        'deleted' => $unsent,
        'id' => (int) $row['id'],
        'conversationId' => (int) $row['conversation_id'],
        'senderId' => (int) $row['sender_id'],
        'senderRole' => $row['sender_role'] === 'staff' ? 'staff' : 'customer',
        'senderLabel' => $row['sender_label'] ?: ($row['sender_role'] === 'staff' ? 'Resort team' : 'Guest'),
        'body' => $row['body'],
        'imageUrl' => cvr_chat_public_image_url($row['image_url'] ?? null),
        'fileUrl' => cvr_chat_file_url($row),
        'fileName' => $row['file_name'] ?? null,
        'fileSize' => isset($row['file_size']) ? (int) $row['file_size'] : null,
        'avatarUrl' => !empty($row['sender_avatar_path']) ? SITE_URL . '/' . $row['sender_avatar_path'] : null,
        'topic' => $row['topic'] ?: null,
        'reservationId' => $row['reservation_id'] ?: null,
        'createdAt' => $row['created_at'],
        'readAt' => $row['read_at'] ?? null,
        'seen' => !empty($row['read_at']),
    ];
}

function cvr_chat_format_conversation(array $row, ?array $customer = null): array
{
    $customerName = '';
    if ($customer) {
        $customerName = trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''));
    }

    return [
        'id' => (int) $row['id'],
        'customerId' => (int) $row['customer_id'],
        'customerName' => $customerName,
        'customerEmail' => $customer['email'] ?? null,
        'customerAvatarUrl' => !empty($customer['avatar_path']) ? SITE_URL . '/' . $customer['avatar_path'] : null,
        'reservationId' => $row['reservation_id'] ?: null,
        'topic' => $row['topic'] ?: null,
        'lastMessageAt' => $row['last_message_at'],
        'lastMessagePreview' => $row['last_message_preview'],
        'lastSenderRole' => $row['last_sender_role'],
        'customerUnread' => (int) $row['customer_unread'],
        'staffUnread' => (int) $row['staff_unread'],
        'status' => $row['status'] ?: 'open',
        'createdAt' => $row['created_at'] ?? null,
    ];
}

function cvr_chat_fetch_messages(PDO $pdo, int $conversationId, int $afterId = 0, int $limit = 200, ?int $viewerId = null): array
{
    $sql = 'SELECT m.*, u.avatar_path AS sender_avatar_path
            FROM live_chat_messages m
            LEFT JOIN users u ON u.id = m.sender_id';
    $params = [];
    if ($viewerId) {
        // Hide messages this person chose to "Remove for you".
        $sql .= ' LEFT JOIN live_chat_hidden_messages hm ON hm.message_id = m.id AND hm.user_id = ?';
        $params[] = $viewerId;
    }
    $sql .= ' WHERE m.conversation_id = ?';
    $params[] = $conversationId;
    if ($afterId > 0) {
        $sql .= ' AND m.id > ?';
        $params[] = $afterId;
    }
    if ($viewerId) {
        $sql .= ' AND hm.message_id IS NULL';
    }
    $sql .= ' ORDER BY m.id ASC LIMIT ' . (int) $limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map('cvr_chat_format_message', $stmt->fetchAll());
}

/** Ids of messages that were unsent, so open browsers can update messages they already show. */
function cvr_chat_deleted_ids(PDO $pdo, int $conversationId, int $afterId = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT id FROM live_chat_messages
         WHERE conversation_id = ? AND deleted_at IS NOT NULL AND id > ?
         ORDER BY id DESC LIMIT 300'
    );
    $stmt->execute([$conversationId, $afterId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Deletes an uploaded attachment from disk (only files this chat created). */
function cvr_chat_unlink_attachment(?string $path): void
{
    $path = (string) $path;
    if ($path !== '' && preg_match('#^uploads/chat/(files/)?chat[f]?_\d+_[a-f0-9]+(\.[a-z0-9]{1,10})?$#i', $path)) {
        $full = __DIR__ . '/../' . $path;
        if (is_file($full)) {
            @unlink($full);
        }
    }
}

function cvr_chat_customer_owns_reservation(PDO $pdo, int $customerId, string $reservationId): bool
{
    $stmt = $pdo->prepare(
        'SELECT id FROM reservations WHERE id = ? AND user_id = ? LIMIT 1'
    );
    $stmt->execute([$reservationId, $customerId]);
    if ($stmt->fetchColumn()) {
        return true;
    }

    $emailStmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $emailStmt->execute([$customerId]);
    $email = strtolower((string) $emailStmt->fetchColumn());
    if ($email === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id FROM reservations WHERE id = ? AND LOWER(email) = ? LIMIT 1'
    );
    $stmt->execute([$reservationId, $email]);
    return (bool) $stmt->fetchColumn();
}

function cvr_chat_customer_reservations(PDO $pdo, int $customerId, string $email): array
{
    try {
        $stmt = $pdo->prepare(
            'SELECT id, room_name, booking_type, check_in, check_out, preferred_date, reservation_status
             FROM reservations
             WHERE user_id = ? OR LOWER(email) = LOWER(?)
             ORDER BY updated_at DESC
             LIMIT 40'
        );
        $stmt->execute([$customerId, $email]);
    } catch (\Throwable $e) {
        return [];
    }

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $label = $row['id'];
        if (!empty($row['room_name'])) {
            $label .= ' · ' . $row['room_name'];
        }
        $date = $row['booking_type'] === 'event' ? ($row['preferred_date'] ?: $row['check_in']) : $row['check_in'];
        if ($date) {
            $label .= ' · ' . $date;
        }
        $out[] = [
            'id' => $row['id'],
            'label' => $label,
            'status' => $row['reservation_status'] ?: '',
        ];
    }
    return $out;
}

function cvr_chat_post_message(
    PDO $pdo,
    array $conversation,
    array $session,
    string $body,
    ?string $topic,
    ?string $reservationId,
    ?string $imageUrl = null,
    ?array $file = null
): array {
    $senderRole = cvr_chat_is_staff_role($session['role'] ?? '') ? 'staff' : 'customer';
    $senderId = (int) $session['user_id'];
    $label = cvr_chat_sender_label($session);
    $preview = mb_substr(preg_replace('/\s+/', ' ', $body), 0, 180);
    if ($preview === '' && $imageUrl) {
        $preview = 'Sent a photo / GIF';
    }
    if ($preview === '' && $file) {
        $preview = 'Sent a file: ' . $file['name'];
    }
    $convId = (int) $conversation['id'];

    $stmt = $pdo->prepare(
        'INSERT INTO live_chat_messages
            (conversation_id, sender_id, sender_role, sender_label, body, image_url, file_path, file_name, file_size, topic, reservation_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $convId,
        $senderId,
        $senderRole,
        $label,
        $body,
        $imageUrl,
        $file['path'] ?? null,
        $file['name'] ?? null,
        $file['size'] ?? null,
        $topic,
        $reservationId,
    ]);
    $messageId = (int) $pdo->lastInsertId();

    if ($senderRole === 'customer') {
        $upd = $pdo->prepare(
            'UPDATE live_chat_conversations
             SET last_message_at = NOW(),
                 last_message_preview = ?,
                 last_sender_role = "customer",
                 staff_unread = staff_unread + 1,
                 reservation_id = COALESCE(?, reservation_id),
                 topic = COALESCE(?, topic),
                 status = "open"
             WHERE id = ?'
        );
        $upd->execute([$preview, $reservationId, $topic, $convId]);
        if ((int) ($conversation['staff_unread'] ?? 0) === 0) {
            cvr_chat_notify_staff($pdo, $convId, $label, $preview);
        }
    } else {
        $upd = $pdo->prepare(
            'UPDATE live_chat_conversations
             SET last_message_at = NOW(),
                 last_message_preview = ?,
                 last_sender_role = "staff",
                 customer_unread = customer_unread + 1,
                 status = "open"
             WHERE id = ?'
        );
        $upd->execute([$preview, $convId]);
        if ((int) ($conversation['customer_unread'] ?? 0) === 0) {
            cvr_chat_notify_customer($pdo, $conversation, $label, $preview);
        }
    }

    $msgStmt = $pdo->prepare(
        'SELECT m.*, u.avatar_path AS sender_avatar_path
         FROM live_chat_messages m
         LEFT JOIN users u ON u.id = m.sender_id
         WHERE m.id = ?'
    );
    $msgStmt->execute([$messageId]);
    return cvr_chat_format_message($msgStmt->fetch());
}

function cvr_chat_notify_staff(PDO $pdo, int $conversationId, string $guestName, string $preview): void
{
    try {
        $pdo->prepare(
            "INSERT INTO notifications (user_id, audience, title, message, link)
             SELECT id, 'staff', ?, ?, ?
             FROM users
             WHERE role IN ('admin', 'event_manager') AND status = 'active'"
        )->execute([
            'New live chat message',
            $guestName . ': ' . $preview,
            'live-chat.html?c=' . $conversationId,
        ]);
    } catch (\Throwable $e) {
        error_log('cvr_chat_notify_staff: ' . $e->getMessage());
    }
}

function cvr_chat_notify_customer(PDO $pdo, array $conversation, string $staffLabel, string $preview): void
{
    $customerId = (int) ($conversation['customer_id'] ?? 0);
    if ($customerId < 1) {
        return;
    }
    try {
        $pdo->prepare(
            "INSERT INTO notifications (user_id, audience, title, message, link)
             VALUES (?, 'customer', ?, ?, ?)"
        )->execute([
            $customerId,
            'Reply from resort staff',
            $staffLabel . ': ' . $preview,
            'customer-dashboard.html#live-chat',
        ]);
    } catch (\Throwable $e) {
        error_log('cvr_chat_notify_customer: ' . $e->getMessage());
    }
}

function cvr_chat_topics_payload(): array
{
    $items = [];
    foreach (CVR_CHAT_TOPICS as $id => $label) {
        $items[] = ['id' => $id, 'label' => $label];
    }
    return $items;
}


/**
 * Marks every message the OTHER side sent as read ("seen"). $readerRole is who just looked.
 */
function cvr_chat_mark_seen(PDO $pdo, int $conversationId, string $readerRole): void
{
    $senderRole = $readerRole === 'staff' ? 'customer' : 'staff';
    $pdo->prepare(
        'UPDATE live_chat_messages SET read_at = NOW()
         WHERE conversation_id = ? AND sender_role = ? AND read_at IS NULL'
    )->execute([$conversationId, $senderRole]);
}

/**
 * The newest message by $senderRole that the other side has seen, so a browser can tick off
 * every one of its own messages up to that id. Returns ['id' => int, 'at' => ?string].
 */
function cvr_chat_seen_state(PDO $pdo, int $conversationId, string $senderRole): array
{
    $stmt = $pdo->prepare(
        'SELECT MAX(id) AS id, MAX(read_at) AS at FROM live_chat_messages
         WHERE conversation_id = ? AND sender_role = ? AND read_at IS NOT NULL'
    );
    $stmt->execute([$conversationId, $senderRole]);
    $row = $stmt->fetch();
    return ['id' => (int) ($row['id'] ?? 0), 'at' => $row['at'] ?? null];
}

/** Permanently removes a conversation, its messages and its uploaded files ("delete for everyone"). */
function cvr_chat_delete_conversation(PDO $pdo, int $conversationId): void
{
    $stmt = $pdo->prepare('SELECT image_url, file_path FROM live_chat_messages WHERE conversation_id = ?');
    $stmt->execute([$conversationId]);
    foreach ($stmt->fetchAll() as $row) {
        foreach (['image_url', 'file_path'] as $col) {
            $path = (string) ($row[$col] ?? '');
            if ($path !== '' && preg_match('#^uploads/chat/(files/)?chat[f]?_\d+_[a-f0-9]+(\.[a-z0-9]{1,10})?$#i', $path)) {
                $full = __DIR__ . '/../' . $path;
                if (is_file($full)) {
                    @unlink($full);
                }
            }
        }
    }
    $pdo->prepare('DELETE FROM live_chat_staff_hidden WHERE conversation_id = ?')->execute([$conversationId]);
    $pdo->prepare('DELETE FROM live_chat_messages WHERE conversation_id = ?')->execute([$conversationId]);
    $pdo->prepare('DELETE FROM live_chat_conversations WHERE id = ?')->execute([$conversationId]);
}


/** Messages up to this id were "deleted for me" by this staff member (0 = nothing hidden). */
function cvr_chat_staff_hidden_before(PDO $pdo, int $conversationId, int $userId): int
{
    $stmt = $pdo->prepare('SELECT hidden_before_id FROM live_chat_staff_hidden WHERE conversation_id = ? AND user_id = ?');
    $stmt->execute([$conversationId, $userId]);
    return (int) $stmt->fetchColumn();
}

/** "Delete for me" for one staff member: hides everything so far from their inbox only. */
function cvr_chat_staff_hide_for_me(PDO $pdo, int $conversationId, int $userId): void
{
    $stmt = $pdo->prepare('SELECT COALESCE(MAX(id), 0) FROM live_chat_messages WHERE conversation_id = ?');
    $stmt->execute([$conversationId]);
    $maxId = (int) $stmt->fetchColumn();
    $pdo->prepare(
        'INSERT INTO live_chat_staff_hidden (conversation_id, user_id, hidden_before_id) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE hidden_before_id = VALUES(hidden_before_id)'
    )->execute([$conversationId, $userId, $maxId]);
}
