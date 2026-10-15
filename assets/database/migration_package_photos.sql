-- ============================================================
-- Migration: admin-managed package photos.
--
-- Room packages already had an `image_path`, but it could only be
-- changed by editing the database by hand. Event packages had no photo
-- at all (the booking/reservation pages used a hardcoded stock image).
--
-- This migration adds `image_path` to event_packages so Admin > Packages
-- can upload a photo for every package. Uploaded photos are stored in
-- uploads/packages/ and saved here as a site-relative path, e.g.
-- 'uploads/packages/pkg_ab12cd34.jpg'.
--
-- Run once:
--   mysql -u root casa_vergara < database/migration_package_photos.sql
-- ============================================================

ALTER TABLE event_packages
    ADD COLUMN IF NOT EXISTS image_path VARCHAR(255) DEFAULT NULL AFTER is_custom;
