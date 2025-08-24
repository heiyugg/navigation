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

// 处理文件下载请求
if (isset($_GET['action']) && $_GET['action'] === 'download' && isset($_GET['file'])) {
    $filename = $_GET['file'];
    $backup_dir = '../backups/';
    
    // 安全检查：只允许下载.sql文件
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
        die('只能下载SQL备份文件');
    }
    
    // 检查文件名是否包含危险字符
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        die('文件名包含非法字符');
    }
    
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath)) {
        // 设置下载头
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // 输出文件内容
        readfile($filepath);
        exit;
    } else {
        die('文件不存在');
    }
}

$message = '';
$error = '';

// 创建备份目录
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'export':
                $result = exportDatabase();
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['message'];
                }
                break;
                
            case 'import':
                if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
                    $result = importDatabase($_FILES['sql_file']);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                } else {
                    $error = '请选择要导入的SQL文件';
                }
                break;
                
            case 'delete_backup':
                if (isset($_POST['filename'])) {
                    $result = deleteBackup($_POST['filename']);
                    if ($result['success']) {
                        $message = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
        }
    }
}

// 获取备份文件列表
function getBackupFiles() {
    global $backup_dir;
    $files = [];
    if (is_dir($backup_dir)) {
        $scan = scandir($backup_dir);
        foreach ($scan as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filepath = $backup_dir . $file;
                $files[] = [
                    'name' => $file,
                    'size' => formatBytes(filesize($filepath)),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath))
                ];
            }
        }
        // 按修改时间倒序排列
        usort($files, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }
    return $files;
}

// 导出数据库
function exportDatabase() {
    global $backup_dir, $db_config;
    
    try {
        $pdo = getDatabase();
        
        // 获取所有表名
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        if (empty($tables)) {
            return ['success' => false, 'message' => '没有找到数据库表'];
        }
        
        // 生成备份文件名
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $filepath = $backup_dir . $filename;
        
        // 开始生成SQL
        $sql_content = "-- 导航站数据库备份\n";
        $sql_content .= "-- 备份时间: " . date('Y-m-d H:i:s') . "\n";
        $sql_content .= "-- 数据库: " . $db_config['database'] . "\n\n";
        $sql_content .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql_content .= "SET AUTOCOMMIT = 0;\n";
        $sql_content .= "START TRANSACTION;\n";
        $sql_content .= "SET time_zone = \"+00:00\";\n\n";
        
        foreach ($tables as $table) {
            // 获取表结构
            $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_NUM);
            $sql_content .= "-- --------------------------------------------------------\n\n";
            $sql_content .= "-- 表的结构 `$table`\n\n";
            $sql_content .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql_content .= $row[1] . ";\n\n";
            
            // 获取表数据
            $stmt = $pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $sql_content .= "-- 转存表中的数据 `$table`\n\n";
                
                // 获取列名
                $columns = array_keys($rows[0]);
                $column_list = '`' . implode('`, `', $columns) . '`';
                
                $sql_content .= "INSERT INTO `$table` ($column_list) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $row_values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $row_values[] = 'NULL';
                        } else {
                            $row_values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $row_values) . ')';
                }
                
                $sql_content .= implode(",\n", $values) . ";\n\n";
            }
        }
        
        $sql_content .= "COMMIT;\n";
        
        // 写入文件
        if (file_put_contents($filepath, $sql_content) !== false) {
            return [
                'success' => true, 
                'message' => "数据库导出成功！备份文件：$filename"
            ];
        } else {
            return ['success' => false, 'message' => '写入备份文件失败'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => '导出失败：' . $e->getMessage()];
    }
}

// 导入数据库
function importDatabase($file) {
    try {
        $pdo = getDatabase();
        
        // 读取SQL文件
        $sql_content = file_get_contents($file['tmp_name']);
        if ($sql_content === false) {
            return ['success' => false, 'message' => '读取SQL文件失败'];
        }
        
        // 清理SQL内容
        $sql_content = str_replace(["\r\n", "\r"], "\n", $sql_content);
        
        // 移除注释和空行，更智能地分割SQL语句
        $lines = explode("\n", $sql_content);
        $statements = [];
        $current_statement = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // 跳过空行和注释行
            if (empty($line) || 
                strpos($line, '--') === 0 || 
                strpos($line, '#') === 0 ||
                strpos($line, '/*') === 0) {
                continue;
            }
            
            // 跳过MySQL特定的设置语句
            if (preg_match('/^(SET|START TRANSACTION|COMMIT|AUTOCOMMIT)/i', $line)) {
                continue;
            }
            
            $current_statement .= $line . ' ';
            
            // 如果行以分号结尾，表示语句结束
            if (substr($line, -1) === ';') {
                $statement = trim($current_statement);
                if (!empty($statement)) {
                    $statements[] = rtrim($statement, ';');
                }
                $current_statement = '';
            }
        }
        
        // 处理最后一个语句（如果没有分号结尾）
        if (!empty(trim($current_statement))) {
            $statements[] = trim($current_statement);
        }
        
        if (empty($statements)) {
            return ['success' => false, 'message' => 'SQL文件中没有找到有效的SQL语句'];
        }
        
        $pdo->beginTransaction();
        
        $executed = 0;
        $errors = [];
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement)) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $executed++;
            } catch (PDOException $e) {
                $error_msg = $e->getMessage();
                
                // 忽略一些常见的非关键错误
                if (strpos($error_msg, 'already exists') !== false || 
                    strpos($error_msg, 'Duplicate entry') !== false ||
                    strpos($error_msg, "doesn't exist") !== false) {
                    continue;
                }
                
                // 记录错误但继续执行
                $errors[] = "语句执行失败: " . substr($statement, 0, 50) . "... - " . $error_msg;
                
                // 如果是严重错误，停止执行
                if (strpos($error_msg, 'syntax error') !== false) {
                    throw $e;
                }
            }
        }
        
        $pdo->commit();
        
        $message = "数据库导入成功！执行了 $executed 条SQL语句";
        if (!empty($errors)) {
            $message .= "\n注意：有 " . count($errors) . " 个非关键错误被忽略";
        }
        
        return [
            'success' => true, 
            'message' => $message
        ];
        
    } catch (Exception $e) {
        if (isset($pdo)) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => '导入失败：' . $e->getMessage()];
    }
}

// 删除备份文件
function deleteBackup($filename) {
    global $backup_dir;
    
    // 安全检查：只允许删除.sql文件
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'sql') {
        return ['success' => false, 'message' => '只能删除SQL备份文件'];
    }
    
    $filepath = $backup_dir . $filename;
    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            return ['success' => true, 'message' => '备份文件删除成功'];
        } else {
            return ['success' => false, 'message' => '删除文件失败'];
        }
    } else {
        return ['success' => false, 'message' => '文件不存在'];
    }
}

// 格式化文件大小
function formatBytes($size, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $base = log($size, 1024);
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $units[floor($base)];
}

$backup_files = getBackupFiles();

// 页面内容
ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">数据导出</h3>
    </div>
    <div class="card-body">
        <p>导出当前数据库的所有数据，生成SQL备份文件，方便网站搬家或数据备份。</p>
        <form method="post" style="display: inline;">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-primary" onclick="return confirm('确定要导出数据库吗？')">
                <i>📤</i> 导出数据库
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">数据导入</h3>
    </div>
    <div class="card-body">
        <p><strong>⚠️ 警告：</strong>导入数据将覆盖现有数据，请确保已备份当前数据！</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import">
            <div class="form-group">
                <label class="form-label">选择SQL备份文件</label>
                <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                <small class="text-muted">只支持.sql格式的文件</small>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('确定要导入数据吗？这将覆盖现有数据！')">
                <i>📥</i> 导入数据库
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">备份文件管理</h3>
    </div>
    <div class="card-body">
        <?php if (empty($backup_files)): ?>
            <p class="text-muted text-center">暂无备份文件</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>文件名</th>
                            <th>文件大小</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($backup_files as $file): ?>
                            <tr>
                                <td><?php echo h($file['name']); ?></td>
                                <td><?php echo h($file['size']); ?></td>
                                <td><?php echo h($file['date']); ?></td>
                                <td>
                                    <a href="backup.php?action=download&file=<?php echo urlencode($file['name']); ?>" 
                                       class="btn btn-secondary" 
                                       style="margin-right: 5px;">
                                        <i>💾</i> 下载
                                    </a>
                                    <form method="post" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_backup">
                                        <input type="hidden" name="filename" value="<?php echo h($file['name']); ?>">
                                        <button type="submit" 
                                                class="btn btn-danger" 
                                                onclick="return confirm('确定要删除这个备份文件吗？')">
                                            <i>🗑️</i> 删除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">使用说明</h3>
    </div>
    <div class="card-body">
        <h4>数据导出</h4>
        <ul>
            <li>点击"导出数据库"按钮，系统会自动生成包含所有数据的SQL文件</li>
            <li>导出的文件保存在 <code>backups/</code> 目录下</li>
            <li>文件名格式：<code>backup_年-月-日_时-分-秒.sql</code></li>
        </ul>
        
        <h4>数据导入</h4>
        <ul>
            <li>选择之前导出的SQL文件进行导入</li>
            <li><strong>注意：导入会覆盖现有数据，请先备份！</strong></li>
            <li>支持导入本系统导出的SQL文件</li>
        </ul>
        
        <h4>网站搬家步骤</h4>
        <ol>
            <li>在旧服务器上导出数据库</li>
            <li>将整个网站文件复制到新服务器</li>
            <li>修改 <code>config/database.php</code> 中的数据库配置</li>
            <li>在新服务器上导入数据库</li>
            <li>检查网站是否正常运行</li>
        </ol>
    </div>
</div>

<?php
$content = ob_get_clean();

// 渲染页面
renderAdminLayout('数据备份', $content, 'backup');
?>
