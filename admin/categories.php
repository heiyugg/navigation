<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查系统是否已安装
checkInstallation();

// 检查登录状态
if (!isAdminLoggedIn()) {
    header('Location: index.php');
    exit;
}

require_once 'layout.php';

$pdo = getDatabase();
$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $is_hidden = (int)($_POST['is_hidden'] ?? 0);
            
            if (empty($name)) {
                $error = '分类名称不能为空';
            } else {
                try {
                    // 检查是否存在 is_hidden 字段
                    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'is_hidden'");
                    $has_hidden_field = $stmt->rowCount() > 0;
                    
                    if ($has_hidden_field) {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon, sort_order, status, is_hidden) VALUES (?, ?, ?, ?, 1, ?)");
                        $stmt->execute([$name, $description, $icon, $sort_order, $is_hidden]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO categories (name, description, icon, sort_order, status) VALUES (?, ?, ?, ?, 1)");
                        $stmt->execute([$name, $description, $icon, $sort_order]);
                    }
                    $message = '分类添加成功';
                } catch (PDOException $e) {
                    $error = '添加失败：' . $e->getMessage();
                    // 如果是字符集错误，提供修复提示
                    if (strpos($e->getMessage(), 'Incorrect string value') !== false || strpos($e->getMessage(), '1366') !== false) {
                        $error .= ' <br><strong>🔤 字符集问题：</strong>数据库不支持 Emoji 表情，请<a href="../fix_database_charset.php" style="color: #dc3545; font-weight: bold;">点击这里修复字符集</a>。';
                    }
                    // 如果是字段不存在的错误，提供升级提示
                    if (strpos($e->getMessage(), 'is_hidden') !== false) {
                        $error .= ' <br><strong>提示：</strong>请先<a href="../update_database.php" style="color: #007bff;">更新数据库</a>以启用隐藏分类功能。';
                    }
                }
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $icon = trim($_POST['icon'] ?? '');
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            $is_hidden = (int)($_POST['is_hidden'] ?? 0);
            
            if (empty($name)) {
                $error = '分类名称不能为空';
            } else {
                try {
                    // 检查是否存在 is_hidden 字段
                    $stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'is_hidden'");
                    $has_hidden_field = $stmt->rowCount() > 0;
                    
                    if ($has_hidden_field) {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ?, sort_order = ?, status = ?, is_hidden = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $icon, $sort_order, $status, $is_hidden, $id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, icon = ?, sort_order = ?, status = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $icon, $sort_order, $status, $id]);
                    }
                    $message = '分类更新成功';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                    // 如果是字段不存在的错误，提供升级提示
                    if (strpos($e->getMessage(), 'is_hidden') !== false) {
                        $error .= ' <br><strong>提示：</strong>请先<a href="../update_database.php" style="color: #007bff;">更新数据库</a>以启用隐藏分类功能。';
                    }
                }
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    // 检查是否有链接使用此分类
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM links WHERE category_id = ?");
                    $stmt->execute([$id]);
                    $link_count = $stmt->fetchColumn();
                    
                    if ($link_count > 0) {
                        $error = '无法删除：该分类下还有 ' . $link_count . ' 个链接';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = '分类删除成功';
                    }
                } catch (PDOException $e) {
                    $error = '删除失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'move_up':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    // 获取当前分类的排序值和名称
                    $stmt = $pdo->prepare("SELECT id, name, sort_order FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_category = $stmt->fetch();
                    
                    if ($current_category) {
                        $current_order = $current_category['sort_order'];
                        
                        // 获取所有分类按排序值排序
                        $stmt = $pdo->prepare("SELECT id, name, sort_order FROM categories ORDER BY sort_order ASC, id ASC");
                        $stmt->execute();
                        $all_categories = $stmt->fetchAll();
                        
                        // 找到当前分类在列表中的位置
                        $current_index = -1;
                        for ($i = 0; $i < count($all_categories); $i++) {
                            if ($all_categories[$i]['id'] == $id) {
                                $current_index = $i;
                                break;
                            }
                        }
                        
                        // 如果不是第一个，则与上一个交换
                        if ($current_index > 0) {
                            $prev_category = $all_categories[$current_index - 1];
                            
                            // 交换排序值
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$prev_category['sort_order'], $id]);
                            $stmt->execute([$current_order, $prev_category['id']]);
                            $pdo->commit();
                            $message = '分类上移成功';
                        } else {
                            $error = '已经是第一个分类，无法上移';
                        }
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = '上移失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'move_down':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    // 获取当前分类的排序值和名称
                    $stmt = $pdo->prepare("SELECT id, name, sort_order FROM categories WHERE id = ?");
                    $stmt->execute([$id]);
                    $current_category = $stmt->fetch();
                    
                    if ($current_category) {
                        $current_order = $current_category['sort_order'];
                        
                        // 获取所有分类按排序值排序
                        $stmt = $pdo->prepare("SELECT id, name, sort_order FROM categories ORDER BY sort_order ASC, id ASC");
                        $stmt->execute();
                        $all_categories = $stmt->fetchAll();
                        
                        // 找到当前分类在列表中的位置
                        $current_index = -1;
                        for ($i = 0; $i < count($all_categories); $i++) {
                            if ($all_categories[$i]['id'] == $id) {
                                $current_index = $i;
                                break;
                            }
                        }
                        
                        // 如果不是最后一个，则与下一个交换
                        if ($current_index >= 0 && $current_index < count($all_categories) - 1) {
                            $next_category = $all_categories[$current_index + 1];
                            
                            // 交换排序值
                            $pdo->beginTransaction();
                            $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
                            $stmt->execute([$next_category['sort_order'], $id]);
                            $stmt->execute([$current_order, $next_category['id']]);
                            $pdo->commit();
                            $message = '分类下移成功';
                        } else {
                            $error = '已经是最后一个分类，无法下移';
                        }
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = '下移失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'fix_sort_order':
            try {
                // 获取所有分类，按 ID 排序
                $stmt = $pdo->query("SELECT id, name, sort_order FROM categories ORDER BY id ASC");
                $categories = $stmt->fetchAll();
                
                if (!empty($categories)) {
                    // 检查是否需要修复
                    $sort_orders = array_column($categories, 'sort_order');
                    $need_fix = (array_sum($sort_orders) == 0 || count($sort_orders) != count(array_unique($sort_orders)));
                    
                    if ($need_fix) {
                        // 开始事务
                        $pdo->beginTransaction();
                        
                        // 为每个分类设置递增的排序值
                        $sort_order = 1;
                        $stmt = $pdo->prepare("UPDATE categories SET sort_order = ? WHERE id = ?");
                        
                        foreach ($categories as $category) {
                            $stmt->execute([$sort_order, $category['id']]);
                            $sort_order++;
                        }
                        
                        // 提交事务
                        $pdo->commit();
                        $message = '分类排序值修复完成！现在可以正常使用上下移动功能了。';
                    } else {
                        $message = '分类排序值正常，无需修复。';
                    }
                } else {
                    $error = '没有找到分类数据';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = '修复失败：' . $e->getMessage();
            }
            break;
            
        case 'fix_charset':
            try {
                // 获取数据库名称
                $stmt = $pdo->query("SELECT DATABASE() as db_name");
                $db_info = $stmt->fetch();
                $database_name = $db_info['db_name'];
                
                // 检查当前数据库字符集
                $stmt = $pdo->query("SELECT DEFAULT_CHARACTER_SET_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = '{$database_name}'");
                $db_charset = $stmt->fetch();
                
                if ($db_charset['DEFAULT_CHARACTER_SET_NAME'] !== 'utf8mb4') {
                    // 开始修复
                    $pdo->beginTransaction();
                    
                    // 修改数据库字符集
                    $pdo->exec("ALTER DATABASE `{$database_name}` CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci");
                    
                    // 修改 categories 表
                    $pdo->exec("ALTER TABLE `categories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("ALTER TABLE `categories` MODIFY `name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("ALTER TABLE `categories` MODIFY `description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo->exec("ALTER TABLE `categories` MODIFY `icon` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    
                    // 修改 links 表
                    $stmt = $pdo->query("SHOW TABLES LIKE 'links'");
                    if ($stmt->rowCount() > 0) {
                        $pdo->exec("ALTER TABLE `links` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("ALTER TABLE `links` MODIFY `title` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("ALTER TABLE `links` MODIFY `description` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("ALTER TABLE `links` MODIFY `icon` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    }
                    
                    $pdo->commit();
                    $message = '数据库字符集修复完成！现在可以正常使用 Emoji 表情了。';
                } else {
                    $message = '数据库字符集已经是 utf8mb4，无需修复。';
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = '字符集修复失败：' . $e->getMessage();
            }
            break;
    }
}

// 检查是否存在 is_hidden 字段
$stmt = $pdo->query("SHOW COLUMNS FROM categories LIKE 'is_hidden'");
$has_hidden_field = $stmt->rowCount() > 0;

// 获取分类列表
$categories = $pdo->query("
    SELECT c.*, COUNT(l.id) as link_count
    FROM categories c
    LEFT JOIN links l ON c.id = l.category_id AND l.status = 1
    GROUP BY c.id
    ORDER BY c.sort_order ASC, c.id ASC
")->fetchAll();

// 获取编辑的分类信息
$edit_category = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_category = $stmt->fetch();
}

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
        <h3 class="card-title"><?php echo $edit_category ? '编辑分类' : '添加分类'; ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit' : 'add'; ?>">
            <?php if ($edit_category): ?>
                <input type="hidden" name="id" value="<?php echo $edit_category['id']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">分类名称 *</label>
                <input type="text" name="name" class="form-control" 
                       value="<?php echo h($edit_category['name'] ?? ''); ?>" 
                       placeholder="请输入分类名称" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">分类描述</label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="请输入分类描述"><?php echo h($edit_category['description'] ?? ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label class="form-label">图标</label>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <input type="text" name="icon" id="icon-input" class="form-control"
                           value="<?php echo h($edit_category['icon'] ?? ''); ?>"
                           placeholder="图标类名、emoji或SVG代码，如：📁" style="flex: 1;">
                    <button type="button" class="btn btn-secondary" onclick="showIconPicker()"
                            style="padding: 8px 12px; white-space: nowrap;">
                        选择图标
                    </button>
                </div>
                <small class="form-text text-muted">可以输入Emoji表情、Font Awesome图标类名、SVG代码或其他图标</small>
            </div>
            
            <div class="form-group">
                <label class="form-label">排序</label>
                <input type="number" name="sort_order" class="form-control"
                       value="<?php echo $edit_category['sort_order'] ?? 0; ?>"
                       placeholder="数字越小排序越靠前">
            </div>
            
            <?php if ($has_hidden_field): ?>
            <div class="form-group">
                <label class="form-label">隐藏分类</label>
                <select name="is_hidden" class="form-control">
                    <option value="0" <?php echo (!$edit_category || !isset($edit_category['is_hidden']) || $edit_category['is_hidden'] == 0) ? 'selected' : ''; ?>>公开显示</option>
                    <option value="1" <?php echo ($edit_category && isset($edit_category['is_hidden']) && $edit_category['is_hidden'] == 1) ? 'selected' : ''; ?>>仅管理员可见</option>
                </select>
                <small class="form-text text-muted">隐藏分类只有管理员登录后才能在首页看到，普通用户无法看到此分类及其下的链接</small>
            </div>
            <?php else: ?>
            <div class="alert alert-info">
                <strong>提示：</strong>隐藏分类功能需要先更新数据库。
                <a href="../update_database.php" class="btn btn-primary" style="margin-left: 10px;">立即更新数据库</a>
            </div>
            <?php endif; ?>
            
            <?php if ($edit_category): ?>
            <div class="form-group">
                <label class="form-label">状态</label>
                <select name="status" class="form-control">
                    <option value="1" <?php echo ($edit_category['status'] == 1) ? 'selected' : ''; ?>>启用</option>
                    <option value="0" <?php echo ($edit_category['status'] == 0) ? 'selected' : ''; ?>>禁用</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_category ? '更新分类' : '添加分类'; ?>
                </button>
                <?php if ($edit_category): ?>
                    <a href="categories.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">分类列表</h3>
        <div style="display: flex; align-items: center; gap: 15px;">
            <span class="text-muted">共 <?php echo count($categories); ?> 个分类</span>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="fix_sort_order">
                <button type="submit" class="btn btn-warning btn-sm"
                        onclick="return confirm('确定要修复分类排序值吗？这将重新设置所有分类的排序值。')"
                        title="如果上下移动功能不正常，可以点击此按钮修复排序值">
                    🔧 修复排序
                </button>
            </form>
            <form method="post" style="display: inline;">
                <input type="hidden" name="action" value="fix_charset">
                <button type="submit" class="btn btn-info btn-sm"
                        onclick="return confirm('确定要修复数据库字符集吗？这将升级数据库以支持 Emoji 表情。')"
                        title="如果无法保存 Emoji 表情，可以点击此按钮修复字符集">
                    🔤 修复字符集
                </button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($categories)): ?>
            <div class="text-center text-muted" style="padding: 40px;">
                <p>暂无分类数据</p>
                <p>请先添加一个分类</p>
            </div>
        <?php else: ?>
            <!-- 紧凑表格显示分类 -->
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">排序</th>
                            <th style="width: 80px;">图标</th>
                            <th style="min-width: 120px;">分类名称</th>
                            <th style="min-width: 200px;">描述</th>
                            <th style="width: 80px;">状态</th>
                            <th style="width: 80px;">链接数</th>
                            <th style="width: 120px;">创建时间</th>
                            <th style="width: 200px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td style="text-align: center; font-weight: 600; color: #666;">
                                    <?php echo $category['sort_order']; ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($category['icon']): ?>
                                        <?php echo renderCategoryIcon($category['icon'], '24px'); ?>
                                    <?php else: ?>
                                        <span style="color: #ccc;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 2px;">
                                        <?php echo h($category['name']); ?>
                                    </div>
                                    <small style="color: #666;">ID: <?php echo $category['id']; ?></small>
                                </td>
                                <td>
                                    <?php if ($category['description']): ?>
                                        <div style="color: #666; line-height: 1.4; max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo h($category['description']); ?>">
                                            <?php echo h($category['description']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #ccc;">无描述</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span class="badge <?php echo $category['status'] ? 'badge-success' : 'badge-danger'; ?>" style="font-size: 10px;">
                                            <?php echo $category['status'] ? '启用' : '禁用'; ?>
                                        </span>
                                        <?php if ($has_hidden_field && isset($category['is_hidden']) && $category['is_hidden']): ?>
                                            <span class="badge badge-warning" style="font-size: 10px;">隐藏</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td style="text-align: center; font-weight: 600; color: #007bff;">
                                    <?php echo $category['link_count']; ?>
                                </td>
                                <td style="font-size: 12px; color: #666;">
                                    <?php echo date('m-d H:i', strtotime($category['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <!-- 上下移动按钮 -->
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="move_up">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 6px;" title="上移">↑</button>
                                        </form>
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="move_down">
                                            <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                            <button type="submit" class="btn btn-secondary" style="font-size: 11px; padding: 4px 6px;" title="下移">↓</button>
                                        </form>
                                        
                                        <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-primary" style="font-size: 11px; padding: 4px 8px;">编辑</a>
                                        
                                        <?php if ($category['link_count'] == 0): ?>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('确定要删除这个分类吗？');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="font-size: 11px; padding: 4px 8px;">删除</button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary" disabled style="font-size: 11px; padding: 4px 8px;" title="该分类下有链接，无法删除">删除</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// 添加图标选择器的JavaScript和CSS
$additional_head = '
<style>
.icon-picker-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    display: none;
}

.icon-picker-modal {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    z-index: 1001;
}

.icon-picker-header {
    padding: 20px;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.icon-picker-body {
    padding: 20px;
    max-height: 60vh;
    overflow-y: auto;
}

.icon-category {
    margin-bottom: 25px;
}

.icon-category h4 {
    margin: 0 0 15px 0;
    color: #495057;
    font-size: 16px;
}

.icon-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(50px, 1fr));
    gap: 8px;
}

.icon-item {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 50px;
    height: 50px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    cursor: pointer;
    font-size: 20px;
    transition: all 0.2s;
    background: white;
}

.icon-item svg {
    width: 24px;
    height: 24px;
    color: #495057;
}

.icon-item:hover {
    border-color: #007bff;
    background: #f8f9fa;
    transform: scale(1.05);
}

.icon-item.selected {
    border-color: #007bff;
    background: #e3f2fd;
}

.close-btn {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6c757d;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.close-btn:hover {
    color: #495057;
}
</style>';

$additional_js = '
<script>
// 图标数据
const iconData = {
    "常用Emoji": [
        "📁", "📂", "📄", "📝", "📊", "📈", "📉", "📋", "📌", "📍",
        "🔗", "🌐", "💻", "📱", "⚙️", "🔧", "🔨", "🛠️", "🎯", "🎨",
        "🎵", "🎬", "📷", "📹", "🎮", "🏠", "🏢", "🏪", "🏫", "🏥",
        "✈️", "🚗", "🚀", "⭐", "🌟", "💡", "🔥", "❤️", "👍", "✅"
    ],
    "分类图标": [
        "📚", "📖", "📓", "📔", "📕", "📗", "📘", "📙", "📰", "📑",
        "🗂️", "🗃️", "🗄️", "📦", "📮", "📭", "📬", "📫", "📪", "📯"
    ],
    "技术图标": [
        "💻", "🖥️", "🖨️", "⌨️", "🖱️", "💾", "💿", "📀", "🔌", "🔋",
        "📡", "📶", "📳", "📴", "☎️", "📞", "📟", "📠", "🔍", "🔎"
    ],
    "Font Awesome": [
        "fas fa-home", "fas fa-user", "fas fa-cog", "fas fa-search", "fas fa-heart",
        "fas fa-star", "fas fa-bookmark", "fas fa-tag", "fas fa-tags", "fas fa-folder",
        "fas fa-file", "fas fa-edit", "fas fa-trash", "fas fa-plus", "fas fa-minus",
        "fas fa-check", "fas fa-times", "fas fa-arrow-up", "fas fa-arrow-down", "fas fa-link"
    ]
};

let selectedIcon = "";

function showIconPicker() {
    const overlay = document.getElementById("icon-picker-overlay");
    if (!overlay) {
        createIconPicker();
    }
    document.getElementById("icon-picker-overlay").style.display = "block";
    document.body.style.overflow = "hidden";
}

function hideIconPicker() {
    document.getElementById("icon-picker-overlay").style.display = "none";
    document.body.style.overflow = "auto";
}

function createIconPicker() {
    const overlay = document.createElement("div");
    overlay.id = "icon-picker-overlay";
    overlay.className = "icon-picker-overlay";
    overlay.onclick = function(e) {
        if (e.target === overlay) hideIconPicker();
    };

    const modal = document.createElement("div");
    modal.className = "icon-picker-modal";

    const header = document.createElement("div");
    header.className = "icon-picker-header";
    header.innerHTML = `
        <h3 style="margin: 0;">选择图标</h3>
        <button type="button" class="close-btn" onclick="hideIconPicker()">&times;</button>
    `;

    const body = document.createElement("div");
    body.className = "icon-picker-body";

    // 生成图标分类
    Object.keys(iconData).forEach(category => {
        const categoryDiv = document.createElement("div");
        categoryDiv.className = "icon-category";

        const categoryTitle = document.createElement("h4");
        categoryTitle.textContent = category;
        categoryDiv.appendChild(categoryTitle);

        const iconGrid = document.createElement("div");
        iconGrid.className = "icon-grid";

        iconData[category].forEach(icon => {
            const iconItem = document.createElement("div");
            iconItem.className = "icon-item";
            iconItem.onclick = () => selectIcon(icon, iconItem);

            if (category === "Font Awesome") {
                iconItem.innerHTML = `<i class="${icon}"></i>`;
                iconItem.title = icon;
            } else {
                iconItem.textContent = icon;
                iconItem.title = icon;
            }

            iconGrid.appendChild(iconItem);
        });

        categoryDiv.appendChild(iconGrid);
        body.appendChild(categoryDiv);
    });

    // 添加确认按钮
    const footer = document.createElement("div");
    footer.style.cssText = "padding: 20px; border-top: 1px solid #dee2e6; text-align: right;";
    footer.innerHTML = `
        <button type="button" class="btn btn-secondary" onclick="hideIconPicker()" style="margin-right: 10px;">取消</button>
        <button type="button" class="btn btn-primary" onclick="confirmIconSelection()">确认选择</button>
    `;

    modal.appendChild(header);
    modal.appendChild(body);
    modal.appendChild(footer);
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
}

function selectIcon(icon, element) {
    // 移除之前选中的状态
    document.querySelectorAll(".icon-item.selected").forEach(item => {
        item.classList.remove("selected");
    });
    
    // 添加选中状态
    element.classList.add("selected");
    selectedIcon = icon;
}

function confirmIconSelection() {
    if (selectedIcon) {
        document.getElementById("icon-input").value = selectedIcon;
        hideIconPicker();
        selectedIcon = "";
    }
}

// ESC键关闭弹窗
document.addEventListener("keydown", function(e) {
    if (e.key === "Escape") {
        hideIconPicker();
    }
});
</script>';

renderAdminLayout('分类管理', $content, 'categories', $additional_head, $additional_js);
?>
