-- ============================================================
-- Migration: official admin login account
-- Creates (or updates) the admin dashboard login for
-- casavergararesort@gmail.com / admin2004
--
-- Safe to re-run: if a user with this email already exists it just
-- resets the password + role instead of creating a duplicate.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
--
-- Hash below was generated for the password: admin2004
-- (bcrypt, compatible with PHP's password_hash/password_verify)
-- ============================================================

INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_verified, status)
VALUES ('Casa Vergara', 'Admin', 'casavergararesort@gmail.com', '0970 323 6544',
'$2b$10$YjviX0E4lcwLWtH17aGIu.XwQ1LHai4NKODnzdFCtmHk450LNHJti', 'admin', 1, 'active')
ON DUPLICATE KEY UPDATE
    password_hash = '$2b$10$YjviX0E4lcwLWtH17aGIu.XwQ1LHai4NKODnzdFCtmHk450LNHJti',
    role = 'admin',
    is_verified = 1,
    status = 'active';
