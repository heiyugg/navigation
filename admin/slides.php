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
            $description = trim($_POST['description'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $link_url = trim($_POST['link_url'] ?? '');
            $link_text = trim($_POST['link_text'] ?? '');
            $link_target = $_POST['link_target'] ?? '_blank';
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            
            if (empty($title) || empty($image)) {
                $error = '标题和图片不能为空';
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO slides (title, description, image, link_url, link_text, link_target, sort_order, status) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([$title, $description, $image, $link_url, $link_text, $link_target, $sort_order]);
                    $message = '幻灯片添加成功';
                } catch (PDOException $e) {
                    $error = '添加失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'edit':
            $id = (int)($_POST['id'] ?? 0);
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $image = trim($_POST['image'] ?? '');
            $link_url = trim($_POST['link_url'] ?? '');
            $link_text = trim($_POST['link_text'] ?? '');
            $link_target = $_POST['link_target'] ?? '_blank';
            $sort_order = (int)($_POST['sort_order'] ?? 0);
            $status = (int)($_POST['status'] ?? 1);
            
            if (empty($title) || empty($image)) {
                $error = '标题和图片不能为空';
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE slides SET title = ?, description = ?, image = ?, link_url = ?, link_text = ?, link_target = ?, sort_order = ?, status = ? WHERE id = ?");
                    $stmt->execute([$title, $description, $image, $link_url, $link_text, $link_target, $sort_order, $status, $id]);
                    $message = '幻灯片更新成功';
                } catch (PDOException $e) {
                    $error = '更新失败：' . $e->getMessage();
                }
            }
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM slides WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = '幻灯片删除成功';
                } catch (PDOException $e) {
                    $error = '删除失败：' . $e->getMessage();
                }
            }
            break;
    }
}

// 获取幻灯片列表
$slides = $pdo->query("SELECT * FROM slides ORDER BY sort_order ASC, id DESC")->fetchAll();

// 获取编辑的幻灯片信息
$edit_slide = null;
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM slides WHERE id = ?");
    $stmt->execute([$edit_id]);
    $edit_slide = $stmt->fetch();
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
        <h3 class="card-title"><?php echo $edit_slide ? '编辑幻灯片' : '添加幻灯片'; ?></h3>
    </div>
    <div class="card-body">
        <form method="post" action="">
            <input type="hidden" name="action" value="<?php echo $edit_slide ? 'edit' : 'add'; ?>">
            <?php if ($edit_slide): ?>
                <input type="hidden" name="id" value="<?php echo $edit_slide['id']; ?>">
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">幻灯片标题 *</label>
                    <input type="text" name="title" class="form-control" 
                           value="<?php echo h($edit_slide['title'] ?? ''); ?>" 
                           placeholder="请输入幻灯片标题" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">图片地址 *</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="url" name="image" id="image-input" class="form-control" 
                               value="<?php echo h($edit_slide['image'] ?? ''); ?>" 
                               placeholder="https://example.com/image.jpg" required style="flex: 1;">
                        <button type="button" class="btn btn-success" onclick="triggerImageUpload()"
                                id="upload-btn" style="padding: 8px 12px; white-space: nowrap;">
                            📁 上传图片
                        </button>
                    </div>
                    <input type="file" id="image-file-input" accept="image/*" style="display: none;">
                    <small class="form-text text-muted">支持上传 JPG、PNG、GIF、WebP、BMP 格式图片，最大5MB</small>
                    <div id="image-preview" style="margin-top: 10px; display: none;">
                        <img id="preview-img" style="max-width: 200px; max-height: 120px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">幻灯片描述</label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="请输入幻灯片描述"><?php echo h($edit_slide['description'] ?? ''); ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">链接地址</label>
                    <input type="url" name="link_url" class="form-control" 
                           value="<?php echo h($edit_slide['link_url'] ?? ''); ?>" 
                           placeholder="https://example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">链接文字</label>
                    <input type="text" name="link_text" class="form-control" 
                           value="<?php echo h($edit_slide['link_text'] ?? ''); ?>" 
                           placeholder="了解更多">
                </div>
                
                <div class="form-group">
                    <label class="form-label">链接打开方式</label>
                    <select name="link_target" class="form-control">
                        <option value="_blank" <?php echo ($edit_slide && $edit_slide['link_target'] == '_blank') ? 'selected' : ''; ?>>新窗口</option>
                        <option value="_self" <?php echo ($edit_slide && $edit_slide['link_target'] == '_self') ? 'selected' : ''; ?>>当前窗口</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">排序</label>
                    <input type="number" name="sort_order" class="form-control" 
                           value="<?php echo $edit_slide['sort_order'] ?? 0; ?>" 
                           placeholder="数字越小排序越靠前">
                </div>
                
                <?php if ($edit_slide): ?>
                <div class="form-group">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-control">
                        <option value="1" <?php echo ($edit_slide['status'] == 1) ? 'selected' : ''; ?>>启用</option>
                        <option value="0" <?php echo ($edit_slide['status'] == 0) ? 'selected' : ''; ?>>禁用</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <?php echo $edit_slide ? '更新幻灯片' : '添加幻灯片'; ?>
                </button>
                <?php if ($edit_slide): ?>
                    <a href="slides.php" class="btn btn-secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">幻灯片列表</h3>
        <div>
            <span class="text-muted">共 <?php echo count($slides); ?> 个幻灯片</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($slides)): ?>
            <div class="text-center text-muted" style="padding: 40px;">
                <p>暂无幻灯片数据</p>
                <p>请先添加一个幻灯片</p>
            </div>
        <?php else: ?>
            <!-- 竖排显示幻灯片 -->
            <div style="display: grid; gap: 20px;">
                <?php foreach ($slides as $slide): ?>
                    <div style="border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: white;">
                        <div style="display: flex; gap: 20px;">
                            <!-- 图片预览 -->
                            <div style="width: 200px; height: 120px; flex-shrink: 0;">
                                <img src="<?php echo h($slide['image']); ?>" 
                                     style="width: 100%; height: 100%; object-fit: cover;" 
                                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjEyMCIgdmlld0JveD0iMCAwIDIwMCAxMjAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMTIwIiBmaWxsPSIjRjVGNUY1Ii8+Cjx0ZXh0IHg9IjEwMCIgeT0iNjAiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPuWbvueJh+WKoOi9veWksei0pTwvdGV4dD4KPC9zdmc+'">
                            </div>
                            
                            <!-- 内容信息 -->
                            <div style="flex: 1; padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                                    <div style="flex: 1;">
                                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                                            <h4 style="margin: 0; color: #2c3e50; font-size: 18px;">
                                                <?php echo h($slide['title']); ?>
                                            </h4>
                                            <span class="badge <?php echo $slide['status'] ? 'badge-success' : 'badge-danger'; ?>">
                                                <?php echo $slide['status'] ? '启用' : '禁用'; ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($slide['description']): ?>
                                            <p style="margin: 0 0 10px 0; color: #666; line-height: 1.5;">
                                                <?php echo h($slide['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if ($slide['link_url']): ?>
                                            <div style="margin-bottom: 10px;">
                                                <a href="<?php echo h($slide['link_url']); ?>" target="<?php echo h($slide['link_target']); ?>" 
                                                   style="color: #3498db; text-decoration: none; font-size: 14px;">
                                                    🔗 <?php echo h($slide['link_text'] ?: $slide['link_url']); ?>
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div style="display: flex; gap: 20px; font-size: 14px; color: #666;">
                                            <span>ID: <?php echo $slide['id']; ?></span>
                                            <span>排序: <?php echo $slide['sort_order']; ?></span>
                                            <span>创建时间: <?php echo date('Y-m-d H:i', strtotime($slide['created_at'])); ?></span>
                                        </div>
                                    </div>
                                    
                                    <div style="display: flex; gap: 8px; margin-left: 15px;">
                                        <a href="slides.php?edit=<?php echo $slide['id']; ?>" 
                                           class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">
                                            编辑
                                        </a>
                                        
                                        <form method="post" style="display: inline;" 
                                              onsubmit="return confirm('确定要删除这个幻灯片吗？');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $slide['id']; ?>">
                                            <button type="submit" class="btn btn-danger" 
                                                    style="font-size: 12px; padding: 6px 12px;">
                                                删除
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// 添加图片上传的JavaScript
$additional_js = '
<script>
let isUploading = false;

// 触发文件选择
function triggerImageUpload() {
    if (isUploading) {
        return;
    }
    document.getElementById("image-file-input").click();
}

// 处理文件上传
document.addEventListener("DOMContentLoaded", function() {
    const fileInput = document.getElementById("image-file-input");
    const imageInput = document.getElementById("image-input");
    const uploadBtn = document.getElementById("upload-btn");
    const imagePreview = document.getElementById("image-preview");
    const previewImg = document.getElementById("preview-img");
    
    // 监听文件选择
    fileInput.addEventListener("change", function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // 验证文件类型
        const allowedTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif", "image/webp", "image/bmp"];
        if (!allowedTypes.includes(file.type)) {
            alert("只支持上传 JPG、PNG、GIF、WebP、BMP 格式的图片文件");
            fileInput.value = "";
            return;
        }
        
        // 验证文件大小（5MB）
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            alert("图片文件大小不能超过5MB");
            fileInput.value = "";
            return;
        }
        
        // 显示预览
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            imagePreview.style.display = "block";
        };
        reader.readAsDataURL(file);
        
        // 上传文件
        uploadImage(file);
    });
    
    // 监听图片地址输入框变化，显示预览
    imageInput.addEventListener("input", function() {
        const url = imageInput.value.trim();
        if (url && isValidImageUrl(url)) {
            previewImg.src = url;
            imagePreview.style.display = "block";
        } else {
            imagePreview.style.display = "none";
        }
    });
    
    // 页面加载时显示现有图片预览
    const currentImageUrl = imageInput.value.trim();
    if (currentImageUrl && isValidImageUrl(currentImageUrl)) {
        previewImg.src = currentImageUrl;
        imagePreview.style.display = "block";
    }
});

// 上传图片到服务器
async function uploadImage(file) {
    const uploadBtn = document.getElementById("upload-btn");
    const imageInput = document.getElementById("image-input");
    
    isUploading = true;
    uploadBtn.disabled = true;
    uploadBtn.innerHTML = "⏳ 上传中...";
    
    try {
        const formData = new FormData();
        formData.append("image", file);
        
        const response = await fetch("../api/upload-image.php", {
            method: "POST",
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // 上传成功，更新图片地址
            imageInput.value = data.url;
            showMessage(`图片上传成功！文件名：${data.filename}`, "success");
        } else {
            alert("上传失败：" + (data.error || "未知错误"));
        }
    } catch (error) {
        console.error("上传失败:", error);
        alert("上传失败：网络错误或服务器无响应");
    } finally {
        isUploading = false;
        uploadBtn.disabled = false;
        uploadBtn.innerHTML = "📁 上传图片";
        
        // 清空文件输入框
        document.getElementById("image-file-input").value = "";
    }
}

// 验证是否为有效的图片URL
function isValidImageUrl(url) {
    try {
        new URL(url);
        const imageExtensions = [".jpg", ".jpeg", ".png", ".gif", ".webp", ".bmp"];
        const lowerUrl = url.toLowerCase();
        return imageExtensions.some(ext => lowerUrl.includes(ext)) || lowerUrl.includes("image");
    } catch (e) {
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
</script>';

renderAdminLayout('幻灯片管理', $content, 'slides', '', $additional_js);
?>
