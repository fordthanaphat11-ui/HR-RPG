<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance_calendar.php';

function assertCalendar(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$settings = getAttendanceSettings($connection);
$now = attendanceNow($settings);
$calendar = attendanceCalendarMonthData($connection, (int) $now->format('Y'), (int) $now->format('n'));
assertCalendar(count($calendar['days']) === (int) $now->format('t'), 'Calendar must contain every day in the month');
assertCalendar(attendanceCalendarPositionMeta('manager') === attendanceCalendarPositionMeta('manager'), 'Position color must be stable');

foreach ($calendar['days'] as $day) {
    assertCalendar(count($day['people']) === $day['present_count'] + $day['absent_count'], 'Daily people list must match totals');
    if ($day['is_future'] || !$day['is_working_day']) {
        assertCalendar($day['absent_count'] === 0, 'Future and non-working dates must never create absences');
    }
    foreach ($day['attendees'] as $person) {
        assertCalendar(in_array($person['status'], ['on_time','late'], true), 'Attendance record status must be present or late');
    }
}

$monthEndSimulation = attendanceCalendarMonthData(
    $connection,
    (int) $now->format('Y'),
    (int) $now->format('n'),
    0,
    '',
    'all',
    new DateTimeImmutable($now->format('Y-m-t') . ' 23:00:00', attendanceTimezone($settings))
);
$trackingStart = (string) $settings['tracking_start_date'];
foreach ($monthEndSimulation['days'] as $date => $day) {
    if ($date >= $trackingStart && $day['is_working_day'] && $day['is_completed']) {
        assertCalendar($day['absent_count'] + $day['present_count'] === $monthEndSimulation['employee_count'], 'Completed workday must account for every active employee');
    }
}

$departmentResult = mysqli_query($connection, 'SELECT Depart_id FROM department ORDER BY Depart_id LIMIT 1');
$department = $departmentResult ? mysqli_fetch_assoc($departmentResult) : null;
if ($department) {
    $departmentId = (int) $department['Depart_id'];
    $filtered = attendanceCalendarMonthData($connection, (int) $now->format('Y'), (int) $now->format('n'), $departmentId);
    foreach ($filtered['days'] as $day) {
        foreach ($day['people'] as $person) assertCalendar((int) $person['department_id'] === $departmentId, 'Department filter leaked another department');
    }
}

echo "Attendance calendar tests passed.\n";
