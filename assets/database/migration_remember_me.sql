-- ============================================================
-- "Remember me" / saved accounts on this browser
-- ============================================================
-- Backs the Facebook-style account picker on pages/login.html: when a guest
-- ticks "Remember me", api/auth/login.php issues a long-lived token that is
-- stored here (hashed) and in an httpOnly cookie on the browser. Clicking
-- that saved account later signs them straight in via
-- api/auth/remember-login.php — no password needed.
--
-- Split into selector + validator so a stolen database row can't be replayed:
-- the selector is the lookup key, and only a HASH of the validator is stored.
--
-- Run this once in phpMyAdmin against the resort database.
-- ============================================================

CREATE TABLE IF NOT EXISTS remember_tokens (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    selector        VARCHAR(32)  NOT NULL,
    validator_hash  VARCHAR(255) NOT NULL,
    expires_at      DATETIME     NOT NULL,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_used_at    DATETIME     DEFAULT NULL,
    UNIQUE KEY uniq_remember_selector (selector),
    KEY idx_remember_user (user_id),
    CONSTRAINT fk_remember_tokens_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
