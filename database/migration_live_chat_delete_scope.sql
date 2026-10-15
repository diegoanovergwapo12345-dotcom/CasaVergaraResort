-- ============================================================
-- Migration: delete a chat "for me" or "for everyone"
-- Safe to re-run. (The chat API also creates this table automatically.)
-- phpMyAdmin -> casa_vergara -> SQL tab -> paste -> Go
-- ============================================================
CREATE TABLE IF NOT EXISTS live_chat_staff_hidden (
    conversation_id INT NOT NULL,
    user_id INT NOT NULL,
    hidden_before_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (conversation_id, user_id)
) ENGINE=InnoDB;
