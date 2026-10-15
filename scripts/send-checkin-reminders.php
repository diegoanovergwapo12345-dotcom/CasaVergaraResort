<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/checkin_reminders.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {

    $key = $_GET['key'] ?? '';
    if (!is_string($key) || $key === '' || !hash_equals(CHECKIN_REMINDER_SECRET, $key)) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }
}

$pdo = get_db_connection();
$summary = run_checkin_reminders($pdo);

if ($isCli) {
    echo sprintf(
        "[%s] Check-in reminders: checked=%d sent=%d failed=%d\n",
        date('Y-m-d H:i:s'),
        $summary['checked'],
        $summary['sent'],
        $summary['failed']
    );
    foreach ($summary['errors'] as $err) {
        echo '  - ' . $err . "\n";
    }
    exit($summary['failed'] > 0 ? 1 : 0);
}

header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => $summary]);
