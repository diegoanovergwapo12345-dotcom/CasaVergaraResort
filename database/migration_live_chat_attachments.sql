-- ============================================================
-- Migration: live chat attachments (photos, files, GIFs, stickers)
--
-- Optional: the chat API adds these columns automatically on the
-- first request. Run this only if you prefer to do it by hand.
-- (If a column already exists, MySQL will say so - that's fine.)
-- ============================================================

ALTER TABLE live_chat_messages
    ADD COLUMN msg_type VARCHAR(12) NOT NULL DEFAULT 'text',
    ADD COLUMN attachment_path VARCHAR(255) DEFAULT NULL,
    ADD COLUMN attachment_name VARCHAR(255) DEFAULT NULL,
    ADD COLUMN attachment_mime VARCHAR(120) DEFAULT NULL,
    ADD COLUMN attachment_size INT UNSIGNED DEFAULT NULL;
