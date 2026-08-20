-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               12.3.2-MariaDB - MariaDB Server
-- Server OS:                    Win64
-- HeidiSQL Version:             12.21.0.7344
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for hr-rpg
CREATE DATABASE IF NOT EXISTS `hr-rpg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `hr-rpg`;

-- Dumping structure for table hr-rpg.admin
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) NOT NULL,
  `username` varchar(45) NOT NULL,
  `password` varchar(45) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr-rpg.admin: ~1 rows (approximately)
INSERT INTO `admin` (`id`, `username`, `password`) VALUES
	(100, 'Arjun', 'abc123');

-- Dumping structure for table hr-rpg.attendance
CREATE TABLE IF NOT EXISTS `attendance` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `attendance_date` date NOT NULL,
  `check_in_at` datetime NOT NULL,
  `check_in_latitude` decimal(10,7) DEFAULT NULL,
  `check_in_longitude` decimal(10,7) DEFAULT NULL,
  `check_in_accuracy` decimal(8,2) DEFAULT NULL,
  `check_in_geofence_id` bigint(20) unsigned DEFAULT NULL,
  `check_in_geofence_name` varchar(160) DEFAULT NULL,
  `check_out_at` datetime DEFAULT NULL,
  `check_out_latitude` decimal(10,7) DEFAULT NULL,
  `check_out_longitude` decimal(10,7) DEFAULT NULL,
  `check_out_accuracy` decimal(8,2) DEFAULT NULL,
  `check_out_geofence_id` bigint(20) unsigned DEFAULT NULL,
  `check_out_geofence_name` varchar(160) DEFAULT NULL,
  `scheduled_start` time NOT NULL,
  `scheduled_end` time NOT NULL,
  `late_minutes` smallint(5) unsigned NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'on_time',
  `created_by` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_attendance_employee_date` (`employee_id`,`attendance_date`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_employee_period` (`employee_id`,`attendance_date`),
  CONSTRAINT `fk_attendance_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`Employee_id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.attendance: ~1 rows (approximately)
INSERT INTO `attendance` (`id`, `employee_id`, `attendance_date`, `check_in_at`, `check_in_latitude`, `check_in_longitude`, `check_in_accuracy`, `check_in_geofence_id`, `check_in_geofence_name`, `check_out_at`, `check_out_latitude`, `check_out_longitude`, `check_out_accuracy`, `check_out_geofence_id`, `check_out_geofence_name`, `scheduled_start`, `scheduled_end`, `late_minutes`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
	(5, 1604023, '2026-08-16', '2026-08-16 21:29:17', NULL, NULL, NULL, NULL, NULL, '2026-08-16 21:30:04', NULL, NULL, NULL, NULL, NULL, '08:30:00', '17:30:00', 0, 'on_time', 'emp1604023', '2026-08-16 14:29:17', '2026-08-16 14:30:04');

-- Dumping structure for table hr-rpg.attendance_geofences
CREATE TABLE IF NOT EXISTS `attendance_geofences` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(160) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `polygon_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`polygon_json`)),
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `scope_type` varchar(20) NOT NULL DEFAULT 'all',
  `department_id` int(11) DEFAULT NULL,
  `priority` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_attendance_geofences_active` (`is_active`,`priority`),
  KEY `idx_attendance_geofences_department` (`department_id`),
  CONSTRAINT `fk_attendance_geofences_department` FOREIGN KEY (`department_id`) REFERENCES `department` (`Depart_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.attendance_geofences: ~1 rows (approximately)
INSERT INTO `attendance_geofences` (`id`, `name`, `description`, `polygon_json`, `is_active`, `scope_type`, `department_id`, `priority`, `created_by`, `created_at`, `updated_at`) VALUES
	(3, 'IT', 'ee', '[{"lat":16.5668317,"lng":104.6906948},{"lat":16.5665797,"lng":104.6908932},{"lat":16.5660398,"lng":104.6912471},{"lat":16.5656182,"lng":104.6914938},{"lat":16.5652943,"lng":104.6915688},{"lat":16.5649755,"lng":104.6902498},{"lat":16.5654382,"lng":104.6900031},{"lat":16.5659061,"lng":104.6898744},{"lat":16.5662558,"lng":104.6897726}]', 1, 'all', NULL, 0, 'Arjun', '2026-08-16 15:14:10', '2026-08-16 15:14:10');

-- Dumping structure for table hr-rpg.attendance_holidays
CREATE TABLE IF NOT EXISTS `attendance_holidays` (
  `holiday_date` date NOT NULL,
  `holiday_name` varchar(160) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.attendance_holidays: ~0 rows (approximately)

-- Dumping structure for table hr-rpg.attendance_location_settings
CREATE TABLE IF NOT EXISTS `attendance_location_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `enforce_geofence` tinyint(1) NOT NULL DEFAULT 0,
  `require_check_in` tinyint(1) NOT NULL DEFAULT 1,
  `require_check_out` tinyint(1) NOT NULL DEFAULT 1,
  `max_accuracy_meters` decimal(8,2) NOT NULL DEFAULT 50.00,
  `default_latitude` decimal(10,7) DEFAULT NULL,
  `default_longitude` decimal(10,7) DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.attendance_location_settings: ~1 rows (approximately)
INSERT INTO `attendance_location_settings` (`id`, `enforce_geofence`, `require_check_in`, `require_check_out`, `max_accuracy_meters`, `default_latitude`, `default_longitude`, `updated_by`, `created_at`, `updated_at`) VALUES
	(1, 0, 1, 1, 50.00, NULL, NULL, NULL, '2026-08-16 14:50:54', '2026-08-16 15:13:24');

-- Dumping structure for table hr-rpg.attendance_settings
CREATE TABLE IF NOT EXISTS `attendance_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `work_start` time NOT NULL DEFAULT '08:30:00',
  `work_end` time NOT NULL DEFAULT '17:30:00',
  `grace_minutes` smallint(5) unsigned NOT NULL DEFAULT 10,
  `working_days` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `timezone` varchar(64) NOT NULL DEFAULT 'Asia/Bangkok',
  `tracking_start_date` date DEFAULT NULL,
  `updated_by` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.attendance_settings: ~1 rows (approximately)
INSERT INTO `attendance_settings` (`id`, `work_start`, `work_end`, `grace_minutes`, `working_days`, `timezone`, `tracking_start_date`, `updated_by`, `created_at`, `updated_at`) VALUES
	(1, '08:30:00', '17:30:00', 10, '1,2,3,4,5', 'Asia/Bangkok', '2026-08-16', NULL, '2026-08-16 13:15:17', '2026-08-16 15:13:24');

-- Dumping structure for table hr-rpg.department
CREATE TABLE IF NOT EXISTS `department` (
  `Depart_id` int(11) NOT NULL,
  `Depart_name` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`Depart_id`),
  UNIQUE KEY `Depart_name` (`Depart_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr-rpg.department: ~6 rows (approximately)
INSERT INTO `department` (`Depart_id`, `Depart_name`) VALUES
	(101, 'IT'),
	(102, 'Electronics'),
	(103, 'Customer Care'),
	(104, 'Marketing'),
	(105, 'Development'),
	(106, 'Finance');

-- Dumping structure for table hr-rpg.employee
CREATE TABLE IF NOT EXISTS `employee` (
  `Employee_id` int(11) NOT NULL,
  `Name` varchar(200) NOT NULL,
  `Address` varchar(200) NOT NULL,
  `Phone_no` varchar(15) DEFAULT NULL,
  `Email` varchar(50) DEFAULT NULL,
  `Start_date` date NOT NULL,
  `dob` date NOT NULL,
  `gender` varchar(15) NOT NULL,
  `loan` int(11) NOT NULL,
  `p_fund` int(11) NOT NULL,
  `jobtitle` varchar(50) NOT NULL,
  `Depart_id` int(11) NOT NULL,
  `managesDepart_id` int(11) DEFAULT NULL,
  `bank_accno` int(11) DEFAULT NULL,
  PRIMARY KEY (`Employee_id`),
  UNIQUE KEY `Phone_no` (`Phone_no`),
  UNIQUE KEY `Email` (`Email`),
  UNIQUE KEY `bank_accno` (`bank_accno`),
  KEY `employee_ibfk_1` (`Depart_id`),
  KEY `employee_ibfk_2` (`managesDepart_id`),
  KEY `employee_ibfk_3` (`jobtitle`),
  CONSTRAINT `employee_ibfk_1` FOREIGN KEY (`Depart_id`) REFERENCES `department` (`Depart_id`),
  CONSTRAINT `employee_ibfk_2` FOREIGN KEY (`managesDepart_id`) REFERENCES `department` (`Depart_id`),
  CONSTRAINT `employee_ibfk_3` FOREIGN KEY (`jobtitle`) REFERENCES `job` (`Job_Title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr-rpg.employee: ~10 rows (approximately)
INSERT INTO `employee` (`Employee_id`, `Name`, `Address`, `Phone_no`, `Email`, `Start_date`, `dob`, `gender`, `loan`, `p_fund`, `jobtitle`, `Depart_id`, `managesDepart_id`, `bank_accno`) VALUES
	(1604023, 'Arun', 'Hyderabad', '9988776655', 'arun@gmail.com', '2018-10-10', '1996-02-29', 'male', 0, 2000, 'manager', 106, 106, 236954128),
	(1604025, 'Bhuvan', 'Chennai', '9977661230', 'bhuvan@gmail.com', '2018-12-18', '2000-01-01', 'male', 3430, 2625, 'executive', 104, NULL, 123654784),
	(1604026, 'Charan', 'Mumbai', '8809765432', 'charan026@gmail.com', '2018-08-14', '1996-07-11', 'male', 0, 2250, 'manager', 102, 102, 365488911),
	(1604027, 'David', 'Delhi', '6303453211', 'david4@gmail.com', '2018-11-01', '1998-09-11', 'male', 4000, 750, 'executive', 103, NULL, 313515669),
	(1604045, 'Sohail', 'Rajasthan', '7654321231', 'sohail@gmail.com', '2019-01-18', '1997-10-25', 'male', 4513, 750, 'executive', 101, NULL, 125432874),
	(1604060, 'Prakhar', 'Pune', '8193264912', 'prakhar16@gmail.com', '2019-01-03', '1997-06-04', 'male', 0, 1250, 'manager', 101, 101, 154297830),
	(1604073, 'Naveen', 'Vellore', '9869803351', 'naveen007@gmail.com', '2018-09-11', '1997-01-25', 'male', 0, 1500, 'accountant', 105, NULL, 147483647),
	(1604078, 'Vinay', 'Madhya Pradesh', '9152140632', 'viany877@gmail.com', '2019-01-18', '1998-03-02', 'male', 0, 3250, 'chief', 101, NULL, 247483647),
	(1604083, 'Bishal', 'Delhi', '7474244680', 'bishal@gmail.com', '2014-06-02', '1997-11-14', 'male', 0, 750, 'executive', 105, NULL, 321569874),
	(1604110, 'Riya', 'Delhi', '7637100931', 'riya143@gmail.com', '2018-10-22', '1999-08-28', 'female', 0, 1250, 'director', 104, NULL, 497483647);

-- Dumping structure for table hr-rpg.employee_accounts
CREATE TABLE IF NOT EXISTS `employee_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_employee_accounts_employee` (`employee_id`),
  UNIQUE KEY `uq_employee_accounts_username` (`username`),
  CONSTRAINT `fk_employee_accounts_employee` FOREIGN KEY (`employee_id`) REFERENCES `employee` (`Employee_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.employee_accounts: ~1 rows (approximately)
INSERT INTO `employee_accounts` (`id`, `employee_id`, `username`, `password_hash`, `is_active`, `must_change_password`, `last_login_at`, `created_by`, `created_at`, `updated_at`) VALUES
	(5, 1604023, 'emp1604023', '$2y$12$Lvk2EyP.WaAYho2K12Svn.4onaQZvD0FcWwpdDBGh2Gbs20/7c5Rq', 1, 0, '2026-08-16 21:28:38', 'Arjun', '2026-08-16 14:27:50', '2026-08-16 14:29:08');

-- Dumping structure for table hr-rpg.job
CREATE TABLE IF NOT EXISTS `job` (
  `Job_Title` varchar(20) NOT NULL,
  `basic_salary` int(10) DEFAULT NULL,
  PRIMARY KEY (`Job_Title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr-rpg.job: ~5 rows (approximately)
INSERT INTO `job` (`Job_Title`, `basic_salary`) VALUES
	('accountant', 35000),
	('chief', 60000),
	('director', 50000),
	('executive', 45000),
	('manager', 40000);

-- Dumping structure for table hr-rpg.payment
CREATE TABLE IF NOT EXISTS `payment` (
  `pay_no` int(11) DEFAULT NULL,
  `emp_id` int(11) NOT NULL,
  `year` int(11) NOT NULL,
  `month` varchar(50) NOT NULL,
  `absence` int(11) NOT NULL,
  `loan_cut` float NOT NULL,
  `pfund_cut` float NOT NULL,
  `overtime` float NOT NULL,
  `season_bonus` float NOT NULL,
  `other_bonus` float NOT NULL,
  `medi_allow` float NOT NULL,
  `house_allow` float NOT NULL,
  `total_pay` float NOT NULL,
  PRIMARY KEY (`emp_id`,`year`,`month`),
  UNIQUE KEY `pay_no` (`pay_no`),
  CONSTRAINT `payment_ibfk_1` FOREIGN KEY (`emp_id`) REFERENCES `employee` (`Employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table hr-rpg.payment: ~8 rows (approximately)
INSERT INTO `payment` (`pay_no`, `emp_id`, `year`, `month`, `absence`, `loan_cut`, `pfund_cut`, `overtime`, `season_bonus`, `other_bonus`, `medi_allow`, `house_allow`, `total_pay`) VALUES
	(1234, 1604023, 2018, 'december', 2, 0, 1000, 6, 2000, 0, 1200, 3200, 45800),
	(1242, 1604025, 2026, 'august', 4, 180.5, 1125, 0, 0, 0, 1350, 3600, 47844.5),
	(1235, 1604026, 2018, 'november', 2, 0, 1000, 6, 2000, 0, 1200, 3200, 45800),
	(1241, 1604026, 2026, 'august', 0, 0, 1000, 0, 0, 0, 1200, 3200, 43400),
	(1238, 1604027, 2018, 'october', 1, 200, 1125, 3, 2000, 0, 1350, 3600, 51325),
	(1239, 1604073, 2018, 'november', 3, 0, 1500, 5, 2000, 0, 1800, 4800, 68000),
	(1243, 1604078, 2026, 'august', 2, 0, 1500, 0, 0, 0, 1800, 4800, 64880),
	(1240, 1604110, 2018, 'december', 5, 0, 1250, 4, 2000, 0, 1500, 4000, 56450);

-- Dumping structure for table hr-rpg.payment_snapshots
CREATE TABLE IF NOT EXISTS `payment_snapshots` (
  `pay_no` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `payroll_year` int(11) NOT NULL,
  `payroll_month` varchar(20) NOT NULL,
  `base_salary` decimal(12,2) NOT NULL,
  `total_additions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL,
  `absence_days` decimal(8,2) DEFAULT NULL,
  `absence_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `absence_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_count` int(11) DEFAULT NULL,
  `late_minutes` int(11) DEFAULT NULL,
  `late_rate` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_deduction` decimal(12,2) NOT NULL DEFAULT 0.00,
  `attendance_source` varchar(20) NOT NULL DEFAULT 'unavailable',
  `payment_note` text DEFAULT NULL,
  `settings_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_snapshot`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pay_no`),
  KEY `idx_snapshot_employee_period` (`emp_id`,`payroll_year`,`payroll_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.payment_snapshots: ~8 rows (approximately)
INSERT INTO `payment_snapshots` (`pay_no`, `emp_id`, `payroll_year`, `payroll_month`, `base_salary`, `total_additions`, `total_deductions`, `net_salary`, `absence_days`, `absence_rate`, `absence_deduction`, `late_count`, `late_minutes`, `late_rate`, `late_deduction`, `attendance_source`, `payment_note`, `settings_snapshot`, `created_at`) VALUES
	(1234, 1604023, 2018, 'december', 39000.00, 8200.00, 1400.00, 45800.00, 2.00, 200.00, 400.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1235, 1604026, 2018, 'november', 39000.00, 8200.00, 1400.00, 45800.00, 2.00, 200.00, 400.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1238, 1604027, 2018, 'october', 45000.00, 7850.00, 1525.00, 51325.00, 1.00, 200.00, 200.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1239, 1604073, 2018, 'november', 60000.00, 10100.00, 2100.00, 68000.00, 3.00, 200.00, 600.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1240, 1604110, 2018, 'december', 50000.00, 8700.00, 2250.00, 56450.00, 5.00, 200.00, 1000.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1241, 1604026, 2026, 'august', 40000.00, 4400.00, 1000.00, 43400.00, 0.00, 0.00, 0.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1242, 1604025, 2026, 'august', 45000.00, 4950.00, 2105.50, 47844.50, 4.00, 200.00, 800.00, NULL, NULL, 0.00, 0.00, 'legacy', NULL, '{"legacy": true, "absence_rate": 200, "overtime_rate": 300}', '2026-08-16 10:08:15'),
	(1243, 1604078, 2026, 'august', 60000.00, 6600.00, 1720.00, 64880.00, 2.00, 100.00, 200.00, NULL, 120, 5.00, 20.00, 'manual', '', '{"id":"1","absence_deduction_enabled":"1","absence_deduction_per_day":"100.00","late_deduction_mode":"per_minutes","late_deduction_per_occurrence":"5.00","late_interval_minutes":"30","late_deduction_per_interval":"5.00","late_rounding_mode":"ceil","late_grace_minutes":"0","max_late_deduction":null,"updated_by":"Arjun","created_at":"2026-08-16 17:08:15","updated_at":"2026-08-16 17:20:14"}', '2026-08-16 11:47:45');

-- Dumping structure for table hr-rpg.payroll_adjustments
CREATE TABLE IF NOT EXISTS `payroll_adjustments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pay_no` int(11) NOT NULL,
  `emp_id` int(11) NOT NULL,
  `adjustment_type` varchar(12) NOT NULL,
  `adjustment_source` varchar(30) NOT NULL,
  `adjustment_name` varchar(120) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_adjustment_payment` (`pay_no`),
  KEY `idx_adjustment_employee` (`emp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.payroll_adjustments: ~45 rows (approximately)
INSERT INTO `payroll_adjustments` (`id`, `pay_no`, `emp_id`, `adjustment_type`, `adjustment_source`, `adjustment_name`, `amount`, `note`, `created_at`) VALUES
	(1, 1234, 1604023, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', 1800.00, NULL, '2026-08-16 10:08:15'),
	(2, 1235, 1604026, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', 1800.00, NULL, '2026-08-16 10:08:15'),
	(3, 1238, 1604027, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', 900.00, NULL, '2026-08-16 10:08:15'),
	(4, 1239, 1604073, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', 1500.00, NULL, '2026-08-16 10:08:15'),
	(5, 1240, 1604110, 'addition', 'legacy_overtime', 'ค่าล่วงเวลา', 1200.00, NULL, '2026-08-16 10:08:15'),
	(8, 1234, 1604023, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', 2000.00, NULL, '2026-08-16 10:08:15'),
	(9, 1235, 1604026, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', 2000.00, NULL, '2026-08-16 10:08:15'),
	(10, 1238, 1604027, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', 2000.00, NULL, '2026-08-16 10:08:15'),
	(11, 1239, 1604073, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', 2000.00, NULL, '2026-08-16 10:08:15'),
	(12, 1240, 1604110, 'addition', 'legacy_season_bonus', 'โบนัสตามฤดูกาล', 2000.00, NULL, '2026-08-16 10:08:15'),
	(15, 1234, 1604023, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1200.00, NULL, '2026-08-16 10:08:15'),
	(16, 1242, 1604025, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1350.00, NULL, '2026-08-16 10:08:15'),
	(17, 1235, 1604026, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1200.00, NULL, '2026-08-16 10:08:15'),
	(18, 1241, 1604026, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1200.00, NULL, '2026-08-16 10:08:15'),
	(19, 1238, 1604027, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1350.00, NULL, '2026-08-16 10:08:15'),
	(20, 1239, 1604073, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1800.00, NULL, '2026-08-16 10:08:15'),
	(21, 1240, 1604110, 'addition', 'legacy_medical', 'ค่ารักษาพยาบาล', 1500.00, NULL, '2026-08-16 10:08:15'),
	(22, 1234, 1604023, 'addition', 'legacy_housing', 'ค่าที่พัก', 3200.00, NULL, '2026-08-16 10:08:15'),
	(23, 1242, 1604025, 'addition', 'legacy_housing', 'ค่าที่พัก', 3600.00, NULL, '2026-08-16 10:08:15'),
	(24, 1235, 1604026, 'addition', 'legacy_housing', 'ค่าที่พัก', 3200.00, NULL, '2026-08-16 10:08:15'),
	(25, 1241, 1604026, 'addition', 'legacy_housing', 'ค่าที่พัก', 3200.00, NULL, '2026-08-16 10:08:15'),
	(26, 1238, 1604027, 'addition', 'legacy_housing', 'ค่าที่พัก', 3600.00, NULL, '2026-08-16 10:08:15'),
	(27, 1239, 1604073, 'addition', 'legacy_housing', 'ค่าที่พัก', 4800.00, NULL, '2026-08-16 10:08:15'),
	(28, 1240, 1604110, 'addition', 'legacy_housing', 'ค่าที่พัก', 4000.00, NULL, '2026-08-16 10:08:15'),
	(29, 1234, 1604023, 'deduction', 'legacy_absence', 'ขาดงาน', 400.00, NULL, '2026-08-16 10:08:15'),
	(30, 1242, 1604025, 'deduction', 'legacy_absence', 'ขาดงาน', 800.00, NULL, '2026-08-16 10:08:15'),
	(31, 1235, 1604026, 'deduction', 'legacy_absence', 'ขาดงาน', 400.00, NULL, '2026-08-16 10:08:15'),
	(32, 1238, 1604027, 'deduction', 'legacy_absence', 'ขาดงาน', 200.00, NULL, '2026-08-16 10:08:15'),
	(33, 1239, 1604073, 'deduction', 'legacy_absence', 'ขาดงาน', 600.00, NULL, '2026-08-16 10:08:15'),
	(34, 1240, 1604110, 'deduction', 'legacy_absence', 'ขาดงาน', 1000.00, NULL, '2026-08-16 10:08:15'),
	(36, 1242, 1604025, 'deduction', 'legacy_loan', 'หักเงินยืม', 180.50, NULL, '2026-08-16 10:08:15'),
	(37, 1238, 1604027, 'deduction', 'legacy_loan', 'หักเงินยืม', 200.00, NULL, '2026-08-16 10:08:15'),
	(39, 1234, 1604023, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1000.00, NULL, '2026-08-16 10:08:15'),
	(40, 1242, 1604025, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1125.00, NULL, '2026-08-16 10:08:15'),
	(41, 1235, 1604026, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1000.00, NULL, '2026-08-16 10:08:15'),
	(42, 1241, 1604026, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1000.00, NULL, '2026-08-16 10:08:15'),
	(43, 1238, 1604027, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1125.00, NULL, '2026-08-16 10:08:15'),
	(44, 1239, 1604073, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1500.00, NULL, '2026-08-16 10:08:15'),
	(45, 1240, 1604110, 'deduction', 'legacy_fund', 'กองทุนสำรองเลี้ยงชีพ', 1250.00, NULL, '2026-08-16 10:08:15'),
	(59, 1243, 1604078, 'addition', 'medical', 'ค่ารักษาพยาบาล', 1800.00, '3% ของเงินเดือนพื้นฐาน', '2026-08-16 11:47:45'),
	(60, 1243, 1604078, 'addition', 'housing', 'ค่าที่พัก', 4800.00, '8% ของเงินเดือนพื้นฐาน', '2026-08-16 11:47:45'),
	(61, 1243, 1604078, 'deduction', 'loan', 'หักเงินยืม', 0.00, '5% ของยอดเงินยืมคงเหลือ', '2026-08-16 11:47:45'),
	(62, 1243, 1604078, 'deduction', 'provident_fund', 'กองทุนสำรองเลี้ยงชีพ', 1500.00, '2.5% ของเงินเดือนพื้นฐาน', '2026-08-16 11:47:45'),
	(63, 1243, 1604078, 'deduction', 'absence', 'ขาดงาน', 200.00, '2.00 วัน × ฿100.00/วัน', '2026-08-16 11:47:45'),
	(64, 1243, 1604078, 'deduction', 'late', 'มาสาย', 20.00, '120 นาที − ผ่อนผัน 0 นาที; 4 รอบ × ฿5.00', '2026-08-16 11:47:45');

-- Dumping structure for table hr-rpg.payroll_settings
CREATE TABLE IF NOT EXISTS `payroll_settings` (
  `id` tinyint(3) unsigned NOT NULL,
  `absence_deduction_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `absence_deduction_mode` varchar(20) NOT NULL DEFAULT 'fixed',
  `absence_deduction_per_day` decimal(12,2) NOT NULL DEFAULT 0.00,
  `absence_salary_divisor_days` smallint(5) unsigned NOT NULL DEFAULT 30,
  `late_deduction_mode` varchar(20) NOT NULL DEFAULT 'none',
  `late_deduction_per_occurrence` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_interval_minutes` int(10) unsigned NOT NULL DEFAULT 30,
  `late_deduction_per_interval` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_deduction_per_minute` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_rounding_mode` varchar(10) NOT NULL DEFAULT 'ceil',
  `late_grace_minutes` int(10) unsigned NOT NULL DEFAULT 0,
  `max_late_deduction` decimal(12,2) DEFAULT NULL,
  `allow_negative_net_salary` tinyint(1) NOT NULL DEFAULT 0,
  `updated_by` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table hr-rpg.payroll_settings: ~1 rows (approximately)
INSERT INTO `payroll_settings` (`id`, `absence_deduction_enabled`, `absence_deduction_mode`, `absence_deduction_per_day`, `absence_salary_divisor_days`, `late_deduction_mode`, `late_deduction_per_occurrence`, `late_interval_minutes`, `late_deduction_per_interval`, `late_deduction_per_minute`, `late_rounding_mode`, `late_grace_minutes`, `max_late_deduction`, `allow_negative_net_salary`, `updated_by`, `created_at`, `updated_at`) VALUES
	(1, 1, 'fixed', 100.00, 30, 'per_minutes', 5.00, 30, 5.00, 0.00, 'ceil', 0, NULL, 0, 'Arjun', '2026-08-16 10:08:15', '2026-08-16 10:20:14');

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
