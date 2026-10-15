-- ============================================================
-- Migration: Allow guests to post multiple reviews
--
-- The original `reviews` table had a UNIQUE KEY on user_id, so a guest
-- could only ever have one review (posting again silently edited it).
-- This migration replaces that constraint with a normal (non-unique)
-- index — still useful for "my reviews" lookups, just no longer unique.
--
-- IMPORTANT: the new index must be added BEFORE the old unique index is
-- dropped. The unique index is what backs the reviews.user_id -> users.id
-- foreign key, and MySQL refuses to drop an index a foreign key still
-- depends on ("#1553 - Cannot drop index ... needed in a foreign key
-- constraint") unless another index already covers that column.
--
-- Safe to re-run: both steps are wrapped so they won't fail if already
-- applied.
--
-- How to run:
--   1. Open http://localhost/phpmyadmin
--   2. Click the "casa_vergara" database, then the "SQL" tab.
--   3. Paste this whole file's contents and click "Go".
-- ============================================================

-- 1) Add the plain index FIRST, so the foreign key has something else
--    to lean on once the unique index is gone.
SET @index_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND INDEX_NAME = 'idx_review_user'
);

SET @add_index_sql := IF(@index_exists = 0,
  'ALTER TABLE reviews ADD INDEX idx_review_user (user_id)',
  'SELECT 1'
);

PREPARE stmt FROM @add_index_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Now it's safe to drop the old unique constraint (name may vary
--    slightly depending on your MySQL version; uniq_review_user is what
--    database/migration_reviews.sql originally created).
SET @constraint_exists := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'reviews'
    AND INDEX_NAME = 'uniq_review_user'
);

SET @drop_sql := IF(@constraint_exists > 0,
  'ALTER TABLE reviews DROP INDEX uniq_review_user',
  'SELECT 1'
);

PREPARE stmt FROM @drop_sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
