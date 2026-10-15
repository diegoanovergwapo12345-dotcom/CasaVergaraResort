-- Adds a generic key-value table for admin-managed photos on public pages
-- (Admin > Site Content). Each row is one named "slot" (e.g. the landing
-- page hero photo). Slots are defined in code (see config/site_images.php);
-- this table only ever stores the override an admin has uploaded for a
-- slot. A slot with no row here just falls back to the site's original
-- default image, so this migration is safe to run on a fresh install or
-- an existing one.
--
-- Run this once: mysql -u <user> -p <database> < database/migration_site_images.sql

CREATE TABLE IF NOT EXISTS site_images (
    slot_key VARCHAR(64) PRIMARY KEY,
    image_path VARCHAR(255) NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL
) ENGINE=InnoDB;
