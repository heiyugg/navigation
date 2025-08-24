<section class="slideshow-section">
    <?php if (!empty($slides)): ?>
        <div class="slideshow-container">
            <div class="slides-wrapper">
                <?php foreach ($slides as $index => $slide): ?>
                    <div class="slide <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>">
                        <?php if (!empty($slide['link_url'])): ?>
                            <a href="<?php echo h($slide['link_url']); ?>" 
                               target="<?php echo !empty($slide['link_target']) ? h($slide['link_target']) : '_blank'; ?>"
                               class="slide-link-wrapper">
                                <?php if (!empty($slide['image'])): ?>
                                    <img src="<?php echo h($slide['image']); ?>" alt="<?php echo h($slide['title']); ?>" class="slide-image">
                                <?php endif; ?>
                            </a>
                        <?php else: ?>
                            <?php if (!empty($slide['image'])): ?>
                                <img src="<?php echo h($slide['image']); ?>" alt="<?php echo h($slide['title']); ?>" class="slide-image">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- 导航点 -->
            <?php if (count($slides) > 1): ?>
                <div class="slide-dots">
                    <?php foreach ($slides as $index => $slide): ?>
                        <span class="dot <?php echo $index === 0 ? 'active' : ''; ?>" 
                              data-slide="<?php echo $index; ?>"></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <!-- 左右箭头 -->
            <?php if (count($slides) > 1): ?>
                <button class="slide-nav prev" data-direction="prev">‹</button>
                <button class="slide-nav next" data-direction="next">›</button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="no-slides">
            <div class="placeholder-slide">
                <h3>欢迎使用导航站</h3>
                <p>这里是幻灯片展示区域，您可以在后台管理中添加幻灯片内容。</p>
            </div>
        </div>
    <?php endif; ?>
</section>
