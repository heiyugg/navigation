<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'layout.php';

// 检查系统是否已安装
checkInstallation();

// 检查登录状态
if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

$pdo = getDatabase();

// 获取筛选参数
$action_filter = trim($_GET['action'] ?? '');
$days_filter = (int)($_GET['days'] ?? 7);
$days_filter = max(1, min(365, $days_filter));

// 处理清理日志操作
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_logs'])) {
    $clear_days = (int)($_POST['clear_days'] ?? 30);
    $clear_days = max(1, min(365, $clear_days));
    
    try {
        $stmt = $pdo->prepare("DELETE FROM system_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$clear_days]);
        $deleted_count = $stmt->rowCount();
        $message = "成功清理了 {$deleted_count} 条 {$clear_days} 天前的日志记录";
    } catch (PDOException $e) {
        $error = '清理日志失败：' . $e->getMessage();
    }
}

// 获取日志统计
try {
    // 总日志数
    $totalLogs = $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
    
    // 最近指定天数的日志数
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM system_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
    $stmt->execute([$days_filter]);
    $recentLogs = $stmt->fetchColumn();
    
    // 今日日志数
    $todayLogs = $pdo->query("SELECT COUNT(*) FROM system_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    
    // 操作类型统计
    $stmt = $pdo->prepare("
        SELECT 
            action,
            COUNT(*) as count
        FROM system_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY action 
        ORDER BY count DESC
    ");
    $stmt->execute([$days_filter]);
    $actionStats = $stmt->fetchAll();
    
    // 获取日志列表
    $sql = "
        SELECT 
            sl.*,
            au.username as admin_username
        FROM system_logs sl
        LEFT JOIN admin_users au ON sl.admin_id = au.id
        WHERE sl.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ";
    $params = [$days_filter];
    
    if (!empty($action_filter)) {
        $sql .= " AND sl.action = ?";
        $params[] = $action_filter;
    }
    
    $sql .= " ORDER BY sl.created_at DESC LIMIT 500";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $error = '获取日志数据失败：' . $e->getMessage();
}

// 获取所有操作类型（用于筛选下拉框）
try {
    $allActions = $pdo->query("
        SELECT DISTINCT action 
        FROM system_logs 
        ORDER BY action ASC
    ")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $allActions = [];
}

ob_start();
?>

<div style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">📋 系统日志</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="showClearDialog()" class="btn btn-danger">🗑️ 清理日志</button>
            <button onclick="window.location.reload()" class="btn btn-secondary">🔄 刷新</button>
        </div>
    </div>
</div>

<?php if (isset($message)): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<!-- 统计卡片 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #3498db; margin-bottom: 10px;">📋</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($totalLogs); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">总日志数</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #e74c3c; margin-bottom: 10px;">📈</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($recentLogs); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">最近<?php echo $days_filter; ?>天</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #27ae60; margin-bottom: 10px;">📅</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($todayLogs); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">今日日志</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #f39c12; margin-bottom: 10px;">🔧</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo count($actionStats); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">操作类型</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- 日志列表 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📋 日志记录</h3>
            <div style="display: flex; gap: 10px; align-items: center;">
                <form method="get" style="display: flex; gap: 10px; align-items: center;">
                    <select name="days" onchange="this.form.submit()" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="1" <?php echo $days_filter == 1 ? 'selected' : ''; ?>>今日</option>
                        <option value="7" <?php echo $days_filter == 7 ? 'selected' : ''; ?>>最近7天</option>
                        <option value="30" <?php echo $days_filter == 30 ? 'selected' : ''; ?>>最近30天</option>
                        <option value="90" <?php echo $days_filter == 90 ? 'selected' : ''; ?>>最近90天</option>
                    </select>
                    
                    <select name="action" onchange="this.form.submit()" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="">所有操作</option>
                        <?php foreach ($allActions as $action): ?>
                            <option value="<?php echo h($action); ?>" <?php echo $action_filter === $action ? 'selected' : ''; ?>>
                                <?php echo h(getActionDisplayName($action)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="hidden" name="days" value="<?php echo $days_filter; ?>">
                </form>
            </div>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (empty($logs)): ?>
                <div style="text-align: center; color: #7f8c8d; padding: 40px;">暂无日志记录</div>
            <?php else: ?>
                <div style="max-height: 600px; overflow-y: auto;">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>时间</th>
                                <th>操作</th>
                                <th>管理员</th>
                                <th>描述</th>
                                <th>IP地址</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 12px;">
                                        <?php echo date('m-d H:i:s', strtotime($log['created_at'])); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo getActionBadgeClass($log['action']); ?>">
                                            <?php echo h(getActionDisplayName($log['action'])); ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 14px;">
                                        <?php echo h($log['admin_username'] ?? '系统'); ?>
                                    </td>
                                    <td style="font-size: 14px; max-width: 300px; word-break: break-word;">
                                        <?php echo h($log['description'] ?? '-'); ?>
                                    </td>
                                    <td style="font-size: 12px; color: #7f8c8d;">
                                        <?php echo h($log['ip_address']); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($logs) >= 500): ?>
                    <div style="padding: 15px; text-align: center; color: #7f8c8d; border-top: 1px solid #dee2e6;">
                        <small>仅显示最近500条记录，如需查看更多请调整筛选条件</small>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 操作统计 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📊 操作统计（最近<?php echo $days_filter; ?>天）</h3>
        </div>
        <div class="card-body">
            <?php if (empty($actionStats)): ?>
                <div style="text-align: center; color: #7f8c8d; padding: 20px;">暂无统计数据</div>
            <?php else: ?>
                <?php $totalActions = array_sum(array_column($actionStats, 'count')); ?>
                <?php foreach ($actionStats as $stat): ?>
                    <?php $percentage = $totalActions > 0 ? round(($stat['count'] / $totalActions) * 100, 1) : 0; ?>
                    <div style="margin-bottom: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                            <span style="font-weight: 600; font-size: 14px;"><?php echo h(getActionDisplayName($stat['action'])); ?></span>
                            <span style="color: #7f8c8d; font-size: 12px;"><?php echo number_format($stat['count']); ?> (<?php echo $percentage; ?>%)</span>
                        </div>
                        <div style="background: #ecf0f1; height: 6px; border-radius: 3px; overflow: hidden;">
                            <div style="background: <?php echo getActionColor($stat['action']); ?>; height: 100%; width: <?php echo $percentage; ?>%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 清理日志对话框 -->
<div id="clearDialog" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); min-width: 400px;">
        <h3 style="margin: 0 0 20px 0; color: #2c3e50;">🗑️ 清理系统日志</h3>
        <form method="post">
            <div class="form-group">
                <label class="form-label">清理多少天前的日志：</label>
                <select name="clear_days" class="form-control">
                    <option value="7">7天前</option>
                    <option value="30" selected>30天前</option>
                    <option value="90">90天前</option>
                    <option value="180">180天前</option>
                    <option value="365">一年前</option>
                </select>
            </div>
            <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 4px; margin-bottom: 20px;">
                <div style="color: #856404; font-size: 14px;">
                    <strong>⚠️ 注意：</strong>此操作将永久删除指定时间之前的所有日志记录，无法恢复！
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="hideClearDialog()" class="btn btn-secondary">取消</button>
                <button type="submit" name="clear_logs" class="btn btn-danger" onclick="return confirm('确定要清理日志吗？此操作无法撤销！')">确认清理</button>
            </div>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();

// 添加JavaScript
$additional_js = '
<script>
function showClearDialog() {
    document.getElementById("clearDialog").style.display = "block";
}

function hideClearDialog() {
    document.getElementById("clearDialog").style.display = "none";
}

// 点击对话框外部关闭
document.getElementById("clearDialog").addEventListener("click", function(e) {
    if (e.target === this) {
        hideClearDialog();
    }
});

// ESC键关闭对话框
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        hideClearDialog();
    }
});
</script>';

// 辅助函数
function getActionDisplayName($action) {
    $names = [
        'login' => '管理员登录',
        'logout' => '管理员退出',
        'category_add' => '添加分类',
        'category_update' => '更新分类',
        'category_delete' => '删除分类',
        'link_add' => '添加链接',
        'link_update' => '更新链接',
        'link_delete' => '删除链接',
        'slide_add' => '添加幻灯片',
        'slide_update' => '更新幻灯片',
        'slide_delete' => '删除幻灯片',
        'settings_update' => '更新设置',
        'system_install' => '系统安装',
        'system_update' => '系统更新',
        'database_update' => '数据库更新',
        'file_upload' => '文件上传',
        'file_delete' => '文件删除',
    ];
    
    return $names[$action] ?? $action;
}

function getActionBadgeClass($action) {
    $classes = [
        'login' => 'badge-success',
        'logout' => 'badge-secondary',
        'category_add' => 'badge-success',
        'category_update' => 'badge-warning',
        'category_delete' => 'badge-danger',
        'link_add' => 'badge-success',
        'link_update' => 'badge-warning',
        'link_delete' => 'badge-danger',
        'slide_add' => 'badge-success',
        'slide_update' => 'badge-warning',
        'slide_delete' => 'badge-danger',
        'settings_update' => 'badge-warning',
        'system_install' => 'badge-success',
        'system_update' => 'badge-warning',
        'database_update' => 'badge-warning',
        'file_upload' => 'badge-success',
        'file_delete' => 'badge-danger',
    ];
    
    return $classes[$action] ?? 'badge-secondary';
}

function getActionColor($action) {
    $colors = [
        'login' => '#28a745',
        'logout' => '#6c757d',
        'category_add' => '#28a745',
        'category_update' => '#ffc107',
        'category_delete' => '#dc3545',
        'link_add' => '#28a745',
        'link_update' => '#ffc107',
        'link_delete' => '#dc3545',
        'slide_add' => '#28a745',
        'slide_update' => '#ffc107',
        'slide_delete' => '#dc3545',
        'settings_update' => '#ffc107',
        'system_install' => '#28a745',
        'system_update' => '#ffc107',
        'database_update' => '#ffc107',
        'file_upload' => '#28a745',
        'file_delete' => '#dc3545',
    ];
    
    return $colors[$action] ?? '#6c757d';
}

renderAdminLayout('系统日志', $content, 'logs', '', $additional_js);
?>
