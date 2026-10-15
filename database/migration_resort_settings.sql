-- ============================================================
-- Migration: resort settings (Resort Name / Email / Phone / Address)
-- Run this ONCE if you already imported an earlier database/schema.sql
-- (i.e. your `casa_vergara` database already exists).
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

CREATE TABLE IF NOT EXISTS resort_settings (
    id INT PRIMARY KEY DEFAULT 1,
    resort_name VARCHAR(150) NOT NULL DEFAULT 'Casa Vergara Resort',
    email VARCHAR(191) NOT NULL DEFAULT 'info@casavergara.com',
    phone VARCHAR(30) NOT NULL DEFAULT '+1 (555) 123-4567',
    address VARCHAR(255) NOT NULL DEFAULT '123 Paradise Boulevard, Tropical Island, TI 12345',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT resort_settings_single_row CHECK (id = 1)
) ENGINE=InnoDB;

INSERT INTO resort_settings (id, resort_name, email, phone, address)
VALUES (1, 'Casa Vergara Resort', 'info@casavergara.com', '+1 (555) 123-4567', '123 Paradise Boulevard, Tropical Island, TI 12345')
ON DUPLICATE KEY UPDATE id = id;
