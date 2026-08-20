<?php
declare(strict_types=1);
$title = 'เปลี่ยนรหัสผ่าน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
$error = '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string) ($_POST['current_password'] ?? '');
    $newPassword = (string) ($_POST['new_password'] ?? '');
    $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
    if (!authValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) $error = 'เซสชันหมดอายุ กรุณาลองใหม่';
    elseif (strlen($newPassword) < 8) $error = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร';
    elseif ($newPassword !== $confirmPassword) $error = 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
    else {
        $employeeId = authEmployeeId();
        $stmt = mysqli_prepare($connection, 'SELECT password_hash FROM employee_accounts WHERE employee_id=? AND is_active=1 LIMIT 1');
        mysqli_stmt_bind_param($stmt, 'i', $employeeId); mysqli_stmt_execute($stmt);
        $account = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)); mysqli_stmt_close($stmt);
        if (!$account || !password_verify($currentPassword, (string) $account['password_hash'])) $error = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        elseif (password_verify($newPassword, (string) $account['password_hash'])) $error = 'รหัสผ่านใหม่ต้องต่างจากรหัสผ่านปัจจุบัน';
        else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $update = mysqli_prepare($connection, 'UPDATE employee_accounts SET password_hash=?, must_change_password=0 WHERE employee_id=?');
            mysqli_stmt_bind_param($update, 'si', $hash, $employeeId); mysqli_stmt_execute($update); mysqli_stmt_close($update);
            $_SESSION['must_change_password'] = false;
            $message = 'เปลี่ยนรหัสผ่านเรียบร้อยแล้ว';
        }
    }
}
if ($message !== '') appToast('success', $message);
if ($error !== '') appToast('error', $error);
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="mx-auto w-full max-w-xl space-y-4">
    <header class="border-b border-[#dedede] pb-3"><h1 class="text-xl font-semibold text-[#202223]">เปลี่ยนรหัสผ่าน</h1><p class="mt-0.5 text-sm text-[#6d7175]">ตั้งรหัสผ่านที่จดจำได้เฉพาะคุณและมีอย่างน้อย 8 ตัวอักษร</p></header>
    <?php if (authMustChangePassword()): ?><div class="rounded-[8px] border border-amber-200 bg-amber-50 p-4 text-[14px] text-amber-900"><i class="fa-solid fa-shield-halved mr-2"></i>นี่เป็นรหัสผ่านชั่วคราว กรุณาเปลี่ยนก่อนเข้าใช้งานส่วนอื่น</div><?php endif; ?>
    <?php if ($error): ?><div class="rounded-[8px] border border-red-200 bg-red-50 p-3 text-[14px] text-red-800"><?= $escape($error) ?></div><?php endif; ?>
    <section class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5"><form method="post" action="/me/password" class="space-y-4"><input type="hidden" name="csrf_token" value="<?= $escape(authCsrfToken()) ?>"><?php foreach ([['current_password','รหัสผ่านปัจจุบัน','current-password'],['new_password','รหัสผ่านใหม่','new-password'],['confirm_password','ยืนยันรหัสผ่านใหม่','new-password']] as $field): ?><div><label for="<?= $field[0] ?>" class="mb-1.5 block text-[13px] font-bold"><?= $field[1] ?></label><input id="<?= $field[0] ?>" name="<?= $field[0] ?>" type="password" required minlength="<?= $field[0] === 'current_password' ? 1 : 8 ?>" autocomplete="<?= $field[2] ?>" class="w-full border border-[#c9cccf] bg-white"></div><?php endforeach; ?><button type="submit" class="min-h-11 w-full rounded-[8px] bg-[#0075de] px-4 font-semibold text-white hover:bg-[#005bab]">บันทึกรหัสผ่านใหม่</button></form></section>
</div>
