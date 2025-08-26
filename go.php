<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 检查系统是否已安装
checkInstallation();

// 获取链接ID
$link_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($link_id <= 0) {
    header('HTTP/1.1 404 Not Found');
    echo '链接不存在';
    exit;
}

try {
    $pdo = getDatabase();
    
    // 获取链接信息
    $stmt = $pdo->prepare("
        SELECT l.*, c.is_hidden 
        FROM links l 
        JOIN categories c ON l.category_id = c.id 
        WHERE l.id = ? AND l.status = 1 AND c.status = 1
    ");
    $stmt->execute([$link_id]);
    $link = $stmt->fetch();
    
    if (!$link) {
        header('HTTP/1.1 404 Not Found');
        echo '链接不存在或已被禁用';
        exit;
    }
    
    // 检查隐藏分类权限
    if ($link['is_hidden'] && !isAdminLoggedIn()) {
        header('HTTP/1.1 403 Forbidden');
        echo '无权访问此链接';
        exit;
    }
    
    // 获取访问者信息
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // 检查是否需要更新访问统计（防止重复计数）
    $stmt = $pdo->prepare("
        SELECT id FROM visit_stats 
        WHERE link_id = ? AND ip_address = ? 
        AND visit_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$link_id, $ip_address]);
    
    $should_count = !$stmt->fetch();
    
    if ($should_count) {
        // 开始事务
        $pdo->beginTransaction();
        
        try {
            // 更新链接访问次数
            $stmt = $pdo->prepare("UPDATE links SET visits = visits + 1 WHERE id = ?");
            $stmt->execute([$link_id]);
            
            // 记录访问统计
            $stmt = $pdo->prepare("INSERT INTO visit_stats (link_id, ip_address, user_agent, referer) VALUES (?, ?, ?, ?)");
            $stmt->execute([$link_id, $ip_address, $user_agent, $referer]);
            
            // 提交事务
            $pdo->commit();
        } catch (Exception $e) {
            // 回滚事务
            $pdo->rollback();
            // 继续执行跳转，不因统计失败而影响用户体验
            error_log("Visit update error: " . $e->getMessage());
        }
    }
    
    // 跳转到目标链接
    header('Location: ' . $link['url']);
    exit;
    
} catch (Exception $e) {
    error_log("Go redirect error: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo '服务器错误，请稍后重试';
    exit;
}
?>
