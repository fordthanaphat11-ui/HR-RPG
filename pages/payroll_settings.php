<?php
$title = 'ตั้งค่าการคำนวณเงินเดือน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/payroll.php';

if (!isset($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['absence_enabled']) ? 1 : 0;
    $absenceRate = filter_var($_POST['absence_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $lateMode = (string) ($_POST['late_mode'] ?? 'none');
    $lateOccurrence = filter_var($_POST['late_occurrence_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $interval = filter_var($_POST['late_interval_minutes'] ?? null, FILTER_VALIDATE_INT);
    $intervalRate = filter_var($_POST['late_interval_rate'] ?? null, FILTER_VALIDATE_FLOAT);
    $rounding = (string) ($_POST['late_rounding'] ?? 'ceil');
    // Grace period belongs to attendance settings; payroll consumes already-adjusted late minutes.
    $grace = 0;
    $maximumRaw = trim((string) ($_POST['max_late_deduction'] ?? ''));
    $maximum = $maximumRaw === '' ? null : filter_var($maximumRaw, FILTER_VALIDATE_FLOAT);

    if ($absenceRate === false || $absenceRate < 0 || !in_array($lateMode, ['none','per_occurrence','per_minutes'], true)
        || $lateOccurrence === false || $lateOccurrence < 0 || $interval === false || $interval <= 0
        || $intervalRate === false || $intervalRate < 0 || !in_array($rounding, ['ceil','floor'], true)
        || ($maximumRaw !== '' && ($maximum === false || $maximum < 0))) {
        $error = 'กรุณาตรวจสอบค่าการคำนวณ ทุกจำนวนต้องไม่ติดลบและช่วงนาทีต้องมากกว่า 0';
    } else {
        $stmt = mysqli_prepare($connection, "UPDATE payroll_settings SET absence_deduction_enabled=?, absence_deduction_per_day=?, late_deduction_mode=?, late_deduction_per_occurrence=?, late_interval_minutes=?, late_deduction_per_interval=?, late_rounding_mode=?, late_grace_minutes=?, max_late_deduction=?, updated_by=? WHERE id=1");
        $username = (string) $_SESSION['username'];
        mysqli_stmt_bind_param($stmt, 'idsdidsids', $enabled, $absenceRate, $lateMode, $lateOccurrence, $interval, $intervalRate, $rounding, $grace, $maximum, $username);
        if (mysqli_stmt_execute($stmt)) $success = 'บันทึกการตั้งค่าเรียบร้อยแล้ว';
        else $error = 'ไม่สามารถบันทึกการตั้งค่าได้';
    }
}

$settings = getPayrollSettings($connection);
$updatedLabel = $settings['updated_at'] ? date('d/m/Y H:i', strtotime($settings['updated_at'])) : 'ยังไม่เคยแก้ไข';
?>

<div data-payroll-settings class="space-y-4">
    <header class="pb-4 border-b border-[#e6e6e6]">
        <p class="text-xs font-medium text-[#6d7175]">ตั้งค่าระบบ</p>
        <h1 class="text-xl font-semibold">ตั้งค่าการคำนวณเงินเดือน</h1>
        <p class="mt-0.5 text-sm text-[#6d7175]">กำหนดกฎรายการหักอัตโนมัติ โดยการจ่ายย้อนหลังจะยังใช้ snapshot เดิมเสมอ</p>
    </header>

    <?php if ($error): ?><div role="alert" class="rounded-[8px] border border-red-200 bg-red-50 p-3 text-red-700"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div role="status" class="rounded-[8px] border border-emerald-200 bg-emerald-50 p-3 text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="post" action="/settings/payroll" class="grid grid-cols-1 gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-[#dedede] bg-white p-4 xl:col-span-2">
            <section class="pb-5" aria-labelledby="absenceSettingsTitle">
                <div class="flex items-start justify-between gap-4">
                    <div><h2 id="absenceSettingsTitle" class="text-[17px] font-bold">การขาดงาน</h2><p class="mt-1 text-[13px] text-[#615d59]">หักเงินตามจำนวนวันที่ขาดในงวดที่เลือก</p></div>
                    <label class="inline-flex items-center gap-2 text-[14px] font-medium"><input type="checkbox" name="absence_enabled" value="1" data-setting-input class="w-5 h-5 accent-[#0075de]" <?= !empty($settings['absence_deduction_enabled']) ? 'checked' : '' ?>> เปิดใช้</label>
                </div>
                <div class="mt-5 max-w-sm"><label class="block text-[14px] font-medium mb-2">จำนวนเงินที่หักต่อวัน</label><div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2">฿</span><input type="number" step="0.01" min="0" name="absence_rate" data-setting-input value="<?= htmlspecialchars((string)$settings['absence_deduction_per_day']) ?>" class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] pl-8 pr-3"></div></div>
            </section>

            <section class="border-t border-[#dedede] pt-5 pb-5" aria-labelledby="lateSettingsTitle">
                <h2 id="lateSettingsTitle" class="text-[17px] font-bold">การมาสาย</h2>
                <p class="mt-1 text-[13px] text-[#615d59]">เลือกวิธีคิดจำนวนเงินจากสถิติที่ส่งมาจากระบบลงเวลา</p>
                <div class="mt-4 grid gap-2 sm:grid-cols-3">
                    <?php foreach (['none'=>'ไม่หักเงิน','per_occurrence'=>'หักต่อครั้ง','per_minutes'=>'หักตามนาที'] as $value=>$label): ?>
                        <label class="flex min-h-11 items-center gap-2 rounded-[8px] border border-[#e6e6e6] px-3"><input type="radio" name="late_mode" value="<?= $value ?>" data-setting-input class="accent-[#0075de]" <?= $settings['late_deduction_mode']===$value?'checked':'' ?>> <?= $label ?></label>
                    <?php endforeach; ?>
                </div>
                <div data-late-occurrence-fields class="mt-4"><label class="block text-[14px] font-medium mb-2">จำนวนเงินต่อครั้ง</label><div class="relative max-w-sm"><span class="absolute left-3 top-1/2 -translate-y-1/2">฿</span><input type="number" step="0.01" min="0" name="late_occurrence_rate" data-setting-input value="<?= htmlspecialchars((string)$settings['late_deduction_per_occurrence']) ?>" class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] pl-8 pr-3"></div></div>
                <div data-late-minute-fields class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div><label class="block text-[14px] font-medium mb-2">ช่วงเวลา (นาที)</label><input type="number" min="1" name="late_interval_minutes" data-setting-input value="<?= htmlspecialchars((string)$settings['late_interval_minutes']) ?>" class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"></div>
                    <div><label class="block text-[14px] font-medium mb-2">จำนวนเงินต่อช่วง</label><input type="number" step="0.01" min="0" name="late_interval_rate" data-setting-input value="<?= htmlspecialchars((string)$settings['late_deduction_per_interval']) ?>" class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"></div>
                    <div><label class="block text-[14px] font-medium mb-2">การปัดเศษ</label><select name="late_rounding" data-setting-input class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"><option value="ceil" <?= $settings['late_rounding_mode']==='ceil'?'selected':'' ?>>ปัดขึ้น</option><option value="floor" <?= $settings['late_rounding_mode']==='floor'?'selected':'' ?>>ปัดลง</option></select></div>
                </div>
                <div class="mt-4 max-w-sm"><label class="block text-[14px] font-medium mb-2">หักสูงสุดต่อเดือน (เว้นว่าง = ไม่จำกัด)</label><input type="number" step="0.01" min="0" name="max_late_deduction" data-setting-input value="<?= htmlspecialchars((string)($settings['max_late_deduction'] ?? '')) ?>" class="w-full min-h-11 rounded-[7px] border border-[#e6e6e6] px-3"><p class="mt-2 text-[12px] text-[#615d59]"><i class="fa-solid fa-circle-info mr-1"></i>เวลาเริ่มงานและช่วงผ่อนผันกำหนดที่หน้า “เวลาเข้า–ออกงาน”</p></div>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-[#dedede] pt-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-[#6d7175]">แก้ไขล่าสุด <?= htmlspecialchars($updatedLabel) ?><?= $settings['updated_by'] ? ' โดย '.htmlspecialchars($settings['updated_by']) : '' ?></p><button type="submit" data-loading-text="กำลังบันทึก..." class="min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white">บันทึกการตั้งค่า</button></div>
        </div>

        <aside class="xl:sticky xl:top-16 xl:self-start rounded-lg border border-[#dedede] bg-white p-4" aria-labelledby="settingsPreviewTitle">
            <h2 id="settingsPreviewTitle" class="font-bold">ตัวอย่างการคำนวณ</h2><p class="mt-1 text-[13px] text-[#615d59]">ขาดงาน 2 วัน · มาสาย 3 ครั้ง รวม 75 นาที</p>
            <dl class="mt-5 space-y-3 text-[14px]"><div class="flex justify-between"><dt>เงินเดือนตัวอย่าง</dt><dd class="font-bold">฿20,000</dd></div><div class="flex justify-between text-red-600"><dt>- ขาดงาน</dt><dd data-settings-absence-preview>-฿0</dd></div><div class="flex justify-between text-red-600"><dt>- มาสาย</dt><dd data-settings-late-preview>-฿0</dd></div><div class="border-t border-[#b7d6f1] pt-3 flex justify-between"><dt class="font-bold">เงินเดือนหลังหัก</dt><dd data-settings-net-preview class="text-xl font-bold">฿20,000</dd></div></dl>
            <p data-settings-formula class="mt-4 rounded-[7px] bg-white p-3 text-[12px] text-[#615d59]"></p>
        </aside>
    </form>
</div>
