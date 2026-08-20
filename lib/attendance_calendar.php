<?php
declare(strict_types=1);

require_once __DIR__ . '/attendance.php';

function attendanceCalendarInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$parts) return 'พ';
    $initials = mb_substr($parts[0], 0, 1, 'UTF-8');
    if (count($parts) > 1) $initials .= mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
    return $initials;
}

function attendanceCalendarPositionMeta(string $position): array
{
    $palette = [
        ['ring'=>'ring-blue-500', 'dot'=>'bg-blue-500', 'badge'=>'bg-blue-50 text-blue-700'],
        ['ring'=>'ring-violet-500', 'dot'=>'bg-violet-500', 'badge'=>'bg-violet-50 text-violet-700'],
        ['ring'=>'ring-amber-500', 'dot'=>'bg-amber-500', 'badge'=>'bg-amber-50 text-amber-800'],
        ['ring'=>'ring-emerald-500', 'dot'=>'bg-emerald-500', 'badge'=>'bg-emerald-50 text-emerald-700'],
        ['ring'=>'ring-rose-500', 'dot'=>'bg-rose-500', 'badge'=>'bg-rose-50 text-rose-700'],
        ['ring'=>'ring-cyan-500', 'dot'=>'bg-cyan-500', 'badge'=>'bg-cyan-50 text-cyan-700'],
        ['ring'=>'ring-slate-500', 'dot'=>'bg-slate-500', 'badge'=>'bg-slate-100 text-slate-700'],
    ];
    $hash = (int) sprintf('%u', crc32(mb_strtolower(trim($position), 'UTF-8')));
    return $palette[$hash % count($palette)];
}

function attendanceCalendarPositionLabel(string $position): string
{
    return [
        'executive'=>'เจ้าหน้าที่', 'manager'=>'ผู้จัดการ', 'director'=>'ผู้อำนวยการ',
        'accountant'=>'นักบัญชี', 'chief'=>'หัวหน้าฝ่าย',
    ][$position] ?? $position;
}

function attendanceCalendarStatusMeta(string $status, bool $checkedOut = true, int $lateMinutes = 0): array
{
    if ($status === 'absent') return ['key'=>'absent', 'label'=>'ขาดงาน', 'dot'=>'bg-red-500', 'text'=>'text-red-700'];
    if (!$checkedOut) {
        if ($lateMinutes > 0 || $status === 'late') return ['key'=>'late', 'label'=>'มาสาย ' . max(0, $lateMinutes) . ' นาที · ยังไม่ออกงาน', 'dot'=>'bg-gray-400', 'text'=>'text-amber-700'];
        return ['key'=>'on_time', 'label'=>'ตรงเวลา · ยังไม่ออกงาน', 'dot'=>'bg-gray-400', 'text'=>'text-[#6d7175]'];
    }
    if ($lateMinutes > 0 || $status === 'late') return ['key'=>'late', 'label'=>'มาสาย ' . max(0, $lateMinutes) . ' นาที', 'dot'=>'bg-amber-500', 'text'=>'text-amber-700'];
    return ['key'=>'on_time', 'label'=>'ตรงเวลา', 'dot'=>'bg-emerald-500', 'text'=>'text-emerald-700'];
}

function attendanceCalendarEmployeeRow(array $employee, ?array $record, string $status): array
{
    $position = (string) ($employee['jobtitle'] ?? 'ไม่ระบุตำแหน่ง');
    $positionMeta = attendanceCalendarPositionMeta($position);
    $checkedOut = !empty($record['check_out_at']);
    $lateMinutes = (int) ($record['late_minutes'] ?? 0);
    $statusMeta = attendanceCalendarStatusMeta($status, $checkedOut, $lateMinutes);
    return [
        'id'=>(int) $employee['Employee_id'],
        'employee_code'=>(string) $employee['Employee_id'],
        'name'=>(string) $employee['Name'],
        'initials'=>attendanceCalendarInitials((string) $employee['Name']),
        'position'=>attendanceCalendarPositionLabel($position),
        'position_key'=>$position,
        'department'=>(string) ($employee['Depart_name'] ?: 'ไม่ระบุแผนก'),
        'department_id'=>(int) $employee['Depart_id'],
        'position_ring'=>$positionMeta['ring'],
        'position_dot'=>$positionMeta['dot'],
        'position_badge'=>$positionMeta['badge'],
        'status'=>$statusMeta['key'],
        'status_label'=>$statusMeta['label'],
        'status_dot'=>$statusMeta['dot'],
        'status_text'=>$statusMeta['text'],
        'check_in'=>$record && !empty($record['check_in_at']) ? date('H:i', strtotime((string) $record['check_in_at'])) : null,
        'check_out'=>$record && !empty($record['check_out_at']) ? date('H:i', strtotime((string) $record['check_out_at'])) : null,
        'late_minutes'=>$lateMinutes,
        'location'=>(string) ($record['check_in_geofence_name'] ?? $record['check_out_geofence_name'] ?? ''),
    ];
}

function attendanceCalendarMonthData(mysqli $connection, int $year, int $month, int $departmentId = 0, string $position = '', string $statusFilter = 'all', ?DateTimeImmutable $referenceNow = null): array
{
    if ($year < 2000 || $year > 2200 || $month < 1 || $month > 12) throw new InvalidArgumentException('เดือนที่เลือกไม่ถูกต้อง');
    if (!in_array($statusFilter, ['all', 'on_time', 'late', 'absent'], true)) $statusFilter = 'all';

    $settings = getAttendanceSettings($connection);
    $timezone = attendanceTimezone($settings);
    $monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month), $timezone);
    $monthEnd = $monthStart->modify('last day of this month');
    $now = $referenceNow ? $referenceNow->setTimezone($timezone) : attendanceNow($settings);
    $today = $now->format('Y-m-d');
    $workEndToday = new DateTimeImmutable($today . ' ' . (string) $settings['work_end'], $timezone);
    $completedThrough = $now >= $workEndToday ? $today : $now->modify('-1 day')->format('Y-m-d');
    $trackingStart = (string) ($settings['tracking_start_date'] ?: $today);
    $workingDays = attendanceWorkingDays($settings);

    $employeeResult = mysqli_query($connection, "SELECT e.Employee_id,e.Name,e.Start_date,e.jobtitle,e.Depart_id,d.Depart_name FROM employee e LEFT JOIN department d ON d.Depart_id=e.Depart_id ORDER BY e.Name,e.Employee_id");
    $allEmployees = $employeeResult ? mysqli_fetch_all($employeeResult, MYSQLI_ASSOC) : [];
    $employees = array_values(array_filter($allEmployees, static function (array $employee) use ($departmentId, $position): bool {
        if ($departmentId > 0 && (int) $employee['Depart_id'] !== $departmentId) return false;
        return $position === '' || (string) $employee['jobtitle'] === $position;
    }));
    $employeeMap = [];
    foreach ($employees as $employee) $employeeMap[(int) $employee['Employee_id']] = $employee;

    $from = $monthStart->format('Y-m-d');
    $to = $monthEnd->format('Y-m-d');
    $recordStmt = mysqli_prepare($connection, 'SELECT employee_id,attendance_date,check_in_at,check_out_at,late_minutes,status,check_in_geofence_name,check_out_geofence_name FROM attendance WHERE attendance_date BETWEEN ? AND ? ORDER BY attendance_date,check_in_at');
    mysqli_stmt_bind_param($recordStmt, 'ss', $from, $to);
    mysqli_stmt_execute($recordStmt);
    $recordResult = mysqli_stmt_get_result($recordStmt);
    $recordsByDate = [];
    while ($record = mysqli_fetch_assoc($recordResult)) {
        $employeeId = (int) $record['employee_id'];
        if (!isset($employeeMap[$employeeId])) continue;
        $recordsByDate[(string) $record['attendance_date']][$employeeId] = $record;
    }

    $holidayStmt = mysqli_prepare($connection, 'SELECT holiday_date,holiday_name FROM attendance_holidays WHERE holiday_date BETWEEN ? AND ?');
    mysqli_stmt_bind_param($holidayStmt, 'ss', $from, $to);
    mysqli_stmt_execute($holidayStmt);
    $holidayResult = mysqli_stmt_get_result($holidayStmt);
    $holidays = [];
    while ($holiday = mysqli_fetch_assoc($holidayResult)) $holidays[(string) $holiday['holiday_date']] = (string) $holiday['holiday_name'];

    $days = [];
    $summary = ['attendance_records'=>0, 'late_records'=>0, 'absence_records'=>0, 'checked_out_records'=>0];
    for ($date = $monthStart; $date <= $monthEnd; $date = $date->modify('+1 day')) {
        $dateKey = $date->format('Y-m-d');
        $isHoliday = isset($holidays[$dateKey]);
        $isWorkingDay = in_array((int) $date->format('N'), $workingDays, true) && !$isHoliday;
        $isFuture = $dateKey > $today;
        $isCompleted = $dateKey <= $completedThrough;
        $dayRecords = $recordsByDate[$dateKey] ?? [];
        $attendees = [];
        $absent = [];

        foreach ($dayRecords as $employeeId => $record) {
            $employee = $employeeMap[$employeeId];
            $recordStatus = (int) $record['late_minutes'] > 0 ? 'late' : 'on_time';
            $attendees[] = attendanceCalendarEmployeeRow($employee, $record, $recordStatus);
        }

        if ($isWorkingDay && $isCompleted && $dateKey >= $trackingStart) {
            foreach ($employees as $employee) {
                $employeeId = (int) $employee['Employee_id'];
                if ((string) $employee['Start_date'] > $dateKey || isset($dayRecords[$employeeId])) continue;
                $absent[] = attendanceCalendarEmployeeRow($employee, null, 'absent');
            }
        }

        $lateCount = count(array_filter($attendees, static fn (array $person): bool => $person['status'] === 'late'));
        $checkedOutCount = count(array_filter($attendees, static fn (array $person): bool => $person['check_out'] !== null));
        $visiblePeople = match ($statusFilter) {
            'on_time' => array_values(array_filter($attendees, static fn (array $person): bool => $person['status'] === 'on_time')),
            'late' => array_values(array_filter($attendees, static fn (array $person): bool => $person['status'] === 'late')),
            'absent' => $absent,
            default => $attendees,
        };
        $allPeople = array_merge($attendees, $absent);
        usort($allPeople, static function (array $a, array $b): int {
            $order = ['late'=>0, 'on_time'=>1, 'absent'=>2];
            return ($order[$a['status']] <=> $order[$b['status']]) ?: strcasecmp($a['name'], $b['name']);
        });
        $summary['attendance_records'] += count($attendees);
        $summary['late_records'] += $lateCount;
        $summary['absence_records'] += count($absent);
        $summary['checked_out_records'] += $checkedOutCount;

        $days[$dateKey] = [
            'date'=>$dateKey, 'day'=>(int) $date->format('j'), 'weekday'=>(int) $date->format('N'),
            'is_today'=>$dateKey === $today, 'is_future'=>$isFuture, 'is_working_day'=>$isWorkingDay,
            'is_completed'=>$isCompleted, 'holiday'=>$holidays[$dateKey] ?? null,
            'present_count'=>count($attendees), 'late_count'=>$lateCount, 'absent_count'=>count($absent),
            'checked_out_count'=>$checkedOutCount, 'attendees'=>$attendees, 'absent'=>$absent,
            'visible_people'=>$visiblePeople, 'people'=>$allPeople,
        ];
    }

    $positions = [];
    foreach ($employees as $employee) {
        $job = (string) $employee['jobtitle'];
        if (!isset($positions[$job])) $positions[$job] = attendanceCalendarPositionMeta($job);
    }
    ksort($positions, SORT_NATURAL | SORT_FLAG_CASE);

    return [
        'year'=>$year, 'month'=>$month, 'month_start'=>$from, 'month_end'=>$to, 'today'=>$today,
        'settings'=>$settings, 'days'=>$days, 'summary'=>$summary, 'positions'=>$positions,
        'employee_count'=>count($employees), 'all_employee_count'=>count($allEmployees),
        'status_filter'=>$statusFilter, 'department_id'=>$departmentId, 'position'=>$position,
    ];
}
