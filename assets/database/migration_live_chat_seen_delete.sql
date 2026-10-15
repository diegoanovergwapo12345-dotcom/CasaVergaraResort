-- ============================================================
-- Migration: "Seen" receipts + delete chat
-- Safe to re-run. (The chat API also adds these columns automatically.)
-- phpMyAdmin -> casa_vergara -> SQL tab -> paste -> Go
-- ============================================================
ALTER TABLE live_chat_messages
  ADD COLUMN IF NOT EXISTS read_at DATETIME DEFAULT NULL;
UPDATE live_chat_messages SET read_at = created_at WHERE read_at IS NULL;

ALTER TABLE live_chat_conversations
  ADD COLUMN IF NOT EXISTS customer_cleared_id INT NOT NULL DEFAULT 0;
