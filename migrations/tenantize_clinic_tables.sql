-- Tenantize clinic (template) tables used under /clinic
-- Run this once on your MySQL database ONLY if you have an existing DB with
-- unprefixed table names (users, patients, appointments, etc.).
--
-- SOURCE OF TRUTH: schema.sql uses tbl_* names and already includes tenant_id
-- and tenant-scoped indexes/uniques. For new installs, use schema.sql.
--
-- Goal (for legacy DBs with users/patients/...):
-- - Add tenant_id to all clinic tables so EVERYTHING under /clinic is isolated per tenant.
-- - Existing rows become GLOBAL (you can later migrate them to a specific tenant_id).
--
-- IMPORTANT:
-- - tenant_id format in your system looks like: TNT_00001 (from tbl_tenants.tenant_id)
-- - After running this, your clinic-side PHP code will filter by tenant_id.

START TRANSACTION;

-- USERS
ALTER TABLE users
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE users SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE users
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_users_tenant (tenant_id),
  ADD UNIQUE KEY uq_users_tenant_email (tenant_id, email),
  ADD UNIQUE KEY uq_users_tenant_username (tenant_id, username),
  ADD UNIQUE KEY uq_users_tenant_userid (tenant_id, user_id);

-- PATIENTS
ALTER TABLE patients
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE patients SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE patients
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_patients_tenant (tenant_id),
  ADD INDEX idx_patients_tenant_patientid (tenant_id, patient_id),
  ADD INDEX idx_patients_tenant_owner (tenant_id, owner_user_id),
  ADD INDEX idx_patients_tenant_linked (tenant_id, linked_user_id);

-- STAFFS
ALTER TABLE staffs
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE staffs SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE staffs
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_staffs_tenant (tenant_id),
  ADD UNIQUE KEY uq_staffs_tenant_staffid (tenant_id, staff_id),
  ADD UNIQUE KEY uq_staffs_tenant_userid (tenant_id, user_id);

-- SERVICES
ALTER TABLE services
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE services SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE services
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_services_tenant (tenant_id),
  ADD UNIQUE KEY uq_services_tenant_serviceid (tenant_id, service_id);

-- APPOINTMENTS
ALTER TABLE appointments
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE appointments SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE appointments
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_appointments_tenant (tenant_id),
  ADD INDEX idx_appointments_tenant_date (tenant_id, appointment_date),
  ADD INDEX idx_appointments_tenant_booking (tenant_id, booking_id),
  ADD INDEX idx_appointments_tenant_patient (tenant_id, patient_id);

-- APPOINTMENT_SERVICES (if present)
ALTER TABLE appointment_services
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE appointment_services SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE appointment_services
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_appointment_services_tenant (tenant_id),
  ADD INDEX idx_appointment_services_tenant_booking (tenant_id, booking_id),
  ADD INDEX idx_appointment_services_tenant_service (tenant_id, service_id);

-- INSTALLMENTS
ALTER TABLE installments
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE installments SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE installments
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_installments_tenant (tenant_id),
  ADD INDEX idx_installments_tenant_booking (tenant_id, booking_id);

-- PAYMENTS
ALTER TABLE payments
  ADD COLUMN tenant_id VARCHAR(50) NULL;
UPDATE payments SET tenant_id = 'GLOBAL' WHERE tenant_id IS NULL OR tenant_id = '';
ALTER TABLE payments
  MODIFY tenant_id VARCHAR(50) NOT NULL,
  ADD INDEX idx_payments_tenant (tenant_id),
  ADD INDEX idx_payments_tenant_booking (tenant_id, booking_id),
  ADD UNIQUE KEY uq_payments_tenant_paymentid (tenant_id, payment_id);

COMMIT;

