<section class="main-links-section">
    <?php 
    $grouped_links = getAllLinksGrouped();
    if (!empty($grouped_links)): 
    ?>
        <?php foreach ($grouped_links as $group): ?>
            <div class="category-group" id="category-<?php echo $group['category']['id']; ?>">
                <div class="category-header">
                    <h2 class="category-title">
                        <?php if (!empty($group['category']['icon'])): ?>
                            <?php echo renderCategoryIcon($group['category']['icon'], '24px'); ?>
                        <?php endif; ?>
                        <?php echo h($group['category']['name']); ?>
                    </h2>
                    <?php if (!empty($group['category']['description'])): ?>
                        <p class="category-description"><?php echo h($group['category']['description']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="link-cards-container">
                    <?php foreach ($group['links'] as $link): ?>
                        <div class="link-card main-card">
                            <a href="<?php echo h($link['url']); ?>" 
                               target="<?php echo !empty($link['target']) ? h($link['target']) : '_blank'; ?>" 
                               class="card-link"
                               onclick="updateVisitCount(<?php echo $link['id']; ?>)">
                                
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
                                    <?php if ($link['is_recommended']): ?>
                                        <span class="recommended-badge">推荐</span>
                                    <?php endif; ?>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="no-content">
            <div class="empty-state">
                <h3>暂无链接内容</h3>
                <p>请在后台管理中添加分类和链接。</p>
                <?php if (isAdminLoggedIn()): ?>
                    <a href="admin/" class="btn btn-primary">前往管理后台</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</section>
