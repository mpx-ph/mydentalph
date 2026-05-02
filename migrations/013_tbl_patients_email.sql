-- Store email on patient rows (e.g. dependents without a linked tbl_users account).
-- Run on existing databases before using add_dependent_patient.php email insert.

ALTER TABLE tbl_patients
    ADD COLUMN email VARCHAR(255) NULL DEFAULT NULL AFTER contact_number;
