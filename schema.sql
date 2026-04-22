SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- TENANTS (Clinics using the system)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenants (
    tenant_id VARCHAR(20) NOT NULL,
    clinic_name VARCHAR(255) NOT NULL,
    clinic_slug VARCHAR(100) DEFAULT NULL,
    country_region VARCHAR(100),
    clinic_address TEXT,
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    subscription_status ENUM('active','inactive','suspended') DEFAULT 'inactive',
    owner_user_id VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tenant_id),
    UNIQUE KEY unique_clinic_slug (clinic_slug),
    UNIQUE KEY unique_owner_user_id (owner_user_id)
    -- FK to tbl_users added after tbl_users exists; see migrations/001_tenant_owner_user_id.sql
);

-- ============================================
-- SUBSCRIPTION PLANS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_subscription_plans (
    plan_id INT AUTO_INCREMENT,
    plan_slug VARCHAR(50) NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    billing_cycle ENUM('monthly','yearly') DEFAULT 'monthly',
    max_users INT,
    max_patients INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (plan_id),
    UNIQUE KEY unique_plan_slug (plan_slug)
);

-- ============================================
-- TENANT SUBSCRIPTIONS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenant_subscriptions (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    plan_id INT NOT NULL,
    subscription_start DATE,
    subscription_end DATE,
    payment_status ENUM('pending','paid','failed','cancelled') DEFAULT 'pending',
    payment_method ENUM('gcash','paymaya','bank_transfer','card','cash'),
    amount_paid DECIMAL(10,2),
    reference_number VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (plan_id) REFERENCES tbl_subscription_plans(plan_id)
);

-- ============================================
-- TENANT INVOICES
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenant_invoices (
    invoice_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    plan_id INT NOT NULL,
    amount DECIMAL(10,2),
    due_date DATE,
    status ENUM('pending','paid','overdue') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (invoice_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (plan_id) REFERENCES tbl_subscription_plans(plan_id)
);

-- ============================================
-- USERS
-- ============================================
-- role ENUM (canonical app roles):
--   tenant_owner, manager, staff, dentist, client — tenant/clinic users
--   superadmin — platform administrator (superadmin panel)
CREATE TABLE IF NOT EXISTS tbl_users (
    user_id VARCHAR(20) NOT NULL,
    tenant_id VARCHAR(20) NOT NULL,
    username VARCHAR(100) NOT NULL,
    -- Sign-in email; unique per tenant (unique_tenant_email). Provider portal profile updates may send a new OTP via tbl_email_verifications when this changes.
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255),
    full_name VARCHAR(255) NOT NULL,
    role ENUM('tenant_owner','manager','staff','dentist','client','superadmin') NOT NULL DEFAULT 'client',
    phone VARCHAR(20),
    photo VARCHAR(500),
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL DEFAULT NULL,
    last_active TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (user_id),
    UNIQUE KEY unique_tenant_username (tenant_id, username),
    UNIQUE KEY unique_tenant_email (tenant_id, email),
    KEY idx_username (username),
    KEY idx_email (email),
    KEY idx_role (role),
    KEY idx_tenant (tenant_id),

    CONSTRAINT fk_users_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tbl_tenants(tenant_id)
        ON DELETE CASCADE
);

-- ============================================
-- LOGIN ATTEMPTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_login_attempts (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    ip_address VARCHAR(45),
    attempts INT DEFAULT 1,
    last_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id)
);

-- ============================================
-- PATIENTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_patients (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50),
    owner_user_id VARCHAR(20) NULL,
    linked_user_id VARCHAR(20) NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact_number VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('Male','Female','Other','Prefer not to say'),
    blood_type VARCHAR(10),
    house_street VARCHAR(255),
    barangay VARCHAR(100),
    city_municipality VARCHAR(100),
    province VARCHAR(100),
    profile_image VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_patient_id (tenant_id, patient_id),
    KEY idx_tenant (tenant_id),
    KEY idx_patients_tenant_patientid (tenant_id, patient_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (owner_user_id) REFERENCES tbl_users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (linked_user_id) REFERENCES tbl_users(user_id) ON DELETE SET NULL
);

-- ============================================
-- PATIENT DEPENDENTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_dependents (
    dependent_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    guardian_user_id VARCHAR(20) NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male','Female'),
    relationship VARCHAR(50) DEFAULT 'child',
    photo_path VARCHAR(500),
    status ENUM('active','inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (dependent_id),

    KEY idx_patient (patient_id),
    KEY idx_guardian (guardian_user_id),
    KEY idx_tenant (tenant_id),

    CONSTRAINT fk_dependents_patient
        FOREIGN KEY (patient_id)
        REFERENCES tbl_patients(patient_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_dependents_guardian
        FOREIGN KEY (guardian_user_id)
        REFERENCES tbl_users(user_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_dependents_tenant
        FOREIGN KEY (tenant_id)
        REFERENCES tbl_tenants(tenant_id)
        ON DELETE CASCADE
);

-- ============================================
-- PATIENT FILES
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_patient_files (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50) NULL,
    dependent_id INT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size BIGINT,
    file_category VARCHAR(100),
    description TEXT,
    uploaded_by VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    FOREIGN KEY (dependent_id) REFERENCES tbl_dependents(dependent_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES tbl_users(user_id) ON DELETE SET NULL,
    CHECK (
        (patient_id IS NOT NULL AND dependent_id IS NULL) OR 
        (patient_id IS NULL AND dependent_id IS NOT NULL)
    )
);

-- ============================================
-- DENTISTS (dentist profiles linked to tbl_users)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_dentists (
    dentist_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    dentist_display_id VARCHAR(32) DEFAULT NULL,
    user_id VARCHAR(20) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    specialization VARCHAR(150),
    license_number VARCHAR(100),
    years_of_experience INT,
    contact_number VARCHAR(20),
    email VARCHAR(255),
    gender ENUM('Male','Female','Other','Prefer not to say') DEFAULT NULL,
    house_street VARCHAR(255) DEFAULT NULL,
    barangay VARCHAR(100) DEFAULT NULL,
    city_municipality VARCHAR(100) DEFAULT NULL,
    province VARCHAR(100) DEFAULT NULL,
    profile_image VARCHAR(500) DEFAULT NULL,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (dentist_id),
    UNIQUE KEY unique_tenant_dentist_display (tenant_id, dentist_display_id),
    UNIQUE KEY unique_tenant_dentist_user_id (tenant_id, user_id),
    KEY idx_dentists_tenant (tenant_id),
    CONSTRAINT fk_dentists_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_dentists_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE
);

-- ============================================
-- STAFFS (admin/staff/doctor/manager profiles linked to tbl_users)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_staffs (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    staff_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact_number VARCHAR(20),
    gender ENUM('Male','Female','Other','Prefer not to say'),
    house_street VARCHAR(255),
    barangay VARCHAR(100),
    city_municipality VARCHAR(100),
    province VARCHAR(100),
    profile_image VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_staff_id (tenant_id, staff_id),
    UNIQUE KEY unique_tenant_user_id (tenant_id, user_id),
    KEY idx_tenant (tenant_id),
    CONSTRAINT fk_staffs_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_staffs_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE
);

-- ============================================
-- MANAGERS (manager profiles linked to tbl_users)
-- manager_id format: M-YYYY-XXXXX (e.g., M-2026-00001)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_managers (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    manager_id VARCHAR(50) NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    contact_number VARCHAR(20),
    gender ENUM('Male','Female','Other','Prefer not to say'),
    house_street VARCHAR(255),
    barangay VARCHAR(100),
    city_municipality VARCHAR(100),
    province VARCHAR(100),
    profile_image VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_manager_id (tenant_id, manager_id),
    UNIQUE KEY unique_tenant_manager_user_id (tenant_id, user_id),
    KEY idx_managers_tenant (tenant_id),
    CONSTRAINT chk_manager_id_format
        CHECK (manager_id REGEXP '^M-[0-9]{4}-[0-9]{5}$'),
    CONSTRAINT fk_managers_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_managers_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE
);

-- ============================================
-- APPOINTMENTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_appointments (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    dentist_id INT NOT NULL,
    booking_id VARCHAR(50),
    patient_id VARCHAR(50),
    appointment_date DATE,
    appointment_time TIME,
    service_type VARCHAR(100),
    service_description TEXT,
    insurance VARCHAR(100),
    treatment_type ENUM('short_term','long_term') DEFAULT 'short_term',
    visit_type ENUM('pre_book','walk_in','emergency') DEFAULT 'pre_book',
    status ENUM('pending','confirmed','completed','cancelled','no_show') DEFAULT 'pending',
    notes TEXT,
    total_treatment_cost DECIMAL(10,2),
    duration_months INT,
    target_completion_date DATE,
    start_date DATE,
    created_by VARCHAR(20),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_booking_per_tenant (tenant_id, booking_id),
    UNIQUE KEY unique_dentist_schedule (tenant_id, dentist_id, appointment_date, appointment_time),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (dentist_id) REFERENCES tbl_dentists(dentist_id),
    FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id),
    FOREIGN KEY (created_by) REFERENCES tbl_users(user_id)
);

-- ============================================
-- PATIENT PAYMENTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_payments (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    payment_id VARCHAR(50),
    patient_id VARCHAR(50),
    booking_id VARCHAR(50),
    installment_number INT,
    amount DECIMAL(10,2),
    payment_method ENUM('cash','credit_card','debit_card','gcash','paymaya','bank_transfer','check'),
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reference_number VARCHAR(100),
    notes TEXT,
    status ENUM('pending','completed','refunded','cancelled') DEFAULT 'completed',
    created_by VARCHAR(20),
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_payment_id (tenant_id, payment_id),
    KEY idx_payments_tenant (tenant_id),
    KEY idx_payments_tenant_booking (tenant_id, booking_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id),
    FOREIGN KEY (created_by) REFERENCES tbl_users(user_id)
    
);

    ALTER TABLE tbl_payments
    ADD payment_type ENUM('downpayment', 'fullpayment', 'balancepayment') NOT NULL;

-- ============================================
-- MYDENTAL WALLET
-- ============================================
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

-- ============================================
-- INSTALLMENTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_installments (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    booking_id VARCHAR(50),
    installment_number INT,
    amount_due DECIMAL(10,2),
    status ENUM('pending','paid','book_visit','locked','completed') DEFAULT 'pending',
    scheduled_date DATE,
    scheduled_time TIME,
    payment_id VARCHAR(50),
    notes TEXT,
    PRIMARY KEY (id),
    KEY idx_installments_tenant_booking (tenant_id, booking_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (booking_id) REFERENCES tbl_appointments(booking_id),
    FOREIGN KEY (payment_id) REFERENCES tbl_payments(payment_id)
);

-- ============================================
-- SERVICES
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_services (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    service_id VARCHAR(50),
    service_name VARCHAR(255),
    service_details TEXT,
    category VARCHAR(100),
    price DECIMAL(10,2),
    downpayment_percentage DECIMAL(5,2) DEFAULT NULL COMMENT 'Optional % for regular (non-installment) bookings; NULL uses clinic default',
    enable_installment TINYINT(1) NOT NULL DEFAULT 0,
    installment_downpayment DECIMAL(10,2) DEFAULT NULL COMMENT 'Peso downpayment when installment plan enabled',
    installment_duration_months INT DEFAULT NULL COMMENT 'Installment term in months',
    status ENUM('active','inactive') DEFAULT 'active',
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_service_id (tenant_id, service_id),
    KEY idx_services_tenant (tenant_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id)
);

-- ============================================
-- APPOINTMENT SERVICES (junction: appointments + services per booking)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_appointment_services (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    booking_id VARCHAR(50) NOT NULL,
    appointment_id INT NULL,
    service_id VARCHAR(50) NOT NULL,
    service_name VARCHAR(255),
    price DECIMAL(10,2),
    service_type ENUM('installment','regular') NOT NULL DEFAULT 'installment',
    is_original TINYINT DEFAULT 1,
    added_by VARCHAR(20),
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tenant (tenant_id),
    KEY idx_appointment_services_tenant_booking (tenant_id, booking_id),
    KEY idx_appointment_services_tenant_service (tenant_id, service_id),
    CONSTRAINT fk_appointment_services_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_services_booking
        FOREIGN KEY (tenant_id, booking_id) REFERENCES tbl_appointments(tenant_id, booking_id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_services_appointment
        FOREIGN KEY (appointment_id) REFERENCES tbl_appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_services_service
        FOREIGN KEY (tenant_id, service_id) REFERENCES tbl_services(tenant_id, service_id) ON DELETE CASCADE,
    CONSTRAINT fk_appointment_services_added_by
        FOREIGN KEY (added_by) REFERENCES tbl_users(user_id) ON DELETE SET NULL
);

-- ============================================
-- MESSAGES (Notifications)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_messages (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    sender_id VARCHAR(20),
    receiver_id VARCHAR(20),
    subject VARCHAR(255),
    message TEXT,
    is_read TINYINT DEFAULT 0,
    status ENUM('sent','delivered','seen') DEFAULT 'sent',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (sender_id) REFERENCES tbl_users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES tbl_users(user_id)
);

-- ============================================
-- REPORTS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_reports (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    date DATE,
    total_bookings INT DEFAULT 0,
    completed_treatments INT DEFAULT 0,
    gross_revenue DECIMAL(10,2) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id)
);

-- ============================================
-- SCHEDULE BLOCKS (unified shifts/breaks/blocks for staff + dentists)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_schedule_blocks (
    schedule_block_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    block_date DATE DEFAULT NULL COMMENT 'One-off schedule date',
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') DEFAULT NULL COMMENT 'Recurring weekly schedule',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    block_type ENUM('shift','break','blocked') NOT NULL DEFAULT 'shift',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    notes TEXT,
    created_by VARCHAR(20) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_block_id),
    KEY idx_schedule_blocks_tenant (tenant_id),
    KEY idx_schedule_blocks_user_date (tenant_id, user_id, block_date),
    KEY idx_schedule_blocks_user_day (tenant_id, user_id, day_of_week),
    KEY idx_schedule_blocks_type (tenant_id, block_type),
    KEY idx_schedule_blocks_created_by (created_by),
    CONSTRAINT fk_schedule_blocks_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_blocks_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_schedule_blocks_created_by
        FOREIGN KEY (created_by) REFERENCES tbl_users(user_id) ON DELETE SET NULL
);

-- ============================================
-- CLINIC HOURS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_clinic_hours (
    clinic_hours_id INT AUTO_INCREMENT PRIMARY KEY,
    day_of_week TINYINT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
    open_time TIME NULL,
    close_time TIME NULL,
    is_closed TINYINT(1) DEFAULT 0 COMMENT '1 = closed, 0 = open',
    notes VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_day (day_of_week)
);

-- ============================================
-- PATIENT QUEUE
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_patient_queue (
    queue_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50),
    appointment_id INT,
    queue_number INT,
    status ENUM('waiting','serving','completed','cancelled') DEFAULT 'waiting',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (queue_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id),
    FOREIGN KEY (appointment_id) REFERENCES tbl_appointments(id)
);

-- ============================================
-- TERMS AND CONDITIONS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_terms_and_conditions (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    title VARCHAR(255),
    content TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id)
);

-- ============================================
-- AUDIT LOGS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_audit_logs (
    log_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    user_id VARCHAR(20),
    action VARCHAR(255),
    description TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id),
    FOREIGN KEY (user_id) REFERENCES tbl_users(user_id)
);

-- ============================================
-- FEEDBACK
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_feedback (
    feedback_id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    appointment_id INT NOT NULL,
    rating TINYINT NOT NULL,
    comments TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (feedback_id),
    FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id) ON DELETE CASCADE,
    FOREIGN KEY (appointment_id) REFERENCES tbl_appointments(id) ON DELETE CASCADE,
    CHECK (rating BETWEEN 1 AND 5)
);

-- ============================================
-- REVIEWS (patient feedback per appointment; clinic-facing)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_reviews (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    review_id VARCHAR(50) NOT NULL,
    appointment_id INT NOT NULL,
    booking_id VARCHAR(50) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    rating TINYINT NOT NULL,
    comment TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY unique_tenant_review_id (tenant_id, review_id),
    KEY idx_tenant (tenant_id),
    KEY idx_reviews_tenant_appointment (tenant_id, appointment_id),
    CONSTRAINT fk_reviews_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_appointment
        FOREIGN KEY (appointment_id) REFERENCES tbl_appointments(id) ON DELETE CASCADE,
    CONSTRAINT fk_reviews_patient
        FOREIGN KEY (tenant_id, patient_id) REFERENCES tbl_patients(tenant_id, patient_id) ON DELETE CASCADE,
    CHECK (rating BETWEEN 1 AND 5)
);

-- ============================================
-- EMAIL VERIFICATIONS
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_email_verifications (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    user_id VARCHAR(20) NOT NULL,
    otp_hash VARCHAR(255) NOT NULL,
    token_hash VARCHAR(255) DEFAULT NULL,
    otp_expires_at DATETIME NOT NULL,
    token_expires_at DATETIME DEFAULT NULL,
    attempts INT NOT NULL DEFAULT 0,
    last_sent_at DATETIME DEFAULT NULL,
    verified_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_expires (otp_expires_at),
    KEY idx_verified (verified_at),
    KEY idx_token_hash (token_hash),
    KEY idx_token_expires (token_expires_at),
    KEY idx_tenant (tenant_id),
    CONSTRAINT fk_email_verifications_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_email_verifications_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE
);

-- ============================================
-- PATIENT NOTES
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_patient_notes (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    author_id VARCHAR(20) NOT NULL,
    note_content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_patient (patient_id),
    KEY idx_author (author_id),
    KEY idx_created (created_at),
    KEY idx_tenant (tenant_id),
    CONSTRAINT fk_patient_notes_patient
        FOREIGN KEY (patient_id) REFERENCES tbl_patients(patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_patient_notes_author
        FOREIGN KEY (author_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_patient_notes_tenant
        FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE
);

-- ============================================
-- PENDING PROVIDER SELF-REGISTRATION (pre-OTP; no tenant/user until verified)
-- ============================================
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

-- ============================================
-- SUPER ADMIN UI SETTINGS (single row, id = 1)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_superadmin_settings (
    id INT NOT NULL PRIMARY KEY DEFAULT 1,
    system_name VARCHAR(255) NOT NULL DEFAULT 'MyDental',
    brand_logo_path VARCHAR(512) NOT NULL DEFAULT 'MyDental Logo.svg',
    brand_tagline VARCHAR(255) NOT NULL DEFAULT 'MANAGEMENT CONSOLE',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tbl_superadmin_settings (id, system_name, brand_logo_path, brand_tagline)
VALUES (1, 'MyDental', 'MyDental Logo.svg', 'MANAGEMENT CONSOLE');

-- ============================================
-- ANONYMOUS WEBSITE VISITS (public clinic pages)
-- ============================================
-- No FK to tbl_tenants (avoids errno 150 when parent table is MyISAM or charset differs).
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

-- ============================================
-- TENANT PUBLIC SERVICES (PatientServices.php catalog)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenant_public_services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price_range VARCHAR(255) NOT NULL DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tenant_sort (tenant_id, sort_order),
    KEY idx_tenant_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TENANT BUSINESS VERIFICATIONS (onboarding)
-- ============================================
-- No FK to tbl_tenants (avoids errno 150 when parent table engine/charset differs in shared hosting).
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

-- ============================================
-- TENANT VERIFICATION REQUESTS (structured onboarding approvals)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenant_verification_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id VARCHAR(20) NOT NULL,
    owner_user_id VARCHAR(20) NOT NULL,
    clinic_name VARCHAR(255) NOT NULL,
    owner_name VARCHAR(255) DEFAULT NULL,
    owner_email VARCHAR(255) DEFAULT NULL,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,
    reviewed_by VARCHAR(20) DEFAULT NULL,
    reviewer_notes TEXT,
    setup_token_hash VARCHAR(255) DEFAULT NULL,
    setup_token_expires_at DATETIME DEFAULT NULL,
    setup_token_used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tenant_verification_request_tenant (tenant_id),
    KEY idx_verification_requests_status (status, submitted_at),
    KEY idx_verification_requests_owner_user (owner_user_id),
    KEY idx_verification_requests_reviewed_by (reviewed_by),
    KEY idx_verification_requests_setup_token_expires (setup_token_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TENANT VERIFICATION FILES (multi-file uploads per request)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_tenant_verification_files (
    file_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    tenant_id VARCHAR(20) NOT NULL,
    document_type VARCHAR(50) DEFAULT 'supporting_document',
    original_file_name VARCHAR(255) NOT NULL,
    stored_file_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(120) DEFAULT NULL,
    file_size_bytes BIGINT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_verification_files_request (request_id),
    KEY idx_verification_files_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TENANT PAYMENT SETTINGS (staff portal)
-- ============================================
-- No FK constraints here to avoid errno 150 on hosts where parent tables
-- were created with different engine/charset/collation.
CREATE TABLE IF NOT EXISTS tbl_payment_settings (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    regular_downpayment_percentage DECIMAL(5,2) NOT NULL DEFAULT 20.00,
    long_term_min_downpayment DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    auto_invoice_enabled TINYINT(1) NOT NULL DEFAULT 1,
    updated_by VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_payment_settings_tenant (tenant_id),
    KEY idx_payment_settings_tenant (tenant_id),
    KEY idx_payment_settings_updated_by (updated_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TREATMENTS (installment source of truth)
-- ============================================
CREATE TABLE IF NOT EXISTS tbl_treatments (
    id INT AUTO_INCREMENT,
    tenant_id VARCHAR(20) NOT NULL,
    treatment_id VARCHAR(50) NOT NULL,
    patient_id VARCHAR(50) NOT NULL,
    primary_service_id VARCHAR(50) NOT NULL,
    primary_service_name VARCHAR(255) DEFAULT NULL,
    total_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    duration_months INT NOT NULL DEFAULT 0,
    months_paid INT NOT NULL DEFAULT 0,
    months_left INT NOT NULL DEFAULT 0,
    status ENUM('active','completed','cancelled') NOT NULL DEFAULT 'active',
    started_at DATE DEFAULT NULL,
    completed_at DATE DEFAULT NULL,
    created_by VARCHAR(20) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_treatments_tenant_treatment (tenant_id, treatment_id),
    KEY idx_treatments_patient_status (tenant_id, patient_id, status),
    KEY idx_treatments_primary_service (tenant_id, primary_service_id),
    CONSTRAINT fk_treatments_tenant FOREIGN KEY (tenant_id) REFERENCES tbl_tenants(tenant_id) ON DELETE CASCADE,
    CONSTRAINT fk_treatments_patient FOREIGN KEY (tenant_id, patient_id) REFERENCES tbl_patients(tenant_id, patient_id) ON DELETE CASCADE,
    CONSTRAINT fk_treatments_service FOREIGN KEY (tenant_id, primary_service_id) REFERENCES tbl_services(tenant_id, service_id) ON DELETE RESTRICT,
    CONSTRAINT fk_treatments_created_by FOREIGN KEY (created_by) REFERENCES tbl_users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE tbl_appointments ADD COLUMN treatment_id VARCHAR(50) NULL AFTER patient_id;
ALTER TABLE tbl_appointment_services ADD COLUMN treatment_id VARCHAR(50) NULL AFTER booking_id;
ALTER TABLE tbl_payments ADD COLUMN treatment_id VARCHAR(50) NULL AFTER booking_id;
ALTER TABLE tbl_installments ADD COLUMN treatment_id VARCHAR(50) NULL AFTER booking_id;

ALTER TABLE tbl_appointments ADD KEY idx_appointments_treatment (tenant_id, treatment_id);
ALTER TABLE tbl_appointment_services ADD KEY idx_appointment_services_treatment (tenant_id, treatment_id);
ALTER TABLE tbl_payments ADD KEY idx_payments_treatment (tenant_id, treatment_id);
ALTER TABLE tbl_installments ADD KEY idx_installments_treatment (tenant_id, treatment_id);

ALTER TABLE tbl_appointments
    ADD CONSTRAINT fk_appointments_treatment
    FOREIGN KEY (tenant_id, treatment_id) REFERENCES tbl_treatments(tenant_id, treatment_id) ON DELETE SET NULL;
ALTER TABLE tbl_appointment_services
    ADD CONSTRAINT fk_appointment_services_treatment
    FOREIGN KEY (tenant_id, treatment_id) REFERENCES tbl_treatments(tenant_id, treatment_id) ON DELETE SET NULL;
ALTER TABLE tbl_payments
    ADD CONSTRAINT fk_payments_treatment
    FOREIGN KEY (tenant_id, treatment_id) REFERENCES tbl_treatments(tenant_id, treatment_id) ON DELETE SET NULL;
ALTER TABLE tbl_installments
    ADD CONSTRAINT fk_installments_treatment
    FOREIGN KEY (tenant_id, treatment_id) REFERENCES tbl_treatments(tenant_id, treatment_id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

