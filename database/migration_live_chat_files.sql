-- ============================================================
-- Migration: file attachments (any type, up to 25 MB) in live chat
-- Safe to re-run. (The chat API also adds these columns automatically.)
-- phpMyAdmin -> casa_vergara -> SQL tab -> paste -> Go
-- ============================================================
ALTER TABLE live_chat_messages
  ADD COLUMN IF NOT EXISTS file_path VARCHAR(500) DEFAULT NULL AFTER image_url,
  ADD COLUMN IF NOT EXISTS file_name VARCHAR(255) DEFAULT NULL AFTER file_path,
  ADD COLUMN IF NOT EXISTS file_size INT UNSIGNED DEFAULT NULL AFTER file_name;
