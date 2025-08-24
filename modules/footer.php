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
