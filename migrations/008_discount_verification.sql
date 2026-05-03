-- Discount programs & patient discount verifications (staff portal)
-- Safe to run once on existing databases.
--
-- NOTE: No FOREIGN KEY constraints — avoids errno 150 on hosts where parent tables
-- differ in engine (MyISAM vs InnoDB), charset, or index definition. Relationships are
-- enforced in clinic/api/discount_*.php.

CREATE TABLE IF NOT EXISTS tbl_discount_programs (
    discount_program_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    discount_type ENUM('percentage','fixed') NOT NULL DEFAULT 'percentage',
    value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    min_spend DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    age_min SMALLINT UNSIGNED NULL DEFAULT NULL,
    age_max SMALLINT UNSIGNED NULL DEFAULT NULL,
    req_upload_proof TINYINT(1) NOT NULL DEFAULT 0,
    req_notes TINYINT(1) NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    valid_from DATE DEFAULT NULL,
    valid_to DATE DEFAULT NULL,
    service_scope ENUM('all','selected') NOT NULL DEFAULT 'all',
    stacking ENUM('yes','no') NOT NULL DEFAULT 'no',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_discount_programs_tenant (tenant_id),
    KEY idx_discount_programs_enabled (tenant_id, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_discount_program_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    discount_program_id INT NOT NULL,
    tenant_id VARCHAR(20) NOT NULL,
    service_id VARCHAR(50) NOT NULL,
    UNIQUE KEY uq_discount_program_service (discount_program_id, service_id),
    KEY idx_dp_services_tenant (tenant_id),
    KEY idx_dp_services_program (discount_program_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tbl_discount_verifications (
    discount_verification_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(20) NOT NULL,
    discount_program_id INT NOT NULL,
    program_name_snapshot VARCHAR(255) NOT NULL,
    req_upload_proof TINYINT(1) NOT NULL DEFAULT 0,
    req_notes TINYINT(1) NOT NULL DEFAULT 0,
    patient_name VARCHAR(255) NOT NULL,
    patient_ref VARCHAR(100) DEFAULT NULL,
    id_number VARCHAR(120) DEFAULT NULL,
    proof_image_path VARCHAR(500) DEFAULT NULL,
    application_notes TEXT,
    date_applied DATE NOT NULL,
    staff_assigned_user_id VARCHAR(20) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    approved_by_user_id VARCHAR(20) DEFAULT NULL,
    remarks TEXT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_dv_tenant_status (tenant_id, status),
    KEY idx_dv_tenant_date (tenant_id, date_applied),
    KEY idx_dv_program (discount_program_id),
    KEY idx_dv_staff_assign (staff_assigned_user_id),
    KEY idx_dv_approved_by (approved_by_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
