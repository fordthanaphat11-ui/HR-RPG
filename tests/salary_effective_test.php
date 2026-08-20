<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';
require __DIR__ . '/../lib/salary.php';

$result = mysqli_query($connection, 'SELECT Employee_id FROM employee ORDER BY Employee_id LIMIT 1');
$employeeId = (int) (mysqli_fetch_assoc($result)['Employee_id'] ?? 0);
if (!$employeeId) throw new RuntimeException('Test requires at least one employee.');

mysqli_begin_transaction($connection);
try {
    $delete = mysqli_prepare($connection, 'DELETE FROM employee_salaries WHERE employee_id=?');
    mysqli_stmt_bind_param($delete, 'i', $employeeId);
    mysqli_stmt_execute($delete);
    $insert = mysqli_prepare($connection, "INSERT INTO employee_salaries (employee_id,salary_amount,effective_from,reason,created_by) VALUES
        (?,18000,'2026-01-01','test','test'),
        (?,20000,'2026-06-01','test','test'),
        (?,22000,'2026-09-01','test','test')");
    mysqli_stmt_bind_param($insert, 'iii', $employeeId, $employeeId, $employeeId);
    mysqli_stmt_execute($insert);

    $cases = ['2026-05-01'=>18000.0, '2026-08-01'=>20000.0, '2026-09-01'=>22000.0];
    foreach ($cases as $date=>$expected) {
        $salary = salaryEffectiveAt($connection, $employeeId, $date);
        $actual = (float) ($salary['salary_amount'] ?? -1);
        if ($actual !== $expected) throw new RuntimeException("{$date}: expected {$expected}, got {$actual}");
    }
    echo "Salary effective-date test passed.\n";
} finally {
    mysqli_rollback($connection);
}
