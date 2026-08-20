<?php
declare(strict_types=1);
$title = 'บัญชีพนักงาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';

$message = '';
$error = '';
$executeAccountStatement = static function (mysqli_stmt $statement): array {
    try {
        $ok = mysqli_stmt_execute($statement);
        return [$ok, $ok ? '' : mysqli_stmt_error($statement)];
    } catch (Throwable $exception) {
        return [false, $exception->getMessage()];
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!authValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) {
        $error = 'เซสชันหมดอายุ กรุณาลองใหม่อีกครั้ง';
    } else {
        $employeeId = filter_var($_POST['employee_id'] ?? null, FILTER_VALIDATE_INT);
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if (!$employeeId || !preg_match('/^[\p{L}\p{N}._@-]{3,100}$/u', $username)) {
            $error = 'ชื่อผู้ใช้ต้องมี 3–100 ตัวอักษร และใช้ได้เฉพาะตัวอักษร ตัวเลข จุด ขีด และ @';
        } elseif ($password !== '' && strlen($password) < 8) {
            $error = 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร';
        } else {
            $lookup = mysqli_prepare($connection, 'SELECT id, password_hash FROM employee_accounts WHERE employee_id=? LIMIT 1');
            mysqli_stmt_bind_param($lookup, 'i', $employeeId);
            mysqli_stmt_execute($lookup);
            $existing = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup));
            mysqli_stmt_close($lookup);

            if (!$existing && $password === '') {
                $error = 'บัญชีใหม่ต้องกำหนดรหัสผ่านชั่วคราว';
            } else {
                $passwordHash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : (string) $existing['password_hash'];
                $mustChange = $password !== '' ? 1 : 0;
                if ($existing && $password === '') {
                    $update = mysqli_prepare($connection, 'UPDATE employee_accounts SET username=?, is_active=? WHERE employee_id=?');
                    mysqli_stmt_bind_param($update, 'sii', $username, $isActive, $employeeId);
                    [$ok, $dbError] = $executeAccountStatement($update);
                    mysqli_stmt_close($update);
                } elseif ($existing) {
                    $update = mysqli_prepare($connection, 'UPDATE employee_accounts SET username=?, password_hash=?, is_active=?, must_change_password=? WHERE employee_id=?');
                    mysqli_stmt_bind_param($update, 'ssiii', $username, $passwordHash, $isActive, $mustChange, $employeeId);
                    [$ok, $dbError] = $executeAccountStatement($update);
                    mysqli_stmt_close($update);
                } else {
                    $createdBy = (string) $_SESSION['username'];
                    $insert = mysqli_prepare($connection, 'INSERT INTO employee_accounts (employee_id,username,password_hash,is_active,must_change_password,created_by) VALUES (?,?,?,?,?,?)');
                    mysqli_stmt_bind_param($insert, 'issiis', $employeeId, $username, $passwordHash, $isActive, $mustChange, $createdBy);
                    [$ok, $dbError] = $executeAccountStatement($insert);
                    mysqli_stmt_close($insert);
                }

                if (!empty($ok)) {
                    $message = $password !== '' ? 'บันทึกบัญชีและตั้งรหัสผ่านชั่วคราวแล้ว' : 'อัปเดตสถานะบัญชีแล้ว';
                } else {
                    $error = str_contains(strtolower((string) $dbError), 'duplicate') ? 'ชื่อผู้ใช้นี้ถูกใช้แล้ว กรุณาเลือกชื่ออื่น' : 'บันทึกบัญชีไม่สำเร็จ';
                }
            }
        }
    }
}
if ($message !== '') appToast('success', $message);
if ($error !== '') appToast('error', $error);

$result = mysqli_query($connection, "SELECT e.Employee_id, e.Name, e.Email, e.jobtitle, d.Depart_name,
                                            a.username, a.is_active, a.must_change_password, a.last_login_at
                                     FROM employee e
                                     LEFT JOIN department d ON d.Depart_id=e.Depart_id
                                     LEFT JOIN employee_accounts a ON a.employee_id=e.Employee_id
                                     ORDER BY e.Name, e.Employee_id");
$employees = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
$accountCount = count(array_filter($employees, static fn(array $row): bool => !empty($row['username'])));
$activeCount = count(array_filter($employees, static fn(array $row): bool => !empty($row['username']) && (int) $row['is_active'] === 1));
$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<div class="w-full space-y-4">
    <header class="flex flex-col gap-3 border-b border-[#dedede] pb-3 sm:flex-row sm:items-end sm:justify-between">
        <div><h1 class="text-xl font-semibold text-[#202223]">บัญชีเข้าสู่ระบบของพนักงาน</h1><p class="mt-0.5 text-sm text-[#6d7175]">สร้างบัญชี รีเซ็ตรหัสผ่าน หรือระงับการเข้าใช้ โดยไม่กระทบข้อมูลพนักงาน</p></div>
        <div class="flex gap-2 text-[12px]"><span class="rounded-full bg-white px-3 py-1.5 border border-[#dedede]">มีบัญชี <?= $accountCount ?> คน</span><span class="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">ใช้งาน <?= $activeCount ?> คน</span></div>
    </header>

    <?php if ($error): ?><div class="rounded-[8px] border border-red-200 bg-red-50 p-3 text-[14px] text-red-800"><i class="fa-solid fa-circle-exclamation mr-2"></i><?= $escape($error) ?></div><?php endif; ?>

    <section class="overflow-hidden rounded-lg border border-[#dedede] bg-white">
        <div class="border-b border-[#e6e6e6] p-4"><h2 class="font-bold">รายชื่อพนักงาน</h2><p class="mt-0.5 text-[13px] text-[#615d59]">รหัสผ่านที่ตั้งใหม่เป็นรหัสชั่วคราว พนักงานต้องเปลี่ยนเมื่อเข้าสู่ระบบครั้งแรก</p></div>
        <div class="hidden md:block">
            <table class="js-data-table w-full text-left text-[14px]" data-export-name="บัญชีพนักงาน">
                <thead><tr><th>พนักงาน</th><th>แผนก / ตำแหน่ง</th><th>ชื่อผู้ใช้</th><th>สถานะ</th><th>เข้าใช้ล่าสุด</th><th data-orderable="false">จัดการ</th></tr></thead>
                <tbody>
                <?php foreach ($employees as $employee): $hasAccount = !empty($employee['username']); ?>
                    <tr>
                        <td><p class="font-bold"><?= $escape($employee['Name']) ?></p><p class="text-[12px] text-[#615d59]">รหัส <?= $escape($employee['Employee_id']) ?> · <?= $escape($employee['Email'] ?: 'ไม่มีอีเมล') ?></p></td>
                        <td><p><?= $escape($employee['Depart_name'] ?: 'ไม่ระบุแผนก') ?></p><p class="text-[12px] text-[#615d59]"><?= $escape($employee['jobtitle']) ?></p></td>
                        <td><?= $hasAccount ? '<span class="font-mono text-[13px]">'.$escape($employee['username']).'</span>' : '<span class="text-[#8a8580]">ยังไม่มีบัญชี</span>' ?></td>
                        <td><?php if (!$hasAccount): ?><span class="rounded-full bg-[#f2f2f2] px-2.5 py-1 text-[12px] text-[#615d59]">ยังไม่สร้าง</span><?php elseif ((int) $employee['is_active'] === 1): ?><span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-semibold text-emerald-700">ใช้งานได้</span><?php else: ?><span class="rounded-full bg-red-50 px-2.5 py-1 text-[12px] font-semibold text-red-700">ระงับ</span><?php endif; ?></td>
                        <td class="text-[13px] text-[#615d59]"><?= $employee['last_login_at'] ? $escape(date('d/m/Y H:i', strtotime($employee['last_login_at']))) : 'ยังไม่เคยเข้าใช้' ?></td>
                        <td><button type="button" data-account-open="<?= $escape($employee['Employee_id']) ?>" class="min-h-9 rounded-[7px] border border-[#d5d3d0] px-3 text-[12px] font-semibold hover:bg-[#f6f5f4]"><i class="fa-solid <?= $hasAccount ? 'fa-pen' : 'fa-user-plus' ?> mr-1.5"></i><?= $hasAccount ? 'แก้ไข' : 'สร้างบัญชี' ?></button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-[#e6e6e6] md:hidden">
            <?php foreach ($employees as $employee): $hasAccount = !empty($employee['username']); ?>
                <article class="p-4"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><p class="truncate font-bold"><?= $escape($employee['Name']) ?></p><p class="text-[12px] text-[#615d59]"><?= $escape($employee['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · รหัส <?= $escape($employee['Employee_id']) ?></p><?php if ($hasAccount): ?><p class="mt-1 font-mono text-[13px]"><?= $escape($employee['username']) ?></p><?php endif; ?></div><span class="shrink-0 rounded-full px-2.5 py-1 text-[12px] <?= !$hasAccount ? 'bg-[#f2f2f2] text-[#615d59]' : ((int) $employee['is_active'] === 1 ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700') ?>"><?= !$hasAccount ? 'ยังไม่มีบัญชี' : ((int) $employee['is_active'] === 1 ? 'ใช้งานได้' : 'ระงับ') ?></span></div><button type="button" data-account-open="<?= $escape($employee['Employee_id']) ?>" class="mt-3 min-h-10 w-full rounded-[7px] border border-[#d5d3d0] px-3 text-[13px] font-semibold"><?= $hasAccount ? 'แก้ไขบัญชี' : 'สร้างบัญชี' ?></button></article>
            <?php endforeach; ?>
        </div>
    </section>

    <div id="accountModal" class="fixed inset-0 z-[80] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle">
        <div class="absolute inset-0 bg-black/40" data-account-close></div>
        <div class="relative w-full max-w-lg rounded-[12px] border border-[#dedede] bg-white p-5 shadow-2xl">
            <div class="flex items-start justify-between gap-3"><div><h2 id="accountModalTitle" class="text-[18px] font-bold">จัดการบัญชีพนักงาน</h2><p id="accountEmployeeName" class="mt-0.5 text-[13px] text-[#615d59]"></p></div><button type="button" data-account-close class="h-9 w-9 rounded-[7px] hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div>
            <form method="post" action="/employee/accounts" class="mt-5 space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $escape(authCsrfToken()) ?>"><input id="accountEmployeeId" type="hidden" name="employee_id">
                <div><label for="accountUsername" class="mb-1.5 block text-[13px] font-bold">ชื่อผู้ใช้</label><input id="accountUsername" name="username" required minlength="3" maxlength="100" class="w-full border border-[#c9cccf] bg-white" autocomplete="off"><p class="mt-1 text-[12px] text-[#615d59]">ใช้ตัวอักษร ตัวเลข จุด ขีด หรือ @</p></div>
                <div><label for="accountPassword" class="mb-1.5 block text-[13px] font-bold">รหัสผ่านชั่วคราว <span id="accountPasswordOptional" class="font-normal text-[#615d59]"></span></label><div class="relative"><input id="accountPassword" name="password" type="password" minlength="8" class="w-full border border-[#c9cccf] bg-white pr-11" autocomplete="new-password"><button type="button" data-toggle-password="accountPassword" class="absolute right-1 top-1/2 h-9 w-9 -translate-y-1/2 rounded-[6px] text-[#615d59]" aria-label="แสดงหรือซ่อนรหัสผ่าน"><i class="fa-solid fa-eye"></i></button></div><p class="mt-1 text-[12px] text-[#615d59]">อย่างน้อย 8 ตัวอักษร หากกรอกใหม่ พนักงานต้องเปลี่ยนรหัสเมื่อเข้าใช้</p></div>
                <label class="flex cursor-pointer items-center gap-3 rounded-[8px] border border-[#e6e6e6] p-3"><input id="accountActive" type="checkbox" name="is_active" value="1" class="h-4 w-4 accent-[#0075de]"><span><span class="block text-[14px] font-bold">อนุญาตให้เข้าสู่ระบบ</span><span class="block text-[12px] text-[#615d59]">นำเครื่องหมายออกเพื่อระงับบัญชีชั่วคราว</span></span></label>
                <div class="grid grid-cols-2 gap-3 pt-1"><button type="button" data-account-close class="min-h-11 rounded-[8px] border border-[#d5d3d0] font-semibold">ยกเลิก</button><button type="submit" class="min-h-11 rounded-[8px] bg-[#0075de] px-4 font-semibold text-white hover:bg-[#005bab]">บันทึกบัญชี</button></div>
            </form>
        </div>
    </div>
</div>

<script type="application/json" id="employeeAccountData"><?= json_encode(array_map(static fn(array $e): array => ['id'=>(string)$e['Employee_id'],'name'=>$e['Name'],'username'=>$e['username'] ?: ('emp'.$e['Employee_id']),'active'=>!empty($e['username']) ? (int)$e['is_active'] === 1 : true,'exists'=>!empty($e['username'])], $employees), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script>
(() => {
    const modal = document.getElementById('accountModal'); if (!modal) return;
    const records = JSON.parse(document.getElementById('employeeAccountData').textContent || '[]');
    const byId = Object.fromEntries(records.map(row => [row.id, row]));
    const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
    document.querySelectorAll('[data-account-open]').forEach(button => button.addEventListener('click', () => {
        const row = byId[button.dataset.accountOpen]; if (!row) return;
        document.getElementById('accountEmployeeId').value = row.id;
        document.getElementById('accountEmployeeName').textContent = row.name + ' · รหัส ' + row.id;
        document.getElementById('accountUsername').value = row.username;
        document.getElementById('accountActive').checked = row.active;
        document.getElementById('accountPassword').value = '';
        document.getElementById('accountPassword').required = !row.exists;
        document.getElementById('accountPasswordOptional').textContent = row.exists ? '(เว้นว่างหากไม่เปลี่ยน)' : '';
        modal.classList.remove('hidden'); modal.classList.add('flex');
        requestAnimationFrame(() => document.getElementById('accountUsername').focus());
    }));
    document.querySelectorAll('[data-account-close]').forEach(button => button.addEventListener('click', close));
    document.querySelectorAll('[data-toggle-password]').forEach(button => button.addEventListener('click', () => { const input = document.getElementById(button.dataset.togglePassword); input.type = input.type === 'password' ? 'text' : 'password'; }));
    document.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
})();
</script>
