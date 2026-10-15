-- ============================================================
-- Migration: password-reset OTP lockout
--
-- Adds two columns to `users` so the forgot-password flow can block an
-- account from receiving / using reset codes after too many CONSECUTIVE
-- wrong OTP entries:
--
--   reset_code_attempts  - consecutive wrong-code count, reset to 0 on a
--                          correct code, a successful reset, or once the
--                          block is applied.
--   reset_block_until    - if set and in the future, forgot-password.php
--                          refuses to send a new code and verify-reset-code
--                          / reset-password refuse the attempt.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS reset_code_attempts INT NOT NULL DEFAULT 0 AFTER reset_expires,
    ADD COLUMN IF NOT EXISTS reset_block_until DATETIME DEFAULT NULL AFTER reset_code_attempts;
