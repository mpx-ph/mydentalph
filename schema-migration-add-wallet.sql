-- MyDental Wallet tables (run once on existing databases)

CREATE TABLE IF NOT EXISTS tbl_wallet_accounts (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    wallet_id VARCHAR(50) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('active','inactive','suspended','closed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_accounts_tenant_wallet (tenant_id, wallet_id),
    UNIQUE KEY uq_wallet_accounts_tenant_patient (tenant_id, patient_id),
    KEY idx_wallet_accounts_tenant_status (tenant_id, status)
);

CREATE TABLE IF NOT EXISTS tbl_wallet_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    wallet_id VARCHAR(50) NOT NULL,
    wallet_transaction_id VARCHAR(50) NOT NULL,
    transaction_type ENUM(
        'refund_credit',
        'manual_credit',
        'manual_debit',
        'payment_debit',
        'adjustment_credit',
        'adjustment_debit',
        'reversal'
    ) NOT NULL,
    direction ENUM('credit','debit') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    balance_before DECIMAL(12,2) NOT NULL,
    balance_after DECIMAL(12,2) NOT NULL,
    source_payment_id VARCHAR(50) DEFAULT NULL,
    reference_number VARCHAR(100) DEFAULT NULL,
    notes TEXT,
    created_by VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wallet_transactions_tenant_txn (tenant_id, wallet_transaction_id),
    KEY idx_wallet_transactions_wallet_date (tenant_id, wallet_id, created_at),
    KEY idx_wallet_transactions_source_payment (tenant_id, source_payment_id),
    KEY idx_wallet_transactions_created_by (created_by)
);
