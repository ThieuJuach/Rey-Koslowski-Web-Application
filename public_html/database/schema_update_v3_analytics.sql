-- ============================================================
-- v3: First-party page analytics
--
-- Apply once, to the same database as schema.sql:
--   hPanel -> Databases -> phpMyAdmin -> SQL tab -> paste -> Go
--
-- Safe to run more than once: every statement is IF NOT EXISTS.
--
-- PRIVACY NOTES
-- No raw IP address and no user-agent string is ever stored. A visitor is
-- represented only by `visitor_hash`, a SHA-256 digest of
-- (IP + user agent + the current date + a server-side secret). Because the date
-- is part of the input, the digest changes every midnight, so the same person on
-- two different days cannot be linked. It exists purely to count "unique
-- visitors today" without retaining anything identifying.
--
-- Rows are only ever written for visitors who clicked Accept on the consent
-- banner; see api/track.php and the consent gate in assets/js/main.js.
-- ============================================================

CREATE TABLE IF NOT EXISTS `page_views` (
  `id`            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

  -- Request path only, e.g. '/publications.php'. Never a full URL and never a
  -- query string, so nothing a visitor typed can end up in here.
  `path`          VARCHAR(255)  NOT NULL,

  -- Host portion of the referrer, e.g. 'google.com'. The full referring URL is
  -- deliberately discarded: the host answers "where did they come from" without
  -- recording the specific page or any search terms it may contain.
  `referrer_host` VARCHAR(255)  DEFAULT NULL,

  -- Daily-rotating, non-reversible visitor token. See PRIVACY NOTES above.
  `visitor_hash`  CHAR(64)      NOT NULL,

  -- Crawlers are recorded but excluded from the dashboard by default, so the
  -- numbers reflect people rather than bots.
  `is_bot`        TINYINT(1)    NOT NULL DEFAULT 0,

  `viewed_at`     DATETIME      NOT NULL,

  -- Dashboard queries filter by date, group by path, and count distinct
  -- visitors per day; one index for each of those access patterns.
  INDEX `idx_viewed_at`    (`viewed_at`),
  INDEX `idx_path_date`    (`path`, `viewed_at`),
  INDEX `idx_visitor_date` (`visitor_hash`, `viewed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
