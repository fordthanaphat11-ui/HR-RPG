<?php
$title = 'ตั้งค่าเวลาเข้า–ออกงาน - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance.php';

if (!isset($_SESSION['username'])) {
    header('Location: /login');
    exit;
}

$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!attendanceValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        $settingsAction = (string) ($_POST['settings_action'] ?? 'schedule');
        if ($settingsAction === 'add_holiday') {
            $holidayDate = trim((string) ($_POST['holiday_date'] ?? ''));
            $holidayName = mb_substr(trim((string) ($_POST['holiday_name'] ?? '')), 0, 160, 'UTF-8');
            $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $holidayDate);
            if (!$parsedDate || $parsedDate->format('Y-m-d') !== $holidayDate || $holidayName === '') throw new InvalidArgumentException('กรุณาระบุวันที่และชื่อวันหยุดให้ครบ');
            $stmt = mysqli_prepare($connection, 'INSERT INTO attendance_holidays (holiday_date,holiday_name) VALUES (?,?) ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name)');
            mysqli_stmt_bind_param($stmt, 'ss', $holidayDate, $holidayName);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('ไม่สามารถบันทึกวันหยุดได้');
            $success = 'บันทึกวันหยุดเรียบร้อยแล้ว';
        } else {
            $workStart = trim((string) ($_POST['work_start'] ?? ''));
            $workEnd = trim((string) ($_POST['work_end'] ?? ''));
            $grace = filter_var($_POST['grace_minutes'] ?? null, FILTER_VALIDATE_INT);
            $workingDays = array_values(array_unique(array_map('intval', (array) ($_POST['working_days'] ?? []))));
            sort($workingDays);
            if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $workStart) || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $workEnd)) throw new InvalidArgumentException('กรุณาระบุเวลาเริ่มและเลิกงานให้ถูกต้อง');
            if ($workEnd <= $workStart) throw new InvalidArgumentException('เวลาเลิกงานต้องอยู่หลังเวลาเริ่มงาน');
            if ($grace === false || $grace < 0 || $grace > 180) throw new InvalidArgumentException('เวลาผ่อนผันต้องอยู่ระหว่าง 0–180 นาที');
            if (!$workingDays || array_diff($workingDays, [1,2,3,4,5,6,7])) throw new InvalidArgumentException('กรุณาเลือกวันทำงานอย่างน้อย 1 วัน');
            $workingDaysValue = implode(',', $workingDays);
            $timezone = 'Asia/Bangkok';
            $username = (string) $_SESSION['username'];
            $stmt = mysqli_prepare($connection, 'UPDATE attendance_settings SET work_start=?,work_end=?,grace_minutes=?,working_days=?,timezone=?,updated_by=? WHERE id=1');
            mysqli_stmt_bind_param($stmt, 'ssisss', $workStart, $workEnd, $grace, $workingDaysValue, $timezone, $username);
            if (!mysqli_stmt_execute($stmt)) throw new RuntimeException('ไม่สามารถบันทึกการตั้งค่าได้');
            $success = 'บันทึกการตั้งค่าเวลาเข้า–ออกงานเรียบร้อยแล้ว';
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$settings = getAttendanceSettings($connection);
$selectedDays = attendanceWorkingDays($settings);
$dayLabels = [1=>'จ',2=>'อ',3=>'พ',4=>'พฤ',5=>'ศ',6=>'ส',7=>'อา'];
$updatedLabel = $settings['updated_at'] ? date('d/m/Y H:i', strtotime($settings['updated_at'])) : 'ยังไม่เคยแก้ไข';
$escape = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$holidays = [];
$holidayResult = mysqli_query($connection, 'SELECT holiday_date,holiday_name FROM attendance_holidays WHERE holiday_date >= DATE_SUB(CURRENT_DATE, INTERVAL 1 MONTH) ORDER BY holiday_date ASC LIMIT 30');
if ($holidayResult) while ($holiday=mysqli_fetch_assoc($holidayResult)) $holidays[]=$holiday;
?>

<div data-attendance-settings class="space-y-4">
    <header class="pb-3 border-b border-[#dedede]"><p class="text-xs font-medium text-[#6d7175]">ตั้งค่าระบบ</p><h1 class="text-lg font-semibold">ตั้งค่าเวลาเข้า–ออกงาน</h1><p class="mt-0.5 text-sm text-[#6d7175]">กำหนดตารางเวลาที่ใช้ตรวจมาสายและคำนวณวันทำงาน</p></header>
    <?php if($error): ?><div role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"><?= $escape($error) ?></div><?php endif; ?>
    <?php if($success): ?><div role="status" class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-sm text-emerald-700"><i class="fa-solid fa-circle-check mr-2"></i><?= $escape($success) ?></div><?php endif; ?>
    <form method="post" action="/settings/attendance" class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_320px]"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="schedule">
        <section class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5" aria-labelledby="workScheduleTitle"><h2 id="workScheduleTitle" class="text-sm font-bold">เวลาทำงาน</h2><p class="mt-1 text-xs text-[#6d7175]">เวลาจริงที่บันทึกยังมาจากเซิร์ฟเวอร์เสมอ</p>
            <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2"><div><label for="attendanceWorkStart" class="mb-1.5 block text-sm font-medium">เวลาเริ่มงาน</label><input id="attendanceWorkStart" type="time" name="work_start" value="<?= $escape(substr((string)$settings['work_start'],0,5)) ?>" required class="min-h-10 w-full rounded-md border border-[#dedede] px-3 text-sm"></div><div><label for="attendanceWorkEnd" class="mb-1.5 block text-sm font-medium">เวลาเลิกงาน</label><input id="attendanceWorkEnd" type="time" name="work_end" value="<?= $escape(substr((string)$settings['work_end'],0,5)) ?>" required class="min-h-10 w-full rounded-md border border-[#dedede] px-3 text-sm"></div></div>
            <div class="mt-4 max-w-xs"><label for="attendanceGrace" class="mb-1.5 block text-sm font-medium">เวลาผ่อนผัน</label><div class="flex items-center gap-2"><input id="attendanceGrace" type="number" name="grace_minutes" min="0" max="180" value="<?= (int)$settings['grace_minutes'] ?>" required class="min-h-10 w-full rounded-md border border-[#dedede] px-3 text-sm"><span class="text-sm text-[#6d7175]">นาที</span></div><p class="mt-1 text-xs text-[#6d7175]">มาสายจะเริ่มนับหลังเวลาเริ่มงานรวมช่วงผ่อนผัน</p></div>
            <fieldset class="mt-5 border-t border-[#e6e6e6] pt-4"><legend class="text-sm font-bold">วันทำงาน</legend><div class="mt-3 flex flex-wrap gap-2"><?php foreach($dayLabels as $day=>$label): ?><label class="inline-flex min-h-9 min-w-11 cursor-pointer items-center justify-center rounded-md border border-[#dedede] px-3 text-sm has-[:checked]:border-[#0075de] has-[:checked]:bg-[#eef6fd] has-[:checked]:text-[#0075de]"><input type="checkbox" name="working_days[]" value="<?= $day ?>" class="sr-only" <?= in_array($day,$selectedDays,true)?'checked':'' ?>><?= $label ?></label><?php endforeach; ?></div></fieldset>
            <div class="mt-5 rounded-md bg-[#f6f5f4] p-3 text-xs text-[#6d7175]"><i class="fa-solid fa-calendar-day mr-1"></i>เริ่มนำข้อมูลไปคำนวณขาดงานตั้งแต่ <?= $escape(date('d/m/Y',strtotime((string)$settings['tracking_start_date']))) ?> เพื่อไม่ให้วันที่ก่อนเปิดใช้โมดูลกลายเป็นวันขาดงาน</div>
            <div class="mt-4 flex flex-col-reverse gap-3 border-t border-[#e6e6e6] pt-4 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs text-[#6d7175]">แก้ไขล่าสุด <?= $escape($updatedLabel) ?><?= $settings['updated_by']?' โดย '.$escape($settings['updated_by']):'' ?></p><button type="submit" data-loading-text="กำลังบันทึก..." class="min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white">บันทึกการตั้งค่า</button></div>
        </section>
        <aside class="rounded-lg border border-[#dedede] bg-white p-4 xl:sticky xl:top-16 xl:self-start" aria-labelledby="attendanceRulePreview"><h2 id="attendanceRulePreview" class="text-sm font-bold">ตัวอย่างการตรวจมาสาย</h2><dl class="mt-4 space-y-2 text-sm"><div class="flex justify-between"><dt class="text-[#6d7175]">เริ่มงาน</dt><dd class="font-semibold"><?= $escape(substr((string)$settings['work_start'],0,5)) ?></dd></div><div class="flex justify-between"><dt class="text-[#6d7175]">ผ่อนผัน</dt><dd class="font-semibold"><?= (int)$settings['grace_minutes'] ?> นาที</dd></div><div class="flex justify-between border-t border-[#e6e6e6] pt-2"><dt class="text-[#6d7175]">เริ่มนับมาสาย</dt><dd class="font-bold text-amber-700"><?= $escape(date('H:i',strtotime((string)$settings['work_start'].' +'.(int)$settings['grace_minutes'].' minutes'))) ?></dd></div></dl><p class="mt-4 rounded-md bg-[#f6f5f4] p-3 text-xs text-[#6d7175]">การตั้งค่านี้เป็นแหล่งเดียวของเวลาเริ่มงานและช่วงผ่อนผัน ส่วนจำนวนเงินที่หักกำหนดในหน้าการคำนวณเงินเดือน</p><p class="mt-3 text-xs text-[#6d7175]"><i class="fa-solid fa-location-dot mr-1"></i>เขตเวลา Asia/Bangkok</p></aside>
    </form>
    <section class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5" aria-labelledby="holidaySettingsTitle"><div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><h2 id="holidaySettingsTitle" class="text-sm font-bold">วันหยุดขององค์กร</h2><p class="mt-1 text-xs text-[#6d7175]">วันที่บันทึกไว้จะไม่ถูกนับเป็นวันขาดงาน</p></div><span class="text-xs text-[#6d7175]"><?= count($holidays) ?> รายการ</span></div><form method="post" action="/settings/attendance" class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-[180px_minmax(0,1fr)_auto]"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="add_holiday"><div><label for="holidayDate" class="mb-1.5 block text-xs font-medium">วันที่</label><input id="holidayDate" type="date" name="holiday_date" required class="min-h-10 w-full rounded-md border border-[#dedede] px-3 text-sm"></div><div><label for="holidayName" class="mb-1.5 block text-xs font-medium">ชื่อวันหยุด</label><input id="holidayName" name="holiday_name" maxlength="160" required class="min-h-10 w-full rounded-md border border-[#dedede] px-3 text-sm" placeholder="เช่น วันหยุดประจำปี"></div><button type="submit" data-loading-text="กำลังบันทึก..." class="min-h-10 self-end rounded-md border border-[#d5d3d0] bg-white px-3 text-sm font-medium hover:bg-[#f6f5f4]"><i class="fa-solid fa-plus mr-2"></i>เพิ่มวันหยุด</button></form><?php if($holidays): ?><div class="mt-4 divide-y divide-[#e6e6e6] border-t border-[#e6e6e6]"><?php foreach($holidays as $holiday): ?><div class="flex items-center justify-between gap-3 py-2.5 text-sm"><span class="font-medium"><?= $escape($holiday['holiday_name']) ?></span><time class="text-xs text-[#6d7175]" datetime="<?= $escape($holiday['holiday_date']) ?>"><?= $escape(date('d/m/Y',strtotime($holiday['holiday_date']))) ?></time></div><?php endforeach; ?></div><?php else: ?><p class="mt-4 rounded-md bg-[#f6f5f4] p-3 text-xs text-[#6d7175]">ยังไม่มีวันหยุดที่ลงทะเบียน</p><?php endif; ?></section>
</div>
