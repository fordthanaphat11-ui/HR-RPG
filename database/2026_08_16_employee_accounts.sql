CREATE TABLE IF NOT EXISTS employee_accounts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    employee_id INT NOT NULL,
    username VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_by VARCHAR(100) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_employee_accounts_employee (employee_id),
    UNIQUE KEY uq_employee_accounts_username (username),
    CONSTRAINT fk_employee_accounts_employee
        FOREIGN KEY (employee_id) REFERENCES employee (Employee_id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
