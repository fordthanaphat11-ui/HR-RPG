<?php
require_once __DIR__ . '/../lib/db.php';

if (!authIsAdmin()) authRedirect('/');

$id = $_GET['id'] ?? null;
if ($id) {
    // Validate that we are not deleting ourselves
    $checkStmt = mysqli_prepare($connection, "SELECT username FROM admin WHERE id=?");
    mysqli_stmt_bind_param($checkStmt, "i", $id);
    mysqli_stmt_execute($checkStmt);
    $result = mysqli_stmt_get_result($checkStmt);
    $admin = mysqli_fetch_assoc($result);
    
    if ($admin) {
        if ($admin['username'] === $_SESSION['username']) {
            appToast('error', 'ไม่สามารถลบบัญชีของตัวเองได้');
        } else {
            $deleteStmt = mysqli_prepare($connection, "DELETE FROM admin WHERE id=?");
            mysqli_stmt_bind_param($deleteStmt, "i", $id);
            if (mysqli_stmt_execute($deleteStmt)) {
                appToast('success', 'ลบผู้ดูแลระบบสำเร็จ');
            } else {
                appToast('error', 'ไม่สามารถลบข้อมูลได้');
            }
        }
    } else {
        appToast('error', 'ไม่พบข้อมูลผู้ดูแลระบบ');
    }
}

authRedirect('/admin/accounts');
