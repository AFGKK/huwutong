<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ isset($article) ? $article->title . ' - ' : '' }}帮助中心 - 互物通| 企业级授权管理系统</title>
    <meta name="description" content="互物通帮助中心——查找常见问题、集成指南、最佳实践和产品文档">
    <link rel="canonical" href="{{ url('/help') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>
        .kb-content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 2rem; margin-bottom: 0.75rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e5e7eb; }
        .kb-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 1.5rem; margin-bottom: 0.5rem; }
        .kb-content p { margin-bottom: 1rem; line-height: 1.75; }
        .kb-content pre { background: #f3f4f6; padding: 1rem; border-radius: 0.5rem; overflow-x: auto; margin-bottom: 1rem; font-size: 0.875rem; }
        .kb-content code { background: #f3f4f6; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.875rem; }
        .kb-content pre code { background: none; padding: 0; }
        .kb-content ul, .kb-content ol { margin-bottom: 1rem; padding-left: 1.5rem; }
        .kb-content li { margin-bottom: 0.25rem; }
        .kb-content blockquote { border-left: 4px solid #3b82f6; padding-left: 1rem; color: #6b7280; margin-bottom: 1rem; font-style: italic; }
        .kb-content img { max-width: 100%; height: auto; border-radius: 0.5rem; }
        body.immersion-mode > footer { display: none !important; }
        body.immersion-mode #ai-chat-btn,
        body.immersion-mode #ai-chat-panel { display: none !important; }
        body.immersion-mode #kb-article { max-width: 100% !important; }
        body.immersion-mode #kb-article article {
            max-width: 720px; margin: 0 auto; border: none !important;
            box-shadow: none !important; background: transparent !important; padding: 24px 16px !important;
        }
        body.immersion-mode .kb-content { font-size: 16px; line-height: 1.9; }
        body.immersion-mode .kb-content p { margin-bottom: 1.25rem; }
        body.immersion-mode .kb-content h2 { font-size: 1.6rem; }
        body.immersion-mode .kb-content h3 { font-size: 1.3rem; }
        @media (max-width: 640px) {
            body.immersion-mode #kb-article article { padding: 16px 12px !important; }
            body.immersion-mode .kb-content { font-size: 15px; }
        }        /* 🔀搜索建议下拉框*/
        .kb-search-wrap { position: relative; max-width: 560px; margin: 0 auto; }
        #kb-suggestions {
            position: absolute; top: 100%; left: 0; right: 0; z-index: 100;
            background: #fff; border: 1px solid #e5e7eb; border-radius: 0 0 12px 12px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1); margin-top: 2px;
            display: none; max-height: 320px; overflow-y: auto;
        }
        #kb-suggestions .s-item {
            display: block; width: 100%; padding: 10px 16px; text-align: left;
            font-size: 13px; color: #374151; cursor: pointer; border: none;
            background: none; border-bottom: 1px solid #f3f4f6; text-decoration: none;
        }
        #kb-suggestions .s-item:last-child { border-bottom: none; border-radius: 0 0 12px 12px; }
        #kb-suggestions .s-item:hover,
        #kb-suggestions .s-item.active { background: #eff6ff; color: #1d4ed8; }
        #kb-suggestions .s-item .s-cat {
            font-size: 11px; color: #9ca3af; margin-left: 6px;
        }
        #kb-suggestions .s-item .s-highlight { color: #2563eb; font-weight: 600; }
        /* 🤖 AI 聊天样式 */
        #ai-chat-btn {
            position: fixed; bottom: 24px; right: 24px; z-index: 9999;
            width: 56px; height: 56px; border-radius: 50%;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff; border: none; cursor: pointer;
            box-shadow: 0 4px 16px rgba(37,99,235,0.4);
            font-size: 24px; transition: all .2s;
            display: flex; align-items: center; justify-content: center;
        }
        #ai-chat-btn:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(37,99,235,0.5); }
        #ai-chat-btn .badge {
            position: absolute; top: -4px; right: -4px;
            width: 18px; height: 18px; border-radius: 50%;
            background: #ef4444; color: #fff;
            font-size: 10px; display: flex; align-items: center; justify-content: center;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%,100% { transform: scale(1); } 50% { transform: scale(1.15); } }
        #ai-chat-panel {
            position: fixed; bottom: 90px; right: 24px; z-index: 9998;
            width: 380px; max-width: calc(100vw - 48px); height: 560px; max-height: calc(100vh - 160px);
            background: #fff; border-radius: 16px; box-shadow: 0 8px 40px rgba(0,0,0,0.15);
            display: none; flex-direction: column; overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        #ai-chat-panel.open { display: flex; }
        #ai-chat-header {
            padding: 14px 16px; background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            color: #fff; display: flex; align-items: center; justify-content: space-between;
            border-radius: 16px 16px 0 0; flex-shrink: 0;
        }
        #ai-chat-header h3 { font-size: 14px; font-weight: 600; margin: 0; display: flex; align-items: center; gap: 6px; }
        #ai-chat-header .close-btn { background: none; border: none; color: rgba(255,255,255,0.7); cursor: pointer; font-size: 20px; padding: 0; line-height: 1; }
        #ai-chat-header .close-btn:hover { color: #fff; }
        #ai-chat-messages {
            flex: 1; overflow-y: auto; padding: 16px; background: #f8fafc;
            display: flex; flex-direction: column; gap: 12px;
        }
        .ai-msg { display: flex; gap: 8px; max-width: 85%; }
        .ai-msg.user { align-self: flex-end; flex-direction: row-reverse; }
        .ai-msg.bot { align-self: flex-start; }
        .ai-msg .avatar {
            width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center; font-size: 14px;
        }
        .ai-msg.user .avatar { background: #1d4ed8; color: #fff; }
        .ai-msg.bot .avatar { background: #e0e7ff; color: #4338ca; }
        .ai-msg .bubble {
            padding: 10px 14px; border-radius: 12px; font-size: 13px; line-height: 1.6;
            word-break: break-word;
        }
        .ai-msg.user .bubble { background: #1d4ed8; color: #fff; }
        .ai-msg.bot .bubble { background: #fff; color: #374151; border: 1px solid #e5e7eb; }
        /* AI 聊天输入区 */
        #ai-chat-footer {
            display: flex; gap: 8px; padding: 12px 16px;
            border-top: 1px solid #e5e7eb; background: #fff;
            flex-shrink: 0; align-items: stretch;
        }
        #ai-chat-footer textarea {
            flex: 1; border: 1px solid #d1d5db; border-radius: 10px;
            padding: 10px 14px; font-size: 13px; line-height: 1.5;
            resize: none; outline: none; font-family: inherit;
            min-height: 40px; max-height: 100px;
        }
        #ai-chat-footer textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.15); }
        #ai-chat-send {
            width: 40px; border-radius: 10px;
            background: #1d4ed8; color: #fff; border: none;
            cursor: pointer; display: flex; align-items: center;
            justify-content: center; flex-shrink: 0;
            transition: background .15s;
        }
        #ai-chat-send:hover { background: #1e40af; }
        #ai-chat-send:disabled { opacity: 0.4; cursor: not-allowed; }
        /* 欢迎语 */
        #ai-chat-welcome { text-align: center; padding: 16px; }
        #ai-chat-welcome .icon { font-size: 40px; margin-bottom: 8px; }
        #ai-chat-welcome h4 { font-size: 15px; font-weight: 600; color: #1f2937; margin: 0 0 6px; }
        #ai-chat-welcome p { font-size: 13px; color: #6b7280; margin: 0; line-height: 1.6; }
        /* 打字指示器 */
        .ai-typing .bubble { display: flex; gap: 4px; align-items: center; padding: 14px 18px !important; }
        .ai-typing .bubble span {
            width: 8px; height: 8px; border-radius: 50%;
            background: #d1d5db; display: inline-block;
            animation: typingBounce 1.4s infinite ease-in-out both;
        }
        .ai-typing .bubble span:nth-child(1) { animation-delay: -0.32s; }
        .ai-typing .bubble span:nth-child(2) { animation-delay: -0.16s; }
        .ai-typing .bubble span:nth-child(3) { animation-delay: 0s; }
        @keyframes typingBounce {
            0%, 80%, 100% { transform: scale(0.6); }
            40% { transform: scale(1); }
        }
        .sources { margin-top: 6px; padding-top: 6px; border-top: 1px solid rgba(0,0,0,0.05); }
        .confidence { font-size: 11px; color: #9ca3af; margin-top: 4px; }
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
        .kb-content { font-size: var(--pg-font-size); }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.nav')

    <main class="pt-24 pb-16">
        <div class="{{ site_setting('page_width', 'max-w-6xl') }} mx-auto px-4 sm:px-6 lg:px-8" id="kb-app">
            <!-- 加载 -->
            <div id="loading-state" class="text-center py-20">
                <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4"></div>
                <p class="text-gray-500">加载中...</p>
            </div>

            <!-- 搜索首页 -->
            <div id="kb-home" class="hidden">
                <div class="text-center mb-12">
                    <h1 class="text-4xl font-bold text-gray-900 mb-3">📚 帮助中心</h1>
                    <p class="text-gray-500 text-lg mb-6">搜索常见问题、集成指南和产品文档</p>
                    <div class="max-w-2xl mx-auto">
                        <div class="kb-search-wrap">
                            <svg class="w-5 h-5 text-gray-400 absolute left-4 top-1/2 -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            <input type="text" id="kb-search-input" class="w-full px-4 py-3 pl-10 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 block" onkeydown="onSearchKeydown(event)" oninput="onSearchInput()" placeholder="搜索文章标题或内容..." autocomplete="off" />
                            <div id="kb-suggestions"></div>
                        </div>
                    </div>
                    <div id="search-suggestions" class="max-w-2xl mx-auto mt-4 hidden">
                        <p class="text-xs text-gray-400 mb-2">热门搜索：</p>
                        <div id="popular-tags" class="flex flex-wrap gap-2 justify-center"></div>
                    </div>
                </div>

                <!-- 分类导航 + 文章列表 -->
                <div id="kb-categories" class="max-w-4xl mx-auto">
                    <!-- 分类快速导航-->
                    <div id="category-nav" class="flex flex-wrap gap-2 justify-center mb-10"></div>
                    <!-- 分类文章区-->
                    <div id="category-sections" class="space-y-10"></div>
                </div>
            </div>

            <!-- 搜索结果 -->
            <div id="kb-search-results" class="hidden max-w-4xl mx-auto">
                <button onclick="showHome()" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700 mb-6 mt-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    返回首页
                </button>
                <p id="search-result-count" class="text-sm text-gray-500 mb-6"></p>
                <div id="search-results-list" class="space-y-3"></div>
            </div>

            <!-- 文章详情 -->
            <div id="kb-article" class="hidden max-w-3xl mx-auto">
                <div class="flex items-center justify-between mb-6 mt-2">
                    <button onclick="showHome()" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        返回帮助中心
                    </button>
                    <button id="immersion-btn" onclick="toggleImmersion()" class="inline-flex items-center gap-1 text-xs px-3 py-1.5 rounded-full border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition" title="沉浸式阅读">
                        <span id="immersion-icon">📖</span>
                        <span id="immersion-text">沉浸阅读</span>
                    </button>
                </div>
                <article class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <div class="flex items-center gap-2 text-xs text-gray-400 mb-4">
                        <span id="article-category" class="bg-primary-50 text-primary-700 px-2.5 py-1 rounded-full font-medium"></span>
                        <span id="article-date"></span>
                        <span id="article-views"></span>
                    </div>
                    <h1 id="article-title" class="text-2xl md:text-3xl font-bold text-gray-900 mb-6"></h1>
                    <!-- 📑 文章目录 -->
                    <div id="article-toc" class="hidden mb-6 p-4 bg-gray-50 rounded-xl border border-gray-100">
                        <div class="text-sm font-semibold text-gray-700 mb-2">📑 目录</div>
                        <div id="toc-list" class="space-y-1"></div>
                    </div>
                    <div id="article-content" class="kb-content text-gray-700"></div>
                    <!-- 上一篇 / 下一篇 -->
                    <div id="article-prev-next" class="flex items-center justify-between gap-4 mt-8 pt-6 border-t border-gray-100 hidden">
                        <div id="prev-article-wrap" class="flex-1 min-w-0">
                            <a id="prev-article-link" href="javascript:void(0)" onclick="" class="group flex items-center gap-2 text-sm text-gray-500 hover:text-primary-600 transition">
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                <div class="min-w-0">
                                    <div class="text-xs text-gray-400 mb-0.5">上一篇</div>
                                    <div id="prev-article-title" class="font-medium truncate"></div>
                                </div>
                            </a>
                        </div>
                        <div id="next-article-wrap" class="flex-1 min-w-0 text-right">
                            <a id="next-article-link" href="javascript:void(0)" onclick="" class="group flex items-center justify-end gap-2 text-sm text-gray-500 hover:text-primary-600 transition">
                                <div class="min-w-0">
                                    <div class="text-xs text-gray-400 mb-0.5">下一篇</div>
                                    <div id="next-article-title" class="font-medium truncate"></div>
                                </div>
                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                    <!-- ⭐收藏 -->
                    <div class="border-t border-gray-100 mt-6 pt-6 text-center">
                        <button id="kb-fav-btn" onclick="toggleKbFav()" class="inline-flex items-center gap-1.5 text-sm px-5 py-2 rounded-full border border-gray-200 bg-white hover:bg-amber-50 hover:border-amber-200 transition font-medium">
                            <span id="kb-fav-icon">⭐</span>
                            <span id="kb-fav-text">收藏文章</span>
                        </button>
                    </div>
                    <!-- 反馈 -->
                    <div class="border-t border-gray-100 mt-8 pt-6 text-center">
                        <p class="text-sm text-gray-500 mb-3">这篇文章有帮助吗？</p>
                        <div class="flex items-center justify-center gap-3">
                            <button id="feedback-yes" onclick="sendFeedback(true)" class="px-5 py-2 bg-green-50 text-green-700 rounded-lg text-sm font-medium hover:bg-green-100 transition border border-green-200">👍 有帮助</button>
                            <button id="feedback-no" onclick="sendFeedback(false)" class="px-5 py-2 bg-red-50 text-red-700 rounded-lg text-sm font-medium hover:bg-red-100 transition border border-red-200">👎 没帮助</button>
                        </div>
                        <p id="feedback-msg" class="text-sm text-gray-400 mt-3 hidden"></p>
                    </div>
                    <!-- 相关文章 -->
                    <div id="related-articles" class="border-t border-gray-100 mt-6 pt-6 hidden">
                        <h3 class="text-sm font-semibold text-gray-900 mb-3">相关文章</h3>
                        <div id="related-list" class="space-y-2"></div>
                    </div>
                </article>
            </div>
        </div>
    </main>

    @include('public.partials.footer')

    <!-- 🤖 AI 智能问答 -->
    <button id="ai-chat-btn" onclick="toggleAIChat()" title="AI 智能问答">
        🤖
        <span class="badge" id="ai-chat-badge"></span>
    </button>
    <div id="ai-chat-panel">
        <div id="ai-chat-header">
            <h3>🤖 AI 智能问答</h3>
            <button class="close-btn" onclick="toggleAIChat()">&times;</button>
        </div>
        <div id="ai-chat-messages">
            <div id="ai-chat-welcome">
                <div class="icon">🤖</div>
                <h4>有什么可以帮助您的？</h4>
                <p>我可以回答关于产品使用、集成开发、<br>常见问题等各方面的问题。</p>
            </div>
        </div>
        <div id="ai-chat-footer">
            <textarea class="ai-chat-input" rows="2" placeholder="输入您的问题..." onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sendAIQuestion()}"></textarea>
            <button id="ai-chat-send" onclick="sendAIQuestion()" title="发送">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                    </button>
        </div>
    </div>

    <script>
    const API = '/api';
    let _sessionId = 'kb_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    let _currentArticleId = null;

    // ─── 加载分类和文章───
    async function loadHome() {
        try {
            // 隐藏加载状态，显示首页
            document.getElementById('loading-state').classList.add('hidden');
            document.getElementById('kb-home').classList.remove('hidden');
            const res = await fetch(API + '/kb/categories?locale=zh-CN');
            const data = await res.json();
            const categories = data.data || [];
            const container = document.getElementById('category-sections');
            const nav = document.getElementById('category-nav');

            if (categories.length === 0) {
                container.innerHTML = '<div class="text-center py-16 text-gray-400"><p class="text-5xl mb-4">📚</p><p>暂无文章分类</p></div>';
                return;
            }

            // 分类导航按钮
            nav.innerHTML = categories.map(function(cat, idx) {
                var count = (cat.articles || []).length;
                return '<button onclick="document.getElementById(\'cat-section-\' + ' + cat.id + ').scrollIntoView({behavior:\'smooth\',block:\'start\'})" ' +
                    'class="px-4 py-2 rounded-xl text-sm font-medium transition bg-white border border-gray-200 text-gray-600 hover:bg-primary-50 hover:text-primary-700 hover:border-primary-200 shadow-sm">' +
                    cat.name +
                    (count > 0 ? ' <span class="text-xs text-gray-400 ml-1">(' + count + ')</span>' : '') +
                    '</button>';
            }).join('');

            // 分类文章卡片
            container.innerHTML = categories.map(function(cat) {
                var catArticles = cat.articles || [];
                var articlesHtml = catArticles.map(function(a) {
                    return '<a href="javascript:void(0)" onclick="loadArticle(' + a.id + ');return false" class="block p-4 rounded-xl border border-gray-100 hover:border-primary-100 hover:shadow-md transition-all">' +
                        '<h4 class="font-medium text-gray-900 flex items-center gap-2">' +
                        '<span class="w-1.5 h-1.5 rounded-full bg-primary-400 flex-shrink-0"></span>' +
                        a.title + '</h4>' +
                        (a.excerpt ? '<p class="text-sm text-gray-500 mt-1 ml-4 line-clamp-2">' + a.excerpt + '</p>' : '') +
                        '<div class="flex items-center gap-3 mt-2 ml-4 text-xs text-gray-400">' +
                        '<span>👁︀' + (a.view_count || 0) + ' 次阅读</span>' +
                        '</div>' +
                        '</a>';
                }).join('');

                var count = catArticles.length;

                return '<div id="cat-section-' + cat.id + '" class="bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">' +
                    '<div class="flex items-center justify-between mb-4">' +
                    '<div class="flex items-center gap-3">' +
                    '<span class="w-2 h-8 bg-primary-500 rounded-full"></span>' +
                    '<div>' +
                    '<h2 class="text-xl font-bold text-gray-900">' + cat.name + '</h2>' +
                    (cat.description ? '<p class="text-sm text-gray-400 mt-0.5">' + cat.description + '</p>' : '') +
                    '</div>' +
                    '</div>' +
                    '<span class="text-xs bg-gray-100 text-gray-500 px-3 py-1 rounded-full font-medium">' + count + ' 篇</span>' +
                    '</div>' +
                    '<div class="space-y-2">' + (articlesHtml || '<p class="text-sm text-gray-400 py-4 text-center">暂无文章</p>') + '</div>' +
                    '</div>';
            }).join('');
        } catch(e) {
            document.getElementById('category-sections').innerHTML = '<div class="text-center py-16 text-gray-400"><p>加载失败</p></div>';
        }
    }

    // ─── 搜索 ───
    async function searchArticles() {
        var q = document.getElementById('kb-search-input').value.trim();
        if (!q) { showHome(); return; }
        document.getElementById('kb-home').classList.add('hidden');
        document.getElementById('kb-article').classList.add('hidden');
        document.getElementById('kb-search-results').classList.remove('hidden');
        document.getElementById('search-result-count').textContent = '搜索："' + q + '"';
        try {
            var res = await fetch(API + '/kb/search?q=' + encodeURIComponent(q));
            var data = await res.json();
            var articles = data.data?.articles?.data || data.data || [];
            if (articles.length === 0) {
                document.getElementById('search-results-list').innerHTML = '<div class="text-center py-16 text-gray-400"><p class="text-5xl mb-4">🔍</p><p>未找到相关文章，请尝试其他关键词</p></div>';
                return;
            }
            document.getElementById('search-results-list').innerHTML = articles.map(function(a) {
                return '<a href="javascript:void(0)" onclick="loadArticle(' + a.id + ');return false" class="block p-4 rounded-xl border border-gray-100 hover:border-primary-100 hover:shadow-md transition-all">' +
                    '<div class="flex items-center gap-2 mb-1">' +
                    (a.category ? '<span class="text-xs bg-primary-50 text-primary-700 px-2 py-0.5 rounded-full">' + a.category.name + '</span>' : '') +
                    '</div>' +
                    '<h3 class="font-semibold text-gray-900">' + a.title + '</h3>' +
                    (a.excerpt ? '<p class="text-sm text-gray-500 mt-1 line-clamp-2">' + a.excerpt + '</p>' : '') +
                    '</a>';
            }).join('');
        } catch(e) {
            document.getElementById('search-results-list').innerHTML = '<div class="text-center py-16 text-gray-400"><p>搜索失败</p></div>';
        }
    }

    // ─── 加载文章详情 ───
    async function loadArticle(id) {
        _currentArticleId = id;
        document.getElementById('kb-home').classList.add('hidden');
        document.getElementById('kb-search-results').classList.add('hidden');
        document.getElementById('kb-article').classList.remove('hidden');

        try {
            var res = await fetch(API + '/kb/articles/' + id);
            var data = await res.json();
            var d = data.data || {};
            var article = d.article || d;
            var related = d.related_articles || [];

            document.getElementById('article-category').textContent = article.category?.name || '';
            document.getElementById('article-category').className = article.category?.name
                ? 'bg-primary-100 text-primary-700 px-2.5 py-1 rounded-full font-medium text-xs'
                : 'hidden';
            document.getElementById('article-date').textContent = article.published_at ? new Date(article.published_at).toLocaleDateString('zh-CN') : '';
            document.getElementById('article-views').textContent = article.view_count + ' 次阅读';
            document.getElementById('article-title').textContent = article.title;
            document.getElementById('article-content').innerHTML = article.content;
            document.title = article.title + ' - 帮助中心 - 互物通';
            // 🎬 嵌入视频
            embedVideos();
            // 📑 生成目录
            buildArticleToc();
            // ⭀收藏状态
            loadKbFavStatus();

            // 相关文章
            if (related.length > 0) {
                document.getElementById('related-articles').classList.remove('hidden');
                document.getElementById('related-list').innerHTML = related.map(function(r) {
                    return '<a href="javascript:void(0)" onclick="loadArticle(' + r.id + ');return false" class="block text-sm text-primary-600 hover:text-primary-700 font-medium">' + r.title + '</a>';
                }).join('');
            } else {
                document.getElementById('related-articles').classList.add('hidden');
            }

            // 上一篇 / 下一篇
            var prevArticle = d.prev_article;
            var nextArticle = d.next_article;
            var prevNextEl = document.getElementById('article-prev-next');
            if (prevArticle || nextArticle) {
                prevNextEl.classList.remove('hidden');
                if (prevArticle) {
                    document.getElementById('prev-article-title').textContent = prevArticle.title;
                    document.getElementById('prev-article-link').onclick = function() { loadArticle(prevArticle.id); return false; };
                    document.getElementById('prev-article-wrap').classList.remove('hidden');
                } else {
                    document.getElementById('prev-article-title').textContent = '';
                    document.getElementById('prev-article-wrap').classList.add('hidden');
                }
                if (nextArticle) {
                    document.getElementById('next-article-title').textContent = nextArticle.title;
                    document.getElementById('next-article-link').onclick = function() { loadArticle(nextArticle.id); return false; };
                    document.getElementById('next-article-wrap').classList.remove('hidden');
                } else {
                    document.getElementById('next-article-title').textContent = '';
                    document.getElementById('next-article-wrap').classList.add('hidden');
                }
            } else {
                prevNextEl.classList.add('hidden');
            }

            document.getElementById('feedback-msg').classList.add('hidden');
        } catch(e) {
            document.getElementById('article-content').innerHTML = '<p class="text-center text-gray-400 py-10">文章加载失败</p>';
        }
    }

    // ─── 反馈 ───
    async function sendFeedback(isHelpful) {
        if (!_currentArticleId) return;
        try {
            var res = await fetch(API + '/kb/articles/' + _currentArticleId + '/feedback', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ is_helpful: isHelpful, session_id: _sessionId }),
            });
            var data = await res.json();
            if (data.success) {
                var msg = document.getElementById('feedback-msg');
                msg.textContent = '感谢您的反馈！';
                msg.className = 'text-sm text-green-600 mt-3';
                msg.classList.remove('hidden');
                document.getElementById('feedback-yes').disabled = true;
                document.getElementById('feedback-no').disabled = true;
            }
        } catch(e) {}
    }

    function showHome() {
        document.getElementById('kb-home').classList.remove('hidden');
        document.getElementById('kb-search-results').classList.add('hidden');
        document.getElementById('kb-article').classList.add('hidden');
    }

    // ─── ⭐收藏文章 ───
    var kbFaved = false;

    async function loadKbFavStatus() {
        var token = localStorage.getItem('auth_token');
        if (!token || !_currentArticleId) return;
        try {
            var res = await fetch('/api/user/interactions/status?type=kb_article&id=' + _currentArticleId, {
                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token }
            });
            var data = await res.json();
            if (data.data) {
                kbFaved = data.data.is_favorited || false;
                updateKbFavBtn();
            }
        } catch(e) {}
    }

    async function toggleKbFav() {
        var token = localStorage.getItem('auth_token');
        if (!token) { alert('请先登录'); window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }
        try {
            var res = await fetch('/api/user/interactions/kb/favorite', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json' },
                body: JSON.stringify({ article_id: _currentArticleId })
            });
            var data = await res.json();
            if (data.success) {
                kbFaved = !kbFaved;
                updateKbFavBtn();
            }
        } catch(e) {}
    }

    function updateKbFavBtn() {
        document.getElementById('kb-fav-icon').textContent = kbFaved ? '❤️' : '⭐';
        document.getElementById('kb-fav-text').textContent = kbFaved ? '已收藏' : '收藏文章';
    }

    // ─── 沉浸式阅读 ───
    var immersionMode = false;
    function toggleImmersion() {
        immersionMode = !immersionMode;
        document.body.classList.toggle('immersion-mode', immersionMode);
        document.getElementById('immersion-icon').textContent = immersionMode ? '📖' : '📖';
        document.getElementById('immersion-text').textContent = immersionMode ? '退出沉浸' : '沉浸阅读';
    }

    // ─── 🎬 嵌入视频 ───
    function embedVideos() {
        var content = document.getElementById('article-content');
        if (!content) return;
        // 替换 Markdown 格式的视频链接
        content.querySelectorAll('a').forEach(function(a) {
            var href = a.getAttribute('href') || '';
            var match = href.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|bilibili\.com\/video\/)([\w-]+)/i);
            if (match) {
                var platform = href.includes('bilibili') ? 'bilibili' : 'youtube';
                var div = document.createElement('div');
                div.className = 'video-embed';
                div.innerHTML = getVideoEmbedHtml(match[1], platform, a.textContent || '视频教程');
                a.parentNode.replaceChild(div, a);
            }
        });
    }
    function getVideoEmbedHtml(videoId, platform, label) {
        var src = '';
        var badge = '🎬 ' + label;
        if (platform === 'youtube') { src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1'; }
        else { src = '//player.bilibili.com/player.html?bvid=' + videoId + '&autoplay=1&high_quality=1'; }
        return '<div class="video-badge">' + badge + '</div>' +
            '<div class="play-overlay" onclick="this.parentElement.classList.add(\'loaded\');var ifr=this.nextElementSibling;ifr.src=ifr.getAttribute(\'data-src\')">' +
            '<div class="play-icon">▀</div></div>' +
            '<iframe data-src="' + src + '" src="" allowfullscreen loading="lazy"></iframe>';
    }
    // ─── 文章目录生成 ───
    function buildArticleToc() {
        var content = document.getElementById('article-content');
        var tocContainer = document.getElementById('article-toc');
        var tocList = document.getElementById('toc-list');
        var headings = content.querySelectorAll('h2, h3');
        if (headings.length < 2) { tocContainer.classList.add('hidden'); return; }

        // 为每个标题添加 id 锚点
        headings.forEach(function(h, i) {
            var id = 'toc-' + i;
            h.id = id;
        });

        // 构建目录
        tocList.innerHTML = '';
        headings.forEach(function(h) {
            var a = document.createElement('a');
            a.href = '#' + h.id;
            a.className = 'block text-sm text-gray-500 hover:text-primary-600 py-1 transition' + (h.tagName === 'H3' ? ' pl-4 text-xs' : '');
            a.textContent = h.textContent;
            tocList.appendChild(a);
        });
        tocContainer.classList.remove('hidden');
    }

    // ─── 搜索建议 ───
    var suggestTimer = null;
    var suggestIndex = -1;

    function onSearchInput() {
        clearTimeout(suggestTimer);
        suggestTimer = setTimeout(fetchSuggestions, 200);
    }

    async function fetchSuggestions() {
        var q = document.getElementById('kb-search-input').value.trim();
        var box = document.getElementById('kb-suggestions');
        if (q.length < 1) { hideSuggestions(); return; }
        try {
            var res = await fetch(API + '/kb/suggest?q=' + encodeURIComponent(q));
            var data = await res.json();
            var suggestions = data.data || [];
            if (suggestions.length === 0) { hideSuggestions(); return; }
            box.innerHTML = suggestions.map(function(a) {
                var title = highlightMatch(a.title, q);
                var cat = a.category ? '<span class="s-cat">#' + a.category.name + '</span>' : '';
                return '<button class="s-item" data-id="' + a.id + '" onclick="loadArticle(' + a.id + ');hideSuggestions()">' +
                    title + cat + '</button>';
            }).join('');
            box.style.display = 'block';
        } catch(e) { hideSuggestions(); }
    }

    function onSearchKeydown(e) {
        var items = document.querySelectorAll('#kb-suggestions .s-item');
        if (e.key === 'Enter') {
            if (suggestIndex >= 0 && items[suggestIndex]) {
                var id = items[suggestIndex].getAttribute('data-id');
                if (id) { loadArticle(parseInt(id)); hideSuggestions(); return; }
            }
            searchArticles();
            hideSuggestions();
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            suggestIndex = Math.min(suggestIndex + 1, items.length - 1);
            updateActiveSuggestion(items);
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            suggestIndex = Math.max(suggestIndex - 1, -1);
            updateActiveSuggestion(items);
            return;
        }
        if (e.key === 'Escape') {
            hideSuggestions();
            return;
        }
    }

    function updateActiveSuggestion(items) {
        items.forEach(function(el, i) {
            el.classList.toggle('active', i === suggestIndex);
        });
        if (suggestIndex >= 0 && items[suggestIndex]) {
            var input = document.getElementById('kb-search-input');
            if (input) input.value = items[suggestIndex].textContent;
        }
    }

    function hideSuggestions() {
        var box = document.getElementById('kb-suggestions');
        if (box) box.style.display = 'none';
        suggestIndex = -1;
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    function highlightMatch(text, query) {
        if (!query) return text;
        var idx = text.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) return text;
        return text.substring(0, idx) + '<span class="s-highlight">' + text.substring(idx, idx + query.length) + '</span>' + text.substring(idx + query.length);
    }

    // 点击外部关闭建议
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.kb-search-wrap')) hideSuggestions();
    });

    // ─── 🤖 AI 智能问答 ───
    var chatOpen = false;
    var chatLoading = false;

    function toggleAIChat() {
        chatOpen = !chatOpen;
        document.getElementById('ai-chat-panel').classList.toggle('open', chatOpen);
        document.getElementById('ai-chat-badge').style.display = 'none';
        if (chatOpen) {
            document.getElementById('ai-chat-footer').querySelector('textarea').focus();
        }
    }

    function addChatMessage(role, text, sources, confidence) {
        var container = document.getElementById('ai-chat-messages');
        var div = document.createElement('div');
        div.className = 'ai-msg ' + role;
        var avatar = role === 'bot' ? '🤖' : '👤';
        var bubble = '<div class="bubble">' + escapeHtml(text);

        if (role === 'bot' && sources && sources.length > 0) {
            bubble += '<div class="sources">📎 ' + sources.map(function(s) {
                var srcId = s.source_id || s.id;
                var title = escapeHtml(s.title);
                if (srcId) {
                    return '<a href="javascript:void(0)" onclick="loadArticle(' + srcId + ');return false" class="text-primary-600 hover:text-primary-700 font-medium text-xs" style="margin-right:8px">' + title + '</a>';
                }
                return '<span class="text-xs text-gray-500" style="margin-right:8px">' + title + '</span>';
            }).join('') + '</div>';
            if (confidence !== undefined) {
                var pct = Math.round(confidence * 100);
                bubble += '<div class="confidence">' + (pct >= 70 ? '🟢' : pct >= 40 ? '🟡' : '🔴') + ' 可信度 ' + pct + '%</div>';
            }
        }

        bubble += '</div>';
        div.innerHTML = '<div class="avatar" style="width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;background:' + (role === 'bot' ? '#e0e7ff' : '#1d4ed8') + ';color:' + (role === 'bot' ? '#4338ca' : '#fff') + '">' + avatar + '</div>' + bubble;
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function showTyping() {
        var container = document.getElementById('ai-chat-messages');
        var div = document.createElement('div');
        div.className = 'ai-msg bot ai-typing';
        div.id = 'ai-typing-indicator';
        div.innerHTML = '<div class="avatar">🤖</div><div class="bubble"><span></span><span></span><span></span></div>';
        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    function removeTyping() {
        var el = document.getElementById('ai-typing-indicator');
        if (el) el.remove();
    }

    async function sendAIQuestion() {
        if (chatLoading) return;
        var input = document.querySelector('#ai-chat-footer textarea');
        if (!input) return;
        var text = input.value.trim();
        if (!text) return;
        input.value = '';
        addChatMessage('user', text);
        chatLoading = true;
        document.getElementById('ai-chat-send').disabled = true;
        showTyping();
        try {
            var res = await fetch(API + '/rag/ask', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ q: text, session_id: _sessionId })
            });
            var data = await res.json();
            removeTyping();
            if (data.success && data.data) {
                var answer = data.data.answer || data.data.text || '';
                var sources = data.data.sources || [];
                var confidence = data.data.confidence || 0;
                addChatMessage('bot', answer, sources, confidence);
            } else {
                addChatMessage('bot', '抱歉，暂时无法回答这个问题，请稍后重试');
            }
        } catch(e) {
            removeTyping();
            addChatMessage('bot', '网络连接失败，请检查网络后重试');
        }
        chatLoading = false;
        document.getElementById('ai-chat-send').disabled = false;
        var inputEl = document.querySelector('#ai-chat-footer textarea');
        if (inputEl) inputEl.focus();
    }

    // ─── 初始化 ───
    document.addEventListener('DOMContentLoaded', function() {
        // 如果 URL 中包含文章 ID，自动加载（不显示首页）
        var pathParts = window.location.pathname.split('/');
        var lastPart = pathParts[pathParts.length - 1];
        if (lastPart && /^\d+$/.test(lastPart) && parseInt(lastPart) > 0) {
            document.getElementById('loading-state').classList.remove('hidden');
            document.getElementById('kb-home').classList.add('hidden');
            loadArticle(parseInt(lastPart));
        } else {
            loadHome();
        }
        // 确保 AI 聊天面板默认关闭
        document.getElementById('ai-chat-panel').classList.remove('open');
    });
</script>
</body>
</html>
