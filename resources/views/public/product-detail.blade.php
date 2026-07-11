<!DOCTYPE html>

<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $product->name }} - 互物通 | 企业级授权管理系统</title>

    <meta name="description" content="浏览互物通平台上的优质软件产品，找到适合您的授权解决方案">

    <meta property="og:title" content="{{ $product->name }} - 互物通 | 企业级授权管理系统">

    <meta property="og:description" content="{{ $product->description ?: $product->name }}">

    <meta property="og:type" content="product">

    <meta property="og:url" content="{{ url('/products/'.$product->slug) }}">

    @if($product->image_url)<meta property="og:image" content="{{ $product->image_url }}">@endif

    <link rel="canonical" href="{{ url('/products/'.$product->slug) }}">

    @include('public.partials.tracking')

    <script>

    // 暗色模式初始化

    (function() {

        const saved = localStorage.getItem('huwutong_theme');

        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

        if (saved === 'dark' || (!saved && prefersDark)) {

            document.documentElement.setAttribute('data-theme', 'dark');

        }

    })();

    </script>

    @php

        $_hasPlans = isset($pricingPlans) && $pricingPlans->count() > 0;

        $_hasSkus = isset($skus) && $skus->count() > 0;

        if ($_hasPlans) {

            $_lowPrice = $pricingPlans->min('price_monthly') ?: 0;

            $_highPrice = $pricingPlans->max('price_monthly') ?: 0;

            $_offerCount = $pricingPlans->count();

        } elseif ($_hasSkus) {

            $_lowPrice = $skus->min('price') ?: 0;

            $_highPrice = $skus->max('price') ?: 0;

            $_offerCount = $skus->count();

        } else {

            $_lowPrice = 0;

            $_highPrice = 0;

            $_offerCount = 1;

        }

        // 计费周期 label
        $_cycleLabel = '';
        if ($_hasSkus) {
            $__minSku = $skus->sortBy('price')->first();
            $_cycleLabel = $__minSku && $__minSku->billing_cycle === 'yearly' ? '/年' : ($__minSku && $__minSku->billing_cycle === 'quarterly' ? '/季' : '/月');
        } elseif ($_hasPlans) {
            $_cycleLabel = '/月';
        }

        $_avgRating = $product->review_stats['avg_rating'] ?? 0;

        $_ratingCount = $product->review_stats['total'] ?? 0;

        $_categoryName = $product->category?->name ?? 'Software';

    @endphp

    <script type="application/ld+json">

    {

        "@context": "https://schema.org",

        "@type": ["Product", "SoftwareApplication"],

        "@id": "{{ url('/products/'.$product->slug) }}#product",

        "name": "{{ $product->name }}",

        "description": "{{ $product->description ?: $product->name }}",

        "url": "{{ url('/products/'.$product->slug) }}",

        "version": "{{ $product->version ?: '1.0' }}",

        "applicationCategory": "{{ $_categoryName }}",

        "operatingSystem": "Windows, macOS, Linux, iOS, Android",

        "category": "{{ $_categoryName }}",

        @if($product->image_url)

        "image": "{{ $product->image_url }}",

        @endif

        @if($product->creator)

        "author": {

            "@type": "Person",

            "name": "{{ $product->creator->name }}"

        },

        @endif

        "offers": {

            "@type": "AggregateOffer",

            "offerCount": "{{ $_offerCount }}",

            "priceCurrency": "CNY",

            "lowPrice": "{{ $_lowPrice }}",

            "highPrice": "{{ $_highPrice }}",

            "availability": "https://schema.org/InStock"

        }

        @if($_ratingCount > 0)

        ,

        "aggregateRating": {

            "@type": "AggregateRating",

            "ratingValue": "{{ number_format($_avgRating, 1) }}",

            "bestRating": "5",

            "worstRating": "1",

            "ratingCount": "{{ $_ratingCount }}"

        }

        @endif

        ,

        "mainEntityOfPage": {

            "@type": "WebPage",

            "@id": "{{ url('/products/'.$product->slug) }}"

        }

    }

    </script>

    @vite('resources/css/public.css')

    <style>

        /* 暗色模式 */

        [data-theme="dark"] { --bg-body: #0f172a; --bg-card: #1e293b; --bg-elevated: #334155; --text-main: #f1f5f9; --text-sec: #94a3b8; --text-muted: #64748b; --border: #475569; }

        [data-theme="dark"] body { background: #0f172a; color: #f1f5f9; }

        [data-theme="dark"] .dark-bg-card { background: #1e293b !important; }

        [data-theme="dark"] .dark-bg-body { background: #0f172a !important; }

        [data-theme="dark"] .dark-border { border-color: #475569 !important; }

        [data-theme="dark"] .dark-text { color: #f1f5f9 !important; }

        [data-theme="dark"] .dark-text-sec { color: #94a3b8 !important; }

        [data-theme="dark"] .dark-text-muted { color: #64748b !important; }

        [data-theme="dark"] nav, [data-theme="dark"] .nav-blur { background: #1e293b !important; }

        [data-theme="dark"] .product-card div { animation: slideUp .3s ease; }

        @keyframes slideUp { from { transform: translateY(60px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        @media (min-width: 768px) {

            #share-dialog > div { animation: zoomIn .2s ease; }

        }

        /* 信任徽章 */

        .trust-badge { transition: all 0.2s ease; }

        .trust-badge:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }

        /* 返回顶部 */

        #back-to-top { transition: all 0.3s ease; }

        #back-to-top:hover { transform: translateY(-3px); }
/* 详细描述富文本样式 */
        .product-long-desc h2 { font-size:1.35rem; font-weight:700; margin-top:1.5rem; margin-bottom:0.5rem; }
        .product-long-desc h3 { font-size:1.15rem; font-weight:600; margin-top:1.25rem; margin-bottom:0.5rem; }
        .product-long-desc p { margin-bottom:0.75rem; line-height:1.75; }
        .product-long-desc ul, .product-long-desc ol { padding-left:1.5rem; margin-bottom:0.75rem; }
        .product-long-desc li { margin-bottom:0.25rem; }
        .product-long-desc blockquote { border-left:4px solid #3b82f6; padding-left:1rem; color:#6b7280; margin-bottom:0.75rem; font-style:italic; }
        .product-long-desc code { background:#f3f4f6; padding:0.125rem 0.375rem; border-radius:0.25rem; font-size:0.875rem; }
        .product-long-desc pre { background:#f3f4f6; padding:1rem; border-radius:0.5rem; overflow-x:auto; margin-bottom:1rem; }
        .product-long-desc pre code { background:none; padding:0; }
        .product-long-desc a { color:#2563eb; text-decoration:underline; }
        .product-long-desc strong { font-weight:700; }
        .product-long-desc em { font-style:italic; }
        .commission-badge { display: none; }

    </style>

</head>

<body class="font-sans antialiased text-gray-800 bg-gray-50">

    <!-- 产品信息区域 -->

    @include('public.partials.nav')

    <!-- 暗色模式切换（产品详情页专用） -->
    <div class="fixed top-4 right-24 z-50 hidden md:block">
        <button onclick="toggleDarkMode()" class="dark-toggle w-9 h-9 rounded-full bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition shrink-0" title="切换暗色模式" id="dark-mode-toggle">
            <svg class="w-5 h-5 text-gray-600 dark-toggle-sun hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            <svg class="w-5 h-5 text-gray-600 dark-toggle-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
        </button>
    </div>



    <!-- 产品信息区域 -->

    <section class="pt-24 pb-4 bg-white border-b border-gray-100">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-sm text-gray-500 flex items-center gap-2">

                <a href="{{ url('/') }}" class="hover:text-primary-600 transition">首页</a>

                <span>/</span>

                <a href="{{ url('/products') }}" class="hover:text-primary-600 transition">产品商城</a>

                <span>/</span>

                <span class="text-gray-900 font-medium">{{ $product->name }}</span>

            </div>

        </div>

    </section>



    <!-- 商品对比区域 -->

    <section class="py-8 bg-white">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="grid md:grid-cols-2 gap-10">

                <!-- 产品描述 -->

                <div>

                    <!-- 标题 -->

                    <div id="main-image" class="aspect-square bg-gray-50 rounded-xl overflow-hidden cursor-pointer" onclick="openLightbox()">

                        @if($product->image_url)

                            <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover" loading="lazy">

                        @else

                            <div class="text-center p-12">

                                <svg class="w-24 h-24 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>

                                <p class="text-gray-400">{{ $product->name }}</p>

                            </div>

                        @endif

                    </div>

                    <!-- 缩略图列表（左右滚动） -->

                    @php $_hasImages = ($product->image_url) || (is_array($product->images) && count($product->images) > 0); @endphp

                    @if($_hasImages)

                    <div class="flex items-center gap-1 mt-4">

                        <button onclick="scrollThumbs(-1)" class="shrink-0 w-7 h-16 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition" title="向左">&lsaquo;</button>

                        <div id="thumb-scroll" class="flex gap-2 overflow-x-auto scroll-smooth pb-1 flex-1" style="scrollbar-width:thin">

                            @if($product->image_url)

                                <button onclick="switchImage(this,'{{ $product->image_url }}')" class="gallery-thumb w-16 h-16 rounded-lg border-2 border-primary-500 overflow-hidden shrink-0 hover:border-primary-300 transition" data-src="{{ $product->image_url }}">

                                    <img src="{{ $product->image_url }}" class="w-full h-full object-cover" loading="lazy">

                                </button>

                            @endif

                            @if(is_array($product->images))

                                @foreach($product->images as $i => $image)

                                    <button onclick="switchImage(this,'{{ $image }}')" class="gallery-thumb w-16 h-16 rounded-lg border-2 border-gray-200 overflow-hidden shrink-0 hover:border-primary-300 transition" data-src="{{ $image }}">

                                        <img src="{{ $image }}" class="w-full h-full object-cover" loading="lazy">

                                    </button>

                                @endforeach

                            @endif

                        </div>

                        <button onclick="scrollThumbs(1)" class="shrink-0 w-7 h-16 rounded-lg bg-gray-100 hover:bg-gray-200 flex items-center justify-center text-gray-500 transition" title="向右">&rsaquo;</button>

                    </div>

                    @endif

                </div>



                <!-- 分享弹窗 -->

                <div class="flex flex-col">

                    <!-- 标题 -->

                    <div class="flex items-start gap-3 mb-2">

                        <h1 class="text-3xl font-bold text-gray-900">{{ $product->name }}</h1>

                        @if($product->version)

                            <span class="text-sm bg-gray-100 text-gray-500 px-3 py-1 rounded-full shrink-0 mt-1">v{{ $product->version }}</span>

                        @endif

                    </div>



                    <!-- 评分 + 评价 + 促销信息-->

                    <div class="bg-gradient-to-r from-red-50 to-orange-50 rounded-xl p-4 mb-4">

                        <div class="flex items-baseline gap-3">

                            @php

                                $_minPrice = $skus ? $skus->min('price') : null;

                                $_maxPrice = $skus ? $skus->max('price') : null;

                                $_soldTotal = $skus ? $skus->sum('sold_count') : 0;

                                $_avgRating = $product->review_stats['avg_rating'] ?? 0;

                                $_ratingCount = $product->review_stats['total'] ?? 0;

                            @endphp

                            <span class="text-3xl font-bold text-red-500">

                                @if($_minPrice && $_maxPrice && $_minPrice != $_maxPrice)

                                    ¥{{ number_format($_minPrice, 2) }}~{{ number_format($_maxPrice, 2) }}

                                @elseif($_minPrice)

                                    ¥{{ number_format($_minPrice, 2) }}

                                @else

                                    联系客服询价

                                @endif

                            </span>

                        </div>

                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-500">

                            <span class="flex items-center gap-1">

                                <span class="text-yellow-400">★</span>

                                <span id="header-avg-rating">{{ number_format($_avgRating, 1) }}</span> <span id="header-rating-count" class="text-gray-400">({{ $_ratingCount }} 条评价)</span>

                            </span>

                            <span>已售 {{ $_soldTotal }}</span>

                            <span class="flex items-center gap-1">

                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>

                                {{ $_wishlistCount ?? 0 }} 收藏

                            </span>

                            <span class="flex items-center gap-1">

                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>

                                {{ $product->view_count ?? 0 }} 次浏览

                            </span>

                            @if($product->category)

                                <span class="text-primary-600 bg-primary-50 px-2 py-0.5 rounded">{{ $product->category->name }}</span>

                            @endif

                        </div>

                    </div>



                    <div class="prose prose-gray max-w-none mb-6">

                        <p class="text-gray-600 leading-relaxed">{{ $product->description ?: '暂无描述' }}</p>

                    </div>



                    <!-- 评分 + 评价 -->

                    <div class="flex items-center gap-2 mb-4">

                        <button id="detail-wishlist-btn" onclick="toggleWishlist({{ $product->id }})" class="flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 hover:border-red-300 hover:text-red-500 transition text-sm text-gray-500">

                            <svg id="detail-wishlist-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>

                            收藏

                        </button>

                        <button onclick="openShareDialog()" class="flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 hover:border-primary-300 hover:text-primary-600 transition text-sm text-gray-500">

                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>

                            分享

                        </button>

                        <button onclick="toggleCompare({{ $product->id }}, '{{ $product->name }}', '{{ $product->image_url }}', '{{ url('/products/'.$product->slug) }}', '¥{{ number_format($_lowPrice, 2) }}')" class="flex items-center gap-1.5 px-4 py-2 rounded-lg border border-gray-200 hover:border-amber-300 hover:text-amber-600 transition text-sm text-gray-500" id="compare-btn-{{ $product->id }}">

                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>

                            对比

                        </button>

                        @if($product->demo_enabled)

                        <button onclick="openDemoDialog()" class="flex items-center gap-1.5 px-4 py-2 rounded-lg border border-primary-200 bg-primary-50 hover:bg-primary-100 hover:border-primary-300 transition text-sm text-primary-600 font-medium">

                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>

                            演示

                        </button>

                        @endif

                    </div>



                    <!-- 页面加载状态区域 -->

                    @if($product->creator)

                    <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">

                        <div class="flex items-center gap-3">

                            <a href="{{ url('/seller/'.$product->creator->id) }}" class="shrink-0">

                                <div class="w-14 h-14 rounded-full overflow-hidden bg-primary-100 flex items-center justify-center">

                                    @if($product->creator->avatar_url)

                                        <img src="{{ $product->creator->avatar_url }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">

                                    @endif

                                    <span class="text-primary-600 font-bold text-lg" @if($product->creator->avatar_url) style="display:none" @endif>{{ mb_substr($product->creator->name, 0, 1) }}</span>

                                </div>

                            </a>

                            <div class="flex-1 min-w-0">

                                <div class="flex items-center gap-2">

                                    <span class="text-base font-semibold text-gray-900 truncate">{{ $product->creator->name }}</span>

                                    <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full font-medium shrink-0">官方授权</span>

                                </div>

                                @if($product->creator->region)

                                    <div class="text-sm text-gray-500 mt-0.5 flex items-center gap-1">

                                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>

                                        {{ $product->creator->region }}

                                    </div>

                                @endif

                            </div>

                        </div>

                        <div class="flex items-center gap-2 mt-4">

                            <button id="follow-seller-btn" data-user-id="{{ $product->creator->id }}"

                                class="flex-1 py-2 rounded-lg border border-primary-600 text-primary-600 hover:bg-primary-50 transition text-sm font-medium"

                                onclick="toggleFollowSeller(this)">

                                + 关注卖家

                            </button>

                            <button onclick="openSellerChat({{ $product->creator->id }},{{ $product->id }})" class="flex-1 py-2 rounded-lg border border-primary-600 text-primary-600 hover:bg-primary-50 transition text-sm font-medium text-center">

                                联系客服

                            </button>

                        </div>

                    </div>



                    <script>

                    // 关注卖家状态初始化

                    document.addEventListener('DOMContentLoaded', async function() {

                        const btn = document.getElementById('follow-seller-btn');

                        if (!btn) return;

                        const userId = btn.dataset.userId;

                        try {

                            const r = await fetch('/api/sellers/' + userId + '/follow-status', {

                                headers: getAuthHeaders()

                            });

                            const data = await r.json();

                            if (data.success && data.data?.is_following) {

                                btn.classList.add('following', 'bg-primary-600', 'text-white');

                                btn.classList.remove('border', 'border-primary-600', 'text-primary-600');

                                btn.textContent = '已关注';

                            }

                        } catch {}

                    });

                    async function toggleFollowSeller(btn) {

                        const userId = btn.dataset.userId;

                        if (!userId) return;

                        const isFollowing = btn.classList.contains('following');

                        try {

                            const url = isFollowing ? '/api/sellers/' + userId + '/unfollow' : '/api/sellers/' + userId + '/follow';

                            const r = await fetch(url, {

                                method: 'POST',

                                headers: { 'Authorization': 'Bearer ' + _token, 'Accept': 'application/json', 'Content-Type': 'application/json' }

                            });

                            if (r.ok) {

                                if (isFollowing) {

                                    btn.classList.remove('following', 'bg-primary-600', 'text-white');

                                    btn.classList.add('border', 'border-primary-600', 'text-primary-600');

                                    btn.textContent = '+ 关注卖家';

                                } else {

                                    btn.classList.add('following', 'bg-primary-600', 'text-white');

                                    btn.classList.remove('border', 'border-primary-600', 'text-primary-600');

                                    btn.textContent = '已关注';

                                }

                            }

                        } catch {}

                    }

                    </script>

                    @endif



                    <!-- 产品详情 / 产品属性 -->

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">

                        <div class="trust-badge flex items-center gap-2.5 bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">

                            <div class="w-9 h-9 rounded-full bg-green-100 flex items-center justify-center shrink-0">

                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>

                            </div>

                            <div>

                                <div class="text-xs font-semibold text-gray-900">正品保障</div>

                                <div class="text-[10px] text-gray-400">官方授权</div>

                            </div>

                        </div>

                        <div class="trust-badge flex items-center gap-2.5 bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">

                            <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center shrink-0">

                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>

                            </div>

                            <div>

                                <div class="text-xs font-semibold text-gray-900">售后无忧</div>

                                <div class="text-[10px] text-gray-400">官方授权</div>

                            </div>

                        </div>

                        <div class="trust-badge flex items-center gap-2.5 bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">

                            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center shrink-0">

                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"/></svg>

                            </div>

                            <div>

                                <div class="text-xs font-semibold text-gray-900">安全支付</div>

                                <div class="text-[10px] text-gray-400">官方授权</div>

                            </div>

                        </div>

                        <div class="trust-badge flex items-center gap-2.5 bg-gray-50 rounded-xl px-4 py-3 border border-gray-100">

                            <div class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0">

                                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>

                            </div>

                            <div>

                                <div class="text-xs font-semibold text-gray-900">正品保障</div>

                                <div class="text-[10px] text-gray-400">官方授权</div>

                            </div>

                        </div>

                    </div>

                    <!-- 促销/优惠券/秒杀/预售展示 -->
                    @php $_hasPromo = ($activePromotions && $activePromotions->count() > 0) || ($activeCoupons && $activeCoupons->count() > 0) || $flashSale || $preSale; @endphp
                    @if($_hasPromo)
                    @php
                        // 格式化金额：整数去小数
                        $_fmt = function($v) { return intval($v) == $v ? intval($v) : number_format($v, $v < 10 ? 2 : 1); };
                    @endphp
                    <div class="mt-6">
                        {{-- 标题栏 --}}
                        <div class="flex items-center gap-2.5 mb-3">
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-red-50 text-xs">🎉</span>
                            <span class="text-sm font-bold text-gray-900">限时优惠</span>
                            <span class="h-px flex-1 bg-gradient-to-r from-red-100 via-orange-100 to-transparent"></span>
                        </div>

                        <div class="space-y-2.5">
                        {{-- ⚡ 秒杀通栏突出 --}}
                        @if($flashSale)
                        <div class="relative overflow-hidden bg-gradient-to-r from-red-500 via-red-500 to-orange-500 rounded-xl p-3.5 group hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                            {{-- 装饰斜纹 --}}
                            <div class="absolute inset-0 opacity-[0.08]" style="background-image: repeating-linear-gradient(45deg, transparent, transparent 8px, rgba(255,255,255,0.3) 8px, rgba(255,255,255,0.3) 16px)"></div>
                            <div class="relative flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0 backdrop-blur-sm"><span class="text-base">⚡</span></div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-[11px] font-bold text-white uppercase tracking-wider">{{ $flashSale->name }}</span>
                                        <span class="text-[11px] text-white/60 line-through">¥{{ $_fmt($flashSale->original_price) }}</span>
                                        <span class="text-lg font-black text-white drop-shadow-sm">¥{{ $_fmt($flashSale->flash_price) }}</span>
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-3">
                                    <div class="hidden sm:flex items-center gap-1.5">
                                        <div class="w-14 h-1.5 bg-white/20 rounded-full overflow-hidden">
                                            @php $_soldPct = $flashSale->stock > 0 ? min(100, round(($flashSale->orders_count ?? 0) / $flashSale->stock * 100)) : 0; @endphp
                                            <div class="h-full bg-white rounded-full transition-all duration-500" style="width:{{ $_soldPct }}%"></div>
                                        </div>
                                        <span class="text-[10px] text-white/70">{{ $flashSale->orders_count ?? 0 }}/{{ $flashSale->stock }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5 bg-black/15 rounded-lg px-2.5 py-1">
                                        <span class="text-[10px] text-white/70">⏱</span>
                                        <span class="text-xs text-white font-mono font-bold flash-countdown tracking-wider" data-end="{{ $flashSale->end_time->timestamp }}">--:--:--</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        @endif

                        {{-- 小网格卡片区 --}}
                        @php
                            $_gridItems = collect();
                            if ($preSale) $_gridItems->push(['type' => 'presale', 'data' => $preSale]);
                            if ($activePromotions) foreach ($activePromotions as $p) $_gridItems->push(['type' => 'promo', 'data' => $p]);
                            if ($activeCoupons) foreach ($activeCoupons as $c) $_gridItems->push(['type' => 'coupon', 'data' => $c]);
                        @endphp
                        @if($_gridItems->count() > 0)
                        <div class="grid grid-cols-2 gap-2 auto-rows-fr">
                            @foreach($_gridItems as $item)
                                @if($item['type'] === 'presale')
                                @php $ps = $item['data']; @endphp
                                <div class="bg-gradient-to-br from-blue-50 to-indigo-50/50 rounded-xl p-3 border border-blue-100/60 flex items-center gap-2.5 h-full group hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform"><span class="text-sm">{{ $ps->type === 'crowdfunding' ? '🚀' : '📋' }}</span></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[11px] font-bold text-blue-700">{{ $ps->type === 'crowdfunding' ? '众筹中' : '预售中' }}</span>
                                            <span class="text-[10px] bg-blue-200/60 text-blue-600 px-1.5 py-0.5 rounded-full font-medium">¥{{ $_fmt($ps->min_amount) }}起</span>
                                        </div>
                                        <div class="flex items-center gap-2 mt-1">
                                            @php $_rp = $ps->target_amount > 0 ? min(100, round($ps->raised_amount / $ps->target_amount * 100)) : 0; @endphp
                                            <div class="flex-1 h-1.5 bg-blue-100 rounded-full overflow-hidden max-w-[80px]">
                                                <div class="h-full bg-blue-400 rounded-full transition-all" style="width:{{ $_rp }}%"></div>
                                            </div>
                                            <span class="text-[10px] text-gray-500 font-medium">{{ $_rp }}%</span>
                                            <span class="text-[10px] text-gray-400">· {{ $ps->current_backers ?? 0 }}人</span>
                                        </div>
                                        @if($ps->estimated_delivery_at)
                                        <div class="text-[10px] text-gray-400 mt-0.5">预计 {{ $ps->estimated_delivery_at->format('Y年n月') }} 发货</div>
                                        @endif
                                    </div>
                                </div>
                                @elseif($item['type'] === 'promo')
                                @php $pr = $item['data']; @endphp
                                <div class="bg-gradient-to-br from-green-50 to-emerald-50/50 rounded-xl p-3 border border-green-100/60 flex items-center gap-2.5 h-full group hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer">
                                    <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform"><span class="text-sm">{{ $pr->type === 'bulk_discount' ? '🏷️' : ($pr->type === 'bundle' ? '📦' : ($pr->type === 'free_gift' ? '🎁' : '🎉')) }}</span></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5 flex-wrap">
                                            <span class="text-[11px] font-bold text-green-800">{{ $pr->name }}</span>
                                            @if($pr->discount_value)
                                            <span class="text-[10px] bg-green-200 text-green-700 px-1.5 py-0.5 rounded-full font-semibold">{{ $pr->discount_type === 'percentage' ? $_fmt($pr->discount_value).'%OFF' : '减¥'.$_fmt($pr->discount_value) }}</span>
                                            @endif
                                        </div>
                                        @if($pr->ends_at)
                                        <div class="text-[10px] text-gray-400 mt-1">⏳ {{ $pr->ends_at->diffForHumans() }} 结束</div>
                                        @endif
                                    </div>
                                </div>
                                @elseif($item['type'] === 'coupon')
                                @php $cp = $item['data']; @endphp
                                <div class="relative bg-white rounded-xl border-2 border-dashed border-primary-200 p-3 flex items-center gap-2.5 h-full group hover:shadow-md hover:-translate-y-0.5 transition-all duration-200 cursor-pointer overflow-hidden">
                                    {{-- 装饰圆点 --}}
                                    <div class="absolute -left-1.5 top-1/2 -translate-y-1/2 w-3 h-3 rounded-full bg-white border-2 border-primary-200"></div>
                                    <div class="absolute -right-1.5 top-1/2 -translate-y-1/2 w-3 h-3 rounded-full bg-white border-2 border-primary-200"></div>
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-100 to-primary-50 flex items-center justify-center shrink-0 group-hover:scale-110 transition-transform"><span class="text-sm">🎟️</span></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-[13px] font-black text-primary-600">
                                            @if($cp->type === 'percentage'){{ $_fmt($cp->value) }}%OFF
                                            @elseif($cp->type === 'fixed_amount')<span class="text-lg">¥{{ $_fmt($cp->value) }}</span>
                                            @else{{ $cp->name }}
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                            <span class="text-[10px] bg-primary-50 text-primary-600 px-1.5 py-0.5 rounded font-mono font-bold">{{ $cp->code }}</span>
                                            @if($cp->usage_limit)
                                            <span class="text-[10px] text-gray-400">剩{{ max(0, $cp->usage_limit - $cp->usage_count) }}张</span>
                                            @endif
                                        </div>
                                    </div>
                                    {{-- 领券按钮 --}}
                                    <div class="shrink-0">
                                        <span class="inline-flex items-center justify-center text-[10px] font-bold text-white bg-gradient-to-r from-primary-500 to-primary-600 rounded-lg px-2.5 py-1.5 group-hover:from-primary-600 group-hover:to-primary-700 transition-all">领券</span>
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        @endif
                        </div>
                    </div>
                    @endif

                    </div>



                </div>

            </div>

        </div>

    </section>



    <!-- 产品信息 / SKU 选择区域 -->

    @if(($pricingPlans && $pricingPlans->count() > 0) || ($skus && $skus->count() > 0))

    <section class="py-16 bg-gray-50">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-12">

                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">选择方案</h2>

                <p class="text-gray-500">选择适合您的方案，开启 {{ $product->name }}</p>

            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 max-w-5xl mx-auto">

                @if($pricingPlans && $pricingPlans->count() > 0)

                    @foreach($pricingPlans as $plan)

                        <div class="plan-card rounded-xl border-2 {{ $plan->badge === 'popular' ? 'border-primary-500 popular' : 'border-gray-200' }} bg-white p-6 flex flex-col relative">

                            @if($plan->badge === 'popular')

                                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-primary-600 text-white text-xs font-semibold px-4 py-1 rounded-full">最佳选择</div>

                            @endif

                            @if($plan->badge === 'best_value')

                                <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-500 text-white text-xs font-semibold px-4 py-1 rounded-full">性价比之选</div>

                            @endif

                            <h3 class="text-lg font-bold text-gray-900 mt-2">{{ $plan->name }}</h3>

                            <p class="text-sm text-gray-500 mt-1 mb-4">{{ $plan->description }}</p>

                            <div class="mb-4">

                                <span class="text-3xl font-bold text-gray-900">¥{{ $plan->price_monthly }}</span>

                                <span class="text-gray-500 text-sm">/月</span>

                            </div>

                            @if($plan->price_yearly > 0)

                                <p class="text-xs text-green-600 mb-4">年付 ￥{{ $plan->price_yearly }}，省 {{ round((1 - $plan->price_yearly / ($plan->price_monthly * 12)) * 100) }}%</p>

                            @endif

                            <ul class="space-y-2 text-sm text-gray-600 flex-1 mb-6">

                                @if(is_array($plan->features))

                                    @foreach($plan->features as $feature)

                                        <li class="flex items-start gap-2">

                                            <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>

                                            {{ $feature }}

                                        </li>

                                    @endforeach

                                @endif

                            </ul>

                            <div class="flex gap-2 mt-auto">

                                <a href="/build/subscribe/{{ $plan->id }}" class="flex-1 block text-center py-3 rounded-xl font-semibold transition {{ $plan->price_monthly == 0 ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-primary-600 text-white hover:bg-primary-700 shadow-lg' }} text-sm">

                                    {{ $plan->price_monthly == 0 ? '免费使用' : '立即订阅' }}

                                </a>

                            </div>

                        </div>

                    @endforeach

                @elseif($skus && $skus->count() > 0)

                    @foreach($skus as $sku)

                        @php

                            $_hasDiscount = $sku->compare_at_price && $sku->compare_at_price > $sku->price;

                            $_discountPct = $_hasDiscount ? round((1 - $sku->price / $sku->compare_at_price) * 100) : 0;

                            $_inStock = $sku->stock === -1 || $sku->stock > 0;

                            $_lowStock = $sku->stock > 0 && $sku->stock <= 5;

                            $_cycleLabel = $sku->billing_cycle === 'yearly' ? '/年' : ($sku->billing_cycle === 'monthly' ? '/月' : '');

                        @endphp

                        <div class="plan-card rounded-xl border-2 border-gray-200 bg-white p-6 flex flex-col relative hover:shadow-lg transition">

                            @if($_hasDiscount)

                                <div class="absolute -top-3 right-4 bg-red-500 text-white text-xs font-bold px-3 py-1 rounded-full">

                                    -{{ $_discountPct }}%

                                </div>

                            @endif

                            @if($_lowStock)

                                <div class="absolute -top-3 left-4 bg-amber-500 text-white text-xs font-bold px-3 py-1 rounded-full">

                                    仅剩 {{ $sku->stock }} 件

                                </div>

                            @endif

                            <h3 class="text-lg font-bold text-gray-900 mt-2">{{ $sku->name }}</h3>

                            @if($_hasDiscount)

                                <div class="mb-1">

                                    <span class="text-sm text-gray-400 line-through">¥{{ $sku->compare_at_price }}</span>

                                    <span class="text-xs text-red-500 ml-1">省 {{ $sku->compare_at_price - $sku->price }}</span>

                                </div>

                            @endif

                            <div class="mb-2">

                                <span class="text-3xl font-bold text-gray-900">¥{{ $sku->price }}</span>

                                <span class="text-gray-500 text-sm">{{ $_cycleLabel }}</span>

                            </div>

                            <div class="flex items-center gap-3 mb-4 text-xs">

                                <span class="flex items-center gap-1 {{ $_inStock ? 'text-green-600' : 'text-red-500' }}">

                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"/></svg>

                                    {{ $_inStock ? ($_lowStock ? '库存紧张' : '有货') : '缺货' }}

                                </span>

                                <span class="text-gray-400">已售 {{ $sku->sold_count ?: 0 }}</span>

                                <span class="text-gray-300">|</span>

                                <span class="text-gray-400 font-mono">{{ $sku->sku_code }}</span>

                            </div>

                            @if($sku->commission_rate)

                                <div class="commission-badge inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold mb-4"
                                     style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e; border: 1px solid #fbbf24; box-shadow: 0 1px 3px rgba(251,191,36,0.2);">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span>推广佣金</span>
                                    <span class="font-bold" style="color:#b45309;">{{ $sku->commission_rate }}%</span>
                                </div>

                            @endif

                            <div class="flex gap-2 mt-auto">

                                @if($_inStock)

                                <button onclick="addToCart({{ $sku->id }})" class="flex-1 py-3 rounded-xl font-semibold transition border-2 border-primary-600 text-primary-600 hover:bg-primary-50 text-sm">

                                    加入购物车

                                </button>

                                <a href="javascript:void(0)" onclick="buyNow({{ $sku->id }})" class="flex-1 block text-center py-3 rounded-xl font-semibold transition bg-primary-600 text-white hover:bg-primary-700 shadow-lg text-sm">

                                    立即购买

                                </a>

                                @else

                                <button onclick="openStockNotify({{ $sku->id }}, '{{ $sku->name }}')" class="w-full py-3 rounded-xl font-semibold transition border-2 border-amber-500 text-amber-600 hover:bg-amber-50 text-sm flex items-center justify-center gap-1.5">

                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>

                                    到货通知

                                </button>

                                @endif

                            </div>

                        </div>

                    @endforeach

                @endif

            </div>

        </div>

    </section>

    @endif



    <!-- 产品详情 Tab切换 评价 | 规格参数 -->

    <section class="py-12 bg-white border-t border-gray-100">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Tab 切换 -->

            <div class="flex border-b border-gray-200 mb-8" id="detail-tabs">

                <button onclick="switchTab('detail')" class="tab-btn px-6 py-3 text-sm font-medium border-b-2 border-primary-600 text-primary-600 transition" data-tab="detail">商品详情</button>

                @if($product->specGroups && $product->specGroups->count() > 0)

                <button onclick="switchTab('specs')" class="tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent transition" data-tab="specs">规格参数</button>

                @endif

                <button onclick="switchTab('reviews')" class="tab-btn px-6 py-3 text-sm font-medium text-gray-500 hover:text-gray-700 border-b-2 border-transparent transition" data-tab="reviews">评价 <span id="tab-rating-count">({{ $_ratingCount }})</span></button>

            </div>



            <!-- Tab 切换 -->

            <div id="tab-detail" class="tab-content">

                <!-- 评价列表 -->

                @if($product->featureFlags && $product->featureFlags->count() > 0)

                    <div class="mb-8">

                        <h3 class="text-lg font-semibold text-gray-900 mb-4">产品特性</h3>

                        <div class="flex flex-wrap gap-3">

                            @foreach($product->featureFlags as $flag)

                                <span class="inline-flex items-center gap-1.5 text-sm text-gray-700 bg-gray-50 px-4 py-2 rounded-lg border border-gray-100">

                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>

                                    {{ $flag->name }}

                                </span>

                            @endforeach

                        </div>

                    </div>

                @endif



                <!-- 产品特性 -->

                @if(is_array($product->modules) && count($product->modules) > 0)

                    <div class="mb-8">

                        <h3 class="text-lg font-semibold text-gray-900 mb-4">包含模块</h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">

                            @foreach($product->modules as $module)

                                <div class="flex items-center gap-2 text-sm text-gray-600 bg-gray-50 px-4 py-3 rounded-lg border border-gray-100">

                                    <svg class="w-4 h-4 text-primary-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>

                                    {{ is_string($module) ? $module : ($module['name'] ?? '') }}

                                </div>

                            @endforeach

                        </div>

                    </div>

                @endif



                <!-- 详细描述 -->

                @if($product->long_description)

                <div class="prose prose-gray max-w-none">

                    <h3 class="text-lg font-semibold text-gray-900 mb-4">详细描述</h3>

                    <div class="product-long-desc text-gray-700">{!! $product->long_description !!}</div>

                </div>

                @elseif($product->description)

                <div class="prose prose-gray max-w-none">

                    <h3 class="text-lg font-semibold text-gray-900 mb-4">详细描述</h3>

                    <p class="text-gray-600 leading-relaxed">{{ $product->description }}</p>

                </div>

                @endif

            </div>



            <!-- 评价 Tab -->

            @if($product->specGroups && $product->specGroups->count() > 0)

            <div id="tab-specs" class="tab-content hidden">

                @foreach($product->specGroups as $group)

                    @if($group->specs && $group->specs->count() > 0)

                        <div class="mb-6">

                            <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ $group->name }}</h4>

                            <div class="bg-gray-50 rounded-xl overflow-hidden">

                                <table class="w-full text-sm">

                                    <tbody>

                                        @foreach($group->specs as $i => $spec)

                                            <tr class="{{ $i % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">

                                                <td class="px-5 py-3 text-gray-500 w-1/3 border-b border-gray-100">{{ $spec->name }}</td>

                                                <td class="px-5 py-3 text-gray-900 font-medium border-b border-gray-100">{{ $spec->value }}</td>

                                            </tr>

                                        @endforeach

                                    </tbody>

                                </table>

                            </div>

                        </div>

                    @endif

                @endforeach

            </div>

            @endif



            <!-- 推荐 Tab -->

            <div id="tab-reviews" class="tab-content hidden">

                <!-- 产品特性 -->

                <div id="review-stats" class="bg-white rounded-xl p-6 mb-8 hidden">

                    <div class="flex items-center gap-8">

                        <div id="rating-summary" class="text-center">

                            <div id="avg-rating" class="text-4xl font-bold text-yellow-500">0.0</div>

                            <div id="rating-stars" class="text-yellow-400 text-lg mt-1"></div>

                            <div id="rating-count" class="text-sm text-gray-400 mt-1">0 条评价</div>

                        </div>

                        <div id="rating-bars" class="flex-1 space-y-1"></div>

                    </div>

                </div>

                <!-- 评价筛选/排序栏 -->
                <div id="review-toolbar" class="flex items-center justify-between mb-4 hidden">
                    <div class="flex items-center gap-2">
                        <button onclick="filterReviews('all')" class="review-filter-btn px-3 py-1.5 rounded-lg text-sm font-medium transition" data-filter="all">全部</button>
                        <button onclick="filterReviews('good')" class="review-filter-btn px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition" data-filter="good">好评 <span class="text-xs text-gray-400">4-5★</span></button>
                        <button onclick="filterReviews('medium')" class="review-filter-btn px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition" data-filter="medium">中评 <span class="text-xs text-gray-400">3★</span></button>
                        <button onclick="filterReviews('bad')" class="review-filter-btn px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition" data-filter="bad">差评 <span class="text-xs text-gray-400">1-2★</span></button>
                    </div>
                    <div class="flex items-center gap-1 text-sm">
                        <button onclick="sortReviews('newest')" class="review-sort-btn px-3 py-1.5 rounded-lg text-sm font-medium transition" data-sort="newest">最新</button>
                        <button onclick="sortReviews('helpful')" class="review-sort-btn px-3 py-1.5 rounded-lg text-sm font-medium text-gray-500 hover:bg-gray-100 transition" data-sort="helpful">最有帮助</button>
                    </div>
                </div>

                <div id="reviews-container" class="space-y-4">

                    <div class="text-center py-12 text-gray-400">

                        <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>

                        <p>暂无评价，成为第一个评价的人</p>

                    </div>

                </div>

                <!-- 评分 -->

                <div class="text-center mt-8">

                    <button id="write-review-btn" onclick="document.getElementById('review-form').classList.toggle('hidden')" class="inline-flex items-center gap-2 px-6 py-3 bg-primary-600 text-white rounded-xl hover:bg-primary-700 transition font-medium text-sm">

                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>

                        写评价

                    </button>

                </div>

                <!-- 产品特性 -->

                <div id="review-form" class="hidden bg-white rounded-xl p-6 mt-6 border border-gray-100">

                    <h3 class="text-lg font-semibold mb-4">写评价</h3>

                    <div class="mb-4">

                        <label class="block text-sm text-gray-600 mb-2">您的评分</label>

                        <div id="review-rating" class="flex gap-1 text-2xl cursor-pointer">

                            <span onclick="setRating(1)" class="star text-gray-300 hover:text-yellow-400 transition">★</span>

                            <span onclick="setRating(2)" class="star text-gray-300 hover:text-yellow-400 transition">★</span>

                            <span onclick="setRating(3)" class="star text-gray-300 hover:text-yellow-400 transition">★</span>

                            <span onclick="setRating(4)" class="star text-gray-300 hover:text-yellow-400 transition">★</span>

                            <span onclick="setRating(5)" class="star text-gray-300 hover:text-yellow-400 transition">★</span>

                        </div>

                    </div>

                    <div class="mb-4">

                        <label class="block text-sm text-gray-600 mb-2">评价内容</label>

                        <textarea id="review-content" rows="4" class="w-full px-4 py-3 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="分享您的使用体验…（至少5个字）"></textarea>

                    </div>

                    <!-- 评价标签 -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-2">评价标签 <span class="text-gray-400 text-xs">(选填，让评价更直观)</span></label>
                        <div id="review-tags" class="flex flex-wrap gap-2">
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">功能强大</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">易于使用</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">性价比高</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">文档清晰</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">性能稳定</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">安装方便</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">客服响应快</span>
                            <span onclick="toggleReviewTag(this)" class="review-tag cursor-pointer px-3 py-1.5 rounded-full text-xs border border-gray-200 text-gray-500 hover:border-primary-300 hover:text-primary-600 transition">持续更新</span>
                        </div>
                    </div>

                    <!-- 图片上传 -->
                    <div class="mb-4">
                        <label class="block text-sm text-gray-600 mb-2">上传图片 <span class="text-gray-400 text-xs">(选填，最多 6 张)</span></label>
                        <div class="flex items-center gap-3">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition text-sm text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                选择图片
                                <input type="file" accept="image/jpeg,image/png,image/gif,image/webp" multiple onchange="handleReviewImages(this)" class="hidden">
                            </label>
                            <span id="review-image-count" class="text-xs text-gray-400">未选择</span>
                        </div>
                        <div id="review-image-previews" class="flex gap-2 mt-3 flex-wrap"></div>
                    </div>

                    <div class="flex items-center justify-between">

                        <label class="flex items-center gap-2 text-sm text-gray-500">

                            <input type="checkbox" id="review-anonymous" class="rounded">

                            匿名提交

                        </label>

                        <button onclick="submitReview({{ $product->id }})" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition text-sm font-medium">提交评价</button>

                    </div>

                    <p id="review-message" class="mt-3 text-sm hidden"></p>

                </div>

            </div>

        </div>

    </section>



    <script>

    function switchTab(tab) {

        document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));

        document.getElementById('tab-' + tab)?.classList.remove('hidden');

        document.querySelectorAll('.tab-btn').forEach(el => {

            el.classList.remove('border-primary-600', 'text-primary-600');

            el.classList.add('border-transparent', 'text-gray-500');

        });

        const btn = document.querySelector(`.tab-btn[data-tab="${tab}"]`);

        if (btn) {

            btn.classList.remove('border-transparent', 'text-gray-500');

            btn.classList.add('border-primary-600', 'text-primary-600');

        }

        // 切换到评价Tab时加载评价
        if (tab === 'reviews' && typeof loadReviews === 'function') {
            loadReviews({{ $product->id }});
        }

    }

    </script>



    <!-- 商品对比区域 -->

    @if($relatedProducts && $relatedProducts->count() > 0)

    <section class="py-16 bg-white">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-10">

                <h2 class="text-2xl font-bold text-gray-900">相关推荐</h2>

            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">

                @foreach($relatedProducts as $rp)

                    <div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col group relative">

                    <a href="{{ url('/products/'.$rp->slug) }}" class="block flex flex-col flex-1">

                        <div class="aspect-square bg-gradient-to-br from-primary-50 to-blue-50">

                            @if($rp->image_url)

                                <img src="{{ $rp->image_url }}" alt="{{ $rp->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" loading="lazy">

                            @else

                                <div class="text-center p-4">

                                    <svg class="w-10 h-10 text-gray-300 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>

                                </div>

                            @endif

                            <!-- 标题 -->

                            <div class="absolute top-2 left-2 flex flex-col gap-1">

                                @if($rp->is_new ?? false)

                                    <span class="px-2 py-0.5 bg-green-500 text-white text-xs font-bold rounded-full">新品</span>

                                @endif

                                @if($rp->is_hot ?? false)

                                    <span class="px-2 py-0.5 bg-red-500 text-white text-xs font-bold rounded-full">热销</span>

                                @endif

                                @if($rp->has_discount ?? false)

                                    <span class="px-2 py-0.5 bg-orange-500 text-white text-xs font-bold rounded-full">优惠</span>

                                @endif

                            </div>

                            <!-- 产品特性 -->

                            <button onclick="event.preventDefault();event.stopPropagation();toggleCompare({{ $rp->id }},'{{ $rp->name }}','{{ $rp->image_url }}','{{ url('/products/'.$rp->slug) }}','¥{{ number_format($rp->lowest_price ?: 0, 2) }}')"

                                class="absolute top-10 right-2 w-7 h-7 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 compare-rp-btn" data-pid="{{ $rp->id }}" title="加入对比">

                                <svg class="w-3.5 h-3.5 text-gray-400 compare-rp-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>

                            </button>

                            <!-- 对比按钮 -->

                            <button onclick="event.preventDefault();event.stopPropagation();toggleWishlist({{ $rp->id }}, event)" class="absolute top-2 right-2 p-1.5 rounded-full bg-white/80 hover:bg-white transition shadow-sm z-10 wishlist-related-btn" data-product-id="{{ $rp->id }}" title="收藏">

                                <svg class="w-4 h-4 text-gray-300 hover:text-red-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>

                            </button>

                        </div>

                        <div class="p-4 flex flex-col flex-1" style="min-height:190px">

                            <h3 class="font-semibold text-gray-900 group-hover:text-primary-600 transition line-clamp-1">{{ $rp->name }}</h3>

                            @if($rp->category)

                                <span class="text-xs text-primary-600 bg-primary-50 px-2 py-0.5 rounded-full inline-block w-fit mb-1 mt-1">{{ $rp->category->name }}</span>

                            @endif

                            <!-- 标题 -->

                            @php $rs = $rp->review_stats; @endphp

                            @if($rs['total'] > 0)

                                <div class="flex items-center gap-1 mb-1">

                                    <span class="text-yellow-400 text-xs">{{ str_repeat('★', min(5, max(0, round($rs['avg_rating'])))) }}{{ str_repeat('☆', max(0, 5 - min(5, max(0, round($rs['avg_rating']))))) }}</span>

                                    <span class="text-xs text-gray-400">{{ number_format($rs['avg_rating'], 1) }}</span>

                                    <span class="text-xs text-gray-300">({{ $rs['total'] }})</span>

                                </div>

                            @endif

                            <p class="text-xs text-gray-500 line-clamp-2 mb-2 flex-1">{{ $rp->description ?: '暂无描述' }}</p>

                            @if($rp->creator)

                                <div class="flex items-center gap-1.5 mb-2">

                                    <div class="w-4 h-4 rounded-full overflow-hidden flex-shrink-0 bg-primary-50 flex items-center justify-center">

                                        @if($rp->creator->avatar_url)

                                            <img src="{{ $rp->creator->avatar_url }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">

                                        @endif

                                        <span class="text-primary-600 font-bold text-[9px]" @if($rp->creator->avatar_url) style="display:none" @endif>{{ mb_substr($rp->creator->name, 0, 1) }}</span>

                                    </div>

                                    <span class="text-[10px] text-gray-400">{{ $rp->creator->name }}</span>

                                </div>

                            @endif

                            <!-- 分享 + 对比 -->

                            <div class="flex items-end justify-between pt-2 border-t border-gray-50 mt-auto">

                                <div>

                                    @if($rp->lowest_price)

                                        @php
                                            $_rpCycle = '月';
                                            if($rp->skus && $rp->skus->count() > 0) {
                                                $__minSku = $rp->skus->sortBy('price')->first();
                                                $_rpCycle = $__minSku->billing_cycle === 'yearly' ? '年' : ($__minSku->billing_cycle === 'quarterly' ? '季' : '月');
                                            }
                                        @endphp

                                        <span class="text-base font-bold text-primary-600">¥{{ number_format($rp->lowest_price, 2) }}</span><span class="text-[10px] text-gray-400 cycle-label">/{{ $_rpCycle }}</span>

                                    @else

                                        <span class="text-xs text-gray-400">咨询价格</span>

                                    @endif

                                </div>

                                <div class="text-right">

                                    <span class="text-[10px] text-gray-400">{{ $rp->sold_total ?? 0 }} 已售</span>

                                </div>

                            </div>

                        </div>

                    </a>

                    </div>

                @endforeach

            </div>

        </div>

    </section>

    @endif



    <!-- 页面内容区域 -->

    <section id="recently-viewed-section" class="py-12 bg-white" style="display:none">

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

            <div class="text-center mb-8">

                <h2 class="text-xl font-bold text-gray-900">最近浏览</h2>

                <p class="text-sm text-gray-500 mt-1">您最近看过的产品</p>

            </div>

            <div class="flex items-center justify-end mb-4">

                <button onclick="clearRecentlyViewed()" class="text-xs text-gray-400 hover:text-red-500 transition flex items-center gap-1">

                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>

                    清除历史

                </button>

            </div>

            <div id="recently-viewed-container">

            </div>

        </div>

    </section>



    <!-- 行动 CTA 区域 -->

    <section class="py-16 bg-gradient-to-r from-primary-600 to-blue-700">

        <div class="max-w-3xl mx-auto px-4 text-center">

            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">开启 {{ $product->name }} 之旅</h2>

            <p class="text-primary-100 mb-6">注册即享全功能试用，无需信用卡</p>

            <a href="/build/register" class="inline-block bg-white text-primary-600 px-8 py-3 rounded-xl font-bold hover:bg-primary-50 transition shadow-lg">免费开始使用</a>

        </div>

    </section>



    <!-- 页面 Footer 区域 -->

    @include('public.partials.footer')



    <script>
var _token=localStorage.getItem('auth_token');
var _currentRating=5;
var _isAuth=!!_token;
var _currentImageIndex=0;
// 佣金显示权限检查：仅代理/管理员可见
(async function(){
    if(!_token) return;
    try{
        var r=await fetch('/api/user/permissions/check-commission',{headers:{'Authorization':'Bearer '+_token,'Accept':'application/json'}});
        var d=await r.json();
        if(d.canSeeCommission){
            document.querySelectorAll('.commission-badge').forEach(function(el){el.style.display='flex'});
        }
    }catch(e){}
})();
function getAuthHeaders(){var h={'Accept':'application/json','Content-Type':'application/json'};if(_token)h['Authorization']='Bearer '+_token;return h}
function showToast(m){var e=document.getElementById('toast-msg');if(e)e.remove();var d=document.createElement('div');d.id='toast-msg';d.className='fixed bottom-6 left-1/2 -translate-x-1/2 z-[999] px-6 py-3 rounded-xl bg-gray-900 text-white text-sm shadow-xl max-w-sm text-center';d.textContent=m;document.body.appendChild(d);setTimeout(function(){d.style.opacity='0';d.style.transition='opacity 0.3s';setTimeout(function(){d.remove()},300)},2500)}
function toggleDarkMode(){var h=document.documentElement;if(h.getAttribute('data-theme')==='dark'){h.removeAttribute('data-theme');localStorage.setItem('huwutong_theme','light')}else{h.setAttribute('data-theme','dark');localStorage.setItem('huwutong_theme','dark')}updateDarkToggleIcon()}
function updateDarkToggleIcon(){var d=document.documentElement.getAttribute('data-theme')==='dark';document.querySelectorAll('.dark-toggle-sun').forEach(function(e){e.classList.toggle('hidden',!d)});document.querySelectorAll('.dark-toggle-moon').forEach(function(e){e.classList.toggle('hidden',d)})}
if(document.readyState!=='loading')updateDarkToggleIcon();else document.addEventListener('DOMContentLoaded',updateDarkToggleIcon);
async function toggleWishlist(pId,ev){if(!_token){window.location.href='/build/login?redirect='+encodeURIComponent(window.location.href);return}try{var r=await fetch('/api/wishlist/toggle',{method:'POST',headers:getAuthHeaders(),body:JSON.stringify({product_id:pId})});var d=await r.json();if(d.success){var btn=ev?ev.currentTarget:document.getElementById('detail-wishlist-btn');if(btn){if(d.data&&d.data.id){btn.classList.add('text-red-500','border-red-300');btn.classList.remove('text-gray-500')}else{btn.classList.remove('text-red-500','border-red-300');btn.classList.add('text-gray-500')}}if(d.message)showToast(d.message)}}catch(e){console.error(e)}}
async function checkWishlist(pId){if(!_token)return;try{var r=await fetch('/api/wishlist/check/'+pId,{headers:getAuthHeaders()});var d=await r.json();if(d.success&&d.data&&d.data.wishlisted){var btn=document.getElementById('detail-wishlist-btn');if(btn){btn.classList.add('text-red-500','border-red-300');btn.classList.remove('text-gray-500')}}}catch(e){}}
// ─── 缩略图滚动并自动选中 ───
function scrollThumbs(dir) {
    var container = document.getElementById('thumb-scroll');
    if (!container) return;
    var thumbs = Array.from(container.querySelectorAll('.gallery-thumb'));
    if (!thumbs.length) return;

    // 找当前高亮的缩略图索引
    var curIdx = -1;
    thumbs.forEach(function(t, i) {
        if (t.classList.contains('border-primary-500')) curIdx = i;
    });
    if (curIdx < 0) curIdx = 0;

    var scrollAmount = container.scrollWidth - container.clientWidth;
    if (scrollAmount > 0) {
        // 有溢出 → 滚动容器
        var step = Math.min(200, scrollAmount);
        var prevScroll = container.scrollLeft;
        var target = Math.max(0, Math.min(container.scrollLeft + dir * step, scrollAmount));

        // 如果在左边界向左，或右边界向右 → 切换到上一张/下一张（循环）
        var atLeftBoundary = prevScroll <= 0 && dir < 0;
        var atRightBoundary = prevScroll >= scrollAmount && dir > 0;

        if (atLeftBoundary || atRightBoundary) {
            var nextIdx = curIdx + dir;
            if (nextIdx < 0) nextIdx = thumbs.length - 1;
            if (nextIdx >= thumbs.length) nextIdx = 0;
            var t = thumbs[nextIdx];
            var src = t.getAttribute('data-src');
            if (src) switchImage(t, src);
            // 如果目标方向还有空间，滚动一下让选中的缩略图更可见
            var nextScroll = Math.max(0, Math.min(container.scrollLeft + dir * step, scrollAmount));
            if (nextScroll !== container.scrollLeft) container.scrollLeft = nextScroll;
            return;
        }

        container.scrollLeft = target;
        // 滚动后选中最可见的缩略图
        var best = null, bestVisible = 0;
        var cr = container.getBoundingClientRect();
        thumbs.forEach(function(t) {
            var tr = t.getBoundingClientRect();
            var visible = Math.max(0, Math.min(tr.right, cr.right) - Math.max(tr.left, cr.left));
            if (visible > bestVisible) { bestVisible = visible; best = t; }
        });
        if (best) { var src = best.getAttribute('data-src'); if (src) switchImage(best, src); }
    } else {
        // 无溢出 → 直接切换上一张/下一张
        var nextIdx = curIdx + dir;
        if (nextIdx < 0) nextIdx = thumbs.length - 1;
        if (nextIdx >= thumbs.length) nextIdx = 0;
        var t = thumbs[nextIdx];
        var src = t.getAttribute('data-src');
        if (src) switchImage(t, src);
    }
}

// ─── 图片切换 ───
var _imageList = []; // 图片URL列表，由 switchImage 维护
function switchImage(btn, src) {
    if (!src) return;
    // 标准化 URL（去除协议/域名，用于相对/绝对路径匹配）
    function normalizeUrl(url) {
        try { return new URL(url, window.location.origin).pathname; }
        catch(e) { return url; }
    }
    var normSrc = normalizeUrl(src);
    // 更新主图
    var mainImg = document.querySelector('#main-image img');
    if (mainImg) mainImg.src = src;
    // 更新缩略图高亮（用标准化 URL 比较）
    document.querySelectorAll('.gallery-thumb').forEach(function(el) {
        var elSrc = normalizeUrl(el.getAttribute('data-src') || '');
        el.classList.toggle('border-primary-500', elSrc === normSrc);
        el.classList.toggle('border-gray-200', elSrc !== normSrc);
    });
    // 维护图片列表用于灯箱导航
    _imageList = [];
    document.querySelectorAll('.gallery-thumb').forEach(function(el) {
        var s = el.getAttribute('data-src');
        if (s && _imageList.indexOf(s) === -1) _imageList.push(s);
    });
    // 用标准化 URL 查找当前索引
    _currentImageIndex = _imageList.findIndex(function(s) { return normalizeUrl(s) === normSrc; });
}
function prevImage(){
    if (_imageList.length > 0) {
        var idx = _currentImageIndex > 0 ? _currentImageIndex - 1 : _imageList.length - 1;
        var src = _imageList[idx];
        switchImage(null, src);
        openLightbox(src);
    }
}
function nextImage(){
    if (_imageList.length > 0) {
        var idx = _currentImageIndex < _imageList.length - 1 ? _currentImageIndex + 1 : 0;
        var src = _imageList[idx];
        switchImage(null, src);
        openLightbox(src);
    }
}
async function addToCart(skuId){if(!_token){window.location.href='/build/login?redirect='+encodeURIComponent(window.location.href);return}try{var r=await fetch('/api/cart/add',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','Authorization':'Bearer '+_token},body:JSON.stringify({sku_id:skuId,quantity:1})});var d=await r.json();if(d.success){var badge=document.getElementById('cart-badge-desktop');if(badge){var c=(parseInt(badge.textContent)||0)+1;badge.textContent=c;badge.classList.remove('hidden')}showToast('已成功添加到购物车')}else showToast(d.message||'添加失败')}catch(e){showToast('请先登录后再操作')}}
function addToCartFromSticky(){var s=document.querySelector('.plan-card');if(s){var b=s.querySelector('button');if(b){b.click();return}}alert('请先选择方案')}
function setRating(v){_currentRating=v;document.querySelectorAll('#review-rating .star').forEach(function(e,i){e.classList.toggle('text-yellow-400',i<v)})}
function renderStars(r){var s='';for(var i=0;i<5;i++)s+='<span class="'+(i<r?'text-yellow-400':'text-gray-300')+'">★</span>';return s}

// ─── 评价标签选择 ───
function toggleReviewTag(el){
    el.classList.toggle('bg-primary-50');
    el.classList.toggle('border-primary-500');
    el.classList.toggle('text-primary-700');
    el.classList.toggle('text-gray-500');
    if(el.classList.contains('bg-primary-50')){
        el.classList.remove('border-gray-200','hover:border-primary-300','hover:text-primary-600');
    }else{
        el.classList.add('border-gray-200','hover:border-primary-300','hover:text-primary-600');
    }
}
function getSelectedTags(){
    var tags=[];
    document.querySelectorAll('#review-tags .review-tag').forEach(function(el){
        if(el.classList.contains('bg-primary-50')) tags.push(el.textContent.trim());
    });
    return tags;
}

// ─── 评价筛选/排序 ───
var _reviewFilter='all',_reviewSort='newest';
function filterReviews(val){
    _reviewFilter=val;
    document.querySelectorAll('.review-filter-btn').forEach(function(b){b.classList.toggle('bg-primary-50',b.getAttribute('data-filter')===val);b.classList.toggle('text-primary-700',b.getAttribute('data-filter')===val);b.classList.toggle('text-gray-500',b.getAttribute('data-filter')!==val)});
    applyFilterAndSort();
}
function sortReviews(val){
    _reviewSort=val;
    document.querySelectorAll('.review-sort-btn').forEach(function(b){b.classList.toggle('bg-primary-50',b.getAttribute('data-sort')===val);b.classList.toggle('text-primary-700',b.getAttribute('data-sort')===val);b.classList.toggle('text-gray-500',b.getAttribute('data-sort')!==val)});
    applyFilterAndSort();
}
function applyFilterAndSort(){
    var c=document.getElementById('reviews-container');
    if(!c)return;
    var items=c.querySelectorAll('.review-item');
    var visible=[];
    items.forEach(function(el){
        var rating=parseInt(el.getAttribute('data-rating')||'0');
        var show=true;
        if(_reviewFilter==='good'&&rating<4)show=false;
        else if(_reviewFilter==='medium'&&rating!==3)show=false;
        else if(_reviewFilter==='bad'&&rating>3)show=false;
        el.style.display=show?'':'none';
        if(show)visible.push(el);
    });
    // 排序
    if(_reviewSort==='helpful'){
        visible.sort(function(a,b){
            var ha=parseInt(a.getAttribute('data-helpful')||'0');
            var hb=parseInt(b.getAttribute('data-helpful')||'0');
            return hb-ha;
        });
        var p=c.parentNode||c;
        visible.forEach(function(el){c.appendChild(el)});
    }
}

// ─── 评价图片上传 ───
var _reviewImages=[]; // 已上传的图片URL

function handleReviewImages(input){
    var files=input.files;
    if(!files||!files.length)return;
    if(!_token){showToast('请先登录后再上传图片');input.value='';return}
    var remaining=6-_reviewImages.length;
    if(files.length>remaining){showToast('最多上传6张图片');return}
    for(var i=0;i<files.length;i++){
        (function(file){
            var fd=new FormData();
            fd.append('file',file);
            showToast('正在上传…');
            fetch('/api/products/upload-image',{
                method:'POST',
                headers:{'Authorization':'Bearer '+_token,'Accept':'application/json'},
                body:fd
            }).then(function(r){
                if(!r.ok){return r.json().then(function(d){throw new Error(d.message||'上传失败('+r.status+')')}).catch(function(){throw new Error('上传失败('+r.status+')')})}
                return r.json();
            }).then(function(d){
                if(d.success&&d.data&&d.data.url){
                    _reviewImages.push(d.data.url);
                    renderReviewPreviews();
                    showToast('图片上传成功');
                }else{showToast(d.message||'上传失败')}
            }).catch(function(e){showToast(e.message||'图片上传失败')});
        })(files[i]);
    }
    input.value='';
}

function renderReviewPreviews(){
    var c=document.getElementById('review-image-previews');
    var ct=document.getElementById('review-image-count');
    if(!c)return;
    c.innerHTML='';
    _reviewImages.forEach(function(url,i){
        var div=document.createElement('div');
        div.className='relative group';
        div.innerHTML='<img src="'+url+'" class="w-16 h-16 object-cover rounded-lg border border-gray-200">'
            +'<button onclick="removeReviewImage('+i+')" class="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition shadow">✕</button>';
        c.appendChild(div);
    });
    if(ct)ct.textContent=_reviewImages.length+'/6 张';
}

function removeReviewImage(idx){
    _reviewImages.splice(idx,1);
    renderReviewPreviews();
}
async function loadReviews(pId){try{var sr=await fetch('/api/products/'+pId+'/reviews/stats',{headers:getAuthHeaders()});var sd=await sr.json();var st=sd.data;if(st&&st.total_reviews>0){document.getElementById('review-stats').classList.remove('hidden');document.getElementById('avg-rating').textContent=(st.avg_rating||0).toFixed(1);document.getElementById('rating-stars').innerHTML=renderStars(Math.round(st.avg_rating||0));document.getElementById('rating-count').textContent=st.total_reviews+' 条评价';var hr=document.getElementById('header-avg-rating');if(hr)hr.textContent=(st.avg_rating||0).toFixed(1);var hc=document.getElementById('header-rating-count');if(hc)hc.textContent='('+st.total_reviews+' 条评价)';var tc=document.getElementById('tab-rating-count');if(tc)tc.textContent='('+st.total_reviews+')';var bars=document.getElementById('rating-bars');bars.innerHTML='';for(var star=5;star>=1;star--){var pct=(st.distribution||{})[star]||0;var div=document.createElement('div');div.className='flex items-center gap-2 text-sm';div.innerHTML='<span class="w-6 text-gray-500">'+star+'★</span><div class="flex-1 bg-gray-100 rounded-full h-2"><div class="bg-yellow-400 h-2 rounded-full" style="width:'+pct+'%"></div></div><span class="w-8 text-right text-gray-400">'+pct+'%</span>';bars.appendChild(div)}}var rr=await fetch('/api/products/'+pId+'/reviews?per_page=10',{headers:getAuthHeaders()});var rd=await rr.json();var c=document.getElementById('reviews-container');if(rd.success&&rd.data&&rd.data.data&&rd.data.data.length>0){var html='';for(var j=0;j<rd.data.data.length;j++){var r=rd.data.data[j];var d=new Date(r.created_at);var ds=d.getFullYear()+'-'+('0'+(d.getMonth()+1)).slice(-2)+'-'+('0'+d.getDate()).slice(-2);var av=r.user&&r.user.avatar_url?r.user.avatar_url:'';var nm=r.is_anonymous?'匿名用户':(r.user?r.user.name:'用户');html+='<div class="bg-white rounded-xl p-6 border border-gray-100"><div class="flex items-center gap-3 mb-3">';if(av)html+='<img src="'+av+'" class="w-10 h-10 rounded-full object-cover">';else html+='<div class="w-10 h-10 rounded-full bg-primary-100 text-primary-600 flex items-center justify-center font-bold">'+nm.charAt(0)+'</div>';html+='<div><div class="font-medium text-gray-900">'+nm+(r.is_verified_purchase?'<span class="ml-1.5 text-[10px] text-green-600 bg-green-50 px-1.5 py-0.5 rounded font-medium">已购买</span>':'')+'</div><div class="text-yellow-400 text-sm">'+renderStars(r.rating)+'</div></div><div class="ml-auto text-xs text-gray-400">'+ds+'</div></div>';html+='<p class="text-gray-600 text-sm leading-relaxed">'+(r.content||'')+'</p>';if(r.images&&r.images.length){html+='<div class="flex gap-2 mt-3">';for(var k=0;k<r.images.length;k++)html+='<img src="'+r.images[k]+'" class="w-20 h-20 object-cover rounded-lg border cursor-pointer hover:opacity-80 transition review-img" onclick="openReviewLightbox(\''+r.images[k]+'\')">';html+='</div>'}if(r.admin_reply)html+='<div class="ml-11 mt-2 p-3 bg-gray-50 rounded-lg text-sm text-gray-500"><span class="font-medium">商家回复：</span>'+r.admin_reply+'</div>';var tagsHtml='';if(r.tags&&r.tags.length){tagsHtml='<div class="flex flex-wrap gap-1.5 mt-2">';for(var t=0;t<r.tags.length;t++)tagsHtml+='<span class="text-[11px] bg-gray-50 text-gray-500 px-2 py-0.5 rounded-full border border-gray-100">'+r.tags[t]+'</span>';tagsHtml+='</div>'}html=html.replace('</div></div>','</div>'+tagsHtml);var helpfulCount=r.helpful_count||r.helpful||0;html+='<div class="flex items-center gap-3 mt-3 pt-3 border-t border-gray-50"><button onclick="helpfulReview('+r.id+',this)" class="inline-flex items-center gap-1 text-xs text-gray-400 hover:text-primary-600 transition"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg><span>有帮助 <span class="helpful-count">('+helpfulCount+')</span></span></button></div>';html=html.replace('<div class="bg-white rounded-xl p-6 border border-gray-100">','<div class="review-item bg-white rounded-xl p-6 border border-gray-100" data-rating="'+(r.rating||0)+'" data-helpful="'+helpfulCount+'">');html+='</div>'}c.innerHTML=html;document.getElementById('review-toolbar').classList.remove('hidden')}else c.innerHTML='<div class="text-center py-12 text-gray-400"><svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg><p>暂无评价</p></div>'}catch(e){console.error(e)}}
async function submitReview(pId){if(!_token){window.location.href='/build/login?redirect='+encodeURIComponent(window.location.href);return}var el=document.getElementById('review-content');if(!el||!el.value||el.value.length<5){showToast('评价内容至少5个字');return}var isAnon=document.getElementById('review-anonymous')?document.getElementById('review-anonymous').checked:false;try{var r=await fetch('/api/products/reviews',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','Authorization':'Bearer '+_token},body:JSON.stringify({product_id:pId,rating:_currentRating,content:el.value,is_anonymous:isAnon,images:_reviewImages,tags:getSelectedTags()})});var d=await r.json();if(d.success){showToast(d.message||'评论已提交');el.value='';_reviewImages=[];renderReviewPreviews();var f=document.getElementById('review-form');if(f)f.classList.add('hidden');loadReviews(pId)}else showToast(d.message||'提交失败')}catch(e){showToast('提交失败，请重试')}}
function helpfulReview(id,btn){var c=btn.querySelector('.helpful-count');var n=parseInt((c?c.textContent.match(/\d+/):['0'])[0])||0;c.textContent='('+(n+1)+')';btn.classList.add('text-primary-600');btn.disabled=true;showToast('感谢您的反馈')}
function openReviewLightbox(src){var lb=document.getElementById('image-lightbox');var img=document.getElementById('lightbox-image');if(lb&&img){img.src=src;lb.classList.remove('hidden');lb.style.display='flex';document.body.style.overflow='hidden'}}
function openShareDialog(){var d=document.getElementById('share-dialog');if(d){d.classList.remove('hidden');d.style.display='flex';document.body.style.overflow='hidden'}}
function closeShareDialog(){var d=document.getElementById('share-dialog');if(d){d.classList.add('hidden');d.style.display='';document.body.style.overflow=''}}
function shareWechat(){closeShareDialog();showToast('请截图后打开微信扫码分享')}
function shareWeibo(){closeShareDialog();window.open('https://service.weibo.com/share/share.php?title='+encodeURIComponent(document.title)+'&url='+encodeURIComponent(window.location.href),'_blank','width=600,height=500')}
function shareCopyLink(){closeShareDialog();if(navigator.clipboard){navigator.clipboard.writeText(window.location.href).then(function(){showToast('链接已复制到剪贴板')}).catch(function(){showToast('复制失败，请手动复制')})}else{showToast('复制失败，请手动复制')}}
function toggleCompare(id,n,img,url,price){var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');var idx=-1;for(var i=0;i<items.length;i++){if(items[i].id===id){idx=i;break}}if(idx>=0){items.splice(idx,1);showToast('已移除对比')}else{if(items.length>=4){showToast('最多对比4个产品');return}items.push({id:id,name:n,image:img,url:url,price:price});showToast('已添加到对比')}sessionStorage.setItem('compare_items',JSON.stringify(items));var btn=document.getElementById('compare-btn-'+id);if(btn)btn.classList.toggle('text-amber-500',idx<0);updateCompareBar()}
function updateCompareBar(){var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');var bar=document.getElementById('compare-floating-bar');var count=document.getElementById('compare-bar-count');var container=document.getElementById('compare-bar-items');var link=document.getElementById('compare-bar-link');if(count)count.textContent=items.length;if(link){var ids=items.map(function(it){return it.id}).join(',');link.href='/compare-products?ids='+ids}if(bar){if(items.length>0){bar.classList.remove('hidden')}else{bar.classList.add('hidden')}}if(container){container.innerHTML='';items.forEach(function(item){var d=document.createElement('div');d.className='flex items-center gap-1.5 px-2 py-1 bg-gray-50 rounded-lg text-xs';d.innerHTML='<img src="'+item.image+'" class="w-6 h-6 rounded object-cover" onerror="this.style.display=\'none\'"><span class="text-gray-700 max-w-[80px] truncate">'+item.name+'</span><button onclick="toggleCompare('+item.id+')" class="text-gray-400 hover:text-red-500 ml-1">&times;</button>';container.appendChild(d)})}}
function goCompare(){var items=JSON.parse(sessionStorage.getItem('compare_items')||'[]');if(items.length<2){showToast('请至少选择2个产品进行对比');return}window.location.href='/compare-products?ids='+items.map(function(it){return it.id}).join(',')}
function saveCompareList(arr){sessionStorage.setItem('compare_items',JSON.stringify(arr));updateCompareBar();document.querySelectorAll('[id^="compare-btn-"]').forEach(function(b){b.classList.remove('text-amber-500')})}
// ─── 灯箱 ───
// ─── 灯箱缩放/平移/捏合 ───
var _zoomScale = 1, _panX = 0, _panY = 0;
var _isPanning = false, _panStartX, _panStartY;
var _pinchDist = 0;
function resetZoomPan() {
    _zoomScale = 1; _panX = 0; _panY = 0;
    var img = document.getElementById('lightbox-image');
    if (img) { img.style.transform = 'scale(1) translate(0,0)'; img.style.cursor = 'grab'; }
}
function startPan(e) {
    if (_zoomScale <= 1) return;
    _isPanning = true;
    _panStartX = e.clientX - _panX;
    _panStartY = e.clientY - _panY;
    document.getElementById('lightbox-image').style.cursor = 'grabbing';
}
function doPan(e) {
    if (!_isPanning) return;
    _panX = e.clientX - _panStartX;
    _panY = e.clientY - _panStartY;
    updateZoomPan();
}
function endPan() {
    _isPanning = false;
    var img = document.getElementById('lightbox-image');
    if (img) img.style.cursor = _zoomScale > 1 ? 'grab' : 'grab';
}
function startPinch(e) {
    if (e.touches.length === 2) {
        _pinchDist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
    }
}
function doPinch(e) {
    if (e.touches.length === 2) {
        e.preventDefault();
        var dist = Math.hypot(e.touches[0].clientX - e.touches[1].clientX, e.touches[0].clientY - e.touches[1].clientY);
        var newScale = _zoomScale * (dist / _pinchDist);
        _zoomScale = Math.max(0.5, Math.min(5, newScale));
        _pinchDist = dist;
        updateZoomPan();
    }
}
function endPinch() {}
// ─── 秒杀倒计时 ───
(function() {
    var els = document.querySelectorAll('.flash-countdown');
    if (!els.length) return;
    function tick() {
        var now = Math.floor(Date.now() / 1000);
        els.forEach(function(el) {
            var end = parseInt(el.getAttribute('data-end'));
            if (!end) return;
            var diff = Math.max(0, end - now);
            if (diff <= 0) { el.textContent = '已结束'; return; }
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            el.textContent = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        });
    }
    tick();
    setInterval(tick, 1000);
})();
function toggleZoom(e) {
    if (_zoomScale > 1) {
        _zoomScale = 1; _panX = 0; _panY = 0;
    } else {
        _zoomScale = 2.5;
        var rect = e.target.getBoundingClientRect();
        _panX = (e.clientX - rect.left - rect.width / 2) * -0.5;
        _panY = (e.clientY - rect.top - rect.height / 2) * -0.5;
    }
    updateZoomPan();
}
function updateZoomPan() {
    var img = document.getElementById('lightbox-image');
    if (img) img.style.transform = 'scale(' + _zoomScale + ') translate(' + _panX + 'px,' + _panY + 'px)';
}

function openLightbox(src){
    var lb = document.getElementById('image-lightbox');
    var img = document.getElementById('lightbox-image');
    if (lb && img) {
        resetZoomPan();
        // 数字索引转URL
        if (typeof src === 'number' && _imageList[src]) src = _imageList[src];
        if (!src) {
            // 获取当前主图的 src
            var curMain = document.querySelector('#main-image img');
            src = curMain ? curMain.src : null;
        }
        if (!src) {
            var firstThumb = document.querySelector('.gallery-thumb');
            if (firstThumb) src = firstThumb.getAttribute('data-src');
        }
        if (src) {
            img.src = src;
            switchImage(null, src);
        }
        lb.classList.remove('hidden');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // 更新计数器
        var counter = document.getElementById('lightbox-counter');
        if (counter && _imageList.length > 0) {
            counter.textContent = (_currentImageIndex + 1) + ' / ' + _imageList.length;
        }
    }
}
function closeLightbox(){
    var lb = document.getElementById('image-lightbox');
    if (lb) {
        lb.classList.add('hidden');
        lb.style.display = 'none';
        document.body.style.overflow = '';
    }
}

// ─── 灯箱键盘导航（只注册一次）───
if (!window._lightboxKeydownRegistered) {
    window._lightboxKeydownRegistered = true;
    document.addEventListener('keydown', function(e) {
        var lb = document.getElementById('image-lightbox');
        if (!lb || lb.classList.contains('hidden')) return;
        if (e.key === 'Escape') { closeLightbox(); }
        else if (e.key === 'ArrowLeft') { e.preventDefault(); prevImage(); }
        else if (e.key === 'ArrowRight') { e.preventDefault(); nextImage(); }
    });
}
document.addEventListener('DOMContentLoaded',function(){var btn=document.getElementById('detail-wishlist-btn');if(btn){var m=btn.getAttribute('onclick').match(/\d+/);if(m)checkWishlist(parseInt(m[0]))}updateCompareBar();initRecentlyViewed({{ $product->id }},'{{ str_replace("'","\\'",$product->name) }}','{{ $product->image_url ?: '' }}','{{ url('/products/'.$product->slug) }}','{{ $_lowPrice }}','{{ $_cycleLabel }}')});
// 页面加载后预加载评价数据（首次切换到评价Tab时再真正渲染）
var _reviewsLoaded=false;
// ═══════ 最近浏览 ═══════
function initRecentlyViewed(id,name,img,url,price,cycle){
  var KEY='huwutong_recently_viewed';var MAX=8;
  var items=JSON.parse(localStorage.getItem(KEY)||'[]');
  for(var i=0;i<items.length;i++){if(items[i].id===id){items.splice(i,1);break}}
  items.unshift({id:id,name:name,image:img,url:url,price:price,cycle:cycle||'',time:Date.now()});
  if(items.length>MAX)items=items.slice(0,MAX);
  localStorage.setItem(KEY,JSON.stringify(items));
  renderRecentlyViewed(items);
}
function renderRecentlyViewed(items){
  var c=document.getElementById('recently-viewed-container');
  var s=document.getElementById('recently-viewed-section');
  if(!c||!s)return;
  if(!items||items.length===0){s.style.display='none';return}
  s.style.display='';
  var grid=document.createElement('div');
  grid.className='grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4';
  grid.innerHTML=items.map(function(item){
    var ago=getTimeAgo(item.time);
    var imgHtml=item.image
      ?'<img src="'+item.image+'" alt="'+item.name+'" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" onerror="this.style.display=\'none\';this.parentNode.classList.add(\'rv-no-img\')" />'
      :'';
    var noImgCls=item.image?'':'rv-no-img';
    return '<div class="product-card bg-white rounded-xl border border-gray-100 overflow-hidden flex flex-col group relative hover:shadow-md transition-shadow">'
      +'<a href="'+item.url+'" class="block flex flex-col flex-1">'
      +'<div class="aspect-square bg-gradient-to-br from-primary-50 to-blue-50 overflow-hidden relative '+noImgCls+'">'+imgHtml
      +'<button onclick="event.preventDefault();event.stopPropagation();toggleCompare('+item.id+',\''+item.name.replace(/'/g,"\\'")+'\',\''+(item.image||'')+'\',\''+item.url+'\',\'¥'+(item.price||'0')+(item.cycle||'')+'\')" class="absolute top-10 right-2 w-7 h-7 rounded-full bg-white/90 hover:bg-white border border-gray-200 flex items-center justify-center transition shadow-sm z-10 compare-rp-btn" data-pid="'+item.id+'" title="加入对比"><svg class="w-3.5 h-3.5 text-gray-400 compare-rp-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></button>'
      +'<button onclick="event.preventDefault();event.stopPropagation();toggleWishlist('+item.id+', event)" class="absolute top-2 right-2 p-1.5 rounded-full bg-white/80 hover:bg-white transition shadow-sm z-10 wishlist-related-btn" data-product-id="'+item.id+'" title="收藏"><svg class="w-4 h-4 text-gray-300 hover:text-red-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg></button>'
      +'</div>'
      +'<div class="p-3 flex flex-col flex-1">'
      +'<h3 class="text-sm font-semibold text-gray-900 group-hover:text-primary-600 transition line-clamp-1">'+item.name+'</h3>'
      +'<div class="flex items-center gap-2 mt-1">'
      +'<span class="text-xs text-gray-400">'+ago+'</span>'
      +'</div>'
      +'<div class="mt-auto pt-2 flex items-center justify-start gap-1">'
      +priceHtml(item.price, item.cycle)
      +'<span class="ml-auto text-[10px] text-primary-500 bg-primary-50 px-1.5 py-0.5 rounded cycle-label">浏览</span>'
      +'</div>'
      +'</div></a></div>';
  }).join('');
  c.innerHTML='';
  c.appendChild(grid);
}
function priceHtml(p,cycle){
  cycle=cycle||'';
  if(p===undefined||p===null||p==='')return '<span class="text-xs text-gray-400">—</span>';
  var n=parseFloat(p);
  if(n===0)return '<span class="text-xs font-semibold text-green-600">免费</span>';
  return '<span class="text-sm font-bold text-gray-900">¥'+n.toFixed(2)+'</span><span class="text-[10px] text-gray-400 cycle-label">'+cycle+'</span>';
}
function getTimeAgo(t){
  var diff=Date.now()-t;
  if(diff<60000)return '刚刚';
  if(diff<3600000)return Math.floor(diff/60000)+'分钟前';
  if(diff<86400000)return Math.floor(diff/3600000)+'小时前';
  return Math.floor(diff/86400000)+'天前';
}
function clearRecentlyViewed(){
  localStorage.removeItem('huwutong_recently_viewed');
  renderRecentlyViewed([]);
  showToast('已清除浏览历史');
}
</script>



    <!-- 商品对比区域 -->

    <div id="share-dialog" class="fixed inset-0 z-[90] bg-black/40 hidden flex-col items-center justify-center" onclick="if(event.target===this)closeShareDialog()">

        <div class="bg-white rounded-2xl w-full max-w-sm mx-auto p-6 shadow-xl" onclick="event.stopPropagation()">

            <div class="flex items-center justify-between mb-6">

                <h3 class="text-lg font-bold text-gray-900">分享给好友</h3>

                <button onclick="closeShareDialog()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100 transition">&times;</button>

            </div>

            <div class="grid grid-cols-3 gap-4">

                <!-- 标题 -->

                <button onclick="shareWechat()" class="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-green-50 transition group">

                    <div class="w-14 h-14 rounded-full bg-green-100 flex items-center justify-center group-hover:bg-green-200 transition">

                        <svg class="w-7 h-7 text-green-600" viewBox="0 0 24 24" fill="currentColor"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 01.213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 00.167-.054l1.903-1.114a.864.864 0 01.717-.098 10.16 10.16 0 002.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178A1.17 1.17 0 014.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 01-1.162 1.178 1.17 1.17 0 01-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 01.598.082l1.584.926a.272.272 0 00.14.045c.133 0 .24-.11.24-.245 0-.06-.024-.12-.04-.178l-.325-1.233a.49.49 0 01.177-.553C23.028 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-7.062-6.122zm-2.18 2.681c.535 0 .969.44.969.982a.976.976 0 01-.969.983.976.976 0 01-.969-.983c0-.542.434-.982.97-.982zm4.845 0c.535 0 .969.44.969.982a.976.976 0 01-.969.983.976.976 0 01-.969-.983c0-.542.434-.982.97-.982z"/></svg>

                    </div>

                    <span class="text-sm text-gray-600 font-medium">微信</span>

                </button>

                <!-- 标题 -->

                <button onclick="shareWeibo()" class="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-red-50 transition group">

                    <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center group-hover:bg-red-200 transition">

                        <svg class="w-7 h-7 text-red-500" viewBox="0 0 24 24" fill="currentColor"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.739 5.443zM20.196 9.4a4.08 4.08 0 01-1.699-.718 4.066 4.066 0 01-1.628-2.352 4.097 4.097 0 01.131-2.347 4.069 4.069 0 011.627-2.354A4.01 4.01 0 0120.336.9c.466.074.908.257 1.294.533.384.275.699.637.924 1.055.222.42.35.882.373 1.355.025.474-.049.947-.218 1.387a4.066 4.066 0 01-1.627 2.354 4.023 4.023 0 01-1.886.816zm.773-4.773c-.275-.556-.784-.93-1.361-.998a1.88 1.88 0 00-1.508.488c-.437.395-.7.957-.727 1.553-.027.597.199 1.177.615 1.588a1.89 1.89 0 001.527.672 1.86 1.86 0 001.25-.531c.37-.365.577-.863.569-1.378a1.892 1.892 0 00-.365-1.394z"/></svg>

                    </div>

                    <span class="text-sm text-gray-600 font-medium">微博</span>

                </button>

                <!-- 产品特性 -->

                <button onclick="shareCopyLink()" class="flex flex-col items-center gap-2 p-4 rounded-xl hover:bg-gray-100 transition group">

                    <div class="w-14 h-14 rounded-full bg-gray-100 flex items-center justify-center group-hover:bg-gray-200 transition">

                        <svg class="w-7 h-7 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>

                    </div>

                    <span class="text-sm text-gray-600 font-medium">复制链接</span>

                </button>

            </div>

            <!-- 分享弹窗 -->

            <div id="wechat-qr-area" class="hidden mt-6 pt-6 border-t border-gray-100 text-center">

                <p class="text-sm text-gray-500 mb-3">分享到社交媒体或复制链接</p>

                <div id="wechat-qr-code" class="inline-block bg-white p-2 rounded-xl border border-gray-200">

                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode(url('/products/'.$product->slug)) }}" alt="QR Code" class="w-36 h-36">

                </div>

                <p class="text-xs text-gray-400 mt-2">选择您要分享的方式</p>

            </div>

        </div>

    </div>



    <!-- 页面加载状态区域 -->

    <div id="stock-notify-dialog" class="fixed inset-0 z-[90] bg-black/40 hidden items-end md:items-center justify-center" onclick="if(event.target===this)closeStockNotify()">

        <div class="bg-white rounded-t-2xl md:rounded-2xl w-full max-w-sm mx-auto p-6 shadow-xl" onclick="event.stopPropagation()">

            <div class="flex items-center justify-between mb-4">

                <h3 class="text-lg font-bold text-gray-900">包含模块</h3>

                <button onclick="closeStockNotify()" class="w-8 h-8 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-full hover:bg-gray-100 transition">&times;</button>

            </div>

            <p class="text-sm text-gray-500 mb-4" id="stock-notify-sku-name">该商品暂时缺货，留下联系方式，到货后我们将通知您</p>

            <div class="space-y-3">

                <div>

                    <label class="text-sm text-gray-600 mb-1 block">邮箱地址</label>

                    <input id="stock-notify-email" type="email" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="请输入邮箱地址" value="{{ auth()->user()?->email ?? '' }}">

                </div>

                <div>

                    <label class="text-sm text-gray-600 mb-1 block">手机号码</label>

                    <input id="stock-notify-phone" type="tel" class="w-full px-4 py-3 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-transparent" placeholder="请输入手机号码">

                </div>

                <button id="stock-notify-submit-btn" onclick="submitStockNotify()" class="w-full py-3 rounded-xl font-semibold bg-primary-600 text-white hover:bg-primary-700 transition text-sm">

                    提交通知

                </button>

                <p id="stock-notify-msg" class="text-sm text-center hidden"></p>

            </div>

        </div>

    </div>



    <!-- 商品对比区域 -->

    <div id="image-lightbox" class="fixed inset-0 z-[100] bg-black/90 hidden items-center justify-center" onclick="if(event.target===this)closeLightbox()">

        <button onclick="closeLightbox()" class="absolute top-4 right-4 w-12 h-12 flex items-center justify-center text-white/70 hover:text-white text-4xl z-10 rounded-full hover:bg-white/10 transition">&times;</button>

        <button onclick="event.stopPropagation();prevImage()" class="absolute left-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center text-white/60 hover:text-white text-5xl z-10 rounded-full hover:bg-white/10 transition opacity-0 md:opacity-100" id="lightbox-prev">&lsaquo;</button>

        <div id="lightbox-image-container" class="flex items-center justify-center w-full h-full overflow-hidden select-none cursor-grab active:cursor-grabbing" onmousedown="startPan(event)" onmousemove="doPan(event)" onmouseup="endPan()" onmouseleave="endPan()" ontouchstart="startPinch(event)" ontouchmove="doPinch(event)" ontouchend="endPinch()" ondblclick="toggleZoom(event)">

            <img id="lightbox-image" class="max-w-[95vw] max-h-[90vh] object-contain select-none pointer-events-none transition-transform duration-200" src="" alt="" draggable="false">

        </div>

        <button onclick="event.stopPropagation();nextImage()" class="absolute right-4 top-1/2 -translate-y-1/2 w-12 h-12 flex items-center justify-center text-white/60 hover:text-white text-5xl z-10 rounded-full hover:bg-white/10 transition opacity-0 md:opacity-100" id="lightbox-next">&rsaquo;</button>

        <div id="lightbox-counter" class="absolute bottom-8 left-1/2 -translate-x-1/2 text-white/50 text-sm font-mono"></div>

        <div id="lightbox-touch" class="absolute inset-0 z-[-1]"></div>

    </div>



    <!-- 页面浮动工具栏区域 -->

    <div id="mobile-sticky-bar" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-50 md:hidden translate-y-full transition-transform duration-300 shadow-[0_-4px_20px_rgba(0,0,0,0.1)] safe-bottom">

        <div class="flex items-center gap-3">

            <div class="shrink-0">

                <span class="text-lg font-bold text-red-500" id="sticky-price">¥{{ number_format($_lowPrice, 2) }}~{{ number_format($_highPrice, 2) }}</span>

            </div>

            <div class="flex-1 flex gap-2">

                <button onclick="addToCartFromSticky()" class="flex-1 py-2.5 rounded-lg font-semibold border-2 border-primary-600 text-primary-600 hover:bg-primary-50 transition text-sm text-center">

                    加入购物车

                </button>

                <a href="javascript:void(0)" onclick="buyNowFromSticky()" class="flex-1 py-2.5 rounded-lg font-semibold bg-primary-600 text-white hover:bg-primary-700 transition text-sm text-center block">

                    立即购买

                </a>

            </div>

        </div>

    </div>



    <!-- 页面快捷键导航 -->

    <div id="compare-floating-bar" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-3 z-30 hidden shadow-[0_-4px_20px_rgba(0,0,0,0.1)]">

        <div class="max-w-7xl mx-auto flex items-center gap-3">

            <div class="flex items-center gap-1 text-sm text-gray-500 shrink-0">

                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>

                <span>对比 (<span id="compare-bar-count">0</span>)</span>

            </div>

            <div id="compare-bar-items" class="flex-1 flex items-center gap-2 overflow-x-auto"></div>

            <a id="compare-bar-link" href="javascript:void(0)" onclick="goCompare()" class="shrink-0 px-4 py-1.5 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700 transition">开始对比</a>

            <button onclick="saveCompareList([])" class="shrink-0 text-xs text-gray-400 hover:text-red-500 transition">清空</button>

        </div>

    </div>



    <!-- 聊天窗口区域 -->

    <div id="demo-dialog" class="fixed inset-0 z-[80] hidden flex items-center justify-center p-4 bg-black/40" onclick="if(event.target===this)closeDemoDialog()">

        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

            <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between z-10">

                <h3 class="text-lg font-bold text-gray-900">预约演示</h3>

                <button onclick="closeDemoDialog()" class="w-8 h-8 rounded-full hover:bg-gray-100 flex items-center justify-center text-gray-400 hover:text-gray-600 transition">

                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>

                </button>

            </div>

            <div class="p-6">

                <div id="demo-loading" class="text-center py-12">

                    <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4"></div>

                    <p class="text-gray-500">加载中...</p>

                </div>

                <div id="demo-content" class="hidden">

                    <div id="demo-platforms" class="flex gap-2 mb-6 flex-wrap"></div>

                    <div class="overflow-x-auto">

                        <table class="w-full text-sm border-collapse">

                            <thead>

                                <tr class="bg-gray-50 border-b border-gray-200">

                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">平台</th>

                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">演示站点</th>

                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">演示账号</th>

                                    <th class="text-left py-3 px-4 font-semibold text-gray-700">演示密码</th>

                                </tr>

                            </thead>

                            <tbody id="demo-table-body"></tbody>

                        </table>

                    </div>

                    <div id="demo-qr-section" class="hidden mt-6 flex gap-6 justify-center flex-wrap">

                        <div id="demo-qr-list" class="flex gap-6 flex-wrap justify-center"></div>

                    </div>

                    <div class="text-center mt-4">

                        <button id="demo-copy-domain" class="px-6 py-2 bg-primary-600 text-white text-sm rounded-lg hover:bg-primary-700 transition font-medium">复制演示地址</button>

                    </div>

                </div>

                <div id="demo-empty" class="hidden text-center py-12 text-gray-400">

                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>

                    <p>暂无演示内容</p>

                </div>

            </div>

        </div>

    </div>



    <!-- 联系卖家（跳转 IM） -->
    @if(($_siteSettings['service_chat_enabled'] ?? $_siteSettings['chat_widget_enabled'] ?? '1') === '1' && $product->creator)
    <button onclick="openSellerChat({{ $product->creator->id }},{{ $product->id }})" class="fixed bottom-40 md:bottom-24 right-4 md:right-8 w-14 h-14 rounded-full bg-primary-600 text-white flex items-center justify-center z-50 shadow-lg hover:bg-primary-700 transition-all duration-300" aria-label="联系卖家" title="联系卖家">
        <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
    </button>
    @endif

    <!-- 页面加载状态区域 -->

    <button id="back-to-top" class="fixed bottom-28 md:bottom-4 right-4 md:right-8 w-12 h-12 rounded-full bg-white dark-bg-card shadow-lg border border-gray-200 dark-border flex items-center justify-center text-gray-500 dark-text-sec hover:text-primary-600 hover:border-primary-300 hover:shadow-primary-100 transition-all duration-300 z-[60] opacity-0 pointer-events-none" aria-label="回到顶部">

        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>

    </button>



    <script>

    // 聊天消息处理

    function addToCartFromSticky() {

        const firstSku = document.querySelector('.plan-card');

        if (firstSku) {

            const btn = firstSku.querySelector('button');

            if (btn) { btn.click(); return; }

        }

        alert('功能开发中');

    }



    function buyNow(skuId) {
        if (!_token) { window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href); return; }
        fetch('/api/cart/quick-buy', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Authorization': 'Bearer ' + _token },
            body: JSON.stringify({ sku_id: skuId, quantity: 1 })
        }).then(function(r) { return r.json(); }).then(function(d) {
            if (d.success && d.data && d.data.order && d.data.order.id) {
                window.location.href = '/build/checkout?order_id=' + d.data.order.id + '&sku_id=' + skuId;
            } else if (d.success && d.data && d.data.cart_id) {
                window.location.href = '/build/checkout?sku_id=' + skuId;
            } else {
                showToast(d.message || '操作失败');
            }
        }).catch(function() { showToast('网络错误'); });
    }

    function buyNowFromSticky() {
        var firstSku = document.querySelector('.plan-card');
        if (firstSku) {
            var btn = firstSku.querySelector('button');
            if (btn) { var m = btn.getAttribute('onclick').match(/\d+/); if (m) { buyNow(parseInt(m[0])); return; } }
        }
        alert('功能开发中');
    }



    // 演示弹窗初始化

    let _demoData = null;

    function openDemoDialog() {

        const dialog = document.getElementById('demo-dialog');

        if (!dialog) return;

        dialog.classList.remove('hidden');

        dialog.classList.add('flex');

        document.body.style.overflow = 'hidden';



        // Show loading

        document.getElementById('demo-loading').classList.remove('hidden');

        document.getElementById('demo-content').classList.add('hidden');

        document.getElementById('demo-empty').classList.add('hidden');



        // Fetch demo data

        fetch('/api/products/{{ $product->id }}/demo', {

            headers: { 'Accept': 'application/json' }

        })

        .then(r => r.json())

        .then(res => {

            document.getElementById('demo-loading').classList.add('hidden');

            if (!res.success || !res.data.demos || res.data.demos.length === 0) {

                document.getElementById('demo-empty').classList.remove('hidden');

                return;

            }

            _demoData = res.data;

            renderDemoData(res.data);

            document.getElementById('demo-content').classList.remove('hidden');

        })

        .catch(() => {

            document.getElementById('demo-loading').classList.add('hidden');

            document.getElementById('demo-empty').classList.remove('hidden');

        });

    }



    function closeDemoDialog() {

        const dialog = document.getElementById('demo-dialog');

        if (!dialog) return;

        dialog.classList.add('hidden');

        dialog.classList.remove('flex');

        document.body.style.overflow = '';

    }



    function renderDemoData(data) {

        // Platform tabs

        const platformsDiv = document.getElementById('demo-platforms');

        platformsDiv.innerHTML = data.demos.map(d =>

            '<span class="px-3 py-1 rounded-full text-xs font-medium bg-primary-50 text-primary-600 border border-primary-200">' +

            escHtml(d.platform) + '</span>'

        ).join('');



        // Table rows

        const tbody = document.getElementById('demo-table-body');

        tbody.innerHTML = data.demos.map(d => {

            const siteUrl = d.site_url

                ? '<a href="' + escHtml(d.site_url) + '" target="_blank" class="text-primary-600 hover:text-primary-700 underline">' + escHtml(d.site_url) + '</a>'

                : '<span class="text-gray-400">-</span>';

            return '<tr class="border-b border-gray-100 hover:bg-gray-50">' +

                '<td class="py-3 px-4 font-medium text-gray-900">' + escHtml(d.platform) + '</td>' +

                '<td class="py-3 px-4">' + siteUrl + '</td>' +

                '<td class="py-3 px-4 text-gray-600 font-mono text-xs">' + escHtml(d.account || '-') + '</td>' +

                '<td class="py-3 px-4 text-gray-600 font-mono text-xs">' + escHtml(d.password || '-') + '</td>' +

                '</tr>';

        }).join('');



        // ??????

        const copyBtn = document.getElementById('demo-copy-domain');

        if (copyBtn) {

            copyBtn.addEventListener('click', function() {

                const firstUrl = data.demos.find(d => d.site_url)?.site_url;

                if (firstUrl) {

                    const domain = new URL(firstUrl).hostname;

                    navigator.clipboard.writeText(domain).then(() => {

                        copyBtn.textContent = '已复制';

                        setTimeout(() => { copyBtn.textContent = '复制链接'; }, 2000);

                    });

                }

            });

        }



        // QR codes - 二维码生成

        const qrList = document.getElementById('demo-qr-list');

        qrList.innerHTML = '';

        if (data.demo_images && data.demo_images.length > 0) {

            document.getElementById('demo-qr-section').classList.remove('hidden');

            data.demo_images.forEach(function(img) {

                if (!img.url) return;

                const div = document.createElement('div');

                div.className = 'text-center';

                div.innerHTML =

                    '<p class="text-sm text-gray-500 mb-2">' + escHtml(img.label || '图片') + '</p>' +

                    '<img src="' + escHtml(img.url) + '" alt="' + escHtml(img.label || '图片') + '" class="w-32 h-32 rounded-lg border border-gray-200" onerror="this.style.display=\'none\'">';

                qrList.appendChild(div);

            });

            if (qrList.children.length === 0) {

                document.getElementById('demo-qr-section').classList.add('hidden');

            }

        } else {

            document.getElementById('demo-qr-section').classList.add('hidden');

        }

    }



    function escHtml(str) {

        if (!str) return '';

        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');

    }

        // ═══════ 联系卖家（跳转 IM）═══════
    function openSellerChat(sellerId, productId) {
        var url = '/build/user-chat?seller_id=' + encodeURIComponent(sellerId);
        if (productId) url += '&product_id=' + encodeURIComponent(productId);
        if (!_token) {
            window.location.href = '/build/login?redirect=' + encodeURIComponent(url);
            return;
        }
        window.location.href = url;
    }

</script>

</body>

</html>

