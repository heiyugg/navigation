-- 导航站数据库结构
-- 创建时间: 2024-01-01
-- 版本: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------

--
-- 网站配置表
--

CREATE TABLE `site_config` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 管理员用户表
--

CREATE TABLE `admin_users` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 分类表
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` text,
  `icon` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `is_hidden` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`),
  KEY `status` (`status`),
  KEY `is_hidden` (`is_hidden`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 链接表
--

CREATE TABLE `links` (
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
  KEY `category_id` (`category_id`),
  KEY `is_recommended` (`is_recommended`),
  KEY `sort_order` (`sort_order`),
  KEY `status` (`status`),
  KEY `visits` (`visits`),
  CONSTRAINT `links_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 幻灯片表
--

CREATE TABLE `slides` (
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
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 访问统计表
--

CREATE TABLE `visit_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `link_id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text,
  `referer` varchar(500) DEFAULT NULL,
  `visit_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `link_id` (`link_id`),
  KEY `visit_time` (`visit_time`),
  KEY `ip_address` (`ip_address`),
  CONSTRAINT `visit_stats_ibfk_1` FOREIGN KEY (`link_id`) REFERENCES `links` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 链接申请表
--

CREATE TABLE `link_applications` (
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
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `link_applications_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='链接申请表';

-- --------------------------------------------------------

--
-- 系统日志表
--

CREATE TABLE `system_logs` (
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
  KEY `created_at` (`created_at`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`admin_id`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 友情链接表
--

CREATE TABLE `friend_links` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `url` varchar(500) NOT NULL,
  `description` text,
  `logo` text DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `sort_order` (`sort_order`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 搜索关键词统计表
--

CREATE TABLE `search_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL,
  `search_engine` varchar(50) NOT NULL DEFAULT 'baidu',
  `search_count` int(11) NOT NULL DEFAULT 1,
  `ip_address` varchar(45) NOT NULL,
  `last_search` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `keyword` (`keyword`),
  KEY `search_engine` (`search_engine`),
  KEY `search_count` (`search_count`),
  KEY `last_search` (`last_search`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 网站设置表（扩展配置）
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(50) DEFAULT 'text',
  `description` varchar(255) DEFAULT NULL,
  `group_name` varchar(50) DEFAULT 'general',
  `sort_order` int(11) DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`),
  KEY `group_name` (`group_name`),
  KEY `sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- 管理员账户增强功能
-- 简化版本，避免存储过程兼容性问题
--

-- 创建管理员账户状态检查视图
CREATE OR REPLACE VIEW `admin_account_status` AS
SELECT 
    COUNT(*) as total_accounts,
    SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_accounts,
    SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_accounts,
    MAX(created_at) as latest_account_created,
    MAX(last_login) as latest_login
FROM admin_users;

-- --------------------------------------------------------

--
-- 注意：管理员账户将在安装过程中通过 install.php 创建
-- 不在此处插入默认账户，确保用户可以自定义管理员信息
--

-- --------------------------------------------------------

--
-- 插入默认设置数据
--

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `group_name`, `sort_order`) VALUES
('site_maintenance', '0', 'boolean', '网站维护模式', 'general', 1),
('allow_registration', '0', 'boolean', '允许用户注册', 'general', 2),
('default_search_engine', 'baidu', 'select', '默认搜索引擎', 'general', 3),
('links_per_page', '20', 'number', '每页显示链接数', 'general', 4),
('enable_visit_stats', '1', 'boolean', '启用访问统计', 'stats', 1),
('enable_search_stats', '1', 'boolean', '启用搜索统计', 'stats', 2),
('cache_enabled', '1', 'boolean', '启用缓存', 'performance', 1),
('cache_time', '3600', 'number', '缓存时间（秒）', 'performance', 2),
('admin_session_timeout', '3600', 'number', '管理员会话超时时间（秒）', 'security', 1),
('admin_login_attempts', '5', 'number', '管理员登录最大尝试次数', 'security', 2),
('admin_password_min_length', '6', 'number', '管理员密码最小长度', 'security', 3),
('admin_require_strong_password', '0', 'boolean', '要求强密码', 'security', 4);

-- --------------------------------------------------------

--
-- 兼容性更新：为现有数据库添加缺失字段
-- 只有在表已存在但字段不存在时才执行
--

-- 检查并添加icp_number字段
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE table_name = 'site_config' AND table_schema = DATABASE());
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'site_config' AND column_name = 'icp_number' AND table_schema = DATABASE());

SET @sql = IF(@table_exists > 0 AND @column_exists = 0,
    'ALTER TABLE `site_config` ADD COLUMN `icp_number` varchar(100) DEFAULT NULL COMMENT ''ICP备案号''',
    'SELECT ''icp_number字段检查完成'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 检查并添加police_number字段
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'site_config' AND column_name = 'police_number' AND table_schema = DATABASE());

SET @sql = IF(@table_exists > 0 AND @column_exists = 0,
    'ALTER TABLE `site_config` ADD COLUMN `police_number` varchar(100) DEFAULT NULL COMMENT ''公安备案号''',
    'SELECT ''police_number字段检查完成'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 插入默认网站配置数据（如果不存在id=1的记录）
INSERT IGNORE INTO `site_config` (`id`, `site_title`, `site_logo`, `site_description`, `copyright`, `icp_number`, `police_number`, `footer_links`, `install_time`) VALUES
(1, '导航站', NULL, '一个简洁实用的网址导航站', '© 2024 导航站', '', '', NULL, NOW());

-- --------------------------------------------------------

--
-- 插入示例分类数据
--

INSERT INTO `categories` (`name`, `description`, `icon`, `sort_order`, `status`, `is_hidden`) VALUES
('搜索引擎', '常用搜索引擎和搜索工具', '🔍', 1, 1, 0),
('社交媒体', '社交网络和通讯平台', '💬', 2, 1, 0),
('开发工具', '编程开发相关工具和资源', '💻', 3, 1, 0),
('在线工具', '实用的在线工具和服务', '🛠️', 4, 1, 0),
('学习资源', '教育学习相关网站', '📚', 5, 1, 0),
('娱乐休闲', '娱乐和休闲相关网站', '🎮', 6, 1, 0),
('新闻资讯', '新闻和资讯类网站', '📰', 7, 1, 0),
('购物网站', '电商和购物平台', '🛒', 8, 1, 0),
('管理员专用', '仅管理员可见的分类', '🔒', 9, 1, 1);

-- --------------------------------------------------------

--
-- 插入示例链接数据
--

INSERT INTO `links` (`category_id`, `title`, `url`, `description`, `icon`, `is_recommended`, `sort_order`, `status`) VALUES
-- 搜索引擎分类
(1, '百度', 'https://www.baidu.com', '中国最大的搜索引擎', '🔍', 1, 1, 1),
(1, '谷歌', 'https://www.google.com', '全球最大的搜索引擎', '🌐', 1, 2, 1),
(1, '必应', 'https://www.bing.com', '微软搜索引擎', '🔎', 0, 3, 1),

-- 社交媒体分类
(2, '微信网页版', 'https://wx.qq.com', '微信网页版登录', '💬', 1, 1, 1),
(2, '微博', 'https://weibo.com', '新浪微博', '📱', 0, 2, 1),
(2, 'QQ空间', 'https://qzone.qq.com', 'QQ空间', '🎭', 0, 3, 1),

-- 开发工具分类
(3, 'GitHub', 'https://github.com', '全球最大的代码托管平台', '💻', 1, 1, 1),
(3, 'Stack Overflow', 'https://stackoverflow.com', '程序员问答社区', '❓', 1, 2, 1),
(3, 'MDN Web Docs', 'https://developer.mozilla.org', 'Web开发文档', '📖', 0, 3, 1),

-- 在线工具分类
(4, '在线PS', 'https://www.photopea.com', '在线图片编辑工具', '🎨', 0, 1, 1),
(4, '文件转换', 'https://convertio.co', '在线文件格式转换', '🔄', 0, 2, 1),
(4, '二维码生成', 'https://qr.net', '在线二维码生成器', '📱', 0, 3, 1),

-- 学习资源分类
(5, '菜鸟教程', 'https://www.runoob.com', '编程学习教程', '📚', 1, 1, 1),
(5, 'Coursera', 'https://www.coursera.org', '在线课程平台', '🎓', 0, 2, 1),
(5, '知乎', 'https://www.zhihu.com', '知识问答社区', '🧠', 0, 3, 1),

-- 管理员专用分类（隐藏）
(9, '服务器监控', 'https://example.com/monitor', '服务器状态监控', '📊', 0, 1, 1),
(9, '数据库管理', 'https://example.com/phpmyadmin', '数据库管理工具', '🗄️', 0, 2, 1);

-- --------------------------------------------------------

--
-- 创建索引以优化查询性能
--

-- 为经常查询的字段创建复合索引
CREATE INDEX `idx_links_category_status` ON `links` (`category_id`, `status`);
CREATE INDEX `idx_links_recommended_status` ON `links` (`is_recommended`, `status`);
CREATE INDEX `idx_categories_status_sort` ON `categories` (`status`, `sort_order`);
CREATE INDEX `idx_categories_status_hidden` ON `categories` (`status`, `is_hidden`);
CREATE INDEX `idx_slides_status_sort` ON `slides` (`status`, `sort_order`);

-- 为拖拽排序功能优化的索引
CREATE INDEX `idx_links_category_sort` ON `links` (`category_id`, `sort_order`);
CREATE INDEX `idx_links_sort_id` ON `links` (`sort_order`, `id`);

-- --------------------------------------------------------

--
-- 创建视图以简化常用查询
--

-- 活跃链接视图（不包含隐藏分类）
CREATE VIEW `active_links` AS
SELECT
    l.*,
    c.name as category_name,
    c.description as category_description,
    c.is_hidden as category_is_hidden
FROM `links` l
JOIN `categories` c ON l.category_id = c.id
WHERE l.status = 1 AND c.status = 1
ORDER BY l.sort_order ASC, l.id ASC;

-- 推荐链接视图（不包含隐藏分类）
CREATE VIEW `recommended_links` AS
SELECT
    l.*,
    c.name as category_name,
    c.is_hidden as category_is_hidden
FROM `links` l
JOIN `categories` c ON l.category_id = c.id
WHERE l.status = 1 AND c.status = 1 AND l.is_recommended = 1
ORDER BY l.sort_order ASC, l.id ASC;

-- 公开分类视图（不包含隐藏分类）
CREATE VIEW `public_categories` AS
SELECT *
FROM `categories`
WHERE status = 1 AND is_hidden = 0
ORDER BY sort_order ASC, id ASC;

-- 公开链接视图（不包含隐藏分类中的链接）
CREATE VIEW `public_links` AS
SELECT
    l.*,
    c.name as category_name,
    c.description as category_description
FROM `links` l
JOIN `categories` c ON l.category_id = c.id
WHERE l.status = 1 AND c.status = 1 AND c.is_hidden = 0
ORDER BY l.sort_order ASC, l.id ASC;

-- 公开推荐链接视图（不包含隐藏分类中的推荐链接）
CREATE VIEW `public_recommended_links` AS
SELECT
    l.*,
    c.name as category_name
FROM `links` l
JOIN `categories` c ON l.category_id = c.id
WHERE l.status = 1 AND c.status = 1 AND c.is_hidden = 0 AND l.is_recommended = 1
ORDER BY l.sort_order ASC, l.id ASC;

-- 活跃幻灯片视图
CREATE VIEW `active_slides` AS
SELECT *
FROM `slides`
WHERE status = 1
ORDER BY sort_order ASC, id ASC;

-- --------------------------------------------------------

--
-- 创建存储过程
--

DELIMITER $$

-- 更新链接访问次数的存储过程
CREATE PROCEDURE `UpdateLinkVisit`(
    IN p_link_id INT,
    IN p_ip_address VARCHAR(45),
    IN p_user_agent TEXT,
    IN p_referer VARCHAR(500)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- 更新链接访问次数
    UPDATE `links` SET `visits` = `visits` + 1 WHERE `id` = p_link_id;
    
    -- 记录访问统计
    INSERT INTO `visit_stats` (`link_id`, `ip_address`, `user_agent`, `referer`)
    VALUES (p_link_id, p_ip_address, p_user_agent, p_referer);
    
    COMMIT;
END$$

-- 获取热门链接的存储过程
CREATE PROCEDURE `GetPopularLinks`(
    IN p_limit INT DEFAULT 10,
    IN p_days INT DEFAULT 30
)
BEGIN
    SELECT 
        l.id,
        l.title,
        l.url,
        l.description,
        l.icon,
        COUNT(vs.id) as recent_visits,
        l.visits as total_visits
    FROM `links` l
    LEFT JOIN `visit_stats` vs ON l.id = vs.link_id 
        AND vs.visit_time >= DATE_SUB(NOW(), INTERVAL p_days DAY)
    WHERE l.status = 1
    GROUP BY l.id
    ORDER BY recent_visits DESC, total_visits DESC
    LIMIT p_limit;
END$$

-- 重新排序链接的存储过程（用于拖拽排序功能）
CREATE PROCEDURE `ReorderLinks`(IN category_filter INT)
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE link_id INT;
    DECLARE new_order INT DEFAULT 1;
    
    -- 声明游标
    DECLARE link_cursor CURSOR FOR 
        SELECT id FROM links 
        WHERE (category_filter = 0 OR category_id = category_filter)
        ORDER BY sort_order ASC, id ASC;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- 开始事务
    START TRANSACTION;
    
    -- 打开游标
    OPEN link_cursor;
    
    -- 循环处理
    read_loop: LOOP
        FETCH link_cursor INTO link_id;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 更新排序
        UPDATE links SET sort_order = new_order WHERE id = link_id;
        SET new_order = new_order + 1;
    END LOOP;
    
    -- 关闭游标
    CLOSE link_cursor;
    
    -- 提交事务
    COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- 创建触发器
--

-- 删除分类时自动删除相关链接的触发器已通过外键约束实现

-- 记录管理员操作日志的触发器
DELIMITER $$

CREATE TRIGGER `log_category_changes` AFTER UPDATE ON `categories`
FOR EACH ROW
BEGIN
    IF OLD.name != NEW.name OR OLD.status != NEW.status THEN
        INSERT INTO `system_logs` (`action`, `description`, `ip_address`, `user_agent`)
        VALUES (
            'category_update',
            CONCAT('分类 "', OLD.name, '" 被修改'),
            COALESCE(@admin_ip, '127.0.0.1'),
            COALESCE(@admin_user_agent, 'System')
        );
    END IF;
END$$

CREATE TRIGGER `log_link_changes` AFTER UPDATE ON `links`
FOR EACH ROW
BEGIN
    IF OLD.title != NEW.title OR OLD.url != NEW.url OR OLD.status != NEW.status THEN
        INSERT INTO `system_logs` (`action`, `description`, `ip_address`, `user_agent`)
        VALUES (
            'link_update',
            CONCAT('链接 "', OLD.title, '" 被修改'),
            COALESCE(@admin_ip, '127.0.0.1'),
            COALESCE(@admin_user_agent, 'System')
        );
    END IF;
END$$

DELIMITER ;

COMMIT;
