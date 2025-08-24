<?php
/**
 * 更新链接访问计数API
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['link_id']) || !is_numeric($input['link_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid link_id']);
    exit;
}

$link_id = (int)$input['link_id'];

try {
    // 引入数据库配置
    require_once '../config/database.php';
    
    $pdo = getDatabase();
    
    // 检查链接是否存在且有效
    $stmt = $pdo->prepare("SELECT id FROM links WHERE id = ? AND status = 1");
    $stmt->execute([$link_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Link not found']);
        exit;
    }
    
    // 获取访问者信息
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    
    // 防止重复计数（同一IP在5分钟内的重复访问不计数）
    $stmt = $pdo->prepare("
        SELECT id FROM visit_stats 
        WHERE link_id = ? AND ip_address = ? 
        AND visit_time > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$link_id, $ip_address]);
    
    if ($stmt->fetch()) {
        // 5分钟内已经访问过，不重复计数
        echo json_encode(['success' => true, 'message' => 'Already counted recently']);
        exit;
    }
    
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
        throw $e;
    }
    
    echo json_encode(['success' => true, 'message' => 'Visit count updated']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
    
    // 记录错误日志
    error_log("Visit update error: " . $e->getMessage());
}
?>
