-- ============================================================
-- Migration: server-side login lockout after repeated wrong passwords
--
-- Adds two columns to `users` so api/auth/login.php can actually enforce
-- a lockout (not just a client-side UI nudge) after too many consecutive
-- wrong-password attempts against a real account:
--
--   failed_login_attempts  - consecutive bad-password count, reset to 0
--                             on a successful login or once locked out.
--   lockout_until           - if set and in the future, login.php refuses
--                             the attempt (and suggests password reset)
--                             until this timestamp passes.
--
-- Safe to re-run.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS failed_login_attempts INT NOT NULL DEFAULT 0 AFTER status,
    ADD COLUMN IF NOT EXISTS lockout_until DATETIME DEFAULT NULL AFTER failed_login_attempts;
