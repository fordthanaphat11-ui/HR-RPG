<?php
$title = 'เช็คชื่อเข้า–ออกงาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance.php';

if (!isset($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$settings = getAttendanceSettings($connection);
$locationSettings = geofenceGetSettings($connection);
$now = attendanceNow($settings);
$today = $now->format('Y-m-d');
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$jobLabels = ['executive'=>'เจ้าหน้าที่','manager'=>'ผู้จัดการ','director'=>'ผู้อำนวยการ','accountant'=>'นักบัญชี','chief'=>'หัวหน้าฝ่าย'];
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if (!$parts) return 'พ';
    return mb_substr($parts[0],0,1,'UTF-8') . (count($parts)>1 ? mb_substr($parts[count($parts)-1],0,1,'UTF-8') : '');
};
$thaiMonths = array_values(attendanceMonthNames());
$thaiWeekdays = [1=>'จันทร์',2=>'อังคาร',3=>'พุธ',4=>'พฤหัสบดี',5=>'ศุกร์',6=>'เสาร์',7=>'อาทิตย์'];
$todayLabel = 'วัน' . $thaiWeekdays[(int) $now->format('N')] . 'ที่ ' . $now->format('j') . ' ' . $thaiMonths[(int) $now->format('n') - 1] . ' ' . ((int) $now->format('Y') + 543);

$selectedId = trim((string) ($_POST['employee_id'] ?? $_GET['employee_id'] ?? $_GET['id'] ?? ''));
if ($selectedId !== '' && !ctype_digit($selectedId)) $selectedId = '';
$feedback = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!attendanceValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        if ($selectedId === '') throw new InvalidArgumentException('กรุณาเลือกพนักงาน');
        $result = attendanceProcessAction($connection, (int) $selectedId, (string) ($_POST['attendance_action'] ?? ''), (string) $_SESSION['username'], $settings, $_POST);
        $feedback = $result['message'];
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$employees = attendanceEmployeeList($connection, $today);
$departments = [];
$departmentResult = mysqli_query($connection, 'SELECT Depart_id, Depart_name FROM department ORDER BY Depart_name');
if ($departmentResult) while ($department = mysqli_fetch_assoc($departmentResult)) $departments[] = $department;
$selectedEmployee = null;
foreach ($employees as $employee) if ((string) $employee['Employee_id'] === $selectedId) { $selectedEmployee = $employee; break; }
$todayRecord = $selectedEmployee ? attendanceFindToday($connection, (int) $selectedEmployee['Employee_id'], $today) : null;
$checkInWindow = attendanceCheckInWindow($settings, $now);
$isCheckInClosed = $selectedEmployee && !$todayRecord && !$checkInWindow['can_check_in'];
$attendanceAction = $todayRecord ? 'check_out' : 'check_in';
$locationRequired = $selectedEmployee ? geofenceRequiresAction($locationSettings, $attendanceAction) : false;
$eligibleGeofenceCount = $selectedEmployee && $locationRequired ? count(geofenceForEmployee($connection, (int)$selectedEmployee['Employee_id'], true)) : 0;
$locationUnavailable = $locationRequired && $eligibleGeofenceCount === 0;

$activeEmployees = array_values(array_filter($employees, static fn (array $employee): bool => (string) $employee['Start_date'] <= $today));
$presentCount = count(array_filter($activeEmployees, static fn (array $employee): bool => !empty($employee['attendance_check_in'])));
$lateCount = count(array_filter($activeEmployees, static fn (array $employee): bool => (int) ($employee['attendance_late_minutes'] ?? 0) > 0));
$notInCount = max(0, count($activeEmployees) - $presentCount);
?>

<div class="w-full space-y-4" data-attendance-page>
    <header class="pb-3 border-b border-[#dedede]">
        <h1 class="text-lg font-semibold text-[#202223]">เช็คชื่อเข้า–ออกงาน</h1>
        <p class="mt-0.5 text-sm text-[#6d7175]">บันทึกเวลาเข้างานและออกงานของพนักงานด้วยเวลาจากเซิร์ฟเวอร์</p>
    </header>

    <section id="attendance-workspace" class="space-y-4">
        <?php if ($feedback): ?><div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i><?= $escape($feedback) ?></div><?php endif; ?>
        <?php if ($error): ?><div role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= $escape($error) ?></div><?php endif; ?>

        <section aria-labelledby="attendanceEmployeeTitle">
            <div class="mb-2 flex items-center justify-between"><h2 id="attendanceEmployeeTitle" class="text-sm font-bold">พนักงาน</h2><span class="text-xs text-[#6d7175]">เลือกได้ครั้งละ 1 คน</span></div>
            <div class="rounded-lg border border-[#dedede] bg-white p-3 sm:p-4">
                <?php if (!$selectedEmployee): ?>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex items-center gap-3"><span class="flex h-10 w-10 items-center justify-center rounded-full bg-[#f2f2f2] text-[#8a8580]"><i class="fa-solid fa-user-clock"></i></span><div><p class="text-sm font-bold">ยังไม่ได้เลือกพนักงาน</p><p class="text-xs text-[#6d7175]">ค้นหาด้วยชื่อ รหัส แผนก หรือตำแหน่ง</p></div></div><button type="button" data-open-employee-picker class="min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white"><i class="fa-solid fa-users mr-2"></i>เลือกพนักงาน</button></div>
                <?php else: ?>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div class="flex min-w-0 items-center gap-3"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#eef6fd] text-sm font-bold text-[#0075de]"><?= $escape($initials($selectedEmployee['Name'])) ?></span><div class="min-w-0"><p class="truncate text-sm font-bold"><?= $escape($selectedEmployee['Name']) ?></p><p class="text-xs text-[#6d7175]"><?= $escape($selectedEmployee['Employee_id']) ?> · <?= $escape($selectedEmployee['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · <?= $escape($jobLabels[$selectedEmployee['jobtitle']] ?? $selectedEmployee['jobtitle']) ?></p></div></div><button type="button" data-open-employee-picker class="min-h-9 rounded-md border border-[#d5d3d0] bg-white px-3 text-sm font-medium hover:bg-[#f6f5f4]"><i class="fa-solid fa-repeat mr-2"></i>เปลี่ยนพนักงาน</button></div>
                <?php endif; ?>
            </div>
        </section>

        <?php if (!$selectedEmployee): ?>
            <section class="flex min-h-[260px] flex-col items-center justify-center rounded-lg border border-dashed border-[#d5d3d0] bg-white px-5 py-10 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#f2f2f2] text-[#8a8580]"><i class="fa-solid fa-fingerprint"></i></span><h2 class="mt-4 text-base font-bold">เลือกพนักงานเพื่อเช็คชื่อเข้า–ออกงาน</h2><p class="mt-1 text-sm text-[#6d7175]">ระบบจะใช้เวลาปัจจุบันจากเซิร์ฟเวอร์เป็นเวลาบันทึกจริง</p><button type="button" data-open-employee-picker class="mt-5 min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white">เลือกพนักงาน</button></section>
        <?php else: ?>
            <?php $statusMeta = attendanceStatusMeta($todayRecord); ?>
            <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white" aria-labelledby="todayAttendanceTitle">
                <header class="flex flex-col gap-2 border-b border-[#e6e6e6] bg-[#fafafa] px-4 py-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 id="todayAttendanceTitle" class="text-sm font-bold">วันนี้ · <?= $escape($todayLabel) ?></h2><p class="mt-0.5 text-xs text-[#6d7175]">กำหนดเวลา <?= $escape(substr((string) $settings['work_start'],0,5)) ?>–<?= $escape(substr((string) $settings['work_end'],0,5)) ?> · ผ่อนผัน <?= (int) $settings['grace_minutes'] ?> นาที</p></div><div class="text-xs text-[#6d7175]">เวลาปัจจุบัน <span data-attendance-clock data-timezone="<?= $escape($settings['timezone']) ?>" class="font-bold text-[#202223]"><?= $escape($now->format('H:i:s')) ?></span></div></header>
                <div class="p-4" data-attendance-location-scope>
                    <div class="grid grid-cols-1 divide-y divide-[#e6e6e6] sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                        <div class="pb-3 sm:px-4 sm:pb-0 sm:first:pl-0"><p class="text-xs text-[#6d7175]">เวลาเข้างาน</p><p class="mt-1 text-lg font-bold"><?= $todayRecord ? $escape(date('H:i', strtotime($todayRecord['check_in_at']))) : '--' ?></p></div>
                        <div class="py-3 sm:px-4 sm:py-0"><p class="text-xs text-[#6d7175]">เวลาออกงาน</p><p class="mt-1 text-lg font-bold"><?= !empty($todayRecord['check_out_at']) ? $escape(date('H:i', strtotime($todayRecord['check_out_at']))) : '--' ?></p></div>
                        <div class="pt-3 sm:px-4 sm:pt-0"><p class="text-xs text-[#6d7175]">ระยะเวลาทำงาน</p><p class="mt-1 text-base font-bold"><?= $escape(attendanceFormatDuration($todayRecord['check_in_at'] ?? null, $todayRecord['check_out_at'] ?? null)) ?></p></div>
                    </div>
                    <div class="mt-4 rounded-md border border-[#e6e6e6] bg-[#fafafa] p-3"><div class="flex items-start gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white text-[#0075de]"><i class="fa-solid fa-location-dot"></i></span><div class="min-w-0"><p class="text-xs font-bold">สถานที่เช็คชื่อ</p><p data-location-client-status class="mt-0.5 text-xs <?= $locationUnavailable ? 'text-red-700' : 'text-[#615d59]' ?>"><?php if ($locationUnavailable): ?>ยังไม่มีพื้นที่ที่อนุญาตสำหรับพนักงานคนนี้ กรุณาตั้งค่าพื้นที่ก่อน<?php elseif ($locationRequired): ?><?= $todayRecord && $todayRecord['check_in_geofence_name'] ? $escape('เช็คอินที่ '.$todayRecord['check_in_geofence_name'].' · ตรวจตำแหน่งอีกครั้งเมื่อเช็คเอาต์') : 'กำลังรอตรวจสอบตำแหน่ง · ระบบจะขอ GPS เมื่อกดเช็คชื่อ' ?><?php else: ?>ไม่ได้บังคับตรวจตำแหน่งสำหรับการทำรายการนี้<?php endif; ?></p><?php if ($todayRecord && $todayRecord['check_out_geofence_name']): ?><p class="mt-1 text-xs font-medium text-emerald-700">เช็คเอาต์ที่ <?= $escape($todayRecord['check_out_geofence_name']) ?></p><?php endif; ?></div></div></div>
                    <div class="mt-4 flex flex-col gap-3 border-t border-[#e6e6e6] pt-4 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs text-[#6d7175]">สถานะวันนี้</p><div class="mt-1.5 flex flex-wrap items-center gap-2"><?php if ($isCheckInClosed): ?><span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700"><i class="fa-solid fa-clock"></i>หมดเวลาเช็คอิน</span><span class="text-xs text-red-700">เลยเวลาเลิกงาน <?= $escape($checkInWindow['work_end']->format('H:i')) ?> แล้ว</span><?php else: ?><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold <?= $statusMeta['class'] ?>"><i class="fa-solid <?= $statusMeta['icon'] ?>"></i><?= $escape($statusMeta['label']) ?></span><?php endif; ?><?php if ($todayRecord && (int)$todayRecord['late_minutes'] > 0): ?><span class="text-xs text-amber-700">มาสาย <?= (int)$todayRecord['late_minutes'] ?> นาที</span><?php endif; ?><?php if (!empty($todayRecord['check_out_at'])): ?><span class="text-xs font-semibold text-[#6d7175]">ออกงานแล้ว</span><?php elseif ($todayRecord): ?><span class="text-xs text-[#6d7175]">กำลังทำงาน</span><?php endif; ?></div></div>
                        <?php if ($isCheckInClosed): ?><div class="sm:text-right"><button type="button" disabled class="min-h-9 w-full cursor-not-allowed rounded-md bg-[#e6e6e6] px-4 text-sm font-medium text-[#8a8580] sm:w-auto"><i class="fa-solid fa-lock mr-2"></i>หมดเวลาเช็คอิน</button><p class="mt-1 text-xs text-[#6d7175]">เวลาเข้างาน <?= $escape($checkInWindow['work_start']->format('H:i')) ?> · ออกงาน <?= $escape($checkInWindow['work_end']->format('H:i')) ?></p></div><?php elseif ($locationUnavailable): ?><div class="sm:text-right"><button type="button" disabled class="min-h-9 w-full cursor-not-allowed rounded-md bg-[#e6e6e6] px-4 text-sm font-medium text-[#8a8580] sm:w-auto"><i class="fa-solid fa-location-dot mr-2"></i>ยังไม่มีพื้นที่เช็คชื่อ</button><p class="mt-1 text-xs text-red-700">กรุณาติดต่อผู้ดูแลระบบ</p></div><?php elseif (!$todayRecord || empty($todayRecord['check_out_at'])): ?><form method="post" action="/attendance?employee_id=<?= rawurlencode((string)$selectedEmployee['Employee_id']) ?>" hx-post="/attendance?employee_id=<?= rawurlencode((string)$selectedEmployee['Employee_id']) ?>" hx-target="#attendance-workspace" hx-select="#attendance-workspace" hx-swap="outerHTML" data-attendance-location-form data-location-required="<?= $locationRequired ? '1' : '0' ?>" class="sm:w-auto"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="employee_id" value="<?= $escape($selectedEmployee['Employee_id']) ?>"><input type="hidden" name="attendance_action" value="<?= $escape($attendanceAction) ?>"><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy"><button type="submit" data-loading-text="กำลังบันทึก..." class="min-h-9 w-full rounded-md bg-[#303030] px-4 text-sm font-medium text-white hover:bg-black sm:w-auto"><i class="fa-solid <?= $todayRecord ? 'fa-right-from-bracket' : 'fa-right-to-bracket' ?> mr-2"></i><?= $todayRecord ? 'เช็คชื่อออกงาน' : 'เช็คชื่อเข้างาน' ?></button></form><?php else: ?><span class="inline-flex min-h-9 items-center rounded-md border border-[#dedede] bg-[#f6f5f4] px-3 text-sm font-medium text-[#6d7175]"><i class="fa-solid fa-lock mr-2"></i>บันทึกครบแล้ว</span><?php endif; ?>
                    </div>
                </div>
            </section>
        <?php endif; ?>

        <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white" aria-labelledby="todayOverviewTitle">
            <header class="border-b border-[#e6e6e6] p-4"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><h2 id="todayOverviewTitle" class="text-sm font-bold">ภาพรวมการเข้างานวันนี้</h2><p class="mt-0.5 text-xs text-[#6d7175]">พนักงานที่เริ่มงานแล้ว <?= count($activeEmployees) ?> คน</p></div><div class="flex flex-wrap gap-x-5 gap-y-2 text-xs"><span>มาทำงานแล้ว <b class="ml-1 text-emerald-700"><?= $presentCount ?></b></span><span>มาสาย <b class="ml-1 text-amber-700"><?= $lateCount ?></b></span><span>ยังไม่เข้างาน <b class="ml-1"><?= $notInCount ?></b></span></div></div></header>
            <section class="js-mobile-list-section md:hidden px-4"><div class="relative my-3"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#a39e98]"></i><input type="search" class="js-mobile-search min-h-10 w-full rounded-md border border-[#dedede] pl-10 pr-3 text-sm" placeholder="ค้นหาชื่อ รหัส หรือแผนก..."></div><div class="js-mobile-records divide-y divide-[#e6e6e6]"><?php foreach ($activeEmployees as $index=>$employee): ?><?php $mobileRecord = $employee['attendance_check_in'] ? ['late_minutes'=>$employee['attendance_late_minutes']] : null; $mobileMeta=attendanceStatusMeta($mobileRecord); ?><article class="js-mobile-record py-3" data-original="<?= $index ?>" data-search="<?= $escape(mb_strtolower($employee['Employee_id'].' '.$employee['Name'].' '.$employee['Depart_name'],'UTF-8')) ?>"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate text-sm font-bold"><?= $escape($employee['Name']) ?></p><p class="text-xs text-[#6d7175]"><?= $escape($employee['Employee_id']) ?> · <?= $escape($employee['Depart_name'] ?: 'ไม่ระบุแผนก') ?></p></div><div class="text-right"><p class="text-sm font-bold"><?= $employee['attendance_check_in'] ? $escape(date('H:i',strtotime($employee['attendance_check_in']))) : '--' ?></p><span class="text-xs <?= $employee['attendance_check_in'] ? ((int)$employee['attendance_late_minutes']>0?'text-amber-700':'text-emerald-700') : 'text-[#6d7175]' ?>"><?= $escape($mobileMeta['label']) ?></span></div></div></article><?php endforeach; ?></div><div class="js-mobile-empty-filter hidden py-8 text-center text-sm text-[#6d7175]">ไม่พบพนักงาน</div></section>
            <div class="hidden md:block"><table class="js-data-table w-full text-left text-sm" data-export-name="การเข้างานวันนี้"><thead class="border-b border-[#e6e6e6] bg-[#fafafa]"><tr><th class="px-4 py-3 text-xs font-semibold text-[#6d7175]">พนักงาน</th><th class="px-4 py-3 text-xs font-semibold text-[#6d7175]">แผนก</th><th class="px-4 py-3 text-xs font-semibold text-[#6d7175]">เวลาเข้า</th><th class="px-4 py-3 text-xs font-semibold text-[#6d7175]">เวลาออก</th><th class="px-4 py-3 text-xs font-semibold text-[#6d7175]">สถานะ</th></tr></thead><tbody class="divide-y divide-[#e6e6e6]"><?php foreach ($activeEmployees as $employee): ?><?php $tableRecord=$employee['attendance_check_in']?['late_minutes'=>$employee['attendance_late_minutes']]:null;$tableMeta=attendanceStatusMeta($tableRecord); ?><tr><td class="px-4 py-3"><p class="font-semibold"><?= $escape($employee['Name']) ?></p><p class="text-xs text-[#6d7175]"><?= $escape($employee['Employee_id']) ?></p></td><td class="px-4 py-3"><?= $escape($employee['Depart_name'] ?: 'ไม่ระบุแผนก') ?></td><td class="px-4 py-3"><?= $employee['attendance_check_in'] ? $escape(date('H:i',strtotime($employee['attendance_check_in']))) : '--' ?></td><td class="px-4 py-3"><?= $employee['attendance_check_out'] ? $escape(date('H:i',strtotime($employee['attendance_check_out']))) : '--' ?></td><td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold <?= $tableMeta['class'] ?>"><?= $escape($tableMeta['label']) ?></span></td></tr><?php endforeach; ?></tbody></table></div>
        </section>

        <?php $employeePickerEmployees=$employees;$employeePickerDepartments=$departments;$employeePickerSelectedId=$selectedEmployee?(string)$selectedEmployee['Employee_id']:'';$employeePickerJobLabels=$jobLabels;$employeePickerContext='attendance';require __DIR__.'/components/employee_picker.php'; ?>
    </section>
</div>
