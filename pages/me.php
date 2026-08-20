<?php
declare(strict_types=1);
$title = 'ภาพรวมของฉัน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance.php';

$employeeId = authEmployeeId();
$employeeStmt = mysqli_prepare($connection, "SELECT e.*, d.Depart_name,
                                              (SELECT es.salary_amount FROM employee_salaries es WHERE es.employee_id=e.Employee_id AND es.effective_from<=CURRENT_DATE ORDER BY es.effective_from DESC,es.id DESC LIMIT 1) AS basic_salary
                                              FROM employee e
                                              LEFT JOIN department d ON d.Depart_id=e.Depart_id
                                              WHERE e.Employee_id=? LIMIT 1");
mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
mysqli_stmt_execute($employeeStmt);
$employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
if (!$employee) { $_SESSION = []; session_destroy(); authRedirect('/login'); }

$settings = getAttendanceSettings($connection);
$now = attendanceNow($settings);
$todayAttendance = attendanceFindToday($connection, (int) $employeeId, $now->format('Y-m-d'));
$monthKey = strtolower($now->format('F'));
$metrics = attendancePeriodMetrics($connection, (int) $employeeId, (int) $now->format('Y'), $monthKey);
$status = attendanceStatusMeta($todayAttendance);

$paymentStmt = mysqli_prepare($connection, "SELECT p.pay_no, p.year, LOWER(p.month) AS month,
                                                   COALESCE(s.net_salary,p.total_pay) AS net_salary
                                            FROM payment p LEFT JOIN payment_snapshots s ON s.pay_no=p.pay_no
                                            WHERE p.emp_id=? ORDER BY p.year DESC,
                                            FIELD(LOWER(p.month),'december','november','october','september','august','july','june','may','april','march','february','january') ASC,
                                            p.pay_no DESC LIMIT 3");
mysqli_stmt_bind_param($paymentStmt, 'i', $employeeId);
mysqli_stmt_execute($paymentStmt);
$recentPayments = mysqli_fetch_all(mysqli_stmt_get_result($paymentStmt), MYSQLI_ASSOC);
$thaiMonths = attendanceMonthNames();
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="w-full space-y-4">
    <header class="border-b border-[#dedede] pb-3"><p class="text-[13px] text-[#615d59]">สวัสดี <?= $escape($employee['Name']) ?></p><h1 class="text-xl font-semibold text-[#202223]">ภาพรวมของฉัน</h1><p class="mt-0.5 text-sm text-[#6d7175]">ข้อมูลพนักงาน เวลาเข้างาน และเงินเดือนของคุณ</p></header>

    <section class="grid gap-4 lg:grid-cols-[1.2fr_.8fr]">
        <article class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5">
            <div class="flex items-start gap-4"><span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-[#eef6fd] text-lg font-bold text-[#0075de]"><?= $escape(mb_substr($employee['Name'], 0, 1, 'UTF-8')) ?></span><div class="min-w-0"><h2 class="truncate text-[18px] font-bold"><?= $escape($employee['Name']) ?></h2><p class="text-[13px] text-[#615d59]"><?= $escape($employee['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · <?= $escape($employee['jobtitle']) ?></p><span class="mt-2 inline-flex rounded-full bg-[#f6f5f4] px-2.5 py-1 text-[12px]">รหัสพนักงาน <?= $escape($employee['Employee_id']) ?></span></div></div>
            <dl class="mt-5 grid grid-cols-1 gap-3 border-t border-[#e6e6e6] pt-4 text-[14px] sm:grid-cols-2"><div><dt class="text-[12px] text-[#615d59]">อีเมล</dt><dd class="mt-0.5 break-all font-medium"><?= $escape($employee['Email'] ?: 'ไม่ได้ระบุ') ?></dd></div><div><dt class="text-[12px] text-[#615d59]">โทรศัพท์</dt><dd class="mt-0.5 font-medium"><?= $escape($employee['Phone_no'] ?: 'ไม่ได้ระบุ') ?></dd></div><div><dt class="text-[12px] text-[#615d59]">วันที่เริ่มงาน</dt><dd class="mt-0.5 font-medium"><?= $escape(date('d/m/Y', strtotime($employee['Start_date']))) ?></dd></div><div><dt class="text-[12px] text-[#615d59]">ฐานเงินเดือนปัจจุบัน</dt><dd class="mt-0.5 font-bold"><?= $employee['basic_salary'] !== null ? '฿'.number_format((float)$employee['basic_salary'],2) : 'ยังไม่กำหนด' ?></dd></div></dl>
        </article>

        <article class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5">
            <div class="flex items-center justify-between gap-3"><div><p class="text-[12px] text-[#615d59]">วันนี้ <?= $escape($now->format('d/m/Y')) ?></p><h2 class="mt-0.5 font-bold">สถานะการทำงาน</h2></div><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[12px] font-semibold <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i><?= $status['label'] ?></span></div>
            <div class="mt-4 grid grid-cols-2 overflow-hidden rounded-[8px] border border-[#e6e6e6]"><div class="p-3"><p class="text-[12px] text-[#615d59]">เวลาเข้า</p><p class="mt-1 text-[18px] font-bold"><?= $todayAttendance ? date('H:i', strtotime($todayAttendance['check_in_at'])) : '--:--' ?></p></div><div class="border-l border-[#e6e6e6] p-3"><p class="text-[12px] text-[#615d59]">เวลาออก</p><p class="mt-1 text-[18px] font-bold"><?= !empty($todayAttendance['check_out_at']) ? date('H:i', strtotime($todayAttendance['check_out_at'])) : '--:--' ?></p></div></div>
            <a href="/me/attendance" class="mt-4 flex min-h-11 items-center justify-center rounded-[8px] bg-[#0075de] px-4 font-semibold text-white hover:bg-[#005bab]"><i class="fa-solid fa-fingerprint mr-2"></i>ไปหน้าเช็คชื่อ</a>
        </article>
    </section>

    <section class="grid grid-cols-2 overflow-hidden rounded-lg border border-[#dedede] bg-white sm:grid-cols-4" aria-label="สรุปการเข้างานเดือนนี้">
        <div class="p-4 sm:border-r sm:border-[#e6e6e6]"><p class="text-[12px] text-[#615d59]">วันทำงานแล้ว</p><p class="mt-1 text-[22px] font-bold"><?= (int) $metrics['attendance_days'] ?></p></div><div class="border-l border-[#e6e6e6] p-4 sm:border-l-0 sm:border-r"><p class="text-[12px] text-[#615d59]">มาสาย</p><p class="mt-1 text-[22px] font-bold text-amber-700"><?= (int) $metrics['late_count'] ?></p></div><div class="border-t border-[#e6e6e6] p-4 sm:border-r sm:border-t-0"><p class="text-[12px] text-[#615d59]">นาทีที่สาย</p><p class="mt-1 text-[22px] font-bold"><?= (int) $metrics['late_minutes'] ?></p></div><div class="border-l border-t border-[#e6e6e6] p-4 sm:border-l-0 sm:border-t-0"><p class="text-[12px] text-[#615d59]">ขาดงาน</p><p class="mt-1 text-[22px] font-bold text-red-700"><?= (int) $metrics['absence_days'] ?></p></div>
    </section>

    <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white">
        <header class="flex items-center justify-between gap-3 border-b border-[#e6e6e6] p-4"><div><h2 class="font-bold">เงินเดือนล่าสุด</h2><p class="text-[13px] text-[#615d59]">ยอดสุทธิที่บันทึกแล้ว</p></div><a href="/me/payhistory" class="text-[13px] font-semibold text-[#0075de]">ดูทั้งหมด <i class="fa-solid fa-arrow-right ml-1"></i></a></header>
        <?php if ($recentPayments): ?><div class="divide-y divide-[#e6e6e6]"><?php foreach ($recentPayments as $payment): ?><div class="flex items-center justify-between gap-3 p-4"><div><p class="font-bold"><?= $escape($thaiMonths[$payment['month']] ?? $payment['month']) ?> <?= $escape($payment['year']) ?></p><p class="text-[12px] text-[#615d59]">รายการ #<?= $escape($payment['pay_no']) ?></p></div><div class="text-right"><p class="text-[17px] font-bold">฿<?= number_format((float) $payment['net_salary'], 2) ?></p><span class="text-[12px] font-semibold text-emerald-700">จ่ายแล้ว</span></div></div><?php endforeach; ?></div><?php else: ?><div class="p-8 text-center text-[14px] text-[#615d59]"><i class="fa-solid fa-receipt mb-2 text-xl text-[#a39e98]"></i><p>ยังไม่มีประวัติการจ่ายเงินเดือน</p></div><?php endif; ?>
    </section>
</div>
