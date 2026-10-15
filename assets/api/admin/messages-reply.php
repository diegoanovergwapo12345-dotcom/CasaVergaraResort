<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin', 'event_manager']);

$body  = get_json_body();
$id    = (int) ($body['id'] ?? 0);
$reply = input_str($body, 'reply');

if ($id <= 0) {
    json_response(false, 'Invalid message.', [], 422);
}
if ($reply === '') {
    json_response(false, 'Please write a reply before sending.', [], 422);
}
if (mb_strlen($reply) > 3000) {
    json_response(false, 'Reply must be 3000 characters or fewer.', [], 422);
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT id, name, email, subject, message FROM contact_messages WHERE id = ?');
$stmt->execute([$id]);
$row = $stmt->fetch();

if (!$row) {
    json_response(false, 'Message not found.', [], 404);
}

$bodyHtml = '
    <p>Hi ' . htmlspecialchars($row['name']) . ',</p>
    <p style="white-space:pre-wrap;">' . nl2br(htmlspecialchars($reply)) . '</p>
    <p style="margin-top:24px; font-size:12px; color:#888;">— Casa Vergara Team</p>
    <div style="margin-top:20px; padding-top:16px; border-top:1px solid #e5e5e5; font-size:12px; color:#999;">
        <p style="margin:0 0 4px;">In response to your message:</p>
        <p style="margin:0; white-space:pre-wrap;">' . nl2br(htmlspecialchars($row['message'])) . '</p>
    </div>
';

$pdo->prepare('UPDATE contact_messages SET admin_reply = ?, replied_at = NOW(), customer_read = 0 WHERE id = ?')
    ->execute([$reply, $id]);

$mailError = null;
$sent = send_app_email(
    $row['email'],
    $row['name'],
    'Re: ' . $row['subject'] . ' — Casa Vergara',
    build_email_html('A reply from Casa Vergara', $bodyHtml),
    $mailError,
    ADMIN_NOTIFY_EMAIL,
    ADMIN_NOTIFY_NAME
);

if (!$sent) {

    json_response(
        true,
        'Reply saved and visible to the guest on the site, but the email could not be sent. ' . ($mailError ?: ''),
        ['emailSent' => false]
    );
}

json_response(true, 'Reply sent to ' . $row['email'] . '.', ['emailSent' => true]);
