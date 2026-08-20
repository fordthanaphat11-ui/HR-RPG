<?php
declare(strict_types=1);

require_once __DIR__ . '/payroll.php';

function salaryPeriodDate(int $year, string $month): string
{
    $index = array_search(strtolower($month), payrollMonths(), true);
    if ($index === false || $year < 2000 || $year > 2200) {
        throw new InvalidArgumentException('งวดเงินเดือนไม่ถูกต้อง');
    }
    return sprintf('%04d-%02d-01', $year, $index + 1);
}

function salaryIsValidDate(string $date): bool
{
    $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $value !== false && $value->format('Y-m-d') === $date;
}

function salaryEffectiveAt(mysqli $connection, int $employeeId, string $targetDate, bool $forUpdate = false): ?array
{
    if (!salaryIsValidDate($targetDate)) return null;
    $lock = $forUpdate ? ' FOR UPDATE' : '';
    $stmt = mysqli_prepare($connection, "SELECT id, employee_id, salary_amount, effective_from, reason, note, created_by, created_at
        FROM employee_salaries
        WHERE employee_id=? AND effective_from<=?
        ORDER BY effective_from DESC, id DESC LIMIT 1{$lock}");
    mysqli_stmt_bind_param($stmt, 'is', $employeeId, $targetDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return $row ?: null;
}

function salaryNextScheduled(mysqli $connection, int $employeeId, string $afterDate): ?array
{
    $stmt = mysqli_prepare($connection, 'SELECT id, employee_id, salary_amount, effective_from, reason, note, created_by, created_at
        FROM employee_salaries WHERE employee_id=? AND effective_from>? ORDER BY effective_from ASC, id ASC LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'is', $employeeId, $afterDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = $result ? mysqli_fetch_assoc($result) : null;
    return $row ?: null;
}

function salaryHistory(mysqli $connection, int $employeeId): array
{
    $stmt = mysqli_prepare($connection, 'SELECT id, employee_id, salary_amount, effective_from, reason, note, created_by, created_at
        FROM employee_salaries WHERE employee_id=? ORDER BY effective_from DESC, id DESC');
    mysqli_stmt_bind_param($stmt, 'i', $employeeId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function salaryEmployeeList(mysqli $connection, string $targetDate): array
{
    if (!salaryIsValidDate($targetDate)) throw new InvalidArgumentException('วันที่อ้างอิงเงินเดือนไม่ถูกต้อง');
    $sql = "SELECT e.Employee_id, e.Name, e.Start_date, e.jobtitle, e.Depart_id, e.loan, e.p_fund,
                   d.Depart_name,
                   (SELECT es.salary_amount FROM employee_salaries es
                    WHERE es.employee_id=e.Employee_id AND es.effective_from<=?
                    ORDER BY es.effective_from DESC, es.id DESC LIMIT 1) AS basic_salary,
                   (SELECT es.effective_from FROM employee_salaries es
                    WHERE es.employee_id=e.Employee_id AND es.effective_from<=?
                    ORDER BY es.effective_from DESC, es.id DESC LIMIT 1) AS salary_effective_from,
                   (SELECT COUNT(*) FROM employee_salaries es WHERE es.employee_id=e.Employee_id) AS salary_history_count
            FROM employee e
            LEFT JOIN department d ON d.Depart_id=e.Depart_id
            ORDER BY (basic_salary IS NULL) DESC, e.Name ASC, e.Employee_id ASC";
    $stmt = mysqli_prepare($connection, $sql);
    mysqli_stmt_bind_param($stmt, 'ss', $targetDate, $targetDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function salaryThaiDate(string $date, bool $short = false): string
{
    if (!salaryIsValidDate($date)) return '-';
    $months = $short
        ? [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.']
        : [1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'];
    $value = new DateTimeImmutable($date);
    return (int) $value->format('j') . ' ' . $months[(int) $value->format('n')] . ' ' . ((int) $value->format('Y') + 543);
}

function salaryChange(float $amount, ?float $previous): array
{
    if ($previous === null) return ['difference'=>null, 'percentage'=>null];
    $difference = round($amount - $previous, 2);
    $percentage = $previous == 0.0 ? null : round(($difference / $previous) * 100, 2);
    return ['difference'=>$difference, 'percentage'=>$percentage];
}
