-- ============================================================
-- Migration: live chat "Seen" receipts + delete for me / for everyone
--
-- Optional: the chat API adds these columns automatically on the
-- first request. Run this only if you prefer to do it by hand.
-- (If a column already exists, MySQL will say so - that's fine.)
--
--   read_at               when the other side opened the chat and saw the message
--   deleted_for_customer  guest chose "Delete for me"
--   deleted_for_staff     staff chose "Delete for me" (shared staff inbox)
--   deleted_for_all       sender chose "Delete for everyone" (content is wiped)
-- ============================================================

ALTER TABLE live_chat_messages
    ADD COLUMN read_at DATETIME DEFAULT NULL,
    ADD COLUMN deleted_for_customer TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN deleted_for_staff TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN deleted_for_all TINYINT(1) NOT NULL DEFAULT 0;
