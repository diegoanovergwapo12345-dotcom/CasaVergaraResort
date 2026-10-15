-- ============================================================
-- Migration: profile photo support for admin/event manager settings
-- Run this ONCE if you already imported an earlier database/schema.sql
-- (i.e. your `casa_vergara` database already exists).
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS avatar_path VARCHAR(255) DEFAULT NULL AFTER password_hash;
