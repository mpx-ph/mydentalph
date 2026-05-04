-- Optional promo code on discount programs (replaces service scoping for code-based promos).
ALTER TABLE tbl_discount_programs
    ADD COLUMN promo_code VARCHAR(64) NULL DEFAULT NULL AFTER name;

CREATE UNIQUE INDEX uq_discount_program_tenant_promo
    ON tbl_discount_programs (tenant_id, promo_code);
