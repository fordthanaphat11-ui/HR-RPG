<?php
// db.php
$db_server = 'localhost';
$db_username = 'root';
$db_password = '';
$db_database = 'hr-rpg';

$connection = mysqli_connect($db_server, $db_username, $db_password, $db_database);

if (!$connection) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
