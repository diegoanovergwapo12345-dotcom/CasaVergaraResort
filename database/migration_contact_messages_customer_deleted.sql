-- ============================================================
-- Migration: per-guest "delete notification" support
--
-- Adds a `customer_deleted` flag to contact_messages so a guest can
-- remove a reply from their own Notifications list (Dashboard + navbar
-- bell) without affecting the admin side — the row and the admin's
-- reply thread stay intact in the admin Messages panel either way.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS customer_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER customer_read;
