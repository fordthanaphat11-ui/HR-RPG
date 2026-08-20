<?php
$title = "ภาพรวมระบบ - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/salary.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$monthNames = [
    'january' => 'มกราคม', 'february' => 'กุมภาพันธ์', 'march' => 'มีนาคม',
    'april' => 'เมษายน', 'may' => 'พฤษภาคม', 'june' => 'มิถุนายน',
    'july' => 'กรกฎาคม', 'august' => 'สิงหาคม', 'september' => 'กันยายน',
    'october' => 'ตุลาคม', 'november' => 'พฤศจิกายน', 'december' => 'ธันวาคม',
];
$monthShortNames = [
    'january' => 'ม.ค.', 'february' => 'ก.พ.', 'march' => 'มี.ค.',
    'april' => 'เม.ย.', 'may' => 'พ.ค.', 'june' => 'มิ.ย.',
    'july' => 'ก.ค.', 'august' => 'ส.ค.', 'september' => 'ก.ย.',
    'october' => 'ต.ค.', 'november' => 'พ.ย.', 'december' => 'ธ.ค.',
];
$monthNumbers = array_combine(array_keys($monthNames), range(1, 12));
$numberToMonth = array_flip($monthNumbers);

$currentMonth = strtolower(date('F'));
$selectedMonth = strtolower($_GET['month'] ?? $currentMonth);
if (!isset($monthNames[$selectedMonth])) {
    $selectedMonth = $currentMonth;
}
$selectedYear = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT) ?: (int) date('Y');
if ($selectedYear < 2000 || $selectedYear > 2100) {
    $selectedYear = (int) date('Y');
}
$escapedMonth = mysqli_real_escape_string($connection, $selectedMonth);

function dashboardScalar(mysqli $connection, string $sql): float
{
    $result = mysqli_query($connection, $sql);
    if (!$result) return 0;
    $row = mysqli_fetch_row($result);
    return (float) ($row[0] ?? 0);
}

$totalEmployees = (int) dashboardScalar($connection, "SELECT COUNT(*) FROM employee");
$totalDepartments = (int) dashboardScalar($connection, "SELECT COUNT(*) FROM department");
$todaySalaryDate = date('Y-m-d');
$basePayroll = dashboardScalar($connection, "SELECT COALESCE(SUM((SELECT es.salary_amount FROM employee_salaries es WHERE es.employee_id=e.Employee_id AND es.effective_from<='{$todaySalaryDate}' ORDER BY es.effective_from DESC,es.id DESC LIMIT 1)),0) FROM employee e");
$averageSalary = dashboardScalar($connection, "SELECT COALESCE(AVG((SELECT es.salary_amount FROM employee_salaries es WHERE es.employee_id=e.Employee_id AND es.effective_from<='{$todaySalaryDate}' ORDER BY es.effective_from DESC,es.id DESC LIMIT 1)),0) FROM employee e");

$paidEmployees = 0;
$paidAmount = 0.0;
$paidResult = mysqli_query($connection, "SELECT COUNT(DISTINCT p.emp_id) AS paid_count, COALESCE(SUM(COALESCE(s.net_salary,p.total_pay)), 0) AS paid_total FROM payment p LEFT JOIN payment_snapshots s ON s.pay_no=p.pay_no WHERE p.`year` = $selectedYear AND LOWER(p.`month`) = '$escapedMonth'");
if ($paidResult && $paidRow = mysqli_fetch_assoc($paidResult)) {
    $paidEmployees = (int) $paidRow['paid_count'];
    $paidAmount = (float) $paidRow['paid_total'];
}

$pendingEmployees = max($totalEmployees - $paidEmployees, 0);
$selectedSalaryDate = salaryPeriodDate($selectedYear, $selectedMonth);
$pendingBasePayroll = dashboardScalar($connection, "SELECT COALESCE(SUM((SELECT es.salary_amount FROM employee_salaries es WHERE es.employee_id=e.Employee_id AND es.effective_from<='{$selectedSalaryDate}' ORDER BY es.effective_from DESC,es.id DESC LIMIT 1)),0) FROM employee e WHERE NOT EXISTS (SELECT 1 FROM payment p WHERE p.emp_id=e.Employee_id AND p.`year`=$selectedYear AND LOWER(p.`month`)='$escapedMonth')");
$paymentProgress = $totalEmployees > 0 ? min(100, round(($paidEmployees / $totalEmployees) * 100)) : 0;

$departmentRows = $departmentLabels = $departmentCounts = [];
$departmentResult = mysqli_query($connection, "SELECT d.Depart_id, d.Depart_name, COUNT(e.Employee_id) AS employee_count FROM department d LEFT JOIN employee e ON e.Depart_id = d.Depart_id GROUP BY d.Depart_id, d.Depart_name ORDER BY employee_count DESC, d.Depart_name ASC");
if ($departmentResult) {
    while ($row = mysqli_fetch_assoc($departmentResult)) {
        $departmentRows[] = $row;
        $departmentLabels[] = $row['Depart_name'];
        $departmentCounts[] = (int) $row['employee_count'];
    }
}

$payrollByPeriod = [];
$availableYears = [(int) date('Y'), $selectedYear];
$trendResult = mysqli_query($connection, "SELECT p.`year`, LOWER(p.`month`) AS payment_month, COALESCE(SUM(COALESCE(s.net_salary,p.total_pay)), 0) AS payroll_total FROM payment p LEFT JOIN payment_snapshots s ON s.pay_no=p.pay_no GROUP BY p.`year`, LOWER(p.`month`)");
if ($trendResult) {
    while ($row = mysqli_fetch_assoc($trendResult)) {
        $year = (int) $row['year'];
        $month = $row['payment_month'];
        $availableYears[] = $year;
        if (isset($monthNumbers[$month])) {
            $payrollByPeriod[sprintf('%04d-%02d', $year, $monthNumbers[$month])] = (float) $row['payroll_total'];
        }
    }
}
$availableYears = array_values(array_unique($availableYears));
rsort($availableYears);

$payrollLabels = $payrollTotals = [];
$selectedDate = new DateTimeImmutable(sprintf('%04d-%02d-01', $selectedYear, $monthNumbers[$selectedMonth]));
for ($offset = 5; $offset >= 0; $offset--) {
    $period = $selectedDate->modify("-$offset months");
    $englishMonth = $numberToMonth[(int) $period->format('n')];
    $payrollLabels[] = $monthShortNames[$englishMonth] . ' ' . ((int) $period->format('Y') + 543);
    $payrollTotals[] = $payrollByPeriod[$period->format('Y-m')] ?? 0;
}
$hasPayrollTrend = array_sum($payrollTotals) > 0;

$recentPayments = [];
$recentResult = mysqli_query($connection, "SELECT p.pay_no, p.emp_id, p.`year`, LOWER(p.`month`) AS payment_month, COALESCE(s.net_salary,p.total_pay) AS total_pay, e.Name, d.Depart_name FROM payment p LEFT JOIN payment_snapshots s ON s.pay_no=p.pay_no INNER JOIN employee e ON e.Employee_id = p.emp_id LEFT JOIN department d ON d.Depart_id = e.Depart_id ORDER BY p.`year` DESC, FIELD(LOWER(p.`month`), 'december','november','october','september','august','july','june','may','april','march','february','january') ASC, p.pay_no DESC LIMIT 5");
if ($recentResult) {
    while ($row = mysqli_fetch_assoc($recentResult)) $recentPayments[] = $row;
}

$periodLabel = $monthNames[$selectedMonth] . ' ' . ($selectedYear + 543);
$trendTotal = array_sum($payrollTotals);
$chartJsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>

<div class="space-y-4 min-w-0" data-dashboard-page>
    <header class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-xl font-semibold text-[#202223]">ภาพรวมระบบ</h1>
            <p class="text-sm text-[#6d7175] mt-0.5">ภาพรวมข้อมูลพนักงานและการจ่ายเงินเดือน</p>
        </div>
        <form method="get" action="/" class="grid grid-cols-2 gap-2 w-full sm:w-auto" aria-label="เลือกช่วงเวลาของภาพรวม">
            <label class="sr-only" for="dashboardMonth">เดือน</label>
            <select id="dashboardMonth" name="month" class="dashboard-period-select min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium">
                <?php foreach ($monthNames as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $selectedMonth === $value ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="dashboardYear">ปี</label>
            <select id="dashboardYear" name="year" class="dashboard-period-select min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium">
                <?php foreach ($availableYears as $year): ?>
                    <option value="<?= $year ?>" <?= $selectedYear === $year ? 'selected' : '' ?>>พ.ศ. <?= $year + 543 ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </header>

    <section aria-labelledby="summaryHeading">
        <h2 id="summaryHeading" class="sr-only">ข้อมูลสรุป <?= htmlspecialchars($periodLabel) ?></h2>
        <div class="sm:hidden rounded-[12px] border border-[#e6e6e6] bg-white divide-y divide-[#e6e6e6] overflow-hidden">
            <?php
            $mobileStats = [
                ['fa-users', 'text-[#5c5f62] bg-[#f1f1f1]', 'พนักงานทั้งหมด', number_format($totalEmployees) . ' คน'],
                ['fa-building', 'text-[#5c5f62] bg-[#f1f1f1]', 'แผนกทั้งหมด', number_format($totalDepartments) . ' แผนก'],
                ['fa-coins', 'text-[#5c5f62] bg-[#f1f1f1]', 'ฐานเงินเดือนรวม', '฿' . number_format($basePayroll)],
                ['fa-clock', 'text-[#a56409] bg-[#fff6e8]', 'รอจ่าย ' . $periodLabel, number_format($pendingEmployees) . ' คน'],
            ];
            foreach ($mobileStats as [$icon, $iconStyle, $label, $value]): ?>
                <div class="flex items-center gap-3 min-h-[66px] px-4 py-3">
                    <span class="w-9 h-9 shrink-0 rounded-[8px] flex items-center justify-center <?= $iconStyle ?>"><i class="fa-solid <?= $icon ?>" aria-hidden="true"></i></span>
                    <span class="flex-1 min-w-0 text-[14px] text-[#31302e] break-words"><?= htmlspecialchars($label) ?></span>
                    <strong class="text-[16px] whitespace-nowrap"><?= htmlspecialchars($value) ?></strong>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="hidden sm:grid sm:grid-cols-2 xl:grid-cols-4 gap-3">
            <?php
            $desktopStats = [
                ['พนักงานทั้งหมด', number_format($totalEmployees), 'คน', 'พนักงานในระบบทั้งหมด', 'fa-users', 'bg-[#f1f1f1] text-[#5c5f62]'],
                ['แผนกทั้งหมด', number_format($totalDepartments), 'แผนก', 'โครงสร้างหน่วยงานปัจจุบัน', 'fa-building', 'bg-[#f1f1f1] text-[#5c5f62]'],
                ['ฐานเงินเดือนรวม', '฿' . number_format($basePayroll), '', 'เฉลี่ย ฿' . number_format($averageSalary) . ' ต่อคน', 'fa-coins', 'bg-[#f1f1f1] text-[#5c5f62]'],
                ['รอจ่ายเดือนนี้', number_format($pendingEmployees), 'คน', $periodLabel . ' · จ่ายแล้ว ' . number_format($paidEmployees) . ' คน', 'fa-clock', 'bg-[#f1f1f1] text-[#5c5f62]'],
            ];
            foreach ($desktopStats as [$label, $value, $unit, $description, $icon, $iconStyle]): ?>
                <article class="rounded-lg border border-[#dedede] bg-white p-4">
                    <div class="flex items-start justify-between gap-3"><p class="text-xs font-medium text-[#6d7175]"><?= htmlspecialchars($label) ?></p><span class="w-7 h-7 rounded-md flex items-center justify-center text-xs <?= $iconStyle ?>"><i class="fa-solid <?= $icon ?>"></i></span></div>
                    <p class="mt-3 text-xl font-semibold leading-none"><?= htmlspecialchars($value) ?> <?php if ($unit): ?><span class="text-xs font-normal text-[#6d7175]"><?= htmlspecialchars($unit) ?></span><?php endif; ?></p>
                    <p class="mt-2 text-xs text-[#6d7175]"><?= htmlspecialchars($description) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
        <section class="dashboard-chart-card xl:col-span-2 min-w-0 overflow-hidden rounded-[12px] border border-[#e6e6e6] bg-white p-4 sm:p-5" aria-labelledby="payrollTrendTitle">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div><h2 id="payrollTrendTitle" class="text-[17px] font-bold">แนวโน้มค่าใช้จ่ายเงินเดือน</h2><p class="text-[13px] sm:text-[14px] text-[#615d59] mt-0.5">ยอดจ่ายเงินเดือนย้อนหลัง 6 เดือน</p></div>
                <div class="sm:text-right"><p class="text-[12px] text-[#615d59]">ยอดรวม 6 เดือน</p><p class="text-[20px] font-bold">฿<?= number_format($trendTotal) ?></p></div>
            </div>
            <?php if ($hasPayrollTrend): ?>
                <div id="payrollTrendChart" class="ct-chart dashboard-line-chart h-[250px] sm:h-[290px] mt-4" role="img" aria-label="กราฟแนวโน้มยอดจ่ายเงินเดือนย้อนหลัง 6 เดือน"></div>
            <?php else: ?>
                <div class="h-[250px] sm:h-[290px] mt-4 rounded-[10px] bg-[#f6f5f4] flex flex-col items-center justify-center text-center p-5"><i class="fa-solid fa-chart-line text-[28px] text-[#a39e98]"></i><p class="font-bold mt-3">ยังไม่มีข้อมูลการจ่ายเงินเดือน</p><p class="text-[13px] text-[#615d59] mt-1">ข้อมูลกราฟจะแสดงเมื่อมีการบันทึกการจ่ายเงิน</p></div>
            <?php endif; ?>
        </section>

        <section class="dashboard-chart-card min-w-0 overflow-hidden rounded-[12px] border border-[#e6e6e6] bg-white p-4 sm:p-5" aria-labelledby="paymentStatusTitle">
            <div><h2 id="paymentStatusTitle" class="text-[17px] font-bold">สถานะการจ่ายเงินเดือน</h2><p class="text-[13px] sm:text-[14px] text-[#615d59] mt-0.5"><?= htmlspecialchars($periodLabel) ?></p></div>
            <?php if ($totalEmployees > 0): ?>
                <div class="relative h-[200px] mt-3"><div id="paymentStatusChart" class="ct-chart h-full" role="img" aria-label="กราฟสัดส่วนพนักงานที่จ่ายเงินแล้วและรอจ่าย"></div><div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center"><strong class="text-[25px]"><?= $paymentProgress ?>%</strong><span class="text-[12px] text-[#615d59]">ดำเนินการแล้ว</span></div></div>
                <div class="grid grid-cols-2 gap-2 mt-2">
                    <div class="rounded-[8px] bg-[#ecf8f2] p-3"><p class="text-[12px] text-[#527568]"><span class="inline-block w-2 h-2 rounded-full bg-[#1f8f68] mr-1"></span>จ่ายแล้ว</p><p class="text-[18px] font-bold mt-1"><?= number_format($paidEmployees) ?> คน</p></div>
                    <div class="rounded-[8px] bg-[#fff6e8] p-3"><p class="text-[12px] text-[#806539]"><span class="inline-block w-2 h-2 rounded-full bg-[#d49a31] mr-1"></span>รอจ่าย</p><p class="text-[18px] font-bold mt-1"><?= number_format($pendingEmployees) ?> คน</p></div>
                </div>
            <?php else: ?>
                <div class="h-[260px] mt-4 rounded-[10px] bg-[#f6f5f4] flex flex-col items-center justify-center text-center p-5"><i class="fa-solid fa-chart-pie text-[28px] text-[#a39e98]"></i><p class="font-bold mt-3">ยังไม่มีข้อมูลพนักงาน</p></div>
            <?php endif; ?>
        </section>
    </div>

    <section class="dashboard-chart-card min-w-0 overflow-hidden rounded-[12px] border border-[#e6e6e6] bg-white p-4 sm:p-5" aria-labelledby="departmentChartTitle">
        <div class="flex items-start justify-between gap-3"><div><h2 id="departmentChartTitle" class="text-[17px] font-bold">พนักงานแยกตามแผนก</h2><p class="text-[13px] sm:text-[14px] text-[#615d59] mt-0.5">จำนวนพนักงานในแต่ละแผนก เรียงจากมากไปน้อย</p></div><a href="/department" class="hidden sm:inline-flex text-[14px] font-medium text-[#0075de] whitespace-nowrap">ดูแผนกทั้งหมด <i class="fa-solid fa-arrow-right ml-1 mt-1"></i></a></div>
        <?php if ($departmentRows): ?>
            <div id="departmentChart" class="ct-chart dashboard-department-chart w-full mt-4" style="height: <?= max(260, count($departmentRows) * 48) ?>px" role="img" aria-label="กราฟจำนวนพนักงานแยกตามแผนก"></div>
            <a href="/department" class="sm:hidden min-h-11 mt-3 rounded-[8px] bg-[#f6f5f4] text-[14px] font-medium flex items-center justify-center">ดูแผนกทั้งหมด <i class="fa-solid fa-arrow-right ml-2"></i></a>
        <?php else: ?>
            <div class="h-[230px] mt-4 rounded-[10px] bg-[#f6f5f4] flex flex-col items-center justify-center text-center p-5"><i class="fa-solid fa-building text-[28px] text-[#a39e98]"></i><p class="font-bold mt-3">ยังไม่มีข้อมูลแผนก</p></div>
        <?php endif; ?>
    </section>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <section class="rounded-[12px] border border-[#e6e6e6] bg-white p-4 sm:p-5" aria-labelledby="payrollSummaryTitle">
            <div class="flex items-center justify-between gap-3"><div><h2 id="payrollSummaryTitle" class="text-[17px] font-bold">สรุปเงินเดือนประจำเดือน</h2><p class="text-[13px] text-[#615d59] mt-0.5"><?= htmlspecialchars($periodLabel) ?></p></div><span class="px-2.5 py-1 rounded-full text-[12px] font-semibold <?= $pendingEmployees === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' ?>"><?= $pendingEmployees === 0 ? 'จ่ายครบแล้ว' : 'กำลังดำเนินการ' ?></span></div>
            <dl class="mt-5 space-y-3 text-[14px]">
                <div class="flex justify-between gap-4"><dt class="text-[#615d59]">ฐานเงินเดือนทั้งหมด</dt><dd class="font-bold">฿<?= number_format($basePayroll) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-[#615d59]">ยอดจ่ายจริงแล้ว</dt><dd class="font-bold text-[#167a56]">฿<?= number_format($paidAmount) ?></dd></div>
                <div class="flex justify-between gap-4"><dt class="text-[#615d59]">ฐานเงินเดือนที่ยังรอจ่าย</dt><dd class="font-bold text-[#a56409]">฿<?= number_format($pendingBasePayroll) ?></dd></div>
            </dl>
            <div class="mt-5 pt-4 border-t border-[#e6e6e6]">
                <div class="flex justify-between gap-3 text-[13px] mb-2"><span class="font-medium">ดำเนินการจ่ายเงินเดือน</span><strong><?= $paymentProgress ?>%</strong></div>
                <div class="h-2.5 rounded-full bg-[#eeecea] overflow-hidden" role="progressbar" aria-valuenow="<?= $paymentProgress ?>" aria-valuemin="0" aria-valuemax="100"><div class="h-full rounded-full bg-[#0075de] transition-all" style="width: <?= $paymentProgress ?>%"></div></div>
                <p class="text-[12px] text-[#615d59] mt-2">จ่ายแล้ว <?= number_format($paidEmployees) ?> จาก <?= number_format($totalEmployees) ?> คน</p>
            </div>
        </section>

        <section class="rounded-[12px] border border-[#e6e6e6] bg-white overflow-hidden" aria-labelledby="recentPaymentsTitle">
            <div class="p-4 sm:p-5 border-b border-[#e6e6e6] flex items-center justify-between gap-3"><div><h2 id="recentPaymentsTitle" class="text-[17px] font-bold">รายการจ่ายเงินล่าสุด</h2><p class="text-[13px] text-[#615d59] mt-0.5">5 รายการล่าสุดในระบบ</p></div><a href="/employee/payhistory" class="text-[14px] font-medium text-[#0075de] whitespace-nowrap">ดูทั้งหมด <i class="fa-solid fa-arrow-right ml-1"></i></a></div>
            <?php if ($recentPayments): ?>
                <div class="divide-y divide-[#e6e6e6]">
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center gap-3 px-4 sm:px-5 py-3 min-w-0">
                            <span class="w-9 h-9 shrink-0 rounded-full bg-[#eef6fd] text-[#0075de] flex items-center justify-center"><i class="fa-solid fa-user text-[13px]"></i></span>
                            <div class="flex-1 min-w-0"><p class="text-[14px] font-bold truncate"><?= htmlspecialchars($payment['Name']) ?></p><p class="text-[12px] text-[#615d59] truncate"><?= htmlspecialchars($payment['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · <?= htmlspecialchars($monthShortNames[$payment['payment_month']] ?? $payment['payment_month']) ?> <?= (int) $payment['year'] + 543 ?></p></div>
                            <div class="text-right shrink-0"><p class="text-[14px] font-bold">฿<?= number_format($payment['total_pay']) ?></p><span class="inline-flex mt-0.5 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">จ่ายแล้ว</span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="py-10 px-5 text-center"><i class="fa-solid fa-receipt text-[26px] text-[#a39e98]"></i><p class="font-bold mt-3">ยังไม่มีรายการจ่ายเงิน</p><p class="text-[13px] text-[#615d59] mt-1">รายการล่าสุดจะแสดงที่นี่เมื่อมีการบันทึกการจ่ายเงิน</p></div>
            <?php endif; ?>
        </section>
    </div>

    <section class="rounded-[12px] border border-[#e6e6e6] bg-[#f6f5f4] p-4 sm:p-5" aria-labelledby="quickActionsTitle">
        <h2 id="quickActionsTitle" class="text-[16px] font-bold">การดำเนินการด่วน</h2>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2 sm:gap-3 mt-3">
            <a href="/employee/add" class="min-h-12 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium flex items-center justify-center gap-2 hover:border-[#0075de] hover:text-[#0075de]"><i class="fa-solid fa-user-plus"></i><span>เพิ่มพนักงาน</span></a>
            <a href="/employee/payment" class="min-h-12 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium flex items-center justify-center gap-2 hover:border-[#0075de] hover:text-[#0075de]"><i class="fa-solid fa-calculator"></i><span>จ่ายเงินเดือน</span></a>
            <a href="/employee/setsalary" class="min-h-12 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium flex items-center justify-center gap-2 hover:border-[#0075de] hover:text-[#0075de]"><i class="fa-solid fa-coins"></i><span>ตั้งค่าเงินเดือน</span></a>
            <a href="/employee/payhistory" class="min-h-12 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px] font-medium flex items-center justify-center gap-2 hover:border-[#0075de] hover:text-[#0075de]"><i class="fa-solid fa-clock-rotate-left"></i><span>ดูประวัติการจ่าย</span></a>
        </div>
    </section>
</div>

<style>
.dashboard-chart-card{position:relative}.dashboard-chart-card .ct-label{color:#77716c;fill:#77716c;font-family:'LINESeedSansTH',sans-serif;font-size:11px}.dashboard-chart-card .ct-grid{stroke:#e8e6e3;stroke-width:1px;stroke-dasharray:3px}.dashboard-line-chart .ct-series-a .ct-line{stroke:#0075de;stroke-width:2.5px}.dashboard-line-chart .ct-series-a .ct-point{stroke:#0075de;stroke-width:7px;stroke-linecap:round}.dashboard-line-chart .ct-series-a .ct-area{fill:#0075de;fill-opacity:.08}.dashboard-department-chart .ct-series-a .ct-bar{stroke:#4d9fe8;stroke-width:16px;stroke-linecap:round}#paymentStatusChart .ct-series-a .ct-slice-donut{stroke:#1f8f68}#paymentStatusChart .ct-series-b .ct-slice-donut{stroke:#d49a31}#paymentStatusChart .ct-slice-donut{stroke-width:18px!important}.dashboard-chart-tooltip{position:fixed;z-index:100;max-width:210px;pointer-events:none;border-radius:7px;background:#202124;color:#fff;padding:7px 10px;font-family:'LINESeedSansTH',sans-serif;font-size:12px;line-height:1.4;box-shadow:0 8px 24px rgba(0,0,0,.16);opacity:0;transform:translateY(4px);transition:opacity .12s,transform .12s}.dashboard-chart-tooltip.is-visible{opacity:1;transform:translateY(0)}@media(max-width:480px){.dashboard-chart-card .ct-label{font-size:10px}.dashboard-department-chart .ct-series-a .ct-bar{stroke-width:13px}}
</style>

<script type="application/json" id="dashboard-chart-data"><?php
echo json_encode([
    'payrollLabels' => $payrollLabels,
    'payrollTotals' => $payrollTotals,
    'departmentLabels' => $departmentLabels,
    'departmentCounts' => $departmentCounts,
    'paidEmployees' => $paidEmployees,
    'pendingEmployees' => $pendingEmployees,
], $chartJsonFlags);
?></script>
