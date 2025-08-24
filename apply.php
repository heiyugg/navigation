<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// 检查系统是否已安装
checkInstallation();

$pdo = getDatabase();
$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $icon = trim($_POST['icon'] ?? '');
    $applicant_name = trim($_POST['applicant_name'] ?? '');
    $applicant_email = trim($_POST['applicant_email'] ?? '');
    $applicant_contact = trim($_POST['applicant_contact'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    
    // 验证必填字段
    if (empty($title)) {
        $error = '请输入链接标题';
    } elseif (empty($url)) {
        $error = '请输入链接地址';
    } elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
        $error = '请输入有效的链接地址';
    } elseif ($category_id <= 0) {
        $error = '请选择分类';
    } elseif (empty($applicant_name)) {
        $error = '请输入您的姓名';
    } elseif (empty($applicant_email)) {
        $error = '请输入您的邮箱';
    } elseif (!filter_var($applicant_email, FILTER_VALIDATE_EMAIL)) {
        $error = '请输入有效的邮箱地址';
    } else {
        try {
            // 检查链接是否已存在
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE url = ?");
            $stmt->execute([$url]);
            $exists_in_links = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM link_applications WHERE url = ? AND status IN (0, 1)");
            $stmt->execute([$url]);
            $exists_in_applications = $stmt->fetchColumn();
            
            if ($exists_in_links > 0) {
                $error = '该链接已存在于网站中';
            } elseif ($exists_in_applications > 0) {
                $error = '该链接已有申请记录，请勿重复提交';
            } else {
                // 插入申请记录
                $stmt = $pdo->prepare("
                    INSERT INTO link_applications 
                    (title, url, description, category_id, icon, applicant_name, applicant_email, applicant_contact, reason, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)
                ");
                $stmt->execute([
                    $title, $url, $description, $category_id, $icon, 
                    $applicant_name, $applicant_email, $applicant_contact, $reason
                ]);
                
                $message = '申请提交成功！我们会尽快审核您的申请。';
                
                // 清空表单数据
                $_POST = [];
            }
        } catch (PDOException $e) {
            $error = '提交失败：' . $e->getMessage();
        }
    }
}

// 获取分类列表（只显示启用的分类）
$categories = $pdo->query("
    SELECT id, name, description, icon 
    FROM categories 
    WHERE status = 1 
    ORDER BY sort_order ASC, id ASC
")->fetchAll();

// 获取网站信息
$site_info = getSiteConfig();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>链接申请 - <?php echo h($site_info['site_title']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .apply-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .apply-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            padding: 40px;
            margin-bottom: 30px;
        }
        
        .apply-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .apply-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .apply-header p {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .form-label.required::after {
            content: ' *';
            color: #e74c3c;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .form-control.error {
            border-color: #e74c3c;
        }
        
        .form-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        
        .category-option {
            position: relative;
        }
        
        .category-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }
        
        .category-label {
            display: flex;
            align-items: center;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            background: #f8f9fa;
        }
        
        .category-option input[type="radio"]:checked + .category-label {
            border-color: #3498db;
            background: #e3f2fd;
        }
        
        .category-icon {
            margin-right: 10px;
            font-size: 20px;
        }
        
        .category-info h4 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .category-info p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #95a5a6;
            color: white;
            margin-right: 15px;
        }
        
        .btn-secondary:hover {
            background: #7f8c8d;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
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
        
        .back-link {
            display: inline-flex;
            align-items: center;
            color: #3498db;
            text-decoration: none;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            color: #2980b9;
        }
        
        .back-link::before {
            content: '←';
            margin-right: 8px;
            font-size: 16px;
        }
        
        /* 图标选择器样式 */
        .icon-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .icon-input-group .form-control {
            flex: 1;
        }
        
        .icon-picker-btn {
            padding: 12px 20px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
            transition: background 0.3s;
        }
        
        .icon-picker-btn:hover {
            background: #2980b9;
        }
        
        .icon-picker {
            position: relative;
            background: white;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            margin-top: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        
        .icon-picker-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .icon-picker-header h4 {
            margin: 0;
            color: #2c3e50;
            font-size: 16px;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #666;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .close-btn:hover {
            background: #f0f0f0;
        }
        
        .icon-picker-tabs {
            display: flex;
            border-bottom: 1px solid #e1e8ed;
        }
        
        .tab-btn {
            flex: 1;
            padding: 15px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            color: #666;
            transition: all 0.3s;
        }
        
        .tab-btn.active {
            color: #3498db;
            background: #f8f9fa;
            border-bottom: 2px solid #3498db;
        }
        
        .tab-btn:hover {
            background: #f8f9fa;
        }
        
        .icon-picker-content {
            padding: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .icon-grid {
            display: none;
            grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
            gap: 10px;
        }
        
        .icon-grid.active {
            display: grid;
        }
        
        .icon-item {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            cursor: pointer;
            font-size: 20px;
            transition: all 0.3s;
            background: white;
        }
        
        .icon-item:hover {
            border-color: #3498db;
            background: #f0f8ff;
            transform: scale(1.1);
        }
        
        .icon-item.fa-icon {
            color: #666;
        }
        
        /* URL获取信息功能样式 */
        .url-input-group {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .url-input-group .form-control {
            flex: 1;
        }
        
        .fetch-info-btn {
            padding: 12px 20px;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            white-space: nowrap;
            transition: background 0.3s;
        }
        
        .fetch-info-btn:hover {
            background: #229954;
        }
        
        .fetch-info-btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
        }
        
        .fetch-status {
            margin-top: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
        }
        
        .fetch-status.loading {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
        }
        
        .fetch-status.success {
            background: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
        }
        
        .fetch-status.error {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
        }
        
        @media (max-width: 768px) {
            .apply-container {
                margin: 20px auto;
                padding: 0 15px;
            }
            
            .apply-card {
                padding: 25px;
            }
            
            .category-grid {
                grid-template-columns: 1fr;
            }
            
            .icon-input-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .icon-picker-btn {
                margin-top: 10px;
            }
            
            .icon-grid {
                grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            }
            
            .icon-item {
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>
    <div class="apply-container">
        <a href="index.php" class="back-link">返回首页</a>
        
        <div class="apply-card">
            <div class="apply-header">
                <h1>链接申请</h1>
                <p>欢迎提交您的优质链接！我们会认真审核每一个申请，通过后将展示在网站首页。</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo h($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo h($error); ?></div>
            <?php endif; ?>
            
            <form method="post" action="">
                <div class="form-group">
                    <label class="form-label required">链接标题</label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo h($_POST['title'] ?? ''); ?>" 
                           placeholder="请输入链接标题，如：GitHub" required>
                    <div class="form-text">请输入简洁明了的标题</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">链接地址</label>
                    <div class="url-input-group">
                        <input type="url" name="url" id="url-input" class="form-control" 
                               value="<?php echo h($_POST['url'] ?? ''); ?>" 
                               placeholder="https://example.com" required>
                        <button type="button" class="fetch-info-btn" onclick="fetchSiteInfo()">获取信息</button>
                    </div>
                    <div class="form-text">请输入完整的网址，包含 http:// 或 https://</div>
                    <div id="fetch-status" class="fetch-status" style="display: none;"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">链接描述</label>
                    <textarea name="description" class="form-control" rows="4" 
                              placeholder="请简要描述这个链接的内容和用途"><?php echo h($_POST['description'] ?? ''); ?></textarea>
                    <div class="form-text">详细的描述有助于用户了解链接内容</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">选择分类</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">请选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                                <?php if ($category['description']): ?>
                                    - <?php echo h($category['description']); ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">请选择最适合的分类</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">图标</label>
                    <div class="icon-input-group">
                        <input type="text" name="icon" id="icon-input" class="form-control" 
                               value="<?php echo h($_POST['icon'] ?? ''); ?>" 
                               placeholder="🌐 或 fas fa-globe 或图片URL">
                        <button type="button" class="icon-picker-btn" onclick="toggleIconPicker()">选择图标</button>
                    </div>
                    <div class="form-text">可以是 Emoji 表情、Font Awesome 图标类名或图片链接</div>
                    
                    <div id="icon-picker" class="icon-picker" style="display: none;">
                        <div class="icon-picker-header">
                            <h4>选择图标</h4>
                            <button type="button" class="close-btn" onclick="toggleIconPicker()">×</button>
                        </div>
                        <div class="icon-picker-tabs">
                            <button type="button" class="tab-btn active" onclick="showIconTab('emoji')">Emoji</button>
                            <button type="button" class="tab-btn" onclick="showIconTab('fontawesome')">Font Awesome</button>
                        </div>
                        <div class="icon-picker-content">
                            <div id="emoji-icons" class="icon-grid active">
                                <div class="icon-item" onclick="selectIcon('🌐')">🌐</div>
                                <div class="icon-item" onclick="selectIcon('🔍')">🔍</div>
                                <div class="icon-item" onclick="selectIcon('💻')">💻</div>
                                <div class="icon-item" onclick="selectIcon('📱')">📱</div>
                                <div class="icon-item" onclick="selectIcon('🛠️')">🛠️</div>
                                <div class="icon-item" onclick="selectIcon('📚')">📚</div>
                                <div class="icon-item" onclick="selectIcon('🎮')">🎮</div>
                                <div class="icon-item" onclick="selectIcon('📰')">📰</div>
                                <div class="icon-item" onclick="selectIcon('🛒')">🛒</div>
                                <div class="icon-item" onclick="selectIcon('💬')">💬</div>
                                <div class="icon-item" onclick="selectIcon('🎨')">🎨</div>
                                <div class="icon-item" onclick="selectIcon('🎵')">🎵</div>
                                <div class="icon-item" onclick="selectIcon('📹')">📹</div>
                                <div class="icon-item" onclick="selectIcon('📊')">📊</div>
                                <div class="icon-item" onclick="selectIcon('🔧')">🔧</div>
                                <div class="icon-item" onclick="selectIcon('⚡')">⚡</div>
                                <div class="icon-item" onclick="selectIcon('🚀')">🚀</div>
                                <div class="icon-item" onclick="selectIcon('💡')">💡</div>
                                <div class="icon-item" onclick="selectIcon('🎯')">🎯</div>
                                <div class="icon-item" onclick="selectIcon('📝')">📝</div>
                                <div class="icon-item" onclick="selectIcon('🔒')">🔒</div>
                                <div class="icon-item" onclick="selectIcon('🌟')">🌟</div>
                                <div class="icon-item" onclick="selectIcon('❤️')">❤️</div>
                                <div class="icon-item" onclick="selectIcon('🏠')">🏠</div>
                            </div>
                            <div id="fontawesome-icons" class="icon-grid">
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-globe')"><i class="fas fa-globe"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-search')"><i class="fas fa-search"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-laptop')"><i class="fas fa-laptop"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-mobile-alt')"><i class="fas fa-mobile-alt"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-tools')"><i class="fas fa-tools"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-book')"><i class="fas fa-book"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-gamepad')"><i class="fas fa-gamepad"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-newspaper')"><i class="fas fa-newspaper"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-shopping-cart')"><i class="fas fa-shopping-cart"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-comments')"><i class="fas fa-comments"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-palette')"><i class="fas fa-palette"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-music')"><i class="fas fa-music"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-video')"><i class="fas fa-video"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-chart-bar')"><i class="fas fa-chart-bar"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-wrench')"><i class="fas fa-wrench"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-bolt')"><i class="fas fa-bolt"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-rocket')"><i class="fas fa-rocket"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-lightbulb')"><i class="fas fa-lightbulb"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-bullseye')"><i class="fas fa-bullseye"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-edit')"><i class="fas fa-edit"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-lock')"><i class="fas fa-lock"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-star')"><i class="fas fa-star"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-heart')"><i class="fas fa-heart"></i></div>
                                <div class="icon-item fa-icon" onclick="selectIcon('fas fa-home')"><i class="fas fa-home"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">站长昵称</label>
                    <input type="text" name="applicant_name" class="form-control" 
                           value="<?php echo h($_POST['applicant_name'] ?? ''); ?>" 
                           placeholder="请输入您的昵称" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label required">邮箱地址</label>
                    <input type="email" name="applicant_email" class="form-control" 
                           value="<?php echo h($_POST['applicant_email'] ?? ''); ?>" 
                           placeholder="your@email.com" required>
                    <div class="form-text">我们会通过邮箱通知您审核结果</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">联系方式</label>
                    <input type="text" name="applicant_contact" class="form-control" 
                           value="<?php echo h($_POST['applicant_contact'] ?? ''); ?>" 
                           placeholder="QQ、微信、电话等（可选）">
                </div>
                
                <div class="form-group">
                    <label class="form-label">申请理由</label>
                    <textarea name="reason" class="form-control" rows="4" 
                              placeholder="请说明推荐这个链接的理由，如：功能特色、使用体验等"><?php echo h($_POST['reason'] ?? ''); ?></textarea>
                    <div class="form-text">详细的理由有助于我们更好地了解您的推荐</div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">提交申请</button>
                    <a href="index.php" class="btn btn-secondary">返回首页</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // 图标选择器功能
        function toggleIconPicker() {
            const picker = document.getElementById('icon-picker');
            if (picker.style.display === 'none') {
                picker.style.display = 'block';
            } else {
                picker.style.display = 'none';
            }
        }
        
        function showIconTab(tabName) {
            // 隐藏所有图标网格
            document.querySelectorAll('.icon-grid').forEach(grid => {
                grid.classList.remove('active');
            });
            
            // 移除所有标签的活动状态
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // 显示选中的图标网格
            document.getElementById(tabName + '-icons').classList.add('active');
            
            // 激活选中的标签
            event.target.classList.add('active');
        }
        
        function selectIcon(iconValue) {
            // 设置输入框的值
            document.getElementById('icon-input').value = iconValue;
            
            // 关闭图标选择器
            toggleIconPicker();
            
            // 添加视觉反馈
            const input = document.getElementById('icon-input');
            input.style.borderColor = '#27ae60';
            setTimeout(() => {
                input.style.borderColor = '#e1e8ed';
            }, 1000);
        }
        
        // 点击外部关闭图标选择器
        document.addEventListener('click', function(event) {
            const picker = document.getElementById('icon-picker');
            const pickerBtn = document.querySelector('.icon-picker-btn');
            const iconInputGroup = document.querySelector('.icon-input-group');
            
            if (picker.style.display === 'block' && 
                !picker.contains(event.target) && 
                !pickerBtn.contains(event.target) && 
                !iconInputGroup.contains(event.target)) {
                picker.style.display = 'none';
            }
        });
        
        // 获取网站信息功能
        function fetchSiteInfo() {
            const urlInput = document.getElementById('url-input');
            const fetchBtn = document.querySelector('.fetch-info-btn');
            const statusDiv = document.getElementById('fetch-status');
            const titleInput = document.querySelector('input[name="title"]');
            const descInput = document.querySelector('textarea[name="description"]');
            const iconInput = document.getElementById('icon-input');
            
            const url = urlInput.value.trim();
            
            if (!url) {
                showFetchStatus('请先输入链接地址', 'error');
                return;
            }
            
            if (!isValidUrl(url)) {
                showFetchStatus('请输入有效的链接地址', 'error');
                return;
            }
            
            // 禁用按钮并显示加载状态
            fetchBtn.disabled = true;
            fetchBtn.textContent = '获取中...';
            showFetchStatus('正在获取网站信息...', 'loading');
            
            // 发送请求获取网站信息
            fetch('api/fetch-site-info.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: url })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 自动填充表单
                    if (data.title && !titleInput.value) {
                        titleInput.value = data.title;
                    }
                    if (data.description && !descInput.value) {
                        descInput.value = data.description;
                    }
                    if (data.icon && !iconInput.value) {
                        iconInput.value = data.icon;
                    }
                    
                    showFetchStatus('网站信息获取成功！', 'success');
                } else {
                    showFetchStatus(data.message || '获取网站信息失败', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showFetchStatus('网络错误，请稍后重试', 'error');
            })
            .finally(() => {
                // 恢复按钮状态
                fetchBtn.disabled = false;
                fetchBtn.textContent = '获取信息';
                
                // 3秒后隐藏状态信息
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 3000);
            });
        }
        
        function showFetchStatus(message, type) {
            const statusDiv = document.getElementById('fetch-status');
            statusDiv.textContent = message;
            statusDiv.className = 'fetch-status ' + type;
            statusDiv.style.display = 'block';
        }
        
        function isValidUrl(string) {
            try {
                new URL(string);
                return true;
            } catch (_) {
                return false;
            }
        }
    </script>
</body>
</html>
