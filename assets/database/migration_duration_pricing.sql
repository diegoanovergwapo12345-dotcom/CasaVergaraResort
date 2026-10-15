-- ============================================================
-- Migration: fixed-duration stays + strict check-in/out + overtime penalty
--
-- Casa Vergara room stays AND event reservations now use two strict,
-- non-negotiable stay windows instead of an open date range:
--   - "12hr"  Day Tour   08:00 AM -> 08:00 PM   (same calendar day)
--   - "22hr"  Overnight  02:00 PM -> 12:00 NN the next day
-- (See js/duration-pricing.js for the source of truth on times/rates.)
--
-- Adds to the existing `reservations` table:
--   - duration_type:        '12hr', '22hr', or 'extended' — which fixed
--                            window (or multi-night extended stay) was booked
--   - scheduled_check_in:   exact DATETIME the guest is expected to arrive
--   - scheduled_check_out:  exact DATETIME the guest must vacate by (strict)
--   - overtime_hours:       whole hours (rounded up) checked out past
--                            scheduled_check_out; 0 if on time or early
--   - penalty_amount:       peso penalty charged for the overtime above,
--                            10% of total_numeric per overtime hour, capped
--                            at 100% of total_numeric (see
--                            js/duration-pricing.js -> cvrOvertimePenalty)
--
-- Existing rows (booked before this migration) will have NULL duration_type
-- / scheduled_* — the app treats those as legacy flexible-date bookings and
-- falls back to date-level (not hour-level) overlap checks for them.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE reservations
    ADD COLUMN IF NOT EXISTS duration_type VARCHAR(10) NULL DEFAULT NULL AFTER check_out,
    ADD COLUMN IF NOT EXISTS scheduled_check_in DATETIME NULL DEFAULT NULL AFTER duration_type,
    ADD COLUMN IF NOT EXISTS scheduled_check_out DATETIME NULL DEFAULT NULL AFTER scheduled_check_in,
    ADD COLUMN IF NOT EXISTS overtime_hours DECIMAL(6,2) NOT NULL DEFAULT 0 AFTER checked_out_at,
    ADD COLUMN IF NOT EXISTS penalty_amount DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER overtime_hours;

CREATE INDEX IF NOT EXISTS idx_res_scheduled_window ON reservations (package_id, scheduled_check_in, scheduled_check_out);
