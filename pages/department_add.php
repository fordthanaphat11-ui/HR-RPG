<?php
$title = "เพิ่มแผนก - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_id = mysqli_real_escape_string($connection, $_POST['depart_id']);
    $dept_name = mysqli_real_escape_string($connection, $_POST['depart_name']);
    
    $query = "INSERT INTO department (Depart_id, Depart_name) VALUES ('$dept_id', '$dept_name')";
    
    if (mysqli_query($connection, $query)) {
        appFlashToast('success', 'เพิ่มแผนกเรียบร้อยแล้ว');
        header('Location: /department');
        exit;
    } else {
        $error = "ไม่สามารถเพิ่มแผนกได้: " . mysqli_error($connection);
        appToast('error', 'ไม่สามารถเพิ่มแผนกได้ กรุณาตรวจสอบข้อมูลแล้วลองใหม่');
    }
}
?>

<div class="w-full max-w-[680px] mx-auto">
    <div class="mb-5 sm:mb-7">
        <a href="/department" class="text-[#615d59] hover:text-[#000000] text-[15px] inline-flex items-center mb-4 transition-colors">
            <span class="sm:hidden">&larr; แผนก</span><span class="hidden sm:inline">&larr; กลับไปยังรายการแผนก</span>
        </a>
        <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px]">เพิ่มแผนก</h1>
    </div>

    <div class="bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] p-4 sm:p-6">
        <?php if ($error): ?>
            <div class="bg-[#f6f5f4] border border-[#e6e6e6] text-[#dd5b00] p-3 rounded-[5px] mb-6 text-[15px]">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="/department/add" class="space-y-6">
            <div>
                <label class="block text-[15px] font-medium text-[#000000] mb-2">รหัสแผนก</label>
                <input type="number" name="depart_id" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div>
                <label class="block text-[15px] font-medium text-[#000000] mb-2">ชื่อแผนก</label>
                <input type="text" name="depart_name" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div class="pt-4 border-t border-[#e6e6e6]">
                <button type="submit" data-loading-text="กำลังบันทึก..." class="w-full sm:w-auto bg-[#0075de] text-[#ffffff] rounded-[8px] py-2 px-6 text-[16px] font-medium hover:bg-[#005bab] transition-colors focus:outline-none">
                    บันทึก
                </button>
            </div>
        </form>
    </div>
</div>
