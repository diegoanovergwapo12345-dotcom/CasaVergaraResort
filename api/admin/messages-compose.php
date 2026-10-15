<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin', 'event_manager']);

$body     = get_json_body();
$clientId = (int) ($body['clientId'] ?? 0);
$name     = input_str($body, 'name');
$email    = strtolower(input_str($body, 'email'));
$subject  = input_str($body, 'subject', 'A message from Casa Vergara');
$message  = input_str($body, 'message');

$pdo = get_db_connection();

if ($clientId > 0) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ? AND role = 'customer'");
    $stmt->execute([$clientId]);
    $client = $stmt->fetch();
    if (!$client) {
        json_response(false, 'That client account no longer exists.', [], 404);
    }
    $name  = trim($client['first_name'] . ' ' . $client['last_name']);
    $email = strtolower((string) $client['email']);
    $phone = $client['phone'];
} else {
    $phone = null;
}

$errors = [];
if ($name === '' || mb_strlen($name) > 150) $errors[] = 'Please enter the recipient\'s name (150 characters or fewer).';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid recipient email address.';
if ($subject === '' || mb_strlen($subject) > 100) $errors[] = 'Please enter a subject (100 characters or fewer).';
if ($message === '') $errors[] = 'Please write a message before sending.';
if (mb_strlen($message) > 3000) $errors[] = 'Message must be 3000 characters or fewer.';

if ($errors) {
    json_response(false, implode(' ', $errors), [], 422);
}

$senderRole = (string) ($session['role'] ?? 'admin');
$senderId   = (int) ($session['user_id'] ?? 0) ?: null;

$origin = $senderRole === 'event_manager' ? 'the resort staff team' : 'the resort management';
$note   = 'Message started by ' . $origin . ' from the admin dashboard.';

try {
    $stmt = $pdo->prepare(
        'INSERT INTO contact_messages
            (name, email, phone, subject, recipient_role, origin, sender_role, sender_id, message, admin_reply, replied_at, customer_read)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), 0)'
    );
    $stmt->execute([
        $name,
        $email,
        $phone,
        $subject,
        $senderRole === 'event_manager' ? 'staff' : 'admin',
        'staff',
        $senderRole,
        $senderId,
        $note,
        $message,
    ]);
    $messageId = (int) $pdo->lastInsertId();
} catch (\Throwable $e) {
    error_log('admin/messages-compose.php: ' . $e->getMessage());
    json_response(false, 'Could not save the message. Has database/migration_contact_messages_origin.sql been run?', [], 500);
}

try {
    $pdo->prepare(
        "INSERT INTO notifications (user_id, audience, title, message, link)
         SELECT id, 'customer', ?, ?, ? FROM users WHERE LOWER(email) = ? AND role = 'customer'"
    )->execute([
        'Message from Casa Vergara — ' . $subject,
        mb_substr($message, 0, 140) . (mb_strlen($message) > 140 ? '…' : ''),
        'customer-dashboard.html#messages',
        $email,
    ]);
} catch (\Throwable $e) {
    error_log('admin/messages-compose.php: notification failed: ' . $e->getMessage());
}

$bodyHtml = '
    <p>Hi ' . htmlspecialchars($name) . ',</p>
    <p style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($message)) . '</p>
    <p style="margin-top:24px; font-size:12px; color:#888;">— Casa Vergara Team</p>
    <p style="font-size:12px; color:#999;">You can reply to this email, or open your Casa Vergara dashboard to see this message under "My Messages".</p>
';

$mailError = null;
$sent = send_app_email(
    $email,
    $name,
    $subject . ' — Casa Vergara',
    build_email_html('A message from Casa Vergara', $bodyHtml),
    $mailError,
    ADMIN_NOTIFY_EMAIL,
    ADMIN_NOTIFY_NAME
);

if (!$sent) {
    json_response(true, 'Message saved and visible to the client on the site, but the email could not be sent. ' . ($mailError ?: ''), [
        'id' => $messageId,
        'emailSent' => false,
    ]);
}

json_response(true, 'Message sent to ' . $email . '.', ['id' => $messageId, 'emailSent' => true]);
