<?php
$title = "เปลี่ยนรหัสผ่านผู้ดูแลระบบ - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!authIsAdmin()) authRedirect('/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $username = $_SESSION['username'];

    if ($new_password !== $confirm_password) {
        appToast('error', 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
        authRedirect('/admin/password');
    }

    $stmt = mysqli_prepare($connection, "SELECT password FROM admin WHERE username=?");
    mysqli_stmt_bind_param($stmt, "s", $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    $valid = false;
    // Check if it's hash or plain text (backward compatibility)
    if (password_verify($current_password, $result['password']) || hash_equals($result['password'], $current_password)) {
        $valid = true;
    }

    if ($valid) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $updateStmt = mysqli_prepare($connection, "UPDATE admin SET password=? WHERE username=?");
        mysqli_stmt_bind_param($updateStmt, "ss", $hashed, $username);
        if (mysqli_stmt_execute($updateStmt)) {
            appToast('success', 'เปลี่ยนรหัสผ่านสำเร็จ');
        } else {
            appToast('error', 'เกิดข้อผิดพลาดในการเปลี่ยนรหัสผ่าน');
        }
    } else {
        appToast('error', 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
    }
    
    authRedirect('/admin/password');
}
?>

<div class="max-w-[500px] mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-[#202223] tracking-tight">เปลี่ยนรหัสผ่าน</h1>
        <p class="text-[14px] text-[#6d7175] mt-1">อัปเดตรหัสผ่านสำหรับบัญชีผู้ดูแลระบบของคุณ</p>
    </div>
    
    <div class="bg-white rounded-xl border border-[#e6e6e6] p-6 shadow-sm">
        <form method="post" action="/admin/password" class="space-y-5">
            <div>
                <label class="block text-[13px] font-medium text-[#31302e] mb-1.5">รหัสผ่านปัจจุบัน</label>
                <input type="password" name="current_password" required class="block w-full h-10 px-3 bg-white border border-[#d5d3d0] rounded-md text-[14px] text-[#202223] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div class="border-t border-[#e6e6e6] pt-5">
                <label class="block text-[13px] font-medium text-[#31302e] mb-1.5">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" required minlength="6" class="block w-full h-10 px-3 bg-white border border-[#d5d3d0] rounded-md text-[14px] text-[#202223] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div>
                <label class="block text-[13px] font-medium text-[#31302e] mb-1.5">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" required minlength="6" class="block w-full h-10 px-3 bg-white border border-[#d5d3d0] rounded-md text-[14px] text-[#202223] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div class="pt-3">
                <button type="submit" class="w-full h-10 bg-[#0075de] text-white rounded-md px-4 text-[14px] font-medium hover:bg-[#005bab] transition-colors focus:outline-none shadow-sm">
                    บันทึกรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>
