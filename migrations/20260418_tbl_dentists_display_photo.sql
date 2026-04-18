-- Dentist display IDs (D-YYYY-XXXXX) and profile photos for tbl_dentists.
-- Run once on existing databases created before this change.

ALTER TABLE tbl_dentists
    ADD COLUMN dentist_display_id VARCHAR(32) NULL DEFAULT NULL AFTER tenant_id,
    ADD COLUMN profile_image VARCHAR(500) NULL DEFAULT NULL AFTER email,
    ADD UNIQUE KEY unique_tenant_dentist_display (tenant_id, dentist_display_id);
