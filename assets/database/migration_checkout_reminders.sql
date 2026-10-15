-- ============================================================
-- Migration: check-out reminder tracking
--
-- Mirrors migration_checkin_checkout.sql's checkin_reminder_sent_at, but
-- for the OTHER end of the stay: adds
--   - checkout_reminder_sent_at: set by scripts/send-checkout-reminders.php
--                                 once a "your check-out time is coming up"
--                                 reminder (website notification + email for
--                                 the guest, plus a staff notification for
--                                 admin/event_manager) has gone out for this
--                                 reservation, so the same guest/stay is
--                                 never reminded twice. Reset back to NULL
--                                 automatically by api/content/reservations-sync.php
--                                 if the guest reschedules to a new
--                                 check-out window.
--
-- See includes/checkout_reminders.php for the sweep that uses this column.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS checkout_reminder_sent_at DATETIME NULL DEFAULT NULL AFTER checkin_reminder_sent_at;

CREATE INDEX IF NOT EXISTS idx_res_checkout_reminder ON reservations (booking_type, scheduled_check_out, checkout_reminder_sent_at);
