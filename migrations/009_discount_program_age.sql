-- Optional patient age range per discount program (staff portal).
-- Run after 008_discount_verification.sql if tables already exist.

ALTER TABLE tbl_discount_programs
    ADD COLUMN age_min SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Minimum patient age; NULL = no minimum' AFTER min_spend,
    ADD COLUMN age_max SMALLINT UNSIGNED NULL DEFAULT NULL COMMENT 'Maximum patient age; NULL = no maximum' AFTER age_min;
