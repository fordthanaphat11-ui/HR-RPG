<?php
$title = "จัดการบัญชีผู้ดูแลระบบ - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!authIsAdmin()) authRedirect('/');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_username = trim($_POST['username'] ?? '');
    $new_password = $_POST['password'] ?? '';
    
    if ($new_username && $new_password) {
        $checkStmt = mysqli_prepare($connection, "SELECT id FROM admin WHERE username=?");
        mysqli_stmt_bind_param($checkStmt, "s", $new_username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);
        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            appToast('error', 'มีชื่อผู้ใช้นี้อยู่แล้วในระบบ');
        } else {
            $res = mysqli_query($connection, "SELECT MAX(id) as max_id FROM admin");
            $row = mysqli_fetch_assoc($res);
            $new_id = ($row['max_id'] ?? 100) + 1;
            
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $insertStmt = mysqli_prepare($connection, "INSERT INTO admin (id, username, password) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($insertStmt, "iss", $new_id, $new_username, $hashed);
            if (mysqli_stmt_execute($insertStmt)) {
                appToast('success', 'เพิ่มผู้ดูแลระบบเรียบร้อยแล้ว');
            } else {
                appToast('error', 'เกิดข้อผิดพลาดในการเพิ่มข้อมูล');
            }
        }
    }
    authRedirect('/admin/accounts');
}

$admins = mysqli_query($connection, "SELECT id, username FROM admin ORDER BY id ASC");
?>

<div class="max-w-[900px] mx-auto">
    <div class="mb-6 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-[#202223] tracking-tight">ผู้ดูแลระบบ</h1>
            <p class="text-[14px] text-[#6d7175] mt-1">จัดการบัญชีและสิทธิ์การเข้าถึงของผู้ดูแลระบบ (Super Admin)</p>
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="md:col-span-1">
            <div class="bg-white rounded-xl border border-[#e6e6e6] p-5 shadow-sm">
                <h2 class="text-base font-semibold text-[#202223] mb-4"><i class="fa-solid fa-user-plus mr-2 text-[#615d59]"></i>เพิ่มผู้ดูแลระบบ</h2>
                <form method="post" action="/admin/accounts" class="space-y-4">
                    <div>
                        <label class="block text-[13px] font-medium text-[#31302e] mb-1.5">ชื่อผู้ใช้ (Username)</label>
                        <input type="text" name="username" required autocomplete="off" class="block w-full h-10 px-3 bg-white border border-[#d5d3d0] rounded-md text-[14px] text-[#202223] focus:outline-none focus:border-[#0075de]">
                    </div>
                    <div>
                        <label class="block text-[13px] font-medium text-[#31302e] mb-1.5">รหัสผ่าน</label>
                        <input type="password" name="password" required autocomplete="new-password" class="block w-full h-10 px-3 bg-white border border-[#d5d3d0] rounded-md text-[14px] text-[#202223] focus:outline-none focus:border-[#0075de]">
                    </div>
                    <div class="pt-2">
                        <button type="submit" class="w-full h-10 bg-[#303030] text-white rounded-md px-4 text-[14px] font-medium hover:bg-[#1f1f1f] transition-colors focus:outline-none shadow-sm">
                            บันทึกข้อมูล
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="md:col-span-2">
            <div class="bg-white rounded-xl border border-[#e6e6e6] overflow-hidden shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-[14px] text-[#31302e]">
                        <thead class="bg-[#f6f5f4] border-b border-[#e6e6e6]">
                            <tr>
                                <th scope="col" class="px-5 py-3.5 text-[12px] font-semibold text-[#615d59] uppercase tracking-wider">ID</th>
                                <th scope="col" class="px-5 py-3.5 text-[12px] font-semibold text-[#615d59] uppercase tracking-wider">ชื่อผู้ใช้</th>
                                <th scope="col" class="px-5 py-3.5 text-[12px] font-semibold text-[#615d59] uppercase tracking-wider text-right">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e6e6e6]">
                            <?php while ($row = mysqli_fetch_assoc($admins)): ?>
                                <tr class="hover:bg-[#faf9f8] transition-colors">
                                    <td class="px-5 py-3 font-medium text-[#615d59]">#<?= htmlspecialchars((string)$row['id']) ?></td>
                                    <td class="px-5 py-3 font-medium text-[#202223]"><?= htmlspecialchars((string)$row['username']) ?>
                                        <?php if ($row['username'] === $_SESSION['username']): ?>
                                            <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800">คุณ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <?php if ($row['username'] !== $_SESSION['username']): ?>
                                            <button type="button" class="text-red-600 hover:text-red-800 focus:outline-none" onclick="document.getElementById('deleteModalRecord').textContent='ผู้ดูแลระบบ: <?= htmlspecialchars((string)$row['username']) ?>'; document.getElementById('confirmDeleteLink').href='/admin/accounts/delete?id=<?= $row['id'] ?>'; document.getElementById('deleteConfirmModal').classList.remove('hidden'); document.getElementById('deleteConfirmModal').classList.add('flex');" title="ลบผู้ดูแลระบบ">
                                                <i class="fa-solid fa-trash-can"></i> ลบ
                                            </button>
                                        <?php else: ?>
                                            <span class="text-[#a39e98] text-[13px]"><i class="fa-solid fa-shield"></i> บัญชีหลัก</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
