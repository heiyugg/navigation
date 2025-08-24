<?php
/**
 * 图片上传API
 * 支持常见图片格式的上传
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '只允许POST请求']);
    exit;
}

// 检查是否有文件上传
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => '请选择要上传的图片文件']);
    exit;
}

$file = $_FILES['image'];

// 验证文件大小（最大5MB）
$maxSize = 5 * 1024 * 1024; // 5MB
if ($file['size'] > $maxSize) {
    echo json_encode(['error' => '图片文件大小不能超过5MB']);
    exit;
}

// 验证文件类型
$allowedTypes = [
    'image/jpeg',
    'image/jpg', 
    'image/png',
    'image/gif',
    'image/webp',
    'image/bmp'
];

$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

$fileType = $file['type'];
$fileName = $file['name'];
$fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

if (!in_array($fileType, $allowedTypes) || !in_array($fileExtension, $allowedExtensions)) {
    echo json_encode(['error' => '只支持上传 JPG、PNG、GIF、WebP、BMP 格式的图片文件']);
    exit;
}

// 创建上传目录
$uploadDir = '../uploads/slides/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        echo json_encode(['error' => '无法创建上传目录']);
        exit;
    }
}

// 生成唯一文件名
$newFileName = date('Y-m-d_H-i-s') . '_' . uniqid() . '.' . $fileExtension;
$uploadPath = $uploadDir . $newFileName;

// 移动上传的文件
if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
    echo json_encode(['error' => '文件上传失败']);
    exit;
}

// 生成完整的URL地址
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . '://' . $host;

// 获取当前脚本的目录路径，然后构建完整URL
$scriptDir = dirname($_SERVER['SCRIPT_NAME']); // /api
$projectDir = dirname($scriptDir); // 项目根目录
$imageUrl = $baseUrl . $projectDir . '/uploads/slides/' . $newFileName;

echo json_encode([
    'success' => true,
    'url' => $imageUrl,
    'filename' => $newFileName,
    'size' => $file['size'],
    'type' => $fileType
], JSON_UNESCAPED_UNICODE);
?>
