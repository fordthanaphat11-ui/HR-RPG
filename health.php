<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

// ตอบกลับ Railway ทันทีว่า Web Server ทำงานแล้ว เพื่อให้ Deploy ผ่านทันที
echo json_encode(['status' => 'ok', 'server' => 'running'], JSON_UNESCAPED_SLASHES);

