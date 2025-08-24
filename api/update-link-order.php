<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查系统是否已安装
checkInstallation();

// 检查登录状态
if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => '未授权访问']);
    exit;
}

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '方法不允许']);
    exit;
}

// 获取JSON数据
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['links']) || !is_array($input['links'])) {
    http_response_code(400);
    echo json_encode(['error' => '无效的请求数据']);
    exit;
}

$pdo = getDatabase();

try {
    // 开始事务
    $pdo->beginTransaction();
    
    // 更新每个链接的排序
    $stmt = $pdo->prepare("UPDATE links SET sort_order = ? WHERE id = ?");
    
    foreach ($input['links'] as $index => $linkId) {
        $linkId = (int)$linkId;
        $sortOrder = $index + 1; // 从1开始排序
        
        if ($linkId > 0) {
            $stmt->execute([$sortOrder, $linkId]);
        }
    }
    
    // 提交事务
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => '排序更新成功']);
    
} catch (PDOException $e) {
    // 回滚事务
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode(['error' => '更新失败：' . $e->getMessage()]);
}
?>
