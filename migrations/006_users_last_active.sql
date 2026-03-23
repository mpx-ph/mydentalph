-- Last activity timestamp (e.g. successful login). Kept separate from last_login for clarity.
-- Safe to run once; ignore error if column already exists.

ALTER TABLE tbl_users
ADD COLUMN last_active TIMESTAMP NULL DEFAULT NULL AFTER last_login;
