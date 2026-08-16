CREATE TABLE IF NOT EXISTS attendance_location_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    enforce_geofence TINYINT(1) NOT NULL DEFAULT 0,
    require_check_in TINYINT(1) NOT NULL DEFAULT 1,
    require_check_out TINYINT(1) NOT NULL DEFAULT 1,
    max_accuracy_meters DECIMAL(8,2) NOT NULL DEFAULT 50.00,
    default_latitude DECIMAL(10,7) NULL,
    default_longitude DECIMAL(10,7) NULL,
    updated_by VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO attendance_location_settings (id) VALUES (1);

CREATE TABLE IF NOT EXISTS attendance_geofences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(500) NULL,
    polygon_json JSON NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    scope_type VARCHAR(20) NOT NULL DEFAULT 'all',
    department_id INT NULL,
    priority INT NOT NULL DEFAULT 0,
    created_by VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_attendance_geofences_active (is_active, priority),
    KEY idx_attendance_geofences_department (department_id),
    CONSTRAINT fk_attendance_geofences_department FOREIGN KEY (department_id)
        REFERENCES department (Depart_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE attendance
    ADD COLUMN IF NOT EXISTS check_in_latitude DECIMAL(10,7) NULL AFTER check_in_at,
    ADD COLUMN IF NOT EXISTS check_in_longitude DECIMAL(10,7) NULL AFTER check_in_latitude,
    ADD COLUMN IF NOT EXISTS check_in_accuracy DECIMAL(8,2) NULL AFTER check_in_longitude,
    ADD COLUMN IF NOT EXISTS check_in_geofence_id BIGINT UNSIGNED NULL AFTER check_in_accuracy,
    ADD COLUMN IF NOT EXISTS check_in_geofence_name VARCHAR(160) NULL AFTER check_in_geofence_id,
    ADD COLUMN IF NOT EXISTS check_out_latitude DECIMAL(10,7) NULL AFTER check_out_at,
    ADD COLUMN IF NOT EXISTS check_out_longitude DECIMAL(10,7) NULL AFTER check_out_latitude,
    ADD COLUMN IF NOT EXISTS check_out_accuracy DECIMAL(8,2) NULL AFTER check_out_longitude,
    ADD COLUMN IF NOT EXISTS check_out_geofence_id BIGINT UNSIGNED NULL AFTER check_out_accuracy,
    ADD COLUMN IF NOT EXISTS check_out_geofence_name VARCHAR(160) NULL AFTER check_out_geofence_id;

