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

// 获取时间范围参数
$days = (int)($_GET['days'] ?? 7);
$days = max(1, min(365, $days)); // 限制在1-365天之间

// 获取基础统计数据
try {
    // 总体统计
    $totalLinks = $pdo->query("SELECT COUNT(*) FROM links WHERE status = 1")->fetchColumn();
    $totalCategories = $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 1")->fetchColumn();
    $totalVisits = $pdo->query("SELECT SUM(visits) FROM links")->fetchColumn() ?: 0;
    $totalSlides = $pdo->query("SELECT COUNT(*) FROM slides WHERE status = 1")->fetchColumn();
    
    // 最近访问统计
    $recentVisits = $pdo->prepare("
        SELECT COUNT(*) 
        FROM visit_stats 
        WHERE visit_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $recentVisits->execute([$days]);
    $recentVisitsCount = $recentVisits->fetchColumn() ?: 0;
    
    // 今日访问统计
    $todayVisits = $pdo->query("
        SELECT COUNT(*) 
        FROM visit_stats 
        WHERE DATE(visit_time) = CURDATE()
    ")->fetchColumn() ?: 0;
    
    // 昨日访问统计
    $yesterdayVisits = $pdo->query("
        SELECT COUNT(*) 
        FROM visit_stats 
        WHERE DATE(visit_time) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
    ")->fetchColumn() ?: 0;
    
    // 热门链接统计（最近指定天数）
    $popularLinks = $pdo->prepare("
        SELECT 
            l.id,
            l.title,
            l.url,
            l.icon,
            COUNT(vs.id) as recent_visits,
            l.visits as total_visits,
            c.name as category_name
        FROM links l
        LEFT JOIN visit_stats vs ON l.id = vs.link_id 
            AND vs.visit_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        LEFT JOIN categories c ON l.category_id = c.id
        WHERE l.status = 1
        GROUP BY l.id
        ORDER BY recent_visits DESC, total_visits DESC
        LIMIT 10
    ");
    $popularLinks->execute([$days]);
    $popularLinksData = $popularLinks->fetchAll();
    
    // 分类访问统计
    $categoryStats = $pdo->prepare("
        SELECT 
            c.id,
            c.name,
            c.icon,
            COUNT(DISTINCT l.id) as link_count,
            SUM(l.visits) as total_visits,
            COUNT(vs.id) as recent_visits
        FROM categories c
        LEFT JOIN links l ON c.id = l.category_id AND l.status = 1
        LEFT JOIN visit_stats vs ON l.id = vs.link_id 
            AND vs.visit_time >= DATE_SUB(NOW(), INTERVAL ? DAY)
        WHERE c.status = 1
        GROUP BY c.id
        ORDER BY recent_visits DESC, total_visits DESC
    ");
    $categoryStats->execute([$days]);
    $categoryStatsData = $categoryStats->fetchAll();
    
} catch (PDOException $e) {
    $error = '获取统计数据失败：' . $e->getMessage();
}

ob_start();
?>

<div style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;">📊 网站统计</h2>
        <div style="display: flex; gap: 10px; align-items: center;">
            <span style="color: #666;">统计周期：</span>
            <select onchange="window.location.href='stats.php?days='+this.value" style="padding: 5px 10px; border: 1px solid #ddd; border-radius: 4px;">
                <option value="1" <?php echo $days == 1 ? 'selected' : ''; ?>>今日</option>
                <option value="7" <?php echo $days == 7 ? 'selected' : ''; ?>>最近7天</option>
                <option value="30" <?php echo $days == 30 ? 'selected' : ''; ?>>最近30天</option>
                <option value="90" <?php echo $days == 90 ? 'selected' : ''; ?>>最近90天</option>
                <option value="365" <?php echo $days == 365 ? 'selected' : ''; ?>>最近一年</option>
            </select>
            <button onclick="window.location.reload()" class="btn btn-secondary" style="padding: 5px 15px;">🔄 刷新</button>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<!-- 总体统计卡片 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #3498db; margin-bottom: 10px;">🔗</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($totalLinks); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">活跃链接</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #e74c3c; margin-bottom: 10px;">📁</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($totalCategories); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">分类数量</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #27ae60; margin-bottom: 10px;">👁️</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($totalVisits); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">总访问量</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #f39c12; margin-bottom: 10px;">🎬</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($totalSlides); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">幻灯片</div>
        </div>
    </div>
</div>

<!-- 访问统计 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #9b59b6; margin-bottom: 10px;">📈</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($recentVisitsCount); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">最近<?php echo $days; ?>天访问</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #1abc9c; margin-bottom: 10px;">📅</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($todayVisits); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">今日访问</div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-body" style="text-align: center; padding: 20px;">
            <div style="font-size: 32px; color: #34495e; margin-bottom: 10px;">📊</div>
            <div style="font-size: 24px; font-weight: bold; color: #2c3e50;"><?php echo number_format($yesterdayVisits); ?></div>
            <div style="color: #7f8c8d; font-size: 14px;">昨日访问</div>
            <?php if ($yesterdayVisits > 0): ?>
                <?php $change = $todayVisits - $yesterdayVisits; ?>
                <?php $changePercent = round(($change / $yesterdayVisits) * 100, 1); ?>
                <div style="font-size: 12px; margin-top: 5px; color: <?php echo $change >= 0 ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo $change >= 0 ? '↗' : '↘'; ?> <?php echo abs($changePercent); ?>%
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
    <!-- 热门链接 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">🔥 热门链接（最近<?php echo $days; ?>天）</h3>
        </div>
        <div class="card-body">
            <?php if (empty($popularLinksData)): ?>
                <div style="text-align: center; color: #7f8c8d; padding: 20px;">暂无访问数据</div>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($popularLinksData as $index => $link): ?>
                        <div style="display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee;">
                            <div style="width: 30px; text-align: center; font-weight: bold; color: <?php echo $index < 3 ? '#e74c3c' : '#7f8c8d'; ?>;">
                                <?php echo $index + 1; ?>
                            </div>
                            <div style="flex: 1; margin-left: 10px;">
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <?php if ($link['icon']): ?>
                                        <?php echo renderLinkIcon($link['icon'], '16px'); ?>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-weight: 600; font-size: 14px;"><?php echo h(mb_substr($link['title'], 0, 20)); ?></div>
                                        <div style="font-size: 12px; color: #7f8c8d;"><?php echo h($link['category_name']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: bold; color: #e74c3c;"><?php echo number_format($link['recent_visits']); ?></div>
                                <div style="font-size: 12px; color: #7f8c8d;">总计: <?php echo number_format($link['total_visits']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- 分类统计 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">📁 分类统计（最近<?php echo $days; ?>天）</h3>
        </div>
        <div class="card-body">
            <?php if (empty($categoryStatsData)): ?>
                <div style="text-align: center; color: #7f8c8d; padding: 20px;">暂无分类数据</div>
            <?php else: ?>
                <div style="max-height: 400px; overflow-y: auto;">
                    <?php foreach ($categoryStatsData as $category): ?>
                        <div style="display: flex; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee;">
                            <div style="display: flex; align-items: center; gap: 8px; flex: 1;">
                                <?php if ($category['icon']): ?>
                                    <?php echo renderCategoryIcon($category['icon'], '16px'); ?>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 600; font-size: 14px;"><?php echo h($category['name']); ?></div>
                                    <div style="font-size: 12px; color: #7f8c8d;"><?php echo $category['link_count']; ?> 个链接</div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: bold; color: #3498db;"><?php echo number_format($category['recent_visits']); ?></div>
                                <div style="font-size: 12px; color: #7f8c8d;">总计: <?php echo number_format($category['total_visits']); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
renderAdminLayout('网站统计', $content, 'stats');
?>
