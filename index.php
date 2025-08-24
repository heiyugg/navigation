<?php
session_start();

// 检查是否已安装
if (!file_exists('config/installed.lock')) {
    header('Location: install.php');
    exit;
}

require_once 'config/database.php';
require_once 'includes/functions.php';

// 获取网站配置
$site_config = getSiteConfig();
$categories = getCategories();
$recommended_links = getRecommendedLinks();
$slides = getSlides();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($site_config['site_title'] ?? '导航站'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body>
    <!-- 页头模块 -->
    <?php include 'modules/header.php'; ?>
    
    <!-- 主要内容区域 -->
    <div class="main-container">
        <!-- 左侧分类导航 -->
        <?php include 'modules/sidebar.php'; ?>
        
        <!-- 右侧内容区域 -->
        <div class="content-area">
            <!-- 幻灯片模块 -->
            <?php include 'modules/slideshow.php'; ?>
            
            <!-- 推荐链接模块 -->
            <?php include 'modules/recommended-links.php'; ?>
            
            <!-- 网站主体链接模块 -->
            <?php include 'modules/main-links.php'; ?>
        </div>
    </div>
    
    <!-- 页脚模块 -->
    <?php include 'modules/footer.php'; ?>
    
    <script src="assets/js/main.js"></script>
</body>
</html>
