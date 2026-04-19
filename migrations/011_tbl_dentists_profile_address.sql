-- Add gender, address fields, and updated_at to tbl_dentists (aligned with tbl_staffs).
-- Idempotent: skips any column that already exists. Back up before running on production.

SET @db := DATABASE();

-- gender
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'gender') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN gender ENUM(''Male'',''Female'',''Other'',''Prefer not to say'') NULL DEFAULT NULL AFTER email',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- house_street
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'house_street') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN house_street VARCHAR(255) NULL DEFAULT NULL AFTER gender',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- barangay
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'barangay') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN barangay VARCHAR(100) NULL DEFAULT NULL AFTER house_street',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- city_municipality
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'city_municipality') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN city_municipality VARCHAR(100) NULL DEFAULT NULL AFTER barangay',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- province
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'province') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN province VARCHAR(100) NULL DEFAULT NULL AFTER city_municipality',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- updated_at (must be last; after created_at)
SET @sql := (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'tbl_dentists' AND COLUMN_NAME = 'updated_at') = 0,
    'ALTER TABLE tbl_dentists ADD COLUMN updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at',
    'SELECT 1'
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
