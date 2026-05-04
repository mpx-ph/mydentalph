<?php

/**
 * Patient-initiated cancel & refund requests (staff approve/decline in Staff Payment Recording).
 */
function refund_requests_ensure_table(PDO $pdo): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS tbl_refund_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tenant_id VARCHAR(20) NOT NULL,
            appointment_id INT NOT NULL,
            booking_id VARCHAR(50) NOT NULL,
            patient_id VARCHAR(50) NOT NULL,
            requester_user_id VARCHAR(20) DEFAULT NULL,
            reason TEXT,
            status ENUM('pending','approved','declined') NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            resolved_at DATETIME NULL,
            resolved_by_user_id VARCHAR(20) NULL,
            KEY idx_rr_tenant_status (tenant_id, status),
            KEY idx_rr_appointment (tenant_id, appointment_id),
            KEY idx_rr_booking (tenant_id, booking_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $done = true;
}
