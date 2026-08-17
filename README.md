# HR-RPG

## ตั้งค่าฐานข้อมูล

คัดลอก `.env.example` เป็น `.env` แล้วแก้ค่าการเชื่อมต่อให้ตรงกับเครื่อง:

```env
DB_HOST=localhost
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=
DB_DATABASE=hr-rpg
DB_CHARSET=utf8mb4
```

ค่าที่กำหนดจาก Web Server หรือระบบปฏิบัติการจะมีลำดับความสำคัญสูงกว่าค่าใน `.env`

## เริ่มระบบ

```powershell
php -S localhost:8000
```

## Deploy

ดูขั้นตอนสำหรับ Railway ที่ [DEPLOY_RAILWAY.md](DEPLOY_RAILWAY.md)
