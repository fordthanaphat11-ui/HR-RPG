CREATE TABLE IF NOT EXISTS `employee_salaries` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `salary_amount` decimal(12,2) NOT NULL,
  `effective_from` date NOT NULL,
  `reason` varchar(120) DEFAULT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_salary_effective` (`employee_id`,`effective_from`),
  KEY `idx_employee_salary_lookup` (`employee_id`,`effective_from`),
  CONSTRAINT `fk_employee_salaries_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`Employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `employee_salaries` (`employee_id`, `salary_amount`, `effective_from`, `reason`, `created_by`)
SELECT e.`Employee_id`, j.`basic_salary`, e.`Start_date`, 'นำเข้าจากอัตราเงินเดือนเดิม', 'system-migration'
FROM `employee` e
INNER JOIN `job` j ON j.`Job_Title` = e.`jobtitle`
WHERE j.`basic_salary` IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM `employee_salaries` es WHERE es.`employee_id` = e.`Employee_id`
  );
