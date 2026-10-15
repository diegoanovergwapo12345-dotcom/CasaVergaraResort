-- ============================================================
-- Migration: in-site notifications for contact form replies
--
-- Adds a `customer_read` flag to contact_messages so a logged-in guest
-- can see the admin's reply to their "Contact Us" message inside their
-- own Dashboard (pages/customer-dashboard.html → Notifications), not
-- just in their email inbox — and so we know whether they've already
-- seen it (for the unread badge in the navbar).
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE contact_messages
    ADD COLUMN IF NOT EXISTS customer_read TINYINT(1) NOT NULL DEFAULT 0 AFTER replied_at;
