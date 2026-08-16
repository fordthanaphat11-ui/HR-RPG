CREATE TABLE IF NOT EXISTS payroll_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    absence_deduction_enabled TINYINT(1) NOT NULL DEFAULT 0,
    absence_deduction_per_day DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_deduction_mode VARCHAR(20) NOT NULL DEFAULT 'none',
    late_deduction_per_occurrence DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_interval_minutes INT UNSIGNED NOT NULL DEFAULT 30,
    late_deduction_per_interval DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_rounding_mode VARCHAR(10) NOT NULL DEFAULT 'ceil',
    late_grace_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    max_late_deduction DECIMAL(12,2) NULL,
    updated_by VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payroll_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS payment_snapshots (
    pay_no INT NOT NULL PRIMARY KEY,
    emp_id INT NOT NULL,
    payroll_year INT NOT NULL,
    payroll_month VARCHAR(20) NOT NULL,
    base_salary DECIMAL(12,2) NOT NULL,
    total_additions DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_deductions DECIMAL(12,2) NOT NULL DEFAULT 0,
    net_salary DECIMAL(12,2) NOT NULL,
    absence_days DECIMAL(8,2) NULL,
    absence_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    absence_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_count INT NULL,
    late_minutes INT NULL,
    late_rate DECIMAL(12,2) NOT NULL DEFAULT 0,
    late_deduction DECIMAL(12,2) NOT NULL DEFAULT 0,
    attendance_source VARCHAR(20) NOT NULL DEFAULT 'unavailable',
    payment_note TEXT NULL,
    settings_snapshot JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_snapshot_employee_period (emp_id, payroll_year, payroll_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payroll_adjustments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    pay_no INT NOT NULL,
    emp_id INT NOT NULL,
    adjustment_type VARCHAR(12) NOT NULL,
    adjustment_source VARCHAR(30) NOT NULL,
    adjustment_name VARCHAR(120) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    note VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_adjustment_payment (pay_no),
    KEY idx_adjustment_employee (emp_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO payment_snapshots
    (pay_no, emp_id, payroll_year, payroll_month, base_salary, total_additions,
     total_deductions, net_salary, absence_days, absence_rate, absence_deduction,
     late_count, late_minutes, late_rate, late_deduction, attendance_source,
     settings_snapshot, created_at)
SELECT
    p.pay_no,
    p.emp_id,
    p.year,
    LOWER(p.month),
    p.total_pay - ((p.overtime * 300) + p.season_bonus + p.other_bonus + p.medi_allow + p.house_allow)
        + (p.loan_cut + (p.absence * 200) + p.pfund_cut),
    (p.overtime * 300) + p.season_bonus + p.other_bonus + p.medi_allow + p.house_allow,
    p.loan_cut + (p.absence * 200) + p.pfund_cut,
    p.total_pay,
    p.absence,
    CASE WHEN p.absence > 0 THEN 200 ELSE 0 END,
    p.absence * 200,
    NULL,
    NULL,
    0,
    0,
    'legacy',
    JSON_OBJECT('legacy', TRUE, 'absence_rate', 200, 'overtime_rate', 300),
    CURRENT_TIMESTAMP
FROM payment p
WHERE p.pay_no IS NOT NULL;

INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', p.overtime * 300 FROM payment p
WHERE p.overtime > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_overtime');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', p.season_bonus FROM payment p
WHERE p.season_bonus > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_season_bonus');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'addition', 'legacy_other_bonus', 'โบนัสอื่น ๆ', p.other_bonus FROM payment p
WHERE p.other_bonus > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_other_bonus');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', p.medi_allow FROM payment p
WHERE p.medi_allow > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_medical');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'addition', 'legacy_housing', 'ค่าที่พัก', p.house_allow FROM payment p
WHERE p.house_allow > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_housing');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'deduction', 'legacy_absence', 'ขาดงาน', p.absence * 200 FROM payment p
WHERE p.absence > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_absence');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'deduction', 'legacy_loan', 'หักเงินยืม', p.loan_cut FROM payment p
WHERE p.loan_cut > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_loan');
INSERT INTO payroll_adjustments (pay_no, emp_id, adjustment_type, adjustment_source, adjustment_name, amount)
SELECT p.pay_no, p.emp_id, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', p.pfund_cut FROM payment p
WHERE p.pfund_cut > 0 AND NOT EXISTS (SELECT 1 FROM payroll_adjustments a WHERE a.pay_no=p.pay_no AND a.adjustment_source='legacy_fund');
