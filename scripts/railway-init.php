<?php
declare(strict_types=1);

require __DIR__ . '/../lib/db.php';

$requiredTables = [
    'admin', 'department', 'employee', 'job', 'payment',
    'attendance', 'attendance_settings', 'attendance_location_settings',
    'attendance_geofences', 'employee_accounts', 'payroll_settings',
    'payment_snapshots', 'payroll_adjustments',
    'employee_salaries',
];

$tableResult = mysqli_query($connection, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()");
$existingTables = [];
while ($row = mysqli_fetch_assoc($tableResult)) $existingTables[] = (string) $row['TABLE_NAME'];

if ($existingTables) {
    echo "Database schema already exists; baseline import skipped.\n";
} else {
    $dumpPath = __DIR__ . '/../database/EE.sql';
    $sql = file_get_contents($dumpPath);
    if ($sql === false) {
        fwrite(STDERR, "Cannot read database/EE.sql\n");
        exit(1);
    }

// Railway supplies the database name. Never create or switch databases from the dump.
    $sql = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*$/mi', '', $sql);
    $sql = preg_replace('/^\s*USE\s+`?[^`;]+`?\s*;\s*$/mi', '', $sql);

    if (!mysqli_multi_query($connection, $sql)) {
        fwrite(STDERR, 'Baseline import failed: ' . mysqli_error($connection) . PHP_EOL);
        exit(1);
    }

    do {
        if ($result = mysqli_store_result($connection)) mysqli_free_result($result);
        if (!mysqli_more_results($connection)) break;
    } while (mysqli_next_result($connection));

    if (mysqli_errno($connection)) {
        fwrite(STDERR, 'Baseline import failed: ' . mysqli_error($connection) . PHP_EOL);
        exit(1);
    }
    echo "Railway database baseline imported successfully.\n";
}

$migrations = [
    'Employee salary history' => __DIR__ . '/../database/2026_08_17_employee_salary_history.sql',
    'Payroll calculation settings' => __DIR__ . '/../database/2026_08_17_payroll_calculation_settings.sql',
];
foreach ($migrations as $migrationName => $migrationPath) {
    $migrationSql = file_get_contents($migrationPath);
    if ($migrationSql === false || !mysqli_multi_query($connection, $migrationSql)) {
        fwrite(STDERR, $migrationName . ' migration failed: ' . mysqli_error($connection) . PHP_EOL);
        exit(1);
    }
    do {
        if ($result = mysqli_store_result($connection)) mysqli_free_result($result);
        if (!mysqli_more_results($connection)) break;
    } while (mysqli_next_result($connection));
    if (mysqli_errno($connection)) {
        fwrite(STDERR, $migrationName . ' migration failed: ' . mysqli_error($connection) . PHP_EOL);
        exit(1);
    }
    echo $migrationName . " migration applied.\n";
}

$missingAfterImport = [];
foreach ($requiredTables as $table) {
    $escaped = mysqli_real_escape_string($connection, $table);
    $result = mysqli_query($connection, "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$escaped}' LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) $missingAfterImport[] = $table;
}
if ($missingAfterImport) {
    fwrite(STDERR, 'Baseline import ended with missing tables: ' . implode(', ', $missingAfterImport) . PHP_EOL);
    exit(1);
}

echo "Railway database schema is ready.\n";
