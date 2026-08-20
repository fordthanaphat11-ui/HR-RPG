<?php
$title = 'จ่ายเงินเดือน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/payroll.php';
require_once __DIR__ . '/../lib/attendance.php';
require_once __DIR__ . '/../lib/salary.php';

if (!isset($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$error = '';
$success = (string) ($_SESSION['payroll_success'] ?? '');
unset($_SESSION['payroll_success']);
if ($success !== '') appToast('success', $success);
$months = payrollMonths();
$thaiMonths = payrollThaiMonths();
$settings = getPayrollSettings($connection);
$jobLabels = [
    'executive' => 'เจ้าหน้าที่',
    'manager' => 'ผู้จัดการ',
    'director' => 'ผู้อำนวยการ',
    'accountant' => 'นักบัญชี',
    'chief' => 'หัวหน้าฝ่าย',
];

$formYear = (string) ($_POST['year'] ?? $_GET['year'] ?? date('Y'));
if (!ctype_digit($formYear) || (int) $formYear < 2000 || (int) $formYear > 2200) {
    $formYear = date('Y');
}
$formMonth = strtolower((string) ($_POST['month'] ?? $_GET['month'] ?? date('F')));
if (!in_array($formMonth, $months, true)) {
    $formMonth = strtolower(date('F'));
}
$formAbsence = (string) ($_POST['absence_days'] ?? '');
$formLateCount = (string) ($_POST['late_count'] ?? '');
$formLateMinutes = (string) ($_POST['late_minutes'] ?? '');
$formOvertime = (string) ($_POST['overtime'] ?? '0');
$formNote = (string) ($_POST['payment_note'] ?? '');
$postedAdditions = (array) ($_POST['additions'] ?? []);
$postedDeductions = (array) ($_POST['deductions'] ?? []);

$requestedEmployeeId = (string) (
    $_POST['empid']
    ?? $_GET['employee_id']
    ?? $_GET['empid']
    ?? $_GET['id']
    ?? ''
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empid = trim((string) ($_POST['empid'] ?? ''));
    $year = trim((string) ($_POST['year'] ?? ''));
    $month = strtolower(trim((string) ($_POST['month'] ?? '')));
    $overtimeRaw = trim((string) ($_POST['overtime'] ?? '0'));

    try {
        if ($empid === '' || !ctype_digit($empid)) throw new InvalidArgumentException('กรุณาเลือกพนักงานก่อนดำเนินการ');
        if ($year === '' || !ctype_digit($year) || !in_array($month, $months, true)) throw new InvalidArgumentException('กรุณาระบุงวดการจ่ายเงินให้ถูกต้อง');
        if (($_POST['confirmed'] ?? '') !== '1') throw new InvalidArgumentException('กรุณาตรวจสอบและยืนยันยอดสุทธิก่อนบันทึก');

        $overtimeHours = payrollNumericValue($overtimeRaw);
        if ($overtimeHours === null || $overtimeHours < 0) throw new InvalidArgumentException('ชั่วโมงล่วงเวลาต้องเป็นตัวเลขตั้งแต่ 0 ขึ้นไป เช่น 120');

        $manualAdditions = normalizePayrollAdjustments($postedAdditions);
        $manualDeductions = normalizePayrollAdjustments($postedDeductions);
        $employeeId = (int) $empid;
        $paymentYear = (int) $year;
        $paymentNote = mb_substr(trim((string) ($_POST['payment_note'] ?? '')), 0, 500, 'UTF-8');

        mysqli_begin_transaction($connection);
        $employeeStmt = mysqli_prepare($connection, 'SELECT e.Employee_id, e.Name, e.loan, e.p_fund FROM employee e WHERE e.Employee_id=? FOR UPDATE');
        mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
        mysqli_stmt_execute($employeeStmt);
        $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
        if (!$employee) throw new RuntimeException('ไม่พบพนักงานที่เลือก');
        $periodDate = salaryPeriodDate($paymentYear, $month);
        $effectiveSalary = salaryEffectiveAt($connection, $employeeId, $periodDate, true);
        if (!$effectiveSalary) throw new RuntimeException('ยังไม่ได้กำหนดเงินเดือนพื้นฐานสำหรับงวดนี้ กรุณากำหนดเงินเดือนก่อนจ่าย');
        $employee['basic_salary'] = $effectiveSalary['salary_amount'];

        $attendanceMetrics = attendancePeriodMetrics($connection, $employeeId, $paymentYear, $month);
        $absenceDays = (float) $attendanceMetrics['absence_days'];
        $lateCount = (int) $attendanceMetrics['late_count'];
        $lateMinutes = (int) $attendanceMetrics['late_minutes'];

        $duplicateStmt = mysqli_prepare($connection, 'SELECT pay_no FROM payment WHERE emp_id=? AND year=? AND month=? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($duplicateStmt, 'iis', $employeeId, $paymentYear, $month);
        mysqli_stmt_execute($duplicateStmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicateStmt))) throw new RuntimeException('พนักงานคนนี้ได้รับการจ่ายเงินเดือนสำหรับงวดที่เลือกแล้ว');

        $settings = getPayrollSettings($connection);
        $calculation = calculatePayroll($employee, $settings, ['absence_days'=>$absenceDays,'late_count'=>$lateCount,'late_minutes'=>$lateMinutes,'source'=>'attendance'], $manualAdditions, $manualDeductions, (float)$overtimeHours);
        $nextResult = mysqli_query($connection, 'SELECT pay_no FROM payment ORDER BY pay_no DESC LIMIT 1 FOR UPDATE');
        $lastPayment = $nextResult ? mysqli_fetch_assoc($nextResult) : null;
        $payNo = (int) ($lastPayment['pay_no'] ?? 0) + 1;
        $legacyAbsence = (float) ($absenceDays ?? 0);
        $legacySeason = 0.0;
        $legacyOther = round(array_sum(array_column($manualAdditions, 'amount')), 2);
        $paymentStmt = mysqli_prepare($connection, 'INSERT INTO payment (pay_no,emp_id,year,month,absence,loan_cut,pfund_cut,overtime,season_bonus,other_bonus,medi_allow,house_allow,total_pay) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        mysqli_stmt_bind_param($paymentStmt, 'iiisddddddddd', $payNo, $employeeId, $paymentYear, $month, $legacyAbsence, $calculation['loan_cut'], $calculation['fund_cut'], $overtimeHours, $legacySeason, $legacyOther, $calculation['medical_allowance'], $calculation['housing_allowance'], $calculation['net_salary']);
        if (!mysqli_stmt_execute($paymentStmt)) throw new RuntimeException('ไม่สามารถบันทึกรายการจ่ายเงินได้');

        $settingsJson = json_encode([
            'payroll'=>$settings,
            'attendance'=>getAttendanceSettings($connection),
            'attendance_metrics'=>array_intersect_key($attendanceMetrics, array_flip(['expected_days','attendance_days','absence_days','late_count','late_minutes','completed_through'])),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $source = 'attendance';
        $baseSalary = (float) $calculation['base_salary'];
        $totalAdditions = (float) $calculation['total_additions'];
        $totalDeductions = (float) $calculation['total_deductions'];
        $netSalary = (float) $calculation['net_salary'];
        $absenceRate = (float) $calculation['absence_rate'];
        $absenceDeduction = (float) $calculation['absence_deduction'];
        $lateRate = (float) $calculation['late_rate'];
        $lateDeduction = (float) $calculation['late_deduction'];
        $snapshotStmt = mysqli_prepare($connection, 'INSERT INTO payment_snapshots (pay_no,emp_id,payroll_year,payroll_month,base_salary,total_additions,total_deductions,net_salary,absence_days,absence_rate,absence_deduction,late_count,late_minutes,late_rate,late_deduction,attendance_source,payment_note,settings_snapshot) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        mysqli_stmt_bind_param($snapshotStmt, 'iiisdddddddiiddsss', $payNo, $employeeId, $paymentYear, $month, $baseSalary, $totalAdditions, $totalDeductions, $netSalary, $absenceDays, $absenceRate, $absenceDeduction, $lateCount, $lateMinutes, $lateRate, $lateDeduction, $source, $paymentNote, $settingsJson);
        if (!mysqli_stmt_execute($snapshotStmt)) throw new RuntimeException('ไม่สามารถบันทึก snapshot การคำนวณได้');

        $adjustmentStmt = mysqli_prepare($connection, 'INSERT INTO payroll_adjustments (pay_no,emp_id,adjustment_type,adjustment_source,adjustment_name,amount,note) VALUES (?,?,?,?,?,?,?)');
        foreach ([['addition',$calculation['automatic_additions']],['addition',$manualAdditions],['deduction',$calculation['automatic_deductions']],['deduction',$manualDeductions]] as [$type,$items]) {
            foreach ($items as $item) {
                $adjustmentType = $type;
                $adjustmentSource = (string) ($item['source'] ?? 'manual');
                $adjustmentName = (string) $item['name'];
                $adjustmentAmount = (float) $item['amount'];
                $adjustmentNote = (string) ($item['note'] ?? '');
                mysqli_stmt_bind_param($adjustmentStmt, 'iisssds', $payNo, $employeeId, $adjustmentType, $adjustmentSource, $adjustmentName, $adjustmentAmount, $adjustmentNote);
                if (!mysqli_stmt_execute($adjustmentStmt)) throw new RuntimeException('ไม่สามารถบันทึกรายละเอียดรายการเพิ่ม/หักได้');
            }
        }

        $newLoan = max(0, (float)$employee['loan'] - $calculation['loan_cut']);
        $newFund = (float)$employee['p_fund'] + $calculation['fund_cut'];
        $employeeUpdate = mysqli_prepare($connection, 'UPDATE employee SET loan=?, p_fund=? WHERE Employee_id=?');
        mysqli_stmt_bind_param($employeeUpdate, 'ddi', $newLoan, $newFund, $employeeId);
        if (!mysqli_stmt_execute($employeeUpdate)) throw new RuntimeException('ไม่สามารถอัปเดตยอดเงินยืมและกองทุนได้');

        mysqli_commit($connection);
        $nextEmployee = findNextUnpaidEmployee($connection, $paymentYear, $month, (string) $employee['Name'], $employeeId);
        if ($nextEmployee) {
            appFlashToast('success', 'จ่ายเงินเดือนให้ '.$employee['Name'].' เรียบร้อยแล้ว (เลขที่ '.$payNo.') ระบบเลือกพนักงานคนถัดไปให้แล้ว');
        } else {
            appFlashToast('success', 'จ่ายเงินเดือนให้ '.$employee['Name'].' เรียบร้อยแล้ว (เลขที่ '.$payNo.') และจ่ายครบทุกคนในงวดนี้แล้ว');
        }
        $redirect = '/employee/payment?year='.rawurlencode((string)$paymentYear).'&month='.rawurlencode($month);
        if ($nextEmployee) $redirect .= '&employee_id='.rawurlencode((string)$nextEmployee['Employee_id']);
        header('Location: '.$redirect);
        exit;
    } catch (Throwable $exception) {
        if (mysqli_errno($connection) || mysqli_thread_id($connection)) @mysqli_rollback($connection);
        $error = $exception->getMessage();
        appToast('error', $error);
    }
}

$periodDate = salaryPeriodDate((int) $formYear, $formMonth);
$employees = salaryEmployeeList($connection, $periodDate);
$paidResult = mysqli_query($connection, "SELECT emp_id, GROUP_CONCAT(DISTINCT CONCAT(year, '|', LOWER(month)) SEPARATOR ',') AS paid_periods FROM payment GROUP BY emp_id");
$paidPeriodsByEmployee = [];
while ($paidResult && $paid = mysqli_fetch_assoc($paidResult)) $paidPeriodsByEmployee[(string)$paid['emp_id']] = (string)$paid['paid_periods'];
foreach ($employees as &$employee) $employee['paid_periods'] = $paidPeriodsByEmployee[(string)$employee['Employee_id']] ?? '';
unset($employee);

$departmentResult = mysqli_query($connection, "SELECT Depart_id, Depart_name FROM department ORDER BY Depart_name ASC");
$departments = [];
if ($departmentResult) {
    while ($department = mysqli_fetch_assoc($departmentResult)) {
        $departments[] = $department;
    }
}

$selectedEmployee = null;
foreach ($employees as $employee) {
    if ((string) $employee['Employee_id'] === $requestedEmployeeId) {
        $selectedEmployee = $employee;
        break;
    }
}

$selectedAttendanceMetrics = null;
if ($selectedEmployee) {
    try {
        $selectedAttendanceMetrics = attendancePeriodMetrics($connection, (int) $selectedEmployee['Employee_id'], (int) $formYear, $formMonth);
        $formAbsence = (string) $selectedAttendanceMetrics['absence_days'];
        $formLateCount = (string) $selectedAttendanceMetrics['late_count'];
        $formLateMinutes = (string) $selectedAttendanceMetrics['late_minutes'];
    } catch (Throwable) {
        $selectedAttendanceMetrics = null;
    }
}

function employeeInitials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if (!$parts) {
        return 'พ';
    }

    $initials = mb_substr($parts[0], 0, 1, 'UTF-8');
    if (count($parts) > 1) {
        $initials .= mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
    }

    return $initials;
}

function paymentEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$currentPeriodKey = $formYear . '|' . $formMonth;
$selectedPaidPeriods = $selectedEmployee ? array_filter(explode(',', (string) ($selectedEmployee['paid_periods'] ?? ''))) : [];
$selectedAlreadyPaid = $selectedEmployee && in_array($currentPeriodKey, $selectedPaidPeriods, true);
$selectedHasSalary = $selectedEmployee && $selectedEmployee['basic_salary'] !== null;
?>

<div class="w-full space-y-4">
    <header class="pb-3 border-b border-[#dedede]">
        <h1 class="text-xl font-semibold text-[#202223]">จ่ายเงินเดือน</h1>
        <p class="text-sm text-[#6d7175] mt-0.5">เลือกพนักงาน ตรวจสอบข้อมูล และบันทึกการจ่ายเงินเดือนประจำงวด</p>
    </header>

    <?php if ($error): ?>
        <div class="rounded-[8px] border border-amber-200 bg-amber-50 p-3.5 text-[14px] text-amber-800" role="alert">
            <i class="fa-solid fa-circle-exclamation mr-2" aria-hidden="true"></i><?= paymentEscape($error) ?>
        </div>
    <?php endif; ?>


    <form id="payrollPaymentForm" method="post" action="/employee/payment" hx-boost="false" class="space-y-5 sm:space-y-6">
        <section aria-labelledby="employeeSelectorTitle">
            <div class="flex items-center justify-between gap-3 mb-2">
                <h2 id="employeeSelectorTitle" class="text-[15px] font-bold">พนักงาน</h2>
                <span class="text-[12px] text-[#615d59]">จำเป็น</span>
            </div>

            <div class="rounded-lg border border-[#dedede] bg-white p-3 sm:p-4">
                <input type="hidden" name="empid" id="selected_employee_id" value="<?= $selectedEmployee ? paymentEscape($selectedEmployee['Employee_id']) : '' ?>">

                <div id="employeeSelectionEmpty" class="<?= $selectedEmployee ? 'hidden' : 'flex' ?> flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 shrink-0 rounded-full bg-[#f6f5f4] text-[#a39e98] flex items-center justify-center">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                        </span>
                        <div>
                            <p class="font-bold">ยังไม่ได้เลือกพนักงาน</p>
                            <p class="mt-0.5 text-[13px] text-[#615d59]">ค้นหาด้วยชื่อ รหัส แผนก หรือตำแหน่ง</p>
                        </div>
                    </div>
                    <button type="button" data-open-employee-picker class="min-h-11 w-full sm:w-auto rounded-[8px] bg-[#0075de] px-4 font-semibold text-white hover:bg-[#005bab]">
                        <i class="fa-solid fa-users mr-2" aria-hidden="true"></i>เลือกพนักงาน
                    </button>
                </div>

                <div id="employeeSelectionSelected" class="<?= $selectedEmployee ? 'flex' : 'hidden' ?> flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-3 min-w-0">
                        <span data-selected-employee-initials class="w-12 h-12 shrink-0 rounded-full bg-[#eef6fd] text-[#0075de] flex items-center justify-center font-bold"><?= $selectedEmployee ? paymentEscape(employeeInitials($selectedEmployee['Name'])) : '' ?></span>
                        <div class="min-w-0">
                            <p data-selected-employee-name class="font-bold text-[16px] truncate"><?= $selectedEmployee ? paymentEscape($selectedEmployee['Name']) : '' ?></p>
                            <p class="text-[13px] text-[#615d59]"><span data-selected-employee-code><?= $selectedEmployee ? paymentEscape($selectedEmployee['Employee_id']) : '' ?></span></p>
                            <p class="mt-0.5 text-[13px] text-[#615d59] truncate">
                                <span data-selected-employee-department><?= $selectedEmployee ? paymentEscape($selectedEmployee['Depart_name'] ?: 'ไม่ระบุแผนก') : '' ?></span>
                                <span aria-hidden="true"> · </span>
                                <span data-selected-employee-position><?= $selectedEmployee ? paymentEscape($jobLabels[$selectedEmployee['jobtitle']] ?? $selectedEmployee['jobtitle']) : '' ?></span>
                            </p>
                        </div>
                    </div>
                    <button type="button" data-open-employee-picker class="min-h-11 w-full sm:w-auto rounded-[8px] border border-[#d5d3d0] bg-white px-4 font-semibold text-[#31302e] hover:bg-[#f6f5f4]">
                        <i class="fa-solid fa-user-pen mr-2" aria-hidden="true"></i>เปลี่ยนพนักงาน
                    </button>
                </div>

                <noscript>
                    <div class="mt-4 border-t border-[#e6e6e6] pt-4">
                        <label for="employeeFallback" class="block text-[14px] font-medium mb-2">เลือกพนักงาน</label>
                        <select id="employeeFallback" name="empid" required class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] bg-white px-3">
                            <option value="">เลือกพนักงาน</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?= paymentEscape($employee['Employee_id']) ?>" <?= (string) $employee['Employee_id'] === $requestedEmployeeId ? 'selected' : '' ?>>
                                    <?= paymentEscape($employee['Employee_id'] . ' - ' . $employee['Name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <style>
                        #paymentEmptyState { display: none !important; }
                        #paymentFields { display: block !important; }
                    </style>
                </noscript>
            </div>
        </section>

        <section class="rounded-lg border border-[#dedede] bg-white overflow-hidden" aria-labelledby="paymentDetailsTitle">
            <div class="border-b border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3.5 sm:px-5">
                <h2 id="paymentDetailsTitle" class="font-bold">รายละเอียดการจ่ายเงิน</h2>
            </div>

            <div id="paymentEmptyState" class="<?= $selectedEmployee ? 'hidden' : 'flex' ?> min-h-[250px] flex-col items-center justify-center px-5 py-10 text-center">
                <span class="w-12 h-12 rounded-full bg-[#f6f5f4] text-[#a39e98] flex items-center justify-center">
                    <i class="fa-solid fa-money-check-dollar text-lg" aria-hidden="true"></i>
                </span>
                <p class="mt-4 font-bold">กรุณาเลือกพนักงาน</p>
                <p class="mt-1 max-w-sm text-[14px] text-[#615d59]">เลือกพนักงานเพื่อแสดงเงินเดือนพื้นฐานและกรอกรายละเอียดการจ่าย</p>
                <button type="button" data-open-employee-picker class="mt-5 min-h-11 rounded-[8px] bg-[#0075de] px-4 font-semibold text-white">เลือกพนักงาน</button>
            </div>

            <div id="paymentFields" class="<?= $selectedEmployee ? 'block' : 'hidden' ?> p-4 sm:p-5 lg:p-6"
                data-payroll-calculator data-attendance-powered="1" data-attendance-source="attendance" data-base-salary="<?= $selectedHasSalary ? paymentEscape($selectedEmployee['basic_salary']) : '0' ?>"
                data-loan-balance="<?= $selectedEmployee ? paymentEscape($selectedEmployee['loan']) : '0' ?>"
                data-fund-balance="<?= $selectedEmployee ? paymentEscape($selectedEmployee['p_fund']) : '0' ?>"
                data-absence-enabled="<?= !empty($settings['absence_deduction_enabled']) ? '1' : '0' ?>"
                data-absence-mode="<?= paymentEscape($settings['absence_deduction_mode']) ?>"
                data-absence-rate="<?= paymentEscape($settings['absence_deduction_per_day']) ?>"
                data-absence-divisor="<?= paymentEscape($settings['absence_salary_divisor_days']) ?>"
                data-late-mode="<?= paymentEscape($settings['late_deduction_mode']) ?>"
                data-late-occurrence-rate="<?= paymentEscape($settings['late_deduction_per_occurrence']) ?>"
                data-late-interval-minutes="<?= paymentEscape($settings['late_interval_minutes']) ?>"
                data-late-interval-rate="<?= paymentEscape($settings['late_deduction_per_interval']) ?>"
                data-late-minute-rate="<?= paymentEscape($settings['late_deduction_per_minute']) ?>"
                data-late-rounding="<?= paymentEscape($settings['late_rounding_mode']) ?>"
                data-late-grace-minutes="<?= paymentEscape($settings['late_grace_minutes']) ?>"
                data-late-maximum="<?= paymentEscape($settings['max_late_deduction'] ?? '') ?>"
                data-allow-negative-net="<?= !empty($settings['allow_negative_net_salary']) ? '1' : '0' ?>">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="rounded-[9px] border border-[#e6e6e6] bg-[#f6f5f4] p-4"><p class="text-[13px] text-[#615d59]">พนักงาน</p><p data-payment-employee-name class="mt-1 font-bold"><?= $selectedEmployee ? paymentEscape($selectedEmployee['Name']) : '' ?></p><p class="text-[13px] text-[#615d59]">รหัส <span data-payment-employee-code><?= $selectedEmployee ? paymentEscape($selectedEmployee['Employee_id']) : '' ?></span></p></div>
                    <div class="rounded-[9px] border border-[#d9e9f8] bg-[#f7fbff] p-4"><p class="text-[13px] text-[#615d59]">เงินเดือนพื้นฐานสำหรับงวดนี้</p><p data-payment-employee-salary class="mt-1 text-[20px] font-extrabold <?= $selectedHasSalary?'text-[#0075de]':'text-amber-700' ?>"><?= $selectedHasSalary ? '฿'.number_format((float)$selectedEmployee['basic_salary'],2) : 'ยังไม่ได้กำหนด' ?></p></div>
                </div>
                <div data-selected-paid-warning class="<?= $selectedAlreadyPaid ? 'flex' : 'hidden' ?> mt-4 items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-800"><i class="fa-solid fa-circle-check mt-0.5"></i><span>พนักงานคนนี้ได้รับการจ่ายเงินเดือนสำหรับงวดที่เลือกแล้ว กรุณาเลือกงวดหรือพนักงานอื่น</span></div>
                <?php if ($selectedEmployee && !$selectedHasSalary): ?><div class="mt-4 flex flex-col gap-3 rounded-[8px] border border-amber-200 bg-amber-50 p-3 text-[13px] text-amber-900 sm:flex-row sm:items-center sm:justify-between"><span><i class="fa-solid fa-triangle-exclamation mr-2"></i>ยังไม่ได้กำหนดเงินเดือนพื้นฐานสำหรับงวดนี้</span><a href="/employee/setsalary?employee_id=<?= rawurlencode((string)$selectedEmployee['Employee_id']) ?>" class="inline-flex min-h-9 items-center justify-center rounded-md border border-amber-300 bg-white px-3 font-semibold">กำหนดเงินเดือน</a></div><?php endif; ?>

                <section class="mt-5" aria-labelledby="periodTitle"><h3 id="periodTitle" class="text-[15px] font-bold">งวดการจ่าย</h3>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label for="paymentYear" class="block text-[14px] font-medium mb-2">ปี (ค.ศ.)</label><input id="paymentYear" type="number" name="year" value="<?= paymentEscape($formYear) ?>" min="2000" max="2200" required data-payment-period-field class="block w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"></div>
                        <div><label for="paymentMonth" class="block text-[14px] font-medium mb-2">เดือน</label><select id="paymentMonth" name="month" required data-payment-period-field class="block w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"><?php foreach($months as $month): ?><option value="<?= paymentEscape($month) ?>" <?= $formMonth===$month?'selected':'' ?>><?= paymentEscape($thaiMonths[$month]) ?></option><?php endforeach; ?></select></div>
                    </div>
                </section>

                <div class="mt-6 grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,.65fr)]">
                    <div class="space-y-5">
                        <section class="rounded-[10px] border border-[#e6e6e6] p-4 sm:p-5">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><h3 class="font-bold">ข้อมูลการเข้างานประจำงวด</h3><p class="mt-1 text-[13px] text-[#615d59]">ดึงจากโมดูลเช็คชื่อและคำนวณใหม่โดยเซิร์ฟเวอร์เมื่อบันทึก</p></div><a href="/attendance/history?employee_id=<?= $selectedEmployee ? rawurlencode((string)$selectedEmployee['Employee_id']) : '' ?>&month=<?= rawurlencode($formMonth) ?>&year=<?= rawurlencode($formYear) ?>" class="inline-flex min-h-9 items-center rounded-md border border-[#d5d3d0] px-3 text-[12px] font-semibold hover:bg-[#f6f5f4]"><i class="fa-solid fa-calendar-check mr-2"></i>ดูประวัติ</a></div>
                            <input id="paymentAbsence" type="hidden" name="absence_days" value="<?= paymentEscape($formAbsence) ?>" data-payroll-input>
                            <input id="paymentLateCount" type="hidden" name="late_count" value="<?= paymentEscape($formLateCount) ?>" data-payroll-input>
                            <input id="paymentLateMinutes" type="hidden" name="late_minutes" value="<?= paymentEscape($formLateMinutes) ?>" data-payroll-input>
                            <div class="mt-4 grid grid-cols-2 overflow-hidden rounded-[8px] border border-[#e6e6e6] sm:grid-cols-4">
                                <div class="p-3 border-r border-b border-[#e6e6e6] sm:border-b-0"><p class="text-[12px] text-[#615d59]">มาทำงาน</p><p class="mt-1 font-bold"><?= (int)($selectedAttendanceMetrics['attendance_days'] ?? 0) ?> วัน</p></div>
                                <div class="p-3 border-b border-[#e6e6e6] sm:border-r sm:border-b-0"><p class="text-[12px] text-[#615d59]">ขาดงาน</p><p class="mt-1 font-bold text-red-700"><?= paymentEscape($formAbsence ?: '0') ?> วัน</p></div>
                                <div class="p-3 border-r border-[#e6e6e6]"><p class="text-[12px] text-[#615d59]">มาสาย</p><p class="mt-1 font-bold text-amber-700"><?= paymentEscape($formLateCount ?: '0') ?> ครั้ง</p></div>
                                <div class="p-3"><p class="text-[12px] text-[#615d59]">มาสายรวม</p><p class="mt-1 font-bold"><?= paymentEscape($formLateMinutes ?: '0') ?> นาที</p></div>
                            </div>
                            <p class="mt-3 text-[12px] text-[#77716c]"><i class="fa-solid fa-shield-halved mr-1"></i>ไม่รวมวันอนาคต วันหยุดประจำสัปดาห์ และวันหยุดที่ลงทะเบียนไว้</p>
                        </section>

                        <section class="rounded-[10px] border border-[#e6e6e6] p-4 sm:p-5">
                            <h3 class="font-bold text-emerald-700"><i class="fa-solid fa-circle-plus mr-2"></i>รายรับเพิ่มเติม</h3>
                            <dl class="mt-4 space-y-3 text-[13px]">
                                <div class="flex justify-between gap-4"><dt>ค่ารักษาพยาบาล <span class="text-[#77716c]">(3% ของฐาน · อัตโนมัติ)</span></dt><dd data-auto-medical class="font-semibold">฿0.00</dd></div>
                                <div class="flex justify-between gap-4"><dt>ค่าที่พัก <span class="text-[#77716c]">(8% ของฐาน · อัตโนมัติ)</span></dt><dd data-auto-housing class="font-semibold">฿0.00</dd></div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-3"><div><label for="paymentOvertime" class="block font-medium mb-1.5">ล่วงเวลา (ชั่วโมง × ฿300)</label><input id="paymentOvertime" type="number" step="0.5" name="overtime" value="<?= paymentEscape($formOvertime) ?>" min="0" data-payroll-input class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"></div><dd data-auto-overtime class="pb-3 font-semibold">฿0.00</dd></div>
                            </dl>
                            <div class="mt-4 border-t border-[#e6e6e6] pt-4"><div class="flex items-center justify-between"><h4 class="text-[13px] font-bold">รายการเพิ่มเอง</h4><button type="button" data-add-adjustment="addition" class="min-h-10 rounded-[7px] border border-[#b7d6f1] px-3 text-[13px] font-semibold text-[#0075de]"><i class="fa-solid fa-plus mr-1"></i>เพิ่มรายการ</button></div>
                                <div data-adjustment-list="addition" class="mt-3 space-y-3"><?php foreach((array)($postedAdditions['name']??[]) as $index=>$name): ?><div data-adjustment-row class="grid grid-cols-1 gap-2 rounded-[8px] bg-[#f6f5f4] p-3 sm:grid-cols-[minmax(0,1fr)_140px_44px]"><input name="additions[name][]" value="<?= paymentEscape($name) ?>" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="ชื่อรายการ"><input type="number" min="0" step="0.01" name="additions[amount][]" value="<?= paymentEscape($postedAdditions['amount'][$index]??'') ?>" data-adjustment-amount data-payroll-input class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="จำนวนเงิน"><button type="button" data-remove-adjustment class="min-h-10 rounded-[7px] text-red-600" aria-label="ลบ"><i class="fa-solid fa-trash"></i></button><input name="additions[note][]" value="<?= paymentEscape($postedAdditions['note'][$index]??'') ?>" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3 sm:col-span-3" placeholder="หมายเหตุ (ไม่บังคับ)"></div><?php endforeach; ?></div>
                            </div>
                        </section>

                        <section class="rounded-[10px] border border-[#e6e6e6] p-4 sm:p-5">
                            <h3 class="font-bold text-red-700"><i class="fa-solid fa-circle-minus mr-2"></i>รายการหัก</h3>
                            <dl class="mt-4 space-y-3 text-[13px]">
                                <div class="flex justify-between gap-4"><dt>หักเงินยืม <span class="text-[#77716c]">(5% ของยอดคงเหลือ)</span></dt><dd data-auto-loan class="font-semibold">-฿0.00</dd></div>
                                <div class="flex justify-between gap-4"><dt>กองทุนสำรองเลี้ยงชีพ <span class="text-[#77716c]">(2.5% ของฐาน)</span></dt><dd data-auto-fund class="font-semibold">-฿0.00</dd></div>
                                <div class="flex justify-between gap-4"><dt>ขาดงาน <span class="text-[#77716c]">(อัตโนมัติ)</span></dt><dd data-auto-absence class="font-semibold">-฿0.00</dd></div>
                                <div class="flex justify-between gap-4"><dt>มาสาย <span class="text-[#77716c]">(อัตโนมัติ)</span></dt><dd data-auto-late class="font-semibold">-฿0.00</dd></div>
                            </dl><div class="mt-3 space-y-1 text-[12px] text-[#77716c]"><p data-absence-formula></p><p data-late-formula></p></div>
                            <div class="mt-4 border-t border-[#e6e6e6] pt-4"><div class="flex items-center justify-between"><h4 class="text-[13px] font-bold">รายการหักเอง</h4><button type="button" data-add-adjustment="deduction" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3 text-[13px] font-semibold"><i class="fa-solid fa-plus mr-1"></i>เพิ่มรายการ</button></div>
                                <div data-adjustment-list="deduction" class="mt-3 space-y-3"><?php foreach((array)($postedDeductions['name']??[]) as $index=>$name): ?><div data-adjustment-row class="grid grid-cols-1 gap-2 rounded-[8px] bg-[#f6f5f4] p-3 sm:grid-cols-[minmax(0,1fr)_140px_44px]"><input name="deductions[name][]" value="<?= paymentEscape($name) ?>" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="ชื่อรายการ"><input type="number" min="0" step="0.01" name="deductions[amount][]" value="<?= paymentEscape($postedDeductions['amount'][$index]??'') ?>" data-adjustment-amount data-payroll-input class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3" placeholder="จำนวนเงิน"><button type="button" data-remove-adjustment class="min-h-10 rounded-[7px] text-red-600" aria-label="ลบ"><i class="fa-solid fa-trash"></i></button><input name="deductions[note][]" value="<?= paymentEscape($postedDeductions['note'][$index]??'') ?>" class="min-h-10 rounded-[7px] border border-[#e6e6e6] px-3 sm:col-span-3" placeholder="หมายเหตุ (ไม่บังคับ)"></div><?php endforeach; ?></div>
                            </div>
                        </section>

                        <section class="rounded-[10px] border border-[#e6e6e6] p-4 sm:p-5"><label for="paymentNote" class="block text-[14px] font-bold mb-2">หมายเหตุการจ่าย</label><textarea id="paymentNote" name="payment_note" maxlength="500" rows="3" class="w-full rounded-[7px] border border-[#e6e6e6] p-3" placeholder="ระบุเหตุผลหรือข้อมูลอ้างอิง (ถ้ามี)"><?= paymentEscape($formNote) ?></textarea></section>
                    </div>

                    <aside class="xl:sticky xl:top-6 xl:self-start rounded-[10px] border border-[#d9e9f8] bg-[#f7fbff] p-4 sm:p-5">
                        <h3 class="font-bold">สรุปการคำนวณ</h3><p class="mt-1 text-[12px] text-[#615d59]">อัปเดตทันที และระบบจะคำนวณซ้ำก่อนบันทึก</p>
                        <dl class="mt-5 space-y-3 text-[14px]"><div class="flex justify-between"><dt>เงินเดือนพื้นฐาน</dt><dd data-summary-base class="font-semibold">฿0.00</dd></div><div class="flex justify-between text-emerald-700"><dt>รายรับเพิ่มเติม</dt><dd data-summary-additions class="font-semibold">+฿0.00</dd></div><div class="flex justify-between text-red-700"><dt>รายการหัก</dt><dd data-summary-deductions class="font-semibold">-฿0.00</dd></div><div class="border-t border-[#b7d6f1] pt-4"><dt class="font-bold">ยอดสุทธิ</dt><dd data-summary-net class="mt-1 text-[28px] font-extrabold text-[#0075de]">฿0.00</dd></div></dl>
                        <p data-negative-net class="hidden mt-3 rounded-[7px] bg-red-50 p-3 text-[12px] text-red-700">ยอดสุทธิติดลบ กรุณาตรวจสอบรายการหัก</p>
                        <button type="submit" data-payment-submit <?= (!$selectedEmployee||$selectedAlreadyPaid||!$selectedHasSalary)?'disabled':'' ?> class="mt-5 min-h-12 w-full rounded-[8px] bg-[#0075de] px-5 font-semibold text-white disabled:bg-[#a9c9e6]"><i class="fa-solid fa-check mr-2"></i><span data-payment-submit-label><?= $selectedAlreadyPaid?'จ่ายแล้วสำหรับงวดนี้':(!$selectedHasSalary?'ต้องกำหนดเงินเดือนก่อน':'ตรวจสอบและยืนยัน') ?></span></button>
                        <p class="mt-3 text-center text-[11px] text-[#615d59]"><i class="fa-solid fa-shield-halved mr-1"></i>เก็บ snapshot เพื่อการตรวจสอบย้อนหลัง</p>
                    </aside>
                </div>
                <input type="hidden" name="confirmed" value="0" data-payment-confirmed><noscript><input type="hidden" name="confirmed" value="1"></noscript>
            </div>
        </section>
    </form>

    <div id="paymentConfirmationModal" class="fixed inset-0 z-[90] hidden items-end justify-center sm:items-center sm:p-4" aria-hidden="true">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" data-close-payment-confirmation></div>
        <section class="relative w-full rounded-t-[16px] bg-white shadow-2xl sm:max-w-md sm:rounded-[16px]" role="dialog" aria-modal="true" aria-labelledby="paymentConfirmationTitle">
            <header class="flex items-start justify-between border-b border-[#e6e6e6] p-4 sm:p-5"><div><h2 id="paymentConfirmationTitle" class="text-[19px] font-bold">ยืนยันการจ่ายเงินเดือน</h2><p class="mt-1 text-[13px] text-[#615d59]">โปรดตรวจสอบยอดก่อนบันทึก</p></div><button type="button" data-close-payment-confirmation class="h-11 w-11 rounded-[8px] hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></header>
            <div class="p-4 sm:p-5"><p data-confirm-employee class="font-bold"></p><p data-confirm-period class="mt-1 text-[13px] text-[#615d59]"></p><dl class="mt-5 space-y-3 text-[14px]"><div class="flex justify-between"><dt>เงินเดือนพื้นฐาน</dt><dd data-confirm-base></dd></div><div class="flex justify-between text-emerald-700"><dt>รายรับเพิ่มเติม</dt><dd data-confirm-additions></dd></div><div class="flex justify-between text-red-700"><dt>รายการหัก</dt><dd data-confirm-deductions></dd></div><div class="flex justify-between border-t border-[#e6e6e6] pt-4 text-[18px] font-bold"><dt>ยอดสุทธิ</dt><dd data-confirm-net class="text-[#0075de]"></dd></div></dl></div>
            <footer class="flex flex-col-reverse gap-2 border-t border-[#e6e6e6] p-4 sm:flex-row sm:justify-end"><button type="button" data-close-payment-confirmation class="min-h-11 rounded-[8px] border border-[#d5d3d0] px-4 font-semibold">กลับไปแก้ไข</button><button type="button" data-confirm-payment class="min-h-11 rounded-[8px] bg-[#0075de] px-4 font-semibold text-white"><i class="fa-solid fa-lock mr-2"></i>ยืนยันและบันทึก</button></footer>
        </section>
    </div>

    <?php
    $employeePickerEmployees = $employees;
    $employeePickerDepartments = $departments;
    $employeePickerSelectedId = $selectedEmployee ? (string) $selectedEmployee['Employee_id'] : '';
    $employeePickerJobLabels = $jobLabels;
    $employeePickerContext = 'payment';
    $employeePickerCurrentPeriodKey = $currentPeriodKey;
    require __DIR__ . '/components/employee_picker.php';
    ?>
</div>
