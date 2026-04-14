-- Add service_type classification for appointment services.
-- Safe for existing databases and idempotent.

SET @db_name := DATABASE();

SET @has_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbl_appointment_services'
      AND COLUMN_NAME = 'service_type'
);

SET @ddl := IF(
    @has_column = 0,
    "ALTER TABLE tbl_appointment_services ADD COLUMN service_type VARCHAR(20) NOT NULL DEFAULT 'installment' AFTER price",
    "SELECT 'service_type already exists' AS info"
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill legacy rows and normalize invalid values.
UPDATE tbl_appointment_services
SET service_type = 'installment'
WHERE service_type IS NULL
   OR TRIM(service_type) = ''
   OR LOWER(TRIM(service_type)) NOT IN ('installment', 'regular');
