-- Add service timing fields for services.
-- Safe for existing databases and idempotent.

SET @db_name := DATABASE();

SET @has_service_duration := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbl_services'
      AND COLUMN_NAME = 'service_duration'
);

SET @ddl := IF(
    @has_service_duration = 0,
    "ALTER TABLE tbl_services ADD COLUMN service_duration INT NOT NULL DEFAULT 0 COMMENT 'Service length in minutes' AFTER price",
    "SELECT 'service_duration already exists' AS info"
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_buffer_time := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'tbl_services'
      AND COLUMN_NAME = 'buffer_time'
);

SET @ddl := IF(
    @has_buffer_time = 0,
    "ALTER TABLE tbl_services ADD COLUMN buffer_time INT NOT NULL DEFAULT 0 COMMENT 'Extra buffer after service in minutes' AFTER service_duration",
    "SELECT 'buffer_time already exists' AS info"
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
