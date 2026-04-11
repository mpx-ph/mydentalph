-- Add per-service payment options (run once on existing databases)
ALTER TABLE tbl_services
    ADD COLUMN downpayment_percentage DECIMAL(5,2) DEFAULT NULL
        COMMENT 'Optional % for regular (non-installment) bookings; NULL uses clinic default' AFTER price,
    ADD COLUMN enable_installment TINYINT(1) NOT NULL DEFAULT 0 AFTER downpayment_percentage;
