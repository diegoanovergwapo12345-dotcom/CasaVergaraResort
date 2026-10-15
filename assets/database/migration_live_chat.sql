-- ============================================================
-- Migration: live chat between guests and staff/admin
--
-- Replaces the AI assistant with real conversations stored in
-- MySQL. Safe to re-run.
--
-- How to run:
--   phpMyAdmin → casa_vergara → SQL tab → paste → Go
-- Tables are also created automatically on first chat API call.
-- ============================================================

CREATE TABLE IF NOT EXISTS live_chat_conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reservation_id VARCHAR(40) DEFAULT NULL,
    topic VARCHAR(80) DEFAULT NULL,
    last_message_at DATETIME DEFAULT NULL,
    last_message_preview VARCHAR(255) DEFAULT NULL,
    last_sender_role ENUM('customer', 'staff') DEFAULT NULL,
    customer_unread INT NOT NULL DEFAULT 0,
    staff_unread INT NOT NULL DEFAULT 0,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_chat_customer (customer_id),
    INDEX idx_chat_last (last_message_at),
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS live_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT NOT NULL,
    sender_id INT NOT NULL,
    sender_role ENUM('customer', 'staff') NOT NULL,
    sender_label VARCHAR(191) NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    topic VARCHAR(80) DEFAULT NULL,
    reservation_id VARCHAR(40) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_chat_msg_conv (conversation_id, id),
    FOREIGN KEY (conversation_id) REFERENCES live_chat_conversations(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS live_chat_presence (
    user_id INT PRIMARY KEY,
    last_seen_at DATETIME NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
