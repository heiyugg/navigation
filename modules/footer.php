<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-content">
            <div class="footer-row">
                <!-- 版权信息 -->
                <div class="footer-item copyright">
                    <span><?php echo h($site_config['copyright'] ?? '© 2024 导航站'); ?></span>
                </div>
                
                <!-- ICP备案 -->
                <?php if (!empty($site_config['icp_number'])): ?>
                    <div class="footer-item icp">
                        <span class="separator">|</span>
                        <a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">
                            <?php echo h($site_config['icp_number']); ?>
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- 公安备案 -->
                <?php if (!empty($site_config['police_number'])): ?>
                    <div class="footer-item police">
                        <span class="separator">|</span>
                        <a href="<?php 
                            // 从公安备案号中提取数字部分作为recordcode
                            $police_number = $site_config['police_number'];
                            $recordcode = '';
                            if (preg_match('/(\d+)/', $police_number, $matches)) {
                                $recordcode = $matches[1];
                            }
                            echo $recordcode ? 'http://www.beian.gov.cn/portal/registerSystemInfo?recordcode=' . $recordcode : 'http://www.beian.gov.cn/';
                        ?>" target="_blank" rel="noopener">
                            <?php echo h($site_config['police_number']); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 网站运行时间 -->
            <div class="footer-row">
                <div class="footer-item runtime">
                    <span>网站运行: <span id="runtime-display"><?php echo getRunningTime(); ?></span></span>
                </div>
            </div>
            
            <!-- GitHub版权地址 -->
            <div class="footer-row">
                <div class="footer-item github">
                    <a href="https://github.com/heiyugg/navigation" target="_blank" rel="noopener">
                        <svg class="github-icon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                        <span class="github-text">开源项目</span>
                        <span class="github-separator">·</span>
                        <span class="github-repo">heiyugg/navigation</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- 友情链接或其他信息 -->
        <?php if (!empty($site_config['footer_links'])): ?>
            <div class="footer-links">
                <?php 
                $footer_links = json_decode($site_config['footer_links'], true);
                if (is_array($footer_links)):
                ?>
                    <?php foreach ($footer_links as $link): ?>
                        <a href="<?php echo h($link['url']); ?>" 
                           target="<?php echo !empty($link['target']) ? h($link['target']) : '_blank'; ?>"
                           class="footer-link">
                            <?php echo h($link['title']); ?>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</footer>

<script>
// 实时更新运行时间
function updateRuntime() {
    <?php
    // 获取建站时间，优先使用用户设置的时间
    $pdo = getDatabase();
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_start_date'");
    $stmt->execute();
    $site_start_date = $stmt->fetchColumn();
    
    if ($site_start_date) {
        $start_time = $site_start_date . ' 00:00:00';
    } else {
        $start_time = $site_config['install_time'] ?? date('Y-m-d H:i:s');
    }
    ?>
    const startTime = new Date('<?php echo $start_time; ?>').getTime();
    const now = new Date().getTime();
    const diff = now - startTime;
    
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    
    document.getElementById('runtime-display').textContent = `${days}天${hours}小时${minutes}分钟${seconds}秒`;
}

// 每秒更新一次
setInterval(updateRuntime, 1000);
</script>
