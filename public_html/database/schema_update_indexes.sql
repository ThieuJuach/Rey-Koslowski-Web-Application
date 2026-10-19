-- ============================================================
-- Schema Update: Performance Indexes
-- Run once on the live database after deploying.
-- All statements use IF NOT EXISTS — safe to re-run.
-- ============================================================

-- ── research_projects ──
-- getCurrentResearch(): WHERE (status='ongoing' OR is_current=1) AND is_visible=1 ORDER BY display_order
-- getResearchProjects(status): WHERE status=? AND is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_research_current`
    ON `research_projects` (`is_current`, `is_visible`, `display_order`);

CREATE INDEX IF NOT EXISTS `idx_research_status`
    ON `research_projects` (`status`, `is_visible`, `display_order`);

-- ── courses ──
-- getCurrentCourses(): WHERE is_current=1 AND is_visible=1 ORDER BY display_order
-- getCourses(level): WHERE level=? AND is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_courses_current`
    ON `courses` (`is_current`, `is_visible`, `display_order`);

CREATE INDEX IF NOT EXISTS `idx_courses_level`
    ON `courses` (`level`, `is_visible`, `display_order`);

-- ── publications ──
-- getCurrentPublications(): WHERE is_current=1 AND is_visible=1 ORDER BY year DESC, display_order
-- getPublications(): WHERE is_visible=1 ORDER BY year DESC, display_order
CREATE INDEX IF NOT EXISTS `idx_publications_current`
    ON `publications` (`is_current`, `is_visible`, `year`, `display_order`);

CREATE INDEX IF NOT EXISTS `idx_publications_visible`
    ON `publications` (`is_visible`, `year`, `display_order`);

-- ── presentations ──
-- getCurrentPresentations(): WHERE is_current=1 AND is_visible=1 ORDER BY presentation_date
-- (idx_presentations_status covers status+is_current; add coverage for is_visible+date)
CREATE INDEX IF NOT EXISTS `idx_presentations_current`
    ON `presentations` (`is_current`, `is_visible`, `presentation_date`);

CREATE INDEX IF NOT EXISTS `idx_presentations_visible`
    ON `presentations` (`status`, `is_visible`, `presentation_date`);

-- ── page_images ──
-- getPageFeaturedImage(): WHERE page_slug=? AND image_type='featured' AND is_visible=1 ORDER BY display_order
-- getProfileImage():      WHERE page_slug='home' AND image_type='profile' AND is_visible=1 ORDER BY display_order
-- Better composite than the existing (page_slug, display_order) alone:
CREATE INDEX IF NOT EXISTS `idx_page_images_type`
    ON `page_images` (`page_slug`, `image_type`, `is_visible`, `display_order`);

-- ── migration_faculty ──
-- getMigrationFaculty(): WHERE is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_migration_faculty_visible`
    ON `migration_faculty` (`is_visible`, `display_order`);

-- ── migration_courses ──
-- getMigrationCourses(): WHERE is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_migration_courses_visible`
    ON `migration_courses` (`is_visible`, `display_order`);

-- ── professional_service_items ──
-- getProfessionalServiceItems(): WHERE is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_service_items_visible`
    ON `professional_service_items` (`is_visible`, `display_order`);

-- ── community_engagement_items ──
-- getCommunityEngagementItems(): WHERE is_visible=1 ORDER BY display_order
CREATE INDEX IF NOT EXISTS `idx_community_items_visible`
    ON `community_engagement_items` (`is_visible`, `display_order`);

-- ── otp_codes ──
-- Cleanup/lookup by expiry
CREATE INDEX IF NOT EXISTS `idx_otp_expires`
    ON `otp_codes` (`expires_at`, `used`);

-- ── Done ──
