<?php

define('SITE_URL', 'http://localhost/resorts');

define('SITE_NAME', 'Casa Vergara');

define('VERIFICATION_TOKEN_TTL_HOURS', 24);

define('VERIFICATION_CODE_TTL_MINUTES', 15);

define('RESET_TOKEN_TTL_HOURS', 2);

define('RESET_CODE_TTL_MINUTES', 15);

define('REMEMBER_ME_DAYS', 30);
define('REMEMBER_COOKIE_NAME', 'cvr_remember');

define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

define('RESET_OTP_MAX_ATTEMPTS', 5);
define('RESET_OTP_BLOCK_HOURS', 24);

define('MAX_ACCOUNTS_PER_PHONE', 1);

define('EMAIL_SMTP_PROBE_ENABLED', true);
define('EMAIL_SMTP_PROBE_TIMEOUT', 2);
define('EMAIL_SMTP_PROBE_HELO', 'localhost');
define('EMAIL_SMTP_PROBE_FROM', 'postmaster@localhost');

// Live chat GIF picker. Get a free key at https://developers.giphy.com (create an API app),
// then paste it here. Leave empty to disable GIF search (guests can still upload .gif files).
define('CVR_GIPHY_API_KEY', '');

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/live_chat.php';

define('CHECKIN_REMINDER_DAYS_BEFORE', 1);

define('CHECKIN_REMINDER_SECRET', 'change-me-cvr-reminder-key');

define('CHECKOUT_REMINDER_HOURS_BEFORE', 1);

define('CHECKOUT_REMINDER_SECRET', 'change-me-cvr-checkout-reminder-key');
