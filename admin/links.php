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
        case 'add':
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $icon = trim($_POST['icon'] ?? '');
            $target = $_POST['target'] ?? '_blank';
            $is_recommended = (int)($_POST['is_recommended'] ?? 0);
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            
            if (empty($title) || empty($url) || $category_id == 0) {
                $error = '标题、链接和分类不能为空';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO links (title, url, description, category_id, icon, target, is_recommended, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$title, $url, $description, $category_id, $icon, $target, $is_recommended, $sort_order]);
                    $message = '链接添加成功';
                } catch (PDOException $e) {
                    $error = '添加失败：' . $e->getMessage();
                    // 如果是字符集错误，提供修复提示
                    if (strpos($e->getMessage(), 'Incorrect string value') !== false || strpos($e->getMessage(), '1366') !== false) {
                        $error .= ' <br><strong>🔤 字符集问题：</strong>数据库不支持 Emoji 表情，请<a href="../fix_database_charset.php" style="color: #dc3545; font-weight: bold;">点击这里修复字符集</a>。';
                    }
                }
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $url = trim($_POST['url'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = (int)($_POST['category_id'] ?? 0);
            $icon = trim($_POST['icon'] ?? '');
            $target = $_POST['target'] ?? '_blank';
            $is_recommended = (int)($_POST['is_recommended'] ?? 0);
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            
            if (empty($title) || empty($url) || $category_id == 0) {
                $error = '标题、链接和分类不能为空';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE links SET title = ?, url = ?, description = ?, category_id = ?, icon = ?, target = ?, is_recommended = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $url, $description, $category_id, $icon, $target, $is_recommended, $sort_order, $status, $id]);
                    $message = '链接更新成功';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM links WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = '链接删除成功';
                } catch (PDOException $e) {
                    $error = '删除失败：' . $e->getMessage();
                }
            }
            break;
    }
}

// 获取分类列表
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order ASC, name ASC")->fetchAll();

// 获取链接列表
$filter_category = (int)($_GET['category'] ?? 0);
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT l.*, c.name as category_name 
    FROM links l 
    LEFT JOIN categories c ON l.category_id = c.id 
    WHERE 1=1
";
$params = [];

if ($filter_category > 0) {
    $sql .= " AND l.category_id = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $sql .= " AND (l.title LIKE ? OR l.description LIKE ? OR l.url LIKE ?)";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$sql .= " ORDER BY l.sort_order ASC, l.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$links = $stmt->fetchAll();

// 获取编辑的链接信息
$edit_link = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_link = $stmt->fetch();
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
        <h3 class="card-title"><?php echo $edit_link ? '编辑链接' : '添加链接'; ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo $edit_link ? 'edit' : 'add'; ?>">
            <?php if ($edit_link): ?>
                <input type="hidden" name="id" value="<?php echo $edit_link['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">链接标题 *</label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo h($edit_link['title'] ?? ''); ?>" 
                           placeholder="请输入链接标题" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">链接地址 *</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="url" name="url" id="url-input" class="form-control" 
                               value="<?php echo h($edit_link['url'] ?? ''); ?>" 
                               placeholder="https://example.com" required style="flex: 1;">
                        <button type="button" class="btn btn-info" onclick="fetchSiteInfo(true)"
                                id="fetch-btn" style="padding: 8px 12px; white-space: nowrap;">
                            🔍 获取信息
                        </button>
                    </div>
                    <small class="form-text text-muted">输入网址后点击"获取信息"按钮自动填充标题、描述和图标</small>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">链接描述</label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="请输入链接描述"><?php echo h($edit_link['description'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">所属分类 *</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">请选择分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($edit_link && $edit_link['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">图标</label>
                    <input type="text" name="icon" class="form-control" 
                           value="<?php echo h($edit_link['icon'] ?? ''); ?>" 
                           placeholder="图标URL或emoji">
                </div>
                
                <div class="form-group">
                    <label class="form-label">打开方式</label>
                    <select name="target" class="form-control">
                        <option value="_blank" <?php echo ($edit_link && $edit_link['target'] == '_blank') ? 'selected' : ''; ?>>新窗口</option>
                        <option value="_self" <?php echo ($edit_link && $edit_link['target'] == '_self') ? 'selected' : ''; ?>>当前窗口</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-control" 
                           value="<?php echo $edit_link['sort_order'] ?? 0; ?>" 
                           placeholder="数字越小排序越靠前">
                </div>
                
                <div class="form-group">
                    <label class="form-label">推荐链接</label>
                    <select name="is_recommended" class="form-control">
                        <option value="0" <?php echo ($edit_link && $edit_link['is_recommended'] == 0) ? 'selected' : ''; ?>>否</option>
                        <option value="1" <?php echo ($edit_link && $edit_link['is_recommended'] == 1) ? 'selected' : ''; ?>>是</option>
                    </select>
                </div>
                
                <?php if ($edit_link): ?>
                <div class="form-group">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?php echo ($edit_link['status'] == 1) ? 'selected' : ''; ?>>启用</option>
                        <option value="0" <?php echo ($edit_link['status'] == 0) ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_link ? '更新链接' : '添加链接'; ?>
                </button>
                <?php if ($edit_link): ?>
                    <a href="links.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">链接列表</h3>
        <div>
            <span class="text-muted">共 <?php echo count($links); ?> 个链接</span>
        </div>
    </div>
    <div class="card-body">
        <!-- 筛选和搜索 -->
        <form method="get" style="margin-bottom: 20px;">
            <div style="display: flex; gap: 15px; align-items: end;">
                <div style="flex: 1;">
                    <label class="form-label">搜索链接</label>
                    <input type="text" name="search" class="form-control" 
                           value="<?php echo h($search); ?>" 
                           placeholder="搜索标题、描述或链接地址">
                </div>
                <div style="min-width: 200px;">
                    <label class="form-label">筛选分类</label>
                    <select name="category" class="form-control">
                        <option value="">全部分类</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                    <?php echo ($filter_category == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo h($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button type="submit" class="btn btn-primary">搜索</button>
                    <a href="links.php" class="btn btn-secondary">重置</a>
                </div>
            </div>
        </form>
        
        <?php if (empty($links)): ?>
            <div class="text-center text-muted" style="padding: 40px;">
                <p>暂无链接数据</p>
                <p>请先添加一个链接</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px; border-left: 4px solid #007bff;">
                <div style="display: flex; align-items: center; gap: 8px; font-size: 14px; color: #495057;">
                    <span>🔄</span>
                    <span><strong>拖拽排序：</strong>拖动表格行可以调整链接的显示顺序，松开鼠标后会自动保存</span>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 60px;">拖拽</th>
                            <th>ID</th>
                            <th>标题</th>
                            <th>链接地址</th>
                            <th>分类</th>
                            <th>访问量</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="sortable-links">
                        <?php foreach ($links as $link): ?>
                            <tr data-id="<?php echo $link['id']; ?>" class="sortable-row">
                                <td class="drag-handle" style="cursor: move; text-align: center; color: #999;">
                                    <span style="font-size: 16px;">⋮⋮</span>
                                </td>
                                <td><?php echo $link['id']; ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <?php if ($link['icon']): ?>
                                            <?php echo renderLinkIcon($link['icon'], '16px'); ?>
                                        <?php endif; ?>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo h($link['title']); ?></div>
                                            <?php if ($link['description']): ?>
                                                <div style="font-size: 12px; color: #666; margin-top: 2px;">
                                                    <?php echo h(mb_substr($link['description'], 0, 50)); ?>
                                                    <?php if (mb_strlen($link['description']) > 50) echo '...'; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="<?php echo h($link['url']); ?>" target="_blank" 
                                       style="color: #3498db; text-decoration: none;">
                                        <?php echo h(mb_substr($link['url'], 0, 40)); ?>
                                        <?php if (mb_strlen($link['url']) > 40) echo '...'; ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge badge-secondary">
                                        <?php echo h($link['category_name'] ?? '未分类'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge badge-success">
                                        <?php echo number_format($link['visits']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span class="badge <?php echo $link['status'] ? 'badge-success' : 'badge-danger'; ?>">
                                            <?php echo $link['status'] ? '启用' : '禁用'; ?>
                                        </span>
                                        <?php if ($link['is_recommended']): ?>
                                            <span class="badge badge-warning">推荐</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="links.php?edit=<?php echo $link['id']; ?>" 
                                           class="btn btn-primary" style="font-size: 12px; padding: 4px 8px;">
                                            编辑
                                        </a>
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('确定要删除这个链接吗？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                            <button type="submit" class="btn btn-danger" 
                                                    style="font-size: 12px; padding: 4px 8px;">
                                                删除
                                            </button>
                                        </form>
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

// 添加自动获取网站信息和拖拽排序的JavaScript
$additional_js = '
<!-- 引入 SortableJS 库 -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
let isAutoFetching = false;
let isSorting = false;

// 初始化拖拽排序
document.addEventListener("DOMContentLoaded", function() {
    const sortableElement = document.getElementById("sortable-links");
    
    if (sortableElement) {
        const sortable = Sortable.create(sortableElement, {
            handle: ".drag-handle",
            animation: 150,
            ghostClass: "sortable-ghost",
            chosenClass: "sortable-chosen",
            dragClass: "sortable-drag",
            onStart: function(evt) {
                isSorting = true;
                // 添加拖拽时的样式
                evt.item.style.opacity = "0.5";
            },
            onEnd: function(evt) {
                // 恢复样式
                evt.item.style.opacity = "";
                
                // 获取新的排序
                const linkIds = [];
                const rows = sortableElement.querySelectorAll("tr[data-id]");
                
                rows.forEach(function(row) {
                    const linkId = row.getAttribute("data-id");
                    if (linkId) {
                        linkIds.push(parseInt(linkId));
                    }
                });
                
                // 发送排序更新请求
                updateLinkOrder(linkIds);
            }
        });
        
        // 添加拖拽相关的CSS样式
        const style = document.createElement("style");
        style.textContent = `
            .sortable-ghost {
                opacity: 0.4;
                background: #f8f9fa;
            }
            .sortable-chosen {
                background: #e3f2fd;
            }
            .sortable-drag {
                background: #fff;
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
            .drag-handle:hover {
                background: #f8f9fa;
                border-radius: 4px;
            }
            .sortable-row {
                transition: all 0.2s ease;
            }
            .sortable-row:hover {
                background: #f8f9fa;
            }
        `;
        document.head.appendChild(style);
    }
});

// 更新链接排序
async function updateLinkOrder(linkIds) {
    if (linkIds.length === 0) return;
    
    try {
        // 显示加载提示
        showSortMessage("正在保存排序...", "info");
        
        const response = await fetch("../api/update-link-order.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ links: linkIds })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSortMessage("排序保存成功！", "success");
        } else {
            showSortMessage("排序保存失败：" + (data.error || "未知错误"), "error");
        }
    } catch (error) {
        console.error("更新排序失败:", error);
        showSortMessage("排序保存失败：网络错误", "error");
    } finally {
        isSorting = false;
    }
}

// 显示排序消息提示
function showSortMessage(message, type = "info") {
    // 移除现有的排序消息
    const existingMessage = document.querySelector(".sort-message");
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // 创建新消息
    const messageDiv = document.createElement("div");
    messageDiv.className = `alert alert-${type === "success" ? "success" : type === "error" ? "error" : "info"} sort-message`;
    messageDiv.style.cssText = "position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 250px; padding: 12px 16px; border-radius: 6px; font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);";
    messageDiv.innerHTML = `
        <div style="display: flex; align-items: center; gap: 8px;">
            <span>${type === "success" ? "✅" : type === "error" ? "❌" : "ℹ️"}</span>
            <span>${message}</span>
        </div>
    `;
    
    document.body.appendChild(messageDiv);
    
    // 自动消失
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.style.opacity = "0";
            messageDiv.style.transform = "translateX(100%)";
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 300);
        }
    }, type === "error" ? 5000 : 3000);
}

// 自动获取网站信息
async function fetchSiteInfo(forceUpdate = false) {
    const urlInput = document.getElementById("url-input");
    const fetchBtn = document.getElementById("fetch-btn");
    const titleInput = document.querySelector("input[name=\"title\"]");
    const descriptionInput = document.querySelector("textarea[name=\"description\"]");
    const iconInput = document.querySelector("input[name=\"icon\"]");
    
    const url = urlInput.value.trim();
    
    if (!url) {
        alert("请先输入链接地址");
        return;
    }
    
    // 验证URL格式
    try {
        new URL(url);
    } catch (e) {
        alert("请输入有效的URL地址");
        return;
    }
    
    // 显示加载状态
    fetchBtn.disabled = true;
    fetchBtn.innerHTML = "⏳ 获取中...";
    
    try {
        const response = await fetch("../api/get-site-info.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({ url: url })
        });
        
        const data = await response.json();
        
        if (data.error) {
            alert("获取失败：" + data.error);
        } else {
            // 自动填充表单
            let filledFields = [];
            
            // 如果是强制更新或字段为空，则填充标题
            if (data.title && (forceUpdate || !titleInput.value.trim())) {
                titleInput.value = data.title;
                filledFields.push("标题");
            }
            
            // 如果是强制更新或字段为空，则填充描述
            if (data.description && (forceUpdate || !descriptionInput.value.trim())) {
                descriptionInput.value = data.description;
                filledFields.push("描述");
            }
            
            // 如果是强制更新或字段为空，则填充图标
            if (data.icon && (forceUpdate || !iconInput.value.trim())) {
                iconInput.value = data.icon;
                filledFields.push("图标");
            }
            
            // 显示成功消息，说明获取了哪些信息
            if (filledFields.length > 0) {
                showMessage(`网站信息获取成功！已自动填充：${filledFields.join("、")}`, "success");
            } else if (!forceUpdate) {
                showMessage("网站信息获取成功，但相关字段已有内容，未自动填充", "info");
            }
        }
    } catch (error) {
        console.error("获取网站信息失败:", error);
        alert("获取失败：网络错误或服务器无响应");
    } finally {
        // 恢复按钮状态
        fetchBtn.disabled = false;
        fetchBtn.innerHTML = "🔍 获取信息";
    }
}

// URL输入框变化时的处理
document.addEventListener("DOMContentLoaded", function() {
    const urlInput = document.getElementById("url-input");
    const titleInput = document.querySelector("input[name=\"title\"]");
    const descriptionInput = document.querySelector("textarea[name=\"description\"]");
    const iconInput = document.querySelector("input[name=\"icon\"]");
    
    let debounceTimer;
    let lastUrl = urlInput.value.trim(); // 记录上一次的URL
    
    // 监听URL输入框变化
    urlInput.addEventListener("input", function() {
        clearTimeout(debounceTimer);
        
        const currentUrl = urlInput.value.trim();
        
        // 延迟自动获取
        debounceTimer = setTimeout(() => {
            if (currentUrl && isValidUrl(currentUrl) && !isAutoFetching) {
                // 检查URL是否发生了变化
                const urlChanged = currentUrl !== lastUrl;
                
                // 如果URL变化了，或者所有字段都为空，则自动获取
                if (urlChanged || (!titleInput.value.trim() && !descriptionInput.value.trim() && !iconInput.value.trim())) {
                    isAutoFetching = true;
                    
                    // 如果URL变化了，强制更新所有字段
                    fetchSiteInfo(urlChanged).finally(() => {
                        isAutoFetching = false;
                        lastUrl = currentUrl; // 更新记录的URL
                    });
                }
            }
        }, 1500); // 1.5秒延迟
    });
    
    // 监听URL输入框失去焦点
    urlInput.addEventListener("blur", function() {
        const currentUrl = urlInput.value.trim();
        
        if (currentUrl && isValidUrl(currentUrl) && !isAutoFetching) {
            // 检查URL是否发生了变化
            const urlChanged = currentUrl !== lastUrl;
            
            // 如果URL变化了，或者所有字段都为空，则自动获取
            if (urlChanged || (!titleInput.value.trim() && !descriptionInput.value.trim() && !iconInput.value.trim())) {
                isAutoFetching = true;
                
                // 如果URL变化了，强制更新所有字段
                fetchSiteInfo(urlChanged).finally(() => {
                    isAutoFetching = false;
                    lastUrl = currentUrl; // 更新记录的URL
                });
            }
        }
    });
    
    // 手动点击获取信息按钮时，总是强制更新
    const fetchBtn = document.getElementById("fetch-btn");
    if (fetchBtn) {
        fetchBtn.addEventListener("click", function() {
            const currentUrl = urlInput.value.trim();
            if (currentUrl) {
                lastUrl = currentUrl; // 更新记录的URL
            }
        });
    }
});

// 验证URL格式
function isValidUrl(string) {
    try {
        new URL(string);
        return true;
    } catch (_) {
        return false;
    }
}

// 显示消息提示
function showMessage(message, type = "info") {
    // 移除现有的消息
    const existingMessage = document.querySelector(".auto-message");
    if (existingMessage) {
        existingMessage.remove();
    }
    
    // 创建新消息
    const messageDiv = document.createElement("div");
    messageDiv.className = `alert alert-${type === "success" ? "success" : "info"} auto-message`;
    messageDiv.style.cssText = "margin: 15px 0; padding: 10px; border-radius: 4px; font-size: 14px;";
    messageDiv.textContent = message;
    
    // 插入到表单后面
    const form = document.querySelector("form");
    form.parentNode.insertBefore(messageDiv, form.nextSibling);
    
    // 3秒后自动消失
    setTimeout(() => {
        if (messageDiv.parentNode) {
            messageDiv.remove();
        }
    }, 3000);
}

// 回车键快捷获取
document.addEventListener("keydown", function(e) {
    if (e.key === "Enter" && e.ctrlKey) {
        const urlInput = document.getElementById("url-input");
        if (document.activeElement === urlInput) {
            e.preventDefault();
            fetchSiteInfo();
        }
    }
});
</script>';

renderAdminLayout('链接管理', $content, 'links', '', $additional_js);
?>
