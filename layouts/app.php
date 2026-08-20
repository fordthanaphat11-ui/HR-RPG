<?php
$pageLabel = trim(explode(' - ', $title ?? 'ระบบบริหารเงินเดือน')[0]);
$isEmployeePortal = authIsEmployee();
$currentUserName = authDisplayName();
$currentUserRole = $isEmployeePortal ? 'พนักงาน' : 'ผู้ดูแลระบบ';
$appToasts = appConsumeToasts();
$navigation = $isEmployeePortal ? [
    ['section' => 'บัญชีของฉัน', 'href' => '/me', 'label' => 'ภาพรวมของฉัน', 'icon' => 'fa-house-user', 'active' => $path === '/me'],
    ['section' => 'บัญชีของฉัน', 'href' => '/me/attendance', 'label' => 'เช็คชื่อของฉัน', 'icon' => 'fa-fingerprint', 'active' => $path === '/me/attendance'],
    ['section' => 'บัญชีของฉัน', 'href' => '/me/payhistory', 'label' => 'ประวัติเงินเดือน', 'icon' => 'fa-clock-rotate-left', 'active' => $path === '/me/payhistory'],
    ['section' => 'บัญชีของฉัน', 'href' => '/me/password', 'label' => 'เปลี่ยนรหัสผ่าน', 'icon' => 'fa-key', 'active' => $path === '/me/password'],
] : [
    ['section' => 'ภาพรวม', 'href' => '/', 'label' => 'หน้าหลัก', 'icon' => 'fa-house', 'active' => $path === '/'],
    ['section' => 'บุคลากร', 'href' => '/department', 'label' => 'แผนก', 'icon' => 'fa-building', 'active' => strpos($path, '/department') === 0],
    ['section' => 'บุคลากร', 'href' => '/employee', 'label' => 'รายชื่อพนักงาน', 'icon' => 'fa-users', 'active' => $path === '/employee' || in_array($path, ['/employee/add', '/employee/update'], true)],
    ['section' => 'บุคลากร', 'href' => '/employee/accounts', 'label' => 'บัญชีพนักงาน', 'icon' => 'fa-user-lock', 'active' => $path === '/employee/accounts'],
    ['section' => 'บุคลากร', 'href' => '/attendance', 'label' => 'เช็คชื่อเข้างาน', 'icon' => 'fa-fingerprint', 'active' => $path === '/attendance'],
    ['section' => 'บุคลากร', 'href' => '/attendance/calendar', 'label' => 'ปฏิทินการเข้างาน', 'icon' => 'fa-calendar-days', 'active' => $path === '/attendance/calendar'],
    ['section' => 'บุคลากร', 'href' => '/attendance/history', 'label' => 'ประวัติการเข้างาน', 'icon' => 'fa-calendar-check', 'active' => $path === '/attendance/history'],
    ['section' => 'บุคลากร', 'href' => '/employee/setsalary', 'label' => 'กำหนดเงินเดือน', 'icon' => 'fa-coins', 'active' => $path === '/employee/setsalary'],
    ['section' => 'เงินเดือน', 'href' => '/employee/payment', 'label' => 'จ่ายเงินเดือน', 'icon' => 'fa-calculator', 'active' => $path === '/employee/payment'],
    ['section' => 'เงินเดือน', 'href' => '/employee/payslip', 'label' => 'สลิปเงินเดือน', 'icon' => 'fa-receipt', 'active' => $path === '/employee/payslip'],
    ['section' => 'เงินเดือน', 'href' => '/employee/payhistory', 'label' => 'ประวัติการจ่ายเงิน', 'icon' => 'fa-clock-rotate-left', 'active' => $path === '/employee/payhistory'],
    ['section' => 'การตั้งค่า', 'href' => '/settings/payroll', 'label' => 'การคำนวณเงินเดือน', 'icon' => 'fa-sliders', 'active' => $path === '/settings/payroll'],
    ['section' => 'การตั้งค่า', 'href' => '/settings/attendance', 'label' => 'เวลาเข้า–ออกงาน', 'icon' => 'fa-clock', 'active' => $path === '/settings/attendance'],
    ['section' => 'การตั้งค่า', 'href' => '/settings/attendance/location', 'label' => 'พื้นที่เช็คชื่อ', 'icon' => 'fa-map-location-dot', 'active' => $path === '/settings/attendance/location'],
    ['section' => 'ผู้ดูแลระบบ', 'href' => '/admin/accounts', 'label' => 'จัดการสิทธิ์', 'icon' => 'fa-shield-halved', 'active' => str_starts_with($path, '/admin/accounts')],
    ['section' => 'ผู้ดูแลระบบ', 'href' => '/admin/password', 'label' => 'เปลี่ยนรหัสผ่าน', 'icon' => 'fa-key', 'active' => $path === '/admin/password'],
];
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($title ?? 'ระบบบริหารเงินเดือนและบุคลากร') ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chartist@1.5.0/dist/index.umd.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/chartist@1.5.0/dist/index.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/htmx.org@2.0.10/dist/htmx.min.js" integrity="sha384-H5SrcfygHmAuTDZphMHqBJLc3FhssKjG7w/CeCpFReSfwBWDTKpkzPP8c+cLsK+V" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.3.1/css/all.min.css" integrity="sha512-QeR2VH+lsBE5LSAe1Q5EnTBbe7XTBubt8dG93Y7gidSgdMCr8nVqKcfKAMyN96SV8KDbZVTDXChatu5G2KQGzg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script src="/lib/browser@4.js"></script>
    <style>
        @font-face {
            font-family: 'LINESeedSansTH';
            src: url('/lib/fonts/LINESeedSansTH_W_Th.woff2') format('woff2');
            font-weight: 100;
        }
        @font-face {
            font-family: 'LINESeedSansTH';
            src: url('/lib/fonts/LINESeedSansTH_W_Rg.woff2') format('woff2');
            font-weight: 400;
        }
        @font-face {
            font-family: 'LINESeedSansTH';
            src: url('/lib/fonts/LINESeedSansTH_W_Bd.woff2') format('woff2');
            font-weight: 700;
        }
        @font-face {
            font-family: 'LINESeedSansTH';
            src: url('/lib/fonts/LINESeedSansTH_W_XBd.woff2') format('woff2');
            font-weight: 800;
        }
        @font-face {
            font-family: 'LINESeedSansTH';
            src: url('/lib/fonts/LINESeedSansTH_W_He.woff2') format('woff2');
            font-weight: 900;
        }
        body {
            font-family: 'LINESeedSansTH', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif !important;
        }
        main form input:not([type="radio"]):not([type="checkbox"]):not([type="hidden"]),
        main form select,
        main form textarea {
            max-width: 100%;
            min-height: 44px;
            padding: 8px 12px;
            border-radius: 7px;
            font-family: inherit;
        }
        main form button[type="submit"] { min-height: 44px; }
        @media (max-width: 639px) {
            main form button[type="submit"] { width: 100%; }
        }

        /* DataTables layout inspired by the reference, using the existing paper theme. */
        .dataTables_wrapper {
            padding: 20px;
            color: #31302e;
        }
        .dataTables_wrapper .dt-top,
        .dataTables_wrapper .dt-bottom {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .dataTables_wrapper .dt-top { margin-bottom: 14px; }
        .dataTables_wrapper .dt-bottom {
            padding-top: 14px;
            border-top: 1px solid #e6e6e6;
        }
        .dt-meta {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 14px;
        }
        .dt-toolbar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
        }
        .dt-tool-button {
            min-height: 34px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 6px 11px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #ffffff;
            color: #31302e;
            font-family: inherit;
            font-size: 14px;
            line-height: 1;
            cursor: pointer;
            transition: background-color .15s, border-color .15s, color .15s;
        }
        .dt-tool-button:hover:not(:disabled),
        .dt-tool-button[aria-expanded="true"] {
            background: #f6f5f4;
            border-color: #d5d3d0;
            color: #000000;
        }
        .dt-tool-button:focus-visible,
        .dataTables_filter input:focus-visible,
        .dt-menu button:focus-visible {
            outline: 2px solid rgba(0, 117, 222, .22);
            outline-offset: 1px;
            border-color: #0075de;
        }
        .dt-tool-button:disabled {
            color: #a39e98;
            background: #fafafa;
            cursor: not-allowed;
        }
        .dt-tool-button--primary {
            color: #ffffff;
            background: #0075de;
            border-color: #0075de;
        }
        .dt-tool-button--primary:hover:not(:disabled) {
            color: #ffffff;
            background: #005bab;
            border-color: #005bab;
        }
        .dt-dropdown { position: relative; }
        .dt-menu {
            position: absolute;
            z-index: 30;
            top: calc(100% + 6px);
            left: 0;
            min-width: 178px;
            padding: 6px;
            border: 1px solid #e6e6e6;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(0, 0, 0, .10);
        }
        .dt-menu[hidden] { display: none; }
        .dt-menu button {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 8px 10px;
            border: 0;
            border-radius: 5px;
            background: transparent;
            color: #31302e;
            font-family: inherit;
            font-size: 14px;
            text-align: left;
            cursor: pointer;
        }
        .dt-menu button:hover { background: #f6f5f4; }
        .dt-search { margin-left: auto; }
        .dataTables_wrapper .dataTables_filter {
            float: none;
            margin: 0;
            color: #615d59;
            font-size: 14px;
            white-space: nowrap;
        }
        .dataTables_wrapper .dataTables_length {
            float: none;
            color: #615d59;
            font-size: 14px;
        }
        .dataTables_wrapper .dataTables_length select {
            min-width: 62px;
            height: 34px;
            margin: 0 5px;
            padding: 4px 24px 4px 8px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #ffffff;
            color: #31302e;
            font-family: inherit;
        }
        .dataTables_wrapper .dataTables_filter input {
            width: min(230px, 36vw);
            height: 34px;
            margin-left: 8px;
            padding: 5px 10px;
            border: 1px solid #e6e6e6;
            border-radius: 6px;
            background: #ffffff;
            color: #000000;
            font-family: inherit;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .dt-table-shell { overflow-x: auto; }
        table.dataTable {
            width: 100% !important;
            margin: 0 !important;
            border-collapse: collapse !important;
        }
        table.dataTable thead th,
        table.dataTable tfoot th {
            position: relative;
            padding: 12px 32px 12px 14px !important;
            border-top: 0 !important;
            border-bottom: 1px solid #d7d5d2 !important;
            background: #f6f5f4;
            color: #31302e;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
        }
        table.dataTable tfoot th {
            padding-top: 11px !important;
            padding-bottom: 11px !important;
            border-top: 1px solid #d7d5d2 !important;
            border-bottom: 0 !important;
            background: #ffffff;
        }
        table.dataTable tbody td {
            padding: 11px 14px !important;
            border-bottom: 1px solid #e6e6e6 !important;
            background: #ffffff;
            vertical-align: middle;
        }
        table.dataTable tbody tr:hover td { background: #faf9f8; }
        table.dataTable tbody tr.dt-row-selected td { background: #eef6fd; }
        table.dataTable thead th.sorting::after,
        table.dataTable thead th.sorting_asc::after,
        table.dataTable thead th.sorting_desc::after {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #a39e98;
            font-family: "Font Awesome 7 Free";
            font-size: 11px;
            font-weight: 900;
            content: "\f0dc";
        }
        table.dataTable thead th.sorting_asc::after { color: #0075de; content: "\f0de"; }
        table.dataTable thead th.sorting_desc::after { color: #0075de; content: "\f0dd"; }
        .dt-select-cell,
        .dt-select-heading {
            width: 38px !important;
            min-width: 38px !important;
            padding-left: 12px !important;
            padding-right: 8px !important;
            text-align: center !important;
        }
        .dt-select-heading::after { display: none !important; }
        .dt-row-check,
        .dt-check-all {
            width: 15px;
            height: 15px;
            accent-color: #0075de;
            cursor: pointer;
        }
        .dataTables_wrapper .dataTables_info {
            float: none;
            padding: 0;
            color: #615d59;
            font-size: 14px;
        }
        .dataTables_wrapper .dataTables_paginate {
            float: none;
            display: flex;
            align-items: center;
            gap: 2px;
            padding: 0;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            min-width: 34px;
            height: 34px;
            margin: 0;
            padding: 6px 10px !important;
            border: 1px solid transparent !important;
            border-radius: 6px;
            background: transparent !important;
            color: #31302e !important;
            font-size: 14px;
            cursor: pointer;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #e6e6e6 !important;
            background: #f6f5f4 !important;
            color: #000000 !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current,
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            border-color: #d7d5d2 !important;
            background: #ffffff !important;
            color: #000000 !important;
            font-weight: 700;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            border-color: transparent !important;
            background: transparent !important;
            color: #c8c5c1 !important;
            cursor: default;
        }
        table.dataTable.no-footer { border-bottom: 0 !important; }
        @media (max-width: 720px) {
            .dataTables_wrapper { padding: 14px; }
            .dataTables_wrapper .dt-top,
            .dataTables_wrapper .dt-bottom { align-items: stretch; flex-direction: column; }
            .dt-meta { justify-content: space-between; }
            .dt-search { width: 100%; margin-left: 0; }
            .dataTables_wrapper .dataTables_filter { display: flex; align-items: center; }
            .dataTables_wrapper .dataTables_filter label { width: 100%; }
            .dataTables_wrapper .dataTables_filter input { width: calc(100% - 54px); }
            .dataTables_wrapper .dataTables_paginate { justify-content: center; }
        }
        #globalProgress {
            position: fixed;
            z-index: 90;
            inset: 0 auto auto 0;
            width: 0;
            height: 3px;
            opacity: 0;
            background: #0075de;
            box-shadow: 0 0 12px rgba(0, 117, 222, .35);
            transition: width .2s ease, opacity .15s ease;
        }
        #globalProgress.is-loading { width: 72%; opacity: 1; }
        #globalProgress.is-finishing { width: 100%; opacity: 0; }
        #app-content { transition: opacity .16s ease, transform .16s ease; }
        #app-content.is-loading { opacity: .58; }
        #app-content.htmx-added { animation: app-content-in .18s ease-out; }
        @keyframes app-content-in {
            from { opacity: .25; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .leaflet-container {
            position: relative;
            z-index: 0;
            font-family: inherit;
        }
    </style>
    <link rel="stylesheet" href="/lib/admin.css">
</head>

<body class="bg-[#f6f5f4] text-[#000000] tracking-tight antialiased overflow-x-hidden">
    <?php if (isset($_SESSION['username']) && $path !== '/login'): ?>
        <div id="app-shell"
             class="flex min-h-screen max-w-full"
             hx-boost="true"
             hx-target="#app-content"
             hx-select="#app-content"
             hx-swap="outerHTML transition:true"
             hx-push-url="true"
             hx-sync="this:replace">
            <aside class="hidden lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-56 lg:shrink-0 lg:self-start overflow-hidden bg-[#ebebeb] text-[#303030] flex-col border-r border-[#dedede]">
                <div class="h-12 px-3 flex items-center gap-2.5 border-b border-[#dedede]">
                    <div class="w-7 h-7 rounded-md bg-[#202223] text-white flex items-center justify-center text-xs"><i class="fa-solid fa-wallet" aria-hidden="true"></i></div>
                    <div class="min-w-0"><h1 class="text-sm font-semibold truncate">ระบบบริหารเงินเดือน</h1><p class="text-[11px] text-[#6d7175] truncate">HR & Payroll</p></div>
                </div>
                <nav class="flex-1 px-2 py-2 overflow-y-auto" aria-label="เมนูหลัก">
                    <?php $currentSection = null; foreach ($navigation as $item): ?>
                        <?php if ($item['section'] !== $currentSection): $currentSection = $item['section']; ?><p class="px-2 pt-3 pb-1 text-[11px] font-medium text-[#6d7175]"><?= htmlspecialchars($currentSection) ?></p><?php endif; ?>
                        <a href="<?= $item['href'] ?>" data-app-nav class="mb-0.5 flex items-center gap-2 min-h-9 px-2.5 py-1.5 rounded-md text-sm <?= $item['active'] ? 'bg-white text-[#202223] font-medium border border-[#dedede] shadow-sm' : 'text-[#303030] hover:bg-[#f5f5f5] transition-colors border border-transparent' ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid <?= $item['icon'] ?> w-4 text-center text-[#615d59]" aria-hidden="true"></i>
                            <span><?= $item['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="p-2 border-t border-[#dedede]">
                    <div class="mb-1 flex items-center gap-2 px-2.5 py-1.5 min-w-0"><div class="w-7 h-7 shrink-0 rounded-md bg-white border border-[#dedede] flex items-center justify-center text-xs font-semibold"><?= htmlspecialchars(mb_strtoupper(mb_substr($currentUserName, 0, 1, 'UTF-8'), 'UTF-8')) ?></div><div class="min-w-0"><p class="text-xs font-medium truncate"><?= htmlspecialchars($currentUserName) ?></p><p class="text-[10px] text-[#6d7175]"><?= $currentUserRole ?></p></div></div>
                    <a href="/logout" hx-boost="false" class="flex items-center gap-2 min-h-9 px-2.5 py-1.5 text-[#303030] hover:bg-[#f5f5f5] text-sm rounded-md transition-colors">
                        <i class="fa-solid fa-arrow-right-from-bracket w-4 text-center" aria-hidden="true"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </aside>

            <div id="mobileSidebarBackdrop" class="fixed inset-0 z-40 bg-black/40 opacity-0 pointer-events-none transition-opacity duration-200 lg:hidden" aria-hidden="true"></div>
            <aside id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 flex w-[min(84vw,280px)] -translate-x-full flex-col bg-[#ebebeb] shadow-xl transition-transform duration-150 lg:hidden" aria-hidden="true">
                <div class="flex h-12 items-center justify-between gap-3 px-3 border-b border-[#dedede]">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold truncate">ระบบบริหารเงินเดือน</p>
                        <p class="text-[11px] text-[#6d7175] truncate"><?= htmlspecialchars($currentUserName) ?></p>
                    </div>
                    <button id="closeMobileSidebar" type="button" class="w-8 h-8 shrink-0 rounded-md hover:bg-black/5 text-[#31302e]" aria-label="ปิดเมนู">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <nav class="flex-1 overflow-y-auto p-2" aria-label="เมนูมือถือ">
                    <?php $mobileSection = null; foreach ($navigation as $item): ?>
                        <?php if ($item['section'] !== $mobileSection): $mobileSection = $item['section']; ?><p class="px-2 pt-3 pb-1 text-[11px] font-medium text-[#6d7175]"><?= htmlspecialchars($mobileSection) ?></p><?php endif; ?>
                        <a href="<?= $item['href'] ?>" data-app-nav class="mb-0.5 flex items-center gap-2 min-h-9 px-2.5 py-1.5 rounded-md text-sm <?= $item['active'] ? 'bg-white text-[#202223] font-medium border border-[#dedede]' : 'text-[#303030] border border-transparent hover:bg-[#f5f5f5]' ?>" <?= $item['active'] ? 'aria-current="page"' : '' ?>>
                            <i class="fa-solid <?= $item['icon'] ?> w-5 text-center text-[#615d59]" aria-hidden="true"></i>
                            <span><?= $item['label'] ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
                <div class="p-3 border-t border-[#e6e6e6]">
                    <a href="/logout" hx-boost="false" class="flex items-center gap-3 min-h-12 px-4 py-2 rounded-[8px] text-[15px] text-[#31302e]">
                        <i class="fa-solid fa-arrow-right-from-bracket w-5 text-center" aria-hidden="true"></i>
                        <span>ออกจากระบบ</span>
                    </a>
                </div>
            </aside>

            <main class="flex-1 min-w-0 min-h-screen flex flex-col bg-[#f3f3f3]">
                <header class="sticky top-0 z-30 h-12 bg-[#202223] text-white border-b border-black/20 px-3 lg:px-4 flex items-center gap-3 min-w-0">
                    <button id="openMobileSidebar" type="button" class="w-8 h-8 shrink-0 rounded-md hover:bg-white/10 lg:hidden" aria-label="เปิดเมนู">
                        <i class="fa-solid fa-bars" aria-hidden="true"></i>
                    </button>
                    <h1 id="mobilePageTitle" class="flex-1 min-w-0 text-sm font-medium truncate"><?= htmlspecialchars($pageLabel) ?></h1>
                    <span class="hidden sm:inline text-xs text-[#c9cccf] truncate max-w-40"><?= htmlspecialchars($currentUserName) ?></span>
                    <div class="w-7 h-7 shrink-0 rounded-md bg-white/10 border border-white/15 text-white flex items-center justify-center text-xs font-semibold" title="<?= htmlspecialchars($currentUserName) ?>">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($currentUserName, 0, 1, 'UTF-8'), 'UTF-8')) ?>
                    </div>
                </header>

                <div id="app-content" data-page-title="<?= htmlspecialchars($title ?? 'ระบบบริหารเงินเดือนและบุคลากร') ?>" tabindex="-1" class="flex-1 min-w-0 w-full p-4 lg:p-5">
                    <?php foreach ($appToasts as $toast): ?><span hidden data-flash-toast data-type="<?= htmlspecialchars((string)($toast['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" data-message="<?= htmlspecialchars((string)($toast['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-duration="<?= (int)($toast['duration'] ?? 0) ?>"></span><?php endforeach; ?>
                    <?= $content ?>
                </div>
            </main>
        </div>
    <?php else: ?>
        <!-- Login/Guest Layout -->
        <div class="min-h-screen flex items-center justify-center bg-[#f6f5f4] p-4">
            <?php foreach ($appToasts as $toast): ?><span hidden data-flash-toast data-type="<?= htmlspecialchars((string)($toast['type'] ?? 'info'), ENT_QUOTES, 'UTF-8') ?>" data-message="<?= htmlspecialchars((string)($toast['message'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" data-duration="<?= (int)($toast['duration'] ?? 0) ?>"></span><?php endforeach; ?>
            <?= $content ?>
        </div>
    <?php endif; ?>

    <div id="deleteConfirmModal" class="fixed inset-0 z-[70] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="deleteModalTitle">
        <div class="absolute inset-0 bg-black/40" data-close-delete-modal></div>
        <div class="relative w-full max-w-md max-h-[85vh] overflow-y-auto rounded-[14px] border border-[#e6e6e6] bg-white p-5 sm:p-6 shadow-2xl">
            <div class="w-11 h-11 rounded-full bg-red-50 text-red-600 flex items-center justify-center mb-4">
                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
            </div>
            <h2 id="deleteModalTitle" class="text-[20px] font-bold">ยืนยันการลบข้อมูล?</h2>
            <p id="deleteModalRecord" class="mt-2 text-[15px] font-medium text-[#31302e]"></p>
            <p class="mt-1 text-[14px] text-[#615d59]">การดำเนินการนี้ไม่สามารถย้อนกลับได้</p>
            <div class="mt-6 grid grid-cols-2 gap-3">
                <button type="button" data-close-delete-modal class="min-h-11 rounded-[8px] border border-[#e6e6e6] bg-white px-4 font-medium hover:bg-[#f6f5f4]">ยกเลิก</button>
                <a id="confirmDeleteLink" href="#" hx-boost="false" class="min-h-11 rounded-[8px] bg-red-600 px-4 text-white font-medium flex items-center justify-center hover:bg-red-700">ลบข้อมูล</a>
            </div>
        </div>
    </div>

    <div id="globalProgress" aria-hidden="true"></div>
    <div id="appConfirmModal" class="fixed inset-0 z-[110] hidden items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="appConfirmTitle" aria-hidden="true"><div class="absolute inset-0 bg-black/40" data-app-confirm-cancel></div><div class="relative w-full max-w-md rounded-xl border border-[#e6e6e6] bg-white p-5 shadow-2xl"><div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-50 text-amber-700"><i class="fa-solid fa-triangle-exclamation"></i></div><h2 id="appConfirmTitle" class="mt-4 text-lg font-bold">ยืนยันการดำเนินการ?</h2><p data-app-confirm-message class="mt-2 text-sm text-[#615d59]"></p><div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"><button type="button" data-app-confirm-cancel class="min-h-10 rounded-md border border-[#d5d3d0] px-4 text-sm font-semibold">ยกเลิก</button><button type="button" data-app-confirm-accept class="min-h-10 rounded-md bg-[#303030] px-4 text-sm font-semibold text-white">ยืนยัน</button></div></div></div>
    <div id="routeStatus" class="sr-only" aria-live="polite"></div>
    <div id="hotToastViewport" class="pointer-events-none fixed right-3 top-3 z-[120] flex w-[min(368px,calc(100vw-24px))] flex-col gap-2 sm:right-4 sm:top-4" aria-live="polite" aria-atomic="false"></div>
    <script src="/lib/jquery-4.0.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="/lib/app.js" defer></script>
    <script src="/lib/geofence.js" defer></script>
</body>

</html>
