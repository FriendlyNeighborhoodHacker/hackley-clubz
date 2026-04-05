-- Migration 004: Add recurrence support to events
-- Adds recurrence_rule and recurrence_parent_id columns.
-- recurrence_rule  : NULL = does not repeat; 'weekly' | 'monthly_nth_weekday' | 'custom'
-- recurrence_parent_id : NULL for standalone/parent events;
--                        child occurrences point back to the first (parent) event row.
--                        Deleting the parent cascades to all children via FK.

ALTER TABLE events
  ADD COLUMN recurrence_rule       VARCHAR(50) NULL DEFAULT NULL
    COMMENT 'weekly | monthly_nth_weekday | custom | NULL = does not repeat'
    AFTER created_by_user_id,
  ADD COLUMN recurrence_parent_id  INT         NULL DEFAULT NULL
    COMMENT 'FK to events.id; set on child occurrences of a recurring series'
    AFTER recurrence_rule;

ALTER TABLE events
  ADD CONSTRAINT fk_events_recurrence
    FOREIGN KEY (recurrence_parent_id) REFERENCES events(id) ON DELETE CASCADE;

CREATE INDEX idx_events_recurrence_parent ON events(recurrence_parent_id);
