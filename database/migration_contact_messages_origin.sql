-- ============================================================
-- Migration: staff-initiated messages to clients
--
-- Admin and Staff can now START a conversation with a client from
-- Admin > Contact Messages ("New message"), not just reply to messages
-- the guest sent first. Those outbound messages are stored in the same
-- contact_messages table (so the guest still sees them in their
-- Dashboard > My Messages thread) and are tagged with:
--
--   origin       'guest'  = came from the public Contact Us form
--                'staff'  = composed by admin/staff from the inbox
--   sender_role  which role composed it (admin / event_manager)
--   sender_id    the users.id of the sender, for auditing
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS origin ENUM('guest', 'staff') NOT NULL DEFAULT 'guest' AFTER recipient_role,
    ADD COLUMN IF NOT EXISTS sender_role VARCHAR(30) NULL AFTER origin,
    ADD COLUMN IF NOT EXISTS sender_id INT NULL AFTER sender_role;
