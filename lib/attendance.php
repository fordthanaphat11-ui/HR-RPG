<?php
declare(strict_types=1);
require_once __DIR__ . '/geofence.php';

function attendanceMonthNames(): array
{
    return [
        'january'=>'มกราคม','february'=>'กุมภาพันธ์','march'=>'มีนาคม','april'=>'เมษายน',
        'may'=>'พฤษภาคม','june'=>'มิถุนายน','july'=>'กรกฎาคม','august'=>'สิงหาคม',
        'september'=>'กันยายน','october'=>'ตุลาคม','november'=>'พฤศจิกายน','december'=>'ธันวาคม',
    ];
}

function getAttendanceSettings(mysqli $connection): array
{
    $result = mysqli_query($connection, 'SELECT * FROM attendance_settings WHERE id=1 LIMIT 1');
    $settings = $result ? mysqli_fetch_assoc($result) : null;
    return $settings ?: [
        'id'=>1, 'work_start'=>'08:30:00', 'work_end'=>'17:30:00', 'grace_minutes'=>10,
        'working_days'=>'1,2,3,4,5', 'timezone'=>'Asia/Bangkok', 'tracking_start_date'=>date('Y-m-d'), 'updated_by'=>null, 'updated_at'=>null,
    ];
}

function attendanceTimezone(array $settings): DateTimeZone
{
    try {
        return new DateTimeZone((string) ($settings['timezone'] ?? 'Asia/Bangkok'));
    } catch (Throwable) {
        return new DateTimeZone('Asia/Bangkok');
    }
}

function attendanceNow(array $settings): DateTimeImmutable
{
    return new DateTimeImmutable('now', attendanceTimezone($settings));
}

function attendanceCheckInWindow(array $settings, ?DateTimeImmutable $now = null): array
{
    $timezone = attendanceTimezone($settings);
    $current = $now ? $now->setTimezone($timezone) : attendanceNow($settings);
    $date = $current->format('Y-m-d');
    $workStart = new DateTimeImmutable($date . ' ' . (string) $settings['work_start'], $timezone);
    $workEnd = new DateTimeImmutable($date . ' ' . (string) $settings['work_end'], $timezone);

    return [
        'now' => $current,
        'work_start' => $workStart,
        'work_end' => $workEnd,
        'can_check_in' => $current <= $workEnd,
        'is_early' => $current < $workStart,
    ];
}

function attendanceWorkingDays(array $settings): array
{
    $days = array_map('intval', array_filter(explode(',', (string) ($settings['working_days'] ?? '1,2,3,4,5')), 'strlen'));
    $days = array_values(array_unique(array_filter($days, static fn (int $day): bool => $day >= 1 && $day <= 7)));
    sort($days);
    return $days ?: [1,2,3,4,5];
}

function attendanceCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return (string) $_SESSION['csrf_token'];
}

function attendanceValidateCsrf(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}

function attendanceFormatDuration(?string $checkIn, ?string $checkOut): string
{
    if (!$checkIn) return '--';
    if (!$checkOut) return 'กำลังทำงาน';
    $start = new DateTimeImmutable($checkIn);
    $end = new DateTimeImmutable($checkOut);
    $minutes = max(0, (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60));
    return intdiv($minutes, 60) . ' ชม. ' . ($minutes % 60) . ' นาที';
}

function attendanceStatusMeta(?array $record): array
{
    if (!$record) return ['label'=>'ยังไม่เข้างาน','class'=>'bg-[#f2f2f2] text-[#6d7175]','icon'=>'fa-clock'];
    if (($record['status'] ?? '') === 'absent') return ['label'=>'ขาดงาน','class'=>'bg-red-50 text-red-700','icon'=>'fa-user-xmark'];
    if ((int) ($record['late_minutes'] ?? 0) > 0) return ['label'=>'มาสาย','class'=>'bg-amber-50 text-amber-700','icon'=>'fa-clock'];
    return ['label'=>'ตรงเวลา','class'=>'bg-emerald-50 text-emerald-700','icon'=>'fa-circle-check'];
}

function attendanceEmployeeList(mysqli $connection, string $date): array
{
    $sql = "SELECT e.Employee_id, e.Name, e.Start_date, e.jobtitle, e.Depart_id,
                   e.loan, e.p_fund, d.Depart_name, j.basic_salary,
                   a.check_in_at AS attendance_check_in, a.check_out_at AS attendance_check_out,
                   a.late_minutes AS attendance_late_minutes, a.status AS attendance_status,
                   COALESCE(h.history_count, 0) AS history_count
            FROM employee e
            INNER JOIN job j ON j.Job_Title=e.jobtitle
            LEFT JOIN department d ON d.Depart_id=e.Depart_id
            LEFT JOIN attendance a ON a.employee_id=e.Employee_id AND a.attendance_date=?
            LEFT JOIN (SELECT employee_id, COUNT(*) AS history_count FROM attendance GROUP BY employee_id) h ON h.employee_id=e.Employee_id
            ORDER BY e.Name ASC, e.Employee_id ASC";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 's', $date);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function attendanceFindToday(mysqli $connection, int $employeeId, string $date): ?array
{
    $stmt = mysqli_prepare($connection, 'SELECT * FROM attendance WHERE employee_id=? AND attendance_date=? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'is', $employeeId, $date);
    mysqli_stmt_execute($stmt);
    $record = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $record ?: null;
}

function attendanceCalculateLateMinutes(DateTimeImmutable $actual, array $settings): int
{
    if (!in_array((int) $actual->format('N'), attendanceWorkingDays($settings), true)) return 0;
    $deadline = new DateTimeImmutable($actual->format('Y-m-d') . ' ' . $settings['work_start'], attendanceTimezone($settings));
    $deadline = $deadline->modify('+' . max(0, (int) $settings['grace_minutes']) . ' minutes');
    return max(0, (int) floor(($actual->getTimestamp() - $deadline->getTimestamp()) / 60));
}

function attendanceProcessAction(mysqli $connection, int $employeeId, string $action, string $username, array $settings, array $locationInput = []): array
{
    if (!in_array($action, ['check_in','check_out'], true)) throw new InvalidArgumentException('คำสั่งลงเวลาไม่ถูกต้อง');
    $now = attendanceNow($settings);
    $date = $now->format('Y-m-d');
    $nowSql = $now->format('Y-m-d H:i:s');
    $employeeStmt = mysqli_prepare($connection, 'SELECT Employee_id, Name FROM employee WHERE Employee_id=? LIMIT 1');
    mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
    mysqli_stmt_execute($employeeStmt);
    $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
    if (!$employee) throw new RuntimeException('ไม่พบพนักงานที่เลือก');

    mysqli_begin_transaction($connection);
    try {
        $recordStmt = mysqli_prepare($connection, 'SELECT * FROM attendance WHERE employee_id=? AND attendance_date=? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($recordStmt, 'is', $employeeId, $date);
        mysqli_stmt_execute($recordStmt);
        $record = mysqli_fetch_assoc(mysqli_stmt_get_result($recordStmt));

        if ($action === 'check_in') {
            if ($record) throw new RuntimeException('พนักงานคนนี้เช็คชื่อเข้างานวันนี้แล้ว');
            $checkInWindow = attendanceCheckInWindow($settings, $now);
            if (!$checkInWindow['can_check_in']) {
                throw new RuntimeException('ไม่สามารถเช็คชื่อเข้างานได้ · เลยเวลาเลิกงาน ' . $checkInWindow['work_end']->format('H:i') . ' แล้ว');
            }
            $location = geofenceValidateAttendanceLocation($connection, $employeeId, $action, $locationInput);
            $matched = $location['geofence'];
            $latitude = $location['latitude'];
            $longitude = $location['longitude'];
            $accuracy = $location['accuracy'];
            $geofenceId = $matched ? (int) $matched['id'] : null;
            $geofenceName = $matched ? (string) $matched['name'] : null;
            $lateMinutes = attendanceCalculateLateMinutes($now, $settings);
            $status = $lateMinutes > 0 ? 'late' : 'on_time';
            $workStart = (string) $settings['work_start'];
            $workEnd = (string) $settings['work_end'];
            $insert = mysqli_prepare($connection, 'INSERT INTO attendance (employee_id,attendance_date,check_in_at,check_in_latitude,check_in_longitude,check_in_accuracy,check_in_geofence_id,check_in_geofence_name,scheduled_start,scheduled_end,late_minutes,status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            mysqli_stmt_bind_param($insert, 'issdddisssiss', $employeeId, $date, $nowSql, $latitude, $longitude, $accuracy, $geofenceId, $geofenceName, $workStart, $workEnd, $lateMinutes, $status, $username);
            if (!mysqli_stmt_execute($insert)) throw new RuntimeException('ไม่สามารถบันทึกเวลาเข้างานได้');
            mysqli_commit($connection);
            return ['message'=>'เช็คชื่อเข้างานเรียบร้อย ' . $now->format('H:i') . ($geofenceName ? ' · ' . $geofenceName : ''), 'time'=>$now->format('H:i'), 'geofence_name'=>$geofenceName, 'accuracy'=>$accuracy];
        }

        if (!$record) throw new RuntimeException('ยังไม่มีเวลาเข้างานของพนักงานวันนี้');
        if (!empty($record['check_out_at'])) throw new RuntimeException('พนักงานคนนี้เช็คชื่อออกงานวันนี้แล้ว');
        if ($nowSql < (string) $record['check_in_at']) throw new RuntimeException('เวลาออกงานต้องไม่ก่อนเวลาเข้างาน');
        $location = geofenceValidateAttendanceLocation($connection, $employeeId, $action, $locationInput);
        $matched = $location['geofence'];
        $latitude = $location['latitude'];
        $longitude = $location['longitude'];
        $accuracy = $location['accuracy'];
        $geofenceId = $matched ? (int) $matched['id'] : null;
        $geofenceName = $matched ? (string) $matched['name'] : null;
        $update = mysqli_prepare($connection, 'UPDATE attendance SET check_out_at=?,check_out_latitude=?,check_out_longitude=?,check_out_accuracy=?,check_out_geofence_id=?,check_out_geofence_name=? WHERE id=? AND check_out_at IS NULL');
        $recordId = (int) $record['id'];
        mysqli_stmt_bind_param($update, 'sdddisi', $nowSql, $latitude, $longitude, $accuracy, $geofenceId, $geofenceName, $recordId);
        if (!mysqli_stmt_execute($update) || mysqli_stmt_affected_rows($update) !== 1) throw new RuntimeException('ไม่สามารถบันทึกเวลาออกงานได้');
        mysqli_commit($connection);
        return ['message'=>'เช็คชื่อออกงานเรียบร้อย ' . $now->format('H:i') . ($geofenceName ? ' · ' . $geofenceName : ''), 'time'=>$now->format('H:i'), 'geofence_name'=>$geofenceName, 'accuracy'=>$accuracy];
    } catch (Throwable $exception) {
        mysqli_rollback($connection);
        throw $exception;
    }
}

function attendancePeriodMetrics(mysqli $connection, int $employeeId, int $year, string $month): array
{
    $months = array_keys(attendanceMonthNames());
    $monthNumber = array_search(strtolower($month), $months, true);
    if ($monthNumber === false || $year < 2000 || $year > 2200) throw new InvalidArgumentException('งวดการลงเวลาไม่ถูกต้อง');
    $monthNumber++;
    $settings = getAttendanceSettings($connection);
    $timezone = attendanceTimezone($settings);
    $start = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $monthNumber), $timezone);
    $end = $start->modify('last day of this month');
    $now = attendanceNow($settings);
    $today = $now->setTime(0, 0);
    $todayWorkEnd = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $settings['work_end'], $timezone);
    $completedThrough = $now >= $todayWorkEnd ? $today : $today->modify('-1 day');
    if ($end > $completedThrough) $end = $completedThrough;

    $employeeStmt = mysqli_prepare($connection, 'SELECT Start_date FROM employee WHERE Employee_id=? LIMIT 1');
    mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
    mysqli_stmt_execute($employeeStmt);
    $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
    if (!$employee) throw new RuntimeException('ไม่พบพนักงาน');
    $employmentStart = new DateTimeImmutable((string) $employee['Start_date'], $timezone);
    if ($start < $employmentStart) $start = $employmentStart;
    $trackingStart = new DateTimeImmutable((string) ($settings['tracking_start_date'] ?: $now->format('Y-m-d')), $timezone);
    if ($start < $trackingStart) $start = $trackingStart;

    $holidayMap = [];
    if ($end >= $start) {
        $from = $start->format('Y-m-d');
        $to = $end->format('Y-m-d');
        $holidayStmt = mysqli_prepare($connection, 'SELECT holiday_date FROM attendance_holidays WHERE holiday_date BETWEEN ? AND ?');
        mysqli_stmt_bind_param($holidayStmt, 'ss', $from, $to);
        mysqli_stmt_execute($holidayStmt);
        $holidayResult = mysqli_stmt_get_result($holidayStmt);
        while ($holiday = mysqli_fetch_assoc($holidayResult)) $holidayMap[$holiday['holiday_date']] = true;
    }

    $expectedDates = [];
    $workingDays = attendanceWorkingDays($settings);
    for ($date = $start; $end >= $start && $date <= $end; $date = $date->modify('+1 day')) {
        $key = $date->format('Y-m-d');
        if (in_array((int) $date->format('N'), $workingDays, true) && !isset($holidayMap[$key])) $expectedDates[$key] = true;
    }

    $periodStart = sprintf('%04d-%02d-01', $year, $monthNumber);
    $periodEnd = (new DateTimeImmutable($periodStart, $timezone))->modify('last day of this month')->format('Y-m-d');
    $recordStmt = mysqli_prepare($connection, 'SELECT * FROM attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND ? ORDER BY attendance_date DESC');
    mysqli_stmt_bind_param($recordStmt, 'iss', $employeeId, $periodStart, $periodEnd);
    mysqli_stmt_execute($recordStmt);
    $recordResult = mysqli_stmt_get_result($recordStmt);
    $records = $recordResult ? mysqli_fetch_all($recordResult, MYSQLI_ASSOC) : [];
    $recordMap = [];
    $attendedExpected = $lateCount = $lateMinutes = 0;
    foreach ($records as $record) {
        $recordMap[$record['attendance_date']] = $record;
        if (isset($expectedDates[$record['attendance_date']])) {
            $attendedExpected++;
            if ((int) $record['late_minutes'] > 0) $lateCount++;
            $lateMinutes += (int) $record['late_minutes'];
        }
    }
    $absenceDays = max(0, count($expectedDates) - $attendedExpected);
    return [
        'source'=>'attendance', 'expected_days'=>count($expectedDates), 'attendance_days'=>$attendedExpected,
        'absence_days'=>$absenceDays, 'late_count'=>$lateCount, 'late_minutes'=>$lateMinutes,
        'expected_dates'=>$expectedDates, 'records'=>$records, 'record_map'=>$recordMap,
        'period_start'=>$periodStart, 'period_end'=>$periodEnd, 'completed_through'=>$completedThrough->format('Y-m-d'),
    ];
}

function attendanceHistoryRows(array $metrics): array
{
    $rows = $metrics['record_map'];
    foreach ($metrics['expected_dates'] as $date => $_) {
        if (!isset($rows[$date])) {
            $rows[$date] = ['attendance_date'=>$date,'check_in_at'=>null,'check_out_at'=>null,'late_minutes'=>0,'status'=>'absent','scheduled_start'=>null,'scheduled_end'=>null];
        }
    }
    krsort($rows);
    return array_values($rows);
}
