Searched for files: *
Viewed README.md:1-27
Viewed DESIGN.md:1-499
Viewed db.php:1-31
Viewed auth.php:1-79
Viewed payroll.php:1-208
Viewed salary.php:1-96
Viewed attendance.php:1-276
Viewed geofence.php:1-164
Searched for files: *.sql
Viewed EE.sql:1-397
Viewed 2026_08_17_employee_salary_history.sql:1-25
Viewed attendance_calendar.php:1-192
Viewed toast.php:1-41
Viewed app.js:1-800
Viewed geofence.js:1-366
Viewed home.php:1-100
Searched for "$_FILES"
Searched for "multipart/form-data"

# บทวิเคราะห์และบทนำเสนอโครงงานฉบับสมบูรณ์ (Technical & Presentation Script)
**ระบบบริหารจัดการทรัพยากรบุคคลและคำนวณเงินเดือน (HR & Payroll Management System / HR-RPG)**

---

## STEP 1 & 2 — สรุปภาพรวมโครงงาน (Project Overview)

### 2.1 ชื่อและประเภทระบบ
*   **ชื่อโครงการ:** ระบบบริหารจัดการทรัพยากรบุคคล การลงเวลา และการประมวลผลเงินเดือน (HR & Payroll Management System / รหัสโครงการ: `HR-RPG`)
*   **ประเภทระบบ:** Monolithic Web Application (สถาปัตยกรรมรวมศูนย์ ทำงานแบบ Client-Server ผ่าน Centralized Routing) พัฒนาด้วยภาษา PHP และจัดการฐานข้อมูลด้วย MySQL/MariaDB

### 2.2 ปัญหาและที่มาของระบบ (Problem Statement)
โครงงานนี้พัฒนาขึ้นเพื่อแก้ปัญหาการทำงานในองค์กรจริง 6 ประการหลัก:
1.  **ความผิดพลาดในการคำนวณเงินเดือนด้วยมือ (Manual Calculation Errors):** การคำนวณฐานเงินเดือน, ค่าล่วงเวลา (OT), โบนัส, ค่ารักษาพยาบาล, ค่าที่พัก ร่วมกับรายการหัก เช่น หักเงินกู้ยืม, กองทุนสำรองเลี้ยงชีพ, การขาดงาน และการมาสาย มักเกิดความล่าช้าและผิดพลาด
2.  **การลงเวลาเข้างานไม่ตรงความจริง (Time Theft / Proxy Attendance):** การตอกบัตรหรือลงชื่อแบบเดิมไม่สามารถพิสูจน์ตำแหน่งจริงของผู้ปฏิบัติงานได้
3.  **ความไม่เชื่อมโยงระหว่างระบบลงเวลากับระบบจ่ายเงิน (Data Silos):** ข้อมูลการขาดงานและมาสายในอดีตต้องพิมพ์ซ้ำซ้อนเข้าโปรแกรมเงินเดือน
4.  **การปรับเปลี่ยนอัตราเงินเดือนไม่มีผลย้อนหลัง/ล่วงหน้าอย่างเป็นระบบ:** ขาดระบบประวัติการปรับฐานเงินเดือนตามวันที่มีผลบังคับใช้ (Effective Date)
5.  **พนักงานเข้าถึงสลิปเงินเดือนได้ยาก:** พนักงานต้องรอเอกสารกระดาษ ไม่สามารถตรวจสอบประวัติการรับเงินและเวลาทำงานของตนเองแบบ Self-Service ได้
6.  **ความปลอดภัยของข้อมูลต่ำ:** ข้อมูลเงินเดือนและรหัสผ่านพนักงานเดิมไม่ได้ถูกเข้ารหัสความปลอดภัยตามมาตรฐาน

---

## STEP 3 — การวิเคราะห์ Technology Stack

### 3.1 ฝั่ง Frontend (ส่วนติดต่อผู้ใช้)
*   **HTML5 & JavaScript (ES6+):** ใช้เขียนโครงสร้างและพฤติกรรมการทำงานของหน้าเว็บแบบ Vanilla JS โดยไม่มี Node/React/Vue Framework
*   **Tailwind CSS (v4 Standalone):** โหลดผ่าน `/lib/browser@4.js` สำหรับจัดการ Design System, Grid, Flexbox, Responsive Breakpoints และคุม Theme สไตล์ Notion
*   **jQuery 4.0.0 (Local Library):** เก็บไฟล์จริงอยู่ที่ `/lib/jquery-4.0.0.min.js` ใช้จัดการ Event Delegation, DOM Manipulation, และรองรับ DataTables
*   **jQuery DataTables (v1.13.6):** ใช้จัดการตารางข้อมูลในหน้าแผนก, พนักงาน, บัญชีผู้ใช้, และประวัติการจ่ายเงินเดือน (รองรับ Search, Multi-column Sort, Pagination, Row Selection)
*   **HTMX (v2.0.10):** โหลดผ่าน CDN สำหรับทำ AJAX Navigation (`hx-boost="true"`), Partial Swap (`#app-content`), และรองรับ Server-Triggered Toast ผ่าน Header `HX-Trigger`
*   **Chartist (v1.5.0):** ใช้เรนเดอร์กราฟแท่งแนวโน้มการจ่ายเงินเดือน 6 เดือนย้อนหลังและสถิติจำนวนพนักงานรายแผนกในหน้า Dashboard (`pages/home.php`)
*   **Leaflet & Leaflet-Draw (v1.9.4 / v1.0.4):** โหลดแบบ Dynamic เมื่อเข้าใช้งานหน้าจัดการพื้นที่ (`lib/geofence.js`) สำหรับแสดงแผนที่ OpenStreetMap และวาด Polygon พื้นที่ Geofence
*   **Font Awesome 7:** สำหรับไอคอนระบบ

### 3.2 ฝั่ง Backend (ส่วนประมวลผลเซิร์ฟเวอร์)
*   **PHP 8.3 (Strict Types `declare(strict_types=1);`):** ใช้ PHP เป็นตัวประมวลผลหลักโดยไม่มี Framework ภายนอก (Pure PHP Architecture)
*   **Centralized Router (`index.php`):** ทำหน้าที่เป็น Single Entry Point รับทุก HTTP Request ทำการตรวจสอบสิทธิ์ (Auth Middleware) แล้วโหลด Controller/View จากโฟลเดอร์ `pages/`
*   **Business Logic Libraries (`lib/`):**
    *   `lib/payroll.php`: ระบบคำนวณเงินเดือน รายรับ รายหัก ขาด-สาย
    *   `lib/salary.php`: ระบบจัดการประวัติฐานเงินเดือนและ Effective Dates
    *   `lib/attendance.php`: ระบบประมวลผลการเช็คชื่อ คำนวณนาทีมาสาย และสรุปงวดการทำงาน
    *   `lib/geofence.php`: อัลกอริทึม Ray-Casting Point-in-Polygon และตรวจสอบความถูกต้องของพิกัด GPS
    *   `lib/auth.php`: ระบบ Session, RBAC, Password Policy, CSRF Protection
    *   `lib/toast.php`: ระบบ Flash Toasts และ HTMX Toast Triggers
    *   `lib/env.php`: Custom `.env` Parser สำหรับโหลดค่าคอนฟิก

### 3.3 ฝั่ง Database (ระบบจัดการฐานข้อมูล)
*   **Database Engine:** MySQL 8.x / MariaDB 10.x/12.x (Charset: `utf8mb4`, Collation: `utf8mb4_unicode_ci`)
*   **Database Driver / Access:** ใช้ **PHP `mysqli` Extension** พร้อมเปิดโหมด `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` ร่วมกับ **Prepared Statements (`mysqli_prepare`, `mysqli_stmt_bind_param`)** ในทุก Transaction ไม่ใช้ ORM เพื่อประสิทธิภาพสูงสุดและความเบาของเซิร์ฟเวอร์

---

## STEP 4 — การวิเคราะห์ฐานข้อมูล (Database Design & Schema)

### 4.1 ตารางทั้งหมดในระบบ (15 Tables จาก Schema จริง)

| ชื่อ Table | หน้าที่การทำงาน | Primary Key | Foreign Key | เชื่อมโยงกับตาราง |
|---|---|---|---|---|
| `admin` | เก็บข้อมูลผู้ดูแลระบบและรหัสผ่าน | `id` | - | - |
| `department` | เก็บรายชื่อแผนกในองค์กร | `Depart_id` | - | - |
| `job` | เก็บรายชื่อตำแหน่งงานและฐานเงินเดือนเริ่มต้น | `Job_Title` | - | - |
| `employee` | เก็บข้อมูลส่วนตัว ประวัติ และข้อมูลการเงินพนักงาน | `Employee_id` | `Depart_id`<br>`managesDepart_id`<br>`jobtitle` | `department(Depart_id)`<br>`department(Depart_id)`<br>`job(Job_Title)` |
| `employee_accounts` | เก็บบัญชีผู้ใช้งานสำหรับพนักงาน (Portal Login) | `id` | `employee_id` | `employee(Employee_id)` |
| `employee_salaries` | ประวัติการปรับเปลี่ยนฐานเงินเดือนและวันที่มีผลบังคับใช้ | `id` | `employee_id` | `employee(Employee_id)` |
| `attendance` | ข้อมูลบันทึกเวลาเข้า-ออกงาน พิกัด GPS และสถานะ | `id` | `employee_id` | `employee(Employee_id)` |
| `attendance_geofences` | ข้อมูลพิกัด Polygon และเงื่อนไขพื้นที่เช็คชื่อ | `id` | `department_id` | `department(Depart_id)` |
| `attendance_settings` | คอนฟิกเวลาเข้างาน เลิกงาน ช่วงเวลาผ่อนผัน วันทำงาน | `id` | - | - |
| `attendance_location_settings` | คอนฟิกการบังคับใช้ Geofence และค่าความแม่นยำ GPS | `id` | - | - |
| `attendance_holidays` | เก็บวันหยุดประจำปีขององค์กร | `holiday_date` | - | - |
| `payment` | ข้อมูลสรุปยอดการจ่ายเงินเดือนแต่ละงวด (Legacy/Summary) | `(emp_id, year, month)` | `emp_id` | `employee(Employee_id)` |
| `payment_snapshots` | Snapshot ข้อมูลและเงื่อนไขการคำนวณเงินเดือน ณ วันที่จ่าย | `pay_no` | `emp_id` | `employee(Employee_id)` |
| `payroll_adjustments` | รายการเงินได้และเงินหักย่อยในแต่ละสลิปเงินเดือน | `id` | `pay_no`, `emp_id` | `payment_snapshots(pay_no)` |
| `payroll_settings` | คอนฟิกอัตราหักขาดงานและสูตรการหักเงินกรณีมาสาย | `id` | - | - |

### 4.2 ความสัมพันธ์ของข้อมูล (Entity Relationships)
1.  **Department ↔ Employee (1:N):** แผนก 1 แผนกมีพนักงานได้หลายคน แต่พนักงาน 1 คนสังกัดได้ 1 แผนก (`employee.Depart_id → department.Depart_id`)
2.  **Employee ↔ Department (Manager) (1:1):** พนักงาน 1 คนสามารถได้รับมอบหมายให้เป็นผู้จัดการแผนกได้ 1 แผนก (`employee.managesDepart_id → department.Depart_id`)
3.  **Employee ↔ Employee Accounts (1:1):** พนักงาน 1 คนมีบัญชีเข้าสู่ระบบได้เพียง 1 บัญชี (`employee_accounts.employee_id` เป็น Unique Key)
4.  **Employee ↔ Employee Salaries (1:N):** พนักงาน 1 คนมีประวัติการปรับเงินเดือนได้หลายครั้งตามช่วงเวลา
5.  **Employee ↔ Attendance (1:N):** พนักงาน 1 คนมีการลงเวลาเข้างานได้หลายวัน แต่ใน 1 วันสามารถลงเวลาได้ 1 รายการ (`UNIQUE KEY(employee_id, attendance_date)`)
6.  **Employee ↔ Payment (1:N):** พนักงาน 1 คนมีประวัติการรับเงินเดือนได้หลายงวดเดือน

### 4.3 ลำดับการไหลของข้อมูลในฐานข้อมูล (Database Flow)
$$\text{Employee} \xrightarrow{\text{เช็คชื่อผ่าน GPS}} \text{Attendance} \xrightarrow{\text{สรุปขาด/สาย}} \text{Attendance Period Metrics}$$
$$\downarrow$$
$$\text{Employee Salaries (Effective Base)} + \text{Payroll Settings (Rules)} \xrightarrow{\text{ประมวลผล}} \text{Payment} + \text{Payment Snapshot} + \text{Payroll Adjustments} \xrightarrow{} \text{Payslip}$$

---

## STEP 5 — การวิเคราะห์สถาปัตยกรรมระบบ (Architecture)

ระบบใช้สถาปัตยกรรม **Lightweight MVC Monolithic Architecture with Centralized Routing**:

```
[Web Browser / Mobile Client]
       │  (HTTP / HTTPS Request)
       ▼
[index.php (Router & Auth Guard)]
       ├─► ตรวจสอบ Session & Role (lib/auth.php)
       ├─► Redirect ไป /login หรือ /me/password (หากถูกบังคับเปลี่ยนรหัสผ่าน)
       ▼
[pages/*.php (Controllers & Views)]
       ├─► เรียกใช้ Business Logic (lib/payroll.php, lib/attendance.php, lib/geofence.php)
       ├─► สื่อสารกับ Database ผ่าน Prepared Statements (lib/db.php)
       ▼
[Output Buffering (ob_start / ob_get_clean)]
       ▼
[layouts/app.php (Main Template)] ───► Response HTML/HTMX กลับสู่ Client
```

---

## STEP 6 — ระบบ Authentication และ Security

1.  **การแยกสิทธิ์การใช้งาน (Role-Based Access Control - RBAC):**
    *   **Admin (ผู้ดูแลระบบ):** เข้าถึงหน้าบริหารจัดการทั้งหมด (`/`, `/department`, `/employee`, `/attendance`, `/settings/*`, `/admin/*`)
    *   **Employee (พนักงาน):** ถูกจำกัดสิทธิ์เข้าถึงเฉพาะ Self-Service Portal (`/me`, `/me/attendance`, `/me/payhistory`, `/me/password`) หากพยายามเข้า URL ฝั่ง Admin ระบบจะ Redirect กลับทันที
2.  **การจัดเก็บรหัสผ่าน (Password Hashing):**
    *   รหัสผ่านบัญชีพนักงานและแอดมินใหม่ถูกเข้ารหัสด้วยฟังก์ชันมาตรฐาน **`password_hash($password, PASSWORD_DEFAULT)`** (BCrypt Algorithm)
    *   ตรวจสอบรหัสผ่านด้วย **`password_verify()`** พร้อมรองรับการตรวจสอบรหัสผ่านเดิมด้วย `hash_equals()`
3.  **การจัดการ Session และความปลอดภัย:**
    *   เมื่อ Login สำเร็จจะมีการเรียกใช้ **`session_regenerate_id(true)`** เพื่อป้องกันการโจมตีแบบ **Session Fixation**
4.  **การป้องกัน SQL Injection:**
    *   ใช้ **Prepared Statements (`mysqli_prepare`)** และผูกตัวแปรด้วย **`mysqli_stmt_bind_param()`** 100% ในทุกคำสั่ง SQL ที่รับค่าจากผู้ใช้งาน
5.  **การป้องกัน Cross-Site Scripting (XSS):**
    *   ใช้ **`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`** กับทุกตัวแปรที่นำไปแสดงผลบนหน้า HTML
6.  **การป้องกัน CSRF (Cross-Site Request Forgery):**
    *   มีฟังก์ชัน `authCsrfToken()` และ `authValidateCsrf()` สร้างและตรวจสอบโทเค็นสุ่มความยาว 32 bytes (`random_bytes(32)`)

---

## STEP 7 — สไตล์ UI/UX Design

*   **Design Concept:** ออกแบบภายใต้แนวคิด **Notion Aesthetic (Document-Calm Workspace)** ตามข้อกำหนดใน `DESIGN.md`
*   **Palette สีของระบบ:**
    *   *Canvas/Background:* สีขาวนวล Warm Paper `#f6f5f4` และ `#f3f3f3`
    *   *Surface Cards:* สีขาวบริสุทธิ์ `#ffffff` ตัดด้วยขอบ Hairline บางเบา `#e6e6e6` / `#dedede`
    *   *Primary Action:* Notion Blue `#0075de` (Active `#005bab`) สำหรับปุ่มบันทึกและไฮไลต์สำคัญ
    *   *Text/Ink:* ดำเกือบสนิท `#202223` และสีเทาหิน `#6d7175` สำหรับข้อความรอง
*   **Typography:** ใช้ฟอนต์ **LINE Seed Sans TH** ที่ติดตั้งอยู่ใน Local (`/lib/fonts/*.woff2`) มี 5 น้ำหนัก (Thin, Regular, Bold, ExtraBold, Heavy)
*   **Responsive Design:**
    *   *Desktop (≥1024px):* แสดง Sidebar แบบ Sticky ด้านซ้ายคงที่
    *   *Mobile/Tablet (<1024px):* ซ่อน Sidebar และมี Hamburger Menu แบบ Off-canvas Drawer พร้อม Backdrop ป้องกันการเลื่อนหน้าจอ (`overflow-hidden`)

---

## STEP 8 — วิเคราะห์ Component สำคัญในระบบ

1.  **Sidebar & Mobile Drawer Navigation:** เมนูนำทางแบบจัดหมวดหมู่ (ภาพรวม, บุคลากร, เงินเดือน, การตั้งค่า, ผู้ดูแลระบบ)
2.  **Employee Picker Modal:** กล่องเลือกพนักงานพร้อมช่องค้นหาแบบเรียลไทม์ ตัวกรองแผนก และสถานะการจ่ายเงิน
3.  **Interactive Geofence Map:** กล่องแผนที่ Leaflet สำหรับปักหมุดและลากเส้น Polygon กำหนดขอบเขตพื้นที่ทำงาน
4.  **Attendance Calendar Grid:** ปฏิทินแสดงการเข้างานรายวันของทั้งบริษัท พร้อม Avatar ชื่อย่อ, จุดสถานะ (ตรงเวลา/สาย/ขาด), และ Tooltip บอกพิกัด
5.  **Hot Toast Notification Viewport:** กล่องแจ้งเตือนมุมขวาบนแบบ Animated รองรับทั้ง Success, Error, Warning, Info, และ Loading State
6.  **Delete Confirmation Modal:** กล่อง Modal เตือนก่อนลบข้อมูล ป้องกันการกดลบโดยไม่ได้ตั้งใจ

---

## STEP 9 & 10 — วิเคราะห์ฟีเจอร์และการทำงานเชิงลึก (Features & Flows)

### ฟีเจอร์ฝั่ง Admin (ผู้ดูแลระบบ)
1.  **Dashboard ภาพรวม:** สรุปยอดพนักงาน, ฐานเงินเดือนเฉลี่ย, ยอดเงินเดือนที่จ่ายแล้ว/รอจ่าย, กราฟแนวโน้ม 6 เดือน
2.  **จัดการแผนกและตำแหน่ง:** CRUD ข้อมูลแผนกและเชื่อมโยงหัวหน้าแผนก
3.  **จัดการข้อมูลพนักงาน:** เพิ่ม, แก้ไข, ลบข้อมูลส่วนตัว, ที่อยู่, เบอร์โทร, กองทุนสำรองเลี้ยงชีพ, และยอดเงินกู้
4.  **จัดการบัญชีผู้ใช้งาน (Accounts):** สร้าง Username/Password ให้พนักงาน กำหนดสถานะ Active และตั้งค่าบังคับเปลี่ยนรหัสผ่านในการเข้าใช้งานครั้งแรก
5.  **กำหนดฐานเงินเดือน (Effective Salary Management):** บันทึกเงินเดือนพร้อมระบุวันที่เริ่มมีผล (Effective Date) เพื่อรองรับการปรับเงินเดือนล่วงหน้าหรือบันทึกประวัติย้อนหลัง
6.  **ตั้งค่าระบบลงเวลา (Attendance Settings):** กำหนดเวลาเริ่มงาน, เวลาเลิกงาน, วันทำงานประจำสัปดาห์ (เช่น จันทร์-ศุกร์), และช่วงเวลาผ่อนผัน (Grace Period)
7.  **ตั้งค่าพื้นที่เช็คชื่อ (Geofence Location):** วาดแผนที่ Polygon เพื่อจำกัดพื้นที่ลงเวลา แยกรายแผนกหรือใช้ทั้งองค์กร
8.  **ประมวลผลการจ่ายเงินเดือน (Payroll Processing):** คำนวณเงินเดือนอัตโนมัติ ดึงยอดขาด/สายจากระบบลงเวลา เพิ่ม/ลดรายการพิเศษ และออกสลิปเงินเดือน

### ฟีเจอร์ฝั่ง Employee (พนักงาน)
1.  **Self-Service Check-In / Check-Out:** ตรวจจับพิกัด GPS อัตโนมัติและเช็คชื่อเข้า-ออกงาน
2.  **ตรวจสอบประวัติการลงเวลาและเงินเดือนของตนเอง:** ดูสถิติการมาทำงานและดาวน์โหลดสลิปเงินเดือน
3.  **เปลี่ยนรหัสผ่าน:** อัปเดตรหัสผ่านส่วนตัวอย่างปลอดภัย

### Technical Feature Walkthrough: การเช็คชื่อเข้างานด้วย Geofence
```
1. พนักงานกดปุ่ม "เช็คชื่อเข้างาน" ในหน้า /me/attendance
2. JavaScript (lib/geofence.js) เรียก navigator.geolocation.getCurrentPosition() ดึงค่า Lat, Lng, Accuracy
3. ตรวจสอบค่าความแม่นยำ: หาก Accuracy > max_accuracy_meters (ค่าเริ่มต้น 50m) จะปฏิเสธคำขอ
4. ส่งค่า Lat, Lng, Accuracy ไปยัง Backend ผ่านคำขอ POST
5. Backend (lib/geofence.php) ดึงข้อมูล Polygon ของแผนกที่พนักงานสังกัด
6. ประมวลผลด้วยฟังก์ชัน geofencePointInPolygon() ด้วยอัลกอริทึม Ray-Casting
7. หากพิกัดอยู่ใน Polygon -> คำนวณนาทีที่มาสายเทียบกับ work_start + grace_minutes
8. เริ่ม Database Transaction: บันทึกข้อมูลลงตาราง attendance และ Commit
9. ส่งข้อความแจ้งเตือนผ่าน Toast กลับไปแสดงผลบนหน้าจอ
```

---

## STEP 11 — สูตรการคำนวณเงินเดือน (Payroll Calculation Formula)

*อ้างอิงจาก Source Code ใน `lib/payroll.php` และ `lib/salary.php` จริง:*

### 1. รายรับอัตโนมัติ (Automatic Additions)
*   **ค่ารักษาพยาบาล (Medical Allowance):** $3\% \times \text{ฐานเงินเดือน}$
*   **ค่าที่พัก (Housing Allowance):** $8\% \times \text{ฐานเงินเดือน}$
*   **ค่าล่วงเวลา (Overtime):** $\text{จำนวนชั่วโมง OT} \times 300 \text{ บาท}$
*   **รายรับอื่นๆ (Manual Additions):** ระบุเพิ่มเติมได้ในฟอร์ม

### 2. รายการหักอัตโนมัติ (Automatic Deductions)
*   **หักชำระเงินกู้ (Loan Cut):** $5\% \times \text{ยอดเงินกู้คงเหลือ}$
*   **หักกองทุนสำรองเลี้ยงชีพ (Provident Fund):** $2.5\% \times \text{ฐานเงินเดือน}$
*   **หักขาดงาน (Absence Deduction):**
    *   *โหมด Fixed:* $\text{จำนวนวันขาด} \times \text{อัตราหักต่อวัน}$
    *   *โหมด Daily Salary:* $\text{จำนวนวันขาด} \times \left(\frac{\text{ฐานเงินเดือน}}{\text{จำนวนวันหาร (เช่น 30)}}\right)$
*   **หักมาสาย (Late Deduction):**
    *   *โหมด Per Occurrence:* $\text{จำนวนครั้งที่สาย} \times \text{อัตราหักต่อครั้ง}$
    *   *โหมด Per Minutes:* $\left\lceil\frac{\text{นาทีสาย} - (\text{เวลาผ่อนผัน} \times \text{จำนวนครั้ง})}{\text{รอบนาที (เช่น 30)}}\right\rceil \times \text{อัตราต่อรอบ}$
    *   *โหมด Per Actual Minute:* $\text{นาทีสายจริง} \times \text{อัตราต่อนาที}$
    *(จำกัดยอดหักสูงสุดตามค่า `max_late_deduction` หากมีการกำหนดไว้)*

### 3. เงินได้สุทธิ (Net Salary)
$$\text{Net Salary} = \text{ฐานเงินเดือน} + \sum \text{รายรับทั้งหมด} - \sum \text{รายการหักทั้งหมด}$$

---

## STEP 12 — ระบบการแจ้งเตือน (Notification System)

*   **สถาปัตยกรรม Toast:** พัฒนาขึ้นมาเองใน `lib/app.js` และ `lib/toast.php` ภายใต้ชื่อ **Hot Toast** (ทำงานบน Element `#hotToastViewport`)
*   **การทำงาน 3 ช่องทาง:**
    1.  *Client-side JavaScript:* เรียก `App.toast.success()`, `App.toast.error()`, `App.toast.loading()`
    2.  *Server-side Redirect / Page Load:* ส่ง Flash Toast ผ่าน `$_SESSION['app_toasts']` และถูกอ่านเรนเดอร์ใน `layouts/app.php`
    3.  *HTMX AJAX Response:* เซิร์ฟเวอร์แนบ Header `HX-Trigger: {"showToast":{"type":"success","message":"..."}}` เพื่อแสดงการแจ้งเตือนโดยไม่ต้องโหลดหน้าใหม่

---

## STEP 13 — การทำงานของ DataTables

*   **Library:** jQuery DataTables v1.13.6
*   **หน้าที่ใช้งาน:** `pages/employee.php`, `pages/department.php`, `pages/employee_accounts.php`, `pages/employee_payhistory.php`, `pages/attendance_history.php`
*   **ความสามารถ:** ค้นหาทุกคอลัมน์ (Global Search), เรียงลำดับข้อมูลหลายมิติ (Sorting), เลือกจำนวนแถวที่แสดง (Length Menu: 10, 25, 50, 100), และแบ่งหน้าแบบกำหนดสไตล์ CSS ให้เข้ากับธีม Notion

---

## STEP 14 — การทำงานของ Attendance Calendar

*   **สถาปัตยกรรม:** พัฒนาขึ้นมาเองด้วย Custom HTML/CSS + JavaScript ร่วมกับ Helper `lib/attendance_calendar.php` โดยไม่ใช้ Third-party Calendar Framework ขนาดใหญ่
*   **จุดเด่น:**
    *   แสดงผลตารางปฏิทินรายเดือน แสดงจำนวนผู้มาทำงาน, มาสาย, และขาดงานในแต่ละวัน
    *   แสดง Avatar ชื่อย่อภาษาไทย พร้อมสีกรอบและสีจุดแยกตามตำแหน่งงาน (Manager, Executive, Director, Accountant, Chief)
    *   มี Tooltip บอกรายละเอียดเวลาเข้า-ออกงานและพิกัดเมื่อ Hover
    *   คลิกดูรายละเอียดรายวันผ่าน Modal Pop-up ได้ทันที

---

## STEP 15 — การจัดการรูปภาพและไฟล์ (File / Image Handling)

*   **ผลการตรวจสอบ:** *ไม่พบข้อมูลระบบอัปโหลดไฟล์จาก Project* (ในระบบปัจจุบันยังไม่มีการอัปโหลดไฟล์ภาพ เพื่อลดภาระพื้นที่เก็บข้อมูลและหลีกเลี่ยงช่องโหว่ด้านความปลอดภัยจากการอัปโหลดไฟล์)
*   **แนวทางที่ระบบใช้งานทดแทน:** ใช้ **Dynamic Initial Avatar** โดยฟังก์ชัน `attendanceCalendarInitials()` จะตัดตัวอักษรแรกของชื่อ-นามสกุลมาสร้างเป็นภาพตัวแทน และกำหนดสีพื้นหลังตาม CRC32 Hash ของชื่อตำแหน่งงาน

---

## STEP 16 — ลำดับขั้นตอนการพัฒนาระบบ (Development Workflow)

1.  **วิเคราะห์ความต้องการ (Requirement Analysis):** กำหนดฟังก์ชันหลักของระบบ HR, เงินเดือน, และการลงเวลา
2.  **ออกแบบฐานข้อมูล (Database Schema):** สร้างตารางความสัมพันธ์ใน MySQL พร้อม Index และ Foreign Key
3.  **วางโครงสร้างสถาปัตยกรรม (Routing & Layout):** สร้าง `index.php` Router และ Layout `layouts/app.php` ตาม Notion Design Language
4.  **พัฒนาระบบ Authentication & Security:** ทำระบบ Login, Password Hashing, Session Management และ Role Guard
5.  **พัฒนาโมดูลบริหารงานบุคคล (HR Module):** ทำ CRUD แผนก, พนักงาน, และระบบประวัติฐานเงินเดือน
6.  **พัฒนาระบบลงเวลาและ Geofencing:** บูรณาการ Leaflet Map, อัลกอริทึม Point-in-Polygon, และระบบคำนวณการมาสาย
7.  **พัฒนาระบบประมวลผลเงินเดือน (Payroll Calculation Engine):** เขียนฟังก์ชันคำนวณเงินได้/เงินหัก และการสร้าง Snapshot สลิปเงินเดือน
8.  **พัฒนา Employee Self-Service Portal:** หน้าจอสำหรับพนักงานตรวจสอบข้อมูลและลงเวลา
9.  **ทดสอบระบบและเตรียมการ Deploy:** เขียน Unit Tests ในโฟลเดอร์ `tests/` และสร้าง Dockerfile สำหรับรันบน Production

---

## STEP 17 — Script การนำเสนอโครงงานฉบับเต็ม (10–15 นาที)

*(สำหรับใช้พูดนำเสนอกรรมการ เป็นภาษาพูดที่เป็นธรรมชาติ ชัดเจน และน่าเชื่อถือ)*

### 1. บทนำ (Opening & Introduction) — 1 นาที
"กราบเรียนท่านอาจารย์และคณะกรรมการทุกท่านครับ วันนี้กระผมขออนุญาตนำเสนอโครงงาน **ระบบบริหารจัดการทรัพยากรบุคคล การลงเวลา และการประมวลผลเงินเดือนผ่านเว็บแอปพลิเคชัน** หรือ **HR-RPG System** ครับ

โครงงานนี้ถูกพัฒนาขึ้นมาจากปัญหาจริงในองค์กร ที่มักประสบปัญหาความล่าช้าและความผิดพลาดในการคำนวณเงินเดือนด้วยมือ พนักงานลืมตอกบัตร หรือมีการลงเวลาแทนกัน รวมถึงการที่ระบบบันทึกเวลาทำงานกับระบบจ่ายเงินเดือนทำงานแยกส่วนกัน ทำให้ฝ่ายบุคคลต้องเสียเวลาสรุปข้อมูลเป็นเวลานานครับ ระบบของเราจึงถูกสร้างขึ้นมาเป็น All-in-One Solution เพื่อแก้ไขปัญหาเหล่านี้แบบอัตโนมัติครับ"

### 2. สถาปัตยกรรมและ Technology Stack — 2 นาที
"ในส่วนของสถาปัตยกรรมระบบ เราเลือกใช้ **Lightweight Monolithic MVC Architecture** โดยควบคุมผ่าน Centralized Router ในภาษา **PHP 8.3** ร่วมกับฐานข้อมูล **MySQL 8** ครับ

*   **เหตุผลที่เราเลือกไม่ใช้ Framework ขนาดใหญ่:** เพื่อให้ระบบทำงานได้รวดเร็วที่สุด กินทรัพยากรเซิร์ฟเวอร์ต่ำ และสามารถติดตั้งได้ง่ายผ่าน Docker Container ครับ
*   **ฝั่ง Frontend:** เราใช้ **Tailwind CSS v4** ออกแบบตามมาตรฐาน **Notion Aesthetic** เพื่อให้หน้าตามีความมินิมอล สะอาดตา ใช้งานง่ายเหมือนเอกสารงานจริง ผสานการทำงานร่วมกับ **HTMX** เพื่อให้การเปลี่ยนหน้าลื่นไหลเหมือน Single Page App โดยไม่ต้องรีเฟรชทั้งหน้าจอ และใช้ **jQuery DataTables** สำหรับการสืบค้นและจัดการตารางข้อมูลขนาดใหญ่ครับ"

### 3. โครงสร้างฐานข้อมูล (Database Architecture) — 2.5 นาที
"ในส่วนของฐานข้อมูล เราได้ออกแบบให้มีความสัมพันธ์แบบ Relational Database ทั้งหมด 15 ตารางหลัก เพื่อป้องกันความซ้ำซ้อนของข้อมูล (Data Normalization):
*   เรามีตาราง `employee` เป็นศูนย์กลาง เชื่อมโยงกับ `department` และ `job`
*   เราแยกตาราง `employee_salaries` ออกมา เพื่อบันทึกประวัติการปรับเงินเดือนตามวันที่มีผลบังคับใช้ (Effective Date) ทำให้ระบบสามารถคำนวณเงินเดือนย้อนหลังหรือกำหนดการปรับเงินเดือนล่วงหน้าได้อย่างถูกต้อง
*   ตาราง `attendance` บันทึกเวลาเข้า-ออกงานพร้อมพิกัดละติจูด ลองจิจูด และค่าความแม่นยำของ GPS
*   และเมื่อมีการประมวลผลเงินเดือน ระบบจะสร้าง **`payment_snapshots`** และ **`payroll_adjustments`** เพื่อบันทึก 'ภาพจำลอง' ของเงื่อนไขและสูตรที่ใช้คำนวณ ณ ขณะนั้นไว้ ทำให้แม้ในอนาคตจะมีการเปลี่ยนฐานเงินเดือน สลิปเงินเดือนในอดีตก็จะยังคงถูกต้อง 100% ไม่เปลี่ยนแปลงครับ"

### 4. จุดเด่นด้านเทคนิค: Geofencing & Automated Payroll — 3.5 นาที
"จุดเด่นสำคัญของระบบมี 2 ประการหลักครับ:

**ประการแรกคือ ระบบเช็คชื่อด้วย Geofencing Polygon:**
เราไม่ได้ใช้เพียงการเช็ครัศมีวงกลมธรรมดา แต่เราใช้ **อัลกอริทึม Ray-Casting Point-in-Polygon** ร่วมกับ OpenStreetMap และ Leaflet ทำให้ฝ่ายบุคคลสามารถลากเส้นพื้นที่อาคารหรือขอบเขตโรงงานเป็นรูปหลายเหลี่ยมได้อย่างอิสระ พนักงานจะต้องอยู่ในขอบเขตพิกัดจริงและมีค่าความแม่นยำ GPS ตามเกณฑ์เท่านั้นจึงจะเช็คชื่อได้ครับ

**ประการที่สองคือ Automated Payroll Engine:**
ระบบจะดึงข้อมูลวันทำงาน วันหยุดนักขัตฤกษ์ และเวลาเข้างานจริงมาสรุปจำนวนวันขาดและนาทีที่มาสายโดยอัตโนมัติ จากนั้นนำมาคำนวณร่วมกับฐานเงินเดือน ค่ารักษาพยาบาล 3%, ค่าที่พัก 8%, ค่าล่วงเวลา, หักเงินกู้ 5%, กองทุนสำรองเลี้ยงชีพ 2.5%, และสูตรหักขาด-สายตามที่องค์กรตั้งค่าไว้ ออกมาเป็นยอดสุทธิพร้อมสลิปเงินเดือนทันทีเพียงคลิกเดียวครับ"

### 5. ลำดับการสาธิตระบบ (Live Demo Flow) — 4 นาที
*(ให้ลำดับการเปิดหน้าจอตามนี้)*
1.  **Login เข้าสู่ระบบ:** สาธิตการ Login ในฐานะ Admin
2.  **หน้า Dashboard:** แสดงกราฟแนวโน้มการจ่ายเงินเดือนและภาพรวมองค์กร
3.  **ตั้งค่า Geofence:** เปิดหน้า `/settings/attendance/location` แสดงแผนที่และการวาดพื้นที่ทำงาน
4.  **เช็คชื่อเข้างาน (Employee Portal):** สลับไปหน้าพนักงาน กดเช็คชื่อเข้างานผ่าน GPS แสดง Toast แจ้งเตือน
5.  **ปฏิทินการลงเวลา:** เปิดหน้า `/attendance/calendar` แสดง Avatar พนักงานและสถานะเข้างานในปฏิทิน
6.  **ประมวลผลเงินเดือน:** เปิดหน้า `/employee/payment` เลือกพนักงาน ระบบดึงยอดขาด-สายมาคำนวณให้อัตโนมัติ และกดออกสลิปเงินเดือน
7.  **ดูสลิปเงินเดือน:** เปิดหน้า `/employee/payslip` แสดงสลิปเงินเดือนที่คำนวณเสร็จสิ้น

### 6. ความปลอดภัยและบทสรุป (Security & Conclusion) — 1.5 นาที
"ในด้านความปลอดภัย ระบบของเราป้องกัน **SQL Injection ด้วย Prepared Statements 100%**, เข้ารหัสผ่านด้วย **BCrypt (password_hash)**, ป้องกันการขโมย Session ด้วย **session_regenerate_id**, และมีระบบ **Role-Based Access Control** ป้องกันไม่ให้พนักงานเข้าถึงข้อมูลของฝ่ายบุคคลได้ครับ

สรุปได้ว่า ระบบ HR & Payroll นี้ สามารถลดเวลาการทำงานของฝ่ายบุคคล ลดข้อผิดพลาดในการจ่ายเงินเดือน และเพิ่มความโปร่งใสให้กับองค์กรได้อย่างสมบูรณ์แบบครับ กระผมขอจบการนำเสนอเพียงเท่านี้ และพร้อมรับคำถามและคำแนะนำจากท่านคณะกรรมการครับ ขอบพระคุณครับ"

---

## STEP 18 — Script การนำเสนอแบบสั้น (5 นาที Fast Track)

"กราบเรียนท่านคณะกรรมการครับ ผมขออนุญาตนำเสนอ **ระบบบริหารจัดการทรัพยากรบุคคลและคำนวณเงินเดือน HR-RPG** ครับ

*   **ปัญหาและเป้าหมาย:** ระบบนี้พัฒนาขึ้นเพื่อแก้ปัญหาการคำนวณเงินเดือนที่ซับซ้อนและผิดพลาด รวมถึงแก้ปัญหาการลงเวลาเข้างานแทนกันในองค์กรครับ
*   **Tech Stack:** พัฒนาด้วย **Pure PHP 8.3** ทำงานร่วมกับฐานข้อมูล **MySQL** โดยมี Front-end เป็น **Tailwind CSS v4** สไตล์ Notion Minimalist ร่วมกับ **HTMX** และ **jQuery DataTables** ครับ
*   **ฟีเจอร์เด่น 3 ด้าน:**
    1.  *Geofencing Attendance:* พนักงานลงเวลาผ่าน GPS มือถือ โดยระบบใช้อัลกอริทึม Ray-Casting ตรวจสอบว่าอยู่ภายในพื้นที่ Polygon ของบริษัทหรือไม่ พร้อมคำนวณการมาสายอัตโนมัติ
    2.  *Effective Salary History:* ระบบบันทึกประวัติฐานเงินเดือนตามวันที่มีผลบังคับใช้ เพื่อความถูกต้องในการคำนวณเงินเดือนตามรอบเวลา
    3.  *Automated Payroll Processing:* คำนวณเงินเดือนสุทธิจากฐานเงินเดือน สวัสดิการค่าที่พัก ค่ารักษาพยาบาล หักเงินกู้ กองทุนสำรองเลี้ยงชีพ และดึงยอดหักขาด-สายจากระบบลงเวลามาประมวลผลให้อัตโนมัติ พร้อมออกสลิปเงินเดือน
*   **ความปลอดภัย:** รหัสผ่านเข้ารหัสด้วย BCrypt, ป้องกัน SQL Injection ด้วย Prepared Statements ทุกคำสั่ง, และแยกสิทธิ์ผู้ใช้งานระหว่าง Admin และ Employee ชัดเจนครับ

ขอเชิญคณะกรรมการรับชมการสาธิตระบบสั้น ๆ ได้เลยครับ"

---

## STEP 19 — Presentation Cheat Sheet (ใบสรุปย่อสำหรับอ่านก่อนขึ้นพูด)

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                       HR-RPG PRESENTATION CHEAT SHEET                       │
├──────────────────────┬──────────────────────────────────────────────────────┤
│ Project Name         │ HR & Payroll Management System (HR-RPG)              │
│ Architecture         │ Lightweight MVC Monolith with Centralized Routing    │
│ Backend              │ Pure PHP 8.3 (Strict Types, Prepared Statements)     │
│ Frontend             │ HTML5, Tailwind CSS v4, HTMX, jQuery 4.0, DataTables │
│ Database             │ MySQL 8 / MariaDB (15 Relational Tables)             │
│ Authentication       │ PHP Session, BCrypt (password_hash), CSRF Token      │
│ UI Style             │ Notion Aesthetic (Warm Paper Canvas #f6f5f4)        │
│ Key Algorithms       │ Ray-Casting Point-in-Polygon (GPS Geofencing)        │
│ Chart & Map Libs     │ Chartist (Dashboard Charts), Leaflet (OSM Maps)      │
│ Main Roles           │ Admin (Full Control) vs Employee (Self-Service)      │
│ Automated Items      │ Med (3%), Housing (8%), Loan (5%), P-Fund (2.5%), OT │
└──────────────────────┴──────────────────────────────────────────────────────┘
```

---

## STEP 20 — รวม 25 คำถามยอดฮิตของคณะกรรมการ + แนวทางการตอบเชิงเทคนิค

#### 1. ทำไมถึงเลือกใช้ Pure PHP แทนที่จะใช้ Laravel หรือ Framework อื่น?
> **แนวตอบ:** "เราต้องการให้ระบบมีขนาดกะทัดรัด (Lightweight) ทำงานได้รวดเร็ว ประมวลผลได้ทันทีโดยไม่ต้องแบกรับ Dependency ขนาดใหญ่ของ Framework และช่วยให้เราควบคุมการทำงานของ Routing, Security, และ Database Transaction ได้อย่างละเอียดในทุกขั้นตอนครับ"

#### 2. ทำไมถึงเลือกใช้ MySQL เป็นฐานข้อมูล?
> **แนวตอบ:** "เนื่องจากข้อมูลทรัพยากรบุคคลและเงินเดือนเป็นข้อมูลที่มีความสัมพันธ์กันสูง (Relational Data) และต้องการคุณสมบัติ ACID เพื่อความถูกต้องแม่นยำของธุรกรรมทางการเงิน MySQL จึงตอบโจทย์เรื่อง Data Integrity และ Foreign Key Constraints ได้ดีที่สุดครับ"

#### 3. ฐานข้อมูลมีทั้งหมดกี่ตาราง และมีความสัมพันธ์อย่างไร?
> **แนวตอบ:** "มีทั้งหมด 15 ตารางครับ โดยมีตาราง `employee` เป็น Entity หลักที่เชื่อมโยงแบบ One-to-Many ไปยัง `attendance`, `employee_salaries`, และ `payment` เพื่อเก็บประวัติการทำงานและการจ่ายเงินของพนักงานแต่ละคนครับ"

#### 4. ระบบจัดการความปลอดภัยของรหัสผ่านอย่างไร?
> **แนวตอบ:** "เราใช้ฟังก์ชัน `password_hash()` ด้วยอัลกอริทึม BCrypt ซึ่งเป็นมาตรฐานสากลที่มีการสุ่ม Salt ในตัว และตรวจสอบด้วย `password_verify()` ทำให้ไม่มีการเก็บรหัสผ่านเป็นข้อความธรรมดาในฐานข้อมูลครับ"

#### 5. ระบบป้องกัน SQL Injection อย่างไร?
> **แนวตอบ:** "เราใช้ Prepared Statements (`mysqli_prepare` ร่วมกับ `mysqli_stmt_bind_param`) 100% ในทุกจุดที่มีการส่งตัวแปรเข้าไปในคำสั่ง SQL เพื่อแยกคำสั่ง SQL ออกจากข้อมูลอย่างเด็ดขาดครับ"

#### 6. ระบบแยกระหว่าง Admin กับ Employee อย่างไร?
> **แนวตอบ:** "เราใช้ Role-Based Access Control ผ่านฟังก์ชัน `authRole()` โดยตรวจสอบค่าใน `$_SESSION['auth_role']` ที่จุดศูนย์กลางใน `index.php` หากพนักงานทั่วไปพยายามเข้าถึง URL ของฝั่งผู้ดูแลระบบ ระบบจะทำ Redirect ออกทันทีครับ"

#### 7. ระบบคำนวณเงินเดือนอย่างไร มีสูตรอะไรบ้าง?
> **แนวตอบ:** "เงินเดือนสุทธิคำนวณจาก ฐานเงินเดือน ณ วันที่มีผลบังคับใช้ + ค่ารักษาพยาบาล 3% + ค่าที่พัก 8% + ค่าล่วงเวลา (ชั่วโมงละ 300 บาท) + รายรับพิเศษ หักด้วย เงินกู้ยืม 5% + กองทุนสำรองเลี้ยงชีพ 2.5% + ค่าขาดงานและมาสายที่คำนวณอัตโนมัติจากระบบลงเวลาครับ"

#### 8. ระบบรู้ได้อย่างไรว่าพนักงานมาสาย?
> **แนวตอบ:** "ระบบจะนำเวลาที่พนักงานกดเช็คชื่อจริง เทียบกับเวลาเริ่มงาน `work_start` ในตาราง `attendance_settings` บวกกับช่วงเวลาผ่อนผัน `grace_minutes` (เช่น 10 นาที) หากเวลาจริงเกินกำหนด ระบบจะคำนวณส่วนต่างออกมาเป็นนาทีที่มาสายทันทีครับ"

#### 9. ระบบตรวจสอบพิกัดการเช็คชื่อเข้างาน (Geofencing) อย่างไร?
> **แนวตอบ:** "เราใช้อัลกอริทึม **Ray-Casting (Point-in-Polygon)** ในไฟล์ `lib/geofence.php` เพื่อตรวจสอบว่าพิกัด Lat/Lng ที่ส่งมาจาก GPS ของเครื่องผู้ใช้งาน ตกอยู่ภายในรูปหลายเหลี่ยม (Polygon) ที่ผู้ดูแลระบบวาดไว้บนแผนที่หรือไม่ครับ"

#### 10. Polygon แตกต่างจากการตรวจจับพิกัดแบบรัศมีวงกลมอย่างไร?
> **แนวตอบ:** "รัศมีวงกลมจะครอบคลุมพื้นที่รอบจุดศูนย์กลางเป็นวงกลม ซึ่งอาจล้ำออกไปนอกพื้นที่อาคารหรือถนนสาธารณะ แต่ Polygon อนุญาตให้เรากำหนดจุดพิกัดได้หลายจุดเพื่อตีกรอบให้ตรงตามแนวเขตอาคารหรือพื้นที่ทำงานจริงได้อย่างแม่นยำครับ"

#### 11. หากผู้ใช้งานใช้โปรแกรมปลอมแปลงตำแหน่ง GPS (Mock Location) ระบบมีแนวทางป้องกันอย่างไร?
> **แนวตอบ:** "ระบบมีการตรวจสอบค่า Accuracy (ความแม่นยำของสัญญาณ GPS) โดยหากค่า Accuracy สูงเกินกว่าที่กำหนดในระบบ (เช่น เกิน 50 เมตร) จะไม่อนุญาตให้เช็คชื่อ รวมถึงในอนาคตสามารถตรวจสอบผ่าน IP Address หรือสัญญาณ Wi-Fi BSSID ร่วมด้วยได้ครับ"

#### 12. ทำไมถึงเลือกใช้ Tailwind CSS?
> **แนวตอบ:** "เพราะ Tailwind CSS เป็น Utility-First CSS Framework ที่ช่วยให้เราควบคุมการจัดเลย์เอาต์ ปรับแต่ง Responsive Breakpoints ได้รวดเร็ว และทำให้โค้ด CSS มีขนาดเล็กมากเมื่อเทียบกับ Framework แบบสำเร็จรูปครับ"

#### 13. jQuery และ DataTables ถูกนำมาใช้ในส่วนใดบ้าง?
> **แนวตอบ:** "เราใช้ jQuery สำหรับ Event Handling และใช้ DataTables ในหน้าตารางรายชื่อพนักงาน, ประวัติเงินเดือน, และประวัติเข้างาน เพื่อเพิ่มความสามารถในการค้นหาข้อมูลแบบเรียลไทม์ การจัดเรียงคอลัมน์ และการแบ่งหน้าครับ"

#### 14. HTMX ช่วยเพิ่มประสิทธิภาพของระบบอย่างไร?
> **แนวตอบ:** "HTMX ช่วยให้เราทำ Single-Page Navigation ได้โดยที่เซิร์ฟเวอร์ส่งกลับมาเฉพาะ HTML Fragment ที่ต้องการเปลี่ยน ไม่ต้องโหลดทั้งหน้าเว็บใหม่ ทำให้เว็บทำงานได้ลื่นไหลเทียบเท่ากับการใช้ React แต่เขียนง่ายและเบากว่ามากครับ"

#### 15. ระบบการแจ้งเตือน (Toast) ทำงานอย่างไร?
> **แนวตอบ:** "เราพัฒนา Custom Hot Toast ใน `lib/app.js` รองรับทั้งการเรียกผ่าน JavaScript ฝั่ง Client, การส่ง Flash Message ผ่าน Session ตอนเปลี่ยนหน้า, และการส่งผ่าน HTTP Header `HX-Trigger` จากคำขอแบบ HTMX ครับ"

#### 16. ถ้าพนักงานลาออกหรือข้อมูลพนักงานถูกลบ ประวัติเงินเดือนในอดีตจะหายหรือไม่?
> **แนวตอบ:** "ในตาราง `payment_snapshots` เราออกแบบให้เก็บข้อมูลสรุปยอดเงินและสูตรที่ใช้ในงวดนั้นๆ ไว้เป็นอิสระ รวมถึงการตั้งค่า Foreign Key บนตารางสำคัญจะใช้ `ON DELETE RESTRICT` หรือ Cascade ที่ถูกควบคุมอย่างปลอดภัย ทำให้ประวัติการเงินไม่สูญหายครับ"

#### 17. ทำไมในระบบถึงไม่มีการอัปโหลดไฟล์รูปภาพพนักงาน?
> **แนวตอบ:** "เราออกแบบให้ระบบใช้ **Dynamic Initial Avatar** ที่สร้างขึ้นจากอักษรย่อของชื่อพนักงานและสีกรอบตามตำแหน่งงาน เพื่อลดภาระพื้นที่เซิร์ฟเวอร์ ป้องกันช่องโหว่ด้าน File Upload Vulnerabilities และช่วยให้หน้าเว็บโหลดได้รวดเร็วที่สุดครับ"

#### 18. ถ้าฐานเงินเดือนของพนักงานมีการปรับขึ้นกลางปี สลิปเงินเดือนย้อนหลังจะคำนวณผิดหรือไม่?
> **แนวตอบ:** "ไม่ผิดครับ เพราะระบบของเราใช้ตาราง `employee_salaries` ที่มีฟิลด์ `effective_from` (วันที่มีผลบังคับใช้) ระบบจะเลือกฐานเงินเดือนที่ถูกต้องตามช่วงเวลาของงวดนั้นๆ มาคำนวณเสมอครับ"

#### 19. ระบบรองรับการทำงานบนหน้าจอมือถือ (Responsive) อย่างไร?
> **แนวตอบ:** "เราใช้ Tailwind Breakpoints (`sm`, `md`, `lg`) ในการปรับโครงสร้าง เช่น หน้าจอมือถือจะเปลี่ยน Sidebar ให้เป็น Drawer แบบ Off-canvas และเปลี่ยนตารางให้เลื่อนในแนวนอนได้ (Horizontal Scroll) พร้อมทั้งปรับฟอร์มให้เป็นแบบ Single Column ครับ"

#### 20. Database Backup และ Migration มีแนวทางอย่างไร?
> **แนวตอบ:** "เรามีไฟล์ SQL Migration ตามลำดับวันที่อยู่ในโฟลเดอร์ `database/` สำหรับการ Version Control ฐานข้อมูล และสามารถสำรองข้อมูลผ่านคำสั่ง `mysqldump` หรือระบบ Automated Snapshot ของ Cloud Platform ครับ"

#### 21. ถ้ามีผู้ใช้งานเข้าใช้งานพร้อมกันจำนวนมาก ระบบรองรับได้ดีแค่ไหน?
> **แนวตอบ:** "เนื่องจากระบบเป็น Pure PHP ที่ประมวลผลเร็วมากและไม่มี Overhead ของ Framework ร่วมกับการใช้ Connection Pooling และ Persistent Database Connections ทำให้ระบบสามารถรองรับ Request ได้หลายร้อยคำขอต่อวินาทีบนเซิร์ฟเวอร์ขนาดเล็กได้อย่างสบายครับ"

#### 22. ทำไมถึงมีการเก็บข้อมูล Payment ซ้ำกันทั้งใน `payment` และ `payment_snapshots`?
> **แนวตอบ:** "ตาราง `payment` เป็นโครงสร้างดั้งเดิมเพื่อความเข้ากันได้ย้อนหลัง ส่วน `payment_snapshots` เป็นตารางที่พัฒนาขึ้นใหม่เพื่อเก็บรายละเอียดเชิงลึก เช่น ค่าตัวคูณ, จำนวนนาทีสายจริง, และบันทึก JSON Settings เพื่อใช้ในการตรวจสอบย้อนหลัง (Audit Trail) ครับ"

#### 23. ระบบป้องกันการส่งฟอร์มซ้ำซ้อน (Double Submit) อย่างไร?
> **แนวตอบ:** "ใน `lib/app.js` เรามีการดักจับ Event การกดปุ่ม Submit โดยจะทำการ Disable ปุ่มทันทีและแสดงไอคอน Loading Spinnner พร้อมทั้งมีระบบ Client-Side Lock ป้องกันไม่ให้ส่งคำขอซ้ำในขณะที่คำขอเดิมกำลังประมวลผลครับ"

#### 24. หากอินเทอร์เน็ตของเครื่องพนักงานหลุดขณะกดเช็คชื่อ จะเกิดอะไรขึ้น?
> **แนวตอบ:** "ระบบจะแจ้งเตือนผ่าน Toast สีแดงว่าไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้ และยกเลิกสถานะ Loading บนปุ่มกด เพื่อให้พนักงานสามารถกดลองใหม่อีกครั้งเมื่อสัญญาณอินเทอร์เน็ตกลับมาครับ"

#### 25. โครงงานนี้สามารถนำไปพัฒนาต่อยอดในอนาคตได้อย่างไรบ้าง?
> **แนวตอบ:** "สามารถต่อยอดเพิ่มระบบขออนุมัติการลาออนไลน์ (Leave Request Workflow), การเชื่อมต่อแจ้งเตือนผ่าน LINE Notify / LINE Messaging API เมื่อเงินเดือนออก, และการ Export รายงานภาษี (ภ.ง.ด.1) เป็นไฟล์ Excel หรือ PDF ครับ"

---

## STEP 21 — อภิธานศัพท์เทคนิค (Technical Terms Glossary)

1.  **Monolithic Architecture:** สถาปัตยกรรมซอฟต์แวร์ที่รวมส่วนติดต่อผู้ใช้ (Frontend) และส่วนประมวลผล (Backend) ไว้ใน Project เดียวกัน
2.  **Centralized Routing:** การรวมการรับส่ง Request ทั้งหมดของระบบไว้ที่ไฟล์ศูนย์กลางเพียงไฟล์เดียว (`index.php`)
3.  **Prepared Statements:** วิธีการส่งคำสั่ง SQL โดยแยกโค้ดคำสั่งออกจากตัวแปรข้อมูล เพื่อป้องกันการโจมตีแบบ SQL Injection
4.  **BCrypt / Password Hashing:** การแปลงรหัสผ่านให้เป็นรหัสสุ่มทางเดียวที่ไม่สามารถถอดรหัสย้อนกลับได้ เพื่อความปลอดภัย
5.  **Session Fixation:** การโจมตีที่ผู้ไม่หวังดีพยายามสวมรอยใช้ Session ID เดิม ซึ่งป้องกันได้ด้วยคำสั่ง `session_regenerate_id()`
6.  **Geofencing:** การสร้างขอบเขตทางภูมิศาสตร์เสมือนจริงบนแผนที่ เพื่อกำหนดพื้นที่ทำงาน
7.  **Point-in-Polygon (Ray-Casting):** อัลกอริทึมทางคณิตศาสตร์ที่ใช้ตรวจสอบว่าจุดพิกัด (Lat, Lng) อยู่ภายในพื้นที่รูปหลายเหลี่ยมหรือไม่
8.  **Audit Snapshot:** การบันทึกภาพถ่ายของสถานะและเงื่อนไขข้อมูล ณ เวลาที่เกิดธุรกรรม เพื่อใช้ตรวจสอบย้อนหลัง
9.  **Effective Date:** วันที่มีผลบังคับใช้ของข้อมูล เช่น การปรับอัตราเงินเดือนที่มีผลลัพธ์ในงวดเดือนถัดไป
10. **HTMX:** ไลบรารี JavaScript ที่ช่วยให้ HTML สามารถส่งคำขอ AJAX และสลับเนื้อหาบางส่วนของหน้าเว็บได้โดยตรง

---

## STEP 22 — สิ่งที่ควรตรวจสอบก่อนขึ้นนำเสนอ (Pre-Presentation Checklist)

### ระดับ High / Critical (สำคัญมาก)
*   [ ] ตรวจสอบว่า Service MySQL และ Web Server กำลังทำงานอยู่ (`localhost:8000`)
*   [ ] ตรวจสอบว่าในตาราง `admin` มีบัญชีสำหรับ Login เข้าทดสอบ (เช่น `username: Arjun`, `password: abc123`)
*   [ ] ตรวจสอบว่าเบราว์เซอร์อนุญาตการเข้าถึง **Location / GPS** แล้วสำหรับการสาธิตระบบ Geofence

### ระดับ Medium (ความเรียบร้อยของข้อมูล Demo)
*   [ ] มีข้อมูลพนักงานตัวอย่างในแผนกต่างๆ อย่างน้อย 5–10 คน
*   [ ] มีการสร้างพื้นที่ Geofence ในหน้าตั้งค่าครอบคลุมพิกัดปัจจุบันที่กำลังใช้พรีเซนต์
*   [ ] ตรวจสอบว่ามีพนักงานบางคนที่ยังไม่ได้จ่ายเงินเดือนในงวดปัจจุบัน เพื่อใช้สาธิตการคำนวณเงินเดือนสดๆ บนเวที

---

## STEP 23 & 24 — สรุปภาพรวมขั้นสุดท้าย (Final Summary)

```
================================================================================
                           FINAL PROJECT SUMMARY
================================================================================
Project Name        : HR & Payroll Management System (HR-RPG)
Project Type        : Web-based HR, Time-Attendance, and Automated Payroll System
Frontend Stack      : HTML5, Tailwind CSS v4, JavaScript (ES6), jQuery 4.0, 
                      DataTables 1.13.6, HTMX 2.0.10, Chartist, Leaflet Maps
Backend Stack       : Pure PHP 8.3 (Strict Typing, Centralized MVC-like Router)
Database Engine     : MySQL 8.x / MariaDB (15 Relational Tables)
Database Driver     : PHP MySQLi Extension with 100% Prepared Statements
Authentication      : PHP Session-based Auth, BCrypt Hashing, Role-Based Access
UI/UX Style         : Notion Aesthetic (Warm Paper #f6f5f4, Clean Typography)
Typography          : LINE Seed Sans TH (5 Weights, Local WOFF2 Fonts)
Security Highlights : Prepared Statements, XSS Escaping, CSRF Tokens, 
                      Session Regeneration, Secure Password Hashing
Core Highlights     : 1. GPS Geofence Check-in with Ray-Casting Polygon
                      2. Effective Date Salary History Management
                      3. Automated Payroll Engine (Medical, Housing, OT, P-Fund)
                      4. Interactive Attendance Calendar with Initials Avatars
                      5. Immutable Payment Snapshots for Audit Integrity
================================================================================
```