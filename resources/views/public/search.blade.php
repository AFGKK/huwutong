<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ site_setting('seo_title', __('app.nav.search')) }}</title>
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        #search-suggestions { max-height: 420px; overflow-y: auto; }
        #search-suggestions .s-item:last-child { border-bottom: none; }
        #suggestion-list a.s-item:hover { background: #f9fafb; }
        #result-list em { font-style: normal; font-weight: 700; }
    </style>
</head>
<body>
@include('public.partials.nav')

<div class="min-h-[80vh] bg-gradient-to-b from-white to-gray-50">
    <div class="flex flex-col items-center justify-center px-4 pt-24 pb-16">
        <div class="w-full max-w-3xl mx-auto text-center">
            <div class="inline-flex items-center gap-3 mb-4">
                @php $logo = site_setting('logo_url'); $siteName = site_setting('site_name', __('app.nav.search')); @endphp
                @if($logo)
                    <img src="{{ $logo }}" alt="{{ $siteName }}" class="h-14 w-auto object-contain" />
                @else
                    <div class="w-14 h-14 bg-gradient-to-br from-slate-800 to-slate-950 rounded-2xl flex items-center justify-center shadow-lg shadow-slate-200">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </div>
                @endif
                <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 tracking-tight">{{ $siteName }}</h1>
            </div>

            <form id="search-form" class="relative max-w-2xl mx-auto mb-2" onsubmit="return doSearch(event)">
                <div class="flex items-center bg-white border-2 border-gray-200 rounded-2xl shadow-sm hover:shadow-md hover:border-slate-300 focus-within:border-slate-800 focus-within:shadow-md transition-all duration-200">
                    <svg class="w-5 h-5 text-gray-400 ml-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input id="search-input" type="text" class="flex-1 border-0 bg-transparent px-3 py-4 text-lg text-gray-900 placeholder-gray-400 focus:ring-0 outline-none" placeholder="{{ __('app.search_page.placeholder') }}" autocomplete="off" value="{{ request('q') }}" />
                    <button type="submit" class="mr-2 px-6 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl font-medium transition-colors shadow-sm">{{ __('app.search_page.button') }}</button>
                </div>
                <!-- 搜索建议下拉 -->
                <div id="search-suggestions" class="absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-gray-200 shadow-lg z-50 hidden text-left overflow-hidden">
                    <div id="suggestion-list"></div>
                </div>
            </form>

            <div class="flex items-center justify-center gap-2 flex-wrap text-sm mb-2">
                <span class="text-slate-400">{{ __('app.search_page.hot') }}</span>
                <a href="/search?q=License" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-full text-slate-500 transition">License</a>
                <a href="/search?q=SDK" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-full text-slate-500 transition">SDK</a>
                <a href="/search?q=API" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-full text-slate-500 transition">API</a>
                <a href="/search?q={{ urlencode(__('app.search_page.hot_install')) }}" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-full text-slate-500 transition">{{ __('app.search_page.hot_install') }}</a>
                <a href="/search?q={{ urlencode(__('app.search_page.hot_pricing')) }}" class="px-3 py-1 bg-slate-100 hover:bg-slate-200 hover:text-slate-900 rounded-full text-slate-500 transition">{{ __('app.search_page.hot_pricing') }}</a>
            </div>
            <div class="flex items-center justify-center gap-2 flex-wrap text-xs mt-1">
                <span class="text-slate-300">∣</span>
                <a href="/search?type=products" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_products') }}</a>
                <a href="/search?type=kb_articles" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_help') }}</a>
                <a href="/search?type=forum_posts" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_community') }}</a>
                <a href="/search?type=blog_posts" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_blog') }}</a>
                <a href="/search?type=oa_articles" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_oa') }}</a>
                <a href="/search?type=marketplace_apps" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_apps') }}</a>
                <a href="/search?type=official_accounts" class="text-slate-400 hover:text-slate-700 transition">{{ __('app.search_page.type_accounts') }}</a>
                <span class="text-slate-300">∣</span>
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
        html += '<a href="/search?q=' + encodeURIComponent(h[i]) + '" class="hover:text-slate-700 transition">' + h[i] + '</a>';
        if (i < h.length - 1) html += '<span class="text-gray-200">|</span>';
    }
    html += '<button onclick="clearHistory()" class="text-xs text-gray-300 hover:text-red-400 ml-1">\u6e05\u7a7a</button>';
    el.innerHTML = html;
}

// ─── 搜索建议（自动补全 ───
var suggestTimer = null;
var suggestXhr = null;
var suggestIndex = -1; // 键盘导航当前项
function setupSuggestions() {
    var input = document.getElementById('search-input');
    input.addEventListener('input', function() {
        clearTimeout(suggestTimer);
        suggestIndex = -1;
        var val = input.value.trim();
        if (!val) { hideSuggestions(); return; }
        suggestTimer = setTimeout(function() { fetchSuggestions(val); }, 200);
    });
    input.addEventListener('keydown', function(e) {
        var items = document.querySelectorAll('#suggestion-list > .s-item');
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            suggestIndex = Math.min(suggestIndex + 1, items.length - 1);
            updateActiveSuggestion(items);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            suggestIndex = Math.max(suggestIndex - 1, -1);
            updateActiveSuggestion(items);
        } else if (e.key === 'Enter' && suggestIndex >= 0 && items[suggestIndex]) {
            e.preventDefault();
            items[suggestIndex].click();
        }
    });
    input.addEventListener('blur', function() { setTimeout(hideSuggestions, 250); });
    input.addEventListener('focus', function() {
        var val = input.value.trim();
        if (val) fetchSuggestions(val);
    });
}
function updateActiveSuggestion(items) {
    for (var i = 0; i < items.length; i++) {
        items[i].classList.toggle('bg-gray-50', i === suggestIndex);
        items[i].classList.toggle('bg-gray-100', i === suggestIndex);
    }
}
function fetchSuggestions(val) {
    if (suggestXhr) { suggestXhr.abort(); }
    var container = document.getElementById('search-suggestions');
    var el = document.getElementById('suggestion-list');
    suggestIndex = -1;
    // 先显示历史匹配
    var history = getHistory();
    var hMatches = history.filter(function(item) { return item !== val && item.indexOf(val) !== -1; }).slice(0, 3);
    var html = '';
    if (hMatches.length > 0) {
        for (var i = 0; i < hMatches.length; i++) {
            html += '<div class="s-item px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm text-gray-500 flex items-center gap-3" onmousedown="selectSuggestion(\'' + hMatches[i].replace(/'/g, "\\'") + '\')" data-q="' + hMatches[i].replace(/"/g, '&quot;') + '">';
            html += '<svg class="w-3.5 h-3.5 text-gray-300 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
            html += '<span>' + hMatches[i] + '</span></div>';
        }
        if (html) html += '<div class="border-t border-gray-100 my-1"></div>';
    }
    // API 建议
    var loadingHtml = html + '<div class="px-4 py-3 text-center text-xs text-gray-300">\u641c\u7d22\u4e2d...</div>';
    el.innerHTML = loadingHtml;
    container.classList.remove('hidden');

    suggestXhr = new XMLHttpRequest();
    suggestXhr.open('GET', '/api/meilisearch/suggest?q=' + encodeURIComponent(val), true);
    suggestXhr.onload = function() {
        try {
            var j = JSON.parse(suggestXhr.responseText);
            if (j.success && j.data.suggestions.length > 0) {
                var apiHtml = html;
                for (var si = 0; si < j.data.suggestions.length; si++) {
                    var s = j.data.suggestions[si];
                    var displayTitle = s.title || SEARCH_I18N.untitled;
                    // 构建直达链接（slug 只用于 products/blog_posts）
                    var directLink = '#';
                    if (s.type === 'products') directLink = '/products/' + (s.slug || s.id);
                    else if (s.type === 'kb_articles') directLink = '/help/' + s.id;
                    else if (s.type === 'blog_posts') directLink = '/blog/' + (s.slug || s.id);
                    else if (s.type === 'oa_articles') directLink = '/build/oa-article/' + s.id;
                    else if (s.type === 'forum_posts') directLink = '/build/plaza/' + s.id;
                    else if (s.type === 'marketplace_apps') directLink = '/docs';
                    else if (s.type === 'official_accounts') directLink = '/build/channels?account=' + s.id;
                    apiHtml += '<a href="' + directLink + '" class="s-item px-4 py-2 hover:bg-gray-50 cursor-pointer text-sm flex items-center gap-3 no-underline" onmousedown="event.preventDefault();selectSuggestion(\'' + displayTitle.replace(/'/g, "\\'") + '\');window.location=this.getAttribute(\'href\')">';
                    if (s.avatar) {
                        apiHtml += '<img src="' + s.avatar + '" class="w-6 h-6 rounded-full object-cover flex-shrink-0" onerror="this.style.display=\'none\'" />';
                    } else {
                        apiHtml += '<span class="w-6 h-6 rounded-full bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center text-white text-xs font-bold flex-shrink-0">' + (displayTitle.charAt(0) || '?') + '</span>';
                    }
                    apiHtml += '<div class="flex-1 min-w-0"><div class="text-gray-700 truncate">' + displayTitle + '</div>';
                    apiHtml += '<div class="text-xs text-gray-400 truncate">' + s.type_label + (s.description ? ' · ' + s.description : '') + '</div></div>';
                    apiHtml += '<span class="text-xs text-gray-300 flex-shrink-0">' + (icons[s.type] || '') + '</span>';
                    apiHtml += '</a>';
                }
                // 底部"查看全部结果"链接
                apiHtml += '<a href="/search?q=' + encodeURIComponent(val) + '" class="s-item block px-4 py-2.5 text-center text-xs text-slate-600 hover:bg-gray-50 font-medium border-t border-gray-100 no-underline" onmousedown="event.preventDefault();hideSuggestions();document.getElementById(\'search-input\').value=\'' + val.replace(/'/g, "\\'") + '\';doSearch(event)">' + SEARCH_I18N.viewAllN.replace(':count', j.data.suggestions.length) + '</a>';
                el.innerHTML = apiHtml;
            } else if (!html) {
                container.classList.add('hidden');
            }
        } catch(e) {}
    };
    suggestXhr.onerror = function() {
        if (!html) container.classList.add('hidden');
    };
    suggestXhr.send();
}
function hideSuggestions() { document.getElementById('search-suggestions').classList.add('hidden'); suggestIndex = -1; }
function selectSuggestion(q) {
    document.getElementById('search-input').value = q;
    hideSuggestions();
    if (!smartJump(q)) execSearch(q);
}

// ─── 搜索主逻辑 ───
var rankedData = []; // 缓存搜索结果，供筛选使用
var activeTypeFilter = 'all';
var currentSort = 'relevance';
var currentPage = 1;
var pageSize = 15;

var lastQuery = ''; // 上次搜索关键词
var labels = {
    products: @json(__('app.search_page.type_products')),
    kb_articles: @json(__('app.search_page.type_help')),
    marketplace_apps: @json(__('app.search_page.type_apps')),
    forum_posts: @json(__('app.search_page.type_forum')),
    blog_posts: @json(__('app.search_page.type_blog')),
    oa_articles: @json(__('app.search_page.type_oa')),
    users: @json(__('app.search_page.type_users')),
    official_accounts: @json(__('app.search_page.type_accounts'))
};
var SEARCH_I18N = {
    untitled: @json(__('app.search_page.untitled')),
    searching: @json(__('app.search_page.searching')),
    unavailable: @json(__('app.search_page.unavailable')),
    noResults: @json(__('app.search_page.no_results')),
    tryOther: @json(__('app.search_page.try_other')),
    related: @json(__('app.search_page.related')),
    trending: @json(__('app.search_page.trending')),
    prev: @json(__('app.search_page.prev')),
    next: @json(__('app.search_page.next')),
    viewAll: @json(__('app.search_page.view_all')),
    viewAllN: @json(__('app.search_page.view_all_n')),
    follow: @json(__('app.search_page.follow')),
    following: @json(__('app.search_page.following')),
    requestFail: @json(__('app.search_page.request_fail')),
    all: @json(__('app.search_page.all')),
    foundAbout: @json(__('app.search_page.found_about')),
    sort: @json(__('app.search_page.sort')),
    sortRelevance: @json(__('app.search_page.sort_relevance')),
    sortNewest: @json(__('app.search_page.sort_newest')),
    sortSmart: @json(__('app.search_page.sort_smart')),
    sortAi: @json(__('app.search_page.sort_ai')),
    sortCf: @json(__('app.search_page.sort_cf')),
    sortSequence: @json(__('app.search_page.sort_sequence')),
    filterEmpty: @json(__('app.search_page.filter_empty')),
    soldN: @json(__('app.search_page.sold_n')),
    noExcerpt: @json(__('app.search_page.no_excerpt')),
    tryTrending: @json(__('app.search_page.try_trending')),
    typeProducts: @json(__('app.search_page.type_products')),
    typeHelp: @json(__('app.search_page.type_help')),
    typeCommunity: @json(__('app.search_page.type_community')),
    typeOa: @json(__('app.search_page.type_oa')),
    free: @json(__('app.search_page.free')),
    justNow: @json(__('app.search_page.just_now')),
    minsAgo: @json(__('app.search_page.mins_ago')),
    hoursAgo: @json(__('app.search_page.hours_ago')),
    daysAgo: @json(__('app.search_page.days_ago')),
    verifiedEnt: @json(__('app.search_page.verified_ent')),
    verifiedPerson: @json(__('app.search_page.verified_person')),
    hotInstall: @json(__('app.search_page.hot_install')),
    hotPricing: @json(__('app.search_page.hot_pricing')),
    hotActivate: @json(__('app.search_page.hot_activate')),
    hotRenew: @json(__('app.search_page.hot_renew')),
    hotTrial: @json(__('app.search_page.hot_trial')),
    typePlaceholder: @json(__('app.search_page.type_placeholder')),
};
var icons = {products:'📦',kb_articles:'📖',marketplace_apps:'🧩',forum_posts:'💬',blog_posts:'📝',oa_articles:'📢',users:'👤',official_accounts:'🏢'};

function getQ() { return document.getElementById('search-input').value.trim(); }

// ─── 分页 ───
function goPage(page) {
    var filtered = activeTypeFilter === 'all' ? rankedData : rankedData.filter(function(h) { return h._content_type === activeTypeFilter; });
    var totalPages = Math.ceil(filtered.length / pageSize) || 1;
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderResults();
    renderPagination();
    // 滚动到结果区域顶部
    var rd = document.getElementById('search-results');
    if (rd) rd.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function renderPagination() {
    var lm = document.getElementById('load-more-wrap');
    if (!lm) return;
    var totalShown = activeTypeFilter === 'all' ? rankedData.length : rankedData.filter(function(h) { return h._content_type === activeTypeFilter; }).length;
    var totalPages = Math.ceil(totalShown / pageSize) || 1;
    if (totalPages <= 1) { lm.classList.add('hidden'); return; }
    lm.classList.remove('hidden');
    var html = '<div class="flex items-center justify-center gap-2 mt-4">';
    html += '<button onclick="goPage(' + (currentPage - 1) + ')" class="px-3 py-1.5 rounded-lg text-sm border border-gray-200 ' + (currentPage <= 1 ? 'bg-gray-50 text-gray-300 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 cursor-pointer') + '" ' + (currentPage <= 1 ? 'disabled' : '') + '>' + SEARCH_I18N.prev + '</button>';
    // Calculate visible page numbers
    var start = Math.max(1, currentPage - 2);
    var end = Math.min(totalPages, currentPage + 2);
    if (start > 1) html += '<span class="text-gray-300 text-sm">...</span>';
    for (var p = start; p <= end; p++) {
        html += '<button onclick="goPage(' + p + ')" class="px-3 py-1.5 rounded-lg text-sm border ' + (p === currentPage ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50') + ' cursor-pointer">' + p + '</button>';
    }
    if (end < totalPages) html += '<span class="text-gray-300 text-sm">...</span>';
    html += '<button onclick="goPage(' + (currentPage + 1) + ')" class="px-3 py-1.5 rounded-lg text-sm border border-gray-200 ' + (currentPage >= totalPages ? 'bg-gray-50 text-gray-300 cursor-not-allowed' : 'bg-white text-gray-600 hover:bg-gray-50 cursor-pointer') + '" ' + (currentPage >= totalPages ? 'disabled' : '') + '>' + SEARCH_I18N.next + '</button>';
    html += '</div>';
    lm.innerHTML = html;
}
// ─── 相关搜索 ───
function renderRelatedSearches(q) {
    var container = document.getElementById('related-searches');
    if (!container) {
        container = document.createElement('div');
        container.id = 'related-searches';
        var lm = document.getElementById('load-more-wrap');
        if (lm && lm.parentNode) lm.parentNode.insertBefore(container, lm);
        else {
            var rl2 = document.getElementById('result-list');
            if (rl2 && rl2.parentNode) rl2.parentNode.appendChild(container);
        }
    }
    fetch('/api/meilisearch/suggest?q=' + encodeURIComponent(q) + '&per_index=3')
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (!d.success || !d.data.suggestions || d.data.suggestions.length < 3) { container.classList.add('hidden'); return; }
        var keywords = [];
        var seen = {};
        for (var si = 0; si < d.data.suggestions.length; si++) {
            var t = d.data.suggestions[si].title || '';
            if (t && !seen[t] && t !== q) { seen[t] = true; keywords.push(t); }
            if (keywords.length >= 10) break;
        }
        if (keywords.length < 3) { container.classList.add('hidden'); return; }
        var html = '<div class="mt-6"><h3 class="text-sm font-medium text-gray-700 mb-3">' + SEARCH_I18N.related + '</h3><div class="grid grid-cols-2 gap-2">';
        for (var ki = 0; ki < keywords.length; ki++) {
            html += '<a href="/search?q=' + encodeURIComponent(keywords[ki]) + '" class="px-3 py-2 rounded-lg bg-gray-50 hover:bg-gray-100 text-sm text-gray-600 hover:text-slate-900 no-underline transition truncate">' + keywords[ki] + '</a>';
        }
        html += '</div></div>';
        container.innerHTML = html;
        container.classList.remove('hidden');
    })
    .catch(function() { container.classList.add('hidden'); });
}
// ─── 更新互物号账号关注状态 ───
function updateFollowStatus() {
    var token = localStorage.getItem('auth_token');
    if (!token || !rankedData.some(function(h) { return h._content_type === 'official_accounts'; })) return;
    fetch('/api/official-accounts/my-followed-ids', {
        headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(fr) {
        if (fr.success) {
            var followedIds = fr.data || [];
            followedIds.forEach(function(fid) {
                var link = document.querySelector('#result-list a[href="/build/channels?account='+fid+'"]');
                if (link) {
                    var card = link.closest('.bg-white.rounded-xl');
                    if (card) {
                        var spans = card.querySelectorAll('.follow-btn-wrap span');
                        if (spans.length >= 2) { spans[0].style.display = 'inline-block'; spans[1].style.display = 'none'; }
                    }
                }
            });
        }
    }).catch(function(){});
}
// ─── 关注/取消关注互物号 ───
function followAccount(accountId, btnEl) {
    var token = localStorage.getItem('auth_token');
    if (!token) { window.location.href = '/build/login'; return; }
    var isFollowed = btnEl.textContent === SEARCH_I18N.following;
    var url = '/api/official-accounts/' + accountId + (isFollowed ? '/unfollow' : '/follow');
    btnEl.textContent = '...';
    btnEl.style.pointerEvents = 'none';
    fetch(url, {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json', 'Content-Type': 'application/json' }
    }).then(function(r) { return r.json(); }).then(function(res) {
        if (res.success) {
            var wrap = btnEl.parentElement;
            var spans = wrap.querySelectorAll('span');
            if (spans.length >= 2) {
                if (isFollowed) {
                    spans[0].style.display = 'none';
                    spans[1].style.display = 'inline-block';
                    spans[1].textContent = SEARCH_I18N.follow;
                    spans[1].className = 'inline-block px-3 py-1 rounded-full text-xs font-medium text-white bg-slate-900 hover:bg-slate-800 cursor-pointer';
                } else {
                    spans[0].style.display = 'inline-block';
                    spans[1].style.display = 'none';
                }
            }
        } else {
            btnEl.textContent = isFollowed ? SEARCH_I18N.following : SEARCH_I18N.follow;
            btnEl.style.pointerEvents = 'auto';
        }
    }).catch(function() {
        btnEl.textContent = isFollowed ? SEARCH_I18N.following : SEARCH_I18N.follow;
        btnEl.style.pointerEvents = 'auto';
    });
}
async function doSearch(e) { e.preventDefault(); hideSuggestions(); var q = getQ(); if (!q) return false; if (smartJump(q)) return false; return await execSearch(q); }

// ─── 搜索直达（智能跳转）───
function smartJump(q) {
    // OA-18 → 互物号文章
    var oaMatch = q.match(/^(OA|oa|Oa)[-:\s]?(\d+)$/);
    if (oaMatch) { window.location.href = '/build/oa-article/' + oaMatch[2]; return true; }

    // ID:3 或 ID-3 或 ID 3 → 尝试多种类型跳转（优先商品）
    var idMatch = q.match(/^(ID|id|Id)[-:\s]?(\d+)$/);
    if (idMatch) { window.location.href = '/products/' + idMatch[2]; return true; }

    // help:关键词 → 优先搜索帮助中心
    var helpMatch = q.match(/^(help|Help|帮助)[-:\s]?(.+)$/);
    if (helpMatch) {
        var helpQ = encodeURIComponent(helpMatch[2].trim());
        window.location.href = '/search?q=' + helpQ + '&type=kb_articles';
        return true;
    }

    // article:关键词 → 优先搜索互物号
    var artMatch = q.match(/^(article|Article|文章)[-:\s]?(.+)$/);
    if (artMatch) {
        var artQ = encodeURIComponent(artMatch[2].trim());
        window.location.href = '/search?q=' + artQ + '&type=oa_articles';
        return true;
    }

    // @名称 → 搜索互物号账号
    var atMatch = q.match(/^@(.+)$/);
    if (atMatch) {
        var atQ = encodeURIComponent(atMatch[1].trim());
        window.location.href = '/search?q=' + atQ + '&type=official_accounts';
        return true;
    }

    return false; // 不匹配，走正常搜索
}

function setSort(sort) {
    currentSort = sort;
    // 更新下拉选中状态
    var sel = document.getElementById('sort-select');
    if (sel) sel.value = sort;
    var q = getQ();
    if (q) execSearch(q);
}

async function execSearch(q) {
    currentPage = 1;
    lastQuery = q;
    addHistory(q);
    var rd = document.getElementById('search-results');
    var rl = document.getElementById('result-list');
    rd.classList.remove('hidden');
    rl.innerHTML = '<div class="text-center py-16"><svg class="animate-spin w-8 h-8 text-slate-600 mx-auto mb-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg><p class="text-gray-400">' + SEARCH_I18N.searching + '</p></div>';
    document.getElementById('result-stats').innerHTML = '';
    document.getElementById('type-filters').classList.add('hidden');
    rd.scrollIntoView({behavior:'smooth',block:'start'});
    try {
        var apiUrl = '/api/meilisearch/unified-search?q='+encodeURIComponent(q)+'&sort='+currentSort+'&limit=50';
        var res = await fetch(apiUrl);
        var j = await res.json();
        if (!j.success) { rl.innerHTML = '<div class="text-center py-16 text-gray-400"><svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><p>' + SEARCH_I18N.unavailable + '</p></div>'; return false; }
        rankedData = j.data.ranked || [];
        activeTypeFilter = 'all';

        if (rankedData.length === 0) {
            rl.innerHTML = '<div class="text-center py-16"><svg class="w-16 h-16 text-gray-200 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg><p class="text-gray-400 text-lg mb-1">' + SEARCH_I18N.noResults.replace(':q', q) + '</p><p class="text-gray-300 text-sm mb-6">' + SEARCH_I18N.tryOther + '</p></div><div id="trending-wrap"></div>';
            showNoResultTips(rl);
            // 异步加载热门推荐
            fetch('/api/meilisearch/trending').then(function(tr) { return tr.json(); }).then(function(tj) {
                if (tj.success && tj.data.length > 0) {
                    var tw = document.getElementById('trending-wrap');
                    var th = '<div class="max-w-4xl mx-auto mt-4"><div class="flex items-center gap-2 mb-4"><h3 class="text-base font-semibold text-gray-700">' + SEARCH_I18N.trending + '</h3></div><div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">';
                    for (var ti = 0; ti < tj.data.length; ti++) {
                        var t = tj.data[ti];
                        var tLink = '#';
                        if (t.type === 'products') tLink = '/products/' + (t.slug || t.id);
                        else if (t.type === 'kb_articles') tLink = '/help/' + t.id;
                        else if (t.type === 'blog_posts') tLink = '/blog/' + (t.slug || t.id);
                        else if (t.type === 'oa_articles') tLink = '/build/oa-article/' + t.id;
                        else if (t.type === 'forum_posts') tLink = '/build/plaza/' + t.id;
                        else if (t.type === 'marketplace_apps') tLink = '/docs';
                        else if (t.type === 'official_accounts') tLink = '/build/channels?account=' + t.id;
                        th += '<a href="' + tLink + '" class="flex items-center gap-3 p-3 bg-white rounded-xl border border-gray-100 hover:shadow-md hover:border-gray-200 transition-all no-underline group">';
                        if (t.image) {
                            th += '<div class="w-12 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-gray-50"><img src="' + t.image + '" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" /></div>';
                        } else {
                            th += '<div class="w-12 h-12 rounded-lg bg-gradient-to-br from-slate-100 to-slate-50 flex items-center justify-center text-lg flex-shrink-0">' + (t.icon || '📄') + '</div>';
                        }
                        th += '<div class="flex-1 min-w-0"><div class="text-sm font-medium text-gray-800 truncate group-hover:text-slate-900 transition">' + (t.title || SEARCH_I18N.untitled) + '</div>';
                        th += '<div class="text-xs text-gray-400 truncate mt-0.5">' + t.icon + ' ' + t.label + (t.description ? ' · ' + t.description.replace(/<[^>]+>/g,'').substring(0, 40) : '') + '</div></div></a>';
                    }
                    th += '</div></div>';
                    tw.innerHTML = th;
                }
            }).catch(function(){});
            return false;
        }
        renderTypeFilters(j.data);
        renderResults();
        // 分页
        var lm = document.getElementById('load-more-wrap');
        if (!lm) {
            var rl2 = document.getElementById('result-list');
            lm = document.createElement('div');
            lm.id = 'load-more-wrap';
            lm.className = 'text-center';
            rl2.parentNode.appendChild(lm);
        }
        renderPagination();
        // 相关搜索
        renderRelatedSearches(q);
        // 关注状态
        updateFollowStatus();
    } catch(e2) { rl.innerHTML = '<div class="text-center py-16 text-red-400">'+SEARCH_I18N.requestFail+'</div>'; }
    return false;
}

// ─── 分类筛选 ───
function renderTypeFilters(data) {
    var tf = document.getElementById('type-filters');
    var stats = {};
    for (var k in data.results) stats[k] = data.results[k].total || 0;
    var html = '<button class="px-3 py-1.5 rounded-full text-sm font-medium transition ' + (activeTypeFilter==='all'?'bg-slate-900 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200') + '" onclick="setTypeFilter(\'all\')">'+SEARCH_I18N.all+' ('+rankedData.length+')</button>';
    for (var t in stats) {
        var cnt = rankedData.filter(function(h) { return h._content_type === t; }).length;
        if (cnt === 0) continue;
        html += '<button class="px-3 py-1.5 rounded-full text-sm font-medium transition ' + (activeTypeFilter===t?'bg-slate-900 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200') + '" onclick="setTypeFilter(\''+t+'\')">'+ (labels[t]||t) + ' ('+cnt+')</button>';
    }
    tf.innerHTML = html;
    tf.classList.remove('hidden');
    // 统计 + 排序切换
    var isAuth = !!localStorage.getItem('auth_token');
    var sortLabels = { relevance:SEARCH_I18N.sortRelevance, newest:SEARCH_I18N.sortNewest, smart:'🤖 '+SEARCH_I18N.sortSmart, ai:'🧠 '+SEARCH_I18N.sortAi, cf:'🔗 '+SEARCH_I18N.sortCf, sequence:'📈 '+SEARCH_I18N.sortSequence };
    var statsHtml = '<div class="flex items-center justify-between flex-wrap gap-2 mb-3"><div class="text-sm text-gray-500">'+SEARCH_I18N.foundAbout.replace(':n','<strong>'+rankedData.length+'</strong>').replace(':cats',Object.keys(stats).length)+'</div>';
    statsHtml += '<div class="flex items-center gap-2">';
    statsHtml += '<label class="text-xs text-gray-400 hidden sm:inline">'+SEARCH_I18N.sort+'</label>';
    statsHtml += '<select id="sort-select" onchange="setSort(this.value)" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-slate-300 focus:border-slate-300 cursor-pointer">';
    var sortOptions = [['relevance',SEARCH_I18N.sortRelevance],['newest',SEARCH_I18N.sortNewest]];
    if (isAuth) sortOptions.push(['smart','🤖 '+SEARCH_I18N.sortSmart],['ai','🧠 '+SEARCH_I18N.sortAi],['cf','🔗 '+SEARCH_I18N.sortCf],['sequence','📈 '+SEARCH_I18N.sortSequence]);
    for (var si = 0; si < sortOptions.length; si++) {
        var val = sortOptions[si][0], label = sortOptions[si][1];
        statsHtml += '<option value="' + val + '" ' + (currentSort === val ? 'selected' : '') + '>' + label + '</option>';
    }
    statsHtml += '</select></div></div>';
    document.getElementById('result-stats').innerHTML = statsHtml;
}
function setTypeFilter(type) {
    activeTypeFilter = type;
    currentPage = 1;
    renderTypeFilters({results:{}}); // refresh buttons
    renderResults();
    renderPagination();
    // 切换分类后重新获取关注状态
    updateFollowStatus();
}

// ─── 渲染结果（Google/Bing 风格）───
function renderResults() {
    var rl = document.getElementById('result-list');
    var filtered = activeTypeFilter === 'all' ? rankedData : rankedData.filter(function(h) { return h._content_type === activeTypeFilter; });
    if (filtered.length === 0) { rl.innerHTML = '<div class="text-center py-12 text-gray-400">'+SEARCH_I18N.filterEmpty+'</div>'; return; }
    var start = (currentPage - 1) * pageSize;
    var pageItems = filtered.slice(start, start + pageSize);
    var html = '<div class="space-y-3">';
    // 安全截断 HTML 文本，防止 <em> 标签被截断导致 DOM 结构损坏
    function safeDesc(d, maxlen) { return (d||'').replace(/<[^>]+>/g,'').substring(0, maxlen); }
    var links = {products:function(h){return '/products/'+(h.slug||h.id);},kb_articles:function(h){return '/help/'+h.id;},marketplace_apps:function(h){return '/docs';},forum_posts:function(h){return '/build/plaza/'+h.id;},blog_posts:function(h){return '/blog/'+(h.slug||h.id);},oa_articles:function(h){return '/build/oa-article/'+h.id;},users:function(){return '#';},official_accounts:function(h){return '/build/channels?account='+h.id;}};
    for (var i = 0; i < pageItems.length; i++) {
        var h = pageItems[i], type = h._content_type || 'other', icon = icons[type] || '📄';
        var title = (h._formatted && (h._formatted.title || h._formatted.name)) || h.title || h.name || SEARCH_I18N.untitled;
        var desc = (h._formatted && h._formatted.content) || h.content || h.description || h.excerpt || h.short_description || '';
        var link = (links[type] || function(){return '#';})(h);
        // 显示友好 URL
        var displayUrl = link.replace(/^https?:\/\/[^\/]+/, '').replace(/\/{2,}/g, '/');

        // ── 商品搜索项（卡片风格）──
        if (type === 'products') {
            var imgSrc = h.image_url || '';
            var price = h.base_price != null ? Number(h.base_price) : null;
            var priceStr = price !== null ? (price > 0 ? '¥' + price.toFixed(2) : SEARCH_I18N.free) : '';
            var sales = h.sales_count || 0;
            var prodTags = h.tags || [];
            if (typeof prodTags === 'string') { try { prodTags = JSON.parse(prodTags); } catch(e) { prodTags = []; } }
            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            html += '<div class="flex gap-4">';
            if (imgSrc) {
                html += '<a href="'+link+'" class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-50"><img src="'+imgSrc+'" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" /></a>';
            }
            html += '<div class="flex-1 min-w-0">';
            html += '<div class="flex items-center gap-1.5 mb-1.5 flex-wrap">';
            html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 rounded text-xs text-gray-500">'+SEARCH_I18N.typeProducts+'</span>';
            if (h.category_name) html += '<span class="text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">'+h.category_name+'</span>';
            if (h.merchant_name) html += '<span class="text-xs text-gray-400">· '+h.merchant_name+'</span>';
            html += '</div>';
            html += '<a href="'+link+'" class="text-base font-semibold text-gray-900 hover:text-gray-900 no-underline leading-snug">'+title+'</a>';
            if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed mt-1 line-clamp-2">'+safeDesc(desc,120)+'</div>';
            html += '<div class="flex items-center gap-2 mt-2 flex-wrap">';
            if (priceStr) html += '<span class="text-lg font-bold ' + (price > 0 ? 'text-[#e8451c]' : 'text-green-600') + '">'+priceStr+'</span>';
            if (sales > 0) html += '<span class="text-xs text-gray-400">'+SEARCH_I18N.soldN.replace(':n',sales)+'</span>';
            for (var ti = 0; ti < prodTags.length && ti < 3; ti++) {
                html += '<span class="text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">#'+prodTags[ti]+'</span>';
            }
            html += '</div></div></div></div>';
            continue;
        }

        // ── 互物号账号（卡片风格，参考截图）──
        if (type === 'official_accounts') {
            var ava = h.avatar || '';
            var vi = h.verified_info;
            var fc = h.follower_count || 0;
            var ac = h.article_count || 0;
            var isAuth = !!localStorage.getItem('auth_token');
            // 修正头像路径
            if (ava && ava.indexOf('://') === -1 && ava.indexOf('/') === 0) { ava = window.location.origin + ava; }
            else if (ava && ava.indexOf('://') === -1) { ava = '/storage/' + ava.replace(/^\/?/, ''); }
            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            // flex 行：头像 | 信息区 | 关注按钮
            html += '<div class="flex items-start gap-3">';
            // 头像（靠左）
            html += '<a href="'+link+'" class="w-14 h-14 rounded-full bg-gradient-to-br from-slate-600 to-slate-800 flex items-center justify-center text-white font-bold text-xl overflow-hidden flex-shrink-0 relative">';
            if (ava) html += '<img src="'+ava+'" class="absolute inset-0 w-full h-full object-cover rounded-full" onload="this.style.display=\'block\';this.parentElement.querySelector(\'span\').style.display=\'none\'" onerror="this.style.display=\'none\';this.parentElement.querySelector(\'span\').style.display=\'\'" />';
            html += '<span class="relative">'+((h.name||'').charAt(0))+'</span></a>';
            // 中间信息区
            html += '<div class="flex-1 min-w-0">';
            html += '<div class="flex items-center gap-2 flex-wrap">';
            html += '<a href="'+link+'" class="inline-block text-base font-semibold text-gray-900 hover:text-gray-900 no-underline align-middle">'+title+'</a>';
            if (h.category_name) html += '<span class="inline-block px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded text-[10px] font-medium align-middle">'+h.category_name+'</span>';
            if (vi && vi.name) {
                var vType = vi.type === 'enterprise' ? SEARCH_I18N.verifiedEnt : SEARCH_I18N.verifiedPerson;
                html += '<span class="inline-block px-1.5 py-0.5 bg-green-50 text-green-700 rounded text-[10px] font-medium border border-green-200 whitespace-nowrap align-middle">'+vType+'·'+vi.name+'</span>';
            }
            html += '</div>';
            html += '<div class="text-sm text-gray-500 mt-0.5 truncate">'+(desc||SEARCH_I18N.noExcerpt)+'</div>';
            html += '<div class="flex items-center gap-4 mt-1.5 text-xs text-gray-400">';
            html += '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5" viewBox="0 0 1024 1024" fill="currentColor"><path d="M512 85.333333c129.6 0 234.666667 105.066667 234.666667 234.666667 0 84.256-44.394667 158.133333-111.072 199.52a425.28 425.28 0 0 1 152.853333 83.466667 32 32 0 1 1-41.493333 48.736A361.045333 361.045333 0 0 0 512 565.333333c-188.672 0-345.429333 144.672-361.344 331.413334a32 32 0 0 1-63.765333-5.429334c15.114667-177.322667 138.048-322.346667 301.546666-371.786666C321.76 478.165333 277.333333 404.266667 277.333333 320c0-129.6 105.066667-234.666667 234.666667-234.666667z m415.946667 627.381334l1.066666 1.013333a29.824 29.824 0 0 1 0 43.413333l-162.261333 152.96a31.925333 31.925333 0 0 1-22.762667 8.704 31.925333 31.925333 0 0 1-22.773333-8.704l-93.184-87.84a29.824 29.824 0 0 1 0-43.413333l1.077333-1.013333a32 32 0 0 1 43.904 0l70.976 66.901333 140.053334-132.021333a32 32 0 0 1 43.904 0zM512 149.333333c-94.261333 0-170.666667 76.405333-170.666667 170.666667s76.405333 170.666667 170.666667 170.666667 170.666667-76.405333 170.666667-170.666667-76.405333-170.666667-170.666667-170.666667z"/></svg> <strong class="text-gray-600">'+fc+'</strong></span>';
            html += '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5" viewBox="0 0 1024 1024" fill="currentColor"><path d="M754.32 71 269.678 71c-63.045 0-114.034 50.987-114.034 114.033l0 655.695c0 62.954 51.076 114.033 114.034 114.033l285.086 0 57.015 0 28.508 0 0-1.098c93.293-10.69 216.463-121.984 226.971-226.973l1.098 0L868.356 185.033C868.355 121.987 817.278 71 754.32 71L754.32 71zM626.033 897.743l-14.254 0L611.779 783.71l0-28.511c0-15.712 12.791-28.508 28.508-28.508l28.511 0 141.079 0C796.355 797.326 690.266 897.743 626.033 897.743L626.033 897.743zM811.335 669.677 597.524 669.677l0 2.011c-20.011 5.205-35.541 20.739-40.753 40.754l-2.007 0 0 14.25 0 171.052L269.678 897.744c-31.527 0-57.015-25.493-57.015-57.015L212.663 185.033c0-31.526 25.488-57.015 57.015-57.015L754.32 128.018c31.525 0 57.015 25.488 57.015 57.015L811.335 669.677 811.335 669.677zM447.856 527.133 333.822 527.133c-11.786 0-21.379 9.599-21.379 21.38 0 11.786 9.593 21.385 21.379 21.385l114.034 0c11.788 0 21.38-9.599 21.38-21.385C469.236 536.637 459.644 527.133 447.856 527.133L447.856 527.133zM533.38 527.133l-14.254 0c-11.784 0-21.38 9.599-21.38 21.38 0 11.786 9.596 21.385 21.38 21.385l14.254 0c11.786 0 21.385-9.599 21.385-21.385C554.765 536.637 545.166 527.133 533.38 527.133L533.38 527.133zM683.052 384.59 340.947 384.59c-15.713 0-28.504 12.796-28.504 28.509 0 15.717 12.791 28.508 28.504 28.508l342.105 0c15.714 0 28.506-12.791 28.506-28.508C711.558 397.293 698.766 384.59 683.052 384.59L683.052 384.59zM683.052 242.052 340.947 242.052c-15.713 0-28.504 12.792-28.504 28.505 0 15.717 12.791 28.509 28.504 28.509l342.105 0c15.714 0 28.506-12.792 28.506-28.509C711.558 254.844 698.766 242.052 683.052 242.052L683.052 242.052zM683.052 242.052"/></svg> <strong class="text-gray-600">'+ac+'</strong></span>';
            html += '</div></div>';
            // 关注按钮（靠右）
            html += '<div class="flex-shrink-0 follow-btn-wrap">';
            if (isAuth) {
                html += '<span class="inline-block px-3 py-1 rounded-full text-xs font-medium border border-gray-200 bg-gray-100 text-gray-500 cursor-default" style="display:none">' + SEARCH_I18N.following + '</span>';
                html += '<span class="inline-block px-3 py-1 rounded-full text-xs font-medium text-white bg-slate-900 hover:bg-slate-800 cursor-pointer" onclick="followAccount(\''+h.id+'\',this)">' + SEARCH_I18N.follow + '</span>';
            } else {
                html += '<a href="/build/login" class="inline-block px-3 py-1 rounded-full text-xs font-medium text-white bg-slate-900 hover:bg-slate-800 no-underline">' + SEARCH_I18N.follow + '</a>';
            }
            html += '</div></div></div>';
            continue;
        }

        // ── 社区帖子（社交动态风格）──
        if (type === 'forum_posts') {
            var pu = h.user_name || '';
            var pa = h.user_avatar || '';
            // 修正头像路径
            if (pa && pa.indexOf('://') === -1 && pa.indexOf('/') === 0) { pa = window.location.origin + pa; }
            else if (pa && pa.indexOf('://') === -1) { pa = '/storage/' + pa.replace(/^\/?/, ''); }
            // 相对时间
            var timeStr = '';
            if (h.created_at) {
                var now = Date.now();
                var created = new Date(h.created_at.replace(' ', 'T')).getTime();
                var diff = Math.floor((now - created) / 1000);
                if (diff < 60) timeStr = SEARCH_I18N.justNow;
                else if (diff < 3600) timeStr = SEARCH_I18N.minsAgo.replace(':n', Math.floor(diff/60));
                else if (diff < 86400) timeStr = SEARCH_I18N.hoursAgo.replace(':n', Math.floor(diff/3600));
                else if (diff < 2592000) timeStr = SEARCH_I18N.daysAgo.replace(':n', Math.floor(diff/86400));
                else timeStr = h.created_at.substring(0,10);
            }
            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            // 头部：头像 + 用户名（内联同行）
            html += '<div class="mb-2">';
            if (pa) {
                html += '<img src="'+pa+'" class="inline-block w-8 h-8 rounded-full object-cover border border-gray-100 align-middle" onerror="this.style.display=\'none\'" />';
            } else {
                html += '<span class="inline-block w-8 h-8 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 text-center leading-8 text-white text-sm font-bold align-middle">'+(pu.charAt(0)||'?')+'</span>';
            }
            html += '<span class="inline-block text-sm font-medium text-gray-900 align-middle ml-2">'+pu+'</span>';
            html += '</div>';
            // 内容
            var contentText = (h.content || '').replace(/<[^>]+>/g,'');
            if (contentText.length > 200) contentText = contentText.substring(0, 200) + '...';
            html += '<a href="'+link+'" class="no-underline">';
            html += '<div class="text-sm leading-relaxed mb-2 whitespace-pre-wrap">'+(h.title || contentText)+'</div>';
            if (h.title && contentText) html += '<div class="text-sm text-gray-500 leading-relaxed mb-2">'+safeDesc(contentText,100)+'</div>';
            html += '</a>';
            // 底部：分类 · 评论 · 浏览 · 时间
            html += '<div class="flex items-center gap-2 pt-2 border-t border-gray-50 text-xs text-gray-400">';
            html += '<span>'+SEARCH_I18N.typeCommunity+'</span>';
            html += '<span class="text-gray-300">·</span><span>💬 '+(h.likes_count||0)+'</span>';
            html += '<span class="text-gray-300">·</span><span>👁️ '+(h.views_count||0)+'</span>';
            html += '<span class="text-gray-300">·</span><span>'+timeStr+'</span>';
            html += '</div></div>';
            continue;
        }

        // ── 互物号文章（新闻流风格 + 多图支持）──
        if (type === 'oa_articles') {
            var an = h.account_name || h.author_name || '';
            var aa = h.account_avatar || h.author_avatar || '';
            var ci = h.cover_image || '';
            // 修正头像路径
            if (aa && aa.indexOf('://') === -1 && aa.indexOf('/') === 0) { aa = window.location.origin + aa; }
            else if (aa && aa.indexOf('://') === -1) { aa = '/storage/' + aa.replace(/^\/?/, ''); }
            // 修正封面图路径
            if (ci && ci.indexOf('://') === -1 && ci.indexOf('/') === 0) { ci = window.location.origin + ci; }
            else if (ci && ci.indexOf('://') === -1) { ci = '/storage/' + ci.replace(/^\/?/, ''); }
            // 相对时间
            var timeStr = '';
            var dateStr = h.published_at || h.created_at || '';
            if (dateStr) {
                var now = Date.now();
                var created = new Date(dateStr.replace(' ', 'T')).getTime();
                var diff = Math.floor((now - created) / 1000);
                if (diff < 60) timeStr = SEARCH_I18N.justNow;
                else if (diff < 3600) timeStr = SEARCH_I18N.minsAgo.replace(':n', Math.floor(diff/60));
                else if (diff < 86400) timeStr = SEARCH_I18N.hoursAgo.replace(':n', Math.floor(diff/3600));
                else if (diff < 2592000) timeStr = SEARCH_I18N.daysAgo.replace(':n', Math.floor(diff/86400));
                else timeStr = dateStr.substring(0,10);
            }
            // 从内容中提取图片
            var contentRaw = h.content || '';
            var allImgs = [];
            if (ci) allImgs.push(ci);
            // 提取 <img src="...">
            var imgTagRe = /<img[^>]+src=["']([^"']+)["']/gi;
            var m;
            while ((m = imgTagRe.exec(contentRaw)) !== null) { allImgs.push(m[1]); }
            // 提取 ![alt](url)
            var mdRe = /!\[.*?\]\(([^\s)]+)\)/g;
            while ((m = mdRe.exec(contentRaw)) !== null) { allImgs.push(m[1]); }
            // 提取纯图片URL
            var urlRe = /https?:\/\/[^\s<>"']+\.(?:png|jpg|jpeg|gif|webp|svg|bmp)/gi;
            while ((m = urlRe.exec(contentRaw)) !== null) { allImgs.push(m[0]); }
            // 去重取前4张
            var uniqueImgs = [];
            for (var ii = 0; ii < allImgs.length; ii++) {
                if (uniqueImgs.indexOf(allImgs[ii]) === -1) uniqueImgs.push(allImgs[ii]);
                if (uniqueImgs.length >= 4) break;
            }

            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            // 头部：头像 + 账号名（内联同行）
            html += '<div class="mb-2">';
            if (aa) {
                html += '<img src="'+aa+'" class="inline-block w-6 h-6 rounded-full object-cover border border-gray-100 align-middle" onerror="this.style.display=\'none\'" />';
            }
            html += '<span class="inline-block text-sm font-medium text-gray-700 align-middle ml-1.5">'+an+'</span>';
            if (h.author_name && h.author_name !== an) {
                html += '<span class="inline-block text-xs text-gray-400 align-middle ml-1">· '+h.author_name+'</span>';
            }
            html += '</div>';
            // 单图：左标题 + 描述 + 右缩略图
            if (uniqueImgs.length === 1) {
                html += '<a href="'+link+'" class="flex gap-4 no-underline">';
                html += '<div class="flex-1 min-w-0">';
                html += '<div class="text-base font-semibold text-gray-900 leading-snug mb-1">'+title+'</div>';
                if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed line-clamp-2">'+safeDesc(desc,100)+'</div>';
                html += '</div>';
                html += '<div class="w-28 h-20 flex-shrink-0 rounded-lg overflow-hidden bg-gray-50"><img src="'+uniqueImgs[0]+'" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" /></div>';
                html += '</a>';
            } else {
                // 无图或多图：标题 + 描述
                html += '<a href="'+link+'" class="no-underline">';
                html += '<div class="text-base font-semibold text-gray-900 leading-snug mb-1">'+title+'</div>';
                if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed line-clamp-2">'+safeDesc(desc,150)+'</div>';
                html += '</a>';
                // 多图网格
                if (uniqueImgs.length === 2) {
                    html += '<div class="grid grid-cols-2 gap-2 mt-2">';
                    for (var ii = 0; ii < uniqueImgs.length; ii++) {
                        html += '<img src="'+uniqueImgs[ii]+'" class="w-full h-28 rounded-lg object-cover bg-gray-50" onerror="this.style.display=\'none\'" />';
                    }
                    html += '</div>';
                } else if (uniqueImgs.length === 3) {
                    html += '<div class="grid grid-cols-2 gap-2 mt-2">';
                    html += '<img src="'+uniqueImgs[0]+'" class="w-full h-32 rounded-lg object-cover bg-gray-50 row-span-2" onerror="this.style.display=\'none\'" />';
                    html += '<div class="flex flex-col gap-2">';
                    html += '<img src="'+uniqueImgs[1]+'" class="w-full h-[60px] rounded-lg object-cover bg-gray-50" onerror="this.style.display=\'none\'" />';
                    html += '<img src="'+uniqueImgs[2]+'" class="w-full h-[60px] rounded-lg object-cover bg-gray-50" onerror="this.style.display=\'none\'" />';
                    html += '</div></div>';
                } else if (uniqueImgs.length >= 4) {
                    html += '<div class="grid grid-cols-2 gap-1.5 mt-2">';
                    for (var ii = 0; ii < 4; ii++) {
                        html += '<img src="'+uniqueImgs[ii]+'" class="w-full h-24 rounded-lg object-cover bg-gray-50" onerror="this.style.display=\'none\'" />';
                    }
                    html += '</div>';
                }
            }
            // 底部：分类 · 评论 · 时间
            html += '<div class="flex items-center gap-2 mt-2 pt-2 border-t border-gray-50 text-xs text-gray-400">';
            html += '<span>'+SEARCH_I18N.typeOa+'</span>';
            html += '<span class="text-gray-300">·</span><span>💬 0</span>';
            html += '<span class="text-gray-300">·</span><span>'+timeStr+'</span>';
            // 标签
            var oaTags = h.tags || [];
            if (typeof oaTags === 'string') { try { oaTags = JSON.parse(oaTags); } catch(e) { oaTags = []; } }
            for (var oti = 0; oti < oaTags.length && oti < 2; oti++) {
                html += '<span class="text-gray-300">·</span><span class="text-gray-400">#'+oaTags[oti]+'</span>';
            }
            html += '</div></div>';
            continue;
        }

        // ── 用户卡片 ──
        if (type === 'users') {
            var un = h.name || '';
            var ua = h.avatar || '';
            // 修正头像路径
            if (ua && ua.indexOf('://') === -1 && ua.indexOf('/') === 0) { ua = window.location.origin + ua; }
            else if (ua && ua.indexOf('://') === -1) { ua = '/storage/' + ua.replace(/^\/?/, ''); }
            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            html += '<div class="flex items-center gap-3 mb-1">';
            if (ua) {
                html += '<img src="'+ua+'" class="w-10 h-10 rounded-full object-cover border border-gray-100" onerror="this.style.display=\'none\'" />';
            } else {
                html += '<span class="w-10 h-10 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white text-sm font-bold">'+(un.charAt(0)||'?')+'</span>';
            }
            html += '<a href="'+link+'" class="text-base font-semibold text-gray-900 hover:text-gray-900 no-underline">'+un+'</a>';
            html += '</div>';
            if (h.email) html += '<div class="text-sm text-gray-500">'+h.email+'</div>';
            html += '</div>';
            continue;
        }

        // ── 帮助中心卡片 ──
        if (type === 'kb_articles') {
            html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
            // 头部：分类标签
            html += '<div class="flex items-center gap-1.5 text-xs text-gray-400 mb-1.5">';
            html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 rounded text-gray-500">'+SEARCH_I18N.typeHelp+'</span>';
            if (h.category_name) html += '<span class="text-gray-300">·</span><span class="text-gray-500">'+h.category_name+'</span>';
            html += '</div>';
            // 标题
            html += '<a href="'+link+'" class="text-base font-semibold text-gray-900 hover:text-gray-900 no-underline leading-snug">'+title+'</a>';
            // 描述
            if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed mt-1 line-clamp-2">'+safeDesc(desc,150)+'</div>';
            // 底部：浏览 · 有帮助
            html += '<div class="flex items-center gap-3 mt-2 pt-2 border-t border-gray-50 text-xs text-gray-400">';
            html += '<span>👁️ '+(h.view_count||0)+'</span>';
            if (h.helpful_count != null) html += '<span class="text-gray-300">·</span><span>👍 '+(h.helpful_count||0)+'</span>';
            // 标签
            var kbTags = h.tags || [];
            if (typeof kbTags === 'string') { try { kbTags = JSON.parse(kbTags); } catch(e) { kbTags = []; } }
            for (var kti = 0; kti < kbTags.length && kti < 2; kti++) {
                html += '<span class="text-gray-300">·</span><span class="text-gray-400">#'+kbTags[kti]+'</span>';
            }
            html += '</div></div>';
            continue;
        }

        // ── 通用搜索项（卡片风格，各栏目特色数据）──
        var avatar = '', authorName = '', imageUrl = '', extraMeta = '';
        if (type === 'blog_posts') {
            authorName = h.author; avatar = h.author_avatar; imageUrl = h.featured_image || '';
            var bt = h.published_at || '';
            if (bt) {
                var now = Date.now();
                var created = new Date(bt.replace(' ', 'T')).getTime();
                var diff = Math.floor((now - created) / 1000);
                if (diff < 60) bt = SEARCH_I18N.justNow;
                else if (diff < 3600) bt = SEARCH_I18N.minsAgo.replace(':n', Math.floor(diff/60));
                else if (diff < 86400) bt = SEARCH_I18N.hoursAgo.replace(':n', Math.floor(diff/3600));
                else if (diff < 2592000) bt = SEARCH_I18N.daysAgo.replace(':n', Math.floor(diff/86400));
                else bt = bt.substring(0,10);
            }
            var blogTags = h.tags || [];
            if (typeof blogTags === 'string') { try { blogTags = JSON.parse(blogTags); } catch(e) { blogTags = []; } }
            extraMeta = '<span class="text-gray-400">'+bt+'</span>';
            for (var ti = 0; ti < blogTags.length && ti < 3; ti++) {
                extraMeta += '<span class="text-xs text-gray-400 bg-gray-50 px-1.5 py-0.5 rounded">#'+blogTags[ti]+'</span>';
            }
        } else if (type === 'marketplace_apps') {
            authorName = h.developer_name; avatar = h.developer_avatar;
            extraMeta = '<span class="flex items-center gap-1"><svg class="w-3.5 h-3.5" viewBox="0 0 16 16" fill="currentColor"><path d="M4 4a4 4 0 118 0 4 4 0 01-8 0zm-3 7a5 5 0 0110 0H1zm13-2a1 1 0 110 2 1 1 0 010-2z"/></svg> <strong class="text-gray-600">'+(h.install_count||0)+'</strong></span>';
            extraMeta += '<span class="text-yellow-500">★ '+(h.avg_rating||'0.0')+'</span>';
        }
        // 处理头像路径
        if (avatar && avatar.indexOf('://') === -1 && avatar.indexOf('/') === 0) { avatar = window.location.origin + avatar; }
        else if (avatar && avatar.indexOf('://') === -1) { avatar = '/storage/' + avatar.replace(/^\/?/, ''); }

        var hasImage = imageUrl && (type === 'blog_posts' || type === 'oa_articles');
        html += '<div class="bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow p-4">';
        // 头部：类型标签 + 作者头像+名称
        html += '<div class="flex items-center gap-1.5 text-xs text-gray-400 mb-1.5">';
        html += '<span class="inline-flex items-center gap-1 px-2 py-0.5 bg-gray-50 rounded text-gray-500">'+(h._content_label||'')+'</span>';
        if (authorName) {
            html += '<span class="flex items-center gap-1 ml-1">';
            if (avatar) {
                html += '<img src="'+avatar+'" class="w-5 h-5 rounded-full object-cover border border-gray-100" onerror="this.style.display=\'none\'" />';
            } else {
                html += '<span class="w-5 h-5 rounded-full bg-gradient-to-br from-slate-500 to-slate-700 flex items-center justify-center text-white text-[10px] font-bold">'+(authorName.charAt(0)||'?')+'</span>';
            }
            html += '<span class="text-gray-600 font-medium truncate max-w-[120px]">'+authorName+'</span></span>';
        }
        if (type === 'users' && h.email) html += '<span class="text-gray-300">·</span><span class="text-gray-400 truncate">'+h.email+'</span>';
        html += '</div>';
        // 带封面的布局（blog_posts/oa_articles）
        if (hasImage) {
            html += '<div class="flex gap-4">';
            html += '<div class="flex-1 min-w-0">';
            html += '<a href="'+link+'" class="text-base font-semibold text-gray-900 hover:text-gray-900 no-underline leading-snug">'+title+'</a>';
            if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed mt-1 line-clamp-2">'+safeDesc(desc,150)+'</div>';
            html += '</div>';
            html += '<a href="'+link+'" class="w-24 h-24 flex-shrink-0 rounded-lg overflow-hidden bg-gray-50"><img src="'+imageUrl+'" class="w-full h-full object-cover" onerror="this.style.display=\'none\'" /></a>';
            html += '</div>';
        } else {
            html += '<a href="'+link+'" class="text-base font-semibold text-gray-900 hover:text-gray-900 no-underline leading-snug">'+title+'</a>';
            if (desc) html += '<div class="text-sm text-gray-500 leading-relaxed mt-1 line-clamp-2">'+safeDesc(desc,150)+'</div>';
        }
        // 底部元信息
        if (extraMeta) html += '<div class="flex items-center gap-3 mt-2 text-xs">' + extraMeta + '</div>';
        html += '</div>';
    }
    html += '</div>';
    rl.innerHTML = html;
}

// ─── 无结果推荐 ───
var HOT_TAGS = ['License', 'SDK', 'API', SEARCH_I18N.hotInstall, SEARCH_I18N.hotPricing, SEARCH_I18N.hotActivate, SEARCH_I18N.hotRenew, SEARCH_I18N.hotTrial];
function showNoResultTips(container) {
    var html = '<div class="mt-8 text-center"><p class="text-sm text-gray-400 mb-3">'+SEARCH_I18N.tryTrending+'</p><div class="flex items-center justify-center gap-2 flex-wrap">';
    for (var i = 0; i < HOT_TAGS.length; i++) {
        html += '<a href="/search?q='+HOT_TAGS[i]+'" class="px-3 py-1.5 bg-gray-100 hover:bg-slate-100 hover:text-slate-900 rounded-full text-sm text-gray-500 transition">'+HOT_TAGS[i]+'</a>';
    }
    html += '</div></div>';
    container.insertAdjacentHTML('beforeend', html);
}

// ─── 首页初始化 ───
document.addEventListener('DOMContentLoaded',function(){
    renderHistory();
    setupSuggestions();
    var p=new URLSearchParams(window.location.search),q=p.get('q'),type=p.get('type'),sort=p.get('sort'),scope=p.get('scope');
    if (sort === 'newest') currentSort = 'newest';
    if(q && smartJump(q)) { /* 智能跳转已处理 */ }
    else if(q){document.getElementById('search-input').value=q;execSearch(q).then(function(){if(type&&labels[type]){setTypeFilter(type);}});}else if(type&&labels[type]){document.getElementById('search-input').placeholder=(SEARCH_I18N.typePlaceholder||'').replace(':type', labels[type]||type);}
});
</script>

@include('public.partials.footer')
</body>
</html>
