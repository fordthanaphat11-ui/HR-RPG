<?php
/**
 * Shared employee picker used by payroll payment and payment history.
 *
 * Expected variables: $employeePickerEmployees, $employeePickerDepartments,
 * $employeePickerSelectedId, $employeePickerJobLabels, $employeePickerContext.
 * Payment may also provide $employeePickerCurrentPeriodKey.
 */
$pickerEmployees = $employeePickerEmployees ?? [];
$pickerDepartments = $employeePickerDepartments ?? [];
$pickerSelectedId = (string) ($employeePickerSelectedId ?? '');
$pickerJobLabels = $employeePickerJobLabels ?? [];
$pickerContext = in_array(($employeePickerContext ?? 'payment'), ['payment','payhistory','attendance','attendance_history'], true)
    ? (string) $employeePickerContext
    : 'payment';
$pickerCurrentPeriodKey = (string) ($employeePickerCurrentPeriodKey ?? '');
$pickerExtraQuery = ltrim((string) ($employeePickerExtraQuery ?? ''), '&?');
$pickerEscape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$pickerInitials = static function (string $name): string {
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    if (!$parts) return 'พ';
    $initials = mb_substr($parts[0], 0, 1, 'UTF-8');
    if (count($parts) > 1) $initials .= mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');
    return $initials;
};
$pickerDescription = match ($pickerContext) {
    'payhistory' => 'ค้นหาและเลือกพนักงานเพื่อดูประวัติการจ่ายเงินเดือน',
    'attendance' => 'ค้นหาและเลือกพนักงานเพื่อเช็คชื่อเข้า–ออกงาน',
    'attendance_history' => 'ค้นหาและเลือกพนักงานเพื่อดูประวัติการเข้างาน',
    default => 'ค้นหาและเลือกพนักงานที่ต้องการจ่ายเงินเดือน',
};
?>

<div id="employeePickerModal" data-employee-picker-context="<?= $pickerEscape($pickerContext) ?>" data-employee-picker-selected-id="<?= $pickerEscape($pickerSelectedId) ?>" class="fixed inset-0 z-[80] hidden items-end justify-center sm:items-center sm:p-4" aria-hidden="true">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-[1px]" data-close-employee-picker></div>
    <section class="relative flex h-[90vh] max-h-[90vh] w-full flex-col overflow-hidden rounded-t-[16px] border border-[#e6e6e6] bg-white shadow-2xl sm:h-[600px] sm:max-h-[85vh] sm:w-[calc(100%-2rem)] sm:max-w-5xl sm:rounded-[16px]" role="dialog" aria-modal="true" aria-labelledby="employee-picker-title" aria-describedby="employee-picker-description">
        <header class="shrink-0 border-b border-[#e6e6e6] bg-white px-4 py-4 sm:px-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="employee-picker-title" class="text-[19px] sm:text-[21px] font-bold">เลือกพนักงาน</h2>
                    <p id="employee-picker-description" class="mt-1 text-[13px] sm:text-[14px] text-[#615d59]"><?= $pickerEscape($pickerDescription) ?></p>
                </div>
                <button type="button" data-close-employee-picker class="w-11 h-11 shrink-0 rounded-[8px] text-[#31302e] hover:bg-[#f6f5f4]" aria-label="ปิด">
                    <i class="fa-solid fa-xmark text-lg" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="shrink-0 space-y-3 border-b border-[#e6e6e6] bg-white px-4 py-3.5 sm:px-5">
            <label for="employeePickerSearch" class="sr-only">ค้นหาพนักงาน</label>
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[#a39e98]" aria-hidden="true"></i>
                <input id="employeePickerSearch" type="search" data-employee-picker-search class="w-full min-h-12 rounded-[9px] border border-[#e6e6e6] bg-white pl-11 pr-4 text-[15px] outline-none focus:border-[#0075de]" placeholder="ค้นหาชื่อ รหัสพนักงาน แผนก หรือตำแหน่ง...">
            </div>
            <div class="grid grid-cols-1 gap-2 <?= $pickerContext === 'payment' ? 'sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]' : 'sm:grid-cols-[minmax(0,1fr)_auto]' ?> sm:items-center">
                <label class="sr-only" for="employeePickerDepartment">กรองตามแผนก</label>
                <select id="employeePickerDepartment" data-employee-picker-department class="w-full min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px]">
                    <option value="">ทุกแผนก</option>
                    <?php foreach ($pickerDepartments as $department): ?>
                        <option value="<?= $pickerEscape($department['Depart_id']) ?>"><?= $pickerEscape($department['Depart_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($pickerContext === 'payment'): ?>
                    <label class="sr-only" for="employeePickerStatus">กรองตามสถานะการจ่าย</label>
                    <select id="employeePickerStatus" data-employee-picker-status class="w-full min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-3 text-[14px]">
                        <option value="">ทุกสถานะ</option>
                        <option value="pending">รอจ่าย</option>
                        <option value="paid">จ่ายแล้ว</option>
                    </select>
                <?php endif; ?>
                <p class="text-right text-[13px] text-[#615d59] whitespace-nowrap"><span data-employee-picker-count><?= count($pickerEmployees) ?></span> คน</p>
            </div>
        </div>

        <div id="employeePickerResults" class="min-h-0 flex-1 overflow-y-auto bg-white" tabindex="-1">
            <div data-employee-picker-list class="<?= $pickerEmployees ? '' : 'hidden' ?> divide-y divide-[#e6e6e6]">
                <?php foreach ($pickerEmployees as $employee): ?>
                    <?php
                    $employeeId = (string) $employee['Employee_id'];
                    $position = $pickerJobLabels[$employee['jobtitle']] ?? $employee['jobtitle'];
                    $departmentName = $employee['Depart_name'] ?: 'ไม่ระบุแผนก';
                    $paidPeriods = (string) ($employee['paid_periods'] ?? '');
                    $historyCount = (int) ($employee['history_count'] ?? 0);
                    $paidForCurrentPeriod = $pickerCurrentPeriodKey !== '' && in_array($pickerCurrentPeriodKey, array_filter(explode(',', $paidPeriods)), true);
                    $isSelected = $employeeId === $pickerSelectedId;
                    $searchText = implode(' ', [$employeeId, $employee['Name'], $departmentName, $employee['Depart_id'], $position, $employee['jobtitle']]);
                    ?>
                    <article
                        data-employee-picker-item
                        data-employee-id="<?= $pickerEscape($employeeId) ?>"
                        data-employee-name="<?= $pickerEscape($employee['Name']) ?>"
                        data-employee-initials="<?= $pickerEscape($pickerInitials($employee['Name'])) ?>"
                        data-employee-department-id="<?= $pickerEscape($employee['Depart_id']) ?>"
                        data-employee-department="<?= $pickerEscape($departmentName) ?>"
                        data-employee-position="<?= $pickerEscape($position) ?>"
                        data-employee-salary="<?= $pickerEscape($employee['basic_salary'] ?? 0) ?>"
                        data-employee-loan="<?= $pickerEscape($employee['loan'] ?? 0) ?>"
                        data-employee-fund="<?= $pickerEscape($employee['p_fund'] ?? 0) ?>"
                        data-employee-paid-periods="<?= $pickerEscape($paidPeriods) ?>"
                        data-employee-history-count="<?= $historyCount ?>"
                        data-employee-search="<?= $pickerEscape(mb_strtolower($searchText, 'UTF-8')) ?>"
                        class="grid gap-3 px-4 py-4 transition-colors sm:px-5 md:grid-cols-[minmax(0,1fr)_minmax(130px,.55fr)_minmax(120px,.4fr)_auto] md:items-center <?= $isSelected ? 'bg-[#eef6fd]' : 'bg-white' ?>"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-11 h-11 shrink-0 rounded-full bg-[#eef6fd] text-[#0075de] flex items-center justify-center font-bold"><?= $pickerEscape($pickerInitials($employee['Name'])) ?></span>
                            <div class="min-w-0">
                                <p class="text-[12px] font-semibold text-[#615d59]"><?= $pickerEscape($employeeId) ?></p>
                                <p class="font-bold truncate"><?= $pickerEscape($employee['Name']) ?></p>
                                <p class="mt-0.5 text-[13px] text-[#615d59] truncate md:hidden"><?= $pickerEscape($departmentName) ?> · <?= $pickerEscape($position) ?></p>
                            </div>
                        </div>
                        <div class="hidden md:block min-w-0">
                            <p class="text-[13px] font-medium truncate"><?= $pickerEscape($departmentName) ?></p>
                            <p class="text-[12px] text-[#615d59] truncate"><?= $pickerEscape($position) ?></p>
                        </div>
                        <div class="flex items-center justify-between gap-3 md:block">
                            <?php if ($pickerContext === 'payment'): ?>
                                <span class="text-[12px] text-[#615d59] md:block">เงินเดือน</span>
                                <span class="font-bold">฿<?= number_format((float) ($employee['basic_salary'] ?? 0)) ?></span>
                            <?php elseif ($pickerContext === 'payhistory'): ?>
                                <span class="text-[12px] text-[#615d59] md:block">ประวัติการจ่าย</span>
                                <span class="font-semibold <?= $historyCount ? 'text-[#31302e]' : 'text-[#8a8580]' ?>"><?= $historyCount ? $historyCount . ' รายการ' : 'ยังไม่มีประวัติ' ?></span>
                            <?php elseif ($pickerContext === 'attendance'): ?>
                                <?php $todayCheckIn = (string) ($employee['attendance_check_in'] ?? ''); $todayLate = (int) ($employee['attendance_late_minutes'] ?? 0); ?>
                                <span class="text-[12px] text-[#615d59] md:block">สถานะวันนี้</span>
                                <span class="font-semibold <?= $todayCheckIn ? ($todayLate ? 'text-amber-700' : 'text-emerald-700') : 'text-[#8a8580]' ?>"><?= $todayCheckIn ? date('H:i', strtotime($todayCheckIn)) . ($todayLate ? ' · มาสาย' : ' · ตรงเวลา') : 'ยังไม่เข้างาน' ?></span>
                            <?php else: ?>
                                <span class="text-[12px] text-[#615d59] md:block">ประวัติการเข้างาน</span>
                                <span class="font-semibold <?= $historyCount ? 'text-[#31302e]' : 'text-[#8a8580]' ?>"><?= $historyCount ? $historyCount . ' วัน' : 'ยังไม่มีประวัติ' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center justify-between gap-2 md:justify-end">
                            <?php if ($pickerContext === 'payment'): ?>
                                <span data-employee-paid-badge class="<?= $paidForCurrentPeriod ? 'inline-flex' : 'hidden' ?> items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-[12px] font-semibold text-emerald-700"><i class="fa-solid fa-circle-check" aria-hidden="true"></i>จ่ายแล้ว</span>
                                <button type="button" data-select-employee <?= ($isSelected || $paidForCurrentPeriod) ? 'disabled' : '' ?> class="min-h-10 min-w-[96px] rounded-[7px] border px-3 text-[13px] font-semibold disabled:cursor-default <?= $isSelected ? 'border-[#b7d6f1] bg-[#eef6fd] text-[#0075de]' : ($paidForCurrentPeriod ? 'border-[#e6e6e6] bg-[#f6f5f4] text-[#77716c]' : 'border-[#0075de] bg-white text-[#0075de] hover:bg-[#eef6fd]') ?>">
                                    <span data-employee-select-label><?= $isSelected ? '✓ เลือกอยู่' : ($paidForCurrentPeriod ? 'จ่ายแล้ว ✓' : 'เลือก') ?></span>
                                </button>
                            <?php elseif ($isSelected): ?>
                                <button type="button" disabled class="min-h-10 min-w-[96px] rounded-[7px] border border-[#b7d6f1] bg-[#eef6fd] px-3 text-[13px] font-semibold text-[#0075de]">✓ เลือกอยู่</button>
                            <?php else: ?>
                                <?php $pickerRoute = match ($pickerContext) { 'attendance'=>'/attendance', 'attendance_history'=>'/attendance/history', default=>'/employee/payhistory' }; ?>
                                <a href="<?= $pickerEscape($pickerRoute) ?>?employee_id=<?= rawurlencode($employeeId) ?><?= $pickerExtraQuery !== '' ? '&amp;' . $pickerEscape($pickerExtraQuery) : '' ?>" hx-push-url="true" data-select-history-employee class="min-h-10 min-w-[96px] inline-flex items-center justify-center rounded-[7px] border border-[#0075de] bg-white px-3 text-[13px] font-semibold text-[#0075de] hover:bg-[#eef6fd]">เลือก</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <div data-employee-picker-empty class="<?= $pickerEmployees ? 'hidden' : 'flex' ?> min-h-[260px] flex-col items-center justify-center px-6 py-10 text-center">
                <span class="w-12 h-12 rounded-full bg-[#f6f5f4] text-[#a39e98] flex items-center justify-center"><i class="fa-solid fa-user-slash" aria-hidden="true"></i></span>
                <p class="mt-4 font-bold">ไม่พบพนักงาน</p>
                <p class="mt-1 max-w-sm text-[13px] text-[#615d59]">ลองค้นหาด้วยชื่อ รหัสพนักงาน หรือเลือกแผนกอื่น</p>
            </div>
        </div>

        <footer class="shrink-0 border-t border-[#e6e6e6] bg-[#f6f5f4] px-4 py-3 text-[13px] text-[#615d59] sm:px-5">
            แสดงพนักงาน <span data-employee-picker-footer-count><?= count($pickerEmployees) ?></span> จาก <?= count($pickerEmployees) ?> คน
        </footer>
    </section>
</div>
