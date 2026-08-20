<?php
$title = "เข้าสู่ระบบ - ระบบบริหารเงินเดือน";

require_once __DIR__ . '/../lib/db.php';

if (authIsLoggedIn()) authRedirect(authIsEmployee() ? '/me' : '/');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $adminStmt = mysqli_prepare($connection, 'SELECT username, password FROM admin WHERE username=? LIMIT 1');
    mysqli_stmt_bind_param($adminStmt, 's', $username);
    mysqli_stmt_execute($adminStmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($adminStmt));
    mysqli_stmt_close($adminStmt);

    if ($admin && (password_verify($password, (string) $admin['password']) || hash_equals((string) $admin['password'], $password))) {
        authStartAdminSession((string) $admin['username']);
        authRedirect('/');
    }

    $employeeStmt = mysqli_prepare($connection, "SELECT a.id, a.employee_id, a.username, a.password_hash,
                                                        a.is_active, a.must_change_password, e.Name
                                                 FROM employee_accounts a
                                                 INNER JOIN employee e ON e.Employee_id=a.employee_id
                                                 WHERE a.username=? LIMIT 1");
    mysqli_stmt_bind_param($employeeStmt, 's', $username);
    mysqli_stmt_execute($employeeStmt);
    $account = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
    mysqli_stmt_close($employeeStmt);

    if ($account && (int) $account['is_active'] === 1 && password_verify($password, (string) $account['password_hash'])) {
        $accountId = (int) $account['id'];
        $loginUpdate = mysqli_prepare($connection, 'UPDATE employee_accounts SET last_login_at=NOW() WHERE id=?');
        mysqli_stmt_bind_param($loginUpdate, 'i', $accountId);
        mysqli_stmt_execute($loginUpdate);
        mysqli_stmt_close($loginUpdate);
        authStartEmployeeSession($account);
        authRedirect(!empty($account['must_change_password']) ? '/me/password' : '/me');
    }

    $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ';
    appToast('error', $error);
}
?>

<div class="w-full max-w-[380px] bg-white rounded-lg border border-[#dedede] p-5 shadow-sm">
    <div class="mb-5 text-center">
        <div class="w-9 h-9 mx-auto mb-3 rounded-md bg-[#202223] text-white flex items-center justify-center"><i class="fa-solid fa-wallet text-sm"></i></div>
        <h2 class="text-xl font-semibold text-[#202223]">ยินดีต้อนรับ</h2>
        <p class="text-sm text-[#6d7175] mt-1">สำหรับผู้ดูแลระบบและพนักงาน</p>
    </div>
    
    <?php if ($error): ?>
        <div class="bg-[#f6f5f4] border border-[#e6e6e6] text-[#dd5b00] p-3 rounded-[5px] mb-6 text-[15px]">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="post" action="/login" class="space-y-4" autocomplete="on">
        <div>
            <label class="block text-[12px] font-semibold text-[#000000] mb-1.5 tracking-[0.125px]">ชื่อผู้ใช้</label>
            <input type="text" name="username" value="<?= htmlspecialchars((string) ($_POST['username'] ?? '')) ?>" placeholder="กรอกชื่อผู้ใช้" autocomplete="username" required autofocus class="block w-full h-9 px-3 bg-white border border-[#c9cccf] rounded-md text-sm text-[#202223] focus:outline-none focus:border-[#8c9196] placeholder-[#8c9196]">
        </div>
        
        <div>
            <label class="block text-[12px] font-semibold text-[#000000] mb-1.5 tracking-[0.125px]">รหัสผ่าน</label>
            <input type="password" name="password" placeholder="••••••••" autocomplete="current-password" required class="block w-full h-9 px-3 bg-white border border-[#c9cccf] rounded-md text-sm text-[#202223] focus:outline-none focus:border-[#8c9196] placeholder-[#8c9196]">
        </div>
        
        <div class="pt-2">
            <button type="submit" data-loading-text="กำลังเข้าสู่ระบบ..." class="w-full h-9 bg-[#303030] text-white rounded-md px-4 text-sm font-medium hover:bg-[#1f1f1f] transition-colors focus:outline-none">
                เข้าสู่ระบบ
            </button>
        </div>
    </form>
</div>
