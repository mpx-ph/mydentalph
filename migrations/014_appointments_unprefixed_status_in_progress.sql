-- Same as 013, for legacy unprefixed `appointments` table (see tenantize_clinic_tables.sql).
-- Run this if 013 was skipped because tbl_appointments does not exist.

UPDATE appointments
SET status = 'pending'
WHERE status = '' OR status IS NULL;

ALTER TABLE appointments
  MODIFY COLUMN status
    ENUM('pending','confirmed','scheduled','in_progress','completed','cancelled','no_show')
    DEFAULT 'pending';
