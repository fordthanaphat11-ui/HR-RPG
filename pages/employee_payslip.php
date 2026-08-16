<?php
$title = "สลิปเงินเดือน - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$result = null;
$payslips = [];
$thaiMonths = ['january' => 'มกราคม', 'february' => 'กุมภาพันธ์', 'march' => 'มีนาคม', 'april' => 'เมษายน', 'may' => 'พฤษภาคม', 'june' => 'มิถุนายน', 'july' => 'กรกฎาคม', 'august' => 'สิงหาคม', 'september' => 'กันยายน', 'october' => 'ตุลาคม', 'november' => 'พฤศจิกายน', 'december' => 'ธันวาคม'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['year']) && isset($_GET['month']))) {
    $year = isset($_POST['year']) ? mysqli_real_escape_string($connection, $_POST['year']) : mysqli_real_escape_string($connection, $_GET['year']);
    $month = isset($_POST['month']) ? mysqli_real_escape_string($connection, $_POST['month']) : mysqli_real_escape_string($connection, $_GET['month']);
    
    $sql = "SELECT p.pay_no, p.emp_id, e.Name, e.bank_accno,
                   COALESCE(s.base_salary, p.total_pay) AS base_salary,
                   COALESCE(s.total_additions, 0) AS total_additions,
                   COALESCE(s.total_deductions, 0) AS total_deductions,
                   COALESCE(s.net_salary, p.total_pay) AS total_pay,
                   GROUP_CONCAT(CASE WHEN a.adjustment_type='addition' THEN CONCAT(a.adjustment_name, ' ฿', FORMAT(a.amount,2)) END ORDER BY a.id SEPARATOR ' • ') AS addition_details,
                   GROUP_CONCAT(CASE WHEN a.adjustment_type='deduction' THEN CONCAT(a.adjustment_name, ' ฿', FORMAT(a.amount,2)) END ORDER BY a.id SEPARATOR ' • ') AS deduction_details
            FROM `employee` e 
            INNER JOIN `payment` p ON e.Employee_id = p.emp_id 
            LEFT JOIN payment_snapshots s ON s.pay_no=p.pay_no
            LEFT JOIN payroll_adjustments a ON a.pay_no=p.pay_no
            WHERE LOWER(p.month)=LOWER('$month') AND p.year='$year'
            GROUP BY p.pay_no, p.emp_id, e.Name, e.bank_accno, s.base_salary, s.total_additions, s.total_deductions, s.net_salary, p.total_pay";
            
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_assoc($result)) {
        $payslips[] = $row;
    }
}
?>

<div class="space-y-5 sm:space-y-7 min-w-0">
    <div class="mb-2">
        <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px]">สลิปเงินเดือน</h1>
        <p class="text-[14px] sm:text-[15px] text-[#615d59] mt-1">เลือกเดือนและปีเพื่อดูสลิปเงินเดือนทั้งหมดของช่วงเวลานั้น</p>
    </div>
    
    <div class="bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] p-4 sm:p-5 lg:p-6">
        <form method="post" action="/employee/payslip" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_auto] gap-4 items-end">
            <div class="flex-1 w-full">
                <label class="block text-[15px] font-medium text-[#000000] mb-2">ปี (ค.ศ.)</label>
                <input type="number" name="year" value="<?= isset($year) ? htmlspecialchars($year) : date('Y') ?>" required class="block w-full min-h-11 px-3 py-2 bg-[#ffffff] border border-[#e6e6e6] rounded-[7px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div class="flex-1 w-full">
                <label class="block text-[15px] font-medium text-[#000000] mb-2">เดือน</label>
                <select name="month" required class="block w-full min-h-11 px-3 py-2 bg-[#ffffff] border border-[#e6e6e6] rounded-[7px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    <?php 
                    $months = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
                    $selected_month = isset($month) ? $month : strtolower(date('F'));
                    foreach($months as $m) {
                        $selected = ($selected_month == $m) ? 'selected' : '';
                        echo "<option value=\"$m\" $selected>" . $thaiMonths[$m] . "</option>";
                    }
                    ?>
                </select>
            </div>
            
            <div class="w-full sm:col-span-2 lg:col-span-1 lg:w-auto">
                <button type="submit" data-loading-text="กำลังค้นหา..." class="w-full lg:w-auto min-h-11 bg-[#0075de] text-[#ffffff] rounded-[8px] py-2 px-6 text-[16px] font-medium hover:bg-[#005bab] transition-colors focus:outline-none flex items-center justify-center whitespace-nowrap">
                    แสดงสลิปเงินเดือน
                </button>
            </div>
        </form>
    </div>

    <?php if ($result !== null): ?>
    <div class="rounded-[12px] border border-[#e6e6e6] overflow-hidden">
        <div class="p-4 border-b border-[#e6e6e6] bg-[#f6f5f4] flex flex-wrap gap-2 justify-between items-center">
            <h3 class="text-[15px] font-semibold text-[#000000]">สลิปเงินเดือน เดือน<?= htmlspecialchars($thaiMonths[$month] ?? $month) ?> ปี <?= htmlspecialchars($year) ?></h3>
            <span class="bg-[#ffffff] text-[#0075de] text-[12px] font-semibold px-2 py-1 rounded-[9999px] border border-[#e6e6e6]"><?= count($payslips) ?> รายการ</span>
        </div>

        <section class="js-mobile-list-section md:hidden bg-white p-3 space-y-3">
            <?php if ($payslips): ?>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#a39e98]"></i>
                    <input type="search" class="js-mobile-search w-full min-h-12 rounded-[9px] border border-[#e6e6e6] pl-11 pr-4 text-[15px] outline-none focus:border-[#0075de]" placeholder="ค้นหาชื่อหรือรหัสพนักงาน..." aria-label="ค้นหาสลิปเงินเดือน">
                </div>
                <div class="js-mobile-records divide-y divide-[#e6e6e6]">
                    <?php foreach ($payslips as $index => $row): ?>
                        <article class="js-mobile-record py-4 first:pt-1" data-name="<?= htmlspecialchars(strtolower($row['Name'])) ?>" data-salary="<?= (float) $row['total_pay'] ?>" data-original="<?= $index ?>" data-search="<?= htmlspecialchars(strtolower($row['pay_no'] . ' ' . $row['emp_id'] . ' ' . $row['Name'] . ' ' . $row['bank_accno'])) ?>">
                            <div class="flex items-start justify-between gap-3 min-w-0">
                                <div class="min-w-0"><p class="font-bold text-[16px] truncate"><?= htmlspecialchars($row['Name']) ?></p><p class="text-[13px] text-[#615d59]">พนักงาน #<?= htmlspecialchars($row['emp_id']) ?> · รายการ #<?= htmlspecialchars($row['pay_no']) ?></p></div>
                                <p class="font-bold text-[17px] whitespace-nowrap">฿<?= number_format($row['total_pay']) ?></p>
                            </div>
                            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1 text-[13px]"><dt class="text-[#615d59]">เงินเดือนพื้นฐาน</dt><dd class="text-right">฿<?= number_format($row['base_salary'],2) ?></dd><dt class="text-emerald-700">รายรับเพิ่มเติม</dt><dd class="text-right text-emerald-700">+฿<?= number_format($row['total_additions'],2) ?></dd><dt class="text-red-700">รายการหัก</dt><dd class="text-right text-red-700">-฿<?= number_format($row['total_deductions'],2) ?></dd><dt class="text-[#615d59]">บัญชีรับเงิน</dt><dd class="text-right break-all"><?= htmlspecialchars($row['bank_accno']) ?></dd></dl>
                            <?php if ($row['addition_details'] || $row['deduction_details']): ?><details class="mt-3 rounded-[7px] bg-[#f6f5f4] p-3 text-[12px]"><summary class="cursor-pointer font-semibold">ดูรายละเอียดการคำนวณ</summary><?php if ($row['addition_details']): ?><p class="mt-2 text-emerald-700">เพิ่ม: <?= htmlspecialchars($row['addition_details']) ?></p><?php endif; ?><?php if ($row['deduction_details']): ?><p class="mt-1 text-red-700">หัก: <?= htmlspecialchars($row['deduction_details']) ?></p><?php endif; ?></details><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <div class="js-mobile-empty-filter hidden py-8 text-center text-[14px] text-[#615d59]">ไม่พบสลิปที่ตรงกับการค้นหา</div>
            <?php else: ?>
                <div class="py-10 text-center"><i class="fa-solid fa-receipt text-[28px] text-[#a39e98]"></i><p class="font-bold mt-3">ไม่พบสลิปเงินเดือน</p><p class="text-[14px] text-[#615d59] mt-1">ยังไม่มีรายการในช่วงเวลาที่เลือก</p></div>
            <?php endif; ?>
        </section>
        
        <div class="hidden md:block min-w-0 bg-white">
            <table class="js-data-table w-full text-left text-[15px] text-[#000000]" data-export-name="สลิปเงินเดือน">
                <thead class="bg-[#f6f5f4] border-b border-[#e6e6e6]">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">เลขที่รายการ</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">รหัสพนักงาน</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">ชื่อ-นามสกุล</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">เงินเดือนพื้นฐาน</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">รายรับเพิ่ม</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">รายการหัก</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">รายละเอียด</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">เงินเดือนสุทธิ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e6e6]">
                    <?php if ($payslips): ?>
                        <?php foreach ($payslips as $row): ?>
                            <tr class="hover:bg-[#f6f5f4] transition-colors">
                                <td class="px-4 py-3 font-medium">#<?= htmlspecialchars($row['pay_no']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['emp_id']) ?></td>
                                <td class="px-4 py-3 font-medium text-[#000000]"><?= htmlspecialchars($row['Name']) ?></td>
                                <td class="px-4 py-3 text-right">฿<?= number_format($row['base_salary'],2) ?></td>
                                <td class="px-4 py-3 text-right text-emerald-700">+฿<?= number_format($row['total_additions'],2) ?></td>
                                <td class="px-4 py-3 text-right text-red-700">-฿<?= number_format($row['total_deductions'],2) ?></td>
                                <td class="px-4 py-3 text-[12px] text-[#615d59]"><details><summary class="cursor-pointer font-semibold text-[#0075de]">ดูรายการ</summary><?php if ($row['addition_details']): ?><p class="mt-2 text-emerald-700">+ <?= htmlspecialchars($row['addition_details']) ?></p><?php endif; ?><?php if ($row['deduction_details']): ?><p class="mt-1 text-red-700">- <?= htmlspecialchars($row['deduction_details']) ?></p><?php endif; ?></details></td>
                                <td class="px-4 py-3 text-right font-medium">฿<?= number_format($row['total_pay']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-[#615d59]">ไม่พบสลิปเงินเดือนในช่วงเวลานี้</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</div>
