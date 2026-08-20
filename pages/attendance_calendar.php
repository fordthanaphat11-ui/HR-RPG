<?php
declare(strict_types=1);

$title = 'ปฏิทินการเข้างาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance_calendar.php';

if (!isset($_SESSION['username'])) { header('Location: /login'); exit; }

$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$jobLabels = ['executive'=>'เจ้าหน้าที่','manager'=>'ผู้จัดการ','director'=>'ผู้อำนวยการ','accountant'=>'นักบัญชี','chief'=>'หัวหน้าฝ่าย'];
$settings = getAttendanceSettings($connection);
$now = attendanceNow($settings);
$monthParam = trim((string) ($_GET['month'] ?? $now->format('Y-m')));
if (!preg_match('/^(20\d{2}|21\d{2}|2200)-(0[1-9]|1[0-2])$/', $monthParam)) $monthParam = $now->format('Y-m');
[$selectedYear, $selectedMonth] = array_map('intval', explode('-', $monthParam));
$selectedDepartment = ctype_digit((string) ($_GET['department_id'] ?? '')) ? (int) $_GET['department_id'] : 0;
$selectedPosition = trim((string) ($_GET['position'] ?? ''));
$selectedStatus = (string) ($_GET['status'] ?? 'all');
if (!in_array($selectedStatus, ['all','on_time','late','absent'], true)) $selectedStatus = 'all';

$departments = [];
$departmentResult = mysqli_query($connection, 'SELECT Depart_id,Depart_name FROM department ORDER BY Depart_name');
if ($departmentResult) while ($row = mysqli_fetch_assoc($departmentResult)) $departments[] = $row;
$jobs = [];
$jobResult = mysqli_query($connection, 'SELECT Job_Title FROM job ORDER BY Job_Title');
if ($jobResult) while ($row = mysqli_fetch_assoc($jobResult)) $jobs[] = (string) $row['Job_Title'];
if ($selectedDepartment > 0 && !in_array($selectedDepartment, array_map(static fn (array $row): int => (int) $row['Depart_id'], $departments), true)) $selectedDepartment = 0;
if ($selectedPosition !== '' && !in_array($selectedPosition, $jobs, true)) $selectedPosition = '';

$calendar = attendanceCalendarMonthData($connection, $selectedYear, $selectedMonth, $selectedDepartment, $selectedPosition, $selectedStatus);
$monthNames = array_values(attendanceMonthNames());
$monthLabel = $monthNames[$selectedMonth - 1] . ' ' . ($selectedYear + 543);
$monthStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $selectedMonth), attendanceTimezone($settings));
$previousMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');
$filterQuery = static function (string $month) use ($selectedDepartment, $selectedPosition, $selectedStatus): string {
    return http_build_query(array_filter([
        'month'=>$month, 'department_id'=>$selectedDepartment ?: null,
        'position'=>$selectedPosition ?: null, 'status'=>$selectedStatus !== 'all' ? $selectedStatus : null,
    ], static fn (mixed $value): bool => $value !== null && $value !== ''));
};
$selectedDate = trim((string) ($_GET['day'] ?? ''));
if (!isset($calendar['days'][$selectedDate])) {
    $selectedDate = isset($calendar['days'][$now->format('Y-m-d')]) ? $now->format('Y-m-d') : $calendar['month_start'];
}
$weekdayLabels = ['จ.','อ.','พ.','พฤ.','ศ.','ส.','อา.'];
$statusLabels = ['all'=>'สถานะทั้งหมด','on_time'=>'ตรงเวลา','late'=>'มาสาย','absent'=>'ขาดงาน'];

$avatar = static function (array $person, string $date, bool $showName = false) use ($escape): string {
    $label = $person['name'] . ' · ' . $person['status_label'];
    ob_start(); ?>
    <button type="button" data-attendance-person data-attendance-date="<?= $escape($date) ?>" data-employee-id="<?= (int) $person['id'] ?>" class="group inline-flex min-w-0 items-center gap-2 rounded-md text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0075de]" aria-label="<?= $escape($label) ?>">
        <span class="relative flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-[#31302e] ring-2 <?= $escape($person['position_ring']) ?> ring-offset-1"><span aria-hidden="true"><?= $escape($person['initials']) ?></span><span class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white <?= $escape($person['status_dot']) ?>"></span></span>
        <?php if ($showName): ?><span class="min-w-0 truncate text-[11px] font-medium"><?= $escape($person['name']) ?></span><?php endif; ?>
    </button>
    <?php return (string) ob_get_clean();
};

$calendarJsonDays = [];
foreach ($calendar['days'] as $date => $day) {
    $calendarJsonDays[$date] = [
        'date'=>$date, 'present_count'=>$day['present_count'], 'late_count'=>$day['late_count'],
        'absent_count'=>$day['absent_count'], 'checked_out_count'=>$day['checked_out_count'],
        'holiday'=>$day['holiday'], 'is_working_day'=>$day['is_working_day'], 'is_future'=>$day['is_future'],
        'people'=>$day['people'],
    ];
}
?>

<section id="attendance-calendar-workspace" data-attendance-calendar data-selected-date="<?= $escape($selectedDate) ?>" class="space-y-4">
    <header class="flex flex-col gap-3 border-b border-[#e6e6e6] pb-4 lg:flex-row lg:items-end lg:justify-between">
        <div><h1 class="text-lg font-semibold" tabindex="-1">ปฏิทินการเข้างาน</h1><p class="mt-1 text-sm text-[#6d7175]">ดูสถานะการเข้างานของพนักงานในแต่ละวัน</p></div>
        <form method="get" action="/attendance/calendar" hx-get="/attendance/calendar" hx-target="#attendance-calendar-workspace" hx-select="#attendance-calendar-workspace" hx-swap="outerHTML" hx-push-url="true" hx-trigger="change" class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
            <label class="sr-only" for="calendarMonth">เดือน</label><input id="calendarMonth" type="month" name="month" value="<?= $escape($monthParam) ?>" min="2000-01" max="2200-12" class="!h-9 !min-h-9 rounded-md border border-[#d5d3d0] bg-white px-2 text-sm">
            <label class="sr-only" for="calendarDepartment">แผนก</label><select id="calendarDepartment" name="department_id" class="!h-9 !min-h-9 rounded-md border border-[#d5d3d0] bg-white px-2 text-sm"><option value="">ทุกแผนก</option><?php foreach ($departments as $department): ?><option value="<?= (int) $department['Depart_id'] ?>" <?= $selectedDepartment === (int) $department['Depart_id'] ? 'selected' : '' ?>><?= $escape($department['Depart_name']) ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="calendarPosition">ตำแหน่ง</label><select id="calendarPosition" name="position" class="!h-9 !min-h-9 rounded-md border border-[#d5d3d0] bg-white px-2 text-sm"><option value="">ทุกตำแหน่ง</option><?php foreach ($jobs as $job): ?><option value="<?= $escape($job) ?>" <?= $selectedPosition === $job ? 'selected' : '' ?>><?= $escape($jobLabels[$job] ?? $job) ?></option><?php endforeach; ?></select>
            <label class="sr-only" for="calendarStatus">สถานะ</label><select id="calendarStatus" name="status" class="!h-9 !min-h-9 rounded-md border border-[#d5d3d0] bg-white px-2 text-sm"><?php foreach ($statusLabels as $value=>$label): ?><option value="<?= $value ?>" <?= $selectedStatus === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select>
        </form>
    </header>

    <section class="rounded-lg border border-[#dedede] bg-white" aria-labelledby="calendarMonthTitle">
        <div class="flex flex-col gap-3 border-b border-[#e6e6e6] px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-2"><a href="/attendance/calendar?<?= $escape($filterQuery($previousMonth)) ?>" hx-get="/attendance/calendar?<?= $escape($filterQuery($previousMonth)) ?>" hx-target="#attendance-calendar-workspace" hx-select="#attendance-calendar-workspace" hx-swap="outerHTML" hx-push-url="true" class="flex h-8 w-8 items-center justify-center rounded-md border border-[#d5d3d0]" aria-label="เดือนก่อน"><i class="fa-solid fa-chevron-left"></i></a><h2 id="calendarMonthTitle" class="min-w-36 text-center text-sm font-semibold"><?= $escape($monthLabel) ?></h2><a href="/attendance/calendar?<?= $escape($filterQuery($nextMonth)) ?>" hx-get="/attendance/calendar?<?= $escape($filterQuery($nextMonth)) ?>" hx-target="#attendance-calendar-workspace" hx-select="#attendance-calendar-workspace" hx-swap="outerHTML" hx-push-url="true" class="flex h-8 w-8 items-center justify-center rounded-md border border-[#d5d3d0]" aria-label="เดือนถัดไป"><i class="fa-solid fa-chevron-right"></i></a><a href="/attendance/calendar?<?= $escape($filterQuery($now->format('Y-m'))) ?>" hx-get="/attendance/calendar?<?= $escape($filterQuery($now->format('Y-m'))) ?>" hx-target="#attendance-calendar-workspace" hx-select="#attendance-calendar-workspace" hx-swap="outerHTML" hx-push-url="true" class="ml-1 inline-flex h-8 items-center rounded-md border border-[#d5d3d0] px-3 text-xs font-medium">วันนี้</a></div>
            <p class="text-xs text-[#6d7175]">มา <strong class="text-[#31302e]"><?= (int) $calendar['summary']['attendance_records'] ?></strong> รายการ <span class="mx-1">·</span> สาย <strong class="text-amber-700"><?= (int) $calendar['summary']['late_records'] ?></strong> ครั้ง <span class="mx-1">·</span> ขาด <strong class="text-red-700"><?= (int) $calendar['summary']['absence_records'] ?></strong> รายการ</p>
        </div>

        <div class="flex flex-col gap-2 border-b border-[#e6e6e6] px-4 py-3 text-xs lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4"><span class="font-medium">สถานะ</span><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-500"></span>ตรงเวลา</span><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-amber-500"></span>มาสาย</span><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-red-500"></span>ขาดงาน</span><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-gray-400"></span>ยังไม่ออกงาน</span></div>
            <div class="flex items-center gap-3 overflow-x-auto whitespace-nowrap pb-1 lg:pb-0"><span class="font-medium">ตำแหน่ง</span><?php foreach ($calendar['positions'] as $job=>$color): ?><span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full <?= $escape($color['dot']) ?>"></span><?= $escape($jobLabels[$job] ?? $job) ?></span><?php endforeach; ?></div>
        </div>

        <?php if ($calendar['all_employee_count'] === 0): ?><div class="p-8 text-center text-sm text-[#6d7175]"><i class="fa-solid fa-users-slash mb-3 block text-lg"></i>ยังไม่มีข้อมูลพนักงาน</div><?php else: ?>
            <?php if ($calendar['employee_count'] === 0): ?><div class="border-b border-[#e6e6e6] bg-amber-50 px-4 py-2.5 text-xs text-amber-800">ไม่พบพนักงานตามตัวกรองที่เลือก</div><?php endif; ?>
            <?php if ($calendar['employee_count'] > 0 && (int) $calendar['summary']['attendance_records'] === 0): ?><div class="border-b border-[#e6e6e6] bg-[#fafafa] px-4 py-2.5 text-xs text-[#6d7175]"><i class="fa-regular fa-calendar-xmark mr-1.5"></i>ยังไม่มีข้อมูลการเข้างานในเดือนนี้</div><?php endif; ?>
            <div class="hidden md:block">
                <div class="grid grid-cols-7 border-b border-[#e6e6e6] bg-[#fafafa] text-center text-[11px] font-medium text-[#6d7175]"><?php foreach ($weekdayLabels as $label): ?><div class="py-2"><?= $label ?></div><?php endforeach; ?></div>
                <div class="grid grid-cols-7 bg-[#e6e6e6] gap-px">
                    <?php for ($blank=1; $blank < (int) $monthStart->format('N'); $blank++): ?><div class="min-h-[128px] bg-[#fafafa]"></div><?php endfor; ?>
                    <?php foreach ($calendar['days'] as $date=>$day): $people=$day['visible_people']; $isWeekend=!$day['is_working_day'] && !$day['holiday']; ?>
                        <article class="min-h-[128px] min-w-0 bg-white p-2.5 <?= $isWeekend ? 'bg-[#fafafa] text-[#8a8580]' : '' ?> <?= $day['is_today'] ? 'ring-1 ring-inset ring-[#77716c]' : '' ?>" data-calendar-date-cell="<?= $escape($date) ?>">
                            <div class="flex items-center justify-between"><button type="button" data-open-attendance-day data-date="<?= $escape($date) ?>" class="flex h-6 min-w-6 items-center justify-center rounded text-xs font-semibold hover:bg-[#eeeeee]"><?= (int) $day['day'] ?></button><?php if ($day['is_today']): ?><span class="rounded bg-[#eeeeee] px-1.5 py-0.5 text-[10px]">วันนี้</span><?php endif; ?></div>
                            <?php if ($day['holiday']): ?><button type="button" data-open-attendance-day data-date="<?= $escape($date) ?>" class="mt-2 line-clamp-2 text-left text-[11px] font-medium text-violet-700"><i class="fa-solid fa-umbrella-beach mr-1"></i><?= $escape($day['holiday']) ?></button>
                            <?php elseif (!$day['is_working_day']): ?><p class="mt-2 text-[10px] text-[#a39e98]">วันหยุดประจำสัปดาห์</p>
                            <?php elseif ($day['is_future']): ?><p class="mt-2 text-[10px] text-[#a39e98]">ยังไม่ถึงวันทำงาน</p>
                            <?php else: ?><button type="button" data-open-attendance-day data-date="<?= $escape($date) ?>" class="mt-2 block text-left text-[10px] text-[#615d59]">มา <?= (int) $day['present_count'] ?> · สาย <?= (int) $day['late_count'] ?> · ขาด <?= (int) $day['absent_count'] ?></button><?php endif; ?>
                            <?php if ($people): ?><div class="mt-2.5"><?php if (count($people) <= 3): ?><div class="space-y-1.5"><?php foreach ($people as $person): ?><?= $avatar($person,$date,true) ?><?php endforeach; ?></div><?php else: ?><div class="flex items-center"><div class="flex -space-x-1.5"><?php foreach (array_slice($people,0,5) as $person): ?><?= $avatar($person,$date,false) ?><?php endforeach; ?></div><?php if (count($people)>5): ?><button type="button" data-open-attendance-day data-date="<?= $escape($date) ?>" class="ml-2 text-[11px] font-semibold text-[#0075de]">+<?= count($people)-5 ?></button><?php endif; ?></div><?php endif; ?></div><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php $used=((int)$monthStart->format('N')-1)+(int)$monthStart->format('t'); for($tail=$used%7;$tail>0&&$tail<7;$tail++): ?><div class="min-h-[128px] bg-[#fafafa]"></div><?php endfor; ?>
                </div>
            </div>

            <div class="md:hidden">
                <div class="grid grid-cols-7 border-b border-[#e6e6e6] bg-[#fafafa] text-center text-[10px] font-medium text-[#6d7175]"><?php foreach ($weekdayLabels as $label): ?><div class="py-2"><?= $label ?></div><?php endforeach; ?></div>
                <div class="grid grid-cols-7 gap-1 p-2"><?php for ($blank=1; $blank < (int) $monthStart->format('N'); $blank++): ?><span></span><?php endfor; ?><?php foreach ($calendar['days'] as $date=>$day): ?><button type="button" data-select-attendance-day data-date="<?= $escape($date) ?>" class="relative flex aspect-square min-w-0 flex-col items-center justify-center rounded-md text-xs <?= $date === $selectedDate ? 'bg-[#303030] text-white' : ($day['is_today'] ? 'bg-[#eeeeee] font-bold' : 'hover:bg-[#f6f5f4]') ?>" aria-label="วันที่ <?= (int)$day['day'] ?>"><span><?= (int)$day['day'] ?></span><span class="mt-1 flex h-1.5 gap-0.5"><?php if ($day['present_count']>0): ?><i class="h-1.5 w-1.5 rounded-full bg-emerald-500"></i><?php endif; ?><?php if ($day['late_count']>0): ?><i class="h-1.5 w-1.5 rounded-full bg-amber-500"></i><?php endif; ?><?php if ($day['absent_count']>0): ?><i class="h-1.5 w-1.5 rounded-full bg-red-500"></i><?php endif; ?></span></button><?php endforeach; ?></div>
                <div data-mobile-day-agenda class="border-t border-[#e6e6e6] p-4"></div>
            </div>
        <?php endif; ?>
    </section>

    <p class="text-xs text-[#6d7175]"><i class="fa-solid fa-circle-info mr-1.5"></i>สีขอบรูปแทนตำแหน่ง จุดมุมขวาล่างแทนสถานะการเข้างาน · ข้อมูลขาดงานเริ่มนับตั้งแต่ <?= $escape(date('d/m/Y',strtotime((string)$settings['tracking_start_date']))) ?></p>

    <script type="application/json" data-attendance-calendar-data><?= json_encode(['days'=>$calendarJsonDays,'selected_date'=>$selectedDate], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?></script>
</section>

<div id="attendancePersonTooltip" class="fixed z-[100] hidden w-[260px] rounded-lg border border-[#dedede] bg-white p-3 text-xs shadow-lg" role="tooltip" aria-hidden="true"></div>
<div id="attendanceDayModal" class="fixed inset-0 z-[90] hidden items-center justify-center p-3 sm:p-4" role="dialog" aria-modal="true" aria-labelledby="attendanceDayModalTitle" aria-hidden="true"><div class="absolute inset-0 bg-black/40" data-close-attendance-day></div><div class="relative flex max-h-[85vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg border border-[#dedede] bg-white shadow-2xl"><header class="flex items-start justify-between border-b border-[#e6e6e6] px-4 py-3 sm:px-5"><div><h2 id="attendanceDayModalTitle" class="text-base font-semibold">รายละเอียดการเข้างาน</h2><p data-attendance-day-label class="mt-0.5 text-xs text-[#6d7175]"></p></div><button type="button" data-close-attendance-day class="flex h-8 w-8 items-center justify-center rounded-md hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></header><div class="border-b border-[#e6e6e6] px-4 py-2.5 sm:px-5"><div data-attendance-day-summary class="text-xs text-[#6d7175]"></div><div class="mt-2 flex gap-1 overflow-x-auto"><button type="button" data-day-status-filter="all" class="rounded-md bg-[#303030] px-2.5 py-1.5 text-xs text-white">ทั้งหมด</button><button type="button" data-day-status-filter="on_time" class="rounded-md border border-[#d5d3d0] px-2.5 py-1.5 text-xs">ตรงเวลา</button><button type="button" data-day-status-filter="late" class="rounded-md border border-[#d5d3d0] px-2.5 py-1.5 text-xs">มาสาย</button><button type="button" data-day-status-filter="absent" class="rounded-md border border-[#d5d3d0] px-2.5 py-1.5 text-xs">ขาดงาน</button></div></div><div data-attendance-day-content class="min-h-0 flex-1 overflow-y-auto"></div></div></div>
