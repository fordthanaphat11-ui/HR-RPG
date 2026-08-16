CREATE TABLE IF NOT EXISTS attendance_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    work_start TIME NOT NULL DEFAULT '08:30:00',
    work_end TIME NOT NULL DEFAULT '17:30:00',
    grace_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 10,
    working_days VARCHAR(20) NOT NULL DEFAULT '1,2,3,4,5',
    timezone VARCHAR(64) NOT NULL DEFAULT 'Asia/Bangkok',
    tracking_start_date DATE NULL,
    updated_by VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE attendance_settings ADD COLUMN IF NOT EXISTS tracking_start_date DATE NULL AFTER timezone;
INSERT IGNORE INTO attendance_settings (id, tracking_start_date) VALUES (1, CURRENT_DATE);
UPDATE attendance_settings SET tracking_start_date=CURRENT_DATE WHERE tracking_start_date IS NULL;

CREATE TABLE IF NOT EXISTS attendance (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    attendance_date DATE NOT NULL,
    check_in_at DATETIME NOT NULL,
    check_out_at DATETIME NULL,
    scheduled_start TIME NOT NULL,
    scheduled_end TIME NOT NULL,
    late_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'on_time',
    created_by VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_attendance_employee_date (employee_id, attendance_date),
    KEY idx_attendance_date (attendance_date),
    KEY idx_attendance_employee_period (employee_id, attendance_date),
    CONSTRAINT fk_attendance_employee FOREIGN KEY (employee_id)
        REFERENCES employee (Employee_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance_holidays (
    holiday_date DATE NOT NULL PRIMARY KEY,
    holiday_name VARCHAR(160) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
