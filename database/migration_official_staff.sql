-- ============================================================
-- Migration: official event-manager (staff) login account
-- Creates (or updates) the staff dashboard login for
-- louisediego.anover@cvsu.edu.ph / staff2004
--
-- Also removes the old placeholder test account
-- (admin@casavergara.test) that was seeded by schema.sql during
-- Phase 1 setup and is no longer needed.
--
-- Safe to re-run: if a user with this email already exists it just
-- resets the password + role instead of creating a duplicate.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
--
-- Hash below was generated for the password: staff2004
-- (bcrypt, compatible with PHP's password_hash/password_verify)
-- ============================================================

INSERT INTO users (first_name, last_name, email, phone, password_hash, role, is_verified, status)
VALUES ('Louise Diego', 'Anover', 'louisediego.anover@cvsu.edu.ph', '0917 000 0000',
'$2b$10$J.HM6.38h06Iliku4kK6v.AHpjeYcW4NcG5L4aOETXkkCOs.MueQW', 'event_manager', 1, 'active')
ON DUPLICATE KEY UPDATE
    password_hash = '$2b$10$J.HM6.38h06Iliku4kK6v.AHpjeYcW4NcG5L4aOETXkkCOs.MueQW',
    role = 'event_manager',
    is_verified = 1,
    status = 'active';

-- Remove the old placeholder staff/test account so it can no longer be
-- used to log in. Safe if it was already removed or never existed.
DELETE FROM users WHERE email = 'admin@casavergara.test';
