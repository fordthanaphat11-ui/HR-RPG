<?php
declare(strict_types=1);

function payrollMonths(): array
{
    return ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december'];
}

function payrollThaiMonths(): array
{
    return ['january'=>'มกราคม','february'=>'กุมภาพันธ์','march'=>'มีนาคม','april'=>'เมษายน','may'=>'พฤษภาคม','june'=>'มิถุนายน','july'=>'กรกฎาคม','august'=>'สิงหาคม','september'=>'กันยายน','october'=>'ตุลาคม','november'=>'พฤศจิกายน','december'=>'ธันวาคม'];
}

function getPayrollSettings(mysqli $connection): array
{
    $result = mysqli_query($connection, "SELECT * FROM payroll_settings WHERE id=1 LIMIT 1");
    $settings = $result ? mysqli_fetch_assoc($result) : null;
    $defaults = [
        'id'=>1, 'absence_deduction_enabled'=>0, 'absence_deduction_per_day'=>0,
        'absence_deduction_mode'=>'fixed', 'absence_salary_divisor_days'=>30,
        'late_deduction_mode'=>'none', 'late_deduction_per_occurrence'=>0,
        'late_interval_minutes'=>30, 'late_deduction_per_interval'=>0,
        'late_deduction_per_minute'=>0,
        'late_rounding_mode'=>'ceil', 'late_grace_minutes'=>0,
        'max_late_deduction'=>null, 'allow_negative_net_salary'=>0,
        'updated_at'=>null, 'updated_by'=>null,
    ];
    return array_merge($defaults, $settings ?: []);
}

function payrollNumericValue(mixed $value, bool $integer = false): int|float|null
{
    $normalized = strtr(trim((string) $value), [
        '๐'=>'0','๑'=>'1','๒'=>'2','๓'=>'3','๔'=>'4',
        '๕'=>'5','๖'=>'6','๗'=>'7','๘'=>'8','๙'=>'9',
        ','=>'', "\u{00A0}"=>'',
    ]);
    if ($normalized === '') return null;
    if (!is_numeric($normalized)) return null;
    $number = $integer ? (int) $normalized : (float) $normalized;
    if ($integer && (string) $number !== ltrim($normalized, '+')) return null;
    return $number;
}

function normalizePayrollAdjustments(array $input): array
{
    $names = $input['name'] ?? [];
    $amounts = $input['amount'] ?? [];
    $notes = $input['note'] ?? [];
    $rows = [];
    foreach ((array) $names as $index => $name) {
        $name = trim((string) $name);
        $amount = payrollNumericValue($amounts[$index] ?? null);
        $note = trim((string) ($notes[$index] ?? ''));
        if ($name === '' && ($amount === null || (float) $amount === 0.0)) continue;
        if ($name === '' || $amount === null || (float) $amount < 0) {
            throw new InvalidArgumentException('กรุณาระบุชื่อและจำนวนเงินของทุกรายการให้ถูกต้อง');
        }
        $rows[] = ['name'=>mb_substr($name,0,120,'UTF-8'), 'amount'=>round((float)$amount,2), 'note'=>mb_substr($note,0,255,'UTF-8')];
    }
    return $rows;
}

function findNextUnpaidEmployee(mysqli $connection, int $year, string $month, string $currentName, int $currentId): ?array
{
    $monthIndex = array_search(strtolower($month), payrollMonths(), true);
    $periodDate = sprintf('%04d-%02d-01', $year, ($monthIndex === false ? 0 : $monthIndex) + 1);
    $sql = "SELECT e.Employee_id, e.Name
            FROM employee e
            WHERE NOT EXISTS (
                SELECT 1 FROM payment p
                WHERE p.emp_id=e.Employee_id AND p.year=? AND LOWER(p.month)=LOWER(?)
            )
            AND EXISTS (
                SELECT 1 FROM employee_salaries es
                WHERE es.employee_id=e.Employee_id AND es.effective_from<=?
            )
            ORDER BY
                CASE WHEN e.Name > ? OR (e.Name = ? AND e.Employee_id > ?) THEN 0 ELSE 1 END,
                e.Name ASC, e.Employee_id ASC
            LIMIT 1";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'issssi', $year, $month, $periodDate, $currentName, $currentName, $currentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $employee = $result ? mysqli_fetch_assoc($result) : null;
    return $employee ?: null;
}

function calculateLateDeduction(array $settings, ?int $lateCount, ?int $lateMinutes, bool $minutesAlreadyAdjusted = false): array
{
    $mode = (string) ($settings['late_deduction_mode'] ?? 'none');
    $count = max(0, (int) ($lateCount ?? 0));
    $minutes = max(0, (int) ($lateMinutes ?? 0));
    $amount = 0.0;
    $formula = 'ปิดการหักเงินกรณีมาสาย';
    $rate = 0.0;

    if ($mode === 'per_occurrence') {
        $rate = max(0, (float) $settings['late_deduction_per_occurrence']);
        $amount = $count * $rate;
        $formula = number_format($count) . ' ครั้ง × ฿' . number_format($rate, 2) . '/ครั้ง';
    } elseif ($mode === 'per_minutes') {
        $interval = max(1, (int) $settings['late_interval_minutes']);
        $rate = max(0, (float) $settings['late_deduction_per_interval']);
        $grace = $minutesAlreadyAdjusted ? 0 : max(0, (int) $settings['late_grace_minutes']);
        $chargeable = max(0, $minutes - ($grace * $count));
        $rawIntervals = $chargeable / $interval;
        $intervals = ($settings['late_rounding_mode'] ?? 'ceil') === 'floor' ? floor($rawIntervals) : ceil($rawIntervals);
        $amount = $intervals * $rate;
        $formula = number_format($minutes) . ' นาที − ผ่อนผัน ' . number_format($grace * $count) . ' นาที; '
            . number_format($intervals) . ' รอบ × ฿' . number_format($rate, 2);
    } elseif ($mode === 'per_actual_minute') {
        $rate = max(0, (float) ($settings['late_deduction_per_minute'] ?? 0));
        $amount = $minutes * $rate;
        $formula = number_format($minutes) . ' นาที × ฿' . number_format($rate, 2) . '/นาที';
    }

    $maximum = $settings['max_late_deduction'];
    if ($maximum !== null && $maximum !== '' && (float)$maximum >= 0) {
        $amount = min($amount, (float)$maximum);
        $formula .= ' (สูงสุด ฿' . number_format((float)$maximum, 2) . ')';
    }

    return ['amount'=>round($amount,2), 'formula'=>$formula, 'rate'=>$rate, 'mode'=>$mode];
}

function calculateAbsenceDeduction(array $settings, float $baseSalary, ?float $absenceDays): array
{
    $enabled = !empty($settings['absence_deduction_enabled']);
    $mode = in_array(($settings['absence_deduction_mode'] ?? 'fixed'), ['fixed', 'daily_salary'], true)
        ? (string) $settings['absence_deduction_mode']
        : 'fixed';
    $days = max(0, (float) ($absenceDays ?? 0));
    $divisor = max(1, (int) ($settings['absence_salary_divisor_days'] ?? 30));
    $rate = $enabled
        ? ($mode === 'daily_salary' ? round(max(0, $baseSalary) / $divisor, 2) : max(0, (float) ($settings['absence_deduction_per_day'] ?? 0)))
        : 0.0;
    $amount = $absenceDays === null ? 0.0 : round($days * $rate, 2);
    $formula = !$enabled
        ? 'ปิดการหักเงินกรณีขาดงาน'
        : ($mode === 'daily_salary'
            ? 'เงินเดือน ฿' . number_format($baseSalary, 2) . ' ÷ ' . number_format($divisor) . ' วัน = ฿' . number_format($rate, 2) . '/วัน'
            : number_format($days, 2) . ' วัน × ฿' . number_format($rate, 2) . '/วัน');

    return ['amount'=>$amount, 'rate'=>$rate, 'mode'=>$mode, 'divisor_days'=>$divisor, 'formula'=>$formula];
}

function calculatePayroll(array $employee, array $settings, array $attendance, array $manualAdditions, array $manualDeductions, float $overtimeHours): array
{
    $base = round(max(0, (float)$employee['basic_salary']), 2);
    $loanCut = round(max(0, (float)$employee['loan']) * 0.05, 2);
    $fundCut = round($base * 0.025, 2);
    $medical = round($base * 0.03, 2);
    $housing = round($base * 0.08, 2);
    $overtimeAmount = round(max(0, $overtimeHours) * 300, 2);
    $absenceDays = $attendance['absence_days'] ?? null;
    $absence = calculateAbsenceDeduction($settings, $base, $absenceDays === null ? null : (float) $absenceDays);
    $absenceRate = $absence['rate'];
    $absenceDeduction = $absence['amount'];
    $late = calculateLateDeduction($settings, $attendance['late_count'] ?? null, $attendance['late_minutes'] ?? null, ($attendance['source'] ?? '') === 'attendance');

    $automaticAdditions = [
        ['name'=>'ค่ารักษาพยาบาล', 'amount'=>$medical, 'source'=>'medical', 'note'=>'3% ของเงินเดือนพื้นฐาน'],
        ['name'=>'ค่าที่พัก', 'amount'=>$housing, 'source'=>'housing', 'note'=>'8% ของเงินเดือนพื้นฐาน'],
    ];
    if ($overtimeAmount > 0) $automaticAdditions[] = ['name'=>'ค่าล่วงเวลา', 'amount'=>$overtimeAmount, 'source'=>'overtime', 'note'=>number_format($overtimeHours,2).' ชั่วโมง × ฿300'];

    $automaticDeductions = [
        ['name'=>'หักเงินยืม', 'amount'=>$loanCut, 'source'=>'loan', 'note'=>'5% ของยอดเงินยืมคงเหลือ'],
        ['name'=>'กองทุนสำรองเลี้ยงชีพ', 'amount'=>$fundCut, 'source'=>'provident_fund', 'note'=>'2.5% ของเงินเดือนพื้นฐาน'],
    ];
    if ($absenceDays !== null && !empty($settings['absence_deduction_enabled'])) {
        $automaticDeductions[] = ['name'=>'ขาดงาน', 'amount'=>$absenceDeduction, 'source'=>'absence', 'note'=>$absence['formula']];
    }
    if (($attendance['late_count'] !== null || $attendance['late_minutes'] !== null) && $late['mode'] !== 'none') {
        $automaticDeductions[] = ['name'=>'มาสาย', 'amount'=>$late['amount'], 'source'=>'late', 'note'=>$late['formula']];
    }

    $totalAdditions = round(array_sum(array_column($automaticAdditions,'amount')) + array_sum(array_column($manualAdditions,'amount')), 2);
    $totalDeductions = round(array_sum(array_column($automaticDeductions,'amount')) + array_sum(array_column($manualDeductions,'amount')), 2);
    $net = round($base + $totalAdditions - $totalDeductions, 2);
    if ($net < 0 && empty($settings['allow_negative_net_salary'])) {
        throw new DomainException('ยอดหักมากกว่ายอดรายได้ ระบบไม่อนุญาตให้เงินเดือนสุทธิติดลบ');
    }

    return [
        'base_salary'=>$base, 'automatic_additions'=>$automaticAdditions, 'manual_additions'=>$manualAdditions,
        'automatic_deductions'=>$automaticDeductions, 'manual_deductions'=>$manualDeductions,
        'total_additions'=>$totalAdditions, 'total_deductions'=>$totalDeductions, 'net_salary'=>$net,
        'absence_days'=>$absenceDays, 'absence_rate'=>$absenceRate, 'absence_deduction'=>$absenceDeduction,
        'absence_formula'=>$absence['formula'], 'absence_mode'=>$absence['mode'],
        'late_count'=>$attendance['late_count'] ?? null, 'late_minutes'=>$attendance['late_minutes'] ?? null,
        'late_rate'=>$late['rate'], 'late_deduction'=>$late['amount'], 'late_formula'=>$late['formula'],
        'loan_cut'=>$loanCut, 'fund_cut'=>$fundCut, 'medical_allowance'=>$medical,
        'housing_allowance'=>$housing, 'overtime_hours'=>$overtimeHours, 'overtime_amount'=>$overtimeAmount,
    ];
}

function loadPaymentAdjustments(mysqli $connection, int $payNo): array
{
    $stmt = mysqli_prepare($connection, "SELECT adjustment_type, adjustment_source, adjustment_name, amount, note FROM payroll_adjustments WHERE pay_no=? ORDER BY id");
    mysqli_stmt_bind_param($stmt, 'i', $payNo);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}
