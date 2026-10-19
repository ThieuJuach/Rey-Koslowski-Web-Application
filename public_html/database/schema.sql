-- ============================================================
-- Rey Koslowski Academic Website - MySQL Database Schema
-- For use with XAMPP (localhost) / MySQL / MariaDB
-- ============================================================
-- 
-- SETUP INSTRUCTIONS:
-- 1. Open phpMyAdmin at http://localhost/phpmyadmin
-- 2. Create a new database called "rey_koslowski_site"
-- 3. Select the database, click "Import", and upload this file
-- OR run: mysql -u root -p rey_koslowski_site < schema.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS `rey_koslowski_site`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `rey_koslowski_site`;

-- ============================================================
-- 1. ADMIN USERS
-- ============================================================
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Administrator account.
--
-- No usable password ships in this repository. Generate a hash for a password of
-- your own and paste it in place of the placeholder below:
--
--   php -r "echo password_hash('your-password-here', PASSWORD_BCRYPT, ['cost'=>12]);"
--
-- The placeholder is deliberately NOT a valid bcrypt hash, so this account cannot
-- be signed into until you replace it. Re-running this file never overwrites an
-- existing admin row.
INSERT INTO `admins` (`username`, `password_hash`, `email`)
VALUES ('admin', 'REPLACE_WITH_A_BCRYPT_HASH', 'admin@example.com')
ON DUPLICATE KEY UPDATE `username` = `username`;

-- ============================================================
-- 2. OTP CODES (for 2FA)
-- ============================================================
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `admin_id` INT UNSIGNED NOT NULL,
  `code` VARCHAR(10) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_otp_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 3. PAGES
-- ============================================================
CREATE TABLE IF NOT EXISTS `pages` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(200) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `meta_description` VARCHAR(300) DEFAULT NULL,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `pages` (`slug`, `title`, `content`) VALUES
  ('home', 'Home', '<p>Welcome to Rey Koslowski\'s academic website.</p>'),
  ('research', 'Research', '<p>Research interests and projects.</p>'),
  ('teaching', 'Teaching', '<p>Teaching portfolio and courses.</p>'),
  ('publications', 'Publications', '<p>Selected publications.</p>'),
  ('migration-studies', 'Migration Studies', '<p>Migration studies at UAlbany.</p>'),
  ('media-appearances', 'Media Appearances', '<p>Media appearances and interviews.</p>'),
  ('services', 'Services', '<p>Blog posts, galleries, and more.</p>'),
  ('contact', 'Contact', '<p>Contact information.</p>'),
  ('professional-service', 'Professional Service', NULL),
  ('community-engagement', 'Community Engagement', NULL),
  ('presentations', 'Presentations', NULL)
ON DUPLICATE KEY UPDATE `slug` = `slug`;

-- ============================================================
-- 4. PAGE SECTIONS (modular, reorderable content blocks)
-- ============================================================
CREATE TABLE IF NOT EXISTS `page_sections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `page_id` INT UNSIGNED NOT NULL,
  `section_type` ENUM('text', 'image', 'video', 'gallery', 'embed', 'custom') DEFAULT 'text',
  `title` VARCHAR(200) DEFAULT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `media_url` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sections_page` FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_sections_page_order` ON `page_sections` (`page_id`, `display_order`);

-- ============================================================
-- 5. PAGE IMAGES (for admin-managed photos per page)
-- ============================================================
CREATE TABLE IF NOT EXISTS `page_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `page_slug` VARCHAR(50) NOT NULL,
  `image_path` VARCHAR(500) NOT NULL,
  `alt_text` VARCHAR(300) DEFAULT NULL,
  `caption` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `image_type` ENUM('featured', 'profile', 'gallery', 'content') DEFAULT 'featured',
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_page_images_slug` ON `page_images` (`page_slug`, `display_order`);

-- ============================================================
-- 6. PUBLICATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `publications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('book', 'edited_volume', 'journal_article', 'book_chapter', 'working_paper', 'report', 'other') NOT NULL DEFAULT 'journal_article',
  `title` VARCHAR(500) NOT NULL,
  `authors` VARCHAR(500) DEFAULT NULL,
  `journal_or_publisher` VARCHAR(300) DEFAULT NULL,
  `year` SMALLINT UNSIGNED DEFAULT NULL,
  `volume` VARCHAR(50) DEFAULT NULL,
  `issue` VARCHAR(50) DEFAULT NULL,
  `pages` VARCHAR(50) DEFAULT NULL,
  `doi` VARCHAR(200) DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `pdf_path` VARCHAR(500) DEFAULT NULL,
  `abstract` TEXT DEFAULT NULL,
  `citation` TEXT DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_publications_type_year` ON `publications` (`type`, `year` DESC);
CREATE INDEX IF NOT EXISTS `idx_publications_order` ON `publications` (`display_order`);

-- ============================================================
-- 7. RESEARCH PROJECTS
-- ============================================================
CREATE TABLE IF NOT EXISTS `research_projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('ongoing', 'completed', 'upcoming') DEFAULT 'ongoing',
  `funding_source` VARCHAR(200) DEFAULT NULL,
  `start_year` SMALLINT UNSIGNED DEFAULT NULL,
  `end_year` SMALLINT UNSIGNED DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `video_path` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 8. COURSES
-- ============================================================
CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `level` ENUM('undergraduate', 'graduate') DEFAULT 'undergraduate',
  `description` TEXT DEFAULT NULL,
  `institution` VARCHAR(200) DEFAULT 'University at Albany (SUNY)',
  `semester` VARCHAR(50) DEFAULT NULL,
  `syllabus_path` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 1,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 9. MEDIA APPEARANCES
-- ============================================================
CREATE TABLE IF NOT EXISTS `media_appearances` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `type` ENUM('print', 'broadcast', 'podcast', 'op_ed', 'online', 'other') DEFAULT 'print',
  `title` VARCHAR(500) NOT NULL,
  `outlet` VARCHAR(200) DEFAULT NULL,
  `date` DATE DEFAULT NULL,
  `year` SMALLINT UNSIGNED DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_media_year` ON `media_appearances` (`year` DESC);

-- ============================================================
-- 10. FILE UPLOADS
-- ============================================================
CREATE TABLE IF NOT EXISTS `uploads` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `filename` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `file_type` ENUM('image', 'pdf', 'video', 'document', 'other') DEFAULT 'other',
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` INT UNSIGNED DEFAULT NULL,
  `file_path` VARCHAR(500) NOT NULL,
  `alt_text` VARCHAR(300) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `uploaded_by` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_uploads_admin` FOREIGN KEY (`uploaded_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 11. SITE SETTINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS `site_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
  ('site_title', 'Rey Koslowski'),
  ('site_subtitle', 'Professor of Political Science'),
  ('contact_email', 'rkoslowski@albany.edu'),
  ('contact_phone', '518-442-5314'),
  ('office_address', 'Department of Political Science, Rockefeller College, 135 Western Ave., Albany, NY 12222'),
  ('google_scholar_url', ''),
  ('researchgate_url', ''),
  ('orcid_url', ''),
  ('university_url', 'https://www.albany.edu/rockefeller'),
  ('cv_url', ''),
  ('cv_file_path', ''),
  ('welcome_message', 'Welcome to my academic website! Feel free to explore my research, publications, and teaching activities.'),
  ('welcome_message_enabled', '1'),
  ('footer_about_text', 'Professor of Political Science & Director, Master of International Affairs Program. Rockefeller College of Public Affairs and Policy, University at Albany (SUNY).'),
  ('footer_affiliation_1_name', 'UAlbany Political Science'),
  ('footer_affiliation_1_url', 'https://www.albany.edu/rockefeller/political-science'),
  ('footer_affiliation_2_name', 'Rockefeller College'),
  ('footer_affiliation_2_url', 'https://www.albany.edu/rockefeller'),
  ('footer_affiliation_3_name', 'UAlbany Faculty Profile'),
  ('footer_affiliation_3_url', 'https://www.albany.edu/~rk289758/')
ON DUPLICATE KEY UPDATE `setting_key` = `setting_key`;

-- ============================================================
-- 12. MIGRATION STUDIES FACULTY
-- ============================================================
CREATE TABLE IF NOT EXISTS `migration_faculty` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(200) NOT NULL,
  `department` VARCHAR(200) DEFAULT NULL,
  `profile_url` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 13. MIGRATION STUDIES COURSES
-- ============================================================
CREATE TABLE IF NOT EXISTS `migration_courses` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) DEFAULT NULL,
  `title` VARCHAR(200) NOT NULL,
  `level` ENUM('undergraduate', 'graduate') DEFAULT 'graduate',
  `description` TEXT DEFAULT NULL,
  `syllabus_url` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 14. PRESENTATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS `presentations` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `event_name` VARCHAR(300) DEFAULT NULL,
  `location` VARCHAR(300) DEFAULT NULL,
  `presentation_date` DATE DEFAULT NULL,
  `end_date` DATE DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('conference', 'invited', 'workshop', 'seminar', 'panel', 'keynote', 'other') DEFAULT 'conference',
  `status` ENUM('upcoming', 'completed') DEFAULT 'upcoming',
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_current` TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE INDEX IF NOT EXISTS `idx_presentations_date` ON `presentations` (`presentation_date` DESC);
CREATE INDEX IF NOT EXISTS `idx_presentations_status` ON `presentations` (`status`, `is_current`);

-- ============================================================
-- 15. PROFESSIONAL SERVICE ITEMS (blog-post style)
-- ============================================================
CREATE TABLE IF NOT EXISTS `professional_service_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `start_year` SMALLINT UNSIGNED DEFAULT NULL,
  `end_year` SMALLINT UNSIGNED DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 16. COMMUNITY ENGAGEMENT ITEMS (blog-post style)
-- ============================================================
CREATE TABLE IF NOT EXISTS `community_engagement_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(500) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `category` VARCHAR(100) DEFAULT NULL,
  `event_date` DATE DEFAULT NULL,
  `location` VARCHAR(300) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 17. BLOG POSTS (legacy)
-- ============================================================
CREATE TABLE IF NOT EXISTS `blog_posts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `content` LONGTEXT DEFAULT NULL,
  `excerpt` TEXT DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `is_published` TINYINT(1) DEFAULT 1,
  `published_at` DATETIME DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- 18. GALLERIES (legacy)
-- ============================================================
CREATE TABLE IF NOT EXISTS `galleries` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `gallery_images` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `gallery_id` INT UNSIGNED NOT NULL,
  `image_path` VARCHAR(500) NOT NULL,
  `caption` VARCHAR(500) DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_gallery_images` FOREIGN KEY (`gallery_id`) REFERENCES `galleries`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 19. SERVICE ITEMS (legacy)
-- ============================================================
CREATE TABLE IF NOT EXISTS `service_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(300) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `link_url` VARCHAR(500) DEFAULT NULL,
  `image_path` VARCHAR(500) DEFAULT NULL,
  `display_order` INT DEFAULT 0,
  `is_visible` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ============================================================
-- DONE! Your database is ready.
-- ============================================================