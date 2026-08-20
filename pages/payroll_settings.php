<?php
declare(strict_types=1);

$title = 'ตั้งค่าการคำนวณเงินเดือน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/payroll.php';

if (!isset($_SESSION['username'])) { header('Location: /login'); exit; }

function payrollSettingsEscape(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$savedSettings = getPayrollSettings($connection);
$settings = $savedSettings;
$fieldErrors = [];
$responseToast = null;
$isHtmxRequest = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $absenceEnabled = isset($_POST['absence_enabled']) ? 1 : 0;
    $absenceMode = (string) ($_POST['absence_mode'] ?? 'fixed');
    $absenceRate = payrollNumericValue($_POST['absence_rate'] ?? '0');
    $absenceDivisor = payrollNumericValue($_POST['absence_divisor_days'] ?? '30', true);
    $lateEnabled = isset($_POST['late_enabled']);
    $lateMode = $lateEnabled ? (string) ($_POST['late_mode'] ?? 'per_occurrence') : 'none';
    $lateOccurrence = payrollNumericValue($_POST['late_occurrence_rate'] ?? '0');
    $lateInterval = payrollNumericValue($_POST['late_interval_minutes'] ?? '30', true);
    $lateIntervalRate = payrollNumericValue($_POST['late_interval_rate'] ?? '0');
    $lateMinuteRate = payrollNumericValue($_POST['late_minute_rate'] ?? '0');
    $lateRounding = (string) ($_POST['late_rounding'] ?? 'ceil');
    $lateMaximumEnabled = isset($_POST['late_maximum_enabled']);
    $lateMaximum = $lateMaximumEnabled ? payrollNumericValue($_POST['max_late_deduction'] ?? '') : null;
    $allowNegative = isset($_POST['allow_negative_net_salary']) ? 1 : 0;

    if (!in_array($absenceMode, ['fixed', 'daily_salary'], true)) $fieldErrors['absence_mode'] = 'วิธีคำนวณขาดงานไม่ถูกต้อง';
    if ($absenceEnabled && $absenceMode === 'fixed' && ($absenceRate === null || $absenceRate < 0)) $fieldErrors['absence_rate'] = 'จำนวนเงินต้องเป็น 0 หรือมากกว่า';
    if ($absenceEnabled && $absenceMode === 'daily_salary' && ($absenceDivisor === null || $absenceDivisor <= 0 || $absenceDivisor > 366)) $fieldErrors['absence_divisor_days'] = 'จำนวนวันต้องอยู่ระหว่าง 1–366 วัน';
    if (!in_array($lateMode, ['none', 'per_occurrence', 'per_minutes', 'per_actual_minute'], true)) $fieldErrors['late_mode'] = 'วิธีคำนวณการมาสายไม่ถูกต้อง';
    if ($lateMode === 'per_occurrence' && ($lateOccurrence === null || $lateOccurrence < 0)) $fieldErrors['late_occurrence_rate'] = 'จำนวนเงินต้องเป็น 0 หรือมากกว่า';
    if ($lateMode === 'per_minutes') {
        if ($lateInterval === null || $lateInterval <= 0 || $lateInterval > 1440) $fieldErrors['late_interval_minutes'] = 'ช่วงเวลาต้องอยู่ระหว่าง 1–1,440 นาที';
        if ($lateIntervalRate === null || $lateIntervalRate < 0) $fieldErrors['late_interval_rate'] = 'จำนวนเงินต้องเป็น 0 หรือมากกว่า';
        if (!in_array($lateRounding, ['ceil', 'floor'], true)) $fieldErrors['late_rounding'] = 'การปัดช่วงเวลาไม่ถูกต้อง';
    }
    if ($lateMode === 'per_actual_minute' && ($lateMinuteRate === null || $lateMinuteRate < 0)) $fieldErrors['late_minute_rate'] = 'จำนวนเงินต้องเป็น 0 หรือมากกว่า';
    if ($lateMaximumEnabled && ($lateMaximum === null || $lateMaximum < 0)) $fieldErrors['max_late_deduction'] = 'ยอดสูงสุดต้องเป็น 0 หรือมากกว่า';

    $settings = array_merge($savedSettings, [
        'absence_deduction_enabled'=>$absenceEnabled, 'absence_deduction_mode'=>$absenceMode,
        'absence_deduction_per_day'=>$absenceRate ?? (string) ($_POST['absence_rate'] ?? ''),
        'absence_salary_divisor_days'=>$absenceDivisor ?? (string) ($_POST['absence_divisor_days'] ?? ''),
        'late_deduction_mode'=>$lateMode,
        'late_deduction_per_occurrence'=>$lateOccurrence ?? (string) ($_POST['late_occurrence_rate'] ?? ''),
        'late_interval_minutes'=>$lateInterval ?? (string) ($_POST['late_interval_minutes'] ?? ''),
        'late_deduction_per_interval'=>$lateIntervalRate ?? (string) ($_POST['late_interval_rate'] ?? ''),
        'late_deduction_per_minute'=>$lateMinuteRate ?? (string) ($_POST['late_minute_rate'] ?? ''),
        'late_rounding_mode'=>$lateRounding,
        'max_late_deduction'=>$lateMaximumEnabled ? ($lateMaximum ?? (string) ($_POST['max_late_deduction'] ?? '')) : null,
        'allow_negative_net_salary'=>$allowNegative,
    ]);

    if ($fieldErrors) {
        $responseToast = ['type'=>'error', 'message'=>'กรุณาตรวจสอบการตั้งค่าที่กรอก'];
        if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') appTriggerToast('error', $responseToast['message']);
    } else {
        $absenceRate = (float) ($absenceRate ?? 0); $absenceDivisor = (int) ($absenceDivisor ?? 30);
        $lateOccurrence = (float) ($lateOccurrence ?? 0); $lateInterval = (int) ($lateInterval ?? 30);
        $lateIntervalRate = (float) ($lateIntervalRate ?? 0); $lateMinuteRate = (float) ($lateMinuteRate ?? 0);
        $grace = 0; $username = (string) $_SESSION['username'];
        $stmt = mysqli_prepare($connection, 'UPDATE payroll_settings SET absence_deduction_enabled=?, absence_deduction_mode=?, absence_deduction_per_day=?, absence_salary_divisor_days=?, late_deduction_mode=?, late_deduction_per_occurrence=?, late_interval_minutes=?, late_deduction_per_interval=?, late_deduction_per_minute=?, late_rounding_mode=?, late_grace_minutes=?, max_late_deduction=?, allow_negative_net_salary=?, updated_by=? WHERE id=1');
        mysqli_stmt_bind_param($stmt, 'isdisdiddsidis', $absenceEnabled, $absenceMode, $absenceRate, $absenceDivisor, $lateMode, $lateOccurrence, $lateInterval, $lateIntervalRate, $lateMinuteRate, $lateRounding, $grace, $lateMaximum, $allowNegative, $username);
        if (mysqli_stmt_execute($stmt)) {
            if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') {
                $responseToast = ['type'=>'success', 'message'=>'บันทึกการตั้งค่าการคำนวณเงินเดือนเรียบร้อยแล้ว'];
                appTriggerToast('success', $responseToast['message']);
                $settings = getPayrollSettings($connection); $savedSettings = $settings;
            } else {
                appFlashToast('success', 'บันทึกการตั้งค่าการคำนวณเงินเดือนเรียบร้อยแล้ว');
                header('Location: /settings/payroll'); exit;
            }
        } else {
            $responseToast = ['type'=>'error', 'message'=>'ไม่สามารถบันทึกการตั้งค่าได้'];
            if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') appTriggerToast('error', $responseToast['message']);
            $fieldErrors['form'] = 'ระบบไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่อีกครั้ง';
        }
    }
}

$updatedLabel = !empty($savedSettings['updated_at']) ? date('d/m/Y H:i', strtotime((string) $savedSettings['updated_at'])) : 'ยังไม่เคยแก้ไข';
$inputClass = 'h-9 w-full rounded-md border border-[#d5d3d0] bg-white px-3 text-sm outline-none focus:border-[#0075de] focus:ring-2 focus:ring-blue-100';
$errorInputClass = ' border-red-400 focus:border-red-500 focus:ring-red-100';
?>

<section id="payroll-settings-workspace" data-payroll-settings data-settings-invalid="<?= $fieldErrors ? '1' : '0' ?>" class="space-y-4">
    <?php if ($responseToast && $isHtmxRequest): $serverToastId = 'payroll-settings-toast-' . bin2hex(random_bytes(4)); ?>
        <div id="<?= $serverToastId ?>" hx-swap-oob="beforeend:#hotToastViewport" data-server-toast role="<?= $responseToast['type'] === 'error' ? 'alert' : 'status' ?>" class="pointer-events-auto rounded-lg border <?= $responseToast['type'] === 'error' ? 'border-red-200' : 'border-emerald-200' ?> bg-white px-3.5 py-3 shadow-lg">
            <div class="flex items-start gap-3"><i class="fa-solid <?= $responseToast['type'] === 'error' ? 'fa-circle-exclamation text-red-600' : 'fa-circle-check text-emerald-600' ?> mt-0.5 shrink-0"></i><p class="min-w-0 flex-1 text-sm leading-5 text-[#202223]"><?= payrollSettingsEscape($responseToast['message']) ?></p><button type="button" data-dismiss-server-toast class="-mr-1 -mt-1 h-8 w-8 shrink-0 rounded-md text-[#77716c] hover:bg-[#f6f5f4]" aria-label="ปิด"><i class="fa-solid fa-xmark"></i></button></div>
        </div>
    <?php elseif ($responseToast): ?><span hidden data-flash-toast data-type="<?= payrollSettingsEscape($responseToast['type']) ?>" data-message="<?= payrollSettingsEscape($responseToast['message']) ?>"></span><?php endif; ?>
    <header class="flex flex-col gap-3 border-b border-[#e6e6e6] pb-4 sm:flex-row sm:items-center sm:justify-between">
        <div><h1 class="text-lg font-semibold" tabindex="-1">ตั้งค่าการคำนวณเงินเดือน</h1><p class="mt-1 text-sm text-[#6d7175]">กำหนดกฎการคำนวณรายการเพิ่ม รายการหัก และเงินเดือนสุทธิ</p></div>
        <button type="submit" form="payrollSettingsForm" data-settings-save data-loading-text="กำลังบันทึก..." class="inline-flex h-9 items-center justify-center rounded-md bg-[#303030] px-4 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-45"><i class="fa-solid fa-floppy-disk mr-2"></i>บันทึกการตั้งค่า</button>
    </header>

    <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 text-xs" aria-label="หัวข้อการตั้งค่า">
        <?php foreach (['absence-settings'=>'การขาดงาน','late-settings'=>'การมาสาย','adjustment-settings'=>'รายการเพิ่ม/หัก','limit-settings'=>'ข้อจำกัด'] as $anchor=>$label): ?><a href="#<?= $anchor ?>" class="shrink-0 rounded-md border border-[#e6e6e6] bg-white px-3 py-2 hover:bg-[#f6f5f4]"><?= $label ?></a><?php endforeach; ?>
    </nav>
    <?php if (isset($fieldErrors['form'])): ?><div role="alert" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700"><?= payrollSettingsEscape($fieldErrors['form']) ?></div><?php endif; ?>

    <form id="payrollSettingsForm" method="post" action="/settings/payroll" hx-post="/settings/payroll" hx-target="#payroll-settings-workspace" hx-select="#payroll-settings-workspace" hx-swap="outerHTML" class="grid grid-cols-1 gap-4 xl:grid-cols-3" novalidate>
        <div class="rounded-lg border border-[#dedede] bg-white px-4 sm:px-5 xl:col-span-2">
            <section id="absence-settings" class="scroll-mt-20 border-b border-[#e6e6e6] py-5" aria-labelledby="absenceSettingsTitle">
                <div class="flex items-start justify-between gap-4"><div><h2 id="absenceSettingsTitle" class="text-sm font-semibold">การขาดงาน</h2><p class="mt-1 text-xs text-[#6d7175]">กำหนดวิธีหักเงินเมื่อพนักงานขาดงาน</p></div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs font-medium"><input type="checkbox" name="absence_enabled" value="1" data-setting-input data-settings-toggle="absence" class="peer sr-only" <?= !empty($settings['absence_deduction_enabled']) ? 'checked' : '' ?>><span class="relative h-5 w-9 rounded-full bg-[#c9c6c2] transition peer-checked:bg-[#0075de] peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span><span data-toggle-label><?= !empty($settings['absence_deduction_enabled']) ? 'เปิด' : 'ปิด' ?></span></label>
                </div>
                <div data-absence-details class="mt-4 space-y-4">
                    <fieldset><legend class="text-xs font-medium">วิธีคำนวณ</legend><div class="mt-2 flex flex-col gap-2 text-sm sm:flex-row sm:gap-5">
                        <label class="inline-flex items-center gap-2"><input type="radio" name="absence_mode" value="fixed" data-setting-input class="accent-[#0075de]" <?= $settings['absence_deduction_mode'] === 'fixed' ? 'checked' : '' ?>>จำนวนเงินคงที่ต่อวัน</label>
                        <label class="inline-flex items-center gap-2"><input type="radio" name="absence_mode" value="daily_salary" data-setting-input class="accent-[#0075de]" <?= $settings['absence_deduction_mode'] === 'daily_salary' ? 'checked' : '' ?>>คำนวณจากเงินเดือนรายวัน</label>
                    </div><?php if (isset($fieldErrors['absence_mode'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['absence_mode']) ?></p><?php endif; ?></fieldset>
                    <div data-absence-fixed-fields class="max-w-sm"><label for="absenceRate" class="mb-1.5 block text-xs font-medium">จำนวนเงินที่หักต่อวัน</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#6d7175]">฿</span><input id="absenceRate" type="number" step="0.01" min="0" name="absence_rate" data-setting-input value="<?= payrollSettingsEscape($settings['absence_deduction_per_day']) ?>" class="<?= $inputClass ?> pl-8<?= isset($fieldErrors['absence_rate']) ? $errorInputClass : '' ?>"></div><?php if (isset($fieldErrors['absence_rate'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['absence_rate']) ?></p><?php else: ?><p data-absence-rate-helper class="mt-1 text-xs text-[#6d7175]"></p><?php endif; ?></div>
                    <div data-absence-salary-fields class="max-w-sm"><label for="absenceDivisor" class="mb-1.5 block text-xs font-medium">จำนวนวันที่ใช้หารเงินเดือน</label><div class="relative"><input id="absenceDivisor" type="number" min="1" max="366" name="absence_divisor_days" data-setting-input value="<?= payrollSettingsEscape($settings['absence_salary_divisor_days']) ?>" class="<?= $inputClass ?> pr-12<?= isset($fieldErrors['absence_divisor_days']) ? $errorInputClass : '' ?>"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#6d7175]">วัน</span></div><?php if (isset($fieldErrors['absence_divisor_days'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['absence_divisor_days']) ?></p><?php else: ?><p class="mt-1 text-xs text-[#6d7175]">เงินเดือนพื้นฐาน ÷ จำนวนวันที่กำหนด ระบบปัดเป็น 2 ตำแหน่ง</p><?php endif; ?></div>
                </div>
            </section>

            <section id="late-settings" class="scroll-mt-20 border-b border-[#e6e6e6] py-5" aria-labelledby="lateSettingsTitle">
                <div class="flex items-start justify-between gap-4"><div><h2 id="lateSettingsTitle" class="text-sm font-semibold">การมาสาย</h2><p class="mt-1 text-xs text-[#6d7175]">กำหนดมูลค่ารายการหักจากข้อมูลเช็คชื่อเข้า–ออกงาน</p></div>
                    <label class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs font-medium"><input type="checkbox" name="late_enabled" value="1" data-setting-input data-settings-toggle="late" class="peer sr-only" <?= $settings['late_deduction_mode'] !== 'none' ? 'checked' : '' ?>><span class="relative h-5 w-9 rounded-full bg-[#c9c6c2] transition peer-checked:bg-[#0075de] peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span><span data-toggle-label><?= $settings['late_deduction_mode'] !== 'none' ? 'เปิด' : 'ปิด' ?></span></label>
                </div>
                <div data-late-details class="mt-4 space-y-4">
                    <fieldset><legend class="text-xs font-medium">วิธีคำนวณ</legend><div class="mt-2 flex flex-col gap-2 text-sm sm:flex-row sm:flex-wrap sm:gap-x-5">
                        <?php foreach (['per_occurrence'=>'หักต่อครั้ง','per_minutes'=>'หักตามช่วงเวลา','per_actual_minute'=>'หักตามนาทีจริง'] as $value=>$label): ?><label class="inline-flex items-center gap-2"><input type="radio" name="late_mode" value="<?= $value ?>" data-setting-input class="accent-[#0075de]" <?= $settings['late_deduction_mode'] === $value || ($settings['late_deduction_mode'] === 'none' && $value === 'per_occurrence') ? 'checked' : '' ?>><?= $label ?></label><?php endforeach; ?>
                    </div><?php if (isset($fieldErrors['late_mode'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['late_mode']) ?></p><?php endif; ?></fieldset>
                    <div data-late-occurrence-fields class="max-w-sm"><label for="lateOccurrenceRate" class="mb-1.5 block text-xs font-medium">จำนวนเงินต่อครั้ง</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#6d7175]">฿</span><input id="lateOccurrenceRate" type="number" step="0.01" min="0" name="late_occurrence_rate" data-setting-input value="<?= payrollSettingsEscape($settings['late_deduction_per_occurrence']) ?>" class="<?= $inputClass ?> pl-8<?= isset($fieldErrors['late_occurrence_rate']) ? $errorInputClass : '' ?>"></div><?php if (isset($fieldErrors['late_occurrence_rate'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['late_occurrence_rate']) ?></p><?php endif; ?></div>
                    <div data-late-minute-fields class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div><label for="lateInterval" class="mb-1.5 block text-xs font-medium">ทุกกี่นาที</label><div class="relative"><input id="lateInterval" type="number" min="1" max="1440" name="late_interval_minutes" data-setting-input value="<?= payrollSettingsEscape($settings['late_interval_minutes']) ?>" class="<?= $inputClass ?> pr-12<?= isset($fieldErrors['late_interval_minutes']) ? $errorInputClass : '' ?>"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#6d7175]">นาที</span></div><?php if (isset($fieldErrors['late_interval_minutes'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['late_interval_minutes']) ?></p><?php endif; ?></div>
                        <div><label for="lateIntervalRate" class="mb-1.5 block text-xs font-medium">หักต่อช่วง</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#6d7175]">฿</span><input id="lateIntervalRate" type="number" step="0.01" min="0" name="late_interval_rate" data-setting-input value="<?= payrollSettingsEscape($settings['late_deduction_per_interval']) ?>" class="<?= $inputClass ?> pl-8<?= isset($fieldErrors['late_interval_rate']) ? $errorInputClass : '' ?>"></div><?php if (isset($fieldErrors['late_interval_rate'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['late_interval_rate']) ?></p><?php endif; ?></div>
                        <div><label for="lateRounding" class="mb-1.5 block text-xs font-medium">การปัดช่วงเวลา</label><select id="lateRounding" name="late_rounding" data-setting-input class="<?= $inputClass ?>"><option value="ceil" <?= $settings['late_rounding_mode'] === 'ceil' ? 'selected' : '' ?>>ปัดขึ้น</option><option value="floor" <?= $settings['late_rounding_mode'] === 'floor' ? 'selected' : '' ?>>ปัดลง</option></select></div>
                    </div>
                    <div data-late-actual-minute-fields class="max-w-sm"><label for="lateMinuteRate" class="mb-1.5 block text-xs font-medium">จำนวนเงินต่อนาที</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#6d7175]">฿</span><input id="lateMinuteRate" type="number" step="0.01" min="0" name="late_minute_rate" data-setting-input value="<?= payrollSettingsEscape($settings['late_deduction_per_minute']) ?>" class="<?= $inputClass ?> pl-8<?= isset($fieldErrors['late_minute_rate']) ? $errorInputClass : '' ?>"></div><?php if (isset($fieldErrors['late_minute_rate'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['late_minute_rate']) ?></p><?php endif; ?></div>
                    <div class="max-w-sm"><label class="inline-flex items-center gap-2 text-xs font-medium"><input type="checkbox" name="late_maximum_enabled" value="1" data-setting-input class="accent-[#0075de]" <?= $settings['max_late_deduction'] !== null && $settings['max_late_deduction'] !== '' ? 'checked' : '' ?>>จำกัดยอดหักจากการมาสายต่อเดือน</label><div data-late-maximum-fields class="relative mt-2"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-[#6d7175]">฿</span><input type="number" step="0.01" min="0" name="max_late_deduction" data-setting-input value="<?= payrollSettingsEscape($settings['max_late_deduction'] ?? '') ?>" class="<?= $inputClass ?> pl-8<?= isset($fieldErrors['max_late_deduction']) ? $errorInputClass : '' ?>" placeholder="1,500"></div><?php if (isset($fieldErrors['max_late_deduction'])): ?><p class="mt-1 text-xs text-red-600"><?= payrollSettingsEscape($fieldErrors['max_late_deduction']) ?></p><?php endif; ?></div>
                    <div class="flex flex-col gap-2 rounded-md bg-[#f6f5f4] p-3 text-xs text-[#615d59] sm:flex-row sm:items-center sm:justify-between"><span><i class="fa-solid fa-circle-info mr-1.5 text-[#0075de]"></i>ข้อมูลการมาสายอ้างอิงจากระบบเช็คชื่อเข้า–ออกงาน</span><a href="/settings/attendance" class="shrink-0 font-medium text-[#0075de] hover:underline">ตั้งค่าเวลาเข้า–ออกงาน <i class="fa-solid fa-arrow-right ml-1"></i></a></div>
                </div>
            </section>

            <section id="adjustment-settings" class="scroll-mt-20 border-b border-[#e6e6e6] py-5"><h2 class="text-sm font-semibold">รายการเพิ่มและรายการหัก</h2><p class="mt-1 text-xs text-[#6d7175]">รายการอัตโนมัติคำนวณโดยระบบ ส่วนรายการกำหนดเองเพิ่มได้ในหน้าจ่ายเงินเดือน</p>
                <div class="mt-4 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2"><div><p class="font-medium text-emerald-700">รายการเพิ่ม</p><p class="mt-2 leading-6 text-[#615d59]">อัตโนมัติ: ค่ารักษาพยาบาล, ค่าที่พัก, ค่าล่วงเวลา<br>กำหนดเอง: โบนัส, เบี้ยขยัน, ค่าตำแหน่ง, ค่าเดินทาง หรือชื่ออื่น</p></div><div><p class="font-medium text-red-700">รายการหัก</p><p class="mt-2 leading-6 text-[#615d59]">อัตโนมัติ: เงินยืม, กองทุน, ขาดงาน, มาสาย<br>กำหนดเอง: ค่าอุปกรณ์ หรือรายการหักอื่น</p></div></div>
                <p class="mt-3 text-xs text-[#6d7175]"><i class="fa-solid fa-lock mr-1.5"></i>รายการขาดงานและมาสายเป็นกฎระบบ จึงไม่สามารถลบจากหน้าจ่ายเงินเดือนได้</p>
            </section>

            <section id="limit-settings" class="scroll-mt-20 border-b border-[#e6e6e6] py-5"><h2 class="text-sm font-semibold">ข้อจำกัดรายการหัก</h2><p class="mt-1 text-xs text-[#6d7175]">ป้องกันยอดหักผิดปกติในการคำนวณใหม่</p>
                <label class="mt-4 flex items-start justify-between gap-4 text-sm"><span><span class="font-medium">อนุญาตให้เงินเดือนสุทธิติดลบ</span><span class="mt-1 block text-xs text-[#6d7175]">เมื่อปิด ระบบจะปฏิเสธการบันทึกหากยอดหักมากกว่ายอดรายได้</span></span><span class="inline-flex shrink-0 cursor-pointer items-center gap-2 text-xs font-medium"><input type="checkbox" name="allow_negative_net_salary" value="1" data-setting-input data-settings-toggle="negative" class="peer sr-only" <?= !empty($settings['allow_negative_net_salary']) ? 'checked' : '' ?>><span class="relative h-5 w-9 rounded-full bg-[#c9c6c2] transition peer-checked:bg-[#0075de] peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-4"></span><span data-toggle-label><?= !empty($settings['allow_negative_net_salary']) ? 'เปิด' : 'ปิด' ?></span></span></label>
            </section>

            <section class="py-5"><h2 class="text-sm font-semibold">ลำดับการคำนวณ</h2><ol class="mt-3 grid grid-cols-1 gap-y-2 text-xs text-[#615d59] sm:grid-cols-2"><li>1. เงินเดือนพื้นฐาน</li><li>2. เพิ่มรายได้เพิ่มเติม</li><li>3. หักขาดงาน</li><li>4. หักมาสาย</li><li>5. หักรายการอื่น</li><li>6. คำนวณเงินเดือนสุทธิ</li></ol><p class="mt-3 rounded-md bg-[#f6f5f4] px-3 py-2 text-xs font-medium">เงินเดือนสุทธิ = เงินเดือนพื้นฐาน + รายการเพิ่ม − รายการหัก</p></section>

            <footer class="-mx-4 flex flex-col gap-3 border-t border-[#e6e6e6] bg-[#fafafa] px-4 py-4 sm:-mx-5 sm:flex-row sm:items-center sm:justify-between sm:px-5"><div><p data-settings-dirty class="hidden text-xs font-medium text-amber-700"><i class="fa-solid fa-circle mr-1 text-[7px]"></i>ยังไม่ได้บันทึกการเปลี่ยนแปลง</p><p class="text-xs text-[#6d7175]">แก้ไขล่าสุด <?= payrollSettingsEscape($updatedLabel) ?><?= !empty($savedSettings['updated_by']) ? ' โดย '.payrollSettingsEscape($savedSettings['updated_by']) : '' ?></p></div><div class="flex gap-2"><button type="button" data-reset-payroll-settings class="h-9 rounded-md border border-[#d5d3d0] bg-white px-3 text-xs font-medium disabled:opacity-40" disabled>ยกเลิกการเปลี่ยนแปลง</button><button type="submit" data-settings-save data-loading-text="กำลังบันทึก..." class="h-9 rounded-md bg-[#303030] px-4 text-sm font-medium text-white disabled:opacity-45">บันทึกการตั้งค่า</button></div></footer>
        </div>

        <aside class="rounded-lg border border-[#dedede] bg-white p-4 xl:sticky xl:top-4 xl:self-start"><h2 class="text-sm font-semibold">ตัวอย่างการคำนวณ</h2><p class="mt-1 text-xs text-[#6d7175]">ข้อมูลตัวอย่างเท่านั้น ไม่ถูกบันทึกเป็นการตั้งค่า</p>
            <div class="mt-4 grid grid-cols-2 gap-3 text-xs"><label class="col-span-2">เงินเดือนตัวอย่าง<div class="relative mt-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6d7175]">฿</span><input type="number" min="0" step="100" value="20000" data-settings-sample="salary" class="<?= $inputClass ?> pl-8"></div></label><label>ขาดงาน<div class="relative mt-1"><input type="number" min="0" step="0.5" value="2" data-settings-sample="absence" class="<?= $inputClass ?> pr-10"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6d7175]">วัน</span></div></label><label>มาสาย<div class="relative mt-1"><input type="number" min="0" value="3" data-settings-sample="late-count" class="<?= $inputClass ?> pr-12"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6d7175]">ครั้ง</span></div></label><label>มาสายรวม<div class="relative mt-1"><input type="number" min="0" value="75" data-settings-sample="late-minutes" class="<?= $inputClass ?> pr-12"><span class="absolute right-3 top-1/2 -translate-y-1/2 text-[#6d7175]">นาที</span></div></label><label>โบนัส<div class="relative mt-1"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6d7175]">฿</span><input type="number" min="0" step="100" value="2000" data-settings-sample="bonus" class="<?= $inputClass ?> pl-8"></div></label></div>
            <dl class="mt-5 space-y-2.5 text-sm"><div class="flex justify-between"><dt>เงินเดือนพื้นฐาน</dt><dd data-settings-base-preview class="font-medium">฿20,000.00</dd></div><div class="flex justify-between text-emerald-700"><dt>โบนัส</dt><dd data-settings-bonus-preview class="font-medium">+฿2,000.00</dd></div><div class="flex justify-between text-red-700"><dt>ขาดงาน</dt><dd data-settings-absence-preview class="font-medium">-฿0.00</dd></div><div class="flex justify-between text-red-700"><dt>มาสาย</dt><dd data-settings-late-preview class="font-medium">-฿0.00</dd></div><div class="border-t border-[#e6e6e6] pt-3"><div class="flex items-end justify-between"><dt class="font-semibold">เงินเดือนสุทธิ</dt><dd data-settings-net-preview class="text-lg font-semibold text-[#0075de]">฿22,000.00</dd></div></div></dl>
            <div class="mt-4 space-y-1 rounded-md bg-[#f6f5f4] p-3 text-xs text-[#615d59]"><p data-settings-absence-formula></p><p data-settings-formula></p></div><p data-settings-negative-warning class="hidden mt-3 rounded-md border border-red-200 bg-red-50 p-2.5 text-xs text-red-700">ตัวอย่างนี้มียอดสุทธิติดลบ และจะไม่สามารถบันทึกการจ่ายได้ตามข้อจำกัดปัจจุบัน</p>
            <div class="mt-5 border-t border-[#e6e6e6] pt-4 text-xs text-[#6d7175]"><p class="font-medium text-[#31302e]">ความปลอดภัยของข้อมูลย้อนหลัง</p><p class="mt-1 leading-5">การเปลี่ยนแปลงนี้มีผลกับการคำนวณใหม่เท่านั้น ประวัติการจ่ายเงินเดือนที่บันทึกแล้วจะไม่ถูกเปลี่ยนแปลง</p></div>
        </aside>
    </form>
</section>
