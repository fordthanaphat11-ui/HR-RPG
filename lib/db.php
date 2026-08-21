<?php
declare(strict_types=1);
require_once __DIR__ . '/env.php';

appLoadEnv(dirname(__DIR__) . '/.env');

$db_server = appEnv('DB_HOST', appEnv('MYSQLHOST', 'localhost'));
$db_username = appEnv('DB_USERNAME', appEnv('MYSQLUSER'));
$db_password = appEnv('DB_PASSWORD', appEnv('MYSQLPASSWORD', ''));
$db_database = appEnv('DB_DATABASE', appEnv('MYSQLDATABASE'));
$db_port = filter_var(appEnv('DB_PORT', appEnv('MYSQLPORT', '3306')), FILTER_VALIDATE_INT);
$db_charset = appEnv('DB_CHARSET', 'utf8mb4');

// Automatically parse connection URL if provided by Railway (MYSQL_URL, DATABASE_URL, MYSQL_PUBLIC_URL)
$databaseUrl = appEnv('MYSQL_URL', appEnv('DATABASE_URL', appEnv('MYSQL_PUBLIC_URL')));
if ($databaseUrl) {
    $parsedUrl = parse_url($databaseUrl);
    if ($parsedUrl) {
        if (!empty($parsedUrl['host'])) $db_server = $parsedUrl['host'];
        if (!empty($parsedUrl['port'])) $db_port = (int) $parsedUrl['port'];
        if (isset($parsedUrl['user'])) $db_username = urldecode($parsedUrl['user']);
        if (isset($parsedUrl['pass'])) $db_password = urldecode($parsedUrl['pass']);
        if (!empty($parsedUrl['path'])) {
            $db_database = ltrim($parsedUrl['path'], '/');
        }
    }
}

if ($db_username === null || $db_username === '' || $db_database === null || $db_database === '') {
    throw new RuntimeException('Database configuration is incomplete. Set DB_USERNAME and DB_DATABASE.');
}
if ($db_port === false || $db_port < 1 || $db_port > 65535) {
    throw new RuntimeException('Database configuration is invalid. DB_PORT must be between 1 and 65535.');
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$connection = null;
$lastException = null;
$maxAttempts = 5;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $conn = mysqli_init();
        mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        mysqli_real_connect($conn, $db_server, $db_username, $db_password, $db_database, $db_port);
        mysqli_set_charset($conn, $db_charset ?: 'utf8mb4');
        $connection = $conn;
        break;
    } catch (Throwable $exception) {
        $lastException = $exception;
        error_log("Database connection attempt {$attempt}/{$maxAttempts} failed: " . $exception->getMessage());
        if ($attempt < $maxAttempts) {
            sleep(2);
        }
    }
}

if ($connection === null) {
    throw new RuntimeException('Database connection is unavailable.', 0, $lastException);
}

