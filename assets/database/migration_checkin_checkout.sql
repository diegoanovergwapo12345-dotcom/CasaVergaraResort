-- ============================================================
-- Migration: flexible checkout + check-in reminder tracking
--
-- Adds two columns to the existing `reservations` table:
--   - checked_out_at:            set the moment a guest uses the new
--                                 "Check out now" button (see
--                                 pages/reservation-status.html /
--                                 pages/customer-dashboard.html). A guest
--                                 may check out any day within their stay
--                                 (check_in..check_out), not only on the
--                                 originally booked check-out date.
--   - checkin_reminder_sent_at:  set by scripts/send-checkin-reminders.php
--                                 once a "your check-in is coming up"
--                                 reminder (website notification + email)
--                                 has gone out for this reservation, so the
--                                 same guest is never reminded twice. Reset
--                                 back to NULL automatically by
--                                 api/content/reservations-sync.php if the
--                                 guest later reschedules to a new check-in
--                                 date.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS checked_out_at DATETIME NULL DEFAULT NULL AFTER check_out,
    ADD COLUMN IF NOT EXISTS checkin_reminder_sent_at DATETIME NULL DEFAULT NULL AFTER checked_out_at;

CREATE INDEX IF NOT EXISTS idx_res_checkin_reminder ON reservations (booking_type, check_in, checkin_reminder_sent_at);
