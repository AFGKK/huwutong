<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ($slug ?? false) ? '文章详情' : '开发者博客' }} - 互物通 | 企业级授权管理系统</title>
    <meta name="description" content="互物通开发者博客——集成教程、最佳实践、客户案例与产品更新日志">
    <meta property="og:title" content="开发者博客 - 互物通">
    <meta property="og:description" content="互物通开发者博客——集成教程、最佳实践、客户案例与产品更新日志">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/blog') }}">
    <link rel="canonical" href="{{ url('/blog') }}">
    @include('public.partials.tracking')
    <link rel="alternate" type="application/rss+xml" title="互物通最新动态" href="/blog/rss/latest">
    <link rel="alternate" type="application/rss+xml" title="开发者博客" href="/blog/rss">
    <link rel="alternate" type="application/rss+xml" title="产品更新日志" href="/blog/rss/changelog">
    @vite('resources/css/public.css')
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <style>
        .blog-content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; }
        .blog-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .blog-content p { margin-bottom: 1rem; line-height: 1.75; }
        .blog-content pre { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1rem; }
        .blog-content code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
        .blog-content pre code { background: none; padding: 0; }
        .blog-content img { max-width: 100%; height: auto; border-radius: 0.5rem; }
    </style>
    <style>
        :root {
            --pg-primary: {{ site_setting('page_primary_color', '#2563eb') }};
            --pg-primary-50: color-mix(in srgb, var(--pg-primary) 10%, white);
            --pg-primary-100: color-mix(in srgb, var(--pg-primary) 20%, white);
            --pg-primary-200: color-mix(in srgb, var(--pg-primary) 35%, white);
            --pg-primary-300: color-mix(in srgb, var(--pg-primary) 50%, white);
            --pg-primary-400: color-mix(in srgb, var(--pg-primary) 70%, white);
            --pg-primary-500: color-mix(in srgb, var(--pg-primary) 85%, white);
            --pg-primary-700: color-mix(in srgb, var(--pg-primary) 85%, black);
            --pg-primary-800: color-mix(in srgb, var(--pg-primary) 70%, black);
            --pg-primary-900: color-mix(in srgb, var(--pg-primary) 55%, black);
            --pg-bg: {{ site_setting('page_background', '#f9fafb') }};
            --pg-content-bg: {{ site_setting('page_content_bg', '#ffffff') }};
            --pg-font-size: {{ site_setting('page_font_size', '16px') }};
        }
        body { background: var(--pg-bg) !important; }
        .blog-content { font-size: var(--pg-font-size); }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    <!-- 导航栏 -->
    @include('public.partials.nav')

    <!-- 主内容 -->
    <main class="pt-24 pb-16">
        <div class="{{ site_setting('page_width', 'max-w-6xl') }} mx-auto px-4 sm:px-6 lg:px-8" id="blog-app">
            <!-- 加载中 -->
            <div id="loading-state" class="text-center py-20">
                <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                <p class="text-gray-500">加载中...</p>
            </div>

            <!-- 文章详情 -->
            <div id="post-detail" class="hidden max-w-3xl mx-auto">
                <a href="/blog" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-primary-600 transition mb-6">&larr; 返回博客列表</a>
                <article>
                    <!-- 阅读进度条 -->
                    <div id="reading-progress-bar" class="fixed top-0 left-0 h-1 bg-primary-500 z-50 transition-all duration-150" style="width:0%"></div>
                    <!-- 图片灯箱 -->
                    <div id="image-lightbox" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)this.classList.add('hidden')">
                        <button class="absolute top-4 right-4 text-white text-3xl hover:text-gray-300 transition z-10" onclick="document.getElementById('image-lightbox').classList.add('hidden')">&times;</button>
                    </div>
                    <div class="flex items-center gap-3 mb-4">
                        <span id="detail-type" class="text-xs font-semibold px-3 py-1 rounded-full bg-primary-100 text-primary-700"></span>
                        <span id="detail-category" class="hidden"></span>
                        <span id="detail-date" class="text-sm text-gray-500"></span>
                        <span id="detail-version" class="text-sm text-gray-400 hidden"></span>
                        <span id="detail-readtime" class="ml-auto text-sm text-gray-400"></span>
                    </div>
                    <h1 id="detail-title" class="text-3xl font-bold text-gray-900 mb-4"></h1>
                    <div class="flex items-center text-sm text-gray-500 mb-8">
                        <span id="detail-author-info" class="flex items-center gap-2">
                            <img id="detail-author-avatar" class="w-6 h-6 rounded-full object-cover hidden" />
                            <span id="detail-author-avatar-fallback" class="w-6 h-6 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center text-xs font-medium hidden"></span>
                            <span id="detail-author-name" class="text-sm text-gray-600"></span>
                            <button id="detail-follow-btn" onclick="handleAuthorFollow(this, blogAuthorId)" class="text-xs px-2 py-1 rounded-full font-medium transition border hidden">
                                ➕ 关注
                            </button>
                            <span id="detail-views" class="text-gray-400"></span>
                        </span>
                        <span id="detail-tags" class="flex gap-1"></span>
                    </div>

                    <!-- 🤖 AI 摘要 -->
                    <div id="ai-summary-box" class="hidden mb-6 p-4 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-100">
                        <div class="flex items-start gap-3">
                            <span class="text-lg flex-shrink-0 mt-0.5">🤖</span>
                            <div class="flex-1 min-w-0">
                                <div class="text-xs font-semibold text-blue-600 mb-1">AI 摘要</div>
                                <p id="ai-summary-text" class="text-sm text-gray-700 leading-relaxed"></p>
                            </div>
                            <button id="ai-summary-btn" onclick="generateAISummary()" class="flex-shrink-0 text-xs px-3 py-1.5 rounded-lg bg-white border border-blue-200 text-blue-600 hover:bg-blue-50 transition font-medium shadow-sm">
                                🤖 生成
                            </button>
                        </div>
                        <div id="ai-summary-loading" class="hidden flex items-center gap-2 mt-1 ml-8">
                            <div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div>
                            <span class="text-xs text-blue-400">AI 思考中...</span>
                        </div>
                    </div>

                    <div id="detail-content" class="blog-content text-gray-700 leading-relaxed"></div>
                </article>

                <!-- 文章目录 -->
                <div id="post-toc" class="hidden mt-8 p-4 bg-gray-50 rounded-xl border border-gray-100">
                    <div class="text-sm font-semibold text-gray-700 mb-2">📑 目录</div>
                    <div id="toc-list" class="space-y-1"></div>
                </div>

                <!-- 点赞与收藏 -->
                <div class="mt-6 flex items-center justify-center gap-4">
                    <button id="blog-like-btn" onclick="toggleBlogLike()" class="flex items-center gap-1.5 text-sm px-4 py-2 rounded-full border border-gray-200 bg-white hover:bg-red-50 hover:border-red-200 transition font-medium">
                        <span id="blog-like-icon">🤍</span>
                        <span id="blog-like-count">0</span>
                    </button>
                    <button id="blog-fav-btn" onclick="toggleBlogFav()" class="flex items-center gap-1.5 text-sm px-4 py-2 rounded-full border border-gray-200 bg-white hover:bg-amber-50 hover:border-amber-200 transition font-medium">
                        <span id="blog-fav-icon">⭐</span>
                        <span>收藏</span>
                    </button>
                    <button id="blog-readlater-btn" onclick="toggleReadLater()" class="flex items-center gap-1.5 text-sm px-4 py-2 rounded-full border border-gray-200 bg-white hover:bg-indigo-50 hover:border-indigo-200 transition font-medium">
                        <span id="blog-readlater-icon">⏰</span>
                        <span id="blog-readlater-text">稍后阅读</span>
                    </button>
                </div>

                <!-- 分享得积分-->
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <div class="flex items-center justify-center gap-2 flex-wrap">
                        <span class="text-xs text-gray-400 mr-1">📤 分享得积分</span>
                        <button onclick="shareBlog('weibo')" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 bg-white hover:bg-red-50 hover:border-red-200 transition font-medium">🔴 微博 🪙+1</button>
                        <button onclick="shareBlog('copy')" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 bg-white hover:bg-gray-100 transition font-medium">🔗 复制链接 🪙+1</button>
                        <button onclick="generatePoster()" class="text-xs px-3 py-1.5 rounded-full border border-gray-200 bg-white hover:bg-purple-50 hover:border-purple-200 transition font-medium">🖼︀生成海报</button>
                    </div>
                    <div id="share-reward-msg" class="text-center text-xs text-green-600 mt-2 hidden"></div>
                </div>

                <!-- 💬 评论区 -->
                <div class="mt-8 pt-6 border-t border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800 mb-4">💬 评论 (<span id="comment-count">0</span>)</h3>

                    <!-- 评论输入 -->
                    <div id="comment-login-hint" class="hidden text-center py-4 bg-gray-50 rounded-xl mb-4">
                        <span class="text-sm text-gray-500">💬 登录后可发表评论</span>
                        <a href="/build/login" class="text-sm font-medium text-primary-600 hover:text-primary-700 transition">立即登录</a>
                    </div>
                    <div id="comment-form" class="hidden mb-6">
                        <textarea id="comment-input" class="w-full border border-gray-200 rounded-xl p-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" rows="3" placeholder="写下你的评论..."></textarea>
                        <div class="flex justify-end mt-2">
                            <button id="comment-submit-btn" onclick="submitComment()" class="px-5 py-2 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition disabled:opacity-50">发表评论</button>
                        </div>
                    </div>

                    <!-- 评论列表 -->
                    <div id="comment-list" class="space-y-4">
                        <div class="text-center py-8 text-gray-400 text-sm">加载中...</div>
                    </div>
                </div>

                <!-- 🎯 猜你喜欢 -->
                <div id="related-posts-section" class="hidden mt-10 pt-6 border-t border-gray-100">
                    <h3 class="text-base font-semibold text-gray-800 mb-5">🎯 猜你喜欢</h3>
                    <div id="related-posts-grid" class="grid md:grid-cols-3 gap-4"></div>
                </div>
            </div>

            <!-- 博客列表 / Changelog -->
            <div id="list-view" class="hidden">
                <!-- Hero -->
                <div class="text-center mb-8">
                    <h1 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-2">📝 开发者博客</h1>
                    <p class="text-gray-500 text-sm">集成教程、最佳实践、客户案例与产品更新</p>
                    <div class="flex items-center justify-center gap-2 mt-4 flex-wrap">
                        <button onclick="switchView('blog')" id="tab-blog" class="px-5 py-2 rounded-full font-medium text-sm transition bg-primary-600 text-white shadow-sm">博客</button>
                        <button onclick="switchView('changelog')" id="tab-changelog" class="px-5 py-2 rounded-full font-medium text-sm transition bg-white text-gray-600 hover:bg-gray-100 border border-gray-200">更新日志</button>
                        <span class="mx-2 text-gray-200">|</span>
                        <button onclick="toggleSubscribe()" class="text-xs text-gray-400 hover:text-primary-600 transition">📬 订阅</button>
                        <a href="/blog/rss" class="text-xs text-gray-400 hover:text-primary-600 transition">📡 RSS</a>
                    </div>
                </div>

                <!-- 工具栏：搜索 + 排序 -->
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div class="flex items-center gap-2 flex-1 max-w-sm">
                        <div class="relative flex-1">
                            <input id="blog-search" type="text" placeholder="搜索文章..." oninput="onSearchInput()"
                                class="w-full px-3 py-2 pl-8 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" />
                            <svg class="absolute left-2.5 top-2.5 w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        </div>
                        <button onclick="clearSearch()" id="clear-search-btn" class="hidden text-xs px-2 py-1.5 rounded border border-gray-200 hover:bg-gray-100 transition">清除</button>
                    </div>
                    <div class="flex items-center gap-1">
                        <button onclick="setSort('latest')" id="sort-latest" class="px-3 py-1.5 rounded-lg text-xs font-medium transition bg-primary-600 text-white">📅 最新</button>
                        <button onclick="setSort('hot')" id="sort-hot" class="px-3 py-1.5 rounded-lg text-xs font-medium transition bg-white text-gray-600 border border-gray-200 hover:bg-gray-100">🔥 热门</button>
                        <button onclick="setSort('favorites')" id="sort-favorites" class="px-3 py-1.5 rounded-lg text-xs font-medium transition bg-white text-gray-600 border border-gray-200 hover:bg-gray-100">⭐ 收藏</button>
                    </div>
                </div>

                <!-- 分类筛选 -->
                <div id="category-filters" class="flex items-center justify-center gap-2 mb-2 flex-wrap"></div>

                <!-- 🏷︀标签 -->
                <div id="tag-cloud" class="flex items-center justify-center gap-2 mb-4 flex-wrap hidden"></div>

                <!-- 博客卡片网格 -->
                <div id="blog-grid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
                <!-- 加载更多指示噀-->
                <div id="blog-list-end" class="text-center py-6">
                    <div id="blog-loading-more" class="hidden">
                        <div class="inline-block w-6 h-6 border-2 border-primary-600 border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-sm text-gray-400 mt-2">加载更多...</p>
                    </div>
                    <div id="blog-no-more" class="hidden text-sm text-gray-300"> 已经到底了 </div>
                </div>

                <!-- Changelog 时间纀-->
                <div id="changelog-view" class="hidden max-w-3xl mx-auto"></div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    @include('public.partials.footer')

    <!-- 订阅弹窗 -->
    <div id="subscribe-modal" class="fixed inset-0 bg-black/40 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)hideSubscribe()">
        <div class="bg-white rounded-2xl p-6 w-full max-w-md shadow-xl">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold text-gray-900">📬 订阅更新通知</h3>
                <button onclick="hideSubscribe()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <div id="sub-success" class="hidden text-center py-6">
                <div class="text-4xl mb-3"></div>
                <p class="text-gray-700 font-medium">订阅确认邮件已发送</p>
                <p class="text-sm text-gray-500 mt-1">请查收邮件完成验诀</p>
            </div>
            <form id="sub-form" onsubmit="return handleSubscribe(event)">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">邮箱 *</label>
                    <input type="email" id="sub-email" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="your@email.com">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">姓名（选填</label>
                    <input type="text" id="sub-name" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" placeholder="您的称呼">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">订阅类型</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="blog" checked class="rounded"> 博客文章</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="changelog" checked class="rounded"> 更新日志</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" value="release_note" class="rounded"> 发布说明</label>
                    </div>
                </div>
                <button type="submit" id="sub-btn" class="w-full py-2.5 bg-primary-600 text-white rounded-xl font-medium hover:bg-primary-700 transition text-sm">确认订阅</button>
                <p id="sub-msg" class="text-sm mt-2 hidden"></p>
            </form>
        </div>
    </div>

    <!-- 🖼︀海报预览弹窗 -->
    <div id="poster-modal" class="fixed inset-0 bg-black/60 z-50 hidden flex items-center justify-center p-4" onclick="if(event.target===this)closePoster()">
        <div class="bg-white rounded-2xl p-5 w-full max-w-sm shadow-2xl" onclick="event.stopPropagation()">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-base font-bold text-gray-900">🖼︀文章海报</h3>
                <button onclick="closePoster()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div id="poster-container" class="flex justify-center bg-gray-50 rounded-xl p-2">
                <img id="poster-img" class="max-w-full rounded-lg" />
            </div>
            <div class="flex items-center justify-center gap-3 mt-4">
                <button onclick="downloadPoster()" class="flex-1 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-medium hover:bg-primary-700 transition flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    下载海报
                </button>
                <button onclick="sharePoster()" class="flex-1 py-2.5 bg-green-600 text-white rounded-xl text-sm font-medium hover:bg-green-700 transition flex items-center justify-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    分享海报
                </button>
            </div>
            <p class="text-xs text-gray-400 text-center mt-3">长按或截图分享到社交媒体</p>
        </div>
    </div>

    <script>
    const API = '/api/public';
    let currentView = 'blog';
    let blogPostId = null;
    let currentPostId = null;
    let blogAuthorId = null;
    let posterDataUrl = null;

    // ─── 切换视图 ───
    function switchView(view) {
        currentView = view;
        document.getElementById('tab-blog').className = view === 'blog'
            ? 'px-5 py-2 rounded-full font-medium text-sm bg-primary-600 text-white shadow-sm'
            : 'px-5 py-2 rounded-full font-medium text-sm bg-white text-gray-600 hover:bg-gray-100 border border-gray-200';
        document.getElementById('tab-changelog').className = view === 'changelog'
            ? 'px-5 py-2 rounded-full font-medium text-sm bg-primary-600 text-white shadow-sm'
            : 'px-5 py-2 rounded-full font-medium text-sm bg-white text-gray-600 hover:bg-gray-100 border border-gray-200';
        document.getElementById('blog-grid').classList.toggle('hidden', view !== 'blog');
        document.getElementById('changelog-view').classList.toggle('hidden', view !== 'changelog');
        if (view === 'changelog' && document.getElementById('changelog-view').children.length === 0) loadChangelog();
    }

    // ─── 格式化日月───
    function fmtDate(d) {
        if (!d) return '';
        return new Date(d).toLocaleDateString('zh-CN', { year: 'numeric', month: '2-digit', day: '2-digit' });
    }

    function stripHtml(html) {
        var d = document.createElement('div');
        d.innerHTML = html || '';
        return d.textContent || '';
    }

    function extractFirstImage(html) {
        if (!html) return '';
        var m = html.match(/<img[^>]+src=["']([^"']+)["']/);
        return m ? m[1] : '';
    }

    function typeLabel(t) {
        return t === 'blog' ? '博客' : t === 'changelog' ? '更新日志' : '发布说明';
    }

    function typeColor(t) {
        return t === 'blog' ? 'bg-blue-100 text-blue-700' : t === 'changelog' ? 'bg-amber-100 text-amber-700' : 'bg-purple-100 text-purple-700';
    }

    // ─── 计算阅读时间 ───
    function readingTime(html) {
        var text = stripHtml(html || '');
        if (!text) return 1;
        var cjk = (text.match(/[\u4e00-\u9fa5]/g) || []).length;
        var words = text.replace(/[\u4e00-\u9fa5]/g, ' ').split(/\s+/).filter(Boolean).length;
        var minutes = Math.max(1, Math.ceil(cjk / 300 + words / 200));
        return minutes;
    }

    // ─── 分类数据与筛选───
    var selectedCategoryId = null;
    var allPosts = [];
    var searchKeyword = '';
    var currentSort = 'latest';
    var selectedTag = '';
    var allTags = [];
    var showAllTags = false;
    var blogPage = 1;
    var blogHasMore = true;
    var blogLoadingMore = false;

    // ─── 搜索 ───
    var searchTimer = null;
    function onSearchInput() {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() {
            searchKeyword = document.getElementById('blog-search').value.trim().toLowerCase();
            document.getElementById('clear-search-btn').classList.toggle('hidden', !searchKeyword);
            loadPosts(true);
        }, 300);
    }
    function clearSearch() {
        document.getElementById('blog-search').value = '';
        searchKeyword = '';
        document.getElementById('clear-search-btn').classList.add('hidden');
        loadPosts(true);
    }

    // ─── 排序 ───
    function setSort(sort) {
        currentSort = sort;
        ['latest','hot','favorites'].forEach(function(s) {
            var btn = document.getElementById('sort-' + s);
            if (s === sort) {
                btn.className = 'px-3 py-1.5 rounded-lg text-xs font-medium transition bg-primary-600 text-white';
            } else {
                btn.className = 'px-3 py-1.5 rounded-lg text-xs font-medium transition bg-white text-gray-600 border border-gray-200 hover:bg-gray-100';
            }
        });
        loadPosts(true);
    }

    // ─── 标签产───
    function loadTagCloud() {
        var tags = {};
        allPosts.forEach(function(p) {
            (p.tags || []).forEach(function(t) {
                tags[t] = (tags[t] || 0) + 1;
            });
        });
        allTags = Object.keys(tags).sort(function(a, b) { return tags[b] - tags[a]; });
        var el = document.getElementById('tag-cloud');
        if (allTags.length === 0) { el.classList.add('hidden'); return; }
        el.classList.remove('hidden');
        var displayTags = allTags.slice(0, 8);
        el.innerHTML = '<span class="text-xs px-2.5 py-1 rounded-full font-medium cursor-pointer transition border ' +
            (!selectedTag ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-100') +
            '" onclick="selectTag(\'\')">🏷︀全部</span>' +
            displayTags.map(function(t) {
                return '<span class="text-xs px-2.5 py-1 rounded-full font-medium cursor-pointer transition border ' +
                    (selectedTag === t ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100') +
                    '" onclick="selectTag(\'' + t.replace(/'/g, "\\'") + '\')">#' + t + '</span>';
            }).join('');
    }
    function selectTag(tag) {
        selectedTag = selectedTag === tag ? '' : tag;
        loadTagCloud();
        loadPosts(true);
    }


    async function loadCategories() {
        try {
            var res = await fetch(API + '/blog/categories');
            var data = await res.json();
            var cats = data.data || [];
            var el = document.getElementById('category-filters');
            if (cats.length === 0) { el.style.display = 'none'; return; }
            el.innerHTML = '<button class="px-3 py-1 rounded-full text-xs font-medium transition border ' +
                (selectedCategoryId === null ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100') +
                '" onclick="filterByCategory(null)">全部</button>' +
                cats.map(function(c) {
                    return '<button class="px-3 py-1 rounded-full text-xs font-medium transition border ' +
                        (selectedCategoryId === c.id ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-100') +
                        '" onclick="filterByCategory(' + c.id + ')" style="border-color:' + (selectedCategoryId === c.id ? '' : (c.color || '#e5e7eb')) + '">' +
                        c.name + '</button>';
                }).join('');
        } catch(e) { document.getElementById('category-filters').style.display = 'none'; }
    }

    function filterByCategory(catId) {
        selectedCategoryId = catId;
        loadPosts(true);
        loadCategories();
    }

    function renderPosts() {
        var grid = document.getElementById('blog-grid');
        var filtered = allPosts;

        // 分类筛选
        if (selectedCategoryId) {
            filtered = filtered.filter(function(p) { return p.category && p.category.id === selectedCategoryId; });
        }
        // 标签筛选
        if (selectedTag) {
            filtered = filtered.filter(function(p) { return (p.tags || []).indexOf(selectedTag) !== -1; });
        }
        // 搜索
        if (searchKeyword) {
            filtered = filtered.filter(function(p) {
                return (p.title || '').toLowerCase().indexOf(searchKeyword) !== -1
                    || stripHtml(p.content || '').toLowerCase().indexOf(searchKeyword) !== -1
                    || (p.excerpt || '').toLowerCase().indexOf(searchKeyword) !== -1;
            });
        }
        // 排序
        if (currentSort === 'hot') {
            filtered = filtered.sort(function(a, b) { return (b.views_count || 0) - (a.views_count || 0); });
        } else if (currentSort === 'favorites') {
            filtered = filtered.sort(function(a, b) { return (b.favorites_count || 0) - (a.favorites_count || 0); });
        } else {
            filtered = filtered.sort(function(a, b) { return new Date(b.published_at) - new Date(a.published_at); });
        }
        if (filtered.length === 0) {
            var emptyMsg = searchKeyword ? '没有匹配 "' + searchKeyword + '" 的文章'
                : selectedTag ? '该标签暂无文章'
                : selectedCategoryId ? '该分类暂无文章'
                : '暂无文章';
            grid.innerHTML = '<div class="col-span-3 text-center py-16 text-gray-400"><p class="text-5xl mb-4">📭</p><p>' + emptyMsg + '</p></div>';
            return;
        }
        grid.innerHTML = filtered.map(function(p) {
            var excerpt = p.excerpt || stripHtml(p.content).substring(0, 120);
            var tags = (p.tags || []).slice(0, 3);
            var catHtml = p.category ? '<span class="text-xs font-medium px-2 py-0.5 rounded" style="background:' + (p.category.color || '#e5e7eb') + '20;color:' + (p.category.color || '#374151') + '">' + p.category.name + '</span>' : '';
            var coverImg = p.featured_image || (p.images && p.images[0]) || extractFirstImage(p.content);
            return '<a href=\"/blog/' + p.slug + '\" class=\"block group rounded-2xl border border-gray-100 bg-white overflow-hidden hover:shadow-lg transition-all duration-300 hover:-translate-y-1\">' +
                (coverImg ? '<div class="aspect-video overflow-hidden bg-gray-100"><img src="' + coverImg + '" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" /></div>' : '<div class="h-32 bg-gradient-to-br from-primary-50 to-blue-50 flex items-center justify-center text-3xl">📝</div>') +
                '<div class="p-5">' +
                '<div class="flex items-center justify-between mb-3">' +
                '<div class="flex items-center gap-2">' +
                '<span class="text-xs font-semibold px-2.5 py-1 rounded-full ' + typeColor(p.type) + '">' + typeLabel(p.type) + '</span>' +
                catHtml + '</div>' +
                '<span class="text-xs text-gray-400">' + fmtDate(p.published_at) + '</span></div>' +
                '<h3 class="font-bold text-gray-900 mb-2 line-clamp-2 min-h-[2.5rem] leading-snug">' + p.title + '</h3>' +
                '<p class="text-sm text-gray-500 leading-relaxed line-clamp-3">' + excerpt + '</p>' +
                '<div class="flex items-center justify-between mt-3 text-xs text-gray-400">' +
                '<div class="flex items-center gap-2">' +
                (p.author_user ? (
                    '<img src="' + (p.author_user.avatar_url || '') + '" class="w-5 h-5 rounded-full object-cover" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'" /><span class="w-5 h-5 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center text-xs font-medium" style="display:none">' + (p.author_user.name?.charAt(0) || '?') + '</span>'
                ) : '') +
                '<span class="text-xs text-gray-400">' + (p.author || '') + '</span>' +
                '</div>' +
                '<span class="flex items-center gap-2">' +
                '<span>👁️ ' + (p.views_count || 0) + '</span>' +
                (tags.length ? tags.map(function(t) { return '<span class="text-gray-300">#' + t + '</span>'; }).join('') : '') +
                '</span></div></div></a>';
        }).join('');
        // 更新加载更多状态
        var loadingEl = document.getElementById('blog-loading-more');
        var noMoreEl = document.getElementById('blog-no-more');
        if (loadingEl) loadingEl.classList.add('hidden');
        if (noMoreEl) noMoreEl.classList.toggle('hidden', !blogHasMore);
    }

    // ─── 无限滚动 ───
    function setupInfiniteScroll() {
        window.addEventListener('scroll', function() {
            if (blogLoadingMore || !blogHasMore) return;
            var scrollBottom = window.scrollY + window.innerHeight;
            var docHeight = document.documentElement.scrollHeight;
            if (scrollBottom >= docHeight - 400) {
                var loadingEl = document.getElementById('blog-loading-more');
                if (loadingEl) loadingEl.classList.remove('hidden');
                loadPosts(false);
            }
        });
    }

    // ─── 加载博客列表 ───
    async function loadPosts(reset) {
        if (reset === undefined) reset = true;
        if (reset) { blogPage = 1; blogHasMore = true; allPosts = []; }
        if (!blogHasMore || blogLoadingMore) return;
        blogLoadingMore = true;
        try {
            var res = await fetch(API + '/blog/published?limit=12&page=' + blogPage);
            var data = await res.json();
            var newPosts = data.data || [];
            if (newPosts.length === 0) { blogHasMore = false; blogLoadingMore = false; renderPosts(); return; }
            allPosts = reset ? newPosts : allPosts.concat(newPosts);
            blogHasMore = newPosts.length >= 12;
            blogPage++;
            renderPosts();
            loadCategories();
            loadTagCloud();
            // 如果是追加，滚动到新内容
            if (!reset) {
                var el = document.getElementById('blog-list-end');
                if (el) el.scrollIntoView({ behavior: 'smooth', block: 'end' });
            }
        } catch(e) {
            document.getElementById('blog-grid').innerHTML = '<div class="col-span-3 text-center py-16 text-gray-400"><p>加载失败</p></div>';
        }
        blogLoadingMore = false;
    }

    // ─── 加载 Changelog ───
    async function loadChangelog() {
        var el = document.getElementById('changelog-view');
        try {
            var res = await fetch(API + '/blog/changelog/versions');
            var data = await res.json();
            var versions = data.data || {};
            var keys = Object.keys(versions);
            if (keys.length === 0) {
                el.innerHTML = '<div class="text-center py-16 text-gray-400"><p class="text-5xl mb-4">🚀</p><p>暂无更新日志</p></div>';
                return;
            }
            el.innerHTML = keys.map(function(v) {
                var posts = versions[v] || [];
                return '<div class="mb-8">' +
                    '<h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center gap-2"><span class="bg-amber-100 text-amber-700 text-sm px-3 py-1 rounded-full">v' + v + '</span></h2>' +
                    '<div class="space-y-3">' +
                    posts.map(function(p) {
                        return '<a href=\"/blog/' + p.slug + '\" class=\"block p-4 rounded-xl border border-gray-100 bg-white hover:shadow-md hover:border-primary-100 transition-all\">' +
                            '<div class="flex items-center justify-between mb-1">' +
                            '<h4 class="font-semibold text-gray-900">' + p.title + '</h4>' +
                            '<span class="text-xs text-gray-400">' + fmtDate(p.published_at) + '</span></div>' +
                            '<p class="text-sm text-gray-500 mt-1">' + (p.excerpt || stripHtml(p.content).substring(0, 150)) + '</p>' +
                            '<p class="text-xs text-gray-400 mt-1">⏱️ ' + readingTime(p.content) + ' 分钟阅读</p>' +
                            '</a>';
                    }).join('') +
                    '</div></div>';
            }).join('');
        } catch(e) {
            el.innerHTML = '<div class="text-center py-16 text-gray-400"><p>加载失败</p></div>';
        }
    }

    // ─── 阅读进度条 ───
    function setupReadingProgress() {
        window.removeEventListener('scroll', onReadingScroll);
        window.addEventListener('scroll', onReadingScroll);
        onReadingScroll();
    }
    function onReadingScroll() {
        var bar = document.getElementById('reading-progress-bar');
        if (!bar) return;
        var scrollTop = window.scrollY || document.documentElement.scrollTop;
        var scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
        var progress = scrollHeight > 0 ? Math.min(100, Math.round(scrollTop / scrollHeight * 100)) : 0;
        bar.style.width = progress + '%';
        bar.style.opacity = progress > 2 ? '1' : '0';
        // 高亮当前目录顀
        highlightTocItem();
    }

    // ─── 文章目录 ───
    var tocItems = [];

    // ─── 图片灯箱 ───
    function setupImageLightbox() {
        var content = document.getElementById('detail-content');
        if (!content) return;
        var imgs = content.querySelectorAll('img');
        imgs.forEach(function(img) {
            img.style.cursor = 'pointer';
            img.addEventListener('click', function() {
                openLightbox(this.src);
            });
        });
    }

    function openLightbox(src) {
        var lb = document.getElementById('image-lightbox');
        if (!lb) return;
        lb.classList.remove('hidden');
        lb.innerHTML = '<div class="fixed inset-0 bg-black/80 z-[100] flex items-center justify-center p-4" onclick="if(event.target===this)this.parentElement.classList.add(\'hidden\')"><img src="' + src + '" class="max-w-full max-h-full rounded-lg shadow-2xl" /></div>';
    }

    // ─── 加载文章详情 ───
    async function loadPost(slug) {
        try {
            document.getElementById('loading-state').classList.remove('hidden');
            document.getElementById('post-detail').classList.add('hidden');
            var res = await fetch(API + '/blog/' + slug);
            var json = await res.json();
            if (!json.success || !json.data) {
                document.getElementById('loading-state').innerHTML = '<p class="text-gray-400">文章不存在</p>';
                return;
            }
            var p = json.data;
            // 记录浏览量并更新显示
            fetch('/api/blog/' + p.id + '/view', { method: 'POST' })
                .then(function(r) { return r.json(); })
                .then(function(v) { if (v.success) document.getElementById('detail-views').textContent = '\uD83D\uDC41\uFE0F ' + v.data.views_count + ' 次浏览'; })
                .catch(function(){});
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('post-detail').classList.remove('hidden');
            document.title = p.title + ' - 互物通| 企业级授权管理系统';
            document.getElementById('detail-title').textContent = p.title;
            document.getElementById('detail-type').textContent = typeLabel(p.type);
            document.getElementById('detail-type').className = 'text-xs font-semibold px-3 py-1 rounded-full ' + typeColor(p.type);
            var catDetailEl = document.getElementById('detail-category');
            if (p.category) {
                catDetailEl.innerHTML = '<span class="text-xs font-medium px-2.5 py-1 rounded-full" style="background:' + (p.category.color || '#e5e7eb') + '20;color:' + (p.category.color || '#374151') + '">' + p.category.name + '</span>';
                catDetailEl.classList.remove('hidden');
            } else { catDetailEl.classList.add('hidden'); }
            document.getElementById('detail-date').textContent = fmtDate(p.published_at);
            // 设置作者头像和关注
            blogAuthorId = p.author_user?.id || null;
            var authorAvatarEl = document.getElementById('detail-author-avatar');
            var authorAvatarFallback = document.getElementById('detail-author-avatar-fallback');
            var authorNameEl = document.getElementById('detail-author-name');
            var followBtn = document.getElementById('detail-follow-btn');
            if (p.author_user) {
                if (p.author_user.avatar_url) {
                    authorAvatarEl.src = p.author_user.avatar_url;
                    authorAvatarEl.classList.remove('hidden');
                    authorAvatarEl.onerror = function() { this.style.display = 'none'; authorAvatarFallback.style.display = 'flex'; };
                } else {
                    authorAvatarEl.classList.add('hidden');
                    authorAvatarFallback.textContent = (p.author_user.name?.charAt(0) || '?');
                    authorAvatarFallback.classList.remove('hidden');
                }
                authorNameEl.textContent = p.author_user.name || p.author;
                followBtn.classList.remove('hidden');
                // 查关注状态
                var token = localStorage.getItem('auth_token');
                if (token && blogAuthorId) {
                    fetch(API + '/blog/follow-status?author_id=' + blogAuthorId, { headers: { 'Authorization': 'Bearer ' + token } })
                        .then(function(r) { return r.json(); })
                        .then(function(json) {
                            if (json.success && json.data?.is_following) {
                                followBtn.textContent = '✅ 已关注';
                                followBtn.className = 'text-xs px-2 py-1 rounded-full font-medium transition border bg-primary-50 text-primary-600 border-primary-200';
                            }
                        }).catch(function() {});
                }
            }
            var ve = document.getElementById('detail-version');
            if (p.version) { ve.textContent = '版本: ' + p.version; ve.classList.remove('hidden'); } else { ve.classList.add('hidden'); }
            var tagsEl = document.getElementById('detail-tags');
            tagsEl.innerHTML = (p.tags || []).map(function(t) { return '<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">#' + t + '</span>'; }).join('');
            document.getElementById('detail-content').innerHTML = p.content;
            // 图片灯箱 - 给所有图片添加点击放大
            setupImageLightbox();
            document.getElementById('detail-readtime').textContent = '⏱️ ' + readingTime(p.content) + ' 分钟阅读';
            document.getElementById('detail-views').textContent = '👁️ ' + (p.views_count || 0) + ' 次浏览';
            blogPostId = p.id;
            currentPostId = p.id;
            // 点赞/收藏计数 & 状态
            document.getElementById('blog-like-count').textContent = p.likes_count || 0;
            loadBlogInteractionStatus();
            // 稍后阅读状态
            loadReadLaterStatus();
            // 评论区
            setupCommentForm();
            loadBlogComments();
            // 阅读进度条
            setupReadingProgress();
            // 生成目录
            buildToc(); // 保存文章ID用于分享积分
            var metaDesc = document.querySelector('meta[name="description"]');
            if (metaDesc) metaDesc.content = p.excerpt || stripHtml(p.content).substring(0, 200);
            // 🤖 AI 摘要
            setupAISummary(p);
            // 🎯 猜你喜欢
            loadRelatedPosts(p.id);
        } catch(e) {
            document.getElementById('loading-state').innerHTML = '<p class="text-gray-400">加载失败</p>';
        }
    }
    @if($slug ?? false)
    loadPost('{{ $slug ?? '' }}');
    @else
    document.getElementById('loading-state').classList.add('hidden');
    document.getElementById('list-view').classList.remove('hidden');
    loadPosts();
    setupInfiniteScroll();
    @endif

    // ─── 订阅 ───
    function toggleSubscribe() {
        document.getElementById('subscribe-modal').classList.remove('hidden');
    }
    function hideSubscribe() {
        document.getElementById('subscribe-modal').classList.add('hidden');
    }
    async function handleSubscribe(e) {
        e.preventDefault();
        var btn = document.getElementById('sub-btn');
        var msg = document.getElementById('sub-msg');
        var email = document.getElementById('sub-email').value.trim();
        if (!email) { msg.textContent = '请输入邮箱'; msg.className = 'text-sm mt-2 text-red-600'; msg.classList.remove('hidden'); return false; }
        var types = Array.from(document.querySelectorAll('#sub-form input[type=checkbox]:checked')).map(function(el) { return el.value; });
        try {
            var res = await fetch(API + '/blog/subscriptions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: email, types: types })
            });
            var json = await res.json();
            if (json.success) {
                msg.textContent = '✅ 订阅成功！';
                msg.className = 'text-sm mt-2 text-green-600';
                msg.classList.remove('hidden');
                btn.disabled = true;
            } else {
                msg.textContent = json.message || '订阅失败';
                msg.className = 'text-sm mt-2 text-red-600';
                msg.classList.remove('hidden');
            }
        } catch(e) {
            msg.textContent = '网络错误';
            msg.className = 'text-sm mt-2 text-red-600';
            msg.classList.remove('hidden');
        }
    }

    async function handleFollow() {
        var token = localStorage.getItem('auth_token');
        if (!token) {
            alert('请先登录后关注');
            window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        try {
            // 先查关注状态
            var statusRes = await fetch(API + '/blog/follow-status', {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            var statusJson = await statusRes.json();
            var isFollowing = statusJson.data?.is_following || false;
            var url = API + (isFollowing ? '/blog/unfollow' : '/blog/follow');
            var res = await fetch(url, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ author_id: blogAuthorId })
            });
            var json = await res.json();
            if (json.success) {
                var btn = document.getElementById('follow-btn');
                if (btn) {
                    btn.innerHTML = json.data.followed ? '已关注' : '关注';
                    btn.className = json.data.followed ? 'px-4 py-2 rounded-full text-sm font-medium bg-primary-50 text-primary-600 border border-primary-200' : 'px-4 py-2 rounded-full text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50';
                }
                updateFollowerCount(json.data.follower_count);
            }
        } catch(e) { /* 静默失败 */ }
    }

    async function toggleBlogFav() {
        var token = localStorage.getItem('auth_token');
        if (!token) { alert('请先登录'); window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }
        try {
            var res = await fetch(API + '/blog/fav/toggle', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }, body: JSON.stringify({ post_id: currentPostId }) });
            var json = await res.json();
            if (json.success) {
                var btn = document.getElementById('fav-btn');
                btn.innerHTML = json.data.favored ? '❤️ 已收藏' : '🤍 收藏';
                btn.className = json.data.favored ? 'px-4 py-2 rounded-lg text-sm font-medium border bg-red-50 text-red-600 border-red-200' : 'px-4 py-2 rounded-lg text-sm font-medium border border-gray-200 text-gray-600 hover:bg-gray-50';
            }
        } catch(e) { /* 静默失败 */ }
    }

    async function loadBlogComments() {
        if (!currentPostId) return;
        try {
            var res = await fetch(API + '/blog/' + currentPostId + '/comments');
            var json = await res.json();
            var list = document.getElementById('comment-list');
            if (!json.data || json.data.length === 0) {
                list.innerHTML = '<div class="text-center py-8 text-gray-400">暂无评论，来说两句吧</div>';
                return;
            }
            list.innerHTML = json.data.map(function(c) {
                var avatarUrl = c.user?.avatar ? (c.user.avatar.startsWith('http') ? c.user.avatar : '/storage/' + c.user.avatar) : '';
                var avatar = avatarUrl ? '<img src="' + avatarUrl + '" class="w-8 h-8 rounded-full object-cover" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'" /><div class="w-8 h-8 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center text-sm font-medium" style="display:none">' + (c.user?.name?.charAt(0) || '?') + '</div>' : '<div class="w-8 h-8 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center text-sm font-medium">' + (c.user?.name?.charAt(0) || '?') + '</div>';
                var replies = (c.replies || []).map(function(r) {
                    var replyAvatarUrl = r.user?.avatar ? (r.user.avatar.startsWith('http') ? r.user.avatar : '/storage/' + r.user.avatar) : '';
                var replyAvatarHtml = replyAvatarUrl ? '<img src="' + replyAvatarUrl + '" class="w-6 h-6 rounded-full object-cover" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'flex\'" /><div class="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs font-medium" style="display:none">' + (r.user?.name?.charAt(0) || '?') + '</div>' : '<div class="w-6 h-6 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-xs font-medium">' + (r.user?.name?.charAt(0) || '?') + '</div>';
                return '<div class="flex gap-2 mt-2 pl-10">' + replyAvatarHtml + '<div><div class="text-xs text-gray-500 font-medium">' + (r.user?.name || '用户') + '</div><div class="text-sm text-gray-700">' + escapeHtml(r.content) + '</div></div></div>';
                }).join('');
                return '<div class="flex gap-3 p-4 bg-gray-50 rounded-xl">' +
                    avatar +
                    '<div class="flex-1 min-w-0">' +
                    '<div class="flex items-center gap-2 mb-1"><span class="text-sm font-medium text-gray-700">' + (c.user?.name || '匿名') + '</span><span class="text-xs text-gray-400">' + fmtTime(c.created_at) + '</span></div>' +
                    '<div class="text-sm text-gray-600">' + escapeHtml(c.content) + '</div>' +
                    (replies ? '<div class="mt-2">' + replies + '</div>' : '') +
                    '</div></div>';
            }).join('');
        } catch(e) { /* 静默失败 */ }
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function fmtTime(date) {
        if (!date) return '';
        var d = new Date(date);
        return d.toLocaleDateString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
    }

    async function submitComment() {
        var token = localStorage.getItem('auth_token');
        if (!token) { alert('请先登录'); return; }
        var content = document.getElementById('comment-input')?.value?.trim();
        if (!content) { alert('请输入评论内容'); return; }
        try {
            var res = await fetch(API + '/blog/' + currentPostId + '/comments', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ content: content })
            });
            var json = await res.json();
            if (json.success) {
                document.getElementById('comment-input').value = '';
                loadBlogComments();
            } else {
                alert(json.message || '评论失败');
            }
        } catch(e) { alert('网络错误'); }
    }

    // ─── 缺失函数定义 ───

    function toggleBlogLike() {
        var token = localStorage.getItem('auth_token');
        if (!token) { alert('请先登录'); window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }
        fetch(API + '/blog/' + currentPostId + '/like', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(json) {
            if (json.success) {
                document.getElementById('blog-like-icon').textContent = json.data.liked ? '❤️' : '🤍';
                document.getElementById('blog-like-count').textContent = json.data.likes_count || 0;
            }
        }).catch(function() {});
    }

    function toggleReadLater() {
        var token = localStorage.getItem('auth_token');
        if (!token) { alert('请先登录'); window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }
        fetch(API + '/blog/' + currentPostId + '/readlater', {
            method: 'POST',
            headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(json) {
            if (json.success) {
                var txt = document.getElementById('blog-readlater-text');
                var ico = document.getElementById('blog-readlater-icon');
                if (json.data.saved) { txt.textContent = '已加入稍后阅读'; ico.textContent = '✅'; }
                else { txt.textContent = '稍后阅读'; ico.textContent = '⏰'; }
            }
        }).catch(function() {});
    }

    function shareBlog(type) {
        var token = localStorage.getItem('auth_token');
        var url = window.location.href;
        var title = document.getElementById('detail-title')?.textContent || '';
        if (type === 'weibo') {
            window.open('https://service.weibo.com/share/share.php?url=' + encodeURIComponent(url) + '&title=' + encodeURIComponent(title), '_blank', 'width=600,height=500');
        }
        if (type === 'copy') {
            var doCopy = function() {
                var msg = document.getElementById('share-reward-msg');
                if (msg) { msg.textContent = '✅ 链接已复制，分享得积分！'; msg.classList.remove('hidden'); setTimeout(function() { msg.classList.add('hidden'); }, 3000); }
            };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(doCopy).catch(function() { fallbackCopy(url); doCopy(); });
            } else { fallbackCopy(url); doCopy(); }
        }
        if (token) {
            fetch(API + '/blog/' + currentPostId + '/share', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: type })
            }).then(function(r) { return r.json(); }).catch(function() {});
        }
    }

    function generatePoster() {
        var title = document.getElementById('detail-title')?.textContent || '文章海报';
        var canvas = document.createElement('canvas');
        canvas.width = 400; canvas.height = 600;
        var ctx = canvas.getContext('2d');
        ctx.fillStyle = '#ffffff'; roundRect(ctx, 0, 0, 400, 600, 20); ctx.fill();
        ctx.fillStyle = '#1f2937'; ctx.font = 'bold 22px sans-serif'; ctx.textAlign = 'center';
        var lines = []; var maxW = 340; var words = title.split('');
        var line = '';
        for (var i = 0; i < words.length; i++) {
            var test = line + words[i];
            if (ctx.measureText(test).width > maxW) { lines.push(line); line = words[i]; }
            else { line = test; }
        }
        if (line) lines.push(line);
        var y0 = 200 - (lines.length - 1) * 14;
        lines.forEach(function(l, idx) { ctx.fillText(l, 200, y0 + idx * 32); });
        ctx.fillStyle = '#6b7280'; ctx.font = '14px sans-serif'; ctx.textAlign = 'center';
        ctx.fillText('互物通 · 开发者博客', 200, 420);
        posterDataUrl = canvas.toDataURL('image/png');
        document.getElementById('poster-modal').classList.remove('hidden');
        document.getElementById('poster-container').innerHTML = '<img src="' + posterDataUrl + '" class="max-w-full rounded-lg" />';
    }

    function sharePoster() {
        if (!posterDataUrl) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(posterDataUrl).then(function() { alert('海报已复制到剪贴板'); }).catch(function() { fallbackCopy(posterDataUrl); });
        } else { fallbackCopy(posterDataUrl); }
    }

    function generateAISummary() {
        var btn = document.getElementById('ai-summary-btn');
        var loading = document.getElementById('ai-summary-loading');
        var textEl = document.getElementById('ai-summary-text');
        if (!btn || !loading || !textEl) return;
        btn.classList.add('hidden'); loading.classList.remove('hidden');
        fetch(API + '/blog/' + currentPostId + '/generate-summary', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ content: document.getElementById('detail-content')?.innerHTML || '' })
        }).then(function(r) { return r.json(); }).then(function(json) {
            loading.classList.add('hidden'); btn.classList.remove('hidden');
            if (json.success && json.data) {
                textEl.textContent = json.data.excerpt || json.data.summary || '';
                btn.textContent = '🔄 重新生成';
            } else { textEl.textContent = '生成失败，请稍后重试'; }
        }).catch(function() { loading.classList.add('hidden'); btn.classList.remove('hidden'); textEl.textContent = '网络错误'; });
    }

    function setupAISummary(p) {
        if (p && p.ai_summary) {
            document.getElementById('ai-summary-box').classList.remove('hidden');
            document.getElementById('ai-summary-text').textContent = p.ai_summary;
            document.getElementById('ai-summary-btn').textContent = '🔄 重新生成';
        } else if (p) {
            document.getElementById('ai-summary-box').classList.remove('hidden');
        }
    }

    function loadRelatedPosts(postId) {
        fetch(API + '/blog/' + postId + '/related').then(function(r) { return r.json(); }).then(function(json) {
            if (json.success && json.data && json.data.length > 0) {
                var grid = document.getElementById('related-posts-grid');
                grid.innerHTML = json.data.map(function(p) {
                    return '<a href="/blog/' + p.slug + '" class="block p-4 rounded-xl border border-gray-100 bg-white hover:shadow-md transition-all">' +
                        '<h4 class="font-semibold text-gray-900 text-sm line-clamp-2">' + p.title + '</h4>' +
                        '<p class="text-xs text-gray-400 mt-1">' + fmtDate(p.published_at) + '</p></a>';
                }).join('');
                document.getElementById('related-posts-section').classList.remove('hidden');
            }
        }).catch(function() {});
    }

    function loadBlogInteractionStatus() {
        var token = localStorage.getItem('auth_token');
        if (!token || !currentPostId) return;
        fetch(API + '/blog/' + currentPostId + '/interaction', {
            headers: { 'Authorization': 'Bearer ' + token }
        }).then(function(r) { return r.json(); }).then(function(json) {
            if (json.success && json.data) {
                if (json.data.liked) { document.getElementById('blog-like-icon').textContent = '❤️'; }
                if (json.data.favored) { document.getElementById('blog-fav-icon').textContent = '❤️'; }
            }
        }).catch(function() {});
    }

    function loadReadLaterStatus() {
        var token = localStorage.getItem('auth_token');
        if (!token || !currentPostId) return;
        fetch(API + '/blog/' + currentPostId + '/readlater/status', {
            headers: { 'Authorization': 'Bearer ' + token }
        }).then(function(r) { return r.json(); }).then(function(json) {
            if (json.success && json.data && json.data.saved) {
                document.getElementById('blog-readlater-text').textContent = '已加入稍后阅读';
                document.getElementById('blog-readlater-icon').textContent = '✅';
            }
        }).catch(function() {});
    }

    function setupCommentForm() {
        var token = localStorage.getItem('auth_token');
        if (token) {
            document.getElementById('comment-form').classList.remove('hidden');
            document.getElementById('comment-login-hint').classList.add('hidden');
        } else {
            document.getElementById('comment-login-hint').classList.remove('hidden');
            document.getElementById('comment-form').classList.add('hidden');
        }
    }

    function buildToc() {
        var content = document.getElementById('detail-content');
        if (!content) return;
        var headings = content.querySelectorAll('h2, h3');
        if (headings.length < 2) { document.getElementById('post-toc').classList.add('hidden'); return; }
        tocItems = [];
        headings.forEach(function(h, idx) {
            var id = 'toc-' + idx;
            h.id = id;
            tocItems.push({ id: id, text: h.textContent, tag: h.tagName });
        });
        var list = document.getElementById('toc-list');
        list.innerHTML = tocItems.map(function(item) {
            var cls = item.tag === 'H3' ? 'pl-4 text-xs' : 'text-sm font-medium';
            return '<div class="' + cls + ' text-gray-500 hover:text-primary-600 cursor-pointer transition" data-toc-id="' + item.id + '" onclick="document.getElementById(\'' + item.id + '\').scrollIntoView({behavior:\'smooth\'})">' + item.text + '</div>';
        }).join('');
        document.getElementById('post-toc').classList.remove('hidden');
    }

    function highlightTocItem() {
        var scrollY = window.scrollY + 120;
        var current = '';
        tocItems.forEach(function(item) {
            var el = document.getElementById(item.id);
            if (el && el.offsetTop <= scrollY) current = item.id;
        });
        document.querySelectorAll('#toc-list > div').forEach(function(div) {
            var id = div.getAttribute('data-toc-id');
            div.className = (id === current ? 'text-sm font-medium text-primary-600' : (div.innerHTML.length > 20 ? 'pl-4 text-xs text-gray-500' : 'text-sm text-gray-500')) + ' hover:text-primary-600 cursor-pointer transition';
        });
    }

    function updateFollowerCount(count) {
        // 已迁移到按作者关注
    }

    async function handleAuthorFollow(btn, authorId) {
        if (!authorId) return;
        var token = localStorage.getItem('auth_token');
        if (!token) {
            alert('请先登录后关注');
            window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href);
            return;
        }
        try {
            var statusRes = await fetch(API + '/blog/follow-status?author_id=' + authorId, {
                headers: { 'Authorization': 'Bearer ' + token }
            });
            var statusJson = await statusRes.json();
            var isFollowing = statusJson.data?.is_following || false;
            var url = API + (isFollowing ? '/blog/unfollow' : '/blog/follow');
            var res = await fetch(url, {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ author_id: authorId })
            });
            var json = await res.json();
            if (json.success) {
                var nowFollowing = !isFollowing;
                if (btn) {
                    btn.textContent = nowFollowing ? '✅ 已关注' : '➕ 关注';
                    btn.className = 'text-xs px-2 py-0.5 rounded-full border font-medium transition ml-1 ' + (nowFollowing ? 'bg-primary-50 text-primary-600 border-primary-200' : 'bg-white text-gray-500 border-gray-200');
                    btn.dataset.following = nowFollowing ? 'true' : 'false';
                }
            } else if (json.error?.code === '已关注') {
                var res2 = await fetch(API + '/blog/unfollow', {
                    method: 'POST',
                    headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ author_id: authorId })
                });
                var json2 = await res2.json();
                if (json2.success && btn) {
                    btn.textContent = '➕ 关注';
                    btn.className = 'text-xs px-2 py-0.5 rounded-full border font-medium transition ml-1 bg-white text-gray-500 border-gray-200';
                    btn.dataset.following = 'false';
                }
            }
        } catch(e) { console.error('关注失败', e); }
    }

    function closeLightbox() {
        var lb = document.getElementById('image-lightbox');
        if (lb) lb.classList.add('hidden');
    }

    // loadComments 别名
    function loadComments() { loadBlogComments(); }

    function fallbackCopy(text) {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        try { document.execCommand('copy'); } catch(e) {}
        document.body.removeChild(ta);
    }

    function roundRect(ctx, x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
    }

    function closePoster() {
        document.getElementById('poster-modal').classList.add('hidden');
    }

    function downloadPoster() {
        if (!posterDataUrl) return;
        var a = document.createElement('a');
        a.href = posterDataUrl;
        a.download = 'poster.png';
        a.click();
    }
</script>
</body>
</html>
