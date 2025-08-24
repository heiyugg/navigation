// 主要JavaScript功能
document.addEventListener('DOMContentLoaded', function() {
    // 初始化所有功能
    initSearchEngine();
    initSlideshow();
    initCategoryNavigation();
    initLinkCards();
    initScrollToTop();
});

/**
 * 搜索引擎切换功能
 */
function initSearchEngine() {
    const searchForm = document.querySelector('.search-form');
    const searchEngineSelect = document.querySelector('.search-engine-select');
    const searchInput = document.querySelector('.search-input');
    
    if (!searchForm || !searchEngineSelect) return;
    
    // 搜索引擎切换
    searchEngineSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const url = selectedOption.dataset.url;
        const param = selectedOption.dataset.param;
        
        // 更新表单action和参数名
        searchForm.action = url;
        searchInput.name = param;
        
        // 保存用户选择
        localStorage.setItem('preferred_search_engine', this.value);
    });
    
    // 恢复用户上次选择的搜索引擎
    const preferredEngine = localStorage.getItem('preferred_search_engine');
    if (preferredEngine) {
        searchEngineSelect.value = preferredEngine;
        // 触发change事件以更新表单
        searchEngineSelect.dispatchEvent(new Event('change'));
    } else {
        // 默认触发一次change事件以初始化表单
        searchEngineSelect.dispatchEvent(new Event('change'));
    }
    
    // 搜索框快捷键和自动提交
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (this.value.trim()) {
                searchForm.submit();
            }
        }
    });
    
    // 搜索表单提交事件
    searchForm.addEventListener('submit', function(e) {
        if (!searchInput.value.trim()) {
            e.preventDefault();
            return false;
        }
    });
}

/**
 * 幻灯片功能
 */
function initSlideshow() {
    const slideshowContainer = document.querySelector('.slideshow-container');
    if (!slideshowContainer) return;
    
    const slides = document.querySelectorAll('.slide');
    const dots = document.querySelectorAll('.dot');
    const prevBtn = document.querySelector('.slide-nav.prev');
    const nextBtn = document.querySelector('.slide-nav.next');
    
    if (slides.length <= 1) return;
    
    let currentSlide = 0;
    let slideInterval;
    
    // 显示指定幻灯片
    function showSlide(index) {
        // 隐藏所有幻灯片
        slides.forEach(slide => slide.classList.remove('active'));
        dots.forEach(dot => dot.classList.remove('active'));
        
        // 显示当前幻灯片
        if (slides[index]) {
            slides[index].classList.add('active');
        }
        if (dots[index]) {
            dots[index].classList.add('active');
        }
        
        currentSlide = index;
    }
    
    // 下一张幻灯片
    function nextSlide() {
        const next = (currentSlide + 1) % slides.length;
        showSlide(next);
    }
    
    // 上一张幻灯片
    function prevSlide() {
        const prev = (currentSlide - 1 + slides.length) % slides.length;
        showSlide(prev);
    }
    
    // 开始自动播放
    function startAutoPlay() {
        slideInterval = setInterval(nextSlide, 5000);
    }
    
    // 停止自动播放
    function stopAutoPlay() {
        if (slideInterval) {
            clearInterval(slideInterval);
        }
    }
    
    // 绑定导航按钮事件
    if (nextBtn) {
        nextBtn.addEventListener('click', function() {
            stopAutoPlay();
            nextSlide();
            startAutoPlay();
        });
    }
    
    if (prevBtn) {
        prevBtn.addEventListener('click', function() {
            stopAutoPlay();
            prevSlide();
            startAutoPlay();
        });
    }
    
    // 绑定导航点事件
    dots.forEach((dot, index) => {
        dot.addEventListener('click', function(e) {
            e.stopPropagation();
            stopAutoPlay();
            showSlide(index);
            startAutoPlay();
        });
    });
    
    // 确保幻灯片链接不被干扰
    const slideLinks = document.querySelectorAll('.slide-link-wrapper');
    slideLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // 允许链接正常跳转，不阻止默认行为
            e.stopPropagation();
        });
    });
    
    // 鼠标悬停时暂停自动播放
    slideshowContainer.addEventListener('mouseenter', stopAutoPlay);
    slideshowContainer.addEventListener('mouseleave', startAutoPlay);
    
    // 开始自动播放
    startAutoPlay();
}

/**
 * 分类导航功能
 */
function initCategoryNavigation() {
    const categoryLinks = document.querySelectorAll('.category-link');
    const showAllBtn = document.getElementById('show-all-links');
    const categoryGroups = document.querySelectorAll('.category-group');
    
    // 分类链接点击事件
    categoryLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            // 移除所有活动状态
            categoryLinks.forEach(l => l.classList.remove('active'));
            // 添加当前活动状态
            this.classList.add('active');
            
            const categoryId = this.dataset.category;
            const targetGroup = document.getElementById(`category-${categoryId}`);
            
            if (targetGroup) {
                // 隐藏所有分类组
                categoryGroups.forEach(group => {
                    group.style.display = 'none';
                });
                
                // 显示目标分类组
                targetGroup.style.display = 'block';
                
                // 滚动到目标位置
                targetGroup.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // 显示全部链接
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // 移除所有分类链接的活动状态
            categoryLinks.forEach(l => l.classList.remove('active'));
            
            // 显示所有分类组
            categoryGroups.forEach(group => {
                group.style.display = 'block';
            });
            
            // 滚动到顶部
            document.querySelector('.main-links-section').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        });
    }
}

/**
 * 链接卡片功能
 */
function initLinkCards() {
    const linkCards = document.querySelectorAll('.link-card');
    
    // 添加点击动画效果
    linkCards.forEach(card => {
        card.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = '';
            }, 150);
        });
        
        // 添加键盘导航支持
        const cardLink = card.querySelector('.card-link');
        if (cardLink) {
            cardLink.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    this.click();
                }
            });
        }
    });
}

/**
 * 更新访问计数
 */
function updateVisitCount(linkId) {
    if (!linkId) return;
    
    // 发送异步请求更新访问计数
    fetch('api/update-visit.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            link_id: linkId
        })
    }).catch(error => {
        console.log('访问计数更新失败:', error);
    });
}

/**
 * 回到顶部功能
 */
function initScrollToTop() {
    // 创建回到顶部按钮
    const scrollTopBtn = document.createElement('button');
    scrollTopBtn.innerHTML = '↑';
    scrollTopBtn.className = 'scroll-to-top';
    scrollTopBtn.style.cssText = `
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #3498db;
        color: white;
        border: none;
        font-size: 20px;
        cursor: pointer;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    `;
    
    document.body.appendChild(scrollTopBtn);
    
    // 滚动事件监听
    window.addEventListener('scroll', function() {
        if (window.pageYOffset > 300) {
            scrollTopBtn.style.opacity = '1';
            scrollTopBtn.style.visibility = 'visible';
        } else {
            scrollTopBtn.style.opacity = '0';
            scrollTopBtn.style.visibility = 'hidden';
        }
    });
    
    // 点击回到顶部
    scrollTopBtn.addEventListener('click', function() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // 悬停效果
    scrollTopBtn.addEventListener('mouseenter', function() {
        this.style.background = '#2980b9';
        this.style.transform = 'scale(1.1)';
    });
    
    scrollTopBtn.addEventListener('mouseleave', function() {
        this.style.background = '#3498db';
        this.style.transform = 'scale(1)';
    });
}

/**
 * 工具函数：防抖
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * 工具函数：节流
 */
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    }
}

/**
 * 响应式处理
 */
function handleResponsive() {
    const sidebar = document.querySelector('.sidebar');
    const mainContainer = document.querySelector('.main-container');
    
    function checkScreenSize() {
        if (window.innerWidth <= 991) {
            // 移动端处理
            if (sidebar) {
                sidebar.style.position = 'static';
            }
        } else {
            // 桌面端处理
            if (sidebar) {
                sidebar.style.position = 'sticky';
            }
        }
    }
    
    // 初始检查
    checkScreenSize();
    
    // 窗口大小改变时检查
    window.addEventListener('resize', debounce(checkScreenSize, 250));
}

// 初始化响应式处理
document.addEventListener('DOMContentLoaded', handleResponsive);

/**
 * 错误处理
 */
window.addEventListener('error', function(e) {
    console.error('JavaScript错误:', e.error);
});

/**
 * 页面可见性API - 当页面不可见时暂停动画
 */
document.addEventListener('visibilitychange', function() {
    const slideshowContainer = document.querySelector('.slideshow-container');
    if (document.hidden) {
        // 页面不可见时暂停所有动画
        if (slideshowContainer) {
            slideshowContainer.style.animationPlayState = 'paused';
        }
    } else {
        // 页面可见时恢复动画
        if (slideshowContainer) {
            slideshowContainer.style.animationPlayState = 'running';
        }
    }
});
