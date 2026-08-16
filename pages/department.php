<?php
$title = "แผนก - ระบบบริหารเงินเดือน";
require_once __DIR__ . '/../lib/db.php';

if (!isset($_SESSION['username'])) {
    header("Location: /login");
    exit;
}

$query = "SELECT * FROM department";
$result = mysqli_query($connection, $query);
$departments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $departments[] = $row;
}
?>

<div class="space-y-5 sm:space-y-6 min-w-0">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between pb-4 border-b border-[#e6e6e6]">
        <div class="min-w-0">
            <h1 class="text-[22px] sm:text-[26px] font-bold text-[#000000] tracking-[-0.625px]">แผนก</h1>
            <p class="text-[14px] sm:text-[15px] text-[#615d59] mt-1">จัดการข้อมูลแผนกทั้งหมด <?= count($departments) ?> แผนก</p>
        </div>
        <a href="/department/add" class="md:hidden w-full sm:w-auto min-h-11 px-4 rounded-[8px] bg-[#0075de] text-white font-medium inline-flex items-center justify-center gap-2"><i class="fa-solid fa-plus"></i>เพิ่มแผนก</a>
    </div>

    <section data-department-mobile-list class="js-mobile-list-section md:hidden space-y-3">
        <?php if ($departments): ?>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#a39e98]"></i>
                <input type="search" class="js-mobile-search w-full min-h-12 rounded-[9px] border border-[#e6e6e6] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0075de]" placeholder="ค้นหารหัสหรือชื่อแผนก..." aria-label="ค้นหาแผนก">
            </div>
            <div class="js-mobile-records rounded-[12px] border border-[#e6e6e6] bg-white divide-y divide-[#e6e6e6] overflow-hidden">
                <?php foreach ($departments as $index => $row): ?>
                    <article class="js-mobile-record p-4" data-name="<?= htmlspecialchars(strtolower($row['Depart_name'])) ?>" data-original="<?= $index ?>" data-search="<?= htmlspecialchars(strtolower($row['Depart_id'] . ' ' . $row['Depart_name'])) ?>">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-8 h-8 shrink-0 rounded-md bg-[#f1f1f1] text-[#5c5f62] flex items-center justify-center text-xs"><i class="fa-solid fa-building"></i></span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-[16px] break-words"><?= htmlspecialchars($row['Depart_name']) ?></p>
                                <p class="text-[13px] text-[#615d59]">รหัสแผนก #<?= htmlspecialchars($row['Depart_id']) ?></p>
                            </div>
                        </div>
                        <a href="/department/delete?id=<?= urlencode($row['Depart_id']) ?>" hx-boost="false" class="js-delete-link mt-4 w-full min-h-11 rounded-[8px] border border-red-100 bg-red-50 text-red-600 font-medium flex items-center justify-center gap-2" data-record="<?= htmlspecialchars($row['Depart_name']) ?>"><i class="fa-solid fa-trash-can"></i>ลบแผนก</a>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="js-mobile-empty-filter hidden rounded-[12px] border border-dashed border-[#d7d5d2] bg-[#f6f5f4] p-6 text-center text-[14px] text-[#615d59]">ไม่พบแผนกที่ตรงกับการค้นหา</div>
        <?php else: ?>
            <div class="rounded-[12px] border border-dashed border-[#d7d5d2] bg-[#f6f5f4] px-5 py-10 text-center">
                <i class="fa-solid fa-building-circle-xmark text-[28px] text-[#a39e98] mb-3"></i>
                <h2 class="text-[17px] font-bold">ยังไม่มีข้อมูลแผนก</h2>
                <p class="text-[14px] text-[#615d59] mt-1">เริ่มต้นโดยเพิ่มแผนกแรก</p>
                <a href="/department/add" class="mt-5 min-h-11 px-5 rounded-[8px] bg-[#0075de] text-white font-medium inline-flex items-center gap-2"><i class="fa-solid fa-plus"></i>เพิ่มแผนก</a>
            </div>
        <?php endif; ?>
    </section>

    <div class="hidden md:block bg-[#ffffff] rounded-[12px] border border-[#e6e6e6] overflow-hidden">
        <div class="min-w-0">
            <table class="js-data-table w-full text-left text-[15px] text-[#000000]" data-create-url="/department/add" data-export-name="ข้อมูลแผนก">
                <thead class="bg-[#f6f5f4] border-b border-[#e6e6e6]">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">รหัสแผนก</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59]">ชื่อแผนก</th>
                        <th scope="col" class="px-4 py-3 text-[12px] font-semibold tracking-[0.125px] text-[#615d59] text-right">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#e6e6e6]">
                    <?php if ($departments): ?>
                        <?php foreach ($departments as $row): ?>
                            <tr class="hover:bg-[#f6f5f4] transition-colors">
                                <td class="px-4 py-3 font-medium">#<?= htmlspecialchars($row['Depart_id']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($row['Depart_name']) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <a href="/department/delete?id=<?= urlencode($row['Depart_id']) ?>" hx-boost="false" class="js-delete-link text-[#31302e] hover:text-red-600 font-medium text-[15px] underline underline-offset-2" data-record="<?= htmlspecialchars($row['Depart_name']) ?>">
                                        ลบ
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="px-4 py-8 text-center text-[#615d59]">ไม่พบข้อมูลแผนก</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
