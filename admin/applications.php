<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

// 检查系统是否已安装
checkInstallation();
require_once 'layout.php';

// 检查管理员登录
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
    $application_id = (int)($_POST['application_id'] ?? 0);
    
    if ($application_id > 0) {
        switch ($action) {
            case 'approve':
                try {
                    // 获取申请信息
                    $stmt = $pdo->prepare("SELECT * FROM link_applications WHERE id = ? AND status = 0");
                    $stmt->execute([$application_id]);
                    $application = $stmt->fetch();
                    
                    if ($application) {
                        $pdo->beginTransaction();
                        
                        // 添加到链接表
                        $stmt = $pdo->prepare("
                            INSERT INTO links (title, url, description, category_id, icon, status, is_recommended, visits, created_at) 
                            VALUES (?, ?, ?, ?, ?, 1, 0, 0, NOW())
                        ");
                        $stmt->execute([
                            $application['title'],
                            $application['url'],
                            $application['description'],
                            $application['category_id'],
                            $application['icon']
                        ]);
                        
                        // 更新申请状态
                        $stmt = $pdo->prepare("
                            UPDATE link_applications 
                            SET status = 1, processed_at = NOW(), processed_by = ? 
                            WHERE id = ?
                        ");
                        $stmt->execute([$_SESSION['admin_username'], $application_id]);
                        
                        $pdo->commit();
                        $message = '申请已通过，链接已添加到网站！';
                    } else {
                        $error = '申请不存在或已处理';
                    }
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = '处理失败：' . $e->getMessage();
                }
                break;
                
            case 'reject':
                $admin_note = trim($_POST['admin_note'] ?? '');
                try {
                    $stmt = $pdo->prepare("
                        UPDATE link_applications 
                        SET status = 2, admin_note = ?, processed_at = NOW(), processed_by = ? 
                        WHERE id = ? AND status = 0
                    ");
                    $stmt->execute([$admin_note, $_SESSION['admin_username'], $application_id]);
                    
                    if ($stmt->rowCount() > 0) {
                        $message = '申请已拒绝！';
                    } else {
                        $error = '申请不存在或已处理';
                    }
                } catch (PDOException $e) {
                    $error = '处理失败：' . $e->getMessage();
                }
                break;
                
            case 'delete':
                try {
                    $stmt = $pdo->prepare("DELETE FROM link_applications WHERE id = ?");
                    $stmt->execute([$application_id]);
                    $message = '申请记录已删除！';
                } catch (PDOException $e) {
                    $error = '删除失败：' . $e->getMessage();
                }
                break;
        }
    }
}

// 获取筛选参数
$status_filter = $_GET['status'] ?? 'all';
$category_filter = (int)($_GET['category'] ?? 0);

// 构建查询条件
$where_conditions = [];
$params = [];

if ($status_filter !== 'all') {
    $where_conditions[] = "la.status = ?";
    $params[] = (int)$status_filter;
}

if ($category_filter > 0) {
    $where_conditions[] = "la.category_id = ?";
    $params[] = $category_filter;
}

$where_clause = empty($where_conditions) ? '' : 'WHERE ' . implode(' AND ', $where_conditions);

// 获取申请列表
$sql = "
    SELECT la.*, c.name as category_name, c.icon as category_icon
    FROM link_applications la
    LEFT JOIN categories c ON la.category_id = c.id
    {$where_clause}
    ORDER BY la.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$applications = $stmt->fetchAll();

// 获取统计数据
$stats = [];
$stats['pending'] = $pdo->query("SELECT COUNT(*) FROM link_applications WHERE status = 0")->fetchColumn();
$stats['approved'] = $pdo->query("SELECT COUNT(*) FROM link_applications WHERE status = 1")->fetchColumn();
$stats['rejected'] = $pdo->query("SELECT COUNT(*) FROM link_applications WHERE status = 2")->fetchColumn();
$stats['total'] = $stats['pending'] + $stats['approved'] + $stats['rejected'];

// 获取分类列表
$categories = $pdo->query("SELECT id, name FROM categories WHERE status = 1 ORDER BY sort_order ASC")->fetchAll();

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-success"><?php echo h($message); ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?php echo h($error); ?></div>
<?php endif; ?>

<!-- 统计卡片 -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #f39c12; margin-bottom: 5px;">
                <?php echo $stats['pending']; ?>
            </div>
            <div style="color: #666; font-size: 14px;">待审核</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #27ae60; margin-bottom: 5px;">
                <?php echo $stats['approved']; ?>
            </div>
            <div style="color: #666; font-size: 14px;">已通过</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #e74c3c; margin-bottom: 5px;">
                <?php echo $stats['rejected']; ?>
            </div>
            <div style="color: #666; font-size: 14px;">已拒绝</div>
        </div>
    </div>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 32px; font-weight: bold; color: #3498db; margin-bottom: 5px;">
                <?php echo $stats['total']; ?>
            </div>
            <div style="color: #666; font-size: 14px;">总申请</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">链接申请管理</h3>
        <div style="display: flex; gap: 15px; align-items: center;">
            <!-- 筛选器 -->
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <select name="status" class="form-control" style="width: auto;" onchange="this.form.submit()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>全部状态</option>
                    <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>待审核</option>
                    <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>已通过</option>
                    <option value="2" <?php echo $status_filter === '2' ? 'selected' : ''; ?>>已拒绝</option>
                </select>
                
                <select name="category" class="form-control" style="width: auto;" onchange="this.form.submit()">
                    <option value="0" <?php echo $category_filter === 0 ? 'selected' : ''; ?>>全部分类</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_filter === $category['id'] ? 'selected' : ''; ?>>
                            <?php echo h($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            
            <span class="text-muted">共 <?php echo count($applications); ?> 条记录</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($applications)): ?>
            <div class="text-center" style="padding: 40px 0;">
                <p>暂无申请记录</p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="table" style="margin: 0;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">ID</th>
                            <th style="min-width: 150px;">链接信息</th>
                            <th style="width: 100px;">分类</th>
                            <th style="min-width: 200px;">申请人信息</th>
                            <th style="min-width: 150px;">申请理由</th>
                            <th style="width: 80px;">状态</th>
                            <th style="width: 120px;">申请时间</th>
                            <th style="width: 200px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td style="font-weight: 600; color: #666;">
                                    <?php echo $app['id']; ?>
                                </td>
                                <td>
                                    <div style="margin-bottom: 8px;">
                                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                            <?php if ($app['icon']): ?>
                                                <?php echo renderCategoryIcon($app['icon'], '16px'); ?>
                                            <?php endif; ?>
                                            <strong style="color: #2c3e50;"><?php echo h($app['title']); ?></strong>
                                        </div>
                                        <div style="font-size: 12px; color: #666; margin-bottom: 4px;">
                                            <a href="<?php echo h($app['url']); ?>" target="_blank" style="color: #3498db;">
                                                <?php echo h($app['url']); ?>
                                            </a>
                                        </div>
                                        <?php if ($app['description']): ?>
                                            <div style="font-size: 12px; color: #666; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo h($app['description']); ?>">
                                                <?php echo h($app['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($app['category_name']): ?>
                                        <div style="display: flex; align-items: center; gap: 5px;">
                                            <?php if ($app['category_icon']): ?>
                                                <?php echo renderCategoryIcon($app['category_icon'], '14px'); ?>
                                            <?php endif; ?>
                                            <span style="font-size: 12px;"><?php echo h($app['category_name']); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #ccc;">未分类</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 12px;">
                                        <div style="margin-bottom: 2px;"><strong><?php echo h($app['applicant_name']); ?></strong></div>
                                        <div style="color: #666; margin-bottom: 2px;"><?php echo h($app['applicant_email']); ?></div>
                                        <?php if ($app['applicant_contact']): ?>
                                            <div style="color: #666;"><?php echo h($app['applicant_contact']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($app['reason']): ?>
                                        <div style="font-size: 12px; color: #666; max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo h($app['reason']); ?>">
                                            <?php echo h($app['reason']); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: #ccc;">无</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [0 => 'badge-warning', 1 => 'badge-success', 2 => 'badge-danger'];
                                    $status_texts = [0 => '待审核', 1 => '已通过', 2 => '已拒绝'];
                                    ?>
                                    <span class="badge <?php echo $status_colors[$app['status']]; ?>" style="font-size: 10px;">
                                        <?php echo $status_texts[$app['status']]; ?>
                                    </span>
                                    <?php if ($app['processed_at']): ?>
                                        <div style="font-size: 10px; color: #666; margin-top: 2px;">
                                            <?php echo date('m-d H:i', strtotime($app['processed_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: #666;">
                                    <?php echo date('m-d H:i', strtotime($app['created_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px; flex-wrap: wrap;">
                                        <?php if ($app['status'] == 0): ?>
                                            <!-- 待审核状态的操作 -->
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                                <button type="submit" class="btn btn-success" style="font-size: 11px; padding: 4px 8px;" 
                                                        onclick="return confirm('确定通过这个申请吗？');">通过</button>
                                            </form>
                                            
                                            <button type="button" class="btn btn-danger" style="font-size: 11px; padding: 4px 8px;" 
                                                    onclick="showRejectModal(<?php echo $app['id']; ?>)">拒绝</button>
                                        <?php endif; ?>
                                        
                                        <button type="button" class="btn btn-secondary" style="font-size: 11px; padding: 4px 8px;" 
                                                onclick="showDetailModal(<?php echo $app['id']; ?>)">详情</button>
                                        
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                            <button type="submit" class="btn btn-danger" style="font-size: 11px; padding: 4px 8px;" 
                                                    onclick="return confirm('确定删除这个申请记录吗？');">删除</button>
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

<!-- 拒绝申请模态框 -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; padding: 30px; max-width: 500px; width: 90%;">
        <h3 style="margin: 0 0 20px 0;">拒绝申请</h3>
        <form method="post" id="rejectForm">
            <input type="hidden" name="action" value="reject">
            <input type="hidden" name="application_id" id="rejectApplicationId">
            
            <div class="form-group">
                <label class="form-label">拒绝理由</label>
                <textarea name="admin_note" class="form-control" rows="4" placeholder="请说明拒绝的理由..."></textarea>
            </div>
            
            <div style="text-align: right; margin-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="hideRejectModal()" style="margin-right: 10px;">取消</button>
                <button type="submit" class="btn btn-danger">确认拒绝</button>
            </div>
        </form>
    </div>
</div>

<!-- 详情模态框 -->
<div id="detailModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; padding: 30px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
        <h3 style="margin: 0 0 20px 0;">申请详情</h3>
        <div id="detailContent"></div>
        <div style="text-align: right; margin-top: 20px;">
            <button type="button" class="btn btn-secondary" onclick="hideDetailModal()">关闭</button>
        </div>
    </div>
</div>

<script>
// 申请详情数据
const applicationDetails = <?php echo json_encode($applications, JSON_UNESCAPED_UNICODE); ?>;

function showRejectModal(applicationId) {
    document.getElementById('rejectApplicationId').value = applicationId;
    document.getElementById('rejectModal').style.display = 'block';
}

function hideRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

function showDetailModal(applicationId) {
    const app = applicationDetails.find(a => a.id == applicationId);
    if (!app) return;
    
    const statusTexts = {0: '待审核', 1: '已通过', 2: '已拒绝'};
    
    let html = `
        <div style="display: grid; gap: 15px;">
            <div>
                <strong>链接标题：</strong>${app.title}
            </div>
            <div>
                <strong>链接地址：</strong><a href="${app.url}" target="_blank" style="color: #3498db;">${app.url}</a>
            </div>
            ${app.description ? `<div><strong>链接描述：</strong>${app.description}</div>` : ''}
            <div>
                <strong>分类：</strong>${app.category_name || '未分类'}
            </div>
            ${app.icon ? `<div><strong>图标：</strong>${app.icon}</div>` : ''}
            <div>
                <strong>申请人：</strong>${app.applicant_name}
            </div>
            <div>
                <strong>邮箱：</strong>${app.applicant_email}
            </div>
            ${app.applicant_contact ? `<div><strong>联系方式：</strong>${app.applicant_contact}</div>` : ''}
            ${app.reason ? `<div><strong>申请理由：</strong>${app.reason}</div>` : ''}
            <div>
                <strong>状态：</strong>${statusTexts[app.status]}
            </div>
            <div>
                <strong>申请时间：</strong>${app.created_at}
            </div>
            ${app.processed_at ? `<div><strong>处理时间：</strong>${app.processed_at}</div>` : ''}
            ${app.processed_by ? `<div><strong>处理人：</strong>${app.processed_by}</div>` : ''}
            ${app.admin_note ? `<div><strong>管理员备注：</strong>${app.admin_note}</div>` : ''}
        </div>
    `;
    
    document.getElementById('detailContent').innerHTML = html;
    document.getElementById('detailModal').style.display = 'block';
}

function hideDetailModal() {
    document.getElementById('detailModal').style.display = 'none';
}

// 点击模态框外部关闭
document.getElementById('rejectModal').onclick = function(e) {
    if (e.target === this) hideRejectModal();
};

document.getElementById('detailModal').onclick = function(e) {
    if (e.target === this) hideDetailModal();
};
</script>

<?php
$content = ob_get_clean();
renderAdminLayout('链接申请管理', $content, 'applications');
?>
