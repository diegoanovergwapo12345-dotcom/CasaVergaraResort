-- ============================================================
-- Migration: admin-managed payment QR code
--
-- Adds two columns to `resort_settings` so the admin can upload the real
-- GCash / e-wallet QR image in Admin > Settings and have it show up on
-- pages/payment.html automatically.
--
--   payment_qr_path  - relative path of the uploaded image, e.g.
--                      uploads/payments/qr_ab12cd34.png
--   payment_qr_label - short caption shown under the QR, e.g.
--                      "GCash - Casa Vergara Resort - 0917 123 4567"
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
--
-- Safe to run more than once: each ALTER is wrapped so an existing column
-- does not abort the migration.
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

SET @add_qr_path := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE resort_settings ADD COLUMN payment_qr_path VARCHAR(255) NULL AFTER address',
        'SELECT "payment_qr_path already exists"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resort_settings' AND COLUMN_NAME = 'payment_qr_path'
);
PREPARE stmt FROM @add_qr_path; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @add_qr_label := (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE resort_settings ADD COLUMN payment_qr_label VARCHAR(150) NOT NULL DEFAULT "" AFTER payment_qr_path',
        'SELECT "payment_qr_label already exists"'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resort_settings' AND COLUMN_NAME = 'payment_qr_label'
);
PREPARE stmt FROM @add_qr_label; EXECUTE stmt; DEALLOCATE PREPARE stmt;

INSERT INTO resort_settings (id, resort_name, email, phone, address)
VALUES (1, 'Casa Vergara Resort', 'info@casavergara.com', '+1 (555) 123-4567', '123 Paradise Boulevard, Tropical Island, TI 12345')
ON DUPLICATE KEY UPDATE id = id;
