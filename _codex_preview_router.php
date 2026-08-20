<?php
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
if ($path !== '/' && is_file($file)) return false;
session_start();
$_SESSION['username'] = 'Codex Preview';
$_SESSION['auth_role'] = 'admin';
$_SESSION['display_name'] = 'Codex Preview';
session_write_close();
require __DIR__ . '/index.php';
