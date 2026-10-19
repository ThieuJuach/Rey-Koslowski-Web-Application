-- ============================================================
-- Schema Update v2: Add PDF, image, video fields to content tables
-- Run after initial schema.sql
-- ============================================================

-- Research projects: add image and video (already has image_path, video_path)
-- No changes needed for research_projects

-- Courses: add image_path for cover photos
ALTER TABLE `courses` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `syllabus_path`;
ALTER TABLE `courses` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `image_path`;
ALTER TABLE `courses` ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) DEFAULT NULL AFTER `video_url`;

-- Publications: already has pdf_path, add video_url and image_path
ALTER TABLE `publications` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `pdf_path`;
ALTER TABLE `publications` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `image_path`;

-- Presentations: add image, video, pdf fields
ALTER TABLE `presentations` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `link_url`;
ALTER TABLE `presentations` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `image_path`;
ALTER TABLE `presentations` ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) DEFAULT NULL AFTER `video_url`;

-- Media appearances: add video_url and pdf_path
ALTER TABLE `media_appearances` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `link_url`;
ALTER TABLE `media_appearances` ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) DEFAULT NULL AFTER `video_url`;
ALTER TABLE `media_appearances` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `pdf_path`;

-- Professional service items: add image and pdf
ALTER TABLE `professional_service_items` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `link_url`;
ALTER TABLE `professional_service_items` ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) DEFAULT NULL AFTER `image_path`;
ALTER TABLE `professional_service_items` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `pdf_path`;

-- Community engagement items: add image and pdf
ALTER TABLE `community_engagement_items` ADD COLUMN IF NOT EXISTS `image_path` VARCHAR(500) DEFAULT NULL AFTER `link_url`;
ALTER TABLE `community_engagement_items` ADD COLUMN IF NOT EXISTS `pdf_path` VARCHAR(500) DEFAULT NULL AFTER `image_path`;
ALTER TABLE `community_engagement_items` ADD COLUMN IF NOT EXISTS `video_url` VARCHAR(500) DEFAULT NULL AFTER `pdf_path`;

-- Page PDFs table (for admin to attach PDFs to any page)
CREATE TABLE IF NOT EXISTS `page_pdfs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `page_slug` VARCHAR(50) NOT NULL,
  `title` VARCHAR(300) NOT NULL,
  `pdf_path` VARCHAR(500) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_page_pdfs_slug` ON `page_pdfs` (`page_slug`, `display_order`);
