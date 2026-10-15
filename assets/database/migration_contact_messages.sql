-- ============================================================
-- Migration: contact form submissions
-- Run this ONCE if you already imported an earlier database/schema.sql
-- (i.e. your `casa_vergara` database already exists).
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(191) NOT NULL,
    phone VARCHAR(30) NULL,
    subject VARCHAR(100) NOT NULL DEFAULT 'General Inquiry',
    message TEXT NOT NULL,
    emailed_admin TINYINT(1) NOT NULL DEFAULT 0,
    admin_reply TEXT NULL,
    replied_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
