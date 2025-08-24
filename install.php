<?php
/**
 * 导航站一键安装程序 - 简化版
 * 专注于核心功能，确保管理员账户创建成功
 */

// 防止重复安装 - 如果已安装则跳转到首页
if (file_exists('config/installed.lock')) {
    header('Location: index.php');
    exit;
}

// 错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 安装步骤
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// 启动session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 处理安装步骤
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 2:
            // 数据库连接测试
            $result = testDatabase($_POST);
            if ($result['success']) {
                $_SESSION['db_config'] = $_POST;
                header('Location: install.php?step=3');
                exit;
            } else {
                $error = $result['error'];
            }
            break;
            
        case 3:
            // 执行安装
            if (!isset($_SESSION['db_config'])) {
                $error = '数据库配置丢失，请重新配置。';
                $step = 2;
                break;
            }
            
            $result = performInstall($_SESSION['db_config'], $_POST);
            if ($result['success']) {
                unset($_SESSION['db_config']);
                header('Location: install.php?step=4');
                exit;
            } else {
                $error = $result['error'];
            }
            break;
    }
}

/**
 * 测试数据库连接
 */
function testDatabase($config) {
    try {
        $host = $config['db_host'] ?: 'localhost';
        $username = $config['db_username'] ?: '';
        $password = $config['db_password'] ?: '';
        $database = $config['db_name'] ?: 'navigation_site';
        
        if (empty($username)) {
            return ['success' => false, 'error' => '请填写数据库用户名'];
        }
        
        // 连接数据库
        $dsn = "mysql:host={$host};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // 创建数据库
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        
        return ['success' => true];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => '数据库连接失败: ' . $e->getMessage()];
    }
}

/**
 * 执行安装
 */
function performInstall($db_config, $site_config) {
    try {
        // 连接数据库
        $host = $db_config['db_host'] ?: 'localhost';
        $username = $db_config['db_username'];
        $password = $db_config['db_password'] ?: '';
        $database = $db_config['db_name'] ?: 'navigation_site';
        
        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // 1. 创建基础表结构
        createTables($pdo);
        
        // 2. 创建管理员账户 - 使用最简单可靠的方法
        createAdmin($pdo, $site_config);
        
        // 3. 插入基础数据
        insertBasicData($pdo, $site_config);
        
        // 4. 保存数据库配置
        saveDatabaseConfig($db_config);
        
        // 5. 创建安装锁定文件
        file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
        
        return ['success' => true];
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * 创建数据表
 */
function createTables($pdo) {
    // 管理员用户表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `admin_users` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `password` varchar(255) NOT NULL,
        `email` varchar(100) NOT NULL,
        `last_login` datetime DEFAULT NULL,
        `login_count` int(11) DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 网站配置表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `site_config` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `site_title` varchar(255) NOT NULL DEFAULT '导航站',
        `site_logo` varchar(500) DEFAULT NULL,
        `site_description` text,
        `copyright` varchar(255) DEFAULT '© 2024 导航站',
        `icp_number` varchar(100) DEFAULT NULL COMMENT 'ICP备案号',
        `police_number` varchar(100) DEFAULT NULL COMMENT '公安备案号',
        `site_start_date` DATE DEFAULT NULL COMMENT '网站建站时间',
        `footer_links` text,
        `install_time` datetime NOT NULL,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 分类表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text,
        `icon` text DEFAULT NULL,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 链接表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `links` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `category_id` int(11) NOT NULL,
        `title` varchar(255) NOT NULL,
        `url` varchar(500) NOT NULL,
        `description` text,
        `icon` text DEFAULT NULL,
        `target` varchar(20) DEFAULT '_blank',
        `is_recommended` tinyint(1) NOT NULL DEFAULT 0,
        `visits` int(11) NOT NULL DEFAULT 0,
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `category_id` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 幻灯片表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `slides` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) DEFAULT NULL,
        `description` text,
        `image` text DEFAULT NULL,
        `link_url` varchar(500) DEFAULT NULL,
        `link_text` varchar(100) DEFAULT NULL,
        `link_target` varchar(20) DEFAULT '_blank',
        `sort_order` int(11) NOT NULL DEFAULT 0,
        `status` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 访问统计表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `visit_stats` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `link_id` int(11) NOT NULL,
        `ip_address` varchar(45) NOT NULL,
        `user_agent` text,
        `referer` varchar(500) DEFAULT NULL,
        `visit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `link_id` (`link_id`),
        KEY `visit_time` (`visit_time`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 系统日志表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `system_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `admin_id` int(11) DEFAULT NULL,
        `action` varchar(100) NOT NULL,
        `description` text,
        `ip_address` varchar(45) NOT NULL,
        `user_agent` text,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `admin_id` (`admin_id`),
        KEY `action` (`action`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 设置表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text,
        `setting_type` varchar(50) DEFAULT 'text',
        `description` varchar(255) DEFAULT NULL,
        `group_name` varchar(50) DEFAULT 'general',
        `sort_order` int(11) DEFAULT 0,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    // 链接申请表
    $pdo->exec("CREATE TABLE IF NOT EXISTS `link_applications` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `title` varchar(255) NOT NULL COMMENT '链接标题',
        `url` varchar(500) NOT NULL COMMENT '链接地址',
        `description` text COMMENT '链接描述',
        `category_id` int(11) DEFAULT NULL COMMENT '申请的分类ID',
        `icon` text COMMENT '图标',
        `applicant_name` varchar(100) DEFAULT NULL COMMENT '申请人姓名',
        `applicant_email` varchar(255) DEFAULT NULL COMMENT '申请人邮箱',
        `applicant_contact` varchar(100) DEFAULT NULL COMMENT '联系方式',
        `reason` text COMMENT '申请理由',
        `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '状态：0=待审核，1=已通过，2=已拒绝',
        `admin_note` text COMMENT '管理员备注',
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '申请时间',
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
        `processed_at` timestamp NULL DEFAULT NULL COMMENT '处理时间',
        `processed_by` varchar(100) DEFAULT NULL COMMENT '处理人',
        PRIMARY KEY (`id`),
        KEY `idx_status` (`status`),
        KEY `idx_category_id` (`category_id`),
        KEY `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='链接申请表'");
}

/**
 * 创建管理员账户 - 简化版本，确保成功
 */
function createAdmin($pdo, $config) {
    $username = $config['admin_username'] ?: 'admin';
    $password = $config['admin_password'] ?: '';
    $email = $config['admin_email'] ?: ($username . '@example.com');
    
    if (empty($password)) {
        throw new Exception('管理员密码不能为空');
    }
    
    // 删除可能存在的同名账户
    $pdo->prepare("DELETE FROM admin_users WHERE username = ? OR email = ?")->execute([$username, $email]);
    
    // 创建管理员账户
    $stmt = $pdo->prepare("INSERT INTO admin_users (username, password, email, status, created_at) VALUES (?, ?, ?, 1, NOW())");
    $result = $stmt->execute([
        $username,
        password_hash($password, PASSWORD_DEFAULT),
        $email
    ]);
    
    if (!$result) {
        throw new Exception('管理员账户创建失败');
    }
    
    // 验证创建结果
    $stmt = $pdo->prepare("SELECT id FROM admin_users WHERE username = ?");
    $stmt->execute([$username]);
    if (!$stmt->fetch()) {
        throw new Exception('管理员账户创建验证失败');
    }
}

/**
 * 插入基础数据
 */
function insertBasicData($pdo, $config) {
    // 插入网站配置
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM site_config");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $stmt = $pdo->prepare("INSERT INTO site_config (site_title, site_description, copyright, install_time) VALUES (?, ?, ?, ?)");
        $stmt->execute([
            $config['site_title'] ?: '我的导航站',
            $config['site_description'] ?: '一个简洁实用的网址导航站',
            $config['copyright'] ?: '© 2024 导航站',
            date('Y-m-d H:i:s')
        ]);
    }
    
    // 插入示例分类
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM categories");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $categories = [
            ['搜索引擎', '常用搜索引擎', 1],
            ['社交媒体', '社交网络平台', 2],
            ['开发工具', '程序开发相关', 3],
            ['在线工具', '实用在线工具', 4],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, description, sort_order, status) VALUES (?, ?, ?, 1)");
        foreach ($categories as $cat) {
            $stmt->execute($cat);
        }
    }
    
    // 插入示例链接
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links");
    $stmt->execute();
    if ($stmt->fetchColumn() == 0) {
        $links = [
            [1, '百度', 'https://www.baidu.com', '百度搜索', 1, 1],
            [1, '谷歌', 'https://www.google.com', '谷歌搜索', 1, 2],
            [2, '微博', 'https://weibo.com', '新浪微博', 0, 1],
            [3, 'GitHub', 'https://github.com', '代码托管平台', 1, 1],
        ];
        
        $stmt = $pdo->prepare("INSERT INTO links (category_id, title, url, description, is_recommended, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, 1)");
        foreach ($links as $link) {
            $stmt->execute($link);
        }
    }
}

/**
 * 保存数据库配置
 */
function saveDatabaseConfig($config) {
    $host = $config['db_host'] ?: 'localhost';
    $username = $config['db_username'];
    $password = $config['db_password'] ?: '';
    $database = $config['db_name'] ?: 'navigation_site';
    
    $configContent = "<?php
\$db_config = [
    'host' => '{$host}',
    'username' => '{$username}',
    'password' => '{$password}',
    'database' => '{$database}',
    'charset' => 'utf8mb4'
];

\$pdo = null;

function getDatabase() {
    global \$pdo, \$db_config;
    
    if (\$pdo === null) {
        try {
            \$dsn = \"mysql:host={\$db_config['host']};dbname={\$db_config['database']};charset={\$db_config['charset']}\";
            \$pdo = new PDO(\$dsn, \$db_config['username'], \$db_config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException \$e) {
            die('数据库连接失败: ' . \$e->getMessage());
        }
    }
    
    return \$pdo;
}
?>";
    
    file_put_contents('config/database.php', $configContent);
}

/**
 * 检查系统环境
 */
function checkSystem() {
    return [
        'PHP版本 >= 7.4' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'PDO扩展' => extension_loaded('pdo'),
        'PDO MySQL扩展' => extension_loaded('pdo_mysql'),
        'config目录可写' => is_writable('config'),
    ];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>导航站安装程序</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .content {
            padding: 40px;
        }
        .steps {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
        }
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ecf0f1;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
            color: #7f8c8d;
        }
        .step.active { background: #3498db; color: white; }
        .step.completed { background: #27ae60; color: white; }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #ecf0f1;
            border-radius: 6px;
            font-size: 16px;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert-error { background: #e74c3c; color: white; }
        .alert-success { background: #27ae60; color: white; }
        .requirements li {
            padding: 10px 0;
            display: flex;
            justify-content: space-between;
        }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-error { color: #e74c3c; font-weight: bold; }
        .text-center { text-align: center; }
        .mt-20 { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>导航站安装程序</h1>
            <p>简洁版本 - 专注核心功能</p>
        </div>
        
        <div class="content">
            <div class="steps">
                <div class="step <?php echo $step >= 1 ? ($step > 1 ? 'completed' : 'active') : ''; ?>">1</div>
                <div class="step <?php echo $step >= 2 ? ($step > 2 ? 'completed' : 'active') : ''; ?>">2</div>
                <div class="step <?php echo $step >= 3 ? ($step > 3 ? 'completed' : 'active') : ''; ?>">3</div>
                <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">4</div>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($step == 1): ?>
                <h2>步骤 1: 环境检查</h2>
                <ul class="requirements">
                    <?php 
                    $requirements = checkSystem();
                    $allPassed = true;
                    foreach ($requirements as $name => $passed): 
                        if (!$passed) $allPassed = false;
                    ?>
                        <li>
                            <span><?php echo $name; ?></span>
                            <span class="<?php echo $passed ? 'status-ok' : 'status-error'; ?>">
                                <?php echo $passed ? '✓ 通过' : '✗ 失败'; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <div class="text-center mt-20">
                    <?php if ($allPassed): ?>
                        <a href="?step=2" class="btn">下一步</a>
                    <?php else: ?>
                        <p style="color: #e74c3c;">请解决上述问题后重新检查</p>
                        <a href="?step=1" class="btn">重新检查</a>
                    <?php endif; ?>
                </div>
                
            <?php elseif ($step == 2): ?>
                <h2>步骤 2: 数据库配置</h2>
                <form method="post">
                    <div class="form-group">
                        <label>数据库主机</label>
                        <input type="text" name="db_host" value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>数据库名称</label>
                        <input type="text" name="db_name" value="<?php echo $_POST['db_name'] ?? 'navigation_site'; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>数据库用户名</label>
                        <input type="text" name="db_username" value="<?php echo $_POST['db_username'] ?? ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label>数据库密码</label>
                        <input type="password" name="db_password" value="<?php echo $_POST['db_password'] ?? ''; ?>">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn">测试连接</button>
                    </div>
                </form>
                
            <?php elseif ($step == 3): ?>
                <h2>步骤 3: 网站配置</h2>
                <form method="post">
                    <div class="form-group">
                        <label>网站标题</label>
                        <input type="text" name="site_title" value="我的导航站" required>
                    </div>
                    <div class="form-group">
                        <label>网站描述</label>
                        <textarea name="site_description" rows="3">一个简洁实用的网址导航站</textarea>
                    </div>
                    <div class="form-group">
                        <label>版权信息</label>
                        <input type="text" name="copyright" value="© 2024 导航站" required>
                    </div>
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_username" value="admin" required>
                    </div>
                    <div class="form-group">
                        <label>管理员密码</label>
                        <input type="password" name="admin_password" required>
                    </div>
                    <div class="form-group">
                        <label>管理员邮箱</label>
                        <input type="email" name="admin_email" placeholder="admin@example.com">
                    </div>
                    <div class="text-center">
                        <button type="submit" class="btn btn-success">开始安装</button>
                    </div>
                </form>
                
            <?php elseif ($step == 4): ?>
                <div class="text-center">
                    <h2>🎉 安装完成！</h2>
                    <p>导航站已经成功安装！</p>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin: 20px 0; text-align: left;">
                        <h3>重要提醒：</h3>
                        <ul style="margin-left: 20px;">
                            <li>请立即删除 install.php 文件</li>
                            <li>请妥善保管管理员账户信息</li>
                            <li>建议定期备份数据库</li>
                        </ul>
                    </div>
                    
                    <div class="mt-20">
                        <a href="index.php" class="btn">访问首页</a>
                        <a href="admin/" class="btn btn-success">进入后台</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
