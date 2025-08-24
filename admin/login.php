<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查是否已安装
if (!file_exists('../config/installed.lock')) {
    header('Location: ../install.php');
    exit;
}

// 处理登录
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (adminLogin($username, $password)) {
        // 记录登录日志
        $pdo = getDatabase();
        $stmt = $pdo->prepare("UPDATE admin_users SET last_login = NOW(), login_count = login_count + 1 WHERE username = ?");
        $stmt->execute([$username]);
        
        // 如果是从首页登录，重定向到后台首页
        header('Location: dashboard.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}

// 如果已登录，跳转到仪表板
if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

// 如果有错误，重定向回首页并显示错误（通过session）
if (isset($error)) {
    $_SESSION['login_error'] = $error;
    header('Location: ../index.php#login-error');
    exit;
}

// 如果是GET请求，重定向到登录页面
header('Location: index.php');
exit;
?>