-- IDPS monthly ingestion supplement; intended for review alongside existing MySQL V1 blueprint.
-- MySQL 8.4, InnoDB, utf8mb4. Run via Laravel migration only after DBA review.
-- Do not duplicate production_records / model_versions / dataset_snapshots already in blueprint.
CREATE TABLE IF NOT EXISTS monthly_observations (
 id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
 period_month DATE NOT NULL COMMENT 'Always first day of calendar month',
 material_code VARCHAR(80) NOT NULL COMMENT 'e.g. crude_total, diesel, gasoline',
 measure_code VARCHAR(64) NOT NULL COMMENT 'actual_t or target_t, never mix',
 quantity_t DECIMAL(20,3) NOT NULL,
 source_file_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 source_period_status VARCHAR(24) NOT NULL DEFAULT 'actual' COMMENT 'actual or semi_actual; independent from review status',
 revision_number INT NOT NULL DEFAULT 1,
 review_status VARCHAR(24) NOT NULL DEFAULT 'staged' COMMENT 'staged, flagged, approved, rejected',
 approved_by CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL,
 approved_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 CHECK (quantity_t >= 0),
 CHECK (DAYOFMONTH(period_month) = 1),
 CHECK (revision_number >= 1),
 CHECK (review_status IN ('staged','flagged','approved','rejected')),
 CHECK (source_period_status IN ('actual','semi_actual')),
 UNIQUE KEY uq_monthly_observation_revision (period_month,material_code,measure_code,revision_number),
 KEY idx_month_review (period_month,review_status),
 KEY idx_month_material (period_month,material_code,measure_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Future migration: FK on source_file_id/approved_by after verifying IDs/types
-- and existence of source_files/users in the target installation.
-- Use transactionally approved latest revision per period/product. Never hard-delete provenance.
