-- Anonymous website visits (public clinic pages under /{slug}/)
-- Logged from tenant_bootstrap.php for patient-facing scripts only.

-- No FOREIGN KEY: shared hosts often have tbl_tenants as MyISAM or mixed charset;
-- errno 150 breaks CREATE. tenant_id is still indexed for reports.
CREATE TABLE IF NOT EXISTS tbl_website_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    visit_path VARCHAR(512) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tenant_created (tenant_id, created_at),
    KEY idx_created (created_at),
    KEY idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
