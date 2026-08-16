<?php
declare(strict_types=1);
$title = 'พื้นที่เช็คชื่อ - ระบบบริหารเงินเดือน';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/attendance.php';

function attendanceLocationSettingsRedirect(string $path): never
{
    if (isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true') {
        header('HX-Location: ' . json_encode(['path'=>$path,'target'=>'#app-content','select'=>'#app-content','swap'=>'outerHTML transition:true'], JSON_UNESCAPED_SLASHES));
        exit;
    }
    header('Location: ' . $path);
    exit;
}

$error = '';
$success = (string) ($_SESSION['attendance_location_flash'] ?? '');
unset($_SESSION['attendance_location_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!attendanceValidateCsrf((string) ($_POST['csrf_token'] ?? ''))) throw new RuntimeException('คำขอหมดอายุ กรุณาลองใหม่อีกครั้ง');
        $action = (string) ($_POST['settings_action'] ?? '');
        $username = (string) $_SESSION['username'];

        if ($action === 'save_policy') {
            $enforce = isset($_POST['enforce_geofence']) ? 1 : 0;
            $requireIn = isset($_POST['require_check_in']) ? 1 : 0;
            $requireOut = isset($_POST['require_check_out']) ? 1 : 0;
            $maxAccuracy = filter_var($_POST['max_accuracy_meters'] ?? null, FILTER_VALIDATE_FLOAT);
            if ($maxAccuracy === false || $maxAccuracy < 5 || $maxAccuracy > 1000) throw new InvalidArgumentException('ความคลาดเคลื่อน GPS สูงสุดต้องอยู่ระหว่าง 5–1,000 เมตร');
            if ($enforce && !$requireIn && !$requireOut) throw new InvalidArgumentException('เมื่อบังคับตรวจตำแหน่ง ต้องเลือกตรวจตอนเช็คอินหรือเช็คเอาต์อย่างน้อยหนึ่งรายการ');
            $stmt = mysqli_prepare($connection, 'UPDATE attendance_location_settings SET enforce_geofence=?,require_check_in=?,require_check_out=?,max_accuracy_meters=?,updated_by=? WHERE id=1');
            mysqli_stmt_bind_param($stmt, 'iiids', $enforce, $requireIn, $requireOut, $maxAccuracy, $username);
            mysqli_stmt_execute($stmt);
            $_SESSION['attendance_location_flash'] = 'บันทึกนโยบายตำแหน่งเรียบร้อยแล้ว';
            attendanceLocationSettingsRedirect('/settings/attendance/location');
        }

        if ($action === 'save_geofence') {
            $geofenceId = filter_var($_POST['geofence_id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
            $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 160, 'UTF-8');
            $description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 500, 'UTF-8');
            $scopeType = (string) ($_POST['scope_type'] ?? 'all');
            $departmentId = $scopeType === 'department' ? (filter_var($_POST['department_id'] ?? null, FILTER_VALIDATE_INT) ?: null) : null;
            $isActive = isset($_POST['is_active']) ? 1 : 0;
            $polygon = geofenceDecodePolygon((string) ($_POST['polygon_json'] ?? ''));
            $polygonJson = geofenceEncodePolygon($polygon);
            if ($name === '') throw new InvalidArgumentException('กรุณาระบุชื่อพื้นที่');
            if (!in_array($scopeType, ['all','department'], true)) throw new InvalidArgumentException('ขอบเขตพนักงานไม่ถูกต้อง');
            if ($scopeType === 'department' && !$departmentId) throw new InvalidArgumentException('กรุณาเลือกแผนก');

            if ($geofenceId > 0) {
                $stmt = mysqli_prepare($connection, 'UPDATE attendance_geofences SET name=?,description=?,polygon_json=?,is_active=?,scope_type=?,department_id=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'sssisii', $name, $description, $polygonJson, $isActive, $scopeType, $departmentId, $geofenceId);
            } else {
                $stmt = mysqli_prepare($connection, 'INSERT INTO attendance_geofences (name,description,polygon_json,is_active,scope_type,department_id,created_by) VALUES (?,?,?,?,?,?,?)');
                mysqli_stmt_bind_param($stmt, 'sssisis', $name, $description, $polygonJson, $isActive, $scopeType, $departmentId, $username);
            }
            mysqli_stmt_execute($stmt);
            $_SESSION['attendance_location_flash'] = 'บันทึกพื้นที่เช็คชื่อเรียบร้อยแล้ว';
            attendanceLocationSettingsRedirect('/settings/attendance/location');
        }

        if (in_array($action, ['toggle_geofence','delete_geofence'], true)) {
            $geofenceId = filter_var($_POST['geofence_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$geofenceId) throw new InvalidArgumentException('ไม่พบพื้นที่ที่เลือก');
            $lookup = mysqli_prepare($connection, 'SELECT id,name,is_active FROM attendance_geofences WHERE id=? LIMIT 1');
            mysqli_stmt_bind_param($lookup, 'i', $geofenceId); mysqli_stmt_execute($lookup);
            $geofence = mysqli_fetch_assoc(mysqli_stmt_get_result($lookup)); mysqli_stmt_close($lookup);
            if (!$geofence) throw new RuntimeException('ไม่พบพื้นที่ที่เลือก');

            if ($action === 'toggle_geofence') {
                $newStatus = (int) $geofence['is_active'] === 1 ? 0 : 1;
                $stmt = mysqli_prepare($connection, 'UPDATE attendance_geofences SET is_active=? WHERE id=?');
                mysqli_stmt_bind_param($stmt, 'ii', $newStatus, $geofenceId); mysqli_stmt_execute($stmt);
                $_SESSION['attendance_location_flash'] = ($newStatus ? 'เปิด' : 'ปิด') . 'ใช้งานพื้นที่ “' . $geofence['name'] . '” แล้ว';
            } else {
                $usage = mysqli_prepare($connection, 'SELECT COUNT(*) AS usage_count FROM attendance WHERE check_in_geofence_id=? OR check_out_geofence_id=?');
                mysqli_stmt_bind_param($usage, 'ii', $geofenceId, $geofenceId); mysqli_stmt_execute($usage);
                $usageCount = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($usage))['usage_count']; mysqli_stmt_close($usage);
                if ($usageCount > 0) {
                    $stmt = mysqli_prepare($connection, 'UPDATE attendance_geofences SET is_active=0 WHERE id=?');
                    mysqli_stmt_bind_param($stmt, 'i', $geofenceId); mysqli_stmt_execute($stmt);
                    $_SESSION['attendance_location_flash'] = 'พื้นที่มีประวัติการใช้งาน จึงปิดใช้งานแทนการลบเพื่อรักษาหลักฐานเดิม';
                } else {
                    $stmt = mysqli_prepare($connection, 'DELETE FROM attendance_geofences WHERE id=?');
                    mysqli_stmt_bind_param($stmt, 'i', $geofenceId); mysqli_stmt_execute($stmt);
                    $_SESSION['attendance_location_flash'] = 'ลบพื้นที่เช็คชื่อเรียบร้อยแล้ว';
                }
            }
            attendanceLocationSettingsRedirect('/settings/attendance/location');
        }

        throw new InvalidArgumentException('คำสั่งตั้งค่าไม่ถูกต้อง');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$locationSettings = geofenceGetSettings($connection);
$departments = [];
$departmentResult = mysqli_query($connection, 'SELECT Depart_id,Depart_name FROM department ORDER BY Depart_name');
if ($departmentResult) while ($department = mysqli_fetch_assoc($departmentResult)) $departments[] = $department;
$geofences = [];
$geofenceResult = mysqli_query($connection, "SELECT g.*,d.Depart_name,
    (SELECT COUNT(*) FROM attendance a WHERE a.check_in_geofence_id=g.id OR a.check_out_geofence_id=g.id) AS usage_count
    FROM attendance_geofences g LEFT JOIN department d ON d.Depart_id=g.department_id
    ORDER BY g.is_active DESC,g.priority DESC,g.name,g.id");
if ($geofenceResult) while ($row = mysqli_fetch_assoc($geofenceResult)) {
    try { $row['points'] = geofenceDecodePolygon((string) $row['polygon_json']); }
    catch (Throwable) { $row['points'] = []; }
    $geofences[] = $row;
}
$activeCount = count(array_filter($geofences, static fn(array $row): bool => (int)$row['is_active'] === 1 && count($row['points']) >= 3));
$mapGeofences = array_map(static fn(array $row): array => [
    'id'=>(int)$row['id'],'name'=>$row['name'],'description'=>$row['description'],'points'=>$row['points'],
    'is_active'=>(int)$row['is_active']===1,'scope_type'=>$row['scope_type'],'department_id'=>$row['department_id'] ? (int)$row['department_id'] : null,
], $geofences);
$firstPoint = $geofences[0]['points'][0] ?? null;
$defaultLat = $firstPoint['lat'] ?? ($locationSettings['default_latitude'] !== null ? (float)$locationSettings['default_latitude'] : 13.7563);
$defaultLng = $firstPoint['lng'] ?? ($locationSettings['default_longitude'] !== null ? (float)$locationSettings['default_longitude'] : 100.5018);
$escape = static fn(mixed $value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$mapConfig = ['geofences'=>$mapGeofences,'default_center'=>['lat'=>$defaultLat,'lng'=>$defaultLng],'default_zoom'=>$geofences ? 16 : 6];
?>

<div class="w-full space-y-4" data-geofence-settings-page>
    <header class="flex flex-col gap-3 border-b border-[#dedede] pb-3 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-medium text-[#6d7175]">ตั้งค่าระบบ</p><h1 class="text-lg font-semibold">พื้นที่เช็คชื่อ</h1><p class="mt-0.5 text-sm text-[#6d7175]">กำหนดตำแหน่งที่อนุญาตให้พนักงานเช็คชื่อเข้า–ออกงาน</p></div><button type="button" data-geofence-add class="min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white"><i class="fa-solid fa-plus mr-2"></i>สร้างพื้นที่</button></header>
    <?php if ($success): ?><div role="status" class="fixed right-4 top-16 z-[70] max-w-sm rounded-md border border-emerald-200 bg-white px-4 py-3 text-sm text-emerald-700 shadow-lg"><i class="fa-solid fa-circle-check mr-2"></i><?= $escape($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div role="alert" class="rounded-md border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700"><?= $escape($error) ?></div><?php endif; ?>
    <?php if ((int)$locationSettings['enforce_geofence'] === 1 && $activeCount === 0): ?><div class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2.5 text-sm text-amber-800"><i class="fa-solid fa-triangle-exclamation mr-2"></i>ยังไม่มีพื้นที่เช็คชื่อที่เปิดใช้งาน พนักงานจะยังเช็คชื่อไม่ได้</div><?php endif; ?>

    <section class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_340px]">
        <div class="min-w-0 overflow-hidden rounded-lg border border-[#dedede] bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[#e6e6e6] px-4 py-3"><div><h2 class="text-sm font-bold">แผนที่พื้นที่อนุญาต</h2><p class="mt-0.5 text-xs text-[#6d7175]">วาด Polygon อย่างน้อย 3 จุด แล้วบันทึกชื่อพื้นที่</p></div><button type="button" data-geofence-current-location class="min-h-8 rounded-md border border-[#d5d3d0] bg-white px-2.5 text-xs font-medium hover:bg-[#f6f5f4]"><i class="fa-solid fa-location-crosshairs mr-1.5"></i>ไปตำแหน่งปัจจุบัน</button></div>
            <div id="attendanceGeofenceMap" class="h-[400px] w-full overflow-hidden bg-[#ececec] sm:h-[500px]" aria-label="แผนที่พื้นที่เช็คชื่อ"></div>
            <div data-geofence-map-error class="hidden border-t border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"></div>
        </div>

        <aside class="min-w-0 overflow-hidden rounded-lg border border-[#dedede] bg-white xl:max-h-[558px]" aria-labelledby="geofenceListTitle"><header class="flex items-center justify-between border-b border-[#e6e6e6] p-4"><div><h2 id="geofenceListTitle" class="text-sm font-bold">พื้นที่ทั้งหมด</h2><p class="mt-0.5 text-xs text-[#6d7175]"><?= count($geofences) ?> พื้นที่ · เปิด <?= $activeCount ?></p></div></header><?php if ($geofences): ?><div class="divide-y divide-[#e6e6e6] xl:max-h-[500px] xl:overflow-y-auto"><?php foreach ($geofences as $geofence): ?><article class="p-4" data-geofence-list-item="<?= (int)$geofence['id'] ?>"><button type="button" data-geofence-focus="<?= (int)$geofence['id'] ?>" class="w-full text-left"><div class="flex items-start justify-between gap-2"><div class="min-w-0"><p class="truncate text-sm font-bold"><?= $escape($geofence['name']) ?></p><p class="mt-0.5 text-xs text-[#6d7175]"><?= count($geofence['points']) ?> จุด · <?= $geofence['scope_type']==='department' ? $escape($geofence['Depart_name'] ?: 'ไม่พบแผนก') : 'พนักงานทุกคน' ?></p></div><span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-semibold <?= (int)$geofence['is_active']===1?'bg-emerald-50 text-emerald-700':'bg-[#f2f2f2] text-[#6d7175]' ?>"><?= (int)$geofence['is_active']===1?'ใช้งาน':'ปิดใช้งาน' ?></span></div><?php if($geofence['description']): ?><p class="mt-2 line-clamp-2 text-xs text-[#6d7175]"><?= $escape($geofence['description']) ?></p><?php endif; ?></button><div class="mt-3 flex flex-wrap gap-2"><button type="button" data-geofence-edit="<?= (int)$geofence['id'] ?>" class="min-h-8 rounded-md border border-[#d5d3d0] px-2.5 text-xs font-medium"><i class="fa-solid fa-pen mr-1.5"></i>แก้ไข</button><form method="post" action="/settings/attendance/location"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="toggle_geofence"><input type="hidden" name="geofence_id" value="<?= (int)$geofence['id'] ?>"><button type="submit" class="min-h-8 rounded-md border border-[#d5d3d0] px-2.5 text-xs font-medium"><?= (int)$geofence['is_active']===1?'ปิด':'เปิด' ?>ใช้งาน</button></form><button type="button" data-geofence-delete="<?= (int)$geofence['id'] ?>" data-geofence-name="<?= $escape($geofence['name']) ?>" class="min-h-8 rounded-md border border-red-200 px-2.5 text-xs font-medium text-red-700">ลบ</button></div><?php if((int)$geofence['usage_count']>0): ?><p class="mt-2 text-[11px] text-[#8a8580]"><i class="fa-solid fa-clock-rotate-left mr-1"></i>มีหลักฐาน <?= (int)$geofence['usage_count'] ?> รายการ จะปิดใช้งานแทนเมื่อลบ</p><?php endif; ?></article><?php endforeach; ?></div><?php else: ?><div class="flex min-h-[230px] flex-col items-center justify-center p-6 text-center"><span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#f2f2f2] text-[#8a8580]"><i class="fa-solid fa-draw-polygon"></i></span><h3 class="mt-3 text-sm font-bold">ยังไม่มีพื้นที่เช็คชื่อ</h3><p class="mt-1 text-xs text-[#6d7175]">กด “สร้างพื้นที่” แล้ววาดขอบเขตบนแผนที่</p></div><?php endif; ?></aside>
    </section>

    <section id="geofenceEditor" class="hidden rounded-lg border border-[#b7d6f1] bg-white p-4 sm:p-5" aria-labelledby="geofenceEditorTitle"><div class="flex items-start justify-between gap-3"><div><h2 id="geofenceEditorTitle" class="text-sm font-bold">รายละเอียดพื้นที่</h2><p class="mt-0.5 text-xs text-[#6d7175]">ลากจุดบนแผนที่เพื่อปรับขอบเขต ระบบเก็บลำดับเป็น latitude/longitude</p></div><button type="button" data-geofence-cancel class="h-8 w-8 rounded-md text-[#6d7175] hover:bg-[#f6f5f4]" aria-label="ยกเลิกการแก้ไข"><i class="fa-solid fa-xmark"></i></button></div><form id="geofenceEditorForm" method="post" action="/settings/attendance/location" hx-post="/settings/attendance/location" hx-target="#app-content" hx-select="#app-content" hx-swap="outerHTML transition:true" class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="save_geofence"><input type="hidden" name="geofence_id" data-geofence-field="id"><input type="hidden" name="polygon_json" data-geofence-field="polygon"><div><label for="geofenceName" class="mb-1.5 block text-sm font-medium">ชื่อพื้นที่</label><input id="geofenceName" name="name" maxlength="160" required class="w-full border border-[#dedede]" placeholder="เช่น สำนักงานใหญ่"></div><div><label for="geofenceDescription" class="mb-1.5 block text-sm font-medium">รายละเอียด</label><input id="geofenceDescription" name="description" maxlength="500" class="w-full border border-[#dedede]" placeholder="เช่น อาคารสำนักงานบริษัท"></div><div><label for="geofenceScope" class="mb-1.5 block text-sm font-medium">ใช้สำหรับ</label><select id="geofenceScope" name="scope_type" data-geofence-scope class="w-full border border-[#dedede]"><option value="all">พนักงานทุกคน</option><option value="department">เฉพาะแผนก</option></select></div><div data-geofence-department-wrap class="hidden"><label for="geofenceDepartment" class="mb-1.5 block text-sm font-medium">แผนก</label><select id="geofenceDepartment" name="department_id" class="w-full border border-[#dedede]"><option value="">เลือกแผนก</option><?php foreach($departments as $department): ?><option value="<?= (int)$department['Depart_id'] ?>"><?= $escape($department['Depart_name']) ?></option><?php endforeach; ?></select></div><label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-md border border-[#e6e6e6] px-3 lg:col-span-2"><input type="checkbox" name="is_active" value="1" data-geofence-field="active" checked class="h-4 w-4 accent-[#0075de]"><span><span class="block text-sm font-medium">เปิดใช้งานพื้นที่นี้</span><span class="block text-xs text-[#6d7175]">พื้นที่ที่ปิดใช้งานจะไม่ผ่านการตรวจตำแหน่ง</span></span></label><div class="flex flex-col-reverse gap-2 border-t border-[#e6e6e6] pt-4 sm:flex-row sm:justify-end lg:col-span-2"><button type="button" data-geofence-cancel class="min-h-9 rounded-md border border-[#d5d3d0] px-3 text-sm font-medium">ยกเลิกการแก้ไข</button><button type="submit" data-geofence-save class="min-h-9 rounded-md bg-[#0075de] px-4 text-sm font-medium text-white">บันทึกพื้นที่</button></div></form></section>

    <div data-geofence-unsaved class="hidden rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p><i class="fa-solid fa-circle-exclamation mr-2"></i>มีการแก้ไขพื้นที่ที่ยังไม่ได้บันทึก</p><div class="flex gap-2"><button type="button" data-geofence-cancel class="min-h-8 rounded-md border border-amber-300 bg-white px-3 text-xs font-medium">ยกเลิกการแก้ไข</button><button type="button" data-geofence-save-trigger class="min-h-8 rounded-md bg-[#303030] px-3 text-xs font-medium text-white">บันทึก</button></div></div></div>

    <form method="post" action="/settings/attendance/location" class="rounded-lg border border-[#dedede] bg-white p-4 sm:p-5"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="save_policy"><div class="flex items-start justify-between gap-4"><div><h2 class="text-sm font-bold">นโยบายตรวจตำแหน่ง</h2><p class="mt-1 text-xs text-[#6d7175]">พิกัดจากเบราว์เซอร์เป็นข้อมูลที่ไม่เชื่อถือจนกว่าฝั่ง PHP จะตรวจสอบ Polygon</p></div><label class="relative inline-flex cursor-pointer items-center"><input type="checkbox" name="enforce_geofence" value="1" class="peer sr-only" <?= (int)$locationSettings['enforce_geofence']===1?'checked':'' ?>><span class="h-6 w-11 rounded-full bg-[#d5d3d0] peer-checked:bg-[#0075de] after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition peer-checked:after:translate-x-5"></span><span class="sr-only">บังคับตรวจสอบตำแหน่ง</span></label></div><div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2"><label class="flex cursor-pointer items-center gap-3 rounded-md border border-[#e6e6e6] p-3"><input type="checkbox" name="require_check_in" value="1" class="h-4 w-4 accent-[#0075de]" <?= (int)$locationSettings['require_check_in']===1?'checked':'' ?>><span class="text-sm font-medium">ตรวจตำแหน่งตอนเช็คอิน</span></label><label class="flex cursor-pointer items-center gap-3 rounded-md border border-[#e6e6e6] p-3"><input type="checkbox" name="require_check_out" value="1" class="h-4 w-4 accent-[#0075de]" <?= (int)$locationSettings['require_check_out']===1?'checked':'' ?>><span class="text-sm font-medium">ตรวจตำแหน่งตอนเช็คเอาต์</span></label></div><div class="mt-4 max-w-sm"><label for="maxGpsAccuracy" class="mb-1.5 block text-sm font-medium">ความคลาดเคลื่อน GPS สูงสุด</label><div class="flex items-center gap-2"><input id="maxGpsAccuracy" type="number" name="max_accuracy_meters" min="5" max="1000" step="1" required value="<?= $escape((float)$locationSettings['max_accuracy_meters']) ?>" class="w-full border border-[#dedede]"><span class="text-sm text-[#6d7175]">เมตร</span></div><p class="mt-1 text-xs text-[#6d7175]">ค่าที่สูงกว่านี้จะถูกปฏิเสธและให้พนักงานลองรับสัญญาณใหม่</p></div><div class="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><p class="font-semibold"><i class="fa-solid fa-shield-halved mr-1.5"></i>ข้อควรทราบด้านความปลอดภัย</p><ul class="mt-1.5 list-disc space-y-1 pl-5"><li>ระบบจริงต้องเปิดผ่าน HTTPS มิฉะนั้นเบราว์เซอร์จะไม่อนุญาตให้ใช้ตำแหน่ง</li><li>ตำแหน่ง GPS สามารถถูกปลอมแปลงได้บางกรณี การตรวจฝั่งเซิร์ฟเวอร์ช่วยลดการข้ามกฎ แต่ไม่สามารถป้องกันการปลอมแปลงได้ทั้งหมด</li></ul></div><div class="mt-4 flex justify-end border-t border-[#e6e6e6] pt-4"><button type="submit" class="min-h-9 rounded-md bg-[#303030] px-3 text-sm font-medium text-white">บันทึกนโยบาย</button></div></form>

    <div id="geofenceDeleteModal" class="fixed inset-0 z-[90] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="geofenceDeleteTitle"><div class="absolute inset-0 bg-black/40" data-geofence-delete-close></div><form method="post" action="/settings/attendance/location" class="relative w-full max-w-md rounded-lg border border-[#dedede] bg-white p-5 shadow-2xl"><input type="hidden" name="csrf_token" value="<?= $escape(attendanceCsrfToken()) ?>"><input type="hidden" name="settings_action" value="delete_geofence"><input type="hidden" name="geofence_id" data-geofence-delete-id><span class="flex h-10 w-10 items-center justify-center rounded-full bg-red-50 text-red-700"><i class="fa-solid fa-trash-can"></i></span><h2 id="geofenceDeleteTitle" class="mt-4 text-base font-bold">ลบพื้นที่เช็คชื่อ?</h2><p data-geofence-delete-name class="mt-1 text-sm font-medium"></p><p class="mt-2 text-xs text-[#6d7175]">พนักงานจะไม่สามารถใช้พื้นที่นี้เช็คชื่อได้อีก หากมีประวัติ ระบบจะปิดใช้งานแทนการลบ</p><div class="mt-5 grid grid-cols-2 gap-3"><button type="button" data-geofence-delete-close class="min-h-10 rounded-md border border-[#d5d3d0] text-sm font-medium">ยกเลิก</button><button type="submit" class="min-h-10 rounded-md bg-red-600 px-3 text-sm font-medium text-white">ลบพื้นที่</button></div></form></div>
    <script type="application/json" id="geofenceMapConfig"><?= json_encode($mapConfig, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
</div>
