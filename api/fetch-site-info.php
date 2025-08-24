<?php
/**
 * 获取网站信息API
 * 通过URL获取网站的标题、描述和图标
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// 只允许POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '只允许POST请求']);
    exit;
}

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true);
$url = trim($input['url'] ?? '');

if (empty($url)) {
    echo json_encode(['success' => false, 'message' => '请提供URL']);
    exit;
}

if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['success' => false, 'message' => '无效的URL格式']);
    exit;
}

try {
    // 获取网站信息
    $siteInfo = fetchWebsiteInfo($url);
    
    if ($siteInfo) {
        echo json_encode([
            'success' => true,
            'title' => $siteInfo['title'],
            'description' => $siteInfo['description'],
            'icon' => $siteInfo['icon']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '无法获取网站信息']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => '获取失败: ' . $e->getMessage()]);
}

/**
 * 获取网站信息
 */
function fetchWebsiteInfo($url) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'follow_location' => true,
            'max_redirects' => 3
        ]
    ]);
    
    $html = @file_get_contents($url, false, $context);
    
    if ($html === false) {
        // 尝试使用cURL
        if (function_exists('curl_init')) {
            return fetchWithCurl($url);
        }
        return false;
    }
    
    return parseHtmlContent($html, $url);
}

/**
 * 使用cURL获取网站信息
 */
function fetchWithCurl($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($html === false || $httpCode >= 400) {
        return false;
    }
    
    return parseHtmlContent($html, $url);
}

/**
 * 解析HTML内容
 */
function parseHtmlContent($html, $url) {
    // 转换编码
    $html = mb_convert_encoding($html, 'UTF-8', 'auto');
    
    $info = [
        'title' => '',
        'description' => '',
        'icon' => ''
    ];
    
    // 创建DOMDocument
    $dom = new DOMDocument();
    @$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    
    // 获取标题
    $titleNodes = $dom->getElementsByTagName('title');
    if ($titleNodes->length > 0) {
        $info['title'] = trim($titleNodes->item(0)->textContent);
    }
    
    // 获取meta标签信息
    $metaTags = $dom->getElementsByTagName('meta');
    foreach ($metaTags as $meta) {
        $name = $meta->getAttribute('name');
        $property = $meta->getAttribute('property');
        $content = $meta->getAttribute('content');
        
        if (empty($content)) continue;
        
        // 获取描述
        if (empty($info['description'])) {
            if (in_array($name, ['description', 'Description']) || 
                in_array($property, ['og:description', 'twitter:description'])) {
                $info['description'] = trim($content);
            }
        }
        
        // 获取图标
        if (empty($info['icon'])) {
            if (in_array($property, ['og:image', 'twitter:image'])) {
                $info['icon'] = resolveUrl($content, $url);
            }
        }
    }
    
    // 如果没有找到图标，尝试查找favicon
    if (empty($info['icon'])) {
        $linkTags = $dom->getElementsByTagName('link');
        foreach ($linkTags as $link) {
            $rel = $link->getAttribute('rel');
            $href = $link->getAttribute('href');
            
            if (in_array($rel, ['icon', 'shortcut icon', 'apple-touch-icon']) && !empty($href)) {
                $info['icon'] = resolveUrl($href, $url);
                break;
            }
        }
    }
    
    // 如果还是没有图标，尝试默认favicon路径
    if (empty($info['icon'])) {
        $parsedUrl = parse_url($url);
        $faviconUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . '/favicon.ico';
        if (checkUrlExists($faviconUrl)) {
            $info['icon'] = $faviconUrl;
        }
    }
    
    // 清理和限制长度
    $info['title'] = mb_substr(trim($info['title']), 0, 100);
    $info['description'] = mb_substr(trim($info['description']), 0, 500);
    
    return $info;
}

/**
 * 解析相对URL为绝对URL
 */
function resolveUrl($relativeUrl, $baseUrl) {
    if (filter_var($relativeUrl, FILTER_VALIDATE_URL)) {
        return $relativeUrl;
    }
    
    $parsedBase = parse_url($baseUrl);
    
    if (strpos($relativeUrl, '//') === 0) {
        return $parsedBase['scheme'] . ':' . $relativeUrl;
    }
    
    if (strpos($relativeUrl, '/') === 0) {
        return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $relativeUrl;
    }
    
    $basePath = isset($parsedBase['path']) ? dirname($parsedBase['path']) : '';
    if ($basePath === '.') $basePath = '';
    
    return $parsedBase['scheme'] . '://' . $parsedBase['host'] . $basePath . '/' . $relativeUrl;
}

/**
 * 检查URL是否存在
 */
function checkUrlExists($url) {
    $headers = @get_headers($url, 1);
    return $headers && strpos($headers[0], '200') !== false;
}
?>
