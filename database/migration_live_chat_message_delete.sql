-- ============================================================
-- Migration: Messenger-style "Remove for you" / "Unsend" on single messages
-- Safe to re-run. (The chat API also adds these automatically.)
-- phpMyAdmin -> casa_vergara -> SQL tab -> paste -> Go
-- ============================================================
ALTER TABLE live_chat_messages
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS live_chat_hidden_messages (
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (message_id, user_id),
    INDEX idx_hidden_user (user_id)
) ENGINE=InnoDB;
