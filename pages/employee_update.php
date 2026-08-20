<?php
$title = "แก้ไขพนักงาน - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$error = '';
$success = '';

if (!isset($_GET['id']) && !isset($_POST['empid'])) {
    header("Location: /employee");
    exit;
}

$id = isset($_GET['id']) ? mysqli_real_escape_string($connection, $_GET['id']) : mysqli_real_escape_string($connection, $_POST['empid']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = mysqli_real_escape_string($connection, $_POST['name']);
    $dob = mysqli_real_escape_string($connection, $_POST['dob']);
    $gender = mysqli_real_escape_string($connection, $_POST['gender']);
    $sdate = mysqli_real_escape_string($connection, $_POST['sdate']);
    $email = mysqli_real_escape_string($connection, $_POST['email']);
    $phone = mysqli_real_escape_string($connection, $_POST['phone']);
    $loan = mysqli_real_escape_string($connection, $_POST['loan']);
    $pfund = mysqli_real_escape_string($connection, $_POST['pfund']);
    $jobtitle = mysqli_real_escape_string($connection, $_POST['jobtitle']);
    $address = mysqli_real_escape_string($connection, $_POST['address']);
    $depid = mysqli_real_escape_string($connection, $_POST['depid']);
    $manid = !empty($_POST['manid']) ? mysqli_real_escape_string($connection, $_POST['manid']) : 'NULL';
    $bacc = mysqli_real_escape_string($connection, $_POST['bacc']);
    
    $query = "UPDATE `employee` SET 
                `Name` = '$name', `Address` = '$address', `Phone_no` = '$phone', 
                `Email` = '$email', `Start_date` = '$sdate', `dob` = '$dob', 
                `gender` = '$gender', `loan` = '$loan', `p_fund` = '$pfund', 
                `jobtitle` = '$jobtitle', `Depart_id` = '$depid', 
                `managesDepart_id` = $manid, `bank_accno` = '$bacc' 
              WHERE `Employee_id` = '$id'";
              
    if (mysqli_query($connection, $query)) {
        appFlashToast('success', 'แก้ไขข้อมูลพนักงานเรียบร้อยแล้ว');
        header('Location: /employee');
        exit;
    } else {
        $error = "ไม่สามารถแก้ไขข้อมูลพนักงานได้: " . mysqli_error($connection);
        appToast('error', 'ไม่สามารถแก้ไขข้อมูลพนักงานได้ กรุณาตรวจสอบข้อมูลแล้วลองใหม่');
    }
}

$rec = mysqli_query($connection, "SELECT * FROM employee WHERE Employee_id='$id'");
if (mysqli_num_rows($rec) === 0) {
    header("Location: /employee");
    exit;
}
$employee = mysqli_fetch_assoc($rec);
?>

<div class="w-full max-w-[900px] mx-auto">
    <div class="mb-5 sm:mb-7">
        <a href="/employee" class="text-[#615d59] hover:text-[#000000] text-[15px] inline-flex items-center mb-4 transition-colors">
            <span class="sm:hidden">&larr; พนักงาน</span><span class="hidden sm:inline">&larr; กลับไปยังรายการพนักงาน</span>
        </a>
        <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px] break-words">แก้ไขพนักงาน #<?= htmlspecialchars($id) ?></h1>
    </div>

    <div class="bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] p-4 sm:p-6 lg:p-8">
        <?php if ($error): ?>
            <div class="bg-[#f6f5f4] border border-[#e6e6e6] text-[#dd5b00] p-3 rounded-[5px] mb-6 text-[15px]">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="/employee/update" class="space-y-6 lg:space-y-8">
            <input type="hidden" name="empid" value="<?= htmlspecialchars($id) ?>">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 lg:gap-x-8 gap-y-5 lg:gap-y-6">
                <!-- Section 1 -->
                <div class="space-y-6">
                    <h3 class="text-[16px] font-semibold text-[#000000] border-b border-[#e6e6e6] pb-2">ข้อมูลส่วนบุคคล</h3>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">ชื่อ-นามสกุล</label>
                        <input type="text" name="name" value="<?= htmlspecialchars($employee['Name']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">วันเดือนปีเกิด</label>
                        <input type="date" name="dob" value="<?= htmlspecialchars($employee['dob']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">เพศ</label>
                        <div class="flex items-center space-x-6">
                            <label class="flex items-center">
                                <input type="radio" name="gender" value="male" <?= $employee['gender'] === 'male' ? 'checked' : '' ?> class="text-[#0075de]">
                                <span class="ml-2 text-[15px] text-[#000000]">ชาย</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="gender" value="female" <?= $employee['gender'] === 'female' ? 'checked' : '' ?> class="text-[#0075de]">
                                <span class="ml-2 text-[15px] text-[#000000]">หญิง</span>
                            </label>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">ที่อยู่</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($employee['Address']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                </div>
                
                <!-- Section 2 -->
                <div class="space-y-6">
                    <h3 class="text-[16px] font-semibold text-[#000000] border-b border-[#e6e6e6] pb-2">ข้อมูลการทำงาน</h3>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">อีเมล</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($employee['Email']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">หมายเลขโทรศัพท์</label>
                        <input type="text" name="phone" value="<?= htmlspecialchars($employee['Phone_no']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">วันที่เริ่มงาน</label>
                        <input type="date" name="sdate" value="<?= htmlspecialchars($employee['Start_date']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                    </div>
                    
                    <div>
                        <label class="block text-[15px] font-medium text-[#000000] mb-2">ตำแหน่งงาน</label>
                        <select name="jobtitle" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                            <option value="executive" <?= $employee['jobtitle'] === 'executive' ? 'selected' : '' ?>>เจ้าหน้าที่</option>
                            <option value="manager" <?= $employee['jobtitle'] === 'manager' ? 'selected' : '' ?>>ผู้จัดการ</option>
                            <option value="director" <?= $employee['jobtitle'] === 'director' ? 'selected' : '' ?>>ผู้อำนวยการ</option>
                            <option value="accountant" <?= $employee['jobtitle'] === 'accountant' ? 'selected' : '' ?>>นักบัญชี</option>
                            <option value="chief" <?= $employee['jobtitle'] === 'chief' ? 'selected' : '' ?>>หัวหน้าฝ่าย</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 lg:gap-x-8 gap-y-5 lg:gap-y-6 pt-4 border-t border-[#e6e6e6]">
                <div>
                    <label class="block text-[15px] font-medium text-[#000000] mb-2">ยอดเงินกู้ (บาท)</label>
                    <input type="number" name="loan" value="<?= htmlspecialchars($employee['loan']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                </div>
                <div>
                    <label class="block text-[15px] font-medium text-[#000000] mb-2">กองทุนสำรองเลี้ยงชีพ (บาท)</label>
                    <input type="number" name="pfund" value="<?= htmlspecialchars($employee['p_fund']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                </div>
                <div>
                    <label class="block text-[15px] font-medium text-[#000000] mb-2">เลขที่บัญชีธนาคาร</label>
                    <input type="number" name="bacc" value="<?= htmlspecialchars($employee['bank_accno']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                </div>
                <div>
                    <label class="block text-[15px] font-medium text-[#000000] mb-2">รหัสแผนก</label>
                    <input type="number" name="depid" value="<?= htmlspecialchars($employee['Depart_id']) ?>" required class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                </div>
                <div>
                    <label class="block text-[15px] font-medium text-[#000000] mb-2">รหัสแผนกที่บริหาร</label>
                    <input type="number" name="manid" value="<?= htmlspecialchars($employee['managesDepart_id']) ?>" class="block w-full px-[6px] py-[6px] bg-[#ffffff] border border-[#e6e6e6] rounded-[4px] text-[15px] text-[#000000] focus:outline-none focus:border-[#0075de]">
                </div>
            </div>
            
            <div class="pt-6 border-t border-[#e6e6e6]">
                <button type="submit" data-loading-text="กำลังบันทึก..." class="w-full sm:w-auto bg-[#0075de] text-[#ffffff] rounded-[8px] py-2 px-6 text-[16px] font-medium hover:bg-[#005bab] transition-colors focus:outline-none">
                    บันทึกการแก้ไข
                </button>
            </div>
        </form>
    </div>
</div>
