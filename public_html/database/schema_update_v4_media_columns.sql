-- ---------------------------------------------------------------------------
-- v4: media columns for content items (portable re-statement of v2)
--
-- These columns are the ones the admin editors write: an optional image, PDF
-- and video URL stored directly on each content row.
--
-- schema_update_v2.sql already adds every one of them, so on a database where
-- v2 applied cleanly this file changes nothing. It exists because v2 uses
-- ALTER TABLE ... ADD COLUMN IF NOT EXISTS, which is MariaDB syntax; MySQL
-- 5.7 and 8 reject it outright, and a host running MySQL would have failed v2
-- partway and be left without these columns. The editors then fail with
-- "Unknown column 'image_path'".
--
-- Every statement here is guarded against information_schema instead, which
-- both engines accept, and each is a no-op when the column already exists.
-- Safe to run on any host, and safe to run more than once.
--
-- research_projects is deliberately absent: it stores its video in video_path
-- and its PDFs in the page_pdfs table, and schema.sql already declares both.
--
-- Apply in phpMyAdmin: select the database, open the SQL tab, paste, run.
-- Verified by building a database from schema.sql + v3 + this file with v2
-- skipped entirely; all thirteen columns were present afterwards.
-- ---------------------------------------------------------------------------

SET @db := DATABASE();

-- community_engagement_items
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'community_engagement_items' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `community_engagement_items` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- courses
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `courses` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'pdf_path') = 0,
    'ALTER TABLE `courses` ADD COLUMN `pdf_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'courses' AND COLUMN_NAME = 'video_url') = 0,
    'ALTER TABLE `courses` ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- media_appearances
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'media_appearances' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `media_appearances` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'media_appearances' AND COLUMN_NAME = 'pdf_path') = 0,
    'ALTER TABLE `media_appearances` ADD COLUMN `pdf_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'media_appearances' AND COLUMN_NAME = 'video_url') = 0,
    'ALTER TABLE `media_appearances` ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- presentations
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'presentations' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `presentations` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'presentations' AND COLUMN_NAME = 'pdf_path') = 0,
    'ALTER TABLE `presentations` ADD COLUMN `pdf_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'presentations' AND COLUMN_NAME = 'video_url') = 0,
    'ALTER TABLE `presentations` ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- professional_service_items
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'professional_service_items' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `professional_service_items` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- publications
SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'publications' AND COLUMN_NAME = 'image_path') = 0,
    'ALTER TABLE `publications` ADD COLUMN `image_path` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'publications' AND COLUMN_NAME = 'video_url') = 0,
    'ALTER TABLE `publications` ADD COLUMN `video_url` VARCHAR(500) DEFAULT NULL',
    'DO 0'));
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Verification: every row below should report OK.
-- ---------------------------------------------------------------------------
SELECT t.TABLE_NAME, t.COLUMN_NAME, 'OK' AS status
  FROM information_schema.COLUMNS t
 WHERE t.TABLE_SCHEMA = DATABASE()
   AND t.COLUMN_NAME IN ('image_path', 'pdf_path', 'video_url')
 ORDER BY t.TABLE_NAME, t.COLUMN_NAME;
