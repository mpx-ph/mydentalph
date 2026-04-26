-- Add in_progress and scheduled to appointment status ENUM (tbl_appointments).
-- Before this migration, setting status to 'in_progress' could be stored as '' (invalid ENUM),
-- which produced an empty status label on the Daily Schedule.
-- If your database uses unprefixed `appointments` only, run 014_appointments_unprefixed_status_in_progress.sql instead (or in addition if both tables exist).

UPDATE tbl_appointments
SET status = 'pending'
WHERE status = '' OR status IS NULL;

ALTER TABLE tbl_appointments
  MODIFY COLUMN status
    ENUM('pending','confirmed','scheduled','in_progress','completed','cancelled','no_show')
    DEFAULT 'pending';
