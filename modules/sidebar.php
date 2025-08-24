<aside class="sidebar">
    <div class="sidebar-header">
        <h3>分类导航</h3>
    </div>
    
    <nav class="category-nav">
        <ul class="category-list">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <li class="category-item">
                        <a href="#category-<?php echo $category['id']; ?>" 
                           class="category-link" 
                           data-category="<?php echo $category['id']; ?>">
                            <?php if (!empty($category['icon'])): ?>
                                <?php echo renderCategoryIcon($category['icon'], '18px'); ?>
                            <?php endif; ?>
                            <span class="category-name"><?php echo h($category['name']); ?></span>
                            <?php 
                            $link_count = count(getLinksByCategory($category['id']));
                            if ($link_count > 0): 
                            ?>
                                <span class="category-count">(<?php echo $link_count; ?>)</span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="category-item">
                    <span class="no-categories">暂无分类</span>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- 快捷操作 -->
    <div class="sidebar-actions">
        <a href="#" class="action-link" id="show-all-links">显示全部</a>
        <a href="apply.php" class="action-link">链接申请</a>
    </div>
</aside>
