CREATE TABLE IF NOT EXISTS tbl_tenant_business_verifications (
    verification_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(20) NOT NULL,
    uploaded_file_path VARCHAR(500) NOT NULL,
    uploaded_file_name VARCHAR(255) DEFAULT NULL,
    ocr_raw_text LONGTEXT,
    ocn_tin_branch VARCHAR(255) DEFAULT NULL,
    taxpayer_name VARCHAR(255) DEFAULT NULL,
    registered_address TEXT,
    verification_status ENUM('pending','submitted','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at DATETIME DEFAULT NULL,
    reviewed_at DATETIME DEFAULT NULL,
    reviewer_notes TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_tenant_status (tenant_id, verification_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
