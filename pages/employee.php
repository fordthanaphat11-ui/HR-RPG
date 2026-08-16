<?php
$title = "พนักงาน - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$sql = "SELECT employee.*, job.basic_salary FROM employee INNER JOIN job ON employee.jobtitle = job.Job_Title";
$result = mysqli_query($connection, $sql);
$employees = [];
while ($row = mysqli_fetch_assoc($result)) {
    $employees[] = $row;
}
$jobLabels = ['executive' => 'เจ้าหน้าที่', 'manager' => 'ผู้จัดการ', 'director' => 'ผู้อำนวยการ', 'accountant' => 'นักบัญชี', 'chief' => 'หัวหน้าฝ่าย'];
$departments = array_values(array_unique(array_column($employees, 'Depart_id')));
sort($departments);
?>

<div class="space-y-5 sm:space-y-6 min-w-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pb-4 border-b border-[#e6e6e6]">
        <div class="min-w-0">
            <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px]">พนักงาน</h1>
            <p class="text-[14px] sm:text-[15px] text-[#615d59] mt-1">จัดการข้อมูลพนักงานทั้งหมด <?= count($employees) ?> คน</p>
        </div>
        <a href="/employee/add" class="md:hidden w-full sm:w-auto min-h-11 px-4 rounded-[8px] bg-[#0075de] text-white font-medium inline-flex items-center justify-center gap-2 hover:bg-[#005bab]">
            <i class="fa-solid fa-plus" aria-hidden="true"></i><span>เพิ่มพนักงาน</span>
        </a>
    </div>

    <section data-employee-mobile-list class="js-mobile-list-section md:hidden space-y-3">
        <?php if ($employees): ?>
            <div class="space-y-2">
                <label for="mobileEmployeeSearch" class="sr-only">ค้นหาพนักงาน</label>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#a39e98]" aria-hidden="true"></i>
                    <input id="mobileEmployeeSearch" type="search" class="js-mobile-search w-full min-h-12 rounded-[9px] border border-[#e6e6e6] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0075de]" placeholder="ค้นหาชื่อ รหัส อีเมล หรือเบอร์โทร...">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <select class="js-mobile-filter w-full min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px]">
                        <option value="">ทุกแผนก</option>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars($department) ?>">แผนก <?= htmlspecialchars($department) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="js-mobile-sort w-full min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px]">
                        <option value="original">เรียงตามค่าเริ่มต้น</option>
                        <option value="name-asc">ชื่อ ก-ฮ</option>
                        <option value="name-desc">ชื่อ ฮ-ก</option>
                        <option value="salary-asc">เงินเดือนน้อย-มาก</option>
                        <option value="salary-desc">เงินเดือนมาก-น้อย</option>
                    </select>
                </div>
            </div>

            <div class="js-mobile-records rounded-[12px] border border-[#e6e6e6] bg-white divide-y divide-[#e6e6e6] overflow-hidden">
                <?php foreach ($employees as $index => $row): ?>
                    <article class="js-mobile-record p-4" data-filter="<?= htmlspecialchars($row['Depart_id']) ?>" data-name="<?= htmlspecialchars(strtolower($row['Name'])) ?>" data-salary="<?= (float) $row['basic_salary'] ?>" data-original="<?= $index ?>" data-search="<?= htmlspecialchars(strtolower(implode(' ', [$row['Employee_id'], $row['Name'], $row['Email'], $row['Phone_no'], $jobLabels[$row['jobtitle']] ?? $row['jobtitle'], $row['Depart_id']]))) ?>">
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="w-10 h-10 shrink-0 rounded-full bg-[#eef6fd] text-[#0075de] flex items-center justify-center font-bold">
                                <i class="fa-solid fa-user text-sm" aria-hidden="true"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-[16px] font-bold truncate"><?= htmlspecialchars($row['Name']) ?></p>
                                        <p class="text-[13px] text-[#615d59]">รหัส #<?= htmlspecialchars($row['Employee_id']) ?></p>
                                    </div>
                                    <span class="shrink-0 px-2 py-1 bg-[#f6f5f4] border border-[#e6e6e6] rounded-[5px] text-[12px] font-medium"><?= htmlspecialchars($jobLabels[$row['jobtitle']] ?? $row['jobtitle']) ?></span>
                                </div>
                                <dl class="mt-4 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-[14px]">
                                    <dt class="text-[#615d59]">แผนก</dt><dd class="text-right font-medium break-words"><?= htmlspecialchars($row['Depart_id']) ?></dd>
                                    <dt class="text-[#615d59]">เงินเดือน</dt><dd class="text-right font-bold">฿<?= number_format($row['basic_salary']) ?></dd>
                                    <dt class="text-[#615d59]">โทรศัพท์</dt><dd class="text-right break-all"><?= htmlspecialchars($row['Phone_no']) ?></dd>
                                </dl>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2 mt-4">
                            <a href="/employee/update?id=<?= urlencode($row['Employee_id']) ?>" class="min-h-11 rounded-[8px] border border-[#d9e9f8] bg-[#f7fbff] text-[#0075de] font-medium flex items-center justify-center gap-2"><i class="fa-solid fa-pen"></i>แก้ไข</a>
                            <a href="/employee/delete?id=<?= urlencode($row['Employee_id']) ?>" hx-boost="false" class="js-delete-link min-h-11 rounded-[8px] border border-red-100 bg-red-50 text-red-600 font-medium flex items-center justify-center gap-2" data-record="<?= htmlspecialchars($row['Name']) ?>"><i class="fa-solid fa-trash-can"></i>ลบ</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="js-mobile-empty-filter hidden rounded-[12px] border border-dashed border-[#d7d5d2] bg-[#f6f5f4] p-6 text-center text-[14px] text-[#615d59]">ไม่พบพนักงานที่ตรงกับการค้นหา</div>
        <?php else: ?>
            <div class="rounded-[12px] border border-dashed border-[#d7d5d2] bg-[#f6f5f4] px-5 py-10 text-center">
                <i class="fa-solid fa-user-plus text-[28px] text-[#a39e98] mb-3"></i>
                <h2 class="text-[17px] font-bold">ยังไม่มีข้อมูลพนักงาน</h2>
                <p class="text-[14px] text-[#615d59] mt-1">เริ่มต้นโดยเพิ่มพนักงานคนแรก</p>
                <a href="/employee/add" class="mt-5 min-h-11 px-5 rounded-[8px] bg-[#0075de] text-white font-medium inline-flex items-center gap-2"><i class="fa-solid fa-plus"></i>เพิ่มพนักงาน</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="hidden md:block bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] overflow-hidden">
        <div class="min-w-0">
            <table class="js-data-table w-full text-left text-[15px] text-[#000000]" data-create-url="/employee/add" data-export-name="ข้อมูลพนักงาน">
                <thead class="bg-[#f6f5f4] border-b border-[#e6e6e6] whitespace-nowrap">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">รหัส</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">ชื่อ-นามสกุล</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">ข้อมูลติดต่อ</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">ตำแหน่ง</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">เงินเดือน</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">แผนก</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e6e6]">
                    <?php if ($employees): ?>
                        <?php foreach ($employees as $row): ?>
                            <tr class="hover:bg-[#f6f5f4] transition-colors">
                                <td class="px-4 py-3 font-medium">#<?= htmlspecialchars($row['Employee_id']) ?></td>
                                <td class="px-4 py-3">
                                    <div class="font-medium"><?= htmlspecialchars($row['Name']) ?></div>
                                    <div class="text-[14px] text-[#615d59]"><?= $row['gender'] === 'female' ? 'หญิง' : 'ชาย' ?></div>
                                </td>
                                <td class="px-4 py-3 text-[14px]">
                                    <div><?= htmlspecialchars($row['Email']) ?></div>
                                    <div class="text-[#615d59]"><?= htmlspecialchars($row['Phone_no']) ?></div>
                                </td>
                                <td class="px-4 py-3 capitalize">
                                    <span class="px-2 py-1 bg-[#f6f5f4] border border-[#e6e6e6] rounded-[4px] text-[12px] font-medium text-[#31302e]">
                                        <?= htmlspecialchars($jobLabels[$row['jobtitle']] ?? $row['jobtitle']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-medium">฿<?= number_format($row['basic_salary']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['Depart_id']) ?></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap space-x-3">
                                    <a href="/employee/update?id=<?= $row['Employee_id'] ?>" class="text-[#0075de] hover:text-[#005bab] font-medium underline underline-offset-2">
                                        แก้ไข
                                    </a>
                                    <a href="/employee/delete?id=<?= urlencode($row['Employee_id']) ?>" hx-boost="false" class="js-delete-link text-[#31302e] hover:text-red-600 font-medium underline underline-offset-2" data-record="<?= htmlspecialchars($row['Name']) ?>">
                                        ลบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-[#615d59]">ไม่พบข้อมูลพนักงาน</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
