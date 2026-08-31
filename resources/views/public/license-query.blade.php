<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('app.license_query_page.meta_desc') }}">
    <meta name="robots" content="index, follow">
    <meta property="og:title" content="{{ __('app.license_query_page.title') }} | {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.license_query_page.subtitle') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/license/query') }}">
    <link rel="canonical" href="{{ url('/license/query') }}">
    @include('public.partials.tracking')
    <script>window.LQ_I18N = @json(__('app.license_query_page'));</script>
    <title>{{ __('app.license_query_page.title') }} | {{ site_setting('site_name', __('app.app_name')) }}</title>
    @vite('resources/css/public.css')
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col font-sans antialiased text-slate-800">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.license_query_page.title'),
        'heroSubtitle' => __('app.license_query_page.subtitle'),
    ])

    <main class="flex-1 pb-16 -mt-4">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- 搜索卡片 -->
            <div class="max-w-4xl mx-auto">
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-10 mb-4 shadow-sm">
                    <div class="flex flex-col sm:flex-row gap-4">
                        <div class="flex-1 relative">
                            <label for="licenseKey" class="sr-only">License Key</label>
                            <input
                                id="licenseKey"
                                type="text"
                                class="w-full px-6 py-4 border border-slate-200 rounded-xl text-base focus:ring-2 focus:ring-slate-400 focus:border-slate-400 outline-none transition placeholder-slate-400 bg-slate-50/50"
                                placeholder="{{ __('app.license_query_page.placeholder') }}"
                                autocomplete="off"
                                oninput="clearFieldError(this)"
                                />
                            <div id="inputError" class="hidden absolute -bottom-6 left-0 text-xs text-red-500"></div>
                        </div>
                        <button
                            id="searchBtn"
                            class="inline-flex items-center justify-center gap-2 px-10 py-4 bg-slate-900 hover:bg-slate-800 text-white font-medium rounded-xl transition-all duration-300 shadow-md hover:shadow-lg active:scale-95 whitespace-nowrap text-base disabled:opacity-50 disabled:cursor-not-allowed disabled:active:scale-100"
                            onclick="doSearch()">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                            </svg>
                            <span>{{ __('app.license_query_page.search') }}</span>
                        </button>
                    </div>

                    <!-- 快捷示例 -->
                    <div class="flex flex-wrap items-center gap-2 mt-5 pt-4 border-t border-slate-100">
                        <span class="text-xs text-slate-400">{{ __('app.license_query_page.try_example') }}</span>
                        <button onclick="fillExample('HWT-DEMO-A1B2-C3D4')" class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition font-mono">
                            HWT-DEMO-A1B2-C3D4
                        </button>
                        <button onclick="fillExample('HWT-ENTERPRISE-E5F6-G7H8')" class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-lg transition font-mono">
                            HWT-ENTERPRISE-E5F6-G7H8
                        </button>
                    </div>
                </div>
                <p class="text-sm text-slate-400 text-center">{{ __('app.license_query_page.hint') }}</p>
            </div>

            <!-- 加载中 -->
            <div id="loading" class="hidden text-center py-12">
                <div class="inline-block w-8 h-8 border-4 border-slate-200 border-t-slate-800 rounded-full animate-spin"></div>
                <p class="text-slate-500 mt-3">{{ __('app.license_query_page.loading') }}</p>
            </div>

            <!-- 错误提示 -->
            <div id="error" class="hidden bg-red-50 border border-red-100 rounded-2xl p-6 mb-6 animate-fade-in">
                <div class="flex items-start gap-4">
                    <div class="flex-shrink-0 w-10 h-10 bg-red-100 rounded-full flex items-center justify-center">
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-red-800">{{ __('app.license_query_page.query_fail') }}</h3>
                        <p id="errorMessage" class="text-red-600 text-sm mt-1"></p>
                    </div>
                    <button onclick="doSearch()" class="shrink-0 px-4 py-2 text-sm font-medium text-red-700 bg-red-100 hover:bg-red-200 rounded-lg transition">
                        {{ __('app.license_query_page.retry') }}
                    </button>
                </div>
            </div>

            <!-- 查询结果 -->
            <div id="result" class="hidden animate-fade-in">
                <!-- 状态卡片 -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
                    <div class="p-6 md:p-8">
                        <!-- 头部：Key + 状态 + 分享 -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                            <div class="min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900">{{ __('app.license_query_page.auth_info') }}</h2>
                                <p id="resultKey" class="text-sm text-gray-400 font-mono mt-1 truncate"></p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <button id="shareBtn" onclick="shareResult()" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-500 bg-gray-100 hover:bg-gray-200 rounded-lg transition" title="{{ __('app.license_query_page.share_title') }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z" />
                                    </svg>
                                    {{ __('app.license_query_page.share') }}
                                </button>
                                <!-- Toast 提示 -->
                                <div id="shareToast" class="hidden fixed top-4 right-4 z-50 bg-gray-900 text-white text-sm px-4 py-2.5 rounded-lg shadow-lg animate-fade-in"></div>
                                <div id="statusBadge" class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap"></div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.product_name') }}</label>
                                    <p id="resultProduct" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.license_type') }}</label>
                                    <p id="resultType" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.created_at') }}</label>
                                    <p id="resultCreated" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.expires_at') }}</label>
                                    <p id="resultExpires" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.activation') }}</label>
                                    <p id="resultActivated" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-400 uppercase tracking-wider">{{ __('app.license_query_page.devices') }}</label>
                                    <p id="resultDevices" class="text-gray-900 font-medium mt-0.5"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ⭐ 激活引导 (根据状态显示不同内容) -->
                <div id="activationGuide" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6 hidden animate-slide-up">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9 9-4.03 9-9-4.03-9-9-9z" />
                        </svg>
                        {{ __('app.license_query_page.next_steps') }}
                    </h3>
                    <div id="guideActive" class="hidden">
                        <p class="text-gray-600 text-sm leading-relaxed">{{ __('app.license_query_page.active_hint') }}</p>
                        <ul class="mt-3 space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="text-slate-500 mt-0.5 shrink-0">📖</span>
                                <span>{!! str_replace(':help', '<a href="/help" class="text-slate-800 hover:text-slate-950 underline">' . e(__('app.license_query_page.help_center')) . '</a>', e(__('app.license_query_page.see_help'))) !!}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-slate-500 mt-0.5 shrink-0">💬</span>
                                <span>{!! str_replace(':contact', '<a href="/contact" class="text-slate-800 hover:text-slate-950 underline">' . e(__('app.license_query_page.online_support')) . '</a>', e(__('app.license_query_page.contact_support'))) !!}</span>
                            </li>
                        </ul>
                    </div>
                    <div id="guidePending" class="hidden">
                        <p class="text-gray-600 text-sm leading-relaxed">{{ __('app.license_query_page.pending_hint') }}</p>
                        <ul class="mt-3 space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="text-slate-500 mt-0.5 shrink-0">1️⃣</span>
                                <span>{{ __('app.license_query_page.step_download') }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-slate-500 mt-0.5 shrink-0">2️⃣</span>
                                <span>{{ __('app.license_query_page.step_enter_key') }}<code class="px-2 py-0.5 bg-slate-100 rounded text-xs font-mono" id="guideKeyDisplay"></code></span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="text-slate-500 mt-0.5 shrink-0">3️⃣</span>
                                <span>{{ __('app.license_query_page.step_done') }}</span>
                            </li>
                        </ul>
                        <div class="mt-4 flex items-center gap-3">
                            <button onclick="copyKey()" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-lg transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" />
                                </svg>
                                {{ __('app.license_query_page.copy_key') }}
                            </button>
                            <a href="/docs/quickstart" class="text-sm font-medium text-slate-800 hover:text-slate-950 underline">{{ __('app.license_query_page.quickstart') }}</a>
                        </div>
                    </div>
                    <div id="guideExpired" class="hidden">
                        <div class="flex items-start gap-3 p-4 bg-orange-50 rounded-xl border border-orange-100">
                            <span class="text-xl shrink-0">⏰</span>
                            <div>
                                <p class="text-sm font-medium text-orange-800">{{ __('app.license_query_page.expired_title') }}</p>
                                <p class="text-sm text-orange-700 mt-1">{{ __('app.license_query_page.expired_desc') }}</p>
                                <div class="mt-3 flex items-center gap-3">
                                    <a href="/pricing" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-slate-900 hover:bg-slate-800 rounded-lg transition shadow-sm">
                                        {{ __('app.license_query_page.see_pricing') }}
                                    </a>
                                    <a href="/contact" class="text-sm font-medium text-orange-700 hover:text-orange-800 underline">{{ __('app.license_query_page.contact_us') }}</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="guideSuspended" class="hidden">
                        <div class="flex items-start gap-3 p-4 bg-red-50 rounded-xl border border-red-100">
                            <span class="text-xl shrink-0">⛔</span>
                            <div>
                                <p class="text-sm font-medium text-red-800">{{ __('app.license_query_page.suspended_title') }}</p>
                                <p class="text-sm text-red-700 mt-1">{{ __('app.license_query_page.suspended_desc') }}</p>
                                <a href="/contact" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-red-700 hover:text-red-800 underline">
                                    {{ __('app.license_query_page.contact_cs') }}
                                </a>
                            </div>
                        </div>
                    </div>
                    <div id="guideRevoked" class="hidden">
                        <div class="flex items-start gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
                            <span class="text-xl shrink-0">🚫</span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ __('app.license_query_page.revoked_title') }}</p>
                                <p class="text-sm text-gray-600 mt-1">{{ __('app.license_query_page.revoked_desc') }}</p>
                                <a href="/contact" class="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 hover:text-gray-800 underline">
                                    {{ __('app.license_query_page.contact_cs') }}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 产品描述 -->
                <div id="resultDescription" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hidden">
                    <h3 class="text-sm font-semibold text-gray-900 uppercase tracking-wider mb-2">{{ __('app.license_query_page.product_desc') }}</h3>
                    <p id="resultDescText" class="text-gray-600 text-sm leading-relaxed"></p>
                </div>
            </div>

            <!-- 未找到 -->
            <div id="notfound" class="hidden bg-amber-50 border border-amber-100 rounded-2xl p-6 md:p-8 text-center animate-fade-in">
                <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-amber-800 mb-1">{{ __('app.license_query_page.not_found_title') }}</h3>
                <p class="text-amber-600 text-sm max-w-md mx-auto">{{ __('app.license_query_page.not_found_desc') }}</p>
                <div class="mt-5 flex items-center justify-center gap-3">
                    <button onclick="document.getElementById('licenseKey').select()" class="text-sm font-medium text-amber-700 hover:text-amber-800 underline">
                        {{ __('app.license_query_page.edit_input') }}
                    </button>
                    <span class="text-amber-300">|</span>
                    <a href="/contact" class="text-sm font-medium text-amber-700 hover:text-amber-800 underline">{{ __('app.license_query_page.contact_us') }}</a>
                    <span class="text-amber-300">|</span>
                    <a href="/help" class="text-sm font-medium text-amber-700 hover:text-amber-800 underline">{{ __('app.license_query_page.help_center') }} →</a>
                </div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')

<script>
/* ====== License 查询 ====== */

var currentResultKey = '';

function doSearch() {
    var input = document.getElementById('licenseKey');
    var key = input ? input.value.trim() : '';
    if (!key) {
        showFieldError(input, (window.LQ_I18N&&LQ_I18N.enter_key)||'');
        return;
    }
    clearFieldError(input);

    document.getElementById('loading').classList.remove('hidden');
    document.getElementById('error').classList.add('hidden');
    document.getElementById('result').classList.add('hidden');
    document.getElementById('notfound').classList.add('hidden');
    document.getElementById('searchBtn').disabled = true;

    fetch('/api/license/public-lookup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify({ license_key: key })
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('searchBtn').disabled = false;

        if (!res.success || !res.found) {
            document.getElementById('notfound').classList.remove('hidden');
            return;
        }

        var d = res.data;
        currentResultKey = d.license_key;

        // 填充结果
        document.getElementById('resultKey').textContent = d.license_key;
        document.getElementById('resultProduct').textContent = d.product_name;
        document.getElementById('resultType').textContent = d.license_type_label;
        document.getElementById('resultCreated').textContent = d.created_at || '-';
        document.getElementById('resultExpires').textContent = d.expires_at || ((window.LQ_I18N&&LQ_I18N.lifetime)||'');
        document.getElementById('resultActivated').textContent = d.activated ? ('✅ '+((window.LQ_I18N&&LQ_I18N.activated)||'')) : ('⏳ '+((window.LQ_I18N&&LQ_I18N.not_activated)||''));
        document.getElementById('resultDevices').textContent = ((window.LQ_I18N&&LQ_I18N.devices_n)||'').replace(':used',d.activated_devices||0).replace(':max',d.max_devices||0);

        // 状态徽章
        var badge = document.getElementById('statusBadge');
        var statusMap = {
            'active': { label: '✅ '+((window.LQ_I18N&&LQ_I18N.status_active)||''), class: 'bg-green-50 text-green-700' },
            'expired': { label: '⏰ '+((window.LQ_I18N&&LQ_I18N.status_expired)||''), class: 'bg-red-50 text-red-700' },
            'suspended': { label: '⛔ '+((window.LQ_I18N&&LQ_I18N.status_suspended)||''), class: 'bg-orange-50 text-orange-700' },
            'revoked': { label: '🚫 '+((window.LQ_I18N&&LQ_I18N.status_revoked)||''), class: 'bg-gray-100 text-gray-600' },
            'pending': { label: '⏳ '+((window.LQ_I18N&&LQ_I18N.status_pending)||''), class: 'bg-slate-100 text-slate-700' },
        };
        var s = d.is_expired ? 'expired' : (statusMap[d.status] ? d.status : 'active');
        var info = statusMap[s] || { label: d.status_label || d.status, class: 'bg-gray-50 text-gray-700' };
        badge.textContent = info.label;
        badge.className = 'px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap ' + info.class;

        // 产品描述
        if (d.product_description) {
            document.getElementById('resultDescText').textContent = d.product_description;
            document.getElementById('resultDescription').classList.remove('hidden');
        } else {
            document.getElementById('resultDescription').classList.add('hidden');
        }

        // ⭐ 激活引导
        showActivationGuide(d);

        document.getElementById('result').classList.remove('hidden');
    })
    .catch(function() {
        document.getElementById('loading').classList.add('hidden');
        document.getElementById('searchBtn').disabled = false;
        document.getElementById('errorMessage').textContent = (window.LQ_I18N&&LQ_I18N.network_fail)||'';
        document.getElementById('error').classList.remove('hidden');
    });
}

/* ====== 激活引导 ====== */

function showActivationGuide(d) {
    hideAllGuides();
    var container = document.getElementById('activationGuide');
    container.classList.remove('hidden');

    // 待激活 — 显示激活步骤
    if (d.status === 'pending' && !d.activated) {
        document.getElementById('guideKeyDisplay').textContent = d.license_key;
        document.getElementById('guidePending').classList.remove('hidden');
        return;
    }

    // 已过期
    if (d.is_expired || d.status === 'expired') {
        document.getElementById('guideExpired').classList.remove('hidden');
        return;
    }

    // 已暂停
    if (d.status === 'suspended') {
        document.getElementById('guideSuspended').classList.remove('hidden');
        return;
    }

    // 已吊销
    if (d.status === 'revoked') {
        document.getElementById('guideRevoked').classList.remove('hidden');
        return;
    }

    // 默认：有效状态 — 显示使用建议
    document.getElementById('guideActive').classList.remove('hidden');
}

function hideAllGuides() {
    var ids = ['guideActive', 'guidePending', 'guideExpired', 'guideSuspended', 'guideRevoked'];
    for (var i = 0; i < ids.length; i++) {
        document.getElementById(ids[i]).classList.add('hidden');
    }
}

/* ====== 分享功能 ====== */

function shareResult() {
    var url = window.location.href;
    var title = ((window.LQ_I18N&&LQ_I18N.share_result_title)||'').replace(':product', document.getElementById('resultProduct').textContent);
    var text = ((window.LQ_I18N&&LQ_I18N.share_result_text)||'').replace(':status', document.getElementById('statusBadge').textContent).replace(':expires', document.getElementById('resultExpires').textContent);

    // 使用 Web Share API（移动端优先）
    if (navigator.share) {
        navigator.share({ title: title, text: text, url: url })
            .catch(function() { /* 用户取消分享，静默处理 */ });
        return;
    }

    // 降级：复制链接
    var temp = document.createElement('input');
    temp.value = url;
    document.body.appendChild(temp);
    temp.select();
    try {
        document.execCommand('copy');
        showToast((window.LQ_I18N&&LQ_I18N.copy_ok)||'');
    } catch(e) {
        showToast((window.LQ_I18N&&LQ_I18N.copy_fail)||'');
    }
    document.body.removeChild(temp);
}

/* ====== 复制 License Key ====== */

function copyKey() {
    var temp = document.createElement('input');
    temp.value = currentResultKey;
    document.body.appendChild(temp);
    temp.select();
    try {
        document.execCommand('copy');
        showToast((window.LQ_I18N&&LQ_I18N.key_copied)||'');
    } catch(e) {
        showToast((window.LQ_I18N&&LQ_I18N.key_copy_fail)||'');
    }
    document.body.removeChild(temp);
}

/* ====== Toast 提示 ====== */

function showToast(msg) {
    var toast = document.getElementById('shareToast');
    toast.textContent = msg;
    toast.classList.remove('hidden');
    setTimeout(function() {
        toast.classList.add('hidden');
    }, 2500);
}

/* ====== 表单辅助 ====== */

function fillExample(key) {
    var input = document.getElementById('licenseKey');
    input.value = key;
    clearFieldError(input);
    doSearch();
}

function showFieldError(input, msg) {
    input.classList.add('border-red-300', 'bg-red-50/50');
    var err = document.getElementById('inputError');
    err.textContent = msg;
    err.classList.remove('hidden');
}

function clearFieldError(input) {
    input.classList.remove('border-red-300', 'bg-red-50/50');
    document.getElementById('inputError').classList.add('hidden');
}

/* ====== Enter 键触发查询 ====== */

document.addEventListener('DOMContentLoaded', function() {
    var input = document.getElementById('licenseKey');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                doSearch();
            }
        });
    }
});
</script>
</body>
</html>
