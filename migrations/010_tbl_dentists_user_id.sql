-- Link tbl_dentists to tbl_users (same pattern as tbl_staffs / tbl_managers).
-- Run once on existing databases created before this change. Back up first.
--
-- Step 1 adds a nullable user_id so existing rows do not fail. Assign a user_id
-- for every dentist row, then run Step 2 to enforce NOT NULL like the team tables.

-- Step 1: add column and constraints (user_id nullable until backfilled)
ALTER TABLE tbl_dentists
    ADD COLUMN user_id VARCHAR(20) NULL DEFAULT NULL AFTER dentist_display_id,
    ADD UNIQUE KEY unique_tenant_dentist_user_id (tenant_id, user_id),
    ADD KEY idx_dentists_tenant (tenant_id),
    ADD CONSTRAINT fk_dentists_user
        FOREIGN KEY (user_id) REFERENCES tbl_users(user_id) ON DELETE CASCADE;

-- After backfilling user_id for all rows (e.g. INSERT/UPDATE from your user provisioning):
-- UPDATE tbl_dentists SET user_id = '...' WHERE dentist_id = ...;
--
-- Step 2: enforce NOT NULL to match tbl_staffs / tbl_managers
-- ALTER TABLE tbl_dentists
--     MODIFY COLUMN user_id VARCHAR(20) NOT NULL;
