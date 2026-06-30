<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ site_setting('seo_title', '互物搜索') }}</title>
    @vite('resources/css/public.css')
</head>
<body>
@include('public.partials.nav')

<div class="min-h-[80vh] bg-gradient-to-b from-white to-gray-50">
    <div class="flex flex-col items-center justify-center px-4 pt-24 pb-16">
        <div class="w-full max-w-3xl mx-auto text-center">
            <div class="inline-flex items-center gap-3 mb-4">
                @php $logo = site_setting('logo_url'); $siteName = site_setting('site_name', '互物搜索'); @endphp
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-14 w-auto object-contain" />
                @else
                    <div class="w-14 h-14 bg-gradient-to-br from-primary-500 to-primary-700 rounded-2xl flex items-center justify-center shadow-lg shadow-primary-200">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                @endif
                <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 tracking-tight">{{ $siteName }}</h1>
            </div>

            <form id="search-form" class="relative max-w-2xl mx-auto mb-2" onsubmit="return doSearch(event)">
                <div class="flex items-center bg-white border-2 border-gray-200 rounded-2xl shadow-sm hover:shadow-md hover:border-primary-300 focus-within:border-primary-500 focus-within:shadow-md transition-all duration-200">
                    <svg class="w-5 h-5 text-gray-400 ml-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="search-input" type="text" class="flex-1 border-0 bg-transparent px-4 py-4 text-lg text-gray-900 placeholder-gray-400 focus:ring-0 outline-none" placeholder="搜索商品、文章、应用、用户..." autocomplete="off" value="{{ request('q') }}" />
                    <button type="submit" class="mr-2 px-6 py-2.5 bg-primary-500 hover:bg-primary-600 text-white rounded-xl font-medium transition-colors shadow-sm">搜索</button>
                </div>
                <!-- 搜索建议下拉 -->
                <div id="search-suggestions" class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-lg z-50 hidden text-left overflow-hidden">
                    <div id="suggestion-list"></div>
                </div>
            </form>

            <div class="flex items-center justify-center gap-2 flex-wrap text-sm mb-2">
                <span class="text-gray-400">热门：</span>
                <a href="/search?q=License" class="px-3 py-1 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-gray-500 transition">License</a>
                <a href="/search?q=SDK" class="px-3 py-1 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-gray-500 transition">SDK</a>
                <a href="/search?q=API" class="px-3 py-1 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-gray-500 transition">API</a>
                <a href="/search?q=安装" class="px-3 py-1 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-gray-500 transition">安装</a>
                <a href="/search?q=定价" class="px-3 py-1 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-gray-500 transition">定价</a>
            </div>
            <!-- 搜索历史 -->
            <div id="search-history" class="hidden text-sm text-gray-400 flex items-center justify-center gap-2 flex-wrap"></div>
        </div>
    </div>

    <div id="search-results" class="hidden max-w-4xl mx-auto px-4 pb-16 w-full">
        <div id="results-container" class="pt-2">
            <!-- 搜索统计 -->
            <div id="result-stats" class="mb-3"></div>
            <!-- 分类筛选 -->
            <div id="type-filters" class="flex flex-wrap gap-2 mb-4 hidden"></div>
            <!-- 结果列表 -->
            <div id="result-list"></div>
        </div>
    </div>
</div>

<script>
// ─── 搜索历史 (localStorage) ───
var HISTORY_KEY = 'huwuku_history';
var MAX_HISTORY = 8;
function getHistory() { try { return JSON.parse(localStorage.getItem(HISTORY_KEY)) || []; } catch(e) { return []; } }
function addHistory(q) {
    var h = getHistory().filter(function(item) { return item !== q; });
    h.unshift(q);
    if (h.length > MAX_HISTORY) h = h.slice(0, MAX_HISTORY);
    localStorage.setItem(HISTORY_KEY, JSON.stringify(h));
    renderHistory();
}
function clearHistory() { localStorage.removeItem(HISTORY_KEY); renderHistory(); }
function renderHistory() {
    var h = getHistory(), el = document.getElementById('search-history');
    if (h.length === 0) { el.classList.add('hidden'); return; }
    el.classList.remove('hidden');
    var html = '<span>\u00b7 \u641c\u7d22\u5386\u53f2\uff1a</span>';
    for (var i = 0; i < h.length; i++) {
        html += '<a href="/search?q=' + encodeURIComponent(h[i]) + '" class="hover:text-primary-500 transition">' + h[i] + '</a>';
        if (i < h.length - 1) html += '<span class="text-gray-200">|</span>';
    }
    html += '<button onclick="clearHistory()" class="text-xs text-gray-300 hover:text-red-400 ml-1">\u6e05\u7a7a</button>';
    el.innerHTML = html;
}

// ─── 搜索建议 ───
var suggestTimer = null;
function setupSuggestions() {
    var input = document.getElementById('search-input');
    input.addEventListener('input', function() {
        clearTimeout(suggestTimer);
        var val = input.value.trim();
        if (!val) { hideSuggestions(); return; }
        suggestTimer = setTimeout(function() { showSuggestions(val); }, 200);
    });
    input.addEventListener('blur', function() { setTimeout(hideSuggestions, 200); });
    input.addEventListener('focus', function() {
        var val = input.value.trim();
        if (val) showSuggestions(val);
    });
}
function showSuggestions(val) {
    var history = getHistory();
    var matches = history.filter(function(item) { return item !== val && item.indexOf(val) !== -1; }).slice(0, 5);
    var el = document.getElementById('suggestion-list');
    var container = document.getElementById('search-suggestions');
    if (matches.length === 0) { container.classList.add('hidden'); return; }
    var html = '';
    for (var i = 0; i < matches.length; i++) {
        html += '<div class="px-4 py-2.5 hover:bg-gray-50 cursor-pointer text-sm text-gray-600 flex items-center gap-3" onmousedown="selectSuggestion(\'' + matches[i].replace(/'/g, "\\'") + '\')">';
        html += '<svg class="w-3.5 h-3.5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
        html += '<span>' + matches[i] + '</span></div>';
    }
    el.innerHTML = html;
    container.classList.remove('hidden');
}
function hideSuggestions() { document.getElementById('search-suggestions').classList.add('hidden'); }
function selectSuggestion(q) {
    document.getElementById('search-input').value = q;
    hideSuggestions();
    execSearch(q);
}

// ─── 搜索主逻辑 ───
var rankedData = []; // 缓存搜索结果，供筛选使用
var activeTypeFilter = 'all';

function getQ() { return document.getElementById('search-input').value.trim(); }
async function doSearch(e) { e.preventDefault(); var q = getQ(); if (!q) return false; return await execSearch(q); }

async function execSearch(q) {
    addHistory(q);
    var rd = document.getElementById('search-results');
    var rl = document.getElementById('result-list');
    rd.classList.remove('hidden');
    rl.innerHTML = '<div class="text-center py-16"><svg class="animate-spin w-8 h-8 text-primary-500 mx-auto mb-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg><p class="text-gray-400">搜索中...</p></div>';
    document.getElementById('result-stats').innerHTML = '';
    document.getElementById('type-filters').classList.add('hidden');
    rd.scrollIntoView({behavior:'smooth',block:'start'});
    try {
        var res = await fetch('/api/meilisearch/unified-search?q='+encodeURIComponent(q));
        var j = await res.json();
        if (!j.success) { rl.innerHTML = '<div class="text-center py-16 text-gray-400"><svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p>搜索服务暂不可用</p></div>'; return false; }
        rankedData = j.data.ranked || [];
        activeTypeFilter = 'all';

        if (rankedData.length === 0) {
            rl.innerHTML = '<div class="text-center py-20"><svg class="w-20 h-20 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><p class="text-gray-400 text-lg mb-1">未找到"'+q+'"的相关结果</p><p class="text-gray-300 text-sm">试试其他关键词</p></div>';
            showNoResultTips(rl);
            return false;
        }
        renderTypeFilters(j.data);
        renderResults();
    } catch(e2) { rl.innerHTML = '<div class="text-center py-16 text-red-400">请求失败，请稍后重试</div>'; }
    return false;
}

// ─── 分类筛选 ───
function renderTypeFilters(data) {
    var tf = document.getElementById('type-filters');
    var stats = {};
    for (var k in data.results) stats[k] = data.results[k].total || 0;
    var labels = {products:'商品',kb_articles:'帮助中心',marketplace_apps:'应用市场',forum_posts:'广场',blog_posts:'博客',oa_articles:'公众号',users:'用户'};
    var icons = {products:'📦',kb_articles:'📖',marketplace_apps:'🧩',forum_posts:'💬',blog_posts:'📝',oa_articles:'📢',users:'👤'};
    var html = '<button class="px-3 py-1.5 rounded-full text-sm font-medium transition ' + (activeTypeFilter==='all'?'bg-primary-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200') + '" onclick="setTypeFilter(\'all\')">全部 ('+rankedData.length+')</button>';
    for (var t in stats) {
        var cnt = rankedData.filter(function(h) { return h._content_type === t; }).length;
        if (cnt === 0) continue;
        html += '<button class="px-3 py-1.5 rounded-full text-sm font-medium transition ' + (activeTypeFilter===t?'bg-primary-500 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200') + '" onclick="setTypeFilter(\''+t+'\')">'+(icons[t]||'')+' '+ (labels[t]||t) + ' ('+cnt+')</button>';
    }
    tf.innerHTML = html;
    tf.classList.remove('hidden');
    // 统计
    document.getElementById('result-stats').innerHTML = '<div class="text-sm text-gray-500 mb-3">找到约 <strong>'+rankedData.length+'</strong> 条结果（共 '+Object.keys(stats).length+' 个分类）</div>';
}
function setTypeFilter(type) {
    activeTypeFilter = type;
    renderTypeFilters({results:{}}); // refresh buttons
    renderResults();
}

// ─── 渲染结果 ───
function renderResults() {
    var rl = document.getElementById('result-list');
    var filtered = activeTypeFilter === 'all' ? rankedData : rankedData.filter(function(h) { return h._content_type === activeTypeFilter; });
    if (filtered.length === 0) { rl.innerHTML = '<div class="text-center py-12 text-gray-400">该分类暂无匹配结果</div>'; return; }
    var html = '<div class="space-y-3">';
    var icons = {products:'📦',kb_articles:'📖',marketplace_apps:'🧩',forum_posts:'💬',blog_posts:'📝',oa_articles:'📢',users:'👤'};
    var links = {products:'/products/',kb_articles:'/help/',marketplace_apps:'/build/app-marketplace/',forum_posts:'/build/forum/',blog_posts:'/blog/',oa_articles:'/build/official-accounts/',users:'#'};
    for (var i = 0; i < filtered.length; i++) {
        var h = filtered[i], type = h._content_type || 'other', icon = icons[type] || '📄';
        var title = (h._formatted && (h._formatted.title || h._formatted.name)) || h.title || h.name || '无标题';
        var desc = (h._formatted && h._formatted.content) || h.content || h.description || h.excerpt || h.short_description || '';
        var link = (links[type] || '#') + h.id;
        var avatar = '', authorName = '';
        if (type === 'forum_posts') { authorName = h.user_name; avatar = h.user_avatar; }
        else if (type === 'blog_posts') { authorName = h.author; avatar = h.author_avatar; }
        else if (type === 'oa_articles') { authorName = h.account_name || h.author_name; avatar = h.account_avatar || h.author_avatar; }
        else if (type === 'marketplace_apps') { authorName = h.developer_name; avatar = h.developer_avatar; }
        else if (type === 'products') { authorName = h.merchant_name; }
        else if (type === 'users') { authorName = h.name; avatar = h.avatar; }
        var authorHtml = '';
        if (authorName) {
            var ai = avatar ? '<img src="'+avatar+'" class="w-5 h-5 rounded-full object-cover" />' : '';
            authorHtml = '<div class="flex items-center gap-1.5 mt-1.5">'+ai+'<span class="text-xs text-gray-400">'+authorName+'</span></div>';
        }
        html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow overflow-hidden"><a href="'+link+'" class="block px-5 py-4 hover:bg-gray-50 transition group">';
        html += '<div class="flex items-center gap-1.5 mb-0.5"><span class="text-xs text-gray-400">'+icon+' '+h._content_label+'</span></div>';
        html += '<div class="text-base font-medium text-gray-900 group-hover:text-primary-600 transition mb-0.5">'+title+'</div>';
        html += authorHtml;
        html += '<div class="text-sm text-gray-400 line-clamp-2 mt-1">'+(desc||'').substring(0,200)+'</div></a></div>';
    }
    html += '</div>';
    rl.innerHTML = html;
}

// ─── 无结果推荐 ───
var HOT_TAGS = ['License', 'SDK', 'API', '安装', '定价', '激活', '续费', '试用'];
function showNoResultTips(container) {
    var html = '<div class="mt-8 text-center"><p class="text-sm text-gray-400 mb-3">试试搜索这些热门关键词</p><div class="flex items-center justify-center gap-2 flex-wrap">';
    for (var i = 0; i < HOT_TAGS.length; i++) {
        html += '<a href="/search?q='+HOT_TAGS[i]+'" class="px-3 py-1.5 bg-gray-100 hover:bg-primary-50 hover:text-primary-600 rounded-full text-sm text-gray-500 transition">'+HOT_TAGS[i]+'</a>';
    }
    html += '</div></div>';
    container.insertAdjacentHTML('beforeend', html);
}

// ─── 首页初始化 ───
document.addEventListener('DOMContentLoaded',function(){
    renderHistory();
    setupSuggestions();
    var p=new URLSearchParams(window.location.search),q=p.get('q');
    if(q){document.getElementById('search-input').value=q;execSearch(q);}
});
</script>

@include('public.partials.footer')
</body>
</html>
