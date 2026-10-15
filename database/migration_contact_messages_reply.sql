-- ============================================================
-- Migration: add admin-reply tracking to contact_messages
-- Run this ONCE, but ONLY IF you already ran the earlier
-- database/migration_contact_messages.sql (i.e. contact_messages already
-- exists without the admin_reply / replied_at columns). If you're
-- setting up contact_messages for the first time, just run
-- migration_contact_messages.sql instead — it already includes these
-- columns and you can skip this file.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS admin_reply TEXT NULL AFTER emailed_admin,
    ADD COLUMN IF NOT EXISTS replied_at TIMESTAMP NULL DEFAULT NULL AFTER admin_reply;
