<?php

require_once __DIR__ . '/../../config/config.php';

$localIps = ['127.0.0.1', '::1', 'localhost'];
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $localIps, true)) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: text/html; charset=utf-8');

$to = isset($_GET['to']) ? trim($_GET['to']) : '';

echo '<html><body style="font-family: Arial, sans-serif; max-width:700px; margin:40px auto; line-height:1.6;">';
echo '<h2>Casa Vergara &mdash; Mail Test</h2>';

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    echo '<p>Add <code>?to=your@email.com</code> to the URL to run the test, e.g.:</p>';
    echo '<p><code>' . htmlspecialchars(SITE_URL) . '/api/auth/test-mail.php?to=you@gmail.com</code></p>';
    echo '</body></html>';
    exit;
}

echo '<p><strong>Sending from:</strong> ' . htmlspecialchars(GMAIL_ADDRESS) . '</p>';
echo '<p><strong>Sending to:</strong> ' . htmlspecialchars($to) . '</p>';
echo '<hr>';

$error = null;
$start = microtime(true);
$sent = send_app_email(
    $to,
    'Test Recipient',
    'Casa Vergara - SMTP Test',
    build_email_html('SMTP Test Email', '<p>If you are reading this, Gmail SMTP is configured correctly. 🎉</p>'),
    $error
);
$elapsed = round(microtime(true) - $start, 2);

if ($sent) {
    echo '<p style="color:green; font-weight:bold; font-size:18px;">✅ Success! Email sent in ' . $elapsed . 's.</p>';
    echo '<p>Check the inbox (and Spam folder) of <strong>' . htmlspecialchars($to) . '</strong>.</p>';
} else {
    echo '<p style="color:#b00020; font-weight:bold; font-size:18px;">❌ Failed after ' . $elapsed . 's.</p>';
    echo '<p><strong>Raw error:</strong></p>';
    echo '<pre style="background:#f5f5f5; padding:16px; border-radius:6px; white-space:pre-wrap;">' . htmlspecialchars((string) $error) . '</pre>';

    echo '<h3>What this usually means:</h3><ul>';
    if (stripos((string) $error, 'authenticate') !== false || stripos((string) $error, 'Username and Password not accepted') !== false) {
        echo '<li>Your <code>GMAIL_APP_PASSWORD</code> in <code>config/mail.php</code> is wrong, expired, or was revoked. Generate a fresh one at <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a> and paste the new 16 characters in.</li>';
        echo '<li>Confirm <code>GMAIL_ADDRESS</code> is the exact account that generated that App Password.</li>';
    } elseif (stripos((string) $error, 'certificate') !== false || stripos((string) $error, 'ssl') !== false) {
        echo '<li>This is a local CA-certificate issue common on XAMPP/Windows. The latest version of this file already relaxes certificate checks - make sure you copied the newest <code>config/mail.php</code>.</li>';
    } elseif (stripos((string) $error, 'connect') !== false || stripos((string) $error, 'timed out') !== false) {
        echo '<li>Something is blocking outbound port 587 (school/office network, antivirus, or firewall). Try a different network (e.g. mobile hotspot) to confirm.</li>';
    } else {
        echo '<li>Copy the raw error above and share it - it tells us exactly what to fix next.</li>';
    }
    echo '</ul>';
}

echo '</body></html>';
