-- Installment plan fields for tbl_services (run once on existing databases)
ALTER TABLE tbl_services
    ADD COLUMN installment_downpayment DECIMAL(10,2) DEFAULT NULL
        COMMENT 'Peso downpayment when installment plan enabled' AFTER enable_installment,
    ADD COLUMN installment_duration_months INT DEFAULT NULL
        COMMENT 'Installment term in months' AFTER installment_downpayment;
