-- Migration: Add superadmin to tbl_users.role ENUM
--
-- Run on existing databases created before superadmin was added to schema.sql.
-- Fresh installs from current schema.sql already include this value; running this
-- is harmless if the ENUM already matches.

ALTER TABLE tbl_users
  MODIFY COLUMN role ENUM('tenant_owner','manager','staff','dentist','client','superadmin') NOT NULL DEFAULT 'client';
