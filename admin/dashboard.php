<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查系统是否已安装
checkInstallation();
require_once 'layout.php';

// 检查登录状态
if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

// 获取统计数据
$pdo = getDatabase();

// 获取基本统计
$stats = [];
$stats['categories'] = $pdo->query("SELECT COUNT(*) FROM categories WHERE status = 1")->fetchColumn();
$stats['links'] = $pdo->query("SELECT COUNT(*) FROM links WHERE status = 1")->fetchColumn();
$stats['recommended_links'] = $pdo->query("SELECT COUNT(*) FROM links WHERE status = 1 AND is_recommended = 1")->fetchColumn();
$stats['slides'] = $pdo->query("SELECT COUNT(*) FROM slides WHERE status = 1")->fetchColumn();
$stats['total_visits'] = $pdo->query("SELECT SUM(visits) FROM links")->fetchColumn() ?: 0;

// 获取最近访问的链接
$recent_visits = $pdo->query("
    SELECT l.title, l.url, vs.visit_time, vs.ip_address
    FROM visit_stats vs
    JOIN links l ON vs.link_id = l.id
    ORDER BY vs.visit_time DESC
    LIMIT 10
")->fetchAll();

// 获取热门链接
$popular_links = $pdo->query("
    SELECT title, url, visits
    FROM links
    WHERE status = 1
    ORDER BY visits DESC
    LIMIT 10
")->fetchAll();

ob_start();
?>

<div class="welcome-message" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 8px; margin-bottom: 30px; text-align: center;">
    <h2 style="margin-bottom: 10px; font-size: 28px;">欢迎使用导航站管理系统</h2>
    <p style="font-size: 16px; opacity: 0.9;">这里是您网站的控制中心，您可以管理所有内容和设置</p>
</div>

<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">快速操作</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <a href="categories.php" class="btn btn-primary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">📁</span>
                <span>管理分类</span>
            </a>
            <a href="links.php" class="btn btn-primary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">🔗</span>
                <span>管理链接</span>
            </a>
            <a href="slides.php" class="btn btn-primary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">🖼️</span>
                <span>管理幻灯片</span>
            </a>
            <a href="settings.php" class="btn btn-secondary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">⚙️</span>
                <span>网站设置</span>
            </a>
<!--             <a href="stats.php" class="btn btn-secondary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">📈</span>
                <span>访问统计</span>
            </a>
            <a href="logs.php" class="btn btn-secondary" style="padding: 15px; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                <span style="font-size: 24px;">📋</span>
                <span>系统日志</span>
            </a> -->
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #3498db; margin-bottom: 5px;">
                <?php echo number_format($stats['categories']); ?>
            </div>
            <div style="color: #666; font-size: 14px;">活跃分类</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #27ae60; margin-bottom: 5px;">
                <?php echo number_format($stats['links']); ?>
            </div>
            <div style="color: #666; font-size: 14px;">总链接数</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #f39c12; margin-bottom: 5px;">
                <?php echo number_format($stats['recommended_links']); ?>
            </div>
            <div style="color: #666; font-size: 14px;">推荐链接</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #9b59b6; margin-bottom: 5px;">
                <?php echo number_format($stats['slides']); ?>
            </div>
            <div style="color: #666; font-size: 14px;">幻灯片</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #e74c3c; margin-bottom: 5px;">
                <?php echo number_format($stats['total_visits']); ?>
            </div>
            <div style="color: #666; font-size: 14px;">总访问量</div>
        </div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">最近访问</h3>
        </div>
        <div class="card-body">
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($recent_visits)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">暂无访问记录</p>
                <?php else: ?>
                    <?php foreach ($recent_visits as $visit): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div style="font-weight: 500; color: #2c3e50; margin-bottom: 4px;">
                                    <?php echo h($visit['title']); ?>
                                </div>
                                <div style="font-size: 12px; color: #666;">
                                    <?php echo h($visit['ip_address']); ?> •
                                    <?php echo date('m-d H:i', strtotime($visit['visit_time'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">热门链接</h3>
        </div>
        <div class="card-body">
            <div style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($popular_links)): ?>
                    <p style="color: #666; text-align: center; padding: 20px;">暂无数据</p>
                <?php else: ?>
                    <?php foreach ($popular_links as $link): ?>
                        <div style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                            <div style="font-weight: 500; color: #2c3e50; flex: 1; margin-right: 10px;">
                                <?php echo h($link['title']); ?>
                            </div>
                            <span class="badge badge-success">
                                <?php echo number_format($link['visits']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
renderAdminLayout('控制台', $content, 'dashboard');
?>
