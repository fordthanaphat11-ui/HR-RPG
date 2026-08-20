<?php
declare(strict_types=1);
$title = 'เช็คชื่อของฉัน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance.php';

$employeeId = (int) authEmployeeId();
$settings = getAttendanceSettings($connection);
$locationSettings = geofenceGetSettings($connection);
$now = attendanceNow($settings);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง';
    } else {
        try {
            $result = attendanceProcessAction($connection, $employeeId, (string) ($_POST['attendance_action'] ?? ''), (string) $_SESSION['username'], $settings, $_POST);
            $message = (string) $result['message'];
            appToast('success', $message);
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }
    $now = attendanceNow($settings);
}
if ($error !== '') appRequestToast('error', $error);

$employeeStmt = mysqli_prepare($connection, 'SELECT Employee_id, Name FROM employee WHERE Employee_id=? LIMIT 1');
mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId); mysqli_stmt_execute($employeeStmt);
$employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
$today = attendanceFindToday($connection, $employeeId, $now->format('Y-m-d'));
$status = attendanceStatusMeta($today);
$metrics = attendancePeriodMetrics($connection, $employeeId, (int) $now->format('Y'), strtolower($now->format('F')));
$recentRows = array_slice(attendanceHistoryRows($metrics), 0, 7);
$checkInWindow = attendanceCheckInWindow($settings, $now);
$isCheckInClosed = !$today && !$checkInWindow['can_check_in'];
$canCheckIn = !$today && $checkInWindow['can_check_in'];
$canCheckOut = $today && empty($today['check_out_at']);
$attendanceAction = $today ? 'check_out' : 'check_in';
$locationRequired = geofenceRequiresAction($locationSettings, $attendanceAction);
$eligibleGeofenceCount = $locationRequired ? count(geofenceForEmployee($connection, $employeeId, true)) : 0;
$locationUnavailable = $locationRequired && $eligibleGeofenceCount === 0;
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="mx-auto w-full max-w-5xl space-y-4">
    <header class="border-b border-[#dedede] pb-3"><h1 class="text-xl font-semibold text-[#202223]">เช็คชื่อของฉัน</h1><p class="mt-0.5 text-sm text-[#6d7175]">บันทึกเวลาเข้าและออกงานด้วยบัญชีของคุณเท่านั้น</p></header>
    <section id="employee-attendance-workspace" class="space-y-4">
    <?php if ($error): ?><div class="rounded-[8px] border border-red-200 bg-red-50 p-3 text-[14px] text-red-800"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= $escape($error) ?></div><?php endif; ?>

    <section class="grid gap-4 lg:grid-cols-[1.1fr_.9fr]" data-attendance-location-scope>
        <article class="rounded-lg border border-[#dedede] bg-white p-5 sm:p-6">
            <div class="flex items-start justify-between gap-3"><div><p class="text-[13px] text-[#615d59]">วันนี้ <?= $escape($now->format('d/m/Y')) ?></p><h2 class="mt-1 text-[19px] font-bold"><?= $escape($employee['Name'] ?? authDisplayName()) ?></h2></div><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i><?= $status['label'] ?></span></div>
            <div class="my-6 text-center"><p class="text-[13px] text-[#615d59]">เวลาปัจจุบัน</p><p class="mt-1 text-[40px] font-bold leading-none tracking-tight"><?= $escape($now->format('H:i')) ?></p><p class="mt-2 text-[12px] text-[#8a8580]">เวลาเข้างาน <?= $escape(substr((string) $settings['work_start'], 0, 5)) ?> · ออกงาน <?= $escape(substr((string) $settings['work_end'], 0, 5)) ?></p></div>
            <form method="post" action="/me/attendance" hx-post="/me/attendance" hx-target="#employee-attendance-workspace" hx-select="#employee-attendance-workspace" hx-swap="outerHTML" data-attendance-location-form data-location-required="<?= $locationRequired ? '1' : '0' ?>"><input type="hidden" name="csrf_token" value="<?= $escape(authCsrfToken()) ?>"><input type="hidden" name="latitude"><input type="hidden" name="longitude"><input type="hidden" name="accuracy"><?php if ($locationUnavailable && ($canCheckIn || $canCheckOut)): ?><button type="button" disabled class="min-h-12 w-full cursor-not-allowed rounded-[8px] bg-[#e6e6e6] px-4 font-bold text-[#8a8580]"><i class="fa-solid fa-location-dot mr-2"></i>ยังไม่มีพื้นที่เช็คชื่อ</button><p class="mt-2 text-center text-[12px] text-red-700">กรุณาติดต่อผู้ดูแลระบบ</p><?php elseif ($canCheckIn): ?><button type="submit" name="attendance_action" value="check_in" class="min-h-12 w-full rounded-[8px] bg-[#0075de] px-4 font-bold text-white hover:bg-[#005bab]"><i class="fa-solid fa-right-to-bracket mr-2"></i>เช็คชื่อเข้างาน</button><?php elseif ($canCheckOut): ?><button type="submit" name="attendance_action" value="check_out" class="min-h-12 w-full rounded-[8px] bg-[#202223] px-4 font-bold text-white hover:bg-black"><i class="fa-solid fa-right-from-bracket mr-2"></i>เช็คชื่อออกงาน</button><?php elseif ($isCheckInClosed): ?><button type="button" disabled class="min-h-12 w-full cursor-not-allowed rounded-[8px] bg-[#e6e6e6] px-4 font-bold text-[#8a8580]"><i class="fa-solid fa-lock mr-2"></i>หมดเวลาเช็คอิน</button><p class="mt-2 text-center text-[12px] text-red-700">เลยเวลาเช็คอินสำหรับวันนี้แล้ว</p><?php else: ?><button type="button" disabled class="min-h-12 w-full cursor-not-allowed rounded-[8px] bg-[#e6e6e6] px-4 font-bold text-[#8a8580]"><i class="fa-solid fa-circle-check mr-2"></i>บันทึกเวลาครบแล้ว</button><?php endif; ?></form>
        </article>

        <article class="rounded-lg border border-[#dedede] bg-white p-5"><h2 class="font-bold">เวลาของวันนี้</h2><div class="mt-4 grid grid-cols-2 overflow-hidden rounded-[8px] border border-[#e6e6e6]"><div class="p-4"><p class="text-[12px] text-[#615d59]">เข้า</p><p class="mt-1 text-[24px] font-bold"><?= $today ? date('H:i', strtotime($today['check_in_at'])) : '--:--' ?></p></div><div class="border-l border-[#e6e6e6] p-4"><p class="text-[12px] text-[#615d59]">ออก</p><p class="mt-1 text-[24px] font-bold"><?= !empty($today['check_out_at']) ? date('H:i', strtotime($today['check_out_at'])) : '--:--' ?></p></div></div><dl class="mt-4 space-y-2 text-[13px]"><div class="flex justify-between gap-3"><dt class="text-[#615d59]">ระยะเวลาทำงาน</dt><dd class="font-semibold"><?= $today ? $escape(attendanceFormatDuration($today['check_in_at'], $today['check_out_at'])) : '--' ?></dd></div><div class="flex justify-between gap-3"><dt class="text-[#615d59]">มาสาย</dt><dd class="font-semibold <?= (int)($today['late_minutes'] ?? 0) > 0 ? 'text-amber-700' : '' ?>"><?= (int)($today['late_minutes'] ?? 0) ?> นาที</dd></div></dl><div class="mt-4 rounded-[8px] border border-[#e6e6e6] bg-[#fafafa] p-3 text-[12px]"><p class="font-bold text-[#202223]"><i class="fa-solid fa-location-dot mr-1.5 text-[#0075de]"></i>สถานที่เช็คชื่อ</p><p data-location-client-status class="mt-1 <?= $locationUnavailable ? 'text-red-700' : 'text-[#615d59]' ?>"><?php if($locationUnavailable): ?>ยังไม่มีพื้นที่ที่อนุญาต กรุณาติดต่อผู้ดูแลระบบ<?php elseif($locationRequired): ?><?= $today && $today['check_in_geofence_name'] ? $escape('เช็คอินที่ '.$today['check_in_geofence_name'].' · ตรวจตำแหน่งอีกครั้งเมื่อเช็คเอาต์') : 'กำลังรอตรวจสอบตำแหน่ง · ระบบจะขอ GPS เมื่อกดเช็คชื่อ' ?><?php else: ?>ไม่ได้บังคับตรวจตำแหน่งสำหรับการทำรายการนี้<?php endif; ?></p><?php if($today && $today['check_out_geofence_name']): ?><p class="mt-1 font-medium text-emerald-700">เช็คเอาต์ที่ <?= $escape($today['check_out_geofence_name']) ?></p><?php endif; ?></div><div class="mt-3 rounded-[8px] <?= $isCheckInClosed ? 'bg-red-50 text-red-700' : 'bg-[#f6f5f4] text-[#615d59]' ?> p-3 text-[12px]"><i class="fa-solid <?= $isCheckInClosed ? 'fa-clock' : 'fa-circle-info' ?> mr-1.5"></i><?php if ($isCheckInClosed): ?>ไม่สามารถเช็คอินได้ · เลยเวลาเลิกงาน <?= $escape($checkInWindow['work_end']->format('H:i')) ?> แล้ว<?php else: ?>ระบบใช้เวลา <?= $escape($settings['timezone']) ?> และไม่อนุญาตให้แก้ไขเวลาย้อนหลัง<?php endif; ?></div></article>
    </section>

    <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white"><header class="flex items-center justify-between border-b border-[#e6e6e6] p-4"><div><h2 class="font-bold">รายการล่าสุดเดือนนี้</h2><p class="text-[13px] text-[#615d59]">รวมวันขาดงานตามวันทำงานที่ผ่านมา</p></div><div class="text-right"><p class="text-[12px] text-[#615d59]">มาทำงาน</p><p class="font-bold"><?= (int)$metrics['attendance_days'] ?> วัน</p></div></header><?php if ($recentRows): ?><div class="divide-y divide-[#e6e6e6]"><?php foreach ($recentRows as $row): $rowStatus=attendanceStatusMeta($row); ?><div class="grid grid-cols-[1fr_auto] gap-3 p-4 sm:grid-cols-[1fr_100px_100px_auto]"><div><p class="font-semibold"><?= $escape(date('d/m/Y', strtotime($row['attendance_date']))) ?></p><span class="mt-1 inline-flex rounded-full px-2 py-0.5 text-[11px] <?= $rowStatus['class'] ?>"><?= $rowStatus['label'] ?></span></div><div class="hidden sm:block"><p class="text-[11px] text-[#615d59]">เข้า</p><p class="font-semibold"><?= !empty($row['check_in_at']) ? date('H:i', strtotime($row['check_in_at'])) : '--:--' ?></p></div><div class="hidden sm:block"><p class="text-[11px] text-[#615d59]">ออก</p><p class="font-semibold"><?= !empty($row['check_out_at']) ? date('H:i', strtotime($row['check_out_at'])) : '--:--' ?></p></div><p class="text-right text-[13px] text-[#615d59]"><?= (int)($row['late_minutes'] ?? 0) > 0 ? 'สาย '.(int)$row['late_minutes'].' นาที' : '' ?></p></div><?php endforeach; ?></div><?php else: ?><div class="p-8 text-center text-[14px] text-[#615d59]">ยังไม่มีรายการในเดือนนี้</div><?php endif; ?></section>
    </section>
</div>
