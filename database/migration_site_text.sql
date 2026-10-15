-- Adds a generic key-value table for admin-managed TEXT on public pages
-- (Admin > Site Content > Page Text). Each row is one named "field" (e.g.
-- the homepage hero heading). Fields are defined in code (see
-- config/site_text.php); this table only ever stores the override an
-- admin has typed for a field. A field with no row here just falls back to
-- the site's original wording, so this migration is safe to run on a fresh
-- install or an existing one — same pattern as migration_site_images.sql.
--
-- Run this once: mysql -u <user> -p <database> < database/migration_site_text.sql

CREATE TABLE IF NOT EXISTS site_text (
    text_key VARCHAR(64) PRIMARY KEY,
    text_value TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL
) ENGINE=InnoDB;
