<?php
$title = 'ประวัติการจ่ายเงินเดือนพนักงาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$thaiMonths = [
    'january' => 'มกราคม', 'february' => 'กุมภาพันธ์', 'march' => 'มีนาคม',
    'april' => 'เมษายน', 'may' => 'พฤษภาคม', 'june' => 'มิถุนายน',
    'july' => 'กรกฎาคม', 'august' => 'สิงหาคม', 'september' => 'กันยายน',
    'october' => 'ตุลาคม', 'november' => 'พฤศจิกายน', 'december' => 'ธันวาคม',
];
$jobLabels = [
    'executive' => 'เจ้าหน้าที่', 'manager' => 'ผู้จัดการ', 'director' => 'ผู้อำนวยการ',
    'accountant' => 'นักบัญชี', 'chief' => 'หัวหน้าฝ่าย',
];
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$initials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if (!$parts) return 'พ';
    $value = mb_substr($parts[0], 0, 1, 'UTF-8');
    if (count($parts) > 1) $value .= mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
    return $value;
};

$requestedEmployeeId = trim((string) ($_GET['employee_id'] ?? $_GET['id'] ?? $_POST['id'] ?? ''));
if ($requestedEmployeeId !== '' && !ctype_digit($requestedEmployeeId)) $requestedEmployeeId = '';
$requestedYear = trim((string) ($_GET['year'] ?? ''));
if ($requestedYear !== '' && !ctype_digit($requestedYear)) $requestedYear = '';

// One aggregate query powers search, department filters, paid periods and history counts.
$employeeSql = "SELECT
                    e.Employee_id, e.Name, e.jobtitle, e.Depart_id, e.loan, e.p_fund,
                    d.Depart_name, j.basic_salary,
                    COUNT(DISTINCT p.pay_no) AS history_count,
                    GROUP_CONCAT(DISTINCT CONCAT(p.year, '|', LOWER(p.month)) SEPARATOR ',') AS paid_periods
                FROM employee e
                INNER JOIN job j ON e.jobtitle = j.Job_Title
                LEFT JOIN department d ON e.Depart_id = d.Depart_id
                LEFT JOIN payment p ON e.Employee_id = p.emp_id
                GROUP BY e.Employee_id, e.Name, e.jobtitle, e.Depart_id, e.loan, e.p_fund,
                         d.Depart_name, j.basic_salary
                ORDER BY e.Name ASC";
$employeeResult = mysqli_query($connection, $employeeSql);
$employees = [];
if ($employeeResult) while ($employee = mysqli_fetch_assoc($employeeResult)) $employees[] = $employee;

$departmentResult = mysqli_query($connection, 'SELECT Depart_id, Depart_name FROM department ORDER BY Depart_name ASC');
$departments = [];
if ($departmentResult) while ($department = mysqli_fetch_assoc($departmentResult)) $departments[] = $department;

$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((string) $employee['Employee_id'] === $requestedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}

$allPayments = [];
if ($selectedEmployee) {
    $employeeId = (int) $selectedEmployee['Employee_id'];
    $paymentSql = "SELECT
                        p.year, LOWER(p.month) AS month, p.pay_no, p.emp_id,
                        e.Name, e.bank_accno,
                        COALESCE(s.base_salary, p.total_pay) AS base_salary,
                        COALESCE(s.total_additions, 0) AS total_additions,
                        COALESCE(s.total_deductions, 0) AS total_deductions,
                        COALESCE(s.net_salary, p.total_pay) AS total_pay,
                        s.payment_note, s.created_at AS paid_at,
                        GROUP_CONCAT(CASE WHEN a.adjustment_type='addition' THEN CONCAT(a.adjustment_name, ' ฿', FORMAT(a.amount, 2)) END ORDER BY a.id SEPARATOR ' • ') AS addition_details,
                        GROUP_CONCAT(CASE WHEN a.adjustment_type='deduction' THEN CONCAT(a.adjustment_name, ' ฿', FORMAT(a.amount, 2)) END ORDER BY a.id SEPARATOR ' • ') AS deduction_details
                    FROM employee e
                    INNER JOIN payment p ON e.Employee_id = p.emp_id
                    LEFT JOIN payment_snapshots s ON s.pay_no = p.pay_no
                    LEFT JOIN payroll_adjustments a ON a.pay_no = p.pay_no
                    WHERE e.Employee_id = ?
                    GROUP BY p.year, p.month, p.pay_no, p.emp_id, e.Name, e.bank_accno,
                             s.base_salary, s.total_additions, s.total_deductions, s.net_salary,
                             s.payment_note, s.created_at, p.total_pay
                    ORDER BY p.year DESC,
                             FIELD(LOWER(p.month), 'december','november','october','september','august','july','june','may','april','march','february','january') ASC,
                             p.pay_no DESC";
    $paymentStmt = mysqli_prepare($connection, $paymentSql);
    if ($paymentStmt) {
        mysqli_stmt_bind_param($paymentStmt, 'i', $employeeId);
        mysqli_stmt_execute($paymentStmt);
        $paymentResult = mysqli_stmt_get_result($paymentStmt);
        while ($row = mysqli_fetch_assoc($paymentResult)) $allPayments[] = $row;
        mysqli_stmt_close($paymentStmt);
    }
}

$availableYears = [];
foreach ($allPayments as $payment) $availableYears[(string) $payment['year']] = true;
$availableYears = array_keys($availableYears);
rsort($availableYears, SORT_NUMERIC);
$selectedYear = $requestedYear !== '' && in_array($requestedYear, $availableYears, true) ? $requestedYear : '';
$payments = $selectedYear === '' ? $allPayments : array_values(array_filter($allPayments, static fn (array $payment): bool => (string) $payment['year'] === $selectedYear));
$totalNet = array_sum(array_map(static fn (array $payment): float => (float) $payment['total_pay'], $payments));
$latestPayment = $allPayments[0] ?? null;
$historyCount = count($allPayments);
?>

<div class="w-full space-y-4">
    <header class="pb-3 border-b border-[#dedede]">
        <h1 class="text-xl font-semibold text-[#202223]">ประวัติการจ่ายเงินเดือนพนักงาน</h1>
        <p class="text-sm text-[#6d7175] mt-0.5">เลือกพนักงานเพื่อดูรายการย้อนหลัง ยอดสรุป และรายละเอียดที่บันทึกไว้</p>
    </header>

    <section aria-labelledby="historyEmployeeTitle">
        <div class="mb-2 flex items-center justify-between gap-3"><h2 id="historyEmployeeTitle" class="text-[15px] font-bold">พนักงาน</h2><span class="text-[12px] text-[#615d59]">เลือกได้ครั้งละ 1 คน</span></div>
        <div class="rounded-lg border border-[#dedede] bg-white p-3 sm:p-4">
            <?php if (!$selectedEmployee): ?>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 min-w-0"><span class="w-11 h-11 shrink-0 rounded-full bg-[#f6f5f4] text-[#a39e98] flex items-center justify-center"><i class="fa-solid fa-user-clock" aria-hidden="true"></i></span><div><p class="font-bold">ยังไม่ได้เลือกพนักงาน</p><p class="mt-0.5 text-[13px] text-[#615d59]">ค้นหาด้วยชื่อ รหัส แผนก หรือตำแหน่ง</p></div></div>
                    <button type="button" data-open-employee-picker class="min-h-11 w-full rounded-[8px] bg-[#0075de] px-4 font-semibold text-white hover:bg-[#005bab] sm:w-auto"><i class="fa-solid fa-users mr-2" aria-hidden="true"></i>เลือกพนักงาน</button>
                </div>
            <?php else: ?>
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 items-center gap-3"><span class="w-12 h-12 shrink-0 rounded-full bg-[#eef6fd] text-[#0075de] flex items-center justify-center font-bold"><?= $escape($initials($selectedEmployee['Name'])) ?></span><div class="min-w-0"><p class="truncate text-[16px] font-bold"><?= $escape($selectedEmployee['Name']) ?></p><p class="text-[13px] text-[#615d59]">รหัส <?= $escape($selectedEmployee['Employee_id']) ?></p><p class="mt-0.5 truncate text-[13px] text-[#615d59]"><?= $escape($selectedEmployee['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · <?= $escape($jobLabels[$selectedEmployee['jobtitle']] ?? $selectedEmployee['jobtitle']) ?></p></div></div>
                    <div class="flex items-center justify-between gap-3 sm:justify-end"><span class="rounded-full bg-[#f6f5f4] px-3 py-1.5 text-[12px] font-semibold text-[#615d59]"><?= $historyCount ?> รายการ</span><button type="button" data-open-employee-picker class="min-h-11 rounded-[8px] border border-[#d5d3d0] bg-white px-4 font-semibold text-[#31302e] hover:bg-[#f6f5f4]"><i class="fa-solid fa-repeat mr-2" aria-hidden="true"></i>เปลี่ยนพนักงาน</button></div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!$selectedEmployee): ?>
        <section class="flex min-h-[300px] flex-col items-center justify-center rounded-lg border border-dashed border-[#d5d3d0] bg-white px-5 py-10 text-center">
            <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#f6f5f4] text-xl text-[#8a8580]"><i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i></span><h2 class="mt-4 text-[17px] font-bold">เลือกพนักงานเพื่อดูประวัติการจ่ายเงิน</h2><p class="mt-1 max-w-md text-[14px] text-[#615d59]">ระบบจะแสดงยอดที่บันทึกไว้ในแต่ละงวด พร้อมรายการเพิ่ม รายการหัก และลิงก์ไปยังสลิปเงินเดือน</p><button type="button" data-open-employee-picker class="mt-5 min-h-11 rounded-[8px] bg-[#0075de] px-4 font-semibold text-white">เลือกพนักงาน</button>
        </section>
    <?php else: ?>
        <section class="grid grid-cols-1 overflow-hidden rounded-lg border border-[#dedede] bg-white sm:grid-cols-3" aria-label="สรุปประวัติการจ่ายเงินเดือน">
            <div class="p-4 sm:border-r sm:border-[#e6e6e6]"><p class="text-[12px] text-[#615d59]">จำนวนรายการ<?= $selectedYear ? ' ปี ' . $escape($selectedYear) : 'ทั้งหมด' ?></p><p class="mt-1 text-[21px] font-bold"><?= count($payments) ?> <span class="text-[13px] font-normal text-[#615d59]">รายการ</span></p></div>
            <div class="border-t border-[#e6e6e6] p-4 sm:border-r sm:border-t-0"><p class="text-[12px] text-[#615d59]">ยอดสุทธิรวม<?= $selectedYear ? 'ตามปีที่เลือก' : '' ?></p><p class="mt-1 text-[21px] font-bold">฿<?= number_format($totalNet, 2) ?></p></div>
            <div class="border-t border-[#e6e6e6] p-4 sm:border-t-0"><p class="text-[12px] text-[#615d59]">งวดล่าสุด</p><p class="mt-1 text-[17px] font-bold"><?= $latestPayment ? $escape(($thaiMonths[$latestPayment['month']] ?? $latestPayment['month']) . ' ' . $latestPayment['year']) : 'ยังไม่มีข้อมูล' ?></p></div>
        </section>

        <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white" aria-labelledby="paymentHistoryListTitle">
            <header class="flex flex-col gap-3 border-b border-[#e6e6e6] p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><h2 id="paymentHistoryListTitle" class="font-bold">รายการจ่ายเงินเดือน</h2><p class="mt-0.5 text-[13px] text-[#615d59]">ยอดทั้งหมดเป็นค่าที่บันทึกไว้ ณ วันที่จ่าย</p></div>
                <?php if ($availableYears): ?><form method="get" action="/employee/payhistory" class="flex items-center gap-2"><input type="hidden" name="employee_id" value="<?= $escape($selectedEmployee['Employee_id']) ?>"><label for="historyYear" class="text-[13px] text-[#615d59]">ปี</label><select id="historyYear" name="year" class="dashboard-period-select min-w-[132px] rounded-[7px] border border-[#dedede] bg-white px-3"><option value="">ทุกปี</option><?php foreach ($availableYears as $year): ?><option value="<?= $escape($year) ?>" <?= $selectedYear === (string) $year ? 'selected' : '' ?>><?= $escape($year) ?></option><?php endforeach; ?></select></form><?php endif; ?>
            </header>

            <?php if ($payments): ?>
                <section class="js-mobile-list-section md:hidden px-4">
                    <div class="relative my-3"><i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#a39e98]" aria-hidden="true"></i><input type="search" class="js-mobile-search w-full min-h-11 rounded-[8px] border border-[#e6e6e6] pl-11 pr-4 text-[14px] outline-none focus:border-[#0075de]" placeholder="ค้นหาเดือน ปี หรือเลขที่รายการ..." aria-label="ค้นหาประวัติการจ่ายเงิน"></div>
                    <div class="js-mobile-records divide-y divide-[#e6e6e6]">
                        <?php foreach ($payments as $index => $row): ?>
                            <article class="js-mobile-record py-4" data-original="<?= $index ?>" data-search="<?= $escape(mb_strtolower($row['pay_no'] . ' ' . $row['year'] . ' ' . ($thaiMonths[$row['month']] ?? $row['month']) . ' ' . $row['total_pay'], 'UTF-8')) ?>">
                                <div class="flex items-start justify-between gap-3"><div><p class="font-bold"><?= $escape($thaiMonths[$row['month']] ?? $row['month']) ?> <?= $escape($row['year']) ?></p><p class="mt-0.5 text-[12px] text-[#615d59]">รายการ #<?= $escape($row['pay_no']) ?></p></div><div class="text-right"><p class="text-[17px] font-bold">฿<?= number_format((float) $row['total_pay'], 2) ?></p><span class="text-[12px] font-semibold text-emerald-700">จ่ายแล้ว</span></div></div>
                                <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-[13px]"><dt class="text-[#615d59]">เงินเดือนพื้นฐาน</dt><dd class="text-right">฿<?= number_format((float) $row['base_salary'], 2) ?></dd><dt class="text-emerald-700">รายรับเพิ่ม</dt><dd class="text-right text-emerald-700">+฿<?= number_format((float) $row['total_additions'], 2) ?></dd><dt class="text-red-700">รายการหัก</dt><dd class="text-right text-red-700">-฿<?= number_format((float) $row['total_deductions'], 2) ?></dd></dl>
                                <div class="mt-3 flex flex-wrap gap-2 border-t border-[#ececec] pt-3"><details class="min-w-0 flex-1 text-[12px]"><summary class="cursor-pointer font-semibold text-[#0075de]">ดูรายละเอียด</summary><div class="mt-2 space-y-1 text-[#615d59]"><?php if ($row['addition_details']): ?><p class="text-emerald-700">เพิ่ม: <?= $escape($row['addition_details']) ?></p><?php endif; ?><?php if ($row['deduction_details']): ?><p class="text-red-700">หัก: <?= $escape($row['deduction_details']) ?></p><?php endif; ?><?php if ($row['payment_note']): ?><p>หมายเหตุ: <?= $escape($row['payment_note']) ?></p><?php endif; ?><?php if (!$row['addition_details'] && !$row['deduction_details'] && !$row['payment_note']): ?><p>ไม่มีรายละเอียดเพิ่มเติม</p><?php endif; ?></div></details><a href="/employee/payslip?year=<?= rawurlencode((string) $row['year']) ?>&month=<?= rawurlencode((string) $row['month']) ?>" class="inline-flex min-h-9 items-center rounded-[7px] border border-[#d5d3d0] px-3 text-[12px] font-semibold hover:bg-[#f6f5f4]"><i class="fa-solid fa-receipt mr-2" aria-hidden="true"></i>ดูสลิป</a></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <div class="js-mobile-empty-filter hidden py-8 text-center text-[14px] text-[#615d59]">ไม่พบรายการที่ตรงกับการค้นหา</div>
                </section>

                <div class="hidden min-w-0 md:block">
                    <table class="js-data-table w-full text-left text-[14px] text-[#202223]" data-export-name="ประวัติการจ่ายเงินเดือน <?= $escape($selectedEmployee['Name']) ?>">
                        <thead class="border-b border-[#e6e6e6] bg-[#f6f5f4]"><tr><th class="px-4 py-3 text-[12px] font-semibold text-[#615d59]">งวด</th><th class="px-4 py-3 text-[12px] font-semibold text-[#615d59]">เลขที่รายการ</th><th class="px-4 py-3 text-right text-[12px] font-semibold text-[#615d59]">เงินเดือนพื้นฐาน</th><th class="px-4 py-3 text-right text-[12px] font-semibold text-[#615d59]">รายรับเพิ่ม</th><th class="px-4 py-3 text-right text-[12px] font-semibold text-[#615d59]">รายการหัก</th><th class="px-4 py-3 text-right text-[12px] font-semibold text-[#615d59]">เงินเดือนสุทธิ</th><th class="px-4 py-3 text-[12px] font-semibold text-[#615d59]">สถานะ</th><th class="px-4 py-3 text-right text-[12px] font-semibold text-[#615d59]">การทำงาน</th></tr></thead>
                        <tbody class="divide-y divide-[#e6e6e6]">
                            <?php foreach ($payments as $row): ?>
                                <tr class="hover:bg-[#f8f8f7]"><td class="px-4 py-3 font-semibold whitespace-nowrap"><?= $escape($thaiMonths[$row['month']] ?? $row['month']) ?> <?= $escape($row['year']) ?></td><td class="px-4 py-3 text-[#615d59]">#<?= $escape($row['pay_no']) ?></td><td class="px-4 py-3 text-right whitespace-nowrap">฿<?= number_format((float) $row['base_salary'], 2) ?></td><td class="px-4 py-3 text-right text-emerald-700 whitespace-nowrap">+฿<?= number_format((float) $row['total_additions'], 2) ?></td><td class="px-4 py-3 text-right text-red-700 whitespace-nowrap">-฿<?= number_format((float) $row['total_deductions'], 2) ?></td><td class="px-4 py-3 text-right font-bold whitespace-nowrap">฿<?= number_format((float) $row['total_pay'], 2) ?></td><td class="px-4 py-3"><span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-semibold text-emerald-700">จ่ายแล้ว</span></td><td class="px-4 py-3"><div class="flex justify-end gap-2"><details class="relative"><summary class="list-none cursor-pointer rounded-[6px] border border-[#d5d3d0] px-2.5 py-1.5 text-[12px] font-semibold hover:bg-[#f6f5f4]">รายละเอียด</summary><div class="absolute right-0 z-20 mt-2 w-72 rounded-[8px] border border-[#dedede] bg-white p-3 text-left text-[12px] font-normal shadow-lg"><p class="font-bold text-[#202223]">รายการ #<?= $escape($row['pay_no']) ?></p><?php if ($row['addition_details']): ?><p class="mt-2 text-emerald-700">เพิ่ม: <?= $escape($row['addition_details']) ?></p><?php endif; ?><?php if ($row['deduction_details']): ?><p class="mt-1 text-red-700">หัก: <?= $escape($row['deduction_details']) ?></p><?php endif; ?><?php if ($row['payment_note']): ?><p class="mt-2 text-[#615d59]">หมายเหตุ: <?= $escape($row['payment_note']) ?></p><?php endif; ?><?php if ($row['paid_at']): ?><p class="mt-2 text-[#8a8580]">บันทึกเมื่อ <?= $escape(date('d/m/Y H:i', strtotime($row['paid_at']))) ?></p><?php endif; ?><?php if (!$row['addition_details'] && !$row['deduction_details'] && !$row['payment_note']): ?><p class="mt-2 text-[#615d59]">ไม่มีรายละเอียดเพิ่มเติม</p><?php endif; ?></div></details><a href="/employee/payslip?year=<?= rawurlencode((string) $row['year']) ?>&month=<?= rawurlencode((string) $row['month']) ?>" class="rounded-[6px] border border-[#d5d3d0] px-2.5 py-1.5 text-[12px] font-semibold hover:bg-[#f6f5f4]" title="ดูสลิปงวดนี้"><i class="fa-solid fa-receipt" aria-hidden="true"></i><span class="sr-only">ดูสลิป</span></a></div></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="flex min-h-[260px] flex-col items-center justify-center px-5 py-10 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#f6f5f4] text-[#8a8580]"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span><h3 class="mt-4 font-bold"><?= $historyCount ? 'ไม่พบรายการในปีที่เลือก' : 'พนักงานคนนี้ยังไม่มีประวัติการจ่ายเงิน' ?></h3><p class="mt-1 text-[13px] text-[#615d59]"><?= $historyCount ? 'ลองเลือกทุกปีหรือเลือกปีอื่น' : 'เมื่อบันทึกการจ่ายเงินแล้ว รายการจะแสดงที่หน้านี้' ?></p><?php if (!$historyCount): ?><a href="/employee/payment?employee_id=<?= rawurlencode((string) $selectedEmployee['Employee_id']) ?>" class="mt-5 inline-flex min-h-11 items-center rounded-[8px] bg-[#0075de] px-4 font-semibold text-white"><i class="fa-solid fa-calculator mr-2" aria-hidden="true"></i>ไปหน้าจ่ายเงินเดือน</a><?php endif; ?></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <?php
    $employeePickerEmployees = $employees;
    $employeePickerDepartments = $departments;
    $employeePickerSelectedId = $selectedEmployee ? (string) $selectedEmployee['Employee_id'] : '';
    $employeePickerJobLabels = $jobLabels;
    $employeePickerContext = 'payhistory';
    require __DIR__ . '/components/employee_picker.php';
    ?>
</div>
