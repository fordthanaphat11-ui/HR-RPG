<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli-server') {
    $requestedFile = __DIR__ . (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '');
    if (is_file($requestedFile)) {
        return false;
    }
}

session_start();
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/toast.php';

$title = "ระบบบริหารเงินเดือนและบุคลากร";
$isHtmxRequest = isset($_SERVER['HTTP_HX_REQUEST']) && $_SERVER['HTTP_HX_REQUEST'] === 'true';

$routes = [
    '/'                     => __DIR__ . '/pages/home.php',
    '/login'                => __DIR__ . '/pages/login.php',
    '/logout'               => __DIR__ . '/pages/logout.php',
    '/department'           => __DIR__ . '/pages/department.php',
    '/department/add'       => __DIR__ . '/pages/department_add.php',
    '/department/delete'    => __DIR__ . '/pages/department_delete.php',
    '/employee'             => __DIR__ . '/pages/employee.php',
    '/employee/add'         => __DIR__ . '/pages/employee_add.php',
    '/employee/update'      => __DIR__ . '/pages/employee_update.php',
    '/employee/delete'      => __DIR__ . '/pages/employee_delete.php',
    '/employee/setsalary'   => __DIR__ . '/pages/employee_setsalary.php',
    '/employee/payment'     => __DIR__ . '/pages/employee_payment.php',
    '/employee/payslip'     => __DIR__ . '/pages/employee_payslip.php',
    '/employee/payhistory'  => __DIR__ . '/pages/employee_payhistory.php',
    '/employee/accounts'    => __DIR__ . '/pages/employee_accounts.php',
    '/attendance'           => __DIR__ . '/pages/attendance.php',
    '/attendance/calendar'  => __DIR__ . '/pages/attendance_calendar.php',
    '/attendance/history'   => __DIR__ . '/pages/attendance_history.php',
    '/settings/payroll'     => __DIR__ . '/pages/payroll_settings.php',
    '/settings/attendance'  => __DIR__ . '/pages/attendance_settings.php',
    '/settings/attendance/location' => __DIR__ . '/pages/attendance_location_settings.php',
    '/admin/accounts'       => __DIR__ . '/pages/admin_accounts.php',
    '/admin/accounts/delete'=> __DIR__ . '/pages/admin_accounts_delete.php',
    '/admin/password'       => __DIR__ . '/pages/admin_password.php',
    '/me'                   => __DIR__ . '/pages/me.php',
    '/me/attendance'        => __DIR__ . '/pages/me_attendance.php',
    '/me/payhistory'        => __DIR__ . '/pages/me_payhistory.php',
    '/me/password'          => __DIR__ . '/pages/me_password.php',
];

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = rtrim($path, '/');

if ($path === '') {
    $path = '/';
}

$publicRoutes = ['/login', '/logout'];
$employeeRoutes = ['/me', '/me/attendance', '/me/payhistory', '/me/password', '/logout'];

if (!in_array($path, $publicRoutes, true) && !authIsLoggedIn()) {
    authRedirect('/login');
}

if (authIsEmployee()) {
    if (!in_array($path, $employeeRoutes, true)) authRedirect('/me');
    if (authMustChangePassword() && !in_array($path, ['/me/password', '/logout'], true)) {
        authRedirect('/me/password');
    }
}

if (authIsAdmin() && str_starts_with($path, '/me')) {
    authRedirect('/');
}

foreach ($routes as $route => $file) {

    if ($path === $route) {

        if (!is_file($file)) {
            http_response_code(500);
            exit('ไม่พบไฟล์สำหรับหน้าที่ร้องขอ');
        }

        ob_start();
        require $file;
        $content = ob_get_clean();

        require __DIR__ . '/layouts/app.php';

        exit;
    }
}

http_response_code(404);

ob_start();
require __DIR__ . '/pages/404.php';
$content = ob_get_clean();

require __DIR__ . '/layouts/app.php';
