-- Migration 003: Split clubs.meets into meeting_days + meeting_location
--
-- Before running: backup your data.
-- The existing free-text "meets" value is migrated into meeting_location.

ALTER TABLE clubs
  ADD COLUMN meeting_days     VARCHAR(50)  DEFAULT NULL COMMENT 'Comma-separated 8-day-cycle day numbers, e.g. "1,3"'
    AFTER meets,
  ADD COLUMN meeting_location VARCHAR(255) DEFAULT NULL COMMENT 'Room / address free text'
    AFTER meeting_days;

-- Migrate existing data: copy old meets text into meeting_location
UPDATE clubs SET meeting_location = meets WHERE meets IS NOT NULL AND meets <> '';

-- Drop the old column
ALTER TABLE clubs DROP COLUMN meets;
