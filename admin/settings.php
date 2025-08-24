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
        case 'update_site_config':
            $site_title = trim($_POST['site_title'] ?? '');
            $site_description = trim($_POST['site_description'] ?? '');
            $copyright = trim($_POST['copyright'] ?? '');
            $icp_number = trim($_POST['icp_number'] ?? '');
            $police_number = trim($_POST['police_number'] ?? '');
            
            if (empty($site_title)) {
                $error = '网站标题不能为空';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE site_config SET site_title = ?, site_description = ?, copyright = ?, icp_number = ?, police_number = ? WHERE id = 1");
                    $stmt->execute([$site_title, $site_description, $copyright, $icp_number, $police_number]);
                    $message = '网站配置更新成功';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'update_settings':
            $settings = $_POST['settings'] ?? [];
            
            try {
                foreach ($settings as $key => $value) {
                    $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                    $stmt->execute([$value, $key]);
                }
                $message = '系统设置更新成功';
            } catch (PDOException $e) {
                $error = '更新失败：' . $e->getMessage();
            }
            break;
            
        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                $error = '所有密码字段都不能为空';
            } elseif ($new_password !== $confirm_password) {
                $error = '新密码和确认密码不匹配';
            } elseif (strlen($new_password) < 6) {
                $error = '新密码长度不能少于6位';
            } else {
                // 验证当前密码
                $stmt = $pdo->prepare("SELECT password FROM admin_users WHERE id = ?");
                $stmt->execute([$_SESSION['admin_id']]);
                $admin = $stmt->fetch();
                
                if (!password_verify($current_password, $admin['password'])) {
                    $error = '当前密码不正确';
                } else {
                    try {
                        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                        $stmt->execute([$new_hash, $_SESSION['admin_id']]);
                        $message = '密码修改成功';
                    } catch (PDOException $e) {
                        $error = '密码修改失败：' . $e->getMessage();
                    }
                }
            }
            break;
    }
}

// 获取网站配置
$site_config = $pdo->query("SELECT * FROM site_config WHERE id = 1")->fetch();

// 获取系统设置
$settings = [];
$stmt = $pdo->query("SELECT * FROM settings ORDER BY group_name, sort_order");
while ($row = $stmt->fetch()) {
    $settings[$row['group_name']][] = $row;
}

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<!-- 网站基本配置 -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">网站基本配置</h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="update_site_config">
            
            <div class="form-group">
                <label class="form-label">网站标题 *</label>
                <input type="text" name="site_title" class="form-control" 
                       value="<?php echo h($site_config['site_title'] ?? ''); ?>" 
                       placeholder="请输入网站标题" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">网站描述</label>
                <textarea name="site_description" class="form-control" rows="3" 
                          placeholder="请输入网站描述"><?php echo h($site_config['site_description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">版权信息</label>
                <input type="text" name="copyright" class="form-control" 
                       value="<?php echo h($site_config['copyright'] ?? ''); ?>" 
                       placeholder="© 2024 导航站">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">ICP备案号</label>
                    <input type="text" name="icp_number" class="form-control" 
                           value="<?php echo h($site_config['icp_number'] ?? ''); ?>" 
                           placeholder="京ICP备xxxxxxxx号">
                </div>
                
                <div class="form-group">
                    <label class="form-label">公安备案号</label>
                    <input type="text" name="police_number" class="form-control" 
                           value="<?php echo h($site_config['police_number'] ?? ''); ?>" 
                           placeholder="京公网安备xxxxxxxx号">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">保存网站配置</button>
            </div>
        </form>
    </div>
</div>



<!-- 修改密码 -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title">修改密码</h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="change_password">
            
            <div style="max-width: 400px;">
                <div class="form-group">
                    <label class="form-label">当前密码 *</label>
                    <input type="password" name="current_password" class="form-control" 
                           placeholder="请输入当前密码" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">新密码 *</label>
                    <input type="password" name="new_password" class="form-control" 
                           placeholder="请输入新密码（至少6位）" required minlength="6">
                </div>
                
                <div class="form-group">
                    <label class="form-label">确认新密码 *</label>
                    <input type="password" name="confirm_password" class="form-control" 
                           placeholder="请再次输入新密码" required minlength="6">
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-danger">修改密码</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- 系统信息 -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">系统信息</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div>
                <h5 style="color: #2c3e50; margin-bottom: 10px;">服务器信息</h5>
                <div style="font-size: 14px; line-height: 1.8;">
                    <div><strong>PHP版本:</strong> <?php echo PHP_VERSION; ?></div>
                    <div><strong>服务器软件:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></div>
                    <div><strong>操作系统:</strong> <?php echo PHP_OS; ?></div>
                </div>
            </div>
            
            <div>
                <h5 style="color: #2c3e50; margin-bottom: 10px;">数据库信息</h5>
                <div style="font-size: 14px; line-height: 1.8;">
                    <?php
                    $db_version = $pdo->query("SELECT VERSION()")->fetchColumn();
                    $db_size = $pdo->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB' FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
                    ?>
                    <div><strong>MySQL版本:</strong> <?php echo $db_version; ?></div>
                    <div><strong>数据库大小:</strong> <?php echo $db_size; ?> MB</div>
                </div>
            </div>
            
            <div>
                <h5 style="color: #2c3e50; margin-bottom: 10px;">安装信息</h5>
                <div style="font-size: 14px; line-height: 1.8;">
                    <div><strong>安装时间:</strong> <?php echo date('Y-m-d H:i:s', strtotime($site_config['install_time'] ?? '')); ?></div>
                    <div><strong>最后更新:</strong> <?php echo date('Y-m-d H:i:s', strtotime($site_config['updated_at'] ?? '')); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
renderAdminLayout('网站设置', $content, 'settings');
?>
