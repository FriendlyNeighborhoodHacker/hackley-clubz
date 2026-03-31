-- Migration 002: Split allowed_email_domains into student_email_domains and adult_email_domains
--
-- Previously a single 'allowed_email_domains' setting held a comma-separated
-- list of all permitted registration domains.  This migration replaces it with
-- two separate settings so that student and adult domains can be managed
-- independently, each still accepting a comma-separated list of domains.
--
-- Run this on an existing installation that was set up with migration 001.

-- Add the two new settings (using the old value if present, otherwise defaults)
INSERT INTO settings (key_name, value)
SELECT 'student_email_domains',
       CASE
         WHEN (SELECT value FROM settings WHERE key_name = 'allowed_email_domains') IS NOT NULL
         THEN (SELECT value FROM settings WHERE key_name = 'allowed_email_domains')
         ELSE 'students.hackleyschool.org'
       END
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO settings (key_name, value)
VALUES ('adult_email_domains', 'hackleyschool.org')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- NOTE: After running this migration, review the student_email_domains setting.
-- It was seeded from the old combined list.  You should edit it via the admin
-- settings page so that it contains only student domains, and set
-- adult_email_domains to contain only faculty/adult domains.

-- Remove the old unified setting (safe once the two new settings are confirmed)
DELETE FROM settings WHERE key_name = 'allowed_email_domains';
