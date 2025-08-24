<header class="site-header">
    <div class="header-container">
        <!-- Logo区域 -->
        <div class="logo-section">
            <a href="index.php" class="logo-link">
                <?php if (!empty($site_config['site_logo'])): ?>
                    <img src="<?php echo h($site_config['site_logo']); ?>" alt="<?php echo h($site_config['site_title']); ?>" class="site-logo">
                <?php else: ?>
                    <div class="site-logo-text"><?php echo h($site_config['site_title']); ?></div>
                <?php endif; ?>
            </a>
        </div>
        
        <!-- 搜索框区域 -->
        <div class="search-section">
            <div class="search-container">
                <select class="search-engine-select" id="searchEngineSelect">
                    <option value="baidu" data-url="https://www.baidu.com/s" data-param="wd">百度</option>
                    <option value="google" data-url="https://www.google.com/search" data-param="q" selected>Google</option>
                    <option value="bing" data-url="https://www.bing.com/search" data-param="q">必应</option>
                </select>
                <form class="search-form" action="https://www.google.com/search" method="get" target="_blank">
                    <input type="text" name="q" class="search-input" placeholder="Google 搜索" autocomplete="off">
                    <button type="submit" class="search-btn">搜索</button>
                </form>
            </div>
        </div>
        
        <!-- 管理员登录区域 -->
        <div class="admin-section">
            <?php if (isAdminLoggedIn()): ?>
                <div class="admin-info">
                    <span>欢迎，<?php echo h($_SESSION['admin_username']); ?></span>
                    <a href="admin/" class="admin-link">管理后台</a>
                    <a href="admin/logout.php" class="logout-link">退出</a>
                </div>
            <?php else: ?>
                <div class="admin-login">
                    <a href="admin/" class="admin-link">管理后台</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</header>
