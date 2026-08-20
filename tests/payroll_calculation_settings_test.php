<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/payroll.php';

function assertPayrollAmount(float $expected, float $actual, string $label): void
{
    if (abs($expected - $actual) > 0.001) {
        throw new RuntimeException($label . ': expected ' . $expected . ', got ' . $actual);
    }
}

$employee = ['basic_salary'=>20000, 'loan'=>0, 'p_fund'=>0];
$baseSettings = [
    'absence_deduction_enabled'=>1,
    'absence_deduction_mode'=>'fixed',
    'absence_deduction_per_day'=>500,
    'absence_salary_divisor_days'=>30,
    'late_deduction_mode'=>'per_occurrence',
    'late_deduction_per_occurrence'=>100,
    'late_interval_minutes'=>30,
    'late_deduction_per_interval'=>50,
    'late_deduction_per_minute'=>2,
    'late_rounding_mode'=>'ceil',
    'late_grace_minutes'=>0,
    'max_late_deduction'=>null,
    'allow_negative_net_salary'=>0,
];

// Test A excludes legacy automatic allowances/deductions from the requested subtotal,
// so validate the two configurable deductions directly.
$testA = calculatePayroll($employee, $baseSettings, ['absence_days'=>2.0, 'late_count'=>3, 'late_minutes'=>75, 'source'=>'attendance'], [['name'=>'โบนัส','amount'=>2000]], [], 0);
assertPayrollAmount(1000, $testA['absence_deduction'], 'Test A absence');
assertPayrollAmount(300, $testA['late_deduction'], 'Test A late');

$intervalSettings = array_merge($baseSettings, ['late_deduction_mode'=>'per_minutes']);
$testB = calculateLateDeduction($intervalSettings, 3, 75, true);
assertPayrollAmount(150, $testB['amount'], 'Test B interval round up');

$dailySettings = array_merge($baseSettings, ['absence_deduction_mode'=>'daily_salary']);
$daily = calculateAbsenceDeduction($dailySettings, 20000, 2);
assertPayrollAmount(666.67, $daily['rate'], 'Daily salary rate');
assertPayrollAmount(1333.34, $daily['amount'], 'Daily salary deduction');

$minuteSettings = array_merge($baseSettings, ['late_deduction_mode'=>'per_actual_minute']);
$minute = calculateLateDeduction($minuteSettings, 1, 25, true);
assertPayrollAmount(50, $minute['amount'], 'Actual minute deduction');

$negativeRejected = false;
try {
    calculatePayroll(
        ['basic_salary'=>10000, 'loan'=>0, 'p_fund'=>0],
        array_merge($baseSettings, ['absence_deduction_enabled'=>0, 'late_deduction_mode'=>'none']),
        ['absence_days'=>0, 'late_count'=>0, 'late_minutes'=>0, 'source'=>'attendance'],
        [],
        [['name'=>'รายการหักทดสอบ','amount'=>15000]],
        0
    );
} catch (DomainException) {
    $negativeRejected = true;
}
if (!$negativeRejected) throw new RuntimeException('Test C must reject a negative net salary');

echo "Payroll calculation settings tests passed.\n";
