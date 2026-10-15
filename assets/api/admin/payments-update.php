<?php

require_once __DIR__ . '/../../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.', [], 405);
}

$session = require_role(['admin']);

$body = get_json_body();
$id = (int) ($body['id'] ?? 0);
$status = input_str($body, 'status');
$note = input_str($body, 'note');

if ($id <= 0) {
    json_response(false, 'Invalid payment submission.', [], 422);
}
if (!in_array($status, ['Approved', 'Rejected', 'Waiting for Verification'], true)) {
    json_response(false, 'Invalid status.', [], 422);
}
if (mb_strlen($note) > 1000) {
    json_response(false, 'Note must be 1000 characters or fewer.', [], 422);
}

$pdo = get_db_connection();

$stmt = $pdo->prepare('SELECT * FROM payment_proofs WHERE id = ?');
$stmt->execute([$id]);
$proof = $stmt->fetch();

if (!$proof) {
    json_response(false, 'That payment submission no longer exists.', [], 404);
}

$pdo->prepare(
    'UPDATE payment_proofs SET status = ?, admin_note = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?'
)->execute([$status, $note !== '' ? $note : null, (int) ($session['user_id'] ?? 0) ?: null, $id]);

$reservationId = $proof['reservation_ref'];

try {
    $resStmt = $pdo->prepare('SELECT payload_json FROM reservations WHERE id = ?');
    $resStmt->execute([$reservationId]);
    $resRow = $resStmt->fetch();

    if ($resRow) {
        $newPaymentStatus = $status === 'Approved' ? 'Approved' : ($status === 'Rejected' ? 'Rejected' : 'Waiting for Verification');
        $payload = json_decode((string) $resRow['payload_json'], true);

        $currentRes = is_array($payload) ? (string) ($payload['reservationStatus'] ?? '') : '';
        [$newReservationStatus, $newPaymentStatus] = cvr_reconcile_statuses($currentRes, $newPaymentStatus);

        if (is_array($payload)) {
            $payload['paymentStatus'] = $newPaymentStatus;
            $payload['reservationStatus'] = $newReservationStatus;
            $payload['paymentReviewedAt'] = date('c');
            if ($note !== '') {
                $payload['paymentAdminNote'] = $note;
            }
            $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        } else {
            $encoded = $resRow['payload_json'];
        }

        $pdo->prepare('UPDATE reservations SET payment_status = ?, reservation_status = ?, payload_json = ? WHERE id = ?')
            ->execute([$newPaymentStatus, $newReservationStatus, $encoded, $reservationId]);
    }
} catch (\Throwable $e) {
    error_log('admin/payments-update.php: reservation status update failed: ' . $e->getMessage());
}
$guestName = $proof['customer_name'] !== '' ? $proof['customer_name'] : 'Guest';

$guestUserId = (int) ($proof['user_id'] ?? 0);
if ($guestUserId <= 0 && !empty($proof['email'])) {
    try {
        $lookup = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
        $lookup->execute([$proof['email']]);
        $found = $lookup->fetchColumn();
        if ($found) {
            $guestUserId = (int) $found;
        }
    } catch (\Throwable $e) {
        error_log('admin/payments-update.php: user lookup failed: ' . $e->getMessage());
    }
}

if ($guestUserId > 0) {
    try {
        $pdo->prepare('INSERT INTO notifications (user_id, audience, title, message, link) VALUES (?, \'customer\', ?, ?, ?)')->execute([
            $guestUserId,
            $status === 'Approved' ? 'Payment verified' : 'Payment needs attention',
            $status === 'Approved'
                ? 'Your payment for ' . $reservationId . ' has been verified.'
                : 'We could not verify your payment for ' . $reservationId . '. Please resubmit your proof of payment.',
            'reservation-status.html?id=' . rawurlencode($reservationId),
        ]);
    } catch (\Throwable $e) {
        error_log('admin/payments-update.php: notification failed: ' . $e->getMessage());
    }
}

if (!empty($proof['email'])) {
    $mailError = null;
    $bodyHtml = $status === 'Approved'
        ? '<p>Hi ' . htmlspecialchars($guestName) . ',</p>
           <p>Good news! Your payment for reservation <strong>' . htmlspecialchars($reservationId) . '</strong> has been
           <strong>verified</strong>. Your booking is confirmed.</p>'
           . ($note !== '' ? '<p style="background:#f4f6f9;padding:12px 16px;border-radius:6px;">' . nl2br(htmlspecialchars($note)) . '</p>' : '')
        : '<p>Hi ' . htmlspecialchars($guestName) . ',</p>
           <p>We were unable to verify the proof of payment for reservation <strong>' . htmlspecialchars($reservationId) . '</strong>.</p>'
           . ($note !== '' ? '<p style="background:#fdf1f1;padding:12px 16px;border-radius:6px;">' . nl2br(htmlspecialchars($note)) . '</p>' : '')
           . '<p>Please upload a clearer receipt on the payment page, or reply to this email so we can help.</p>';

    send_app_email(
        $proof['email'],
        $guestName,
        ($status === 'Approved' ? 'Payment verified' : 'Payment could not be verified') . ' — Casa Vergara',
        build_email_html($status === 'Approved' ? 'Payment Verified' : 'Payment Needs Attention', $bodyHtml),
        $mailError,
        ADMIN_NOTIFY_EMAIL,
        ADMIN_NOTIFY_NAME
    );
}

$deleted = false;
if ($status === 'Rejected') {
    try {
        $pdo->prepare('DELETE FROM payment_proofs WHERE id = ?')->execute([$id]);
        $deleted = true;

        $relative = (string) ($proof['file_path'] ?? '');
        if ($relative !== '' && strpos($relative, '..') === false) {
            $absolute = __DIR__ . '/../../' . $relative;
            if (is_file($absolute)) {
                @unlink($absolute);
            }
        }
    } catch (\Throwable $e) {
        error_log('admin/payments-update.php: delete after reject failed: ' . $e->getMessage());
    }
}

json_response(true, $deleted ? 'Payment rejected and removed.' : 'Payment submission updated.', [
    'id' => $id,
    'status' => $status,
    'deleted' => $deleted,
]);
