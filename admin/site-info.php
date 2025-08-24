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
$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_site_start_date':
            $site_start_date = trim($_POST['site_start_date'] ?? '');
            
            try {
                // 更新或插入建站时间设置
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, setting_type, description, group_name, sort_order) 
                                     VALUES ('site_start_date', ?, 'date', '网站建站时间', 'site_info', 1) 
                                     ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$site_start_date, $site_start_date]);
                $message = '网站建站时间更新成功';
            } catch (PDOException $e) {
                $error = '更新失败：' . $e->getMessage();
            }
            break;
            
        case 'upload_logo':
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/';
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 2 * 1024 * 1024; // 2MB
                
                $file = $_FILES['logo'];
                $file_type = $file['type'];
                $file_size = $file['size'];
                
                if (!in_array($file_type, $allowed_types)) {
                    $error = '只支持 JPG、PNG、GIF、WebP 格式的图片';
                } elseif ($file_size > $max_size) {
                    $error = '图片大小不能超过 2MB';
                } else {
                    // 生成唯一文件名
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'logo_' . date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $extension;
                    $filepath = $upload_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        try {
                            // 删除旧logo文件
                            $stmt = $pdo->query("SELECT site_logo FROM site_config WHERE id = 1");
                            $old_logo = $stmt->fetchColumn();
                            if ($old_logo && file_exists('../' . $old_logo)) {
                                unlink('../' . $old_logo);
                            }
                            
                            // 更新数据库
                            $logo_path = 'uploads/' . $filename;
                            $stmt = $pdo->prepare("UPDATE site_config SET site_logo = ? WHERE id = 1");
                            $stmt->execute([$logo_path]);
                            $message = 'Logo上传成功';
                        } catch (PDOException $e) {
                            $error = '数据库更新失败：' . $e->getMessage();
                            // 删除已上传的文件
                            if (file_exists($filepath)) {
                                unlink($filepath);
                            }
                        }
                    } else {
                        $error = '文件上传失败';
                    }
                }
            } else {
                $error = '请选择要上传的logo文件';
            }
            break;
            
        case 'remove_logo':
            try {
                // 获取当前logo路径
                $stmt = $pdo->query("SELECT site_logo FROM site_config WHERE id = 1");
                $logo_path = $stmt->fetchColumn();
                
                // 删除文件
                if ($logo_path && file_exists('../' . $logo_path)) {
                    unlink('../' . $logo_path);
                }
                
                // 更新数据库
                $stmt = $pdo->prepare("UPDATE site_config SET site_logo = NULL WHERE id = 1");
                $stmt->execute();
                $message = 'Logo删除成功';
            } catch (PDOException $e) {
                $error = '删除失败：' . $e->getMessage();
            }
            break;
    }
}

// 获取网站配置
$site_config = $pdo->query("SELECT * FROM site_config WHERE id = 1")->fetch();

// 获取建站时间设置
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_start_date'");
$stmt->execute();
$site_start_date = $stmt->fetchColumn() ?: '';

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<!-- 网站Logo设置 -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">网站Logo设置</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 30px; align-items: start;">
            <!-- 当前Logo显示 -->
            <div>
                <h5 style="margin-bottom: 15px;">当前Logo</h5>
                <div style="border: 2px dashed #ddd; padding: 20px; text-align: center; border-radius: 8px; background: #f9f9f9;">
                    <?php if (!empty($site_config['site_logo'])): ?>
                        <img src="../<?php echo h($site_config['site_logo']); ?>" 
                             alt="网站Logo" 
                             style="max-width: 200px; max-height: 100px; object-fit: contain;">
                        <div style="margin-top: 10px;">
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="action" value="remove_logo">
                                <button type="submit" class="btn btn-danger btn-sm" 
                                        onclick="return confirm('确定要删除当前Logo吗？')">删除Logo</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div style="color: #999; padding: 20px;">
                            <i style="font-size: 48px;">🖼️</i>
                            <p>暂无Logo</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Logo上传表单 -->
            <div>
                <h5 style="margin-bottom: 15px;">上传新Logo</h5>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_logo">
                    
                    <div class="form-group">
                        <label class="form-label">选择Logo文件</label>
                        <input type="file" name="logo" class="form-control" 
                               accept="image/jpeg,image/png,image/gif,image/webp" required>
                        <small class="form-text text-muted">
                            支持 JPG、PNG、GIF、WebP 格式，文件大小不超过 2MB<br>
                            建议尺寸：宽度不超过 200px，高度不超过 100px
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">上传Logo</button>
                    </div>
                </form>
                
                <div style="margin-top: 20px; padding: 15px; background: #e8f4fd; border-radius: 6px; border-left: 4px solid #3498db;">
                    <h6 style="margin-bottom: 10px; color: #2c3e50;">💡 Logo使用说明</h6>
                    <ul style="margin: 0; padding-left: 20px; font-size: 14px; color: #555;">
                        <li>Logo将显示在网站头部导航栏中</li>
                        <li>建议使用透明背景的PNG格式</li>
                        <li>Logo会自动适应导航栏高度</li>
                        <li>上传新Logo会自动替换旧Logo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 网站建站时间设置 -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">网站建站时间</h3>
    </div>
    <div class="card-body">
        <div style="max-width: 500px;">
            <form method="post">
                <input type="hidden" name="action" value="update_site_start_date">
                
                <div class="form-group">
                    <label class="form-label">建站时间</label>
                    <input type="date" name="site_start_date" class="form-control" 
                           value="<?php echo h($site_start_date); ?>" 
                           placeholder="选择建站日期">
                    <small class="form-text text-muted">
                        用于计算网站运行天数等统计信息，可在前端页面显示
                    </small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">保存建站时间</button>
                </div>
            </form>
            
            <?php if ($site_start_date): ?>
                <div style="margin-top: 20px; padding: 15px; background: #f0f9ff; border-radius: 6px; border-left: 4px solid #3498db;">
                    <h6 style="margin-bottom: 10px; color: #2c3e50;">📊 运行统计</h6>
                    <?php
                    $start_date = new DateTime($site_start_date);
                    $current_date = new DateTime();
                    $diff = $current_date->diff($start_date);
                    $days = $diff->days;
                    ?>
                    <div style="font-size: 14px; color: #555;">
                        <div><strong>建站日期：</strong><?php echo date('Y年m月d日', strtotime($site_start_date)); ?></div>
                        <div><strong>运行天数：</strong><?php echo $days; ?> 天</div>
                        <div><strong>运行时间：</strong><?php echo $diff->y; ?> 年 <?php echo $diff->m; ?> 个月 <?php echo $diff->d; ?> 天</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 使用说明 -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">使用说明</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
            <div>
                <h5 style="color: #2c3e50; margin-bottom: 15px;">🖼️ Logo相关</h5>
                <ul style="line-height: 1.8; color: #555;">
                    <li>Logo会显示在网站头部导航栏左侧</li>
                    <li>如果没有设置Logo，将显示网站标题文字</li>
                    <li>Logo支持点击跳转到首页</li>
                    <li>建议Logo宽高比为 2:1 或 3:1</li>
                </ul>
            </div>
            
            <div>
                <h5 style="color: #2c3e50; margin-bottom: 15px;">📅 建站时间相关</h5>
                <ul style="line-height: 1.8; color: #555;">
                    <li>建站时间用于计算网站运行天数</li>
                    <li>可以在前端页面底部显示运行信息</li>
                    <li>有助于展示网站的历史和稳定性</li>
                    <li>可用于统计分析和纪念活动</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

$additional_head = '
<style>
.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

.form-control[type="file"] {
    padding: 8px;
    border: 2px dashed #ddd;
    background: #f9f9f9;
    transition: all 0.3s;
}

.form-control[type="file"]:hover {
    border-color: #3498db;
    background: #f0f8ff;
}

.form-text {
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.text-muted {
    color: #6c757d !important;
}

.logo-preview {
    transition: all 0.3s;
}

.logo-preview:hover {
    transform: scale(1.05);
}

.info-box {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.info-box h6 {
    color: white !important;
    margin-bottom: 15px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.stat-item {
    background: rgba(255,255,255,0.1);
    padding: 15px;
    border-radius: 6px;
    text-align: center;
}

.stat-number {
    font-size: 24px;
    font-weight: bold;
    display: block;
}

.stat-label {
    font-size: 12px;
    opacity: 0.8;
    margin-top: 5px;
}
</style>
';

renderAdminLayout('网站信息设置', $content, 'site-info', $additional_head);
?>
