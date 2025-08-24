<?php
session_start();
require_once '../includes/functions.php';

// 执行退出登录
adminLogout();

// 重定向到登录页面
header('Location: index.php');
exit;
?>
