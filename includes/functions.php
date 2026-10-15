<?php

function json_response(bool $success, string $message, array $data = [], int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message,
    ], $data ? ['data' => $data] : []));
    exit;
}

function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function input_str(array $data, string $key, string $default = ''): string
{
    return isset($data[$key]) ? trim((string) $data[$key]) : $default;
}

function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function generate_verification_code(): string
{
    return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function cvr_remember_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) {
        return $available;
    }
    try {
        $pdo->query('SELECT 1 FROM remember_tokens LIMIT 1');
        $available = true;
    } catch (\Throwable $e) {
        $available = false;
    }
    return $available;
}

function cvr_remember_cookie_pairs(): array
{
    $raw = (string) ($_COOKIE[REMEMBER_COOKIE_NAME] ?? '');
    if ($raw === '') {
        return [];
    }

    $pairs = [];
    foreach (explode('|', $raw) as $chunk) {
        $parts = explode(':', $chunk, 2);
        if (count($parts) !== 2) {
            continue;
        }
        [$selector, $validator] = $parts;
        if (preg_match('/^[a-f0-9]{16,32}$/', $selector) && preg_match('/^[a-f0-9]{32,128}$/', $validator)) {
            $pairs[$selector] = $validator;
        }
    }
    return $pairs;
}

function cvr_remember_write_cookie(array $pairs): void
{
    if (headers_sent()) {
        return;
    }

    if (!$pairs) {
        setcookie(REMEMBER_COOKIE_NAME, '', [
            'expires'  => time() - 42000,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        unset($_COOKIE[REMEMBER_COOKIE_NAME]);
        return;
    }

    $value = [];
    foreach ($pairs as $selector => $validator) {
        $value[] = $selector . ':' . $validator;
    }
    $value = implode('|', $value);

    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires'  => time() + REMEMBER_ME_DAYS * 86400,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[REMEMBER_COOKIE_NAME] = $value;
}

function cvr_remember_issue(PDO $pdo, int $userId): void
{
    if (!cvr_remember_available($pdo)) {
        return;
    }

    $pairs = cvr_remember_cookie_pairs();

    foreach (cvr_remember_lookup($pdo) as $row) {
        if ((int) $row['user_id'] === $userId) {
            unset($pairs[$row['selector']]);
            $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
        }
    }

    $selector  = bin2hex(random_bytes(8));
    $validator = bin2hex(random_bytes(32));
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_ME_DAYS * 86400);

    $pdo->prepare('INSERT INTO remember_tokens (user_id, selector, validator_hash, expires_at) VALUES (?, ?, ?, ?)')
        ->execute([$userId, $selector, hash('sha256', $validator), $expires]);

    $pairs[$selector] = $validator;
    cvr_remember_write_cookie($pairs);
}

function cvr_remember_lookup(PDO $pdo): array
{
    if (!cvr_remember_available($pdo)) {
        return [];
    }

    $pairs = cvr_remember_cookie_pairs();
    if (!$pairs) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($pairs), '?'));
    $stmt = $pdo->prepare(
        'SELECT t.id, t.user_id, t.selector, t.validator_hash, t.expires_at,
                u.first_name, u.last_name, u.email, u.phone, u.avatar_path,
                u.role, u.status, u.is_verified, u.created_at
         FROM remember_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector IN (' . $placeholders . ')'
    );
    $stmt->execute(array_keys($pairs));

    $rows = [];
    $keep = [];
    foreach ($stmt->fetchAll() as $row) {
        $validator = $pairs[$row['selector']] ?? '';

        if (!hash_equals((string) $row['validator_hash'], hash('sha256', $validator))) {
            continue;
        }
        if (strtotime($row['expires_at']) < time()) {
            $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
            continue;
        }
        if ($row['status'] === 'suspended' || (int) $row['is_verified'] !== 1) {
            continue;
        }

        $rows[] = $row;
        $keep[$row['selector']] = $validator;
    }

    if (count($keep) !== count($pairs)) {
        cvr_remember_write_cookie($keep);
    }

    return $rows;
}

function cvr_remember_forget(PDO $pdo, string $email): void
{
    if (!cvr_remember_available($pdo)) {
        return;
    }

    $pairs = cvr_remember_cookie_pairs();
    foreach (cvr_remember_lookup($pdo) as $row) {
        if (strcasecmp((string) $row['email'], $email) === 0) {
            $pdo->prepare('DELETE FROM remember_tokens WHERE id = ?')->execute([$row['id']]);
            unset($pairs[$row['selector']]);
        }
    }
    cvr_remember_write_cookie($pairs);
}

function cvr_remember_rotate(PDO $pdo, array $row): void
{
    $validator = bin2hex(random_bytes(32));
    $expires   = date('Y-m-d H:i:s', time() + REMEMBER_ME_DAYS * 86400);

    $pdo->prepare('UPDATE remember_tokens SET validator_hash = ?, expires_at = ?, last_used_at = NOW() WHERE id = ?')
        ->execute([hash('sha256', $validator), $expires, $row['id']]);

    $pairs = cvr_remember_cookie_pairs();
    $pairs[$row['selector']] = $validator;
    cvr_remember_write_cookie($pairs);
}

function cvr_user_payload(array $user): array
{
    return [
        'id' => $user['id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'email' => $user['email'],
        'phone' => $user['phone'] ?? '',
        'role' => $user['role'],
        'avatarUrl' => !empty($user['avatar_path']) ? SITE_URL . '/' . $user['avatar_path'] : null,
        'joinedAt' => $user['created_at'] ?? null,
    ];
}

function require_login(): array
{
    if (empty($_SESSION['user_id'])) {
        json_response(false, 'You must be logged in.', [], 401);
    }

    $pdo = get_db_connection();
    $stmt = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $status = $stmt->fetchColumn();

    if ($status === false) {

        session_unset();
        session_destroy();
        json_response(false, 'You must be logged in.', [], 401);
    }

    if ($status === 'suspended') {
        session_unset();
        session_destroy();
        json_response(false, 'This account has been suspended. Please contact Casa Vergara.', ['suspended' => true], 403);
    }

    return $_SESSION;
}

function require_role(array $roles): array
{
    $session = require_login();
    if (!in_array($session['role'] ?? '', $roles, true)) {
        json_response(false, 'You do not have permission to perform this action.', [], 403);
    }
    return $session;
}

function is_valid_phone(string $phone): bool
{
    return (bool) preg_match('/^[0-9+\-\s]{7,20}$/', $phone);
}

function normalize_phone_digits(string $phone): string
{
    return preg_replace('/\D+/', '', $phone) ?? '';
}

function count_accounts_with_phone(PDO $pdo, string $phone, string $excludeEmail = ''): int
{
    $digits = normalize_phone_digits($phone);
    if ($digits === '') {
        return 0;
    }

    $sql = "SELECT COUNT(*) FROM users
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''), '.', '') = ?";
    $params = [$digits];

    if ($excludeEmail !== '') {
        $sql .= ' AND email <> ?';
        $params[] = $excludeEmail;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

function is_full_name_taken(PDO $pdo, string $firstName, string $lastName, string $excludeEmail = ''): bool
{
    $firstName = trim($firstName);
    $lastName = trim($lastName);
    if ($firstName === '' || $lastName === '') {
        return false;
    }

    $sql = 'SELECT COUNT(*) FROM users
            WHERE LOWER(TRIM(first_name)) = LOWER(?)
              AND LOWER(TRIM(last_name)) = LOWER(?)
              AND is_verified = 1';
    $params = [$firstName, $lastName];

    if ($excludeEmail !== '') {
        $sql .= ' AND email <> ?';
        $params[] = $excludeEmail;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn() > 0;
}

function validate_email_deliverability(string $email, ?string &$reason = null, ?string &$suggestion = null): bool
{
    $reason = null;
    $suggestion = null;

    $email = trim(strtolower($email));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $reason = 'Please enter a valid email address.';
        return false;
    }

    [$local, $domain] = explode('@', $email, 2);

    $typos = [
        'gmial.com' => 'gmail.com', 'gmai.com' => 'gmail.com', 'gmil.com' => 'gmail.com',
        'gmaill.com' => 'gmail.com', 'gmail.co' => 'gmail.com', 'gmail.cm' => 'gmail.com',
        'gmail.con' => 'gmail.com', 'gnail.com' => 'gmail.com', 'gamil.com' => 'gmail.com',
        'googlemail.co' => 'gmail.com',
        'yahoo.co' => 'yahoo.com', 'yaho.com' => 'yahoo.com', 'yahooo.com' => 'yahoo.com',
        'hotmial.com' => 'hotmail.com', 'hotmai.com' => 'hotmail.com',
        'outlok.com' => 'outlook.com', 'outook.com' => 'outlook.com',
    ];
    if (isset($typos[$domain])) {
        $suggestion = $local . '@' . $typos[$domain];
        $reason = 'That email domain looks like a typo. Did you mean ' . $suggestion . '?';
        return false;
    }

    $hasMx = function_exists('checkdnsrr') ? @checkdnsrr($domain, 'MX') : true;
    if (!$hasMx) {
        $hasFallback = (function_exists('checkdnsrr') && (@checkdnsrr($domain, 'A') || @checkdnsrr($domain, 'AAAA')));
        if (!$hasFallback) {
            $reason = 'We could not find a mail server for "' . $domain . '". Please check the email address.';
            return false;
        }
    }

    $googleDomains = ['gmail.com', 'googlemail.com'];
    if (in_array($domain, $googleDomains, true)) {
        $plain = str_replace('.', '', explode('+', $local)[0]);

        if (!preg_match('/^[a-z0-9.]+(\+[^@]*)?$/', $local)) {
            $reason = 'That Google account does not exist. Gmail addresses can only use letters, numbers and dots.';
            return false;
        }
        if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
            $reason = 'That Google account does not exist. Gmail addresses cannot start or end with a dot, or use two dots in a row.';
            return false;
        }
        if (strlen($plain) < 6 || strlen($plain) > 30) {
            $reason = 'That Google account does not exist. Gmail usernames are between 6 and 30 characters.';
            return false;
        }
    }

    $probe = smtp_mailbox_exists($email, $domain);
    if ($probe === false) {
        $reason = 'That email address does not exist. Please use a real, working email address.';
        return false;
    }

    return true;
}

function smtp_mailbox_exists(string $email, string $domain): ?bool
{
    if (defined('EMAIL_SMTP_PROBE_ENABLED') && !EMAIL_SMTP_PROBE_ENABLED) {
        return null;
    }
    if (!function_exists('fsockopen') || !function_exists('getmxrr')) {
        return null;
    }

    $hosts = [];
    $weights = [];
    if (@getmxrr($domain, $hosts, $weights) && $hosts) {
        array_multisort($weights, $hosts);
    } else {
        $hosts = [$domain];
    }

    $timeout = defined('EMAIL_SMTP_PROBE_TIMEOUT') ? (int) EMAIL_SMTP_PROBE_TIMEOUT : 2;
    $helo = defined('EMAIL_SMTP_PROBE_HELO') ? EMAIL_SMTP_PROBE_HELO : (parse_url(SITE_URL, PHP_URL_HOST) ?: 'localhost');
    $from = defined('EMAIL_SMTP_PROBE_FROM') ? EMAIL_SMTP_PROBE_FROM : ('postmaster@' . $helo);

    foreach (array_slice($hosts, 0, 1) as $host) {
        $socket = @fsockopen($host, 25, $errNo, $errStr, $timeout);
        if (!$socket) {
            continue;
        }
        stream_set_timeout($socket, $timeout);

        $read = function () use ($socket): string {
            $out = '';
            while (($line = fgets($socket, 515)) !== false) {
                $out .= $line;
                if (strlen($line) < 4 || $line[3] !== '-') break;
            }
            return $out;
        };
        $cmd = function (string $line) use ($socket, $read): string {
            fwrite($socket, $line . "\r\n");
            return $read();
        };

        $greeting = $read();
        if (strncmp($greeting, '220', 3) !== 0) {
            fclose($socket);
            continue;
        }

        $cmd('EHLO ' . $helo);
        $cmd('MAIL FROM:<' . $from . '>');
        $rcpt = $cmd('RCPT TO:<' . $email . '>');
        $cmd('QUIT');
        fclose($socket);

        $code = (int) substr(trim($rcpt), 0, 3);

        if ($code >= 200 && $code < 300) {
            return true;
        }
        if ($code === 550 || $code === 551 || $code === 553 || $code === 501) {
            return false;
        }
        return null;
    }

    return null;
}

function get_payment_qr(PDO $pdo): array
{
    $empty = ['path' => null, 'label' => '', 'url' => null];

    try {
        $row = $pdo->query('SELECT payment_qr_path, payment_qr_label FROM resort_settings WHERE id = 1')->fetch();
    } catch (\Throwable $e) {
        error_log('get_payment_qr: could not read payment QR columns (run database/migration_payment_qr.sql): ' . $e->getMessage());
        return $empty;
    }

    if (!$row) {
        return $empty;
    }

    $path = trim((string) ($row['payment_qr_path'] ?? ''));
    $label = trim((string) ($row['payment_qr_label'] ?? ''));

    if ($path === '' || !is_file(__DIR__ . '/../' . $path)) {
        return ['path' => null, 'label' => $label, 'url' => null];
    }

    return [
        'path'  => $path,
        'label' => $label,
        'url'   => SITE_URL . '/' . $path,
    ];
}

function cvr_reconcile_statuses(string $reservationStatus, string $paymentStatus): array
{
    $pay = trim($paymentStatus);
    $res = trim($reservationStatus);
    $payLower = strtolower($pay);
    $resLower = strtolower($res);

    if ($payLower === '' ) {
        $pay = 'Pending Payment';
    } elseif (strpos($payLower, 'approv') !== false || strpos($payLower, 'verified') !== false || strpos($payLower, 'paid') !== false) {
        $pay = 'Approved';
    } elseif (strpos($payLower, 'reject') !== false || strpos($payLower, 'declin') !== false) {
        $pay = 'Rejected';
    } elseif (strpos($payLower, 'waiting') !== false || strpos($payLower, 'verification') !== false) {
        $pay = 'Waiting for Verification';
    } elseif (strpos($payLower, 'pending') !== false) {
        $pay = 'Pending Payment';
    }

    $isTerminal = strpos($resLower, 'cancel') !== false
        || strpos($resLower, 'checked out') !== false
        || strpos($resLower, 'completed') !== false;

    $isStaffProgress = $pay === 'Approved'
        && (strpos($resLower, 'awaiting check-in') !== false || strpos($resLower, 'checked in') !== false);

    if (!$isTerminal && !$isStaffProgress) {
        if ($pay === 'Approved') {
            $res = 'Confirmed';
        } elseif ($pay === 'Rejected') {
            $res = 'Payment Rejected';
        } elseif ($pay === 'Waiting for Verification') {
            $res = 'Pending Verification';
        } else {
            $res = 'Pending Payment';
        }
    }

    return [$res, $pay];
}

function cvr_notify_guest_status_change(
    PDO $pdo,
    string $reservationId,
    ?int $userId,
    string $email,
    string $guestName,
    string $reservationStatus,
    string $paymentStatus
): void {
    $name = $guestName !== '' ? $guestName : 'Guest';
    $link = 'reservation-status.html?id=' . rawurlencode($reservationId);
    $summary = 'Reservation ' . $reservationId . ' is now "' . $reservationStatus
        . '" and the payment is "' . $paymentStatus . '".';

    try {
        if ($userId !== null && $userId > 0) {
            $pdo->prepare(
                "INSERT INTO notifications (user_id, audience, title, message, link)
                 VALUES (?, 'customer', ?, ?, ?)"
            )->execute([$userId, 'Reservation updated', $summary, $link]);
        }
    } catch (\Throwable $e) {
        error_log('cvr_notify_guest_status_change: notification insert failed: ' . $e->getMessage());
    }

    if ($email === '') {
        return;
    }

    $mailError = null;
    send_app_email(
        $email,
        $name,
        'Your reservation was updated — ' . SITE_NAME,
        build_email_html('Reservation updated',
            '<p>Hi ' . htmlspecialchars($name) . ',</p>' .
            '<p>' . htmlspecialchars($summary) . '</p>' .
            '<p>You can review the details any time from your account dashboard.</p>'),
        $mailError
    );
    if ($mailError) {
        error_log('cvr_notify_guest_status_change: email failed for ' . $reservationId . ': ' . $mailError);
    }
}

function cvr_reset_otp_columns_available(PDO $pdo): bool
{
    static $available = null;
    if ($available !== null) return $available;
    try {
        $pdo->query('SELECT reset_code_attempts, reset_block_until FROM users LIMIT 1');
        $available = true;
    } catch (\Throwable $e) {
        $available = false;
    }
    return $available;
}

function cvr_reset_block_seconds_left(?array $user): int
{
    if (!$user || empty($user['reset_block_until'])) return 0;
    $left = strtotime((string) $user['reset_block_until']) - time();
    return $left > 0 ? $left : 0;
}

function cvr_format_wait($seconds): string
{
    $seconds = max(60, (int) $seconds);
    $hours   = intdiv($seconds, 3600);
    $minutes = (int) ceil(($seconds % 3600) / 60);
    if ($minutes === 60) { $hours++; $minutes = 0; }
    $parts = [];
    if ($hours > 0)   $parts[] = $hours . ' hour' . ($hours === 1 ? '' : 's');
    if ($minutes > 0) $parts[] = $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    return $parts ? implode(' ', $parts) : 'a moment';
}

function cvr_register_wrong_reset_code(PDO $pdo, array $user): array
{
    if (!cvr_reset_otp_columns_available($pdo)) {
        return ['blocked' => false, 'attemptsLeft' => RESET_OTP_MAX_ATTEMPTS, 'secondsLeft' => 0];
    }

    $attempts = (int) ($user['reset_code_attempts'] ?? 0) + 1;

    if ($attempts >= RESET_OTP_MAX_ATTEMPTS) {
        $blockUntil = date('Y-m-d H:i:s', time() + RESET_OTP_BLOCK_HOURS * 3600);

        $pdo->prepare('UPDATE users SET reset_code_attempts = 0, reset_block_until = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?')
            ->execute([$blockUntil, $user['id']]);
        return ['blocked' => true, 'attemptsLeft' => 0, 'secondsLeft' => RESET_OTP_BLOCK_HOURS * 3600];
    }

    $pdo->prepare('UPDATE users SET reset_code_attempts = ? WHERE id = ?')->execute([$attempts, $user['id']]);
    return ['blocked' => false, 'attemptsLeft' => RESET_OTP_MAX_ATTEMPTS - $attempts, 'secondsLeft' => 0];
}

function cvr_clear_reset_code_attempts(PDO $pdo, array $user): void
{
    if (!cvr_reset_otp_columns_available($pdo)) return;
    if ((int) ($user['reset_code_attempts'] ?? 0) === 0) return;
    $pdo->prepare('UPDATE users SET reset_code_attempts = 0 WHERE id = ?')->execute([$user['id']]);
}
