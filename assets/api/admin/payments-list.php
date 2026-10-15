<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(false, 'Invalid request method.', [], 405);
}

require_role(['admin']);

$pdo = get_db_connection();

try {
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS payment_proofs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reservation_ref VARCHAR(100) NOT NULL,
            user_id INT NULL,
            customer_name VARCHAR(150) NOT NULL DEFAULT \'\',
            email VARCHAR(190) NOT NULL DEFAULT \'\',
            phone VARCHAR(40) NULL,
            package_name VARCHAR(190) NULL,
            amount VARCHAR(50) NULL,
            payment_method VARCHAR(60) NOT NULL DEFAULT \'QR / GCash\',
            file_path VARCHAR(255) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_type VARCHAR(60) NOT NULL,
            file_size INT NOT NULL DEFAULT 0,
            status ENUM(\'Waiting for Verification\', \'Approved\', \'Rejected\') NOT NULL DEFAULT \'Waiting for Verification\',
            admin_note TEXT NULL,
            reviewed_by INT NULL,
            reviewed_at TIMESTAMP NULL DEFAULT NULL,
            emailed_admin TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (reservation_ref),
            INDEX (status)
        ) ENGINE=InnoDB'
    );
} catch (\Throwable $e) {
    error_log('admin/payments-list.php (create table): ' . $e->getMessage());
}

try {
    $rows = $pdo->query(
        'SELECT id, reservation_ref, customer_name, email, phone, package_name, amount, payment_method,
                file_path, file_name, file_type, file_size, status, admin_note, reviewed_at, created_at
         FROM payment_proofs
         ORDER BY created_at DESC, id DESC'
    )->fetchAll();
} catch (\Throwable $e) {
    error_log('admin/payments-list.php: ' . $e->getMessage());
    json_response(false, 'Could not load payment submissions.', [], 500);
}

$proofs = array_map(static function (array $row): array {
    return [
        'id' => (int) $row['id'],
        'reservationId' => $row['reservation_ref'],
        'customerName' => $row['customer_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'packageName' => $row['package_name'],
        'amount' => $row['amount'],
        'paymentMethod' => $row['payment_method'],
        'fileUrl' => SITE_URL . '/' . $row['file_path'],
        'fileName' => $row['file_name'],
        'fileType' => $row['file_type'],
        'fileSize' => (int) $row['file_size'],
        'status' => $row['status'],
        'adminNote' => $row['admin_note'],
        'reviewedAt' => $row['reviewed_at'],
        'createdAt' => $row['created_at'],
    ];
}, $rows);

$counts = ['waiting' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($proofs as $proof) {
    if ($proof['status'] === 'Approved') $counts['approved']++;
    elseif ($proof['status'] === 'Rejected') $counts['rejected']++;
    else $counts['waiting']++;
}

json_response(true, 'OK', ['proofs' => $proofs, 'counts' => $counts]);
