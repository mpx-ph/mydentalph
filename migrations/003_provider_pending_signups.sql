-- Pending provider self-registration: no tbl_tenants/tbl_users rows until email is verified.
CREATE TABLE IF NOT EXISTS tbl_provider_pending_signups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clinic_name VARCHAR(255) NOT NULL,
    country_region VARCHAR(100) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    plan VARCHAR(50) NOT NULL DEFAULT 'monthly',
    otp_hash VARCHAR(255) NOT NULL,
    otp_expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_sent_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_pending_email (email),
    UNIQUE KEY unique_pending_username (username),
    KEY idx_otp_expires (otp_expires_at)
);
