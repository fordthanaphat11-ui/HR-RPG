<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/lib/db.php';
    $result = mysqli_query($connection, 'SELECT 1 AS healthy');
    if (!$result || (int) mysqli_fetch_assoc($result)['healthy'] !== 1) throw new RuntimeException('Database probe failed.');
    echo json_encode(['status' => 'ok', 'database' => 'connected'], JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    error_log('Health check failed: ' . $exception->getMessage());
    http_response_code(503);
    echo json_encode(['status' => 'error', 'database' => 'unavailable'], JSON_UNESCAPED_SLASHES);
}
