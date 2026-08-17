# Deploy ระบบ HR-RPG ขึ้น Railway

โปรเจกต์นี้เตรียม `Dockerfile`, `railway.json` และคำสั่งสร้างฐานข้อมูลครั้งแรกไว้แล้ว

## 1. Push โค้ดขึ้น GitHub

ตรวจสอบว่าไฟล์ `.env` ไม่ถูก commit จากนั้น push โค้ดขึ้น repository ที่ต้องการใช้ deploy

## 2. สร้างโปรเจกต์และ MySQL

1. เปิด Railway แล้วสร้าง `Empty Project`
2. กด `+ New` → `Database` → `MySQL`
3. กด `+ New` → `GitHub Repo` แล้วเลือก repository ของระบบนี้

Railway จะพบ `Dockerfile` ที่ root และใช้สร้าง PHP 8.3 + Apache ให้อัตโนมัติ

## 3. เชื่อมตัวแปร MySQL เข้ากับ Web Service

เปิด Web Service → `Variables` → `Raw Editor` แล้วใส่ค่าด้านล่าง หาก database service ชื่อ `MySQL`:

```env
DB_HOST=${{MySQL.MYSQLHOST}}
DB_PORT=${{MySQL.MYSQLPORT}}
DB_USERNAME=${{MySQL.MYSQLUSER}}
DB_PASSWORD=${{MySQL.MYSQLPASSWORD}}
DB_DATABASE=${{MySQL.MYSQLDATABASE}}
DB_CHARSET=utf8mb4
```

ถ้าเปลี่ยนชื่อ database service ให้แทนคำว่า `MySQL` ด้วยชื่อ service จริง หรือเลือก `Add Reference` จากหน้า Variables เพื่อไม่ต้องพิมพ์เอง

ไม่ต้องตั้ง `PORT` เอง ตัวเริ่ม Apache จะอ่าน port ที่ Railway ส่งมาและ bind ที่ `0.0.0.0` ให้อัตโนมัติ

## 4. Deploy

กด `Deploy` หรือ push commit ใหม่ขึ้น branch ที่เชื่อมไว้ การ deploy ครั้งแรกจะรัน:

```text
php scripts/railway-init.php
```

คำสั่งนี้ import `database/EE.sql` เฉพาะเมื่อฐานข้อมูลว่าง และจะไม่สร้าง database ชื่อ `hr-rpg` ทับ database ที่ Railway จัดให้ หากฐานข้อมูลมี schema ครบแล้ว ระบบจะข้ามการ import

หากฐานข้อมูลไม่ว่างแต่มี schema ไม่ครบ deployment จะหยุด เพื่อป้องกันข้อมูลเสียหาย

## 5. เปิด Public Domain

เปิด Web Service → `Settings` → `Networking` → `Generate Domain` แล้วเข้า URL `https://...up.railway.app`

Health check ใช้ path `/health.php` เพื่อตรวจทั้ง PHP และการเชื่อมต่อฐานข้อมูล โดยไม่เปิดเผย credential และ Apache เปิด `.htaccess` rewrite สำหรับ route ของระบบแล้ว

## 6. หลัง deploy

- ทดสอบล็อกอิน หน้าเช็คชื่อ ประวัติ และหน้าจ่ายเงินเดือน
- เปลี่ยนรหัสผ่านผู้ดูแลเริ่มต้นทันที และไม่ใช้รหัสตัวอย่างใน production
- ตั้งพื้นที่เช็คชื่อจริงก่อนเปิดนโยบายบังคับ Geofence
- สำรองฐานข้อมูลก่อนแก้ schema หรือ import ข้อมูลเพิ่ม

## Deploy จาก Railway CLI (ทางเลือก)

หลังสร้าง Project, MySQL และ Variables ใน Dashboard แล้ว สามารถ deploy โค้ดจากเครื่องโดยใช้:

```powershell
railway login
railway link
railway up
```
