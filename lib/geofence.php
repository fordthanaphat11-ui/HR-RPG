<?php
declare(strict_types=1);

/**
 * Coordinate convention used everywhere in this module:
 * latitude = Y axis, longitude = X axis, JSON vertex = {"lat": Y, "lng": X}.
 */

function geofenceGetSettings(mysqli $connection): array
{
    $result = mysqli_query($connection, 'SELECT * FROM attendance_location_settings WHERE id=1 LIMIT 1');
    $settings = $result ? mysqli_fetch_assoc($result) : null;
    return $settings ?: [
        'id'=>1, 'enforce_geofence'=>0, 'require_check_in'=>1, 'require_check_out'=>1,
        'max_accuracy_meters'=>50, 'default_latitude'=>null, 'default_longitude'=>null,
        'updated_by'=>null, 'updated_at'=>null,
    ];
}

function geofenceDecodePolygon(string $json): array
{
    try {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('ข้อมูล Polygon ไม่ถูกต้อง');
    }
    if (!is_array($decoded)) throw new InvalidArgumentException('ข้อมูล Polygon ไม่ถูกต้อง');

    $points = [];
    $distinct = [];
    foreach ($decoded as $point) {
        if (!is_array($point) || !isset($point['lat'], $point['lng']) || !is_numeric($point['lat']) || !is_numeric($point['lng'])) {
            throw new InvalidArgumentException('แต่ละจุดของ Polygon ต้องมี latitude และ longitude');
        }
        $lat = (float) $point['lat'];
        $lng = (float) $point['lng'];
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) throw new InvalidArgumentException('พิกัด Polygon อยู่นอกช่วงที่กำหนด');
        $points[] = ['lat'=>$lat, 'lng'=>$lng];
        $distinct[sprintf('%.7F,%.7F', $lat, $lng)] = true;
    }
    if (count($points) < 3 || count($distinct) < 3) throw new InvalidArgumentException('พื้นที่ต้องมีอย่างน้อย 3 จุดที่ไม่ซ้ำกัน');
    return $points;
}

function geofenceEncodePolygon(array $points): string
{
    return json_encode($points, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
}

function geofencePointOnSegment(float $x, float $y, float $x1, float $y1, float $x2, float $y2): bool
{
    $epsilon = 1.0e-10;
    $cross = ($x - $x1) * ($y2 - $y1) - ($y - $y1) * ($x2 - $x1);
    if (abs($cross) > $epsilon) return false;
    return $x >= min($x1, $x2) - $epsilon && $x <= max($x1, $x2) + $epsilon
        && $y >= min($y1, $y2) - $epsilon && $y <= max($y1, $y2) + $epsilon;
}

function geofencePointInPolygon(float $latitude, float $longitude, array $polygon): bool
{
    $count = count($polygon);
    if ($count < 3) return false;
    $x = $longitude;
    $y = $latitude;
    $inside = false;

    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $xi = (float) $polygon[$i]['lng'];
        $yi = (float) $polygon[$i]['lat'];
        $xj = (float) $polygon[$j]['lng'];
        $yj = (float) $polygon[$j]['lat'];
        if (geofencePointOnSegment($x, $y, $xi, $yi, $xj, $yj)) return true;
        $crosses = (($yi > $y) !== ($yj > $y))
            && ($x < (($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi));
        if ($crosses) $inside = !$inside;
    }
    return $inside;
}

function geofencePolygonArea(array $polygon): float
{
    $area = 0.0;
    $count = count($polygon);
    for ($i = 0, $j = $count - 1; $i < $count; $j = $i++) {
        $area += (float) $polygon[$j]['lng'] * (float) $polygon[$i]['lat'];
        $area -= (float) $polygon[$i]['lng'] * (float) $polygon[$j]['lat'];
    }
    return abs($area / 2.0);
}

function geofenceRequiresAction(array $settings, string $action): bool
{
    if ((int) ($settings['enforce_geofence'] ?? 0) !== 1) return false;
    return $action === 'check_out'
        ? (int) ($settings['require_check_out'] ?? 1) === 1
        : (int) ($settings['require_check_in'] ?? 1) === 1;
}

function geofenceForEmployee(mysqli $connection, int $employeeId, bool $onlyActive = true): array
{
    $employeeStmt = mysqli_prepare($connection, 'SELECT Depart_id FROM employee WHERE Employee_id=? LIMIT 1');
    mysqli_stmt_bind_param($employeeStmt, 'i', $employeeId);
    mysqli_stmt_execute($employeeStmt);
    $employee = mysqli_fetch_assoc(mysqli_stmt_get_result($employeeStmt));
    mysqli_stmt_close($employeeStmt);
    if (!$employee) return [];
    $departmentId = (int) $employee['Depart_id'];
    $activeSql = $onlyActive ? 'AND is_active=1' : '';
    $stmt = mysqli_prepare($connection, "SELECT * FROM attendance_geofences
                                         WHERE (scope_type='all' OR (scope_type='department' AND department_id=?)) $activeSql
                                         ORDER BY priority DESC, id ASC");
    mysqli_stmt_bind_param($stmt, 'i', $departmentId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function geofenceValidateLocationInput(array $input, float $maxAccuracy): array
{
    foreach (['latitude','longitude','accuracy'] as $key) {
        if (!isset($input[$key]) || $input[$key] === '' || !is_numeric($input[$key])) throw new RuntimeException('ไม่สามารถตรวจสอบตำแหน่งได้ · กรุณาอนุญาตการเข้าถึงตำแหน่งแล้วลองใหม่');
    }
    $latitude = (float) $input['latitude'];
    $longitude = (float) $input['longitude'];
    $accuracy = (float) $input['accuracy'];
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180 || $accuracy <= 0) {
        throw new RuntimeException('ข้อมูลตำแหน่งไม่ถูกต้อง กรุณาตรวจสอบ GPS แล้วลองใหม่');
    }
    if ($accuracy > $maxAccuracy) {
        throw new RuntimeException('ไม่สามารถยืนยันตำแหน่งได้ · ความแม่นยำ GPS ต่ำเกินไป ±' . number_format($accuracy, 0) . ' เมตร (กำหนดไม่เกิน ' . number_format($maxAccuracy, 0) . ' เมตร)');
    }
    return ['latitude'=>$latitude, 'longitude'=>$longitude, 'accuracy'=>$accuracy];
}

function geofenceValidateAttendanceLocation(mysqli $connection, int $employeeId, string $action, array $input): array
{
    $settings = geofenceGetSettings($connection);
    if (!geofenceRequiresAction($settings, $action)) {
        return ['required'=>false, 'latitude'=>null, 'longitude'=>null, 'accuracy'=>null, 'geofence'=>null];
    }
    $geofences = geofenceForEmployee($connection, $employeeId, true);
    if (!$geofences) throw new RuntimeException('ไม่สามารถเช็คชื่อได้ · ยังไม่มีพื้นที่เช็คชื่อที่เปิดใช้งาน กรุณาติดต่อผู้ดูแลระบบ');
    $location = geofenceValidateLocationInput($input, max(1.0, (float) $settings['max_accuracy_meters']));
    $matches = [];
    foreach ($geofences as $geofence) {
        try {
            $polygon = geofenceDecodePolygon((string) $geofence['polygon_json']);
        } catch (Throwable) {
            continue;
        }
        if (geofencePointInPolygon($location['latitude'], $location['longitude'], $polygon)) {
            $geofence['_area'] = geofencePolygonArea($polygon);
            $matches[] = $geofence;
        }
    }
    if (!$matches) throw new RuntimeException('ไม่สามารถเช็คชื่อได้ · คุณอยู่นอกพื้นที่ที่อนุญาต');
    usort($matches, static fn(array $a, array $b): int => ($a['_area'] <=> $b['_area']) ?: ((int)$a['id'] <=> (int)$b['id']));
    $location['required'] = true;
    $location['geofence'] = $matches[0];
    return $location;
}
