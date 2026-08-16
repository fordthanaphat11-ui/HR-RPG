<?php
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($connection, $_GET['id']);
    
    // Delete payments first due to foreign key constraint
    mysqli_query($connection, "DELETE FROM payment WHERE emp_id='$id'");
    
    // Delete employee
    mysqli_query($connection, "DELETE FROM employee WHERE Employee_id='$id'");
}

header("Location: /employee");
exit;
