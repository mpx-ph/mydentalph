-- Migration to support individual document approval and rejection.
-- Modifies tbl_tenant_verification_requests status column to include 'action_required'.
-- Adds status and reviewer_notes columns to tbl_tenant_verification_files.

ALTER TABLE tbl_tenant_verification_requests
    MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'action_required') NOT NULL DEFAULT 'pending';

ALTER TABLE tbl_tenant_verification_files
    ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER file_size_bytes,
    ADD COLUMN reviewer_notes TEXT DEFAULT NULL AFTER status;
