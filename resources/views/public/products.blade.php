<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.seo.products_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}</title>
    <meta name="description" content="{{ __('app.products_page.subtitle') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        .product-card { transition: all 0.25s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.22); }
        .category-pill.active { background: var(--pg-primary); color: #fff; border-color: var(--pg-primary); }
        .category-pill-hero { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.75); border-color: rgba(255,255,255,0.2); }
        .category-pill-hero:hover { background: rgba(255,255,255,0.16); }
        .category-pill-hero.active { background: #fff !important; color: var(--pg-primary) !important; border-color: #fff !important; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.4; } }
        .animate-pulse > div { animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite; }
        .line-clamp-1 { display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .aspect-square { aspect-ratio: 1 / 1 !important; }
        #im-chat-btn { transition: all 0.3s ease; box-shadow: 0 4px 16px rgba(var(--pg-primary-rgb), 0.25); }
        #im-chat-btn:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(var(--pg-primary-rgb), 0.3); }
        #im-chat-dialog { animation: chatSlideUp .3s ease; }
        @keyframes chatSlideUp { from { transform: translateY(20px) scale(0.95); opacity: 0; } to { transform: translateY(0) scale(1); opacity: 1; } }
        .chat-msg-bubble { max-width: 80%; word-break: break-word; }
        #im-chat-dialog:not(.hidden) { display: flex !important; }
        .chat-seller-btn { transition: all 0.2s ease; }
        .chat-seller-btn:hover { color: var(--pg-primary); background: #f1f5f9; }
        #view-grid.active, #view-list.active { background: var(--pg-primary); color: #fff; }
        #view-grid:not(.active), #view-list:not(.active) { color: #94a3b8; }
        #view-grid:not(.active):hover, #view-list:not(.active):hover { color: #334155; background: #f1f5f9; }
        /* 列表视图 */
        #products-grid.list-view { grid-template-columns: 1fr !important; }
        #products-grid.list-view .product-card { flex-direction: row !important; }
        #products-grid.list-view .product-card > a { flex-direction: row !important; }
        #products-grid.list-view .aspect-square { width: 200px !important; min-height: 200px; flex-shrink: 0; }
        @media (max-width: 639px) {
            #products-grid.list-view .product-card,
            #products-grid.list-view .product-card > a { flex-direction: column !important; }
            #products-grid.list-view .aspect-square { width: 100% !important; min-height: auto; }
        }
        #products-grid.list-view .p-5 { flex: 1; }
        #products-grid.list-view .wishlist-btn, #products-grid.list-view .compare-rp-btn { display: none; }
        /* ─── 高级筛选面板 ─── */
        .filter-section { transition: all 0.3s ease; }
        .filter-section.collapsed { max-height: 0; overflow: hidden; padding-top: 0; padding-bottom: 0; opacity: 0; }
        .filter-tag-btn { transition: all 0.2s ease; }
        /* ─── 佣金徽章 ─── */
        .commission-badge {
            display: none;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 999px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: #fff;
            box-shadow: 0 1px 3px rgba(251,191,36,0.3);
            white-space: nowrap;
        }
        .filter-tag-btn.active { background: var(--pg-primary); color: #fff; border-color: var(--pg-primary); }
        input[type="range"] { -webkit-appearance: none; appearance: none; height: 6px; background: #e2e8f0; border-radius: 3px; outline: none; }
        input[type="range"]::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 18px; height: 18px; border-radius: 50%; background: var(--pg-primary); cursor: pointer; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        input[type="range"]::-moz-range-thumb { width: 18px; height: 18px; border-radius: 50%; background: var(--pg-primary); cursor: pointer; border: 2px solid #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.2); }
        .filter-active-count { background: var(--pg-primary); color: #fff; font-size: 10px; min-width: 18px; height: 18px; border-radius: 9px; display: inline-flex; align-items: center; justify-content: center; padding: 0 5px; }
        @media (max-width: 639px) {
            .product-card .p-5 { padding: 0.75rem !important; min-height: 180px !important; }
            .product-card h3 { font-size: 0.95rem; }
            #products-grid { gap: 0.75rem; }
            .section.py-8 { padding-top: 1rem; padding-bottom: 1rem; }
            .hero-search-section .text-3xl { font-size: 1.5rem; }
            #products-grid .text-lg { font-size: 0.9rem; }
            #products-grid .text-sm { font-size: 0.8rem; }
            #products-grid .p-5 { min-height: 180px; }
            #advanced-filter-panel .grid { grid-template-columns: 1fr !important; }
        }
        /* 标签云 */
        .product-tag-btn.active { background: var(--pg-primary) !important; color: #fff !important; border-color: var(--pg-primary) !important; }
        /* 搜索输入框占位符颜色 */
        #search-input::placeholder { color: rgba(255,255,255,0.4); }
        #search-input::-webkit-input-placeholder { color: rgba(255,255,255,0.4); }
        #search-input:-moz-placeholder { color: rgba(255,255,255,0.4); }
        .products-hero-bg {
            background: linear-gradient(
                145deg,
                color-mix(in srgb, var(--pg-primary) 88%, #020617) 0%,
                var(--pg-primary) 48%,
                color-mix(in srgb, var(--pg-primary) 65%, #334155) 100%
            );
        }
        .products-trust-strip { border-bottom: 1px solid #e2e8f0; background: #fff; }
        .products-trust-item {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 0.875rem;
            padding: 0.85rem 1rem;
        }
        .products-trust-value {
            font-family: "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--pg-primary);
            letter-spacing: -0.02em;
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.miniprogram-env')
    @include('public.partials.nav')

    <!-- ─── Hero ─── -->
    <section class="pt-24 pb-8 relative overflow-hidden">
        <div class="absolute inset-0 products-hero-bg" aria-hidden="true"></div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <nav class="flex items-center gap-1.5 text-sm mb-6 text-slate-400">
                <a href="{{ url('/') }}" class="hover:text-white transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('app.products_page.breadcrumb_home') }}
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-white font-medium">{{ __('app.products_page.title') }}</span>
            </nav>
            <div class="text-center">
                <h1 class="text-3xl md:text-5xl font-extrabold mb-4 text-white tracking-tight">{{ __('app.products_page.title') }}</h1>
                <p class="text-lg max-w-2xl mx-auto mb-8 text-slate-300">{{ __('app.products_page.subtitle') }}</p>
                <div class="max-w-2xl w-full mx-auto flex flex-col sm:flex-row sm:items-center gap-2 bg-white/10 backdrop-blur-sm rounded-2xl p-2 sm:p-1.5 sm:pl-5 border border-white/15 shadow-xl">
                    <div class="flex items-center gap-2 flex-1 min-w-0">
                        <svg class="w-5 h-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input id="search-input" type="text" placeholder="{{ __('app.products_page.search_placeholder') }}" class="flex-1 border-0 text-sm outline-none w-full text-white bg-transparent" style="min-width:0" value="{{ $highlight ?? '' }}" />
                    </div>
                    <button type="button" onclick="searchProducts()" class="w-full sm:w-auto shrink-0 px-4 sm:px-6 py-2.5 bg-white text-slate-900 rounded-xl text-sm font-bold hover:bg-slate-100 transition shadow-sm" style="min-height:44px">{{ __('app.products_page.search') }}</button>
                </div>
                <div class="flex items-center justify-center gap-2 mt-3 text-xs text-slate-400">
                    <span>{{ __('app.products_page.hot') }}</span>
                    <a href="javascript:void(0)" onclick="quickSearch('SDK')" class="hover:text-white transition">SDK</a>
                    <a href="javascript:void(0)" onclick="quickSearch('License')" class="hover:text-white transition">License</a>
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-center gap-3 mt-8 pt-6 border-t border-white/10">
                <span class="text-xs text-slate-400">{{ __('app.products_page.category') }}</span>
                <button type="button" onclick="filterCategory('')" class="category-pill category-pill-hero px-4 py-1.5 rounded-full text-sm border transition active">{{ __('app.products_page.all') }}</button>
                @foreach($categories as $cat)
                    <button type="button" onclick="filterCategory('{{ $cat->slug }}')" class="category-pill category-pill-hero px-4 py-1.5 rounded-full text-sm border transition" data-slug="{{ $cat->slug }}">{{ $cat->name }}</button>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ─── 能力信号（可核验，非虚荣数字）─── -->
    <section class="products-trust-strip py-6" aria-label="{{ __('app.landing.trust_kicker') }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="products-trust-item">
                    <div class="products-trust-value">{{ __('app.landing.trust_sig_crypto') }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ __('app.landing.trust_sig_crypto_desc') }}</div>
                </div>
                <div class="products-trust-item">
                    <div class="products-trust-value">{{ __('app.landing.trust_sig_offline') }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ __('app.landing.trust_sig_offline_desc') }}</div>
                </div>
                <div class="products-trust-item">
                    <div class="products-trust-value">{{ __('app.landing.trust_sig_sdk') }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ __('app.landing.trust_sig_sdk_desc') }}</div>
                </div>
                <div class="products-trust-item">
                    <div class="products-trust-value">{{ __('app.landing.trust_sig_deploy') }}</div>
                    <div class="text-xs text-slate-500 mt-1">{{ __('app.landing.trust_sig_deploy_desc') }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 排序 + 筛选栏 ─── -->
    <section class="py-2 sm:py-4 bg-white border-b border-gray-100 sticky top-16 z-40">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-1.5 sm:gap-3">
                <div class="flex items-center gap-2 text-xs text-slate-400">
                    {{ __('app.products_page.total_prefix') }}
                    <span id="product-total-count">{{ $products->total() }}</span>
                    {{ __('app.products_page.total_suffix') }}
                </div>
                <div class="flex items-center gap-1 sm:gap-2 flex-wrap">
                    <select id="sort-select" onchange="changeSort(this.value)" class="text-xs sm:text-sm border border-slate-200 rounded-lg px-1.5 sm:px-3 py-1.5 sm:py-2 focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white" style="min-height:36px;max-width:130px">
                        <option value="latest">{{ __('app.products_page.sort_latest') }}</option>
                        <option value="recommended">{{ __('app.products_page.sort_recommended') }}</option>
                        <option value="price_asc">{{ __('app.products_page.sort_price_asc') }}</option>
                        <option value="price_desc">{{ __('app.products_page.sort_price_desc') }}</option>
                        <option value="sold">{{ __('app.products_page.sort_sold') }}</option>
                        <option value="name">{{ __('app.products_page.sort_name') }}</option>
                    </select>
                    <button type="button" id="toggle-advanced-filter" onclick="toggleAdvancedFilter()" class="px-2 sm:px-3 py-1.5 sm:py-2 text-xs sm:text-sm border border-slate-200 rounded-lg hover:bg-slate-50 hover:border-slate-300 transition flex items-center gap-1 text-slate-500" style="min-height:36px" title="{{ __('app.products_page.filter') }}">
                        <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                        <span class="hidden sm:inline">{{ __('app.products_page.filter') }}</span>
                        <span id="filter-active-badge" class="hidden filter-active-count ml-1">0</span>
                    </button>
                    <!-- 视图切换 -->
                    <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                        <button type="button" id="view-grid" onclick="setViewMode('grid')" class="px-1.5 sm:px-3 py-1.5 transition active" title="{{ __('app.products_page.view_grid') }}" style="min-height:36px;display:inline-flex;align-items:center">
                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zm10 0a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        </button>
                        <div class="w-px h-4 bg-gray-200"></div>
                        <button type="button" id="view-list" onclick="setViewMode('list')" class="px-1.5 sm:px-3 py-1.5 transition" title="{{ __('app.products_page.view_list') }}" style="min-height:36px;display:inline-flex;align-items:center">
                            <svg class="w-3.5 h-3.5 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                        </button>
                    </div>
            </div>

            <!-- ─── 高级筛选面板 ─── -->
            <div id="advanced-filter-panel" class="mt-4 pt-4 border-t border-gray-100 hidden filter-section">
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <!-- 价格区间 -->
                    <div class="space-y-2">
                        <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            {{ __('app.products_page.price_range') }}
                        </span>
                        <div class="flex items-center gap-2">
                            <input id="filter-price-min" type="number" min="0" step="10" placeholder="{{ __('app.products_page.price_min') }}" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400" oninput="onFilterChange()">
                            <span class="text-gray-300 shrink-0">—</span>
                            <input id="filter-price-max" type="number" min="0" step="10" placeholder="{{ __('app.products_page.price_max') }}" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400" oninput="onFilterChange()">
                        </div>
                        <div class="relative pt-1">
                            <div class="flex justify-between text-[10px] text-gray-400">
                                <span>¥0</span>
                                <span>¥500+</span>
                            </div>
                        </div>
                    </div>

                    <!-- 角标筛选 -->
                    <div class="space-y-2">
                        <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                            {{ __('app.products_page.badges_filter') }}
                        </span>
                        <div class="flex flex-wrap gap-1.5">
                            <button type="button" onclick="toggleTag('is_new')" class="filter-tag-btn px-2.5 py-1 text-xs border border-slate-200 text-slate-700 bg-slate-50 rounded-full hover:bg-slate-100" data-tag="is_new">{{ __('app.products_page.badge_new') }}</button>
                            <button type="button" onclick="toggleTag('is_hot')" class="filter-tag-btn px-2.5 py-1 text-xs border border-slate-200 text-slate-700 bg-slate-50 rounded-full hover:bg-slate-100" data-tag="is_hot">{{ __('app.products_page.badge_hot') }}</button>
                            <button type="button" onclick="toggleTag('has_discount')" class="filter-tag-btn px-2.5 py-1 text-xs border border-slate-200 text-slate-700 bg-slate-50 rounded-full hover:bg-slate-100" data-tag="has_discount">{{ __('app.products_page.badge_discount') }}</button>
                            <button type="button" onclick="toggleTag('demo_enabled')" class="filter-tag-btn px-2.5 py-1 text-xs border border-slate-200 text-slate-700 bg-slate-50 rounded-full hover:bg-slate-100" data-tag="demo_enabled">{{ __('app.products_page.badge_demo') }}</button>
                        </div>
                    </div>

                    <!-- 卖家筛选 -->
                    <div class="space-y-2">
                        <span class="text-xs font-medium text-gray-500 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ __('app.products_page.seller') }}
                        </span>
                        <select id="filter-creator" onchange="onFilterChange()" class="w-full px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-slate-400 bg-white">
                            <option value="">{{ __('app.products_page.all_sellers') }}</option>
                            @foreach($sellers as $seller)
                                <option value="{{ $seller->id }}">{{ $seller->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <!-- 操作按钮 -->
                    <div class="space-y-2 flex flex-col justify-end">
                        <div class="flex gap-2">
                            <button onclick="clearAllFilters()" class="flex-1 px-3 py-1.5 text-xs text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">{{ __('app.products_page.reset_filter') }}</button>
                            <button onclick="applyFilters()" class="flex-1 px-3 py-1.5 text-xs bg-slate-900 text-white rounded-lg hover:bg-slate-800 transition">{{ __('app.products_page.apply_filter') }}</button>
                        </div>
                        <div class="text-[10px] text-gray-400 text-center">{{ __('app.products_page.enter_hint') }}</div>
                    </div>
                </div>
                <!-- 标签云（产品内容标签） -->
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <span class="text-xs font-medium text-gray-500 flex items-center gap-1 mb-3">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                        {{ __('app.products_page.tag_cloud') }}
                    </span>
                    <div class="flex flex-wrap gap-2">
                        @forelse($allTags as $tag)
                            <button onclick="toggleProductTag('{{ $tag }}')" class="product-tag-btn px-3 py-1.5 text-xs border border-gray-200 text-gray-500 bg-white rounded-lg hover:border-slate-300 hover:text-slate-900 hover:bg-slate-50 transition shadow-sm" data-product-tag="{{ $tag }}">{{ $tag }}</button>
                        @empty
                            <span class="text-xs text-gray-300">{{ __('app.products_page.no_tags') }}</span>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div id="products-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                @forelse($products as $product)
                    <div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col group relative">
                        <a href="{{ url('/products/' . $product->slug) }}" class="block flex flex-col flex-1">
                        <!-- Product Image -->
                        <div class="aspect-square bg-gradient-to-br from-gray-100 to-gray-50 overflow-hidden rounded-t-xl relative">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" width="400" height="400" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">
                            @else
                                <div class="text-center p-6">
                                    <svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span class="text-sm text-gray-400">{{ $product->name }}</span>
                                </div>
                            @endif
                            <!-- 角标 -->
                            <div class="absolute top-2 left-2 flex flex-col gap-1">
                                @if($product->is_new ?? false)
                                    <span class="px-2 py-0.5 bg-slate-900 text-white text-xs font-bold rounded-full">{{ __('app.products_page.badge_new') }}</span>
                                @endif
                                @if($product->is_hot ?? false)
                                    <span class="px-2 py-0.5 bg-slate-700 text-white text-xs font-bold rounded-full">{{ __('app.products_page.badge_hot') }}</span>
                                @endif
                                @if($product->has_discount ?? false)
                                    <span class="px-2 py-0.5 bg-amber-600 text-white text-xs font-bold rounded-full">{{ __('app.products_page.badge_discount') }}</span>
                                @endif
                                @if($product->demo_enabled ?? false)
                                    <span class="px-2 py-0.5 bg-slate-900 text-white text-xs font-bold rounded-full">{{ __('app.products_page.badge_demo') }}</span>
                                @endif
                                @if(($product->max_commission_rate ?? 0) > 0)
                                    <span class="commission-badge" style="display:none;">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ __('app.products_page.commission', ['rate' => $product->max_commission_rate]) }}
                                    </span>
                                @endif
                            </div>
                            <!-- 对比按钮 -->
                            <button onclick="event.preventDefault();event.stopPropagation();toggleCompare({{ $product->id }},'{{ str_replace("'","\\'",$product->name) }}','{{ $product->image_url ?: '' }}','{{ url('/products/'.$product->slug) }}','¥{{ number_format($product->lowest_price ?: 0, 2) }}')" class="absolute top-10 right-2 w-7 h-7 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 compare-rp-btn" data-pid="{{ $product->id }}" title="{{ __('app.products_page.add_compare') }}">
                                <svg class="w-3.5 h-3.5 text-gray-400 compare-rp-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            </button>
                        </div>
                        <!-- Product Info -->
                        <div class="p-5 flex flex-col flex-1" style="min-height:220px">
                            <div class="flex items-start justify-between mb-2">
                                <h3 class="text-lg font-semibold text-gray-900 group-hover:text-slate-900 transition line-clamp-1">{!! $highlight ? str_ireplace($highlight, '<mark class="bg-yellow-200 px-0.5 rounded">'.$highlight.'</mark>', e($product->name)) : e($product->name) !!}</h3>
                                @if($product->version)
                                    <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0 ml-2">v{{ $product->version }}</span>
                                @endif
                            </div>

                            <!-- 评分 -->
                            @php $rs = $product->review_stats; @endphp
                            @if($rs['total'] > 0)
                                <div class="flex items-center gap-1 mb-1">
                                    <span class="text-yellow-400 text-sm">{{ str_repeat('★', min(5, max(0, round($rs['avg_rating'])))) }}{{ str_repeat('★', max(0, 5 - min(5, max(0, round($rs['avg_rating']))))) }}</span>
                                    <span class="text-xs text-gray-400">{{ number_format($rs['avg_rating'], 1) }}</span>
                                    <span class="text-xs text-gray-300">({{ $rs['total'] }})</span>
                                </div>
                            @endif

                            @if($product->category)
                                <span class="text-xs text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full inline-block w-fit mb-2">{{ $product->category->name }}</span>
                            @endif

                            <!-- 描述（高亮） -->
                            <p class="text-sm text-gray-500 line-clamp-2 mb-3 flex-1">
                                {!! $highlight ? str_ireplace($highlight, '<mark class="bg-yellow-200 px-0.5 rounded">'.$highlight.'</mark>', e($product->description ?: __('app.products_page.no_desc'))) : e($product->description ?: __('app.products_page.no_desc')) !!}
                            </p>

                            <!-- 卖家 + 在线客服 -->
                            @if($product->creator)
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0 bg-slate-100 flex items-center justify-center">
                                        @if($product->creator->avatar_url)
                                            <img src="{{ $product->creator->avatar_url }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" loading="lazy">
                                        @endif
                                        <span class="text-slate-800 font-bold text-[10px]" @if($product->creator->avatar_url) style="display:none" @endif>{{ mb_substr($product->creator->name, 0, 1) }}</span>
                                    </div>
                                    <span class="text-xs text-gray-400">{{ $product->creator->name }}</span>
                                    <button onclick="event.preventDefault();event.stopPropagation();openSellerChat({{ $product->creator->id }},{{ $product->id }})" class="chat-seller-btn ml-1 inline-flex items-center gap-0.5 text-[10px] text-slate-600 bg-slate-100 hover:bg-slate-100 px-1.5 py-0.5 rounded-full transition" title="{{ __('app.products_page.consult') }}">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>{{ __('app.products_page.chat_seller') }}</button>
                                </div>
                            @endif

                            <!-- 价格 + 销量-->
                            <div class="flex items-end justify-between pt-2 border-t border-gray-50 mt-auto">
                                <div>
                                    @if($product->lowest_price)
                                        <span class="text-lg font-bold text-slate-800">¥{{ number_format($product->lowest_price, 2) }}</span>
                                        @if($product->highest_price && $product->highest_price > $product->lowest_price)
                                            <span class="text-xs text-gray-400"> - ¥{{ number_format($product->highest_price, 2) }}</span>
                                        @endif
                                        <span class="text-xs text-gray-400 ml-1">{{ __('app.products_page.per_month') }}</span>
                                    @else
                                        <span class="text-sm text-gray-400">{{ __('app.products_page.price_tbd') }}</span>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-gray-400">{{ __('app.products_page.sold', ['count' => $product->sold_total ?? 0]) }}</span>
                                    <div class="text-xs text-gray-400">{{ $product->licenses_count ?? 0 }} License</div>
                                </div>
                            </div>
                        </div>
                        </a>
                        <!-- 收藏按钮 -->
                        <button onclick="event.preventDefault();event.stopPropagation();toggleListWishlist({{ $product->id }}, this)" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/80 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 wishlist-btn" data-product-id="{{ $product->id }}" title="{{ __('app.products_page.wishlist') }}">
                            <svg class="w-3.5 h-3.5 wishlist-list-icon text-gray-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </button>
                    </div>
                @empty
                    <div class="col-span-full text-center py-20">
                        <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                        <p class="text-gray-400 text-lg">{{ __('app.products_page.empty') }}</p>
                        <p class="text-gray-400 text-sm mt-2">{{ __('app.products_page.empty_hint') }}</p>
                    </div>
                @endforelse
            </div>

            <!-- ─── 加载更多 ─── -->
            <div id="load-more-container" class="mt-10 text-center">
                <button id="load-more-btn" onclick="loadMore()" class="inline-flex items-center gap-2 px-8 py-3 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-600 hover:border-slate-300 hover:text-slate-900 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 13l-7 7-7-7m14-8l-7 7-7-7"/></svg>
                    {{ __('app.products_page.load_more') }}
                </button>
                <div id="load-more-spinner" class="hidden items-center justify-center gap-2 text-sm text-gray-400 py-3">
                    <svg class="w-5 h-5 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>{{ __('app.products_page.loading') }}</span>
                </div>
                <div id="load-more-end" class="hidden text-sm text-gray-300 py-3">{{ __('app.products_page.all_shown') }}</div>
            </div>
        </div>
    </section>

    <!-- ─── CTA ─── -->
    <section class="py-16 bg-slate-900">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4 tracking-tight">{{ __('app.landing.cta_title') }}</h2>
            <p class="text-slate-300 mb-6">{{ __('app.landing.cta_subtitle') }}</p>
            <a href="/build/register" class="inline-block bg-white text-slate-900 px-8 py-3 rounded-xl font-bold hover:bg-slate-100 transition shadow-lg">{{ __('app.landing.cta_button') }} →</a>
        </div>
    </section>

    <!-- ─── Footer ─── -->
    @include('public.partials.footer')

    <script>
    var PRODUCTS_I18N = {
        badgeNew: @json(__('app.products_page.badge_new')),
        badgeHot: @json(__('app.products_page.badge_hot')),
        badgeDiscount: @json(__('app.products_page.badge_discount')),
        badgeDemo: @json(__('app.products_page.badge_demo')),
        noDesc: @json(__('app.products_page.no_desc')),
        consult: @json(__('app.products_page.consult')),
        chat: @json(__('app.products_page.chat_seller')),
        addCompare: @json(__('app.products_page.add_compare')),
        wishlist: @json(__('app.products_page.wishlist')),
        perMonth: @json(__('app.products_page.per_month')),
        priceTbd: @json(__('app.products_page.price_tbd')),
        sold: @json(__('app.products_page.sold')),
        emptyMatch: @json(__('app.products_page.empty_match')),
        compareNeed2: @json(__('app.products_page.compare_need_2')),
        compareRemoved: @json(__('app.products_page.compare_removed')),
        compareMax: @json(__('app.products_page.compare_max')),
        compareAdded: @json(__('app.products_page.compare_added')),
        cart: @json(__('app.products_page.cart')),
        logout: @json(__('app.products_page.logout')),
    };
    function productsSoldLabel(n) {
        return PRODUCTS_I18N.sold.replace(':count', String(n || 0));
    }
        // ─── 状态变量 ───
        let currentCategory = '';
        let currentSearch = {!! json_encode($highlight ?? '') !!};
        let currentPage = 1;
        let lastPage = 1;
        let currentSort = 'latest';
        let _listToken = localStorage.getItem('auth_token');
        let _isLoadingMore = false;
        let _isNewSearch = true; // true=替换结果, false=追加结果

        // ─── 高级筛选状态 ───
        let filterPriceMin = '';
        let filterPriceMax = '';
        let filterTags = []; // ['is_new', 'is_hot', 'has_discount', 'demo_enabled']
        let filterCreatorId = '';
        let filterPanelOpen = false;

        @if($highlight)
        document.getElementById('search-input').value = '{{ addslashes($highlight) }}';
        @endif

        // ─── 高级筛选：切换面板 ───
        function toggleAdvancedFilter() {
            var panel = document.getElementById('advanced-filter-panel');
            if (!panel) return;
            filterPanelOpen = !filterPanelOpen;
            panel.classList.toggle('hidden', !filterPanelOpen);
            var btn = document.getElementById('toggle-advanced-filter');
            if (btn) {
                btn.classList.toggle('bg-slate-100', filterPanelOpen);
                btn.classList.toggle('border-slate-300', filterPanelOpen);
                btn.classList.toggle('text-slate-800', filterPanelOpen);
            }
        }

        // ─── 高级筛选：标签切换 ───
        function toggleTag(tag) {
            var idx = filterTags.indexOf(tag);
            if (idx >= 0) {
                filterTags.splice(idx, 1);
            } else {
                filterTags.push(tag);
            }
            // 更新按钮样式
            document.querySelectorAll('.filter-tag-btn').forEach(function(btn) {
                var t = btn.getAttribute('data-tag');
                btn.classList.toggle('active', filterTags.indexOf(t) >= 0);
            });
            updateFilterBadge();
        }

        // ─── 产品标签云切换 ───
        var _activeProductTag = '';
        function toggleProductTag(tag) {
            _activeProductTag = (_activeProductTag === tag) ? '' : tag;
            document.querySelectorAll('.product-tag-btn').forEach(function(btn) {
                btn.classList.toggle('active', btn.getAttribute('data-product-tag') === _activeProductTag);
            });
            updateFilterBadge();
        }

        // ─── 高级筛选：值变更时触发 ───
        function onFilterChange() {
            filterPriceMin = document.getElementById('filter-price-min')?.value || '';
            filterPriceMax = document.getElementById('filter-price-max')?.value || '';
            filterCreatorId = document.getElementById('filter-creator')?.value || '';
            updateFilterBadge();
        }

        // ─── 高级筛选：更新角标 ───
        function updateFilterBadge() {
            var count = 0;
            if (filterPriceMin || filterPriceMax) count++;
            if (filterTags.length > 0) count++;
            if (filterCreatorId) count++;
            if (_activeProductTag) count++;
            var badge = document.getElementById('filter-active-badge');
            if (badge) {
                badge.textContent = count;
                badge.classList.toggle('hidden', count === 0);
            }
        }

        // ─── 高级筛选：重置 ───
        function clearAllFilters() {
            filterPriceMin = '';
            filterPriceMax = '';
            filterTags = [];
            filterCreatorId = '';
            _activeProductTag = '';
            if (document.getElementById('filter-price-min')) document.getElementById('filter-price-min').value = '';
            if (document.getElementById('filter-price-max')) document.getElementById('filter-price-max').value = '';
            if (document.getElementById('filter-creator')) document.getElementById('filter-creator').value = '';
            document.querySelectorAll('.filter-tag-btn').forEach(function(btn) { btn.classList.remove('active'); });
            document.querySelectorAll('.product-tag-btn').forEach(function(btn) { btn.classList.remove('active'); });
            updateFilterBadge();
            _isNewSearch = true;
            currentPage = 1;
            loadProducts();
        }

        // ─── 高级筛选：应用 ───
        function applyFilters() {
            onFilterChange();
            _isNewSearch = true;
            currentPage = 1;
            loadProducts();
        }

        // ─── Enter 键搜索 ───
        document.getElementById('search-input')?.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') searchProducts();
        });

        function renderStars(avg) {
            var r = Math.round(avg || 0);
            var s = '';
            for (var i = 0; i < 5; i++) {
                s += i < r ? '⭐' : '☆';
            }
            return '<span class="text-yellow-400 text-sm">' + s + '</span>';
        }

        function highlightText(text, keyword) {
            if (!keyword || !text) return text || '';
            var re = new RegExp('(' + keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
            return text.replace(re, '<mark class="bg-yellow-200 px-0.5 rounded">$1</mark>');
        }

        function showSkeleton() {
            const grid = document.getElementById('products-grid');
            grid.innerHTML = Array.from({length: 8}, () => `
                <div class="bg-white rounded-xl border border-gray-100 overflow-hidden animate-pulse">
                    <div class="aspect-square bg-gray-100"></div>
                    <div class="p-5 space-y-3">
                        <div class="h-5 bg-gray-100 rounded w-3/4"></div>
                        <div class="h-3 bg-gray-100 rounded w-1/4"></div>
                        <div class="h-3 bg-gray-100 rounded w-full"></div>
                        <div class="h-3 bg-gray-100 rounded w-2/3"></div>
                        <div class="flex justify-between pt-3 border-t border-gray-50">
                            <div class="h-6 bg-gray-100 rounded w-20"></div>
                            <div class="h-4 bg-gray-100 rounded w-16"></div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        async function loadProducts() {
            const params = new URLSearchParams();
            if (currentCategory) params.set('category', currentCategory);
            if (currentSearch) params.set('search', currentSearch);
            if (currentSort && currentSort !== 'latest') {
                var sortMap = { price_asc: 'price', price_desc: '-price', sold: '-sold_total', name: 'name', recommended: 'recommended', ai: 'ai', collaborative: 'collaborative', sequence: 'sequence' };
                params.set('sort', sortMap[currentSort] || '-created_at');
            }
            // 高级筛选参数
            if (filterPriceMin) params.set('price_min', filterPriceMin);
            if (filterPriceMax) params.set('price_max', filterPriceMax);
            if (filterTags.length > 0) params.set('tags', filterTags.join(','));
            if (filterCreatorId) params.set('creator_id', filterCreatorId);
            if (_activeProductTag) params.set('product_tag', _activeProductTag);
            params.set('page', currentPage);

            if (_isNewSearch) showSkeleton();

            try {
                const resp = await fetch(`/api/public/products?${params}`);
                const json = await resp.json();
                const grid = document.getElementById('products-grid');
                
                // 更新分页信息
                lastPage = json.meta?.last_page || 1;
                document.getElementById('product-total-count').textContent = json.meta?.total || 0;

                if (!json.data || json.data.length === 0) {
                    if (_isNewSearch) {
                        grid.innerHTML = `<div class="col-span-full text-center py-20">
                            <svg class="w-16 h-16 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                            <p class="text-gray-400 text-lg">${PRODUCTS_I18N.emptyMatch}</p>
                        </div>`;
                    }
                    updateLoadMoreUI();
                    return;
                }

                // Check wishlist statuses
                let wishlistedIds = new Set();
                if (_listToken) {
                    try {
                        const wRes = await fetch('/api/wishlist/my/product-ids', { headers: getListHeaders() });
                        const wData = await wRes.json();
                        if (wData.data?.product_ids) wishlistedIds = new Set(wData.data.product_ids);
                    } catch {}
                }

                var html = json.data.map(p => {
                    const badges = [];
                    if (p.is_new) badges.push('<span class="px-2 py-0.5 bg-slate-900 text-white text-xs font-bold rounded-full">' + @json(__('app.products_page.badge_new')) + '</span>');
                    if (p.is_hot) badges.push('<span class="px-2 py-0.5 bg-slate-700 text-white text-xs font-bold rounded-full">' + @json(__('app.products_page.badge_hot')) + '</span>');
                    if (p.has_discount) badges.push('<span class="px-2 py-0.5 bg-amber-600 text-white text-xs font-bold rounded-full">' + @json(__('app.products_page.badge_discount')) + '</span>');
                    if (p.demo_enabled) badges.push('<span class="px-2 py-0.5 bg-slate-900 text-white text-xs font-bold rounded-full">' + @json(__('app.products_page.badge_demo')) + '</span>');
                    const badgesHtml = badges.length ? `<div class="absolute top-2 left-2 flex flex-col gap-1">${badges.join('')}</div>` : '';

                    const reviewStats = p.review_stats || {};
                    const ratingHtml = reviewStats.total > 0
                        ? `<div class="flex items-center gap-1 mb-1">${renderStars(reviewStats.avg_rating)}<span class="text-xs text-gray-400">${(reviewStats.avg_rating || 0).toFixed(1)}</span><span class="text-xs text-gray-300">(${reviewStats.total})</span></div>`
                        : '';

                    const priceHtml = p.sku_price_min
                        ? `<span class="text-lg font-bold text-slate-800">¥${Number(p.sku_price_min).toFixed(2)}</span>${p.sku_price_max && p.sku_price_max > p.sku_price_min ? `<span class="text-xs text-gray-400"> - ¥${Number(p.sku_price_max).toFixed(2)}</span>` : ''}<span class="text-xs text-gray-400 ml-1">{{ __('app.products_page.per_month') }}</span>`
                        : '<span class="text-sm text-gray-400">{{ __('app.products_page.price_tbd') }}</span>';

                    const creatorHtml = p.creator
                        ? `<div class="flex items-center gap-2 mb-2"><div class="w-5 h-5 rounded-full overflow-hidden flex-shrink-0 bg-slate-100 flex items-center justify-center">${p.creator.avatar_url ? `<img src="${p.creator.avatar_url}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'" loading="lazy">` : ''}<span class="text-slate-800 font-bold text-[10px]"${p.creator.avatar_url ? ' style="display:none"' : ''}>${(p.creator.name || '?')[0]}</span></div><span class="text-xs text-gray-400">${p.creator.name}</span>
                        <button onclick="event.preventDefault();event.stopPropagation();openSellerChat(${p.creator.id},${p.id})" class="chat-seller-btn ml-1 inline-flex items-center gap-0.5 text-[10px] text-slate-600 bg-slate-100 hover:bg-slate-100 px-1.5 py-0.5 rounded-full transition" title="{{ __('app.products_page.consult') }}"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>${PRODUCTS_I18N.chat}</button></div>`
                        : '';

                    const isWishlisted = wishlistedIds.has(p.id);

                    return `
                        <div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col group relative">
                        <a href="/products/${p.slug}" class="block flex flex-col flex-1">
                            <div class="aspect-square bg-gradient-to-br from-gray-100 to-gray-50 overflow-hidden rounded-t-xl relative">
                                ${p.image_url ? `<img src="${p.image_url}" alt="${p.name}" width="400" height="400" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">` : `<div class="text-center p-6"><svg class="w-12 h-12 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg><span class="text-sm text-gray-400">${p.name}</span></div>`}
                                <button onclick="event.preventDefault();event.stopPropagation();toggleCompare(${p.id},'${(p.name||'').replace(/'/g,"\\'")}','${p.image_url||''}','/products/${p.slug}','¥${Number(p.sku_price_min||0).toFixed(2)}')" class="absolute top-10 right-2 w-7 h-7 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 compare-rp-btn" data-pid="${p.id}" title="{{ __('app.products_page.add_compare') }}">
                                    <svg class="w-3.5 h-3.5 text-gray-400 compare-rp-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                                </button>
                            </div>
                            <div class="p-5 flex flex-col flex-1" style="min-height:220px">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900 group-hover:text-slate-900 transition line-clamp-1">${highlightText(p.name, currentSearch)}</h3>
                                    ${p.version ? '<span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full shrink-0 ml-2">v' + p.version + '</span>' : ''}
                                </div>
                                ${ratingHtml}
                                ${p.category ? '<span class="text-xs text-slate-700 bg-slate-100 px-2 py-0.5 rounded-full inline-block w-fit mb-2">' + p.category.name + '</span>' : ''}
                                <p class="text-sm text-gray-500 line-clamp-2 mb-3 flex-1">${highlightText(p.description || PRODUCTS_I18N.noDesc, currentSearch)}</p>
                                ${creatorHtml}
                                <div class="flex items-end justify-between pt-2 border-t border-gray-50 mt-auto">
                                    <div>${priceHtml}</div>
                                    <div class="text-right">
                                        <span class="text-xs text-gray-400">${productsSoldLabel(p.sold_total || p.sales_count || 0)}</span>
                                        <div class="text-xs text-gray-400">${p.licenses_count || 0} License</div>
                                    </div>
                                </div>
                            </div>
                        </a>
                        <button onclick="event.preventDefault();event.stopPropagation();toggleListWishlist(${p.id}, this)" class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/80 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 wishlist-btn" data-product-id="${p.id}" title="{{ __('app.products_page.wishlist') }}">
                            <svg class="w-3.5 h-3.5 wishlist-list-icon transition ${isWishlisted ? 'text-red-500' : 'text-gray-300'}" fill="${isWishlisted ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                        </button>
                        </div>
                    `;
                }).join('');

                if (_isNewSearch) {
                    grid.innerHTML = html;
                } else {
                    grid.insertAdjacentHTML('beforeend', html);
                }
                
                updateLoadMoreUI();
            } catch (e) {
                console.error('Failed to load products', e);
            } finally {
                _isLoadingMore = false;
            }
        }

        // ─── 加载更多 ───
        function loadMore() {
            if (_isLoadingMore || currentPage >= lastPage) return;
            _isLoadingMore = true;
            _isNewSearch = false;
            currentPage++;
            loadProducts();
        }

        // ─── 更新加载更多按钮状态 ───
        function updateLoadMoreUI() {
            var btn = document.getElementById('load-more-btn');
            var spinner = document.getElementById('load-more-spinner');
            var end = document.getElementById('load-more-end');
            if (!btn) return;
            if (currentPage >= lastPage) {
                btn.classList.add('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (end) end.classList.remove('hidden');
            } else {
                btn.classList.remove('hidden');
                if (spinner) spinner.classList.add('hidden');
                if (end) end.classList.add('hidden');
            }
        }

        // ─── 重置搜索/筛选（恢复到第一页） ───

        function filterCategory(slug) {
            currentCategory = slug;
            _isNewSearch = true;
            currentPage = 1;
            document.querySelectorAll('.category-pill').forEach(b => {
                b.classList.toggle('active', slug === '' && b.dataset.slug === undefined || b.dataset.slug === slug);
            });
            loadProducts();
        }

        function searchProducts() {
            currentSearch = document.getElementById('search-input').value;
            _isNewSearch = true;
            currentPage = 1;
            loadProducts();
        }

        // 热门搜索快捷跳转
        function quickSearch(keyword) {
            var ip = document.getElementById('search-input');
            if (ip) ip.value = keyword;
            searchProducts();
        }

        // ─── 视图切换 ───
        var _viewMode = localStorage.getItem('product_view_mode') || 'grid';
        function setViewMode(mode) {
            _viewMode = mode;
            localStorage.setItem('product_view_mode', mode);
            var grid = document.getElementById('products-grid');
            var btnG = document.getElementById('view-grid');
            var btnL = document.getElementById('view-list');
            if (grid) grid.classList.toggle('list-view', mode === 'list');
            if (btnG) btnG.classList.toggle('active', mode === 'grid');
            if (btnL) btnL.classList.toggle('active', mode === 'list');
        }
        // 初始化视图
        document.addEventListener('DOMContentLoaded', function() { setViewMode(_viewMode); });

        function changeSort(value) {
            currentSort = value;
            _isNewSearch = true;
            currentPage = 1;
            loadProducts();
        }

        function getListHeaders() {
            return { 'Authorization': 'Bearer ' + _listToken, 'Accept': 'application/json' };
        }

        // Activate "all" category pill by default
        document.querySelector('.category-pill')?.classList.add('active');
        
        // 初始化加载更多状态
        (function() {
            var totalEl = document.getElementById('product-total-count');
            if (totalEl) {
                var total = parseInt(totalEl.textContent) || 0;
                lastPage = Math.ceil(total / 12) || 1;
                updateLoadMoreUI();
            }
        })();
    </script>

    <!-- ─── 收藏功能 ─── -->
    <script>
    async function toggleListWishlist(productId, btn) {
        if (!_listToken) { window.location.href = '/build/login'; return; }
        try {
            const res = await fetch('/api/wishlist/toggle', {
                method: 'POST',
                headers: { 'Authorization': 'Bearer ' + _listToken, 'Content-Type': 'application/json' },
                body: JSON.stringify({ product_id: productId })
            });
            const json = await res.json();
            const svg = btn.querySelector('svg');
            if (svg) {
                const isWishlisted = json.success && json.data?.id;
                svg.setAttribute('fill', isWishlisted ? 'currentColor' : 'none');
                svg.classList.toggle('text-red-500', isWishlisted);
                svg.classList.toggle('text-gray-300', !isWishlisted);
            }
        } catch(e) { console.error('wishlist error', e); }
    }

    // 页面加载后初始化收藏状态（服务端渲染的卡片）
    document.addEventListener('DOMContentLoaded', function() {
        if (!_listToken) return;
        fetch('/api/wishlist/my/product-ids', { headers: { 'Authorization': 'Bearer ' + _listToken } })
            .then(r => r.json())
            .then(res => {
                if (!res.data?.product_ids || !Array.isArray(res.data.product_ids)) return;
                var ids = new Set(res.data.product_ids);
                document.querySelectorAll('.wishlist-btn').forEach(function(btn) {
                    var pid = parseInt(btn.getAttribute('data-product-id'));
                    if (ids.has(pid)) {
                        var svg = btn.querySelector('svg');
                        if (svg) {
                            svg.setAttribute('fill', 'currentColor');
                            svg.classList.remove('text-gray-300');
                            svg.classList.add('text-red-500');
                        }
                    }
                });
            })
            .catch(function() {});
    });
    </script>

    <!-- ─── Token 登录检查 ─── -->
    <script>
    (function() {
        const token = localStorage.getItem('auth_token');
        if (!token) return;
        if (document.querySelector('#session-user-section')) return;

        fetch('/api/user', {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
            credentials: 'same-origin',
        }).then(r => r.json()).then(res => {
            if (!res.data) return;
            const u = res.data;
            const initial = (u.name || '?').charAt(0).toUpperCase();
            const avatarHtml = u.avatar_url
                ? `<img src="${u.avatar_url}" alt="" class="w-8 h-8 rounded-full object-cover bg-gray-200" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">`
                : '';
            const fallbackHtml = `<div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 text-sm font-bold flex items-center justify-center"${u.avatar_url ? ' style="display:none"' : ''}>${initial}</div>`;

            const guestLinks = document.querySelector('.guest-links-desktop');
            if (guestLinks) {
                guestLinks.innerHTML =
                    '<a href="/build/cart" class="relative">' + PRODUCTS_I18N.cart + '<span id="cart-badge-desktop" class="absolute -top-2 -right-4 bg-red-500 text-white text-xs rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 hidden">0</span></a>' +
                    '<div class="flex items-center gap-2 pl-4 border-l border-gray-200">' +
                        avatarHtml + fallbackHtml +
                        '<span class="text-sm font-medium text-gray-700">' + u.name + '</span>' +
                        '<a href="/build/logout" id="logout-link">' + PRODUCTS_I18N.logout + '</a>' +
                    '</div>';
            }

            const mobileGuestLinks = document.querySelector('.guest-links-mobile');
            if (mobileGuestLinks) {
                mobileGuestLinks.innerHTML =
                    '<div class="flex items-center gap-3 py-2 border-b border-gray-100 mb-2">' +
                        (u.avatar_url
                            ? `<img src="${u.avatar_url}" alt="" class="w-10 h-10 rounded-full object-cover bg-gray-200">`
                            : `<div class="w-10 h-10 rounded-full bg-slate-100 text-slate-700 font-bold flex items-center justify-center">${initial}</div>`) +
                        '<div><div class="text-sm font-medium text-gray-900">' + u.name + '</div><div class="text-xs text-gray-500">' + (u.email || '') + '</div></div>' +
                    '</div>' +
                    '<a href="/build/cart">' + PRODUCTS_I18N.cart + '</a>' +
                    '<a href="/build/logout" id="logout-link-mobile">' + PRODUCTS_I18N.logout + '</a>';
            }

            fetch('/api/cart/summary', {
                headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    const count = d.data?.total_quantity || 0;
                    const badge = document.getElementById('cart-badge-desktop');
                    if (badge) { badge.textContent = count; badge.classList.toggle('hidden', count === 0); }
                }
            }).catch(() => {});
        }).catch(() => {});
    })();

    // ═══════ 产品对比 ═══════
    function goCompare(){var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');if(items.length<2){showToast(PRODUCTS_I18N.compareNeed2);return}window.location.href='/compare-products?ids='+items.map(function(it){return it.id}).join(',')}
    function saveCompareList(arr){sessionStorage.setItem('compare_items',JSON.stringify(arr));updateCompareBar();document.querySelectorAll('[id^="compare-btn-"]').forEach(function(b){b.classList.remove('text-amber-500')})}
    function toggleCompare(id,n,img,url,price){
        var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');
        var idx=-1;
        for(var i=0;i<items.length;i++){if(items[i].id===id){idx=i;break}}
        if(idx>=0){items.splice(idx,1);showToast(PRODUCTS_I18N.compareRemoved)}
        else{if(items.length>=4){showToast(PRODUCTS_I18N.compareMax);return}
        items.push({id:id,name:n,image:img,url:url,price:price});showToast(PRODUCTS_I18N.compareAdded)}
        sessionStorage.setItem('compare_items',JSON.stringify(items));
        var btn=document.getElementById('compare-btn-'+id);
        if(btn)btn.classList.toggle('text-amber-500',idx<0);
        updateCompareBar();
    }
    function updateCompareBar(){
        var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');
        var bar=document.getElementById('compare-floating-bar');
        var count=document.getElementById('compare-bar-count');
        var container=document.getElementById('compare-bar-items');
        var link=document.getElementById('compare-bar-link');
        if(count)count.textContent=items.length;
        if(link){var ids=items.map(function(it){return it.id}).join(',');link.href='/compare-products?ids='+ids}
        if(bar){bar.classList.toggle('hidden',items.length===0)}
        if(container){
            container.innerHTML='';
            items.forEach(function(item){
                var d=document.createElement('div');
                d.className='flex items-center gap-1.5 px-2 py-1 bg-gray-50 rounded-lg text-xs';
                d.innerHTML='<img src="'+item.image+'" class="w-6 h-6 rounded object-cover" onerror="this.style.display=\'none\'"><span class="text-gray-700 max-w-[80px] truncate">'+item.name+'</span><button onclick="toggleCompare('+item.id+')" class="text-gray-400 hover:text-red-500 ml-1">&times;</button>';
                container.appendChild(d)})
        }
    }
    function showToast(msg){
        var t=document.createElement('div');
        t.className='fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-sm px-5 py-2.5 rounded-xl shadow-lg z-[999] animate-fade-in';
        t.textContent=msg;
        document.body.appendChild(t);
        setTimeout(function(){t.style.opacity='0';t.style.transition='opacity 0.3s';setTimeout(function(){t.remove()},300)},2000)
    }
    // 初始化对比栏
    document.addEventListener('DOMContentLoaded',function(){updateCompareBar()});
    </script>

    <div id="compare-floating-bar" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-3 py-2.5 z-40 hidden shadow-[0_-4px_20px_rgba(0,0,0,0.1)] safe-bottom">
        <div class="max-w-7xl mx-auto flex items-center gap-2">
            <div class="flex items-center gap-1 text-xs sm:text-sm text-gray-500 shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                <span>{{ __('app.products_page.compare_start') }} (<span id="compare-bar-count">0</span>)</span>
            </div>
            <div id="compare-bar-items" class="flex-1 flex items-center gap-2 overflow-x-auto"></div>
            <a id="compare-bar-link" href="javascript:void(0)" onclick="goCompare()" class="shrink-0 px-4 py-1.5 bg-slate-900 text-white text-sm rounded-lg hover:bg-slate-800 transition">{{ __('app.products_page.compare_start') }}</a>
            <button onclick="saveCompareList([])" class="shrink-0 text-xs text-gray-400 hover:text-red-500 transition">{{ __('app.products_page.clear_compare') }}</button>
        </div>
    </div>

    <!-- ═══════ 联系客服 ═══════ -->
    <a href="/help" class="fixed bottom-28 md:bottom-8 right-3 md:right-8 w-12 h-12 md:w-14 md:h-14 rounded-full bg-slate-900 text-white flex items-center justify-center z-50 shadow-lg hover:bg-slate-800 hover:scale-110 transition-all duration-300" aria-label="{{ __('app.products_page.contact_support') }}" title="{{ __('app.products_page.contact_support') }}" style="display:none">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
    </a>

    <script>
    // 打开在线客服弹窗
    function openSellerChat(sellerId, productId) {
        var target = '/build/user-chat?seller_id=' + encodeURIComponent(sellerId) + '&product_id=' + encodeURIComponent(productId);
        var token = localStorage.getItem('auth_token');
        if (!token) {
            window.location.href = '/build/login?redirect=' + encodeURIComponent(target);
            return;
        }
        window.location.href = target;
    }
    </script>
</body>
</html>
