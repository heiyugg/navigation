<?php
/**
 * 核心功能函数
 */

/**
 * 检查系统是否已安装
 */
function checkInstallation() {
    if (!file_exists(__DIR__ . '/../config/installed.lock')) {
        // 获取当前脚本相对于根目录的路径
        $currentPath = $_SERVER['PHP_SELF'];
        $rootPath = '';
        
        // 如果在admin目录下，需要返回上级目录
        if (strpos($currentPath, '/admin/') !== false) {
            $rootPath = '../';
        }
        
        header('Location: ' . $rootPath . 'install.php');
        exit;
    }
}

/**
 * 获取网站配置
 */
function getSiteConfig() {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT * FROM site_config LIMIT 1");
    $config = $stmt->fetch();
    
    if (!$config) {
        return [
            'site_title' => '导航站',
            'site_logo' => '',
            'site_description' => '简洁的导航站',
            'copyright' => '© 2024 导航站',
            'icp_number' => '',
            'police_number' => '',
            'install_time' => date('Y-m-d H:i:s')
        ];
    }
    
    return $config;
}

/**
 * 获取所有分类
 */
function getCategories($include_hidden = null) {
    $pdo = getDatabase();
    
    // 如果没有明确指定是否包含隐藏分类，则根据登录状态决定
    if ($include_hidden === null) {
        $include_hidden = isAdminLoggedIn();
    }
    
    if ($include_hidden) {
        // 管理员登录时，显示所有启用的分类（包括隐藏分类）
        $stmt = $pdo->query("SELECT * FROM categories WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    } else {
        // 普通用户只能看到公开分类
        $stmt = $pdo->query("SELECT * FROM categories WHERE status = 1 AND is_hidden = 0 ORDER BY sort_order ASC, id ASC");
    }
    
    return $stmt->fetchAll();
}

/**
 * 获取推荐链接
 */
function getRecommendedLinks($limit = 5, $include_hidden = null) {
    $pdo = getDatabase();
    
    // 如果没有明确指定是否包含隐藏分类，则根据登录状态决定
    if ($include_hidden === null) {
        $include_hidden = isAdminLoggedIn();
    }
    
    if ($include_hidden) {
        // 管理员登录时，显示所有推荐链接（包括隐藏分类中的）
        $stmt = $pdo->prepare("
            SELECT l.*, c.name as category_name, c.is_hidden as category_is_hidden
            FROM links l
            JOIN categories c ON l.category_id = c.id
            WHERE l.is_recommended = 1 AND l.status = 1 AND c.status = 1
            ORDER BY l.sort_order ASC, l.id ASC
            LIMIT ?
        ");
    } else {
        // 普通用户只能看到公开分类中的推荐链接
        $stmt = $pdo->prepare("
            SELECT l.*, c.name as category_name, c.is_hidden as category_is_hidden
            FROM links l
            JOIN categories c ON l.category_id = c.id
            WHERE l.is_recommended = 1 AND l.status = 1 AND c.status = 1 AND c.is_hidden = 0
            ORDER BY l.sort_order ASC, l.id ASC
            LIMIT ?
        ");
    }
    
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * 获取幻灯片
 */
function getSlides() {
    $pdo = getDatabase();
    $stmt = $pdo->query("SELECT * FROM slides WHERE status = 1 ORDER BY sort_order ASC, id ASC");
    return $stmt->fetchAll();
}

/**
 * 根据分类获取链接
 */
function getLinksByCategory($category_id, $limit = null, $include_hidden = null) {
    $pdo = getDatabase();
    
    // 如果没有明确指定是否包含隐藏分类，则根据登录状态决定
    if ($include_hidden === null) {
        $include_hidden = isAdminLoggedIn();
    }
    
    if ($include_hidden) {
        // 管理员登录时，显示所有链接（包括隐藏分类中的）
        $sql = "SELECT * FROM links WHERE category_id = ? AND status = 1 ORDER BY sort_order ASC, id ASC";
    } else {
        // 普通用户只能看到公开分类中的链接
        $sql = "
            SELECT l.* FROM links l
            JOIN categories c ON l.category_id = c.id
            WHERE l.category_id = ? AND l.status = 1 AND c.status = 1 AND c.is_hidden = 0
            ORDER BY l.sort_order ASC, l.id ASC
        ";
    }
    
    if ($limit) {
        $sql .= " LIMIT ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id, $limit]);
    } else {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$category_id]);
    }
    return $stmt->fetchAll();
}

/**
 * 获取所有链接（分组）
 */
function getAllLinksGrouped($include_hidden = null) {
    // 如果没有明确指定是否包含隐藏分类，则根据登录状态决定
    if ($include_hidden === null) {
        $include_hidden = isAdminLoggedIn();
    }
    
    $categories = getCategories($include_hidden);
    $result = [];
    
    foreach ($categories as $category) {
        $links = getLinksByCategory($category['id'], null, $include_hidden);
        if (!empty($links)) {
            $result[] = [
                'category' => $category,
                'links' => $links
            ];
        }
    }
    
    return $result;
}

/**
 * 安全输出HTML
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * 检查管理员登录状态
 */
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * 管理员登录验证
 */
function adminLogin($username, $password) {
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    
    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return true;
    }
    
    return false;
}

/**
 * 管理员退出登录
 */
function adminLogout() {
    unset($_SESSION['admin_logged_in']);
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
}

/**
 * 计算网站运行时间
 */
function getRunningTime() {
    $pdo = getDatabase();
    
    // 优先使用用户设置的建站时间
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_start_date'");
    $stmt->execute();
    $site_start_date = $stmt->fetchColumn();
    
    if ($site_start_date) {
        // 使用用户设置的建站时间
        $start_time = strtotime($site_start_date);
    } else {
        // 如果没有设置建站时间，使用安装时间
        $config = getSiteConfig();
        $start_time = strtotime($config['install_time']);
    }
    
    $current_time = time();
    $diff = $current_time - $start_time;
    
    $days = floor($diff / (24 * 60 * 60));
    $hours = floor(($diff % (24 * 60 * 60)) / (60 * 60));
    $minutes = floor(($diff % (60 * 60)) / 60);
    
    return "{$days}天{$hours}小时{$minutes}分钟";
}

/**
 * 智能图标渲染函数
 * 自动检测图标类型并正确渲染
 */
function renderIcon($icon, $size = '20px', $class = '') {
    if (empty($icon)) {
        return '';
    }
    
    $icon = trim($icon);
    
    // 检测是否为SVG代码
    if (strpos($icon, '<svg') === 0 || strpos($icon, '<?xml') === 0) {
        // SVG图标：直接输出SVG代码，添加样式
        $svg = $icon;
        // 为SVG添加样式属性
        if (strpos($svg, 'style=') === false && strpos($svg, 'width=') === false) {
            $svg = str_replace('<svg', '<svg style="width: ' . $size . '; height: ' . $size . '; vertical-align: middle;"', $svg);
        }
        if (!empty($class)) {
            $svg = str_replace('<svg', '<svg class="' . h($class) . '"', $svg);
        }
        return $svg;
    }
    
    // 检测是否为Font Awesome类名
    if (strpos($icon, 'fa-') !== false || strpos($icon, 'fas ') === 0 || strpos($icon, 'far ') === 0 || strpos($icon, 'fab ') === 0) {
        // Font Awesome图标
        $iconClass = h($icon);
        if (!empty($class)) {
            $iconClass .= ' ' . h($class);
        }
        return '<i class="' . $iconClass . '" style="font-size: ' . $size . '; vertical-align: middle;"></i>';
    }
    
    // 检测是否为Emoji或其他Unicode字符
    if (mb_strlen($icon, 'UTF-8') <= 4 && preg_match('/[\x{1F000}-\x{1F9FF}]|[\x{2600}-\x{26FF}]|[\x{2700}-\x{27BF}]/u', $icon)) {
        // Emoji表情
        $emojiClass = !empty($class) ? ' class="' . h($class) . '"' : '';
        return '<span' . $emojiClass . ' style="font-size: ' . $size . '; vertical-align: middle; display: inline-block;">' . h($icon) . '</span>';
    }
    
    // 检测是否为图片URL
    if (filter_var($icon, FILTER_VALIDATE_URL) || strpos($icon, '/') !== false) {
        // 图片URL
        $imgClass = !empty($class) ? ' class="' . h($class) . '"' : '';
        return '<img src="' . h($icon) . '" alt="icon"' . $imgClass . ' style="width: ' . $size . '; height: ' . $size . '; vertical-align: middle; object-fit: contain;">';
    }
    
    // 默认情况：作为文本或其他类名处理
    if (strlen($icon) <= 10) {
        // 短文本，可能是单个字符或简短类名
        $textClass = !empty($class) ? ' class="' . h($class) . '"' : '';
        return '<span' . $textClass . ' style="font-size: ' . $size . '; vertical-align: middle; display: inline-block;">' . h($icon) . '</span>';
    } else {
        // 长文本，作为CSS类名处理
        $iconClass = h($icon);
        if (!empty($class)) {
            $iconClass .= ' ' . h($class);
        }
        return '<i class="' . $iconClass . '" style="font-size: ' . $size . '; vertical-align: middle;"></i>';
    }
}

/**
 * 渲染分类图标
 * 专门用于分类图标的渲染，带有默认样式
 */
function renderCategoryIcon($icon, $size = '20px') {
    return renderIcon($icon, $size, 'category-icon');
}

/**
 * 渲染链接图标
 * 专门用于链接图标的渲染，带有默认样式
 */
function renderLinkIcon($icon, $size = '24px') {
    return renderIcon($icon, $size, 'link-icon');
}
?>
