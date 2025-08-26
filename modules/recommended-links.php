<section class="recommended-section" id="recommended">
    <div class="section-header">
        <h2 class="section-title">推荐链接</h2>
    </div>
    
    <div class="recommended-links">
        <?php if (!empty($recommended_links)): ?>
            <div class="link-cards-container">
                <?php foreach ($recommended_links as $link): ?>
                    <div class="link-card recommended-card">
                        <a href="go.php?id=<?php echo $link['id']; ?>" 
                           target="<?php echo !empty($link['target']) ? h($link['target']) : '_blank'; ?>" 
                           class="card-link"
                           title="<?php echo h($link['url']); ?>">
                            
                            <?php if (!empty($link['icon'])): ?>
                                <div class="card-icon">
                                    <?php echo renderLinkIcon($link['icon'], '32px'); ?>
                                </div>
                            <?php else: ?>
                                <div class="card-icon default-icon">
                                    <span><?php echo mb_substr($link['title'], 0, 1, 'UTF-8'); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-content">
                                <h3 class="card-title"><?php echo h($link['title']); ?></h3>
                                <?php if (!empty($link['description'])): ?>
                                    <p class="card-description"><?php echo h($link['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-meta">
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-links">
                <p>暂无推荐链接，请在后台管理中添加。</p>
            </div>
        <?php endif; ?>
    </div>
</section>
