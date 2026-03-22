-- Super Admin UI branding (single row, id = 1)
CREATE TABLE IF NOT EXISTS tbl_superadmin_settings (
    id INT NOT NULL PRIMARY KEY DEFAULT 1,
    system_name VARCHAR(255) NOT NULL DEFAULT 'MyDental',
    brand_logo_path VARCHAR(512) NOT NULL DEFAULT 'MyDental Logo.svg',
    brand_tagline VARCHAR(255) NOT NULL DEFAULT 'MANAGEMENT CONSOLE',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_superadmin_settings (id, system_name, brand_logo_path, brand_tagline)
VALUES (1, 'MyDental', 'MyDental Logo.svg', 'MANAGEMENT CONSOLE');
