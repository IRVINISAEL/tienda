<?php
// auth/logout.php
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();
session_destroy();
setcookie('PHPSESSID', '', time() - 3600, '/');
header('Location: login.php');
exit;