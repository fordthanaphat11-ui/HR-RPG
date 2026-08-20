<?php
declare(strict_types=1);

$title = 'กำหนดเงินเดือนพื้นฐาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/salary.php';

if (!authIsAdmin()) authRedirect('/login');
function salaryEscape(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }

$jobLabels = ['executive'=>'เจ้าหน้าที่','manager'=>'ผู้จัดการ','director'=>'ผู้อำนวยการ','accountant'=>'นักบัญชี','chief'=>'หัวหน้าฝ่าย'];
$error = $success = '';
$selectedId = trim((string) ($_POST['employee_id'] ?? $_GET['employee_id'] ?? ''));
$effectiveDate = trim((string) ($_POST['effective_from'] ?? date('Y-m-d')));
$salaryDisplay = trim((string) ($_POST['salary_display'] ?? ''));
$salaryAmountInput = trim((string) ($_POST['salary_amount'] ?? ''));
$reasonInput = trim((string) ($_POST['reason'] ?? ''));
$noteInput = trim((string) ($_POST['note'] ?? ''));
$reasonOptions = ['ปรับเงินเดือนประจำปี','ผ่านทดลองงาน','เลื่อนตำแหน่ง','ปรับตามโครงสร้างบริษัท','อื่น ๆ'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!authValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        if (($_POST['confirmed'] ?? '') !== '1') throw new RuntimeException('กรุณาตรวจสอบและยืนยันข้อมูลก่อนบันทึก');
        if ($selectedId === '' || !ctype_digit($selectedId)) throw new RuntimeException('กรุณาเลือกพนักงาน');
        $employeeId = (int) $selectedId;
        $amount = payrollNumericValue($salaryAmountInput);
        if ($amount === null || $amount <= 0) throw new RuntimeException('เงินเดือนพื้นฐานต้องเป็นตัวเลขมากกว่า 0');
        if ($amount > 9999999999.99) throw new RuntimeException('จำนวนเงินสูงเกินขอบเขตที่ระบบรองรับ กรุณาตรวจสอบอีกครั้ง');
        if (!salaryIsValidDate($effectiveDate)) throw new RuntimeException('วันที่มีผลไม่ถูกต้อง');
        if ($reasonInput !== '' && !in_array($reasonInput, $reasonOptions, true)) throw new RuntimeException('เหตุผลที่เลือกไม่ถูกต้อง');

        mysqli_begin_transaction($connection);
        $employeeStmt = mysqli_prepare($connection, 'SELECT Employee_id, Name, Start_date FROM employee WHERE Employee_id=? FOR UPDATE');
        mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
        mysqli_stmt_execute($employeeStmt);
        $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
        if (!$employee) throw new RuntimeException('ไม่พบพนักงานที่เลือก');
        if ($effectiveDate < (string) $employee['Start_date']) throw new RuntimeException('วันที่มีผลต้องไม่ก่อนวันเริ่มงานของพนักงาน');
        $duplicateStmt = mysqli_prepare($connection, 'SELECT id FROM employee_salaries WHERE employee_id=? AND effective_from=? LIMIT 1 FOR UPDATE');
        mysqli_stmt_bind_param($duplicateStmt, 'is', $employeeId, $effectiveDate);
        mysqli_stmt_execute($duplicateStmt);
        if (mysqli_fetch_assoc(mysqli_stmt_get_result($duplicateStmt))) throw new RuntimeException('มีรายการเงินเดือนของพนักงานในวันที่มีผลนี้แล้ว กรุณาเลือกวันอื่น');

        $reason = $reasonInput !== '' ? $reasonInput : null;
        $note = $noteInput !== '' ? mb_substr($noteInput, 0, 500, 'UTF-8') : null;
        $createdBy = mb_substr(authDisplayName(), 0, 100, 'UTF-8');
        $insertStmt = mysqli_prepare($connection, 'INSERT INTO employee_salaries (employee_id,salary_amount,effective_from,reason,note,created_by) VALUES (?,?,?,?,?,?)');
        $amountValue = round((float) $amount, 2);
        mysqli_stmt_bind_param($insertStmt, 'idssss', $employeeId, $amountValue, $effectiveDate, $reason, $note, $createdBy);
        mysqli_stmt_execute($insertStmt);
        mysqli_commit($connection);
        $success = 'บันทึกเงินเดือนของ ' . $employee['Name'] . ' เรียบร้อยแล้ว';
        appRequestToast('success', $success);
        $salaryDisplay = $salaryAmountInput = $reasonInput = $noteInput = '';
        $effectiveDate = date('Y-m-d');
    } catch (Throwable $exception) {
        @mysqli_rollback($connection);
        $error = $exception->getMessage();
        appRequestToast('error', $error);
    }
}

$today = date('Y-m-d');
$employees = salaryEmployeeList($connection, $today);
$departments = [];
$departmentResult = mysqli_query($connection, 'SELECT Depart_id, Depart_name FROM department ORDER BY Depart_name');
while ($departmentResult && $department = mysqli_fetch_assoc($departmentResult)) $departments[] = $department;
$selectedEmployee = null;
foreach ($employees as $employee) if ((string) $employee['Employee_id'] === $selectedId) { $selectedEmployee = $employee; break; }
if ($selectedId !== '' && !$selectedEmployee && $error === '') $error = 'ไม่พบพนักงานที่เลือก';
$currentSalary = $selectedEmployee ? salaryEffectiveAt($connection, (int) $selectedEmployee['Employee_id'], $today) : null;
$upcomingSalary = $selectedEmployee ? salaryNextScheduled($connection, (int) $selectedEmployee['Employee_id'], $today) : null;
$history = $selectedEmployee ? salaryHistory($connection, (int) $selectedEmployee['Employee_id']) : [];
$initialAmount = $salaryAmountInput !== '' ? $salaryAmountInput : ($currentSalary ? (string) $currentSalary['salary_amount'] : '');
$initialDisplay = $salaryDisplay !== '' ? $salaryDisplay : ($initialAmount !== '' ? number_format((float) $initialAmount, 2, '.', ',') : '');
?>
<div class="w-full space-y-4">
    <header class="flex flex-col gap-3 border-b border-[#dedede] pb-3 sm:flex-row sm:items-start sm:justify-between"><div><h1 class="text-lg font-semibold text-[#202223]">กำหนดเงินเดือนพื้นฐาน</h1><p class="mt-0.5 text-sm text-[#6d7175]">จัดการเงินเดือนพื้นฐานของพนักงาน</p></div><a href="/employee" class="inline-flex min-h-9 items-center justify-center rounded-md border border-[#d5d3d0] bg-white px-3 text-sm font-semibold hover:bg-[#f6f5f4]"><i class="fa-solid fa-users mr-2"></i>ดูพนักงานทั้งหมด</a></header>
    <section id="salary-workspace" data-salary-management class="space-y-4">
        <?php if ($error): ?><div role="alert" class="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800"><i class="fa-solid fa-circle-exclamation mt-0.5"></i><span><?= salaryEscape($error) ?></span></div><?php endif; ?>
        <section class="rounded-lg border border-[#e6e6e6] bg-white p-4 sm:p-5" aria-labelledby="salaryEmployeeTitle"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0"><h2 id="salaryEmployeeTitle" class="text-sm font-semibold">พนักงาน</h2><?php if ($selectedEmployee): ?><div class="mt-3 flex items-center gap-3"><span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-[#eef6fd] font-bold text-[#0075de]"><i class="fa-solid fa-user"></i></span><div class="min-w-0"><p class="truncate text-sm font-bold"><?= salaryEscape($selectedEmployee['Name']) ?></p><p class="mt-0.5 truncate text-xs text-[#615d59]"><?= salaryEscape($selectedEmployee['Employee_id']) ?> · <?= salaryEscape($selectedEmployee['Depart_name'] ?: 'ไม่ระบุแผนก') ?> · <?= salaryEscape($jobLabels[$selectedEmployee['jobtitle']] ?? $selectedEmployee['jobtitle']) ?></p></div></div><?php else: ?><p class="mt-2 text-sm text-[#615d59]">ยังไม่ได้เลือกพนักงาน</p><?php endif; ?></div><button type="button" data-open-employee-picker class="min-h-10 shrink-0 rounded-md border border-[#0075de] bg-white px-4 text-sm font-semibold text-[#0075de] hover:bg-[#eef6fd]"><i class="fa-solid fa-user-plus mr-2"></i><?= $selectedEmployee ? 'เปลี่ยนพนักงาน' : 'เลือกพนักงาน' ?></button></div></section>
        <?php if (!$selectedEmployee): ?>
            <section class="flex min-h-[260px] flex-col items-center justify-center rounded-lg border border-dashed border-[#d5d3d0] bg-white p-8 text-center"><span class="flex h-12 w-12 items-center justify-center rounded-full bg-[#f6f5f4] text-[#8a8580]"><i class="fa-solid fa-money-check-dollar"></i></span><h2 class="mt-4 text-sm font-bold">เลือกพนักงานเพื่อจัดการเงินเดือน</h2><p class="mt-1 text-xs text-[#615d59]">ค้นหาได้จากชื่อ รหัส แผนก หรือตำแหน่ง</p><button type="button" data-open-employee-picker class="mt-4 min-h-10 rounded-md bg-[#0075de] px-4 text-sm font-semibold text-white">เลือกพนักงาน</button></section>
        <?php else: ?>
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
                <form id="salaryManagementForm" method="post" action="/employee/setsalary" hx-post="/employee/setsalary" hx-target="#salary-workspace" hx-select="#salary-workspace" hx-swap="outerHTML" class="space-y-5 rounded-lg border border-[#e6e6e6] bg-white p-4 sm:p-5 xl:col-span-2" data-salary-form>
                    <input type="hidden" name="csrf_token" value="<?= salaryEscape(authCsrfToken()) ?>"><input type="hidden" name="employee_id" id="selected_employee_id" value="<?= salaryEscape($selectedEmployee['Employee_id']) ?>"><input type="hidden" name="salary_amount" value="<?= salaryEscape($initialAmount) ?>" data-salary-amount><input type="hidden" name="confirmed" value="0" data-salary-confirmed>
                    <div class="flex flex-col gap-3 border-b border-[#e6e6e6] pb-4 sm:flex-row sm:items-start sm:justify-between"><div><h2 class="text-sm font-bold">เงินเดือนปัจจุบัน</h2><?php if ($currentSalary): ?><p class="mt-1 text-xl font-extrabold">฿<?= number_format((float)$currentSalary['salary_amount'],2) ?> <span class="text-xs font-normal text-[#615d59]">/ เดือน</span></p><p class="mt-1 text-xs text-[#615d59]">กำหนดล่าสุด <?= salaryEscape(salaryThaiDate($currentSalary['effective_from'])) ?></p><?php else: ?><p class="mt-2 text-sm font-semibold text-amber-700">ยังไม่ได้กำหนดเงินเดือนพื้นฐาน</p><?php endif; ?></div><?php if ($upcomingSalary): ?><div class="rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-800"><span class="font-semibold">กำลังจะมีผล</span><br><?= salaryEscape(salaryThaiDate($upcomingSalary['effective_from'],true)) ?> · ฿<?= number_format((float)$upcomingSalary['salary_amount'],2) ?></div><?php endif; ?></div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2"><div class="sm:col-span-2"><label for="salaryDisplay" class="mb-2 block text-sm font-medium">เงินเดือนพื้นฐานใหม่ <span class="text-red-600">*</span></label><div class="flex h-10 items-center overflow-hidden rounded-md border border-[#d5d3d0] focus-within:border-[#0075de]"><span class="flex h-full items-center border-r border-[#e6e6e6] bg-[#f6f5f4] px-3 text-sm font-semibold">฿</span><input id="salaryDisplay" name="salary_display" value="<?= salaryEscape($initialDisplay) ?>" inputmode="decimal" autocomplete="off" required data-salary-display class="h-full min-w-0 flex-1 px-3 text-sm outline-none" placeholder="20,000.00"></div><p class="mt-1 text-xs text-[#77716c]">กรอกตัวเลขมากกว่า 0 ระบบจะจัดรูปแบบให้อัตโนมัติ</p></div><div><label for="salaryEffectiveDate" class="mb-2 block text-sm font-medium">วันที่มีผล <span class="text-red-600">*</span></label><input id="salaryEffectiveDate" type="date" name="effective_from" value="<?= salaryEscape($effectiveDate) ?>" min="<?= salaryEscape($selectedEmployee['Start_date']) ?>" required data-salary-effective-date class="h-10 w-full rounded-md border border-[#d5d3d0] px-3 text-sm"></div><div><label for="salaryReason" class="mb-2 block text-sm font-medium">เหตุผล</label><select id="salaryReason" name="reason" data-salary-reason class="h-10 w-full rounded-md border border-[#d5d3d0] bg-white px-3 text-sm"><option value="">ไม่ระบุ</option><?php foreach ($reasonOptions as $reason): ?><option value="<?= salaryEscape($reason) ?>" <?= $reasonInput===$reason?'selected':'' ?>><?= salaryEscape($reason) ?></option><?php endforeach; ?></select></div><div class="sm:col-span-2"><label for="salaryNote" class="mb-2 block text-sm font-medium">หมายเหตุ <span class="font-normal text-[#77716c]">(ไม่บังคับ)</span></label><textarea id="salaryNote" name="note" maxlength="500" rows="3" data-salary-note class="w-full rounded-md border border-[#d5d3d0] p-3 text-sm" placeholder="ข้อมูลเพิ่มเติมหรือเอกสารอ้างอิง"><?= salaryEscape($noteInput) ?></textarea></div></div>
                    <div class="flex justify-end border-t border-[#e6e6e6] pt-4"><button type="submit" data-loading-text="กำลังบันทึก..." class="min-h-10 w-full rounded-md bg-[#0075de] px-5 text-sm font-semibold text-white hover:bg-[#005bab] sm:w-auto"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกเงินเดือน</button></div>
                </form>
                <aside class="rounded-lg border border-[#d9e9f8] bg-[#f7fbff] p-4 sm:p-5 xl:sticky xl:top-6 xl:self-start" data-salary-preview data-current-salary="<?= $currentSalary?salaryEscape($currentSalary['salary_amount']):'' ?>" data-employee-name="<?= salaryEscape($selectedEmployee['Name']) ?>" data-employee-id="<?= salaryEscape($selectedEmployee['Employee_id']) ?>"><h2 class="text-sm font-bold">ตัวอย่างก่อนบันทึก</h2><p class="mt-1 text-xs text-[#615d59]">อัปเดตทันทีเมื่อแก้ไขข้อมูล</p><dl class="mt-5 space-y-3 text-sm"><div class="flex justify-between gap-3"><dt class="text-[#615d59]">เงินเดือนปัจจุบัน</dt><dd data-preview-current class="font-semibold"><?= $currentSalary?'฿'.number_format((float)$currentSalary['salary_amount'],2):'ยังไม่กำหนด' ?></dd></div><div class="flex justify-between gap-3"><dt class="text-[#615d59]">เงินเดือนใหม่</dt><dd data-preview-new class="font-semibold">-</dd></div><div class="border-t border-[#b7d6f1] pt-3"><div class="flex justify-between gap-3"><dt class="font-semibold">เปลี่ยนแปลง</dt><dd data-preview-difference class="font-bold">-</dd></div><p data-preview-percentage class="mt-1 text-right text-xs text-[#615d59]">-</p></div><div class="flex justify-between gap-3 border-t border-[#b7d6f1] pt-3"><dt class="text-[#615d59]">มีผลตั้งแต่</dt><dd data-preview-date class="text-right font-semibold">-</dd></div></dl><p data-salary-decrease-warning class="mt-4 hidden rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i><span></span></p></aside>
            </div>
            <section class="rounded-lg border border-[#e6e6e6] bg-white" aria-labelledby="salaryHistoryTitle"><header class="border-b border-[#e6e6e6] p-4 sm:px-5"><h2 id="salaryHistoryTitle" class="text-sm font-bold">ประวัติเงินเดือน</h2><p class="mt-1 text-xs text-[#615d59]"><?= count($history) ?> รายการ · เก็บเป็นประวัติ ไม่เขียนทับรายการเดิม</p></header><?php if (!$history): ?><div class="p-8 text-center text-sm text-[#615d59]">ยังไม่มีประวัติเงินเดือน</div><?php else: ?><div class="hidden overflow-x-auto md:block"><table class="w-full text-left text-sm"><thead class="bg-[#f6f5f4] text-xs text-[#615d59]"><tr><th class="px-5 py-3 font-semibold">วันที่มีผล</th><th class="px-5 py-3 font-semibold">เงินเดือน</th><th class="px-5 py-3 font-semibold">เปลี่ยนแปลง</th><th class="px-5 py-3 font-semibold">เหตุผล / ผู้บันทึก</th></tr></thead><tbody class="divide-y divide-[#e6e6e6]">
            <?php foreach ($history as $index=>$record): $older=$history[$index+1]??null; $change=salaryChange((float)$record['salary_amount'],$older?(float)$older['salary_amount']:null); ?><tr><td class="whitespace-nowrap px-5 py-3"><?= salaryEscape(salaryThaiDate($record['effective_from'],true)) ?></td><td class="px-5 py-3 font-bold">฿<?= number_format((float)$record['salary_amount'],2) ?></td><td class="px-5 py-3"><?php if ($change['difference']===null): ?>-<?php else: $positive=$change['difference']>=0; ?><span class="font-semibold <?= $positive?'text-emerald-700':'text-red-700' ?>"><?= $positive?'+':'-' ?>฿<?= number_format(abs($change['difference']),2) ?></span><?php if ($change['percentage']!==null): ?><span class="ml-1 text-xs text-[#77716c]">(<?= $change['percentage']>=0?'+':'' ?><?= number_format($change['percentage'],2) ?>%)</span><?php endif; ?><?php endif; ?></td><td class="px-5 py-3"><p><?= salaryEscape($record['reason']?:'ไม่ระบุเหตุผล') ?></p><p class="mt-0.5 text-xs text-[#77716c]"><?= salaryEscape($record['created_by']?:'ไม่ระบุผู้บันทึก') ?> · <?= salaryEscape(date('d/m/Y H:i',strtotime($record['created_at']))) ?></p><?php if ($record['note']): ?><p class="mt-1 text-xs text-[#615d59]"><?= salaryEscape($record['note']) ?></p><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div><div class="divide-y divide-[#e6e6e6] md:hidden"><?php foreach ($history as $index=>$record): $older=$history[$index+1]??null; $change=salaryChange((float)$record['salary_amount'],$older?(float)$older['salary_amount']:null); ?><article class="p-4"><div class="flex items-start justify-between gap-3"><div><p class="text-xs text-[#615d59]"><?= salaryEscape(salaryThaiDate($record['effective_from'],true)) ?></p><p class="mt-1 text-base font-bold">฿<?= number_format((float)$record['salary_amount'],2) ?></p></div><?php if ($change['difference']!==null): $positive=$change['difference']>=0; ?><span class="text-sm font-semibold <?= $positive?'text-emerald-700':'text-red-700' ?>"><?= $positive?'+':'-' ?>฿<?= number_format(abs($change['difference']),2) ?></span><?php endif; ?></div><p class="mt-2 text-xs"><?= salaryEscape($record['reason']?:'ไม่ระบุเหตุผล') ?></p><?php if ($record['note']): ?><p class="mt-1 text-xs text-[#77716c]"><?= salaryEscape($record['note']) ?></p><?php endif; ?></article><?php endforeach; ?></div><?php endif; ?></section>
        <?php endif; ?>
        <?php $employeePickerEmployees=$employees; $employeePickerDepartments=$departments; $employeePickerSelectedId=$selectedEmployee?(string)$selectedEmployee['Employee_id']:''; $employeePickerJobLabels=$jobLabels; $employeePickerContext='salary'; require __DIR__ . '/components/employee_picker.php'; ?>
        <?php if ($selectedEmployee): ?><div id="salaryConfirmationModal" class="fixed inset-0 z-[90] hidden items-end justify-center sm:items-center sm:p-4" aria-hidden="true"><div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" data-close-salary-confirmation></div><section class="relative w-full rounded-t-xl bg-white shadow-2xl sm:max-w-md sm:rounded-xl" role="dialog" aria-modal="true" aria-labelledby="salaryConfirmationTitle"><header class="flex items-start justify-between border-b border-[#e6e6e6] p-4"><div><h2 id="salaryConfirmationTitle" data-salary-confirm-title class="text-base font-bold">ยืนยันการเปลี่ยนเงินเดือน?</h2><p class="mt-1 text-xs text-[#615d59]"><?= salaryEscape($selectedEmployee['Name']) ?> · <?= salaryEscape($selectedEmployee['Employee_id']) ?></p></div><button type="button" data-close-salary-confirmation class="h-10 w-10 rounded-md hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></header><div class="p-4"><dl class="space-y-3 text-sm"><div class="flex justify-between"><dt>เงินเดือนเดิม</dt><dd data-salary-confirm-current class="font-semibold"></dd></div><div class="flex justify-between"><dt>เงินเดือนใหม่</dt><dd data-salary-confirm-new class="font-bold"></dd></div><div class="flex justify-between border-t border-[#e6e6e6] pt-3"><dt>เปลี่ยนแปลง</dt><dd data-salary-confirm-change class="font-semibold"></dd></div><div class="flex justify-between"><dt>มีผลตั้งแต่</dt><dd data-salary-confirm-date class="font-semibold"></dd></div></dl></div><footer class="flex flex-col-reverse gap-2 border-t border-[#e6e6e6] p-4 sm:flex-row sm:justify-end"><button type="button" data-close-salary-confirmation class="min-h-10 rounded-md border border-[#d5d3d0] px-4 text-sm font-semibold">ยกเลิก</button><button type="button" data-confirm-salary class="min-h-10 rounded-md bg-[#0075de] px-4 text-sm font-semibold text-white">ยืนยันและบันทึก</button></footer></section></div><?php endif; ?>
    </section>
</div>
