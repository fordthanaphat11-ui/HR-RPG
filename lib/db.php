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

if ($db_username === null || $db_username === '' || $db_database === null || $db_database === '') {
    die('Database configuration is incomplete. Please set DB_USERNAME and DB_DATABASE in .env.');
}
if ($db_port === false || $db_port < 1 || $db_port > 65535) {
    die('Database configuration is invalid. DB_PORT must be between 1 and 65535.');
}

$connection = mysqli_connect($db_server, $db_username, $db_password, $db_database, $db_port);

if (!$connection) {
    die("Database Connection Failed: " . mysqli_connect_error());
}

if (!mysqli_set_charset($connection, $db_charset ?: 'utf8mb4')) {
    die('Database charset configuration failed: ' . mysqli_error($connection));
}
