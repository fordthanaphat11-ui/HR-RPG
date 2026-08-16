<?php
$title = "กำหนดเงินเดือน - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $jobtitle = mysqli_real_escape_string($connection, $_POST['jobtitle']);
    $salary = mysqli_real_escape_string($connection, $_POST['salary']);
    
    // Check if job exists
    $check = mysqli_query($connection, "SELECT * FROM job WHERE Job_Title='$jobtitle'");
    if (mysqli_num_rows($check) > 0) {
        $query = "UPDATE job SET basic_salary='$salary' WHERE Job_Title='$jobtitle'";
    } else {
        $query = "INSERT INTO job (Job_Title, basic_salary) VALUES ('$jobtitle', '$salary')";
    }
    
    if (mysqli_query($connection, $query)) {
        $success = "บันทึกอัตราเงินเดือนเรียบร้อยแล้ว";
    } else {
        $error = "ไม่สามารถบันทึกอัตราเงินเดือนได้: " . mysqli_error($connection);
    }
}
?>

<div class="w-full max-w-[680px] mx-auto">
    <div class="mb-5 sm:mb-7">
        <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px]">กำหนดเงินเดือนพื้นฐาน</h1>
        <p class="text-[14px] sm:text-[15px] text-[#615d59] mt-1">กำหนดหรือแก้ไขอัตราเงินเดือนพื้นฐานตามตำแหน่งงาน</p>
    </div>

    <div class="bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] p-4 sm:p-6">
        <?php if ($error): ?>
            <div class="bg-[#f6f5f4] border border-[#e6e6e6] text-[#dd5b00] p-3 rounded-[5px] mb-6 text-[15px]">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-[#f6f5f4] border border-[#e6e6e6] text-[#1aae39] p-3 rounded-[5px] mb-6 text-[15px]">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="/employee/setsalary" class="space-y-6">
            <div>
                <label class="block text-[15px] font-medium text-[#000000] mb-2">ตำแหน่งงาน</label>
                <select name="jobtitle" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    <option value="executive">เจ้าหน้าที่</option>
                    <option value="manager">ผู้จัดการ</option>
                    <option value="director">ผู้อำนวยการ</option>
                    <option value="accountant">นักบัญชี</option>
                    <option value="chief">หัวหน้าฝ่าย</option>
                </select>
            </div>
            
            <div>
                <label class="block text-[15px] font-medium text-[#000000] mb-2">เงินเดือนพื้นฐาน (บาท)</label>
                <input type="number" name="salary" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
            </div>
            
            <div class="pt-4 border-t border-[#e6e6e6]">
                <button type="submit" data-loading-text="กำลังบันทึก..." class="w-full sm:w-auto bg-[#0075de] text-[#ffffff] rounded-[8px] py-2 px-6 text-[16px] font-medium hover:bg-[#005bab] transition-colors focus:outline-none">
                    บันทึกเงินเดือน
                </button>
            </div>
        </form>
    </div>
</div>
