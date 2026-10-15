-- ============================================================
-- Migration: GIF / photo support in live chat
-- Safe to re-run. (The chat API also adds this column automatically.)
-- phpMyAdmin -> casa_vergara -> SQL tab -> paste -> Go
-- ============================================================
ALTER TABLE live_chat_messages
  ADD COLUMN IF NOT EXISTS image_url VARCHAR(500) DEFAULT NULL AFTER body;
