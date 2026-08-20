ALTER TABLE payroll_settings
    ADD COLUMN IF NOT EXISTS absence_deduction_mode VARCHAR(20) NOT NULL DEFAULT 'fixed' AFTER absence_deduction_enabled,
    ADD COLUMN IF NOT EXISTS absence_salary_divisor_days SMALLINT UNSIGNED NOT NULL DEFAULT 30 AFTER absence_deduction_per_day,
    ADD COLUMN IF NOT EXISTS late_deduction_per_minute DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER late_deduction_per_interval,
    ADD COLUMN IF NOT EXISTS allow_negative_net_salary TINYINT(1) NOT NULL DEFAULT 0 AFTER max_late_deduction;

UPDATE payroll_settings
SET absence_deduction_mode = 'fixed'
WHERE absence_deduction_mode NOT IN ('fixed', 'daily_salary');

UPDATE payroll_settings
SET absence_salary_divisor_days = 30
WHERE absence_salary_divisor_days = 0;

UPDATE payroll_settings
SET late_deduction_mode = 'none'
WHERE late_deduction_mode NOT IN ('none', 'per_occurrence', 'per_minutes', 'per_actual_minute');
