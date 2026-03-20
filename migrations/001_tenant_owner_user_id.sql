-- Migration: Tenant owner via owner_user_id (remove owner_name dependency)
--
-- When using schema.sql alone (fresh DB): run Step 2 after schema.sql to add the FK.
-- When upgrading an old DB that had owner_name: uncomment Step 1 and run it first, then run Step 2.

-- Step 1: Only for existing DBs that still have owner_name (leave commented for fresh install)
-- ALTER TABLE tbl_tenants DROP COLUMN owner_name;

-- Step 2: Required after schema.sql (tbl_tenants is created before tbl_users, so FK is added here)
ALTER TABLE tbl_tenants
  ADD CONSTRAINT fk_tenants_owner_user
    FOREIGN KEY (owner_user_id) REFERENCES tbl_users(user_id) ON DELETE SET NULL;
