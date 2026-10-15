-- ============================================================
-- Migration: server-side notifications get a title + optional link
--
-- The `notifications` table already existed in schema.sql but nothing
-- wrote to it — all "reservation/payment updated" and similar guest
-- notifications lived only in the admin's own browser localStorage
-- (see js/main.js: pushCvrNotification / cvrNotifications), so they
-- never actually reached the guest's device.
--
-- This adds the two columns needed to move that content server-side:
--   - title: short heading (e.g. "Payment verified")
--   - link:  optional relative URL the notification should open
--            (e.g. "privacy.html", "reservation-status.html?id=...")
--
-- `message` is kept as the body text, matching how it reads today.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE notifications
    ADD COLUMN IF NOT EXISTS title VARCHAR(255) NOT NULL DEFAULT '' AFTER booking_id,
    ADD COLUMN IF NOT EXISTS link VARCHAR(255) DEFAULT NULL AFTER message;
