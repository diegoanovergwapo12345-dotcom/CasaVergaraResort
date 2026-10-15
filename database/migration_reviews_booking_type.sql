-- ============================================================
-- Migration: booking-specific reviews
--
-- Lets a guest write a review about ONE particular stay/package
-- (e.g. "Deluxe Room") in addition to the existing general
-- resort/website review. Adds:
--   - review_type:    'general' (about the resort/website as a whole,
--                      the original behavior) or 'booking' (about one
--                      specific reservation).
--   - reservation_id: which reservation the review is about, when
--                      review_type = 'booking'. Nullable + ON DELETE
--                      SET NULL so the review still displays (using the
--                      frozen booking_label below) even if the
--                      reservation row is ever removed.
--   - booking_label:  a frozen snapshot of the room/package name at the
--                      time of posting (e.g. "Deluxe Room"), so the
--                      review still shows what it was about even if the
--                      reservation is later deleted or its payload
--                      changes.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE reviews
    ADD COLUMN IF NOT EXISTS review_type ENUM('general', 'booking') NOT NULL DEFAULT 'general' AFTER comment,
    ADD COLUMN IF NOT EXISTS reservation_id VARCHAR(40) DEFAULT NULL AFTER review_type,
    ADD COLUMN IF NOT EXISTS booking_label VARCHAR(191) DEFAULT NULL AFTER reservation_id;

CREATE INDEX IF NOT EXISTS idx_review_reservation ON reviews (reservation_id);

-- Add the foreign key only if it isn't already there (re-running this
-- file shouldn't error out on a duplicate constraint).
SET @fk_exists := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND CONSTRAINT_NAME = 'fk_review_reservation'
);

SET @add_fk_sql := IF(@fk_exists = 0,
  'ALTER TABLE reviews ADD CONSTRAINT fk_review_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE SET NULL',
  'SELECT 1'
);

PREPARE stmt FROM @add_fk_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
