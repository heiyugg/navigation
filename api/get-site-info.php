<?php
/**
 * 获取网站信息API
 * 自动获取网站的标题、描述和图标
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

// 获取POST数据
$input = json_decode(file_get_contents('php://input'), true);
$url = $input['url'] ?? '';

if (empty($url)) {
    echo json_encode(['error' => 'URL不能为空']);
    exit;
}

// 验证URL格式
if (!filter_var($url, FILTER_VALIDATE_URL)) {
    echo json_encode(['error' => 'URL格式不正确']);
    exit;
}

/**
 * 获取网站信息
 */
function getSiteInfo($url) {
    $result = [
        'title' => '',
        'description' => '',
        'icon' => '',
        'error' => ''
    ];
    
    try {
        // 设置用户代理，模拟浏览器访问
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
                    'Accept-Encoding: identity',
                    'Cache-Control: no-cache',
                    'Connection: close'
                ],
                'timeout' => 15,
                'follow_location' => true,
                'max_redirects' => 5
            ]
        ]);
        
        // 获取网页内容
        $html = @file_get_contents($url, false, $context);
        
        if ($html === false) {
            $result['error'] = '无法访问该网站';
            return $result;
        }
        
        // 检测和处理编码
        $encoding = mb_detect_encoding($html, ['UTF-8', 'GBK', 'GB2312', 'BIG5', 'ISO-8859-1'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $html = mb_convert_encoding($html, 'UTF-8', $encoding);
        }
        
        // 使用正则表达式先提取基本信息，避免DOM解析问题
        
        // 获取标题 - 多种方式
        $title = '';
        
        // 方式1: 正则匹配title标签
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // 方式2: 如果没有获取到，尝试og:title
        if (empty($title) && preg_match('/<meta[^>]*property=["\']og:title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // 方式3: 尝试其他title meta标签
        if (empty($title) && preg_match('/<meta[^>]*name=["\']title["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $title = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        $result['title'] = $title;
        
        // 获取描述 - 多种方式
        $description = '';
        
        // 方式1: og:description
        if (preg_match('/<meta[^>]*property=["\']og:description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // 方式2: name="description"
        if (empty($description) && preg_match('/<meta[^>]*name=["\']description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // 方式3: name="Description" (大写D)
        if (empty($description) && preg_match('/<meta[^>]*name=["\']Description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        // 方式4: twitter:description
        if (empty($description) && preg_match('/<meta[^>]*name=["\']twitter:description["\'][^>]*content=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $description = trim(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'));
        }
        
        $result['description'] = $description;
        
        // 使用DOM解析作为备用方案
        if (empty($result['title']) || empty($result['description'])) {
            try {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                
                // 获取标题
                if (empty($result['title'])) {
                    $titleNodes = $xpath->query('//title');
                    if ($titleNodes->length > 0) {
                        $result['title'] = trim($titleNodes->item(0)->textContent);
                    }
                }
                
                // 获取描述
                if (empty($result['description'])) {
                    $descriptionSelectors = [
                        '//meta[@property="og:description"]/@content',
                        '//meta[@name="description"]/@content',
                        '//meta[@name="Description"]/@content',
                        '//meta[@name="twitter:description"]/@content'
                    ];
                    
                    foreach ($descriptionSelectors as $selector) {
                        $nodes = $xpath->query($selector);
                        if ($nodes->length > 0) {
                            $desc = trim($nodes->item(0)->value);
                            if (!empty($desc)) {
                                $result['description'] = $desc;
                                break;
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // DOM解析失败，继续使用正则表达式的结果
            }
        }
        
        // 获取图标 - 使用正则表达式
        $iconUrl = '';
        
        // 方式1: apple-touch-icon
        if (preg_match('/<link[^>]*rel=["\']apple-touch-icon["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $iconUrl = trim($matches[1]);
        }
        
        // 方式2: apple-touch-icon-precomposed
        if (empty($iconUrl) && preg_match('/<link[^>]*rel=["\']apple-touch-icon-precomposed["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $iconUrl = trim($matches[1]);
        }
        
        // 方式3: icon
        if (empty($iconUrl) && preg_match('/<link[^>]*rel=["\']icon["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $iconUrl = trim($matches[1]);
        }
        
        // 方式4: shortcut icon
        if (empty($iconUrl) && preg_match('/<link[^>]*rel=["\']shortcut icon["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $iconUrl = trim($matches[1]);
        }
        
        // 方式5: favicon
        if (empty($iconUrl) && preg_match('/<link[^>]*rel=["\']favicon["\'][^>]*href=["\']([^"\']*)["\'][^>]*>/i', $html, $matches)) {
            $iconUrl = trim($matches[1]);
        }
        
        // 使用DOM解析作为图标获取的备用方案
        if (empty($iconUrl)) {
            try {
                $dom = new DOMDocument();
                libxml_use_internal_errors(true);
                $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
                libxml_clear_errors();
                
                $xpath = new DOMXPath($dom);
                
                $iconSelectors = [
                    '//link[@rel="apple-touch-icon"]/@href',
                    '//link[@rel="apple-touch-icon-precomposed"]/@href',
                    '//link[@rel="icon"]/@href',
                    '//link[@rel="shortcut icon"]/@href',
                    '//link[@rel="favicon"]/@href'
                ];
                
                foreach ($iconSelectors as $selector) {
                    $nodes = $xpath->query($selector);
                    if ($nodes->length > 0) {
                        $iconUrl = trim($nodes->item(0)->value);
                        if (!empty($iconUrl)) {
                            break;
                        }
                    }
                }
            } catch (Exception $e) {
                // DOM解析失败，继续
            }
        }
        
        // 如果没有找到图标，尝试默认的favicon.ico
        if (empty($iconUrl)) {
            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $iconUrl = $baseUrl . '/favicon.ico';
        }
        
        // 处理相对URL
        if (!empty($iconUrl) && !filter_var($iconUrl, FILTER_VALIDATE_URL)) {
            $parsedUrl = parse_url($url);
            $baseUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            
            if (strpos($iconUrl, '//') === 0) {
                // 协议相对URL
                $iconUrl = $parsedUrl['scheme'] . ':' . $iconUrl;
            } elseif (strpos($iconUrl, '/') === 0) {
                // 绝对路径
                $iconUrl = $baseUrl . $iconUrl;
            } else {
                // 相对路径
                $basePath = dirname($parsedUrl['path'] ?? '/');
                $iconUrl = $baseUrl . $basePath . '/' . $iconUrl;
            }
        }
        
        // 验证图标URL是否可访问
        if (!empty($iconUrl)) {
            $iconContext = stream_context_create([
                'http' => [
                    'method' => 'HEAD',
                    'timeout' => 5,
                    'follow_location' => true
                ]
            ]);
            
            $headers = @get_headers($iconUrl, 1, $iconContext);
            if ($headers && strpos($headers[0], '200') !== false) {
                $result['icon'] = $iconUrl;
            }
        }
        
        // 清理数据
        $result['title'] = htmlspecialchars_decode($result['title'], ENT_QUOTES);
        $result['description'] = htmlspecialchars_decode($result['description'], ENT_QUOTES);
        
        // 限制长度
        if (mb_strlen($result['title']) > 100) {
            $result['title'] = mb_substr($result['title'], 0, 100) . '...';
        }
        if (mb_strlen($result['description']) > 200) {
            $result['description'] = mb_substr($result['description'], 0, 200) . '...';
        }
        
    } catch (Exception $e) {
        $result['error'] = '获取网站信息失败：' . $e->getMessage();
    }
    
    return $result;
}

// 获取网站信息
$siteInfo = getSiteInfo($url);

// 返回JSON响应
echo json_encode($siteInfo, JSON_UNESCAPED_UNICODE);
?>
