<?php
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

if (isset($_GET['id'])) {
    $id = mysqli_real_escape_string($connection, $_GET['id']);
    
    // First need to check if there are any employees in this department
    $check_query = "SELECT COUNT(*) as count FROM employee WHERE Depart_id='$id' OR managesDepart_id='$id'";
    $check_result = mysqli_query($connection, $check_query);
    $count = mysqli_fetch_assoc($check_result)['count'];
    
    if ($count > 0) {
        die("ไม่สามารถลบแผนกนี้ได้ เนื่องจากยังมีพนักงานอยู่ในแผนก <a href='/department' class='text-blue-500 underline'>กลับไปยังรายการ</a>");
    }
    
    $query = "DELETE FROM department WHERE Depart_id='$id'";
    mysqli_query($connection, $query);
}

header("Location: /department");
exit;
