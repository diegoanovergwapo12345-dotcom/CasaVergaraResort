-- ============================================================
-- Migration: "send to Admin or Staff" choice on the Contact Us form
--
-- Adds a `recipient_role` column to contact_messages so a guest can pick
-- who their message goes to (the resort owner/Admin, or the on-site
-- Staff/event-manager team). The Admin > Contact Messages inbox is still
-- shared (both roles can see and reply to every message, same as before)
-- but the new column lets that page show who a message was addressed to,
-- and lets api/content/contact.php know which role's notification bell
-- (see database/migration_notifications_title_link.sql +
-- api/content/my-notifications.php) to ping when the message comes in.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS recipient_role ENUM('admin', 'staff') NOT NULL DEFAULT 'admin' AFTER subject;
