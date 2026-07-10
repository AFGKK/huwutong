<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="互物通定价- 从免费版到企业版，提供灵活的定价方案满足不同规模团队的需求。免费版永久免费，专业版付¥299/月起">
    <meta property="og:title" content="互物通定价- 企业级授权管理">
    <meta property="og:description" content="透明定价，从免费版到企业版，满足不同规模团队的需求">
    <link rel="canonical" href="{{ url('/pricing') }}">
    <title>定价 - 互物通| 企业级授权管理系统</title>
    @include('public.partials.tracking')

    @vite('resources/css/public.css')
    <style>
        html { scroll-behavior: smooth; }
        .plan-card { transition: all 0.3s ease; min-width: 280px; }
        @media (max-width: 380px) { .plan-card { min-width: 260px; } }
        .plan-card.popular { border-color: #3b82f6; transform: scale(1.03); }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(0,0,0,0.15); }
        .plan-card.popular:hover { transform: scale(1.03) translateY(-6px); }
        /* Tooltip */
        .feature-row td:first-child .group { position: relative; }
        .feature-row td:first-child .group .absolute { visibility: hidden; }
        .feature-row td:first-child .group:hover .absolute { visibility: visible; }
        /* 移动端横向滑动 */
        #plans-container { scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
        #plans-container::-webkit-scrollbar { display: none; }
        #plans-container > .plan-card { scroll-snap-align: start; }
        @media (max-width: 767px) {
            #plans-container { display: flex !important; flex-wrap: nowrap !important; gap: 1rem; overflow-x: auto; -webkit-overflow-scrolling: touch; scroll-snap-type: x mandatory; padding: 0.5rem 1rem 1rem; margin: 0 -1rem; }
            #plans-container > .plan-card { flex: 0 0 280px; scroll-snap-align: start; }
        }
        @media (max-width: 380px) {
            #plans-container > .plan-card { flex: 0 0 260px; }
        }
        /* 粘性定价栏 */
        #sticky-pricing-bar { transition: transform 0.3s ease, opacity 0.3s ease; z-index: 40; }
        #sticky-pricing-bar.hidden-sticky { transform: translateY(-100%); opacity: 0; }
        #comparison-table { border-collapse: separate; border-spacing: 0; }
        #comparison-table thead th { position: sticky; top: 80px; z-index: 20; }
        #comparison-table thead th:first-child { z-index: 21; border-top-left-radius: 12px; }
        #comparison-table thead th:last-child { border-top-right-radius: 12px; }
        #comparison-table tbody tr:last-child td:first-child { border-bottom-left-radius: 12px; }
        #comparison-table tbody tr:last-child td:last-child { border-bottom-right-radius: 12px; }
        /* 价格切换动画 */
        .price-animate { animation: pricePop 0.35s ease-out; }
        @keyframes pricePop { 0% { transform: scale(0.8); opacity: 0.3; } 50% { transform: scale(1.08); } 100% { transform: scale(1); opacity: 1; } }
        /* 用量计算器 */
        #calc-products, #calc-activations { -webkit-appearance: none; appearance: none; height: 8px; background: #dbeafe; border-radius: 4px; outline: none; cursor: pointer; }
        #calc-products::-webkit-slider-thumb, #calc-activations::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 22px; height: 22px; border-radius: 50%; background: #2563eb; cursor: pointer; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(37,99,235,0.3); }
        #calc-products::-moz-range-thumb, #calc-activations::-moz-range-thumb { width: 22px; height: 22px; border-radius: 50%; background: #2563eb; cursor: pointer; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(37,99,235,0.3); }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.nav')

    <!-- ─── 定价头部 ─── -->
    <section class="pt-24 pb-16 md:pb-20 bg-white relative overflow-hidden">
        <!-- 背景装饰 -->
        <div class="absolute inset-0 pointer-events-none opacity-[0.03]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-primary-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-blue-400 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <!-- 面包屑导航 -->
            <nav class="flex items-center gap-1.5 text-sm mb-8" style="color:rgba(107,114,128,0.8)">
                <a href="{{ url('/') }}" class="hover:text-primary-600 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    首页
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-gray-700 font-medium">定价</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">简单透明的定价</h1>
                <p class="text-lg text-gray-600 max-w-2xl mx-auto mb-8">从独立开发者到跨国企业，找到适合您的方案。所有套餐均包含 14 天免费试用。</p>
                <!-- 计费切换（增强） -->
                <div class="inline-flex items-center bg-gray-100 rounded-full p-1 shadow-sm">
                    <button id="mo-btn" class="px-6 py-2.5 rounded-full font-medium transition-all duration-200 bg-white shadow-sm text-gray-900" onclick="switchBilling('monthly')">
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>月度</span>
                    </button>
                    <button id="yr-btn" class="px-6 py-2.5 rounded-full font-medium transition-all duration-200 text-gray-500 hover:text-gray-900" onclick="switchBilling('yearly')">
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>年度 <span class="ml-1 text-xs bg-green-100 text-green-700 font-bold px-2 py-0.5 rounded-full">省20%</span></span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 用量计算器 ─── -->
    <section class="py-6 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border border-blue-100 p-6 md:p-8">
                <button onclick="toggleCalculator()" class="w-full flex items-center justify-between text-left">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="text-base font-semibold text-gray-900">📊 用量计算器</span>
                        <span class="text-xs text-gray-400 font-normal">拖动滑块估算您的费用</span>
                    </div>
                    <svg id="calc-arrow" class="w-5 h-5 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="calc-panel" class="hidden mt-6 space-y-6">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">产品数量</label>
                            <span class="text-sm font-bold text-primary-600" id="calc-products-display">5</span>
                        </div>
                        <input type="range" min="1" max="100" value="5" oninput="updateCalculator()" id="calc-products" class="w-full">
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>1</span><span>10</span><span>50</span><span>100</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-gray-700">月度激活数</label>
                            <span class="text-sm font-bold text-primary-600" id="calc-activations-display">1,000</span>
                        </div>
                        <input type="range" min="100" max="100000" value="1000" step="100" oninput="updateCalculator()" id="calc-activations" class="w-full">
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>100</span><span>1K</span><span>10K</span><span>100K</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-blue-100">
                        <div class="text-xs font-medium text-gray-500 mb-3">推荐方案</div>
                        <div id="calc-result" class="flex items-center justify-between">
                            <div>
                                <span id="calc-plan-name" class="text-lg font-bold text-gray-900">基础版</span>
                                <span id="calc-plan-desc" class="text-sm text-gray-500 ml-2">适合您的规模</span>
                            </div>
                            <div class="text-right">
                                <span id="calc-plan-price" class="text-2xl font-extrabold text-primary-600">¥99</span>
                                <span class="text-sm text-gray-500" id="calc-plan-period">/月</span>
                                <div id="calc-plan-yearly" class="text-xs text-green-600 hidden"></div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-gray-100 grid grid-cols-3 gap-3 text-center text-xs">
                            <div>
                                <div class="font-semibold text-gray-900" id="calc-p-products">5</div>
                                <div class="text-gray-500">产品</div>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900" id="calc-p-activations">1,000</div>
                                <div class="text-gray-500">激活/月</div>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-900" id="calc-p-total">¥1,188</div>
                                <div class="text-gray-500">年付/年</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 定价卡片 ─── -->
    <section class="pb-16 pt-2 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div id="plans-container" class="grid md:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-8 max-w-6xl mx-auto">
                @forelse($plans as $plan)
                @php
                    $isPopular = ($plan['badge'] ?? '') === 'popular';
                    $monthlyPrice = (float)($plan['price_monthly'] ?? 0);
                    $yearlyPrice = (float)($plan['price_yearly'] ?? 0);
                    $features = $plan['features'] ?? [];
                    $limits = $plan['limits'] ?? [];
                @endphp
                <div class="plan-card rounded-2xl border-2 {{ $isPopular ? 'border-primary-500 popular shadow-xl shadow-primary-100' : 'border-gray-100' }} bg-white p-8 flex flex-col relative"
                     data-slug="{{ $plan['slug'] }}"
                     data-price-monthly="{{ $monthlyPrice }}"
                     data-price-quarterly="{{ (float)($plan['price_quarterly'] ?? 0) }}"
                     data-price-semi-annually="{{ (float)($plan['price_semi_annually'] ?? 0) }}"
                     data-price-yearly="{{ $yearlyPrice }}">
                    @if($isPopular)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 text-white text-xs font-bold px-5 py-1 rounded-full shadow-lg flex items-center gap-1" style="background:linear-gradient(135deg,#fbbf24,#f59e0b)">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        最受欢迎
                    </div>
                    @endif
                    <h3 class="text-xl font-bold text-gray-900 mt-2">{{ $plan['name'] }}</h3>
                    <p class="text-sm text-gray-500 mt-1 mb-5">{{ $plan['description'] ?? '' }}</p>
                    @if($monthlyPrice > 0)
                    <div class="mb-6">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-gray-900" id="price-{{ $plan['slug'] }}">¥{{ number_format($monthlyPrice) }}</span>
                            <span class="text-gray-500" id="period-{{ $plan['slug'] }}">/月</span>
                        </div>
                        @if($yearlyPrice > 0 && $yearlyPrice < $monthlyPrice)
                        <div id="yearly-hint-{{ $plan['slug'] }}" class="hidden text-xs text-green-600 mt-1">¥{{ number_format($yearlyPrice) }}/月× 12，<strong class="text-green-700">省 ¥{{ number_format(($monthlyPrice - $yearlyPrice) * 12) }}/年</strong></div>
                        @endif
                        @if(($plan['trial_days'] ?? 0) > 0)
                        <div class="text-xs text-primary-600 mt-1">{{ $plan['trial_days'] }} 天免费试用</div>
                        @endif
                    </div>
                    @else
                    <div class="mb-6">
                        <span class="text-4xl font-extrabold text-gray-900">¥0</span>
                        <span class="text-gray-500">/永久</span>
                    </div>
                    @endif
                    <ul class="space-y-3 text-sm text-gray-600 flex-1 mb-8">
                        @forelse($features as $feature)
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-green-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ $feature }}
                        </li>
                        @empty
                        <li class="text-gray-400 italic">暂无功能说明</li>
                        @endforelse
                    </ul>
                    <a href="/build/register{{ $monthlyPrice > 0 ? '?redirect=/plans' : '' }}" class="block w-full text-center py-3 rounded-xl font-semibold {{ $isPopular ? 'bg-gradient-to-r from-primary-600 to-blue-600 text-white hover:from-primary-700 hover:to-blue-700 shadow-lg shadow-primary-200' : 'bg-primary-600 text-white hover:bg-primary-700' }} transition text-sm">
                        {{ $monthlyPrice > 0 ? '立即订阅' : '免费开始' }}
                        @if($isPopular) <span class="text-primary-200 ml-1">→</span> @endif
                    </a>
                </div>
                @empty
                <div class="col-span-4 text-center py-16 text-gray-400">
                    <p class="text-5xl mb-4">📋</p>
                    <p>暂无定价方案</p>
                </div>
                @endforelse
            </div>
            <!-- 移动端滑动提示 -->
            <div class="md:hidden text-center text-xs text-gray-400 mt-4">
                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg> 左右滑动查看更多方案 <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg></span>
            </div>
        </div>
    </section>

    <!-- ─── 客户 Logo 墙 ─── -->
    <section class="py-12 bg-white border-t border-gray-100 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider mb-2">Trusted by 500+</p>
            <p class="text-lg text-gray-400 mb-8">受到全球 10,000+ 企业的信赖</p>
            <div class="flex flex-wrap items-center justify-center gap-x-12 gap-y-6 opacity-60">
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-50 flex items-center justify-center text-xl font-bold text-blue-600 border border-blue-100">TC</div>
                    <span class="text-xs text-gray-500">TechCorp</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-purple-50 to-pink-50 flex items-center justify-center text-xl font-bold text-purple-600 border border-purple-100">DF</div>
                    <span class="text-xs text-gray-500">DataFlow</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-green-50 to-emerald-50 flex items-center justify-center text-xl font-bold text-green-600 border border-green-100">CB</div>
                    <span class="text-xs text-gray-500">CloudBase</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-amber-50 to-orange-50 flex items-center justify-center text-xl font-bold text-amber-600 border border-amber-100">SW</div>
                    <span class="text-xs text-gray-500">SoftWare</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-50 to-red-50 flex items-center justify-center text-xl font-bold text-rose-600 border border-rose-100">AI</div>
                    <span class="text-xs text-gray-500">AIStudio</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-50 to-sky-50 flex items-center justify-center text-xl font-bold text-cyan-600 border border-cyan-100">NC</div>
                    <span class="text-xs text-gray-500">NetCore</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-teal-50 to-green-50 flex items-center justify-center text-xl font-bold text-teal-600 border border-teal-100">FT</div>
                    <span class="text-xs text-gray-500">FinTech</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-violet-50 to-purple-50 flex items-center justify-center text-xl font-bold text-violet-600 border border-violet-100">MC</div>
                    <span class="text-xs text-gray-500">MediCore</span>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 功能对比矩阵 ─── -->
    <section id="comparison" class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">功能对比</h2>
            <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table id="comparison-table" class="w-full text-sm" style="table-layout:fixed">
                    <thead>
                        <tr>
                            <th class="text-left font-semibold text-gray-700" style="width:240px;padding:14px 16px;background:#fff;position:sticky;top:80px;z-index:21">功能</th>
                            <th class="text-center" style="width:auto;padding:14px 8px;background:#fff;position:sticky;top:80px;z-index:20">
                                <div class="font-semibold text-gray-600 text-xs">免费版</div>
                                <div class="text-base font-bold text-gray-900 price-display" data-plan="free">¥0</div>
                                <div class="text-[10px] text-gray-400">永久免费</div>
                                <a href="/build/register" class="mt-1.5 inline-block text-xs bg-primary-600 text-white px-2.5 py-1 rounded-lg hover:bg-primary-700 transition font-medium">免费开始</a>
                            </th>
                            <th class="text-center" style="width:auto;padding:14px 8px;background:#fff;position:sticky;top:80px;z-index:20">
                                <div class="font-semibold text-gray-600 text-xs">基础版</div>
                                <div class="text-base font-bold text-gray-900 mt-0.5 price-display" id="sticky-basic-price" data-plan="basic">¥99</div>
                                <div class="text-[10px] text-gray-400" id="sticky-basic-period">/月</div>
                                <a href="/build/register?redirect=/plans" class="mt-1.5 inline-block text-xs bg-primary-600 text-white px-2.5 py-1 rounded-lg hover:bg-primary-700 transition font-medium">订阅</a>
                            </th>
                            <th class="text-center" style="width:auto;padding:14px 8px;background:#eff6ff;position:sticky;top:80px;z-index:20">
                                <div class="font-semibold" style="color:#2563eb;font-size:12px">专业版</div>
                                <div class="text-base font-bold text-gray-900 mt-0.5 price-display" id="sticky-pro-price" data-plan="pro">¥299</div>
                                <div class="text-[10px]" style="color:#6b7280" id="sticky-pro-period">/月</div>
                                <a href="/build/register?redirect=/plans" class="mt-1.5 inline-block text-xs text-white px-2.5 py-1 rounded-lg font-medium" style="background:linear-gradient(135deg,#2563eb,#1d4ed8);box-shadow:0 1px 3px rgba(37,99,235,0.3)">订阅</a>
                            </th>
                            <th class="text-center" style="width:auto;padding:14px 8px;background:#fff;position:sticky;top:80px;z-index:20">
                                <div class="font-semibold text-gray-600 text-xs">企业版</div>
                                <div class="text-base font-bold text-gray-900 mt-0.5 price-display" id="sticky-ent-price" data-plan="ent">¥999</div>
                                <div class="text-[10px] text-gray-400" id="sticky-ent-period">/月</div>
                                <a href="/build/register?redirect=/plans" class="mt-1.5 inline-block text-xs bg-primary-600 text-white px-2.5 py-1 rounded-lg hover:bg-primary-700 transition font-medium">订阅</a>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @php
                        $features = [
                            ['label' => '产品数量',        'free' => '1 个',       'basic' => '5 个',       'pro' => '无限',      'ent' => '无限',   'tip' => '您可以在平台上发布管理的软件产品数量'],
                            ['label' => '设备激活数',      'free' => '100 个',     'basic' => '1,000 个',  'pro' => '无限',      'ent' => '无限',   'tip' => '每月允许的终端设备激活总数（License 激活）'],
                            ['label' => 'API 限流',        'free' => '60/分钟',    'basic' => '600/分钟',  'pro' => '3,000/分钟', 'ent' => '10,000/分钟', 'tip' => 'API 请求频率限制，超出将暂时被限流'],
                            ['label' => 'API Key',         'free' => '5 个',       'basic' => '20 个',     'pro' => '100 个',    'ent' => '无限',   'tip' => '可创建的 API 密钥数量，用于 SDK 集成和身份验证'],
                            ['label' => '团队成员',        'free' => '1 人',       'basic' => '5 人',      'pro' => '20 人',     'ent' => '无限',   'tip' => '可添加到团队协作的成员数量'],
                            ['label' => 'RBAC 权限管理',    'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '基于角色的访问控制（RBAC），精细化权限分配'],
                            ['label' => 'Webhook',         'free' => '—',          'basic' => '✓',         'pro' => '✓',         'ent' => '✓',     'tip' => '支持 Webhook 回调通知，实时获取 License 事件'],
                            ['label' => 'Webhook 重试/过滤','free' => '—',          'basic' => '—',         'pro' => '重试+过滤',  'ent' => '完整',    'tip' => 'Webhook 自动重试、事件过滤、回放及死信监控'],
                            ['label' => '客户 Portal',     'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '为客户提供自助门户，查看和管理他们的 License'],
                            ['label' => '多币种定价',       'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '支持 CNY/USD/EUR 等多币种商品定价与结算'],
                            ['label' => '自定义域名',      'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '使用自有域名托管 License 验证服务'],
                            ['label' => '多语言 SDK',      'free' => '3 种',       'basic' => '6 种',      'pro' => '6 种',      'ent' => '6 种',   'tip' => '支持多种编程语言的 SDK（PHP/Node/Python/Java/Go/.NET）'],
                            ['label' => '离线授权',        'free' => '—',          'basic' => '✓',         'pro' => '✓',         'ent' => '✓',     'tip' => '支持离线环境下的 License 生成和验证'],
                            ['label' => '设备指纹',        'free' => '—',          'basic' => '✓',         'pro' => '✓',         'ent' => '✓',     'tip' => '基于硬件特征的设备唯一标识，防止 License 滥用'],
                            ['label' => '席位池浮动',      'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => 'License 在团队设备间动态分配，不绑定固定设备'],
                            ['label' => 'OEM 白标',        'free' => '—',          'basic' => '—',         'pro' => '—',         'ent' => '✓',     'tip' => '去除互物通品牌，以您的品牌呈现 License 管理界面'],
                            ['label' => 'SSO/SAML',        'free' => '—',          'basic' => '—',         'pro' => '—',         'ent' => '✓',     'tip' => '支持 SAML 2.0 / OIDC 单点登录，集成企业身份认证'],
                            ['label' => '审计日志',        'free' => '—',          'basic' => '—',         'pro' => '—',         'ent' => '✓',     'tip' => '完整的操作审计日志，满足合规和安全审查需求'],
                            ['label' => 'AI 智能分析',     'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '基于 AI 的 License 使用分析和异常检测'],
                            // ── IM 即时通讯 ──
                            ['label' => '在线客服',        'free' => '✓',          'basic' => '✓',         'pro' => '✓',         'ent' => '✓',     'tip' => '网站即时通讯（Live Chat），访客对话与消息管理'],
                            ['label' => 'AI 智能客服',     'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => 'AI 驱动的智能回复、RAG 知识库问答、自动回复规则'],
                            ['label' => '人工转接',        'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => 'AI 自动转人工客服（Handoff），支持置信度阈值/超时策略'],
                            ['label' => '快捷回复',        'free' => '—',          'basic' => '10 条',    'pro' => '100 条',    'ent' => '500 条', 'tip' => '预设快捷回复模板（Canned Replies），提升客服效率'],
                            ['label' => '客服组/部门',     'free' => '—',          'basic' => '—',         'pro' => '5 个',      'ent' => '无限',   'tip' => '创建客服分组/部门，实现多团队协作和会话分配'],
                            ['label' => 'IM 通知集成',     'free' => '—',          'basic' => '—',         'pro' => '✓',         'ent' => '✓',     'tip' => '集成 Slack、钉钉、企业微信、飞书，实时通知 IM 事件'],
                            ['label' => 'SLA 保障',        'free' => '—',          'basic' => '99.9%',    'pro' => '99.95%',   'ent' => '99.99%', 'tip' => '服务可用性等级保障（SLA），确保平台稳定性'],
                            ['label' => '数据导出',        'free' => '—',          'basic' => '—',         'pro' => 'CSV',       'ent' => 'CSV+JSON', 'tip' => '导出 License、审计日志、客户数据为 CSV 或 JSON 格式'],
                            ['label' => '试用管理',         'free' => '7 天',       'basic' => '14 天',    'pro' => '30 天',     'ent' => '定制',   'tip' => 'Trial 试用授权管理，支持限制/裁剪/到期停用/一键转正'],
                            ['label' => '专属客户经理',    'free' => '—',          'basic' => '—',         'pro' => '—',         'ent' => '✓',     'tip' => '配备专属客户成功经理，提供一对一技术支持'],
                            ['label' => '私有化部署',      'free' => '—',          'basic' => '—',         'pro' => '—',         'ent' => '✓',     'tip' => '支持在您自己的服务器上部署，数据完全私有化'],
                        ];
                        @endphp
                        @foreach($features as $index => $f)
                        <tr class="feature-row" @if($index % 2 === 0) style="background:#f8fafc" @endif>
                            <td class="py-3 px-4 font-medium text-gray-700" style="min-width:240px;overflow:hidden">
                                <span class="inline-flex items-center gap-1 group relative cursor-help">
                                    {{ $f['label'] }}
                                    <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-primary-400 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <!-- Tooltip -->
                                    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg shadow-lg whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 max-w-[260px] whitespace-normal">
                                        {{ $f['tip'] }}
                                        <span class="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-gray-900"></span>
                                    </span>
                                </span>
                            </td>
                            <td class="text-center py-3 px-3 text-sm text-gray-600">{{ $f['free'] }}</td>
                            <td class="text-center py-3 px-3 text-sm text-gray-600">{{ $f['basic'] }}</td>
                            <td class="text-center py-3 px-3 text-sm font-medium" style="background:#eff6ff;color:#1e40af">{{ $f['pro'] }}</td>
                            <td class="text-center py-3 px-3 text-sm text-gray-600">{{ $f['ent'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- ─── 企业联系 ─── -->
    <section id="enterprise" class="py-16 md:py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-primary-600 to-blue-700 rounded-2xl p-8 md:p-12 text-white text-center">
                <h2 class="text-2xl md:text-3xl font-bold mb-4">需要企业定制方案？</h2>
                <p class="text-primary-100 mb-8 max-w-lg mx-auto">超过 500 名员工？需要定制合同、SLA 或专属部署方案？联系我们的企业团队。</p>
                <div class="max-w-md mx-auto space-y-4 text-left">
                    <form id="enterprise-form" onsubmit="submitEnterpriseForm(event)" class="space-y-4">
                        <input type="text" name="company" placeholder="公司名称 *" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-primary-300">
                        <input type="text" name="name" placeholder="您的姓名 *" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-primary-300">
                        <input type="email" name="email" placeholder="工作邮箱 *" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-primary-300">
                        <input type="tel" name="phone" placeholder="手机号（选填）" class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-primary-300">
                        <select name="employees" class="w-full px-4 py-3 rounded-lg text-gray-900 border-0 focus:ring-2 focus:ring-primary-300">
                            <option value="">员工规模（选填）</option>
                            <option value="1-50">1-50 人</option>
                            <option value="51-200">51-200 人</option>
                            <option value="201-1000">201-1000 人</option>
                            <option value="1000+">1000+ 人</option>
                        </select>
                        <textarea name="message" placeholder="描述您的需求（选填）" rows="3" class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-primary-300"></textarea>
                        <button type="submit" class="w-full bg-white text-primary-600 font-bold py-3 rounded-lg hover:bg-primary-50 transition shadow-lg">提交咨询</button>
                    </form>
                    <p id="enterprise-success" class="hidden text-green-200 text-center py-4">✅ 提交成功！我们将在1 个工作日内联系您。</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── FAQ ─── -->
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">定价常见问题</h2>
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">可以随时升级或降级套餐吗？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">可以。您可以随时在管理后台自助升级或降级套餐，差价按比例折算。升级即时生效，降级在当前周期结束后生效。</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">免费版有什么限制？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">免费版永久免费，包含 1 个产品、月度100 个激活、基础 API 访问。适合个人开发者和小型项目试用。</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">年付可以节省多少？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">选择年付可节省20% 费用。例如专业版月付 ¥299/月，年付 ¥2,868/年（相当于¥239/月），每年节省¥720。</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">支持哪些支付方式？</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">支持微信支付、支付宝、Stripe（信用卡）和 PayPal。企业客户可选择银行转账并开具增值税发票。</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── Footer ─── -->
    @include('public.partials.footer')

    <script>
    // ─── 月/年切换（带动画） ───
    function animatePrice(el) {
        if (!el) return;
        el.classList.remove('price-animate');
        // Force reflow for re-trigger animation
        void el.offsetWidth;
        el.classList.add('price-animate');
    }

    function switchBilling(period) {
        const moBtn = document.getElementById('mo-btn');
        const yrBtn = document.getElementById('yr-btn');
        moBtn.className = `px-6 py-2.5 rounded-full font-medium transition-all duration-200 ${period === 'monthly' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-900'}`;
        yrBtn.className = `px-6 py-2.5 rounded-full font-medium transition-all duration-200 ${period === 'yearly' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-900'}`;

        // 从数据属性读取各套餐价格，动态切换
        const isYearly = period === 'yearly';
        document.querySelectorAll('.plan-card').forEach(function(card) {
            const slug = card.dataset.slug;
            if (!slug) return;
            const monthly = parseFloat(card.dataset.priceMonthly) || 0;
            const quarterly = parseFloat(card.dataset.priceQuarterly) || 0;
            const semiAnnually = parseFloat(card.dataset.priceSemiAnnually) || 0;
            const yearly = parseFloat(card.dataset.priceYearly) || 0;

            const priceEl = document.getElementById('price-' + slug);
            const periodEl = document.getElementById('period-' + slug);
            const hintEl = document.getElementById('yearly-hint-' + slug);
            if (!priceEl) return;

            if (isYearly && yearly > 0) {
                priceEl.textContent = '¥' + yearly.toLocaleString();
                if (periodEl) periodEl.textContent = '/月（年付）';
                // 计算年付相比月付节省
                if (yearly < monthly && monthly > 0) {
                    const saved = (monthly - yearly) * 12;
                    if (hintEl) {
                        hintEl.innerHTML = '¥' + yearly.toLocaleString() + '/月 × 12，<strong>省 ¥' + saved.toLocaleString() + '/年</strong>';
                        hintEl.classList.remove('hidden');
                    }
                } else if (hintEl) {
                    hintEl.classList.add('hidden');
                }
            } else {
                priceEl.textContent = '¥' + monthly.toLocaleString();
                if (periodEl) periodEl.textContent = '/月';
                if (hintEl) hintEl.classList.add('hidden');
            }
            animatePrice(priceEl);
        });

        // 更新粘性栏价格
        document.querySelectorAll('[id^="sticky-price-"]').forEach(function(el) {
            const slug = el.id.replace('sticky-price-', '');
            const card = document.querySelector('.plan-card[data-slug="' + slug + '"]');
            if (!card) return;
            const monthly = parseFloat(card.dataset.priceMonthly) || 0;
            const yearly = parseFloat(card.dataset.priceYearly) || 0;
            el.textContent = '¥' + (isYearly && yearly > 0 ? yearly : monthly).toLocaleString();
            animatePrice(el);
        });
        document.querySelectorAll('[id^="sticky-period-"]').forEach(function(el) {
            const slug = el.id.replace('sticky-period-', '');
            const card = document.querySelector('.plan-card[data-slug="' + slug + '"]');
            if (!card) return;
            const yearly = parseFloat(card?.dataset?.priceYearly) || 0;
            el.textContent = isYearly && yearly > 0 ? '/月（年付）' : '/月';
        });
    }

    // ─── 用量计算器 ───
    var _isYearly = false;
    function toggleCalculator() {
        var panel = document.getElementById('calc-panel');
        var arrow = document.getElementById('calc-arrow');
        if (!panel) return;
        var h = panel.classList.contains('hidden');
        panel.classList.toggle('hidden');
        if (arrow) arrow.classList.toggle('rotate-180', !h);
        if (h) updateCalculator();
    }
    function updateCalculator() {
        var products = parseInt(document.getElementById('calc-products').value) || 1;
        var activations = parseInt(document.getElementById('calc-activations').value) || 100;
        
        document.getElementById('calc-products-display').textContent = products;
        document.getElementById('calc-activations-display').textContent = activations.toLocaleString();
        
        // Determine recommended plan
        var plan = 'free', planName = '免费版', planDesc = '适合个人入门', price = 0, unitPrice = 0;
        if (products <= 1 && activations <= 100) {
            plan = 'free'; planName = '免费版'; planDesc = '适合个人入门'; unitPrice = 0;
        } else if (products <= 5 && activations <= 1000) {
            plan = 'basic'; planName = '基础版'; planDesc = '适合小型团队'; unitPrice = _isYearly ? 79 : 99;
        } else if (products <= 50 && activations <= 10000) {
            plan = 'pro'; planName = '专业版'; planDesc = '适合成长型团队'; unitPrice = _isYearly ? 239 : 299;
        } else {
            plan = 'ent'; planName = '企业版'; planDesc = '适合大型企业'; unitPrice = _isYearly ? 799 : 999;
        }
        
        document.getElementById('calc-plan-name').textContent = planName;
        document.getElementById('calc-plan-desc').textContent = planDesc;
        document.getElementById('calc-plan-price').textContent = '¥' + unitPrice;
        document.getElementById('calc-plan-period').textContent = _isYearly ? '/月（年付）' : '/月';
        
        var yearlyHint = document.getElementById('calc-plan-yearly');
        if (yearlyHint) {
            var savings = 0;
            if (plan === 'basic') savings = 240;
            else if (plan === 'pro') savings = 720;
            else if (plan === 'ent') savings = 2400;
            yearlyHint.textContent = savings > 0 ? '年付省 ¥' + savings.toLocaleString() + '/年' : '';
            yearlyHint.classList.toggle('hidden', !_isYearly || plan === 'free');
        }
        
        document.getElementById('calc-p-products').textContent = products;
        document.getElementById('calc-p-activations').textContent = activations.toLocaleString();
        
        // Annual total
        var annualTotal = 0;
        if (plan === 'basic') annualTotal = _isYearly ? 79 * 12 : 99 * 12;
        else if (plan === 'pro') annualTotal = _isYearly ? 239 * 12 : 299 * 12;
        else if (plan === 'ent') annualTotal = _isYearly ? 799 * 12 : 999 * 12;
        document.getElementById('calc-p-total').textContent = annualTotal > 0 ? '¥' + annualTotal.toLocaleString() : '免费';
        
        // Update pricing cards billing period to match
        switchBilling(_isYearly ? 'yearly' : 'monthly');
    }
    // 同步计算器与月/年切换
    document.addEventListener('DOMContentLoaded', function() {
        var moBtn = document.getElementById('mo-btn');
        var yrBtn = document.getElementById('yr-btn');
        if (moBtn) moBtn.addEventListener('click', function() { _isYearly = false; updateCalculator(); });
        if (yrBtn) yrBtn.addEventListener('click', function() { _isYearly = true; updateCalculator(); });
    });

    function toggleFaq(btn) {
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('svg');
        content.classList.toggle('hidden');
        icon.classList.toggle('rotate-180');
    }

    function submitEnterpriseForm(e) {
        e.preventDefault();
        const form = e.target;
        const data = Object.fromEntries(new FormData(form));
        fetch('/api/public/enterprise-contact', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            body: JSON.stringify(data),
        }).then(r => r.json()).then(res => {
            document.getElementById('enterprise-success').classList.remove('hidden');
            form.classList.add('hidden');
        }).catch(() => {
            alert('提交失败，请稍后重试或发送邮件至 enterprise@huwutong.com');
        });
    }
</script>
</body>
</html>
