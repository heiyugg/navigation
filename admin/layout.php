<?php
// 检查登录状态
if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

function renderAdminLayout($title, $content, $current_page = '', $additional_head = '', $additional_js = '') {
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($title); ?> - 导航站管理后台</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .admin-layout {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #34495e;
            border-bottom: 1px solid #4a5f7a;
        }
        
        .sidebar-header h2 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .sidebar-header .user-info {
            font-size: 14px;
            opacity: 0.8;
        }
        
        .sidebar-nav {
            padding: 20px 0;
        }
        
        .nav-group {
            margin-bottom: 30px;
        }
        
        .nav-group-title {
            padding: 0 20px 10px;
            font-size: 12px;
            text-transform: uppercase;
            opacity: 0.6;
            font-weight: 600;
        }
        
        .nav-item {
            display: block;
            padding: 12px 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .nav-item:hover {
            background: rgba(255,255,255,0.1);
            border-left-color: #3498db;
        }
        
        .nav-item.active {
            background: rgba(52, 152, 219, 0.2);
            border-left-color: #3498db;
        }
        
        .nav-item i {
            width: 20px;
            margin-right: 10px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            background: #f5f5f5;
        }
        
        .top-bar {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 24px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        .top-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c0392b;
        }
        
        .content-area {
            padding: 30px;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card-header {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .card-body {
            padding: 20px;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        
        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #28a745;
            color: white;
        }
        
        .badge-danger {
            background: #dc3545;
            color: white;
        }
        
        .badge-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .text-center {
            text-align: center;
        }
        
        .text-muted {
            color: #6c757d;
        }
        
        .mb-3 {
            margin-bottom: 1rem;
        }
        
        .mt-3 {
            margin-top: 1rem;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-bar {
                padding: 15px;
            }
            
            .content-area {
                padding: 15px;
            }
        }
    </style>
    <?php echo $additional_head; ?>
</head>
<body>
    <div class="admin-layout">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>导航站管理</h2>
                <div class="user-info">欢迎，<?php echo h($_SESSION['admin_username']); ?></div>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-group">
                    <div class="nav-group-title">主要功能</div>
                    <a href="dashboard.php" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i>📊</i> 控制台
                    </a>
                    <a href="categories.php" class="nav-item <?php echo $current_page === 'categories' ? 'active' : ''; ?>">
                        <i>📁</i> 分类管理
                    </a>
                    <a href="links.php" class="nav-item <?php echo $current_page === 'links' ? 'active' : ''; ?>">
                        <i>🔗</i> 链接管理
                    </a>
                    <a href="applications.php" class="nav-item <?php echo $current_page === 'applications' ? 'active' : ''; ?>">
                        <i>📝</i> 链接申请
                    </a>
                    <a href="slides.php" class="nav-item <?php echo $current_page === 'slides' ? 'active' : ''; ?>">
                        <i>🖼️</i> 幻灯片管理
                    </a>
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">系统设置</div>
                    <a href="site-info.php" class="nav-item <?php echo $current_page === 'site-info' ? 'active' : ''; ?>">
                        <i>🏠</i> 网站信息
                    </a>
                    <a href="settings.php" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                        <i>⚙️</i> 系统设置
                    </a>
                    <a href="stats.php" class="nav-item <?php echo $current_page === 'stats' ? 'active' : ''; ?>">
                        <i>📈</i> 访问统计
                    </a>
                    <a href="backup.php" class="nav-item <?php echo $current_page === 'backup' ? 'active' : ''; ?>">
                        <i>💾</i> 数据备份
                    </a>
<!--                     <a href="logs.php" class="nav-item <?php echo $current_page === 'logs' ? 'active' : ''; ?>">
                        <i>📋</i> 系统日志
                    </a>-->
                </div>
                
                <div class="nav-group">
                    <div class="nav-group-title">其他</div>
                    <a href="../index.php" target="_blank" class="nav-item">
                        <i>🌐</i> 查看网站
                    </a>
                    <a href="logout.php" class="nav-item">
                        <i>🚪</i> 退出登录
                    </a>
                </div>
            </nav>
        </div>
        
        <div class="main-content">
            <div class="top-bar">
                <h1 class="page-title"><?php echo h($title); ?></h1>
                <div class="top-actions">
                    <a href="../index.php" target="_blank" class="btn btn-secondary">查看网站</a>
                    <a href="logout.php" class="btn btn-danger">退出登录</a>
                </div>
            </div>
            
            <div class="content-area">
                <?php echo $content; ?>
            </div>
        </div>
    </div>
    <?php echo $additional_js; ?>
</body>
</html>
<?php
}
?>
