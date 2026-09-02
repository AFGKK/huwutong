<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('app.pricing.meta_desc') }}">
    <meta property="og:title" content="{{ __('app.pricing.og_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}">
    <meta property="og:description" content="{{ __('app.pricing.meta_desc') }}">
    <link rel="canonical" href="{{ url('/pricing') }}">
    <title>{{ __('app.pricing.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    @include('public.partials.tracking')

    @vite('resources/css/public.css')
    <style>
        html { scroll-behavior: smooth; }
        .plan-card { transition: all 0.3s ease; min-width: 280px; }
        @media (max-width: 380px) { .plan-card { min-width: 260px; } }
        .plan-card.popular { border-color: var(--pg-primary); transform: scale(1.03); }
        .plan-card:hover { transform: translateY(-6px); box-shadow: 0 20px 40px -12px rgba(var(--pg-primary-rgb), 0.12); }
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
        #calc-products, #calc-activations { -webkit-appearance: none; appearance: none; height: 8px; background: #e2e8f0; border-radius: 4px; outline: none; cursor: pointer; }
        #calc-products::-webkit-slider-thumb, #calc-activations::-webkit-slider-thumb { -webkit-appearance: none; appearance: none; width: 22px; height: 22px; border-radius: 50%; background: var(--pg-primary); cursor: pointer; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(var(--pg-primary-rgb), 0.25); }
        #calc-products::-moz-range-thumb, #calc-activations::-moz-range-thumb { width: 22px; height: 22px; border-radius: 50%; background: var(--pg-primary); cursor: pointer; border: 3px solid #fff; box-shadow: 0 2px 6px rgba(var(--pg-primary-rgb), 0.25); }
        .trust-chip {
            flex-shrink: 0;
            min-width: 120px;
            height: 52px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
            padding: 0 1rem;
        }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.miniprogram-env')
    @include('public.partials.nav')

    <!-- ─── 定价头部 ─── -->
    <section class="pt-24 pb-16 md:pb-20 bg-white relative overflow-hidden">
        <!-- 背景装饰 -->
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-slate-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-slate-500 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <!-- 面包屑导航 -->
            <nav class="flex items-center gap-1.5 text-sm mb-8 text-slate-500">
                <a href="{{ url('/') }}" class="hover:text-slate-900 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('app.pricing.breadcrumb_home') }}
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-slate-700 font-medium">{{ __('app.pricing.title') }}</span>
            </nav>
            <div class="text-center">
                <h1 class="text-4xl md:text-5xl font-extrabold text-slate-900 mb-4">{{ __('app.pricing.hero_title') }}</h1>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto mb-8">{{ __('app.pricing.hero_subtitle') }}</p>
                <!-- 计费切换（增强） -->
                <div class="inline-flex items-center bg-slate-100 rounded-lg p-1 shadow-sm">
                    <button type="button" id="mo-btn" class="px-6 py-2.5 rounded-lg font-medium transition-all duration-200 bg-slate-900 text-white">
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>{{ __('app.pricing.monthly') }}</span>
                    </button>
                    <button type="button" id="yr-btn" class="px-6 py-2.5 rounded-lg font-medium transition-all duration-200 text-slate-500 hover:text-slate-900">
                        <span class="flex items-center gap-1.5"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>{{ __('app.pricing.yearly') }} <span class="ml-1 text-xs bg-emerald-100 text-emerald-700 font-bold px-2 py-0.5 rounded-full">{{ __('app.pricing.save_20') }}</span></span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 用量计算器 ─── -->
    <section class="py-6 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-slate-50 rounded-2xl border border-slate-200 p-6 md:p-8">
                <button onclick="toggleCalculator()" class="w-full flex items-center justify-between text-left">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="text-base font-semibold text-slate-900">{{ __('app.pricing.calculator') }}</span>
                        <span class="text-xs text-slate-400 font-normal">{{ __('app.pricing.calculator_hint') }}</span>
                    </div>
                    <svg id="calc-arrow" class="w-5 h-5 text-gray-400 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div id="calc-panel" class="hidden mt-6 space-y-6">
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-slate-700">{{ __('app.pricing.calc_products') }}</label>
                            <span class="text-sm font-bold text-slate-900" id="calc-products-display">5</span>
                        </div>
                        <input type="range" min="1" max="100" value="5" oninput="updateCalculator()" id="calc-products" class="w-full">
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>1</span><span>10</span><span>50</span><span>100</span>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <label class="text-sm font-medium text-slate-700">{{ __('app.pricing.calc_activations') }}</label>
                            <span class="text-sm font-bold text-slate-900" id="calc-activations-display">1,000</span>
                        </div>
                        <input type="range" min="100" max="100000" value="1000" step="100" oninput="updateCalculator()" id="calc-activations" class="w-full">
                        <div class="flex justify-between text-xs text-gray-400">
                            <span>100</span><span>1K</span><span>10K</span><span>100K</span>
                        </div>
                    </div>
                    <div class="bg-white rounded-xl p-5 border border-slate-200">
                        <div class="text-xs font-medium text-slate-500 mb-3">{{ __('app.pricing.calc_recommend') }}</div>
                        <div id="calc-result" class="flex items-center justify-between">
                            <div>
                                <span id="calc-plan-name" class="text-lg font-bold text-slate-900">{{ __('app.pricing.plan_basic') }}</span>
                                <span id="calc-plan-desc" class="text-sm text-slate-500 ml-2">{{ __('app.pricing.calc_fit') }}</span>
                            </div>
                            <div class="text-right">
                                <span id="calc-plan-price" class="text-2xl font-extrabold text-slate-900">¥99</span>
                                <span class="text-sm text-slate-500" id="calc-plan-period">{{ __('app.pricing.per_month') }}</span>
                                <div id="calc-plan-yearly" class="text-xs text-emerald-600 hidden"></div>
                            </div>
                        </div>
                        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-3 gap-3 text-center text-xs">
                            <div>
                                <div class="font-semibold text-slate-900" id="calc-p-products">5</div>
                                <div class="text-slate-500">{{ __('app.pricing.calc_products_label') }}</div>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-900" id="calc-p-activations">1,000</div>
                                <div class="text-slate-500">{{ __('app.pricing.calc_activations_label') }}</div>
                            </div>
                            <div>
                                <div class="font-semibold text-slate-900" id="calc-p-total">¥1,188</div>
                                <div class="text-slate-500">{{ __('app.pricing.calc_yearly_label') }}</div>
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
                <div class="plan-card rounded-2xl border-2 {{ $isPopular ? 'border-slate-900 popular shadow-xl shadow-slate-200/60' : 'border-gray-100' }} bg-white p-8 flex flex-col relative"
                     data-slug="{{ $plan['slug'] }}"
                     data-plan-id="{{ $plan['id'] ?? '' }}"
                     data-price-monthly="{{ $monthlyPrice }}"
                     data-price-quarterly="{{ (float)($plan['price_quarterly'] ?? 0) }}"
                     data-price-semi-annually="{{ (float)($plan['price_semi_annually'] ?? 0) }}"
                     data-price-yearly="{{ $yearlyPrice }}"
                     data-max-products="{{ $limits['max_products'] ?? '' }}"
                     data-max-activations="{{ $limits['max_activations'] ?? '' }}"
                     data-plan-name="{{ $plan['name'] }}">
                    @if($isPopular)
                    <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-slate-900 text-white text-xs font-bold px-5 py-1 rounded-full shadow-lg flex items-center gap-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        {{ __('app.landing.pricing_popular') }}
                    </div>
                    @endif
                    <h3 class="text-xl font-bold text-gray-900 mt-2">{{ $plan['name'] }}</h3>
                    <p class="text-sm text-gray-500 mt-1 mb-5">{{ $plan['description'] ?? '' }}</p>
                    @if($monthlyPrice > 0)
                    <div class="mb-6 min-h-[4.5rem]">
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-extrabold text-gray-900" id="price-{{ $plan['slug'] }}">¥{{ number_format($monthlyPrice) }}</span>
                            <span class="text-gray-500" id="period-{{ $plan['slug'] }}">{{ __('app.pricing.per_month') }}</span>
                        </div>
                        <div id="yearly-hint-{{ $plan['slug'] }}" class="invisible text-xs text-emerald-600 mt-1 min-h-[1rem]" aria-hidden="true">&nbsp;</div>
                        @if(($plan['trial_days'] ?? 0) > 0)
                        <div class="text-xs text-slate-600 mt-1">{{ __('app.pricing.trial_days', ['days' => $plan['trial_days']]) }}</div>
                        @endif
                    </div>
                    @else
                    <div class="mb-6">
                        <span class="text-4xl font-extrabold text-gray-900">¥0</span>
                        <span class="text-gray-500">{{ __('app.pricing.forever') }}</span>
                    </div>
                    @endif
                    <ul class="space-y-3 text-sm text-gray-600 flex-1 mb-8">
                        @forelse($features as $feature)
                        <li class="flex items-start gap-2">
                            <svg class="w-4 h-4 text-slate-700 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            {{ $feature }}
                        </li>
                        @empty
                        <li class="text-gray-400 italic">{{ __('app.pricing.no_features') }}</li>
                        @endforelse
                    </ul>
                    <a href="{{ $monthlyPrice > 0 ? url('/build/subscribe/'.($plan['id'] ?? '').'?period=monthly') : url('/build/register') }}"
                       class="plan-cta block w-full text-center py-3 rounded-xl font-semibold bg-slate-900 text-white hover:bg-slate-800 transition text-sm"
                       data-plan-paid="{{ $monthlyPrice > 0 ? '1' : '0' }}"
                       data-plan-id="{{ $plan['id'] ?? '' }}">
                        {{ $monthlyPrice > 0 ? __('app.pricing.subscribe') : __('app.pricing.start_free') }}
                    </a>
                </div>
                @empty
                <div class="col-span-4 text-center py-16 text-gray-400">
                    <p>{{ __('app.pricing.no_plans') }}</p>
                </div>
                @endforelse
            </div>
            <!-- 移动端滑动提示 -->
            <div class="md:hidden text-center text-xs text-gray-400 mt-4">
                <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg> {{ __('app.pricing.swipe_hint') }} <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg></span>
            </div>
        </div>
    </section>

    <!-- ─── 行业信任 ─── -->
    <section class="py-12 bg-white border-t border-gray-100 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-sm text-slate-500 mb-6">{{ __('app.pricing.trust_line') }}</p>
            <div class="flex flex-wrap items-center justify-center gap-3">
                @foreach(__('app.landing.industries') as $industry)
                <span class="trust-chip">{{ $industry }}</span>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ─── 功能对比矩阵 ─── -->
    <section id="comparison" class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">{{ __('app.pricing.compare_title') }}</h2>
            @php
                $comparePlans = collect($plans)->values();
                $matrixRows = $matrixRows ?? [];
            @endphp
            <div class="table-scroll-wrap overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm">
                <table id="comparison-table" class="w-full text-sm" style="table-layout:fixed">
                    <thead>
                        <tr>
                            <th class="text-left font-semibold text-gray-700" style="width:240px;padding:14px 16px;background:#fff;position:sticky;top:80px;z-index:21">{{ __('app.pricing.compare_feature') }}</th>
                            @foreach($comparePlans as $plan)
                                @php
                                    $stickyPrice = (float) ($plan['price_monthly'] ?? 0);
                                    $isPopularCol = ($plan['badge'] ?? '') === 'popular';
                                @endphp
                                <th class="text-center" style="width:auto;padding:14px 8px;background:{{ $isPopularCol ? '#eff6ff' : '#fff' }};position:sticky;top:80px;z-index:20">
                                    <div class="font-semibold text-xs" style="{{ $isPopularCol ? 'color:var(--pg-primary)' : 'color:#4b5563' }}">{{ $plan['name'] }}</div>
                                    <div class="text-base font-bold text-gray-900 mt-0.5 price-display" id="sticky-price-{{ $plan['slug'] }}" data-plan="{{ $plan['slug'] }}">¥{{ number_format($stickyPrice, $stickyPrice == floor($stickyPrice) ? 0 : 2) }}</div>
                                    <div class="text-[10px] text-gray-400" id="sticky-period-{{ $plan['slug'] }}">{{ $stickyPrice > 0 ? __('app.pricing.per_month') : __('app.pricing.forever_free') }}</div>
                                    @if($stickyPrice > 0 && !empty($plan['id']))
                                        <a href="/build/subscribe/{{ $plan['id'] }}?period=monthly" class="sticky-cta mt-1.5 inline-block text-xs bg-slate-900 text-white px-2.5 py-1 rounded-lg hover:bg-slate-800 transition font-medium" data-plan-id="{{ $plan['id'] }}">{{ __('app.pricing.subscribe_short') }}</a>
                                    @else
                                        <a href="/build/register" class="mt-1.5 inline-block text-xs bg-slate-900 text-white px-2.5 py-1 rounded-lg hover:bg-slate-800 transition font-medium">{{ __('app.pricing.start_free') }}</a>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($matrixRows as $index => $row)
                        <tr class="feature-row" @if($index % 2 === 0) style="background:#f8fafc" @endif>
                            <td class="py-3 px-4 font-medium text-gray-700" style="min-width:240px;overflow:hidden">
                                <span class="inline-flex items-center gap-1 group relative cursor-help">
                                    {{ $row['label'] ?? '' }}
                                    <svg class="w-3.5 h-3.5 text-gray-300 group-hover:text-slate-500 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-3 py-1.5 bg-gray-900 text-white text-xs rounded-lg shadow-lg opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none z-10 max-w-[260px] whitespace-normal">
                                        {{ $row['tip'] ?? '' }}
                                        <span class="absolute top-full left-1/2 -translate-x-1/2 -mt-px border-4 border-transparent border-t-gray-900"></span>
                                    </span>
                                </span>
                            </td>
                            @foreach($row['cells'] ?? [] as $cellIndex => $cell)
                                @php $isPopularCol = ($comparePlans[$cellIndex]['badge'] ?? '') === 'popular'; @endphp
                                <td class="text-center py-3 px-3 text-sm {{ $isPopularCol ? 'font-medium' : 'text-gray-600' }}" @if($isPopularCol) style="background:#eff6ff;color:#1e40af" @endif>
                                    {{ $cell }}
                                </td>
                            @endforeach
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
            <div class="bg-slate-900 rounded-2xl p-8 md:p-12 text-white text-center">
                <h2 class="text-2xl md:text-3xl font-bold mb-4">{{ __('app.pricing.enterprise') }}</h2>
                <p class="text-slate-300 mb-8 max-w-lg mx-auto">{{ __('app.pricing.enterprise_desc') }}</p>
                <div class="max-w-md mx-auto space-y-4 text-left">
                    <form id="enterprise-form" onsubmit="submitEnterpriseForm(event)" class="space-y-4">
                        <input type="text" name="company" placeholder="{{ __('app.pricing.form_company') }}" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-slate-400">
                        <input type="text" name="name" placeholder="{{ __('app.pricing.form_name') }}" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-slate-400">
                        <input type="email" name="email" placeholder="{{ __('app.pricing.form_email') }}" required class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-slate-400">
                        <input type="tel" name="phone" placeholder="{{ __('app.pricing.form_phone') }}" class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-slate-400">
                        <select name="employees" class="w-full px-4 py-3 rounded-lg text-gray-900 border-0 focus:ring-2 focus:ring-slate-400">
                            <option value="">{{ __('app.pricing.form_employees') }}</option>
                            <option value="1-50">{{ __('app.pricing.form_employees_n', ['range' => '1-50']) }}</option>
                            <option value="51-200">{{ __('app.pricing.form_employees_n', ['range' => '51-200']) }}</option>
                            <option value="201-1000">{{ __('app.pricing.form_employees_n', ['range' => '201-1000']) }}</option>
                            <option value="1000+">{{ __('app.pricing.form_employees_n', ['range' => '1000+']) }}</option>
                        </select>
                        <textarea name="message" placeholder="{{ __('app.pricing.form_message') }}" rows="3" class="w-full px-4 py-3 rounded-lg text-gray-900 placeholder-gray-400 border-0 focus:ring-2 focus:ring-slate-400"></textarea>
                        <button type="submit" class="w-full bg-white text-slate-900 font-bold py-3 rounded-lg hover:bg-slate-100 transition shadow-lg">{{ __('app.pricing.contact_btn') }}</button>
                    </form>
                    <p id="enterprise-success" class="hidden text-green-200 text-center py-4">{{ __('app.pricing.form_success') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── FAQ ─── -->
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-gray-900 text-center mb-12">{{ __('app.pricing.faq_section_title') }}</h2>
            <div class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button type="button" onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">{{ __('app.pricing.faq_1_q') }}</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">{{ __('app.pricing.faq_1_a') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button type="button" onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">{{ __('app.pricing.faq_2_q') }}</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">{{ __('app.pricing.faq_2_a') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button type="button" onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">{{ __('app.pricing.faq_3_q') }}</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">{{ __('app.pricing.faq_3_a') }}</div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 p-6">
                    <button type="button" onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left">
                        <span class="font-semibold text-gray-900">{{ __('app.pricing.faq_4_q') }}</span>
                        <svg class="w-5 h-5 text-gray-400 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div class="faq-content hidden mt-4 text-gray-600 leading-relaxed">{{ __('app.pricing.faq_4_a') }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── Footer ─── -->
    @include('public.partials.footer')

    <script>
    // ─── 月/年切换（与首页一致：price_yearly 可为年总额或折合月价） ───
    var _isYearly = false;
    var PRICING_I18N = {
        perMonth: @json(__('app.pricing.per_month')),
        perMonthYearly: @json(__('app.landing.pricing_per_month_yearly')),
        yearlyHint: @json(__('app.landing.pricing_yearly_hint')),
        foreverFree: @json(__('app.pricing.forever_free')),
        freeLabel: @json(__('app.pricing.free_label')),
        planFree: @json(__('app.pricing.plan_free')),
        planBasic: @json(__('app.pricing.plan_basic')),
        planPro: @json(__('app.pricing.plan_pro')),
        planEnt: @json(__('app.pricing.plan_ent')),
        calcFreeDesc: @json(__('app.pricing.calc_free_desc')),
        calcBasicDesc: @json(__('app.pricing.calc_basic_desc')),
        calcProDesc: @json(__('app.pricing.calc_pro_desc')),
        calcEntDesc: @json(__('app.pricing.calc_ent_desc')),
        calcYearlySave: @json(__('app.pricing.calc_yearly_save')),
        enterpriseSubmitFail: @json(__('app.pricing.enterprise_submit_fail')),
        calcRecommendPrefix: @json(__('app.pricing.calc_recommend_prefix')),
    };

    function formatPricingPrice(n) {
        return (Number(n) || 0).toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    function resolvePricingYearly(monthly, yearly) {
        var m = Number(monthly) || 0;
        var y = Number(yearly) || 0;
        if (y <= 0) return { display: m, period: 'month', hint: '' };
        if (y < m) {
            var savedA = Math.max(0, (m - y) * 12);
            return {
                display: y,
                period: 'month_yearly',
                hint: savedA > 0
                    ? PRICING_I18N.yearlyHint.replace(':annual', formatPricingPrice(y * 12)).replace(':saved', formatPricingPrice(savedA))
                    : '',
            };
        }
        var monthlyEquiv = Math.round((y / 12) * 100) / 100;
        var savedB = Math.max(0, m * 12 - y);
        return {
            display: monthlyEquiv,
            period: 'month_yearly',
            hint: savedB > 0
                ? PRICING_I18N.yearlyHint.replace(':annual', formatPricingPrice(y)).replace(':saved', formatPricingPrice(savedB))
                : '',
        };
    }

    function animatePrice(el) {
        if (!el) return;
        el.classList.remove('price-animate');
        void el.offsetWidth;
        el.classList.add('price-animate');
    }

    function switchBilling(period) {
        var moBtn = document.getElementById('mo-btn');
        var yrBtn = document.getElementById('yr-btn');
        var isYearly = period === 'yearly';
        _isYearly = isYearly;
        if (moBtn) moBtn.className = 'px-6 py-2.5 rounded-lg font-medium transition-all duration-200 ' + (isYearly ? 'text-slate-500 hover:text-slate-900' : 'bg-slate-900 text-white');
        if (yrBtn) yrBtn.className = 'px-6 py-2.5 rounded-lg font-medium transition-all duration-200 ' + (isYearly ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-900');

        document.querySelectorAll('.plan-card').forEach(function(card) {
            var slug = card.dataset.slug;
            if (!slug) return;
            var monthly = parseFloat(card.dataset.priceMonthly) || 0;
            var yearly = parseFloat(card.dataset.priceYearly) || 0;
            var priceEl = document.getElementById('price-' + slug);
            var periodEl = document.getElementById('period-' + slug);
            var hintEl = document.getElementById('yearly-hint-' + slug);
            var ctaEl = card.querySelector('.plan-cta');
            if (!priceEl) return;

            var resolved = (isYearly && yearly > 0)
                ? resolvePricingYearly(monthly, yearly)
                : { display: monthly, period: 'month', hint: '' };

            priceEl.textContent = '¥' + formatPricingPrice(resolved.display);
            if (periodEl) {
                periodEl.textContent = resolved.period === 'month_yearly'
                    ? PRICING_I18N.perMonthYearly
                    : PRICING_I18N.perMonth;
            }
            if (hintEl) {
                if (resolved.hint) {
                    hintEl.textContent = resolved.hint;
                    hintEl.classList.remove('invisible');
                    hintEl.setAttribute('aria-hidden', 'false');
                } else {
                    hintEl.innerHTML = '&nbsp;';
                    hintEl.classList.add('invisible');
                    hintEl.setAttribute('aria-hidden', 'true');
                }
            }
            if (ctaEl && ctaEl.dataset.planPaid === '1') {
                var planId = ctaEl.dataset.planId || card.dataset.planId || '';
                if (planId) {
                    ctaEl.setAttribute('href', '/build/subscribe/' + planId + '?period=' + (isYearly ? 'yearly' : 'monthly'));
                }
            }
            animatePrice(priceEl);
        });

        document.querySelectorAll('.sticky-cta[data-plan-id]').forEach(function(el) {
            var planId = el.dataset.planId || '';
            if (planId) {
                el.setAttribute('href', '/build/subscribe/' + planId + '?period=' + (isYearly ? 'yearly' : 'monthly'));
            }
        });

        document.querySelectorAll('[id^="sticky-price-"]').forEach(function(el) {
            var slug = el.id.replace('sticky-price-', '');
            var card = document.querySelector('.plan-card[data-slug="' + slug + '"]');
            if (!card) return;
            var monthly = parseFloat(card.dataset.priceMonthly) || 0;
            var yearly = parseFloat(card.dataset.priceYearly) || 0;
            var resolved = (isYearly && yearly > 0)
                ? resolvePricingYearly(monthly, yearly)
                : { display: monthly, period: 'month' };
            el.textContent = '¥' + formatPricingPrice(resolved.display);
            animatePrice(el);
            var periodEl = document.getElementById('sticky-period-' + slug);
            if (periodEl) {
                periodEl.textContent = monthly <= 0
                    ? (PRICING_I18N.foreverFree || PRICING_I18N.perMonth)
                    : (resolved.period === 'month_yearly' ? PRICING_I18N.perMonthYearly : PRICING_I18N.perMonth);
            }
        });
    }

    // ─── 用量计算器 ───
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

        function fits(card) {
            var maxP = parseFloat(card.dataset.maxProducts);
            var maxA = parseFloat(card.dataset.maxActivations);
            var okP = isNaN(maxP) || maxP < 0 || products <= maxP;
            var okA = isNaN(maxA) || maxA < 0 || activations <= maxA;
            return okP && okA;
        }

        var cards = Array.prototype.slice.call(document.querySelectorAll('.plan-card[data-slug]'))
            .sort(function(a, b) {
                return (parseFloat(a.dataset.priceMonthly) || 0) - (parseFloat(b.dataset.priceMonthly) || 0);
            });

        var card = cards.find(fits) || cards[cards.length - 1] || null;
        var planName = card ? (card.dataset.planName || card.dataset.slug || '') : PRICING_I18N.planFree;
        var monthly = card ? (parseFloat(card.dataset.priceMonthly) || 0) : 0;
        var yearly = card ? (parseFloat(card.dataset.priceYearly) || 0) : 0;
        var unitPrice = _isYearly && yearly > 0 ? resolvePricingYearly(monthly, yearly).display : monthly;
        var planDesc = (PRICING_I18N.calcRecommendPrefix || '') + planName;
        if (!planDesc || planDesc === 'null') {
            planDesc = planName;
        }

        document.getElementById('calc-plan-name').textContent = planName;
        document.getElementById('calc-plan-desc').textContent = planDesc;
        document.getElementById('calc-plan-price').textContent = '¥' + formatPricingPrice(unitPrice);
        document.getElementById('calc-plan-period').textContent = monthly <= 0
            ? (PRICING_I18N.foreverFree || PRICING_I18N.perMonth)
            : (_isYearly ? PRICING_I18N.perMonthYearly : PRICING_I18N.perMonth);

        var yearlyHint = document.getElementById('calc-plan-yearly');
        if (yearlyHint) {
            var savings = 0;
            if (card && yearly > 0 && monthly > 0) {
                savings = yearly < monthly ? Math.max(0, (monthly - yearly) * 12) : Math.max(0, monthly * 12 - yearly);
            }
            yearlyHint.textContent = savings > 0 ? (PRICING_I18N.calcYearlySave || '').replace(':amount', formatPricingPrice(savings)) : '';
            yearlyHint.classList.toggle('hidden', !_isYearly || monthly <= 0 || savings <= 0);
        }

        document.getElementById('calc-p-products').textContent = products;
        document.getElementById('calc-p-activations').textContent = activations.toLocaleString();

        var annualTotal = 0;
        if (card && monthly > 0) {
            if (_isYearly && yearly > 0) {
                annualTotal = yearly < monthly ? Math.round(yearly * 12) : yearly;
            } else {
                annualTotal = Math.round(monthly * 12);
            }
        }
        document.getElementById('calc-p-total').textContent = annualTotal > 0 ? '¥' + formatPricingPrice(annualTotal) : PRICING_I18N.freeLabel;
    }
    // 同步计算器与月/年切换
    document.addEventListener('DOMContentLoaded', function() {
        var moBtn = document.getElementById('mo-btn');
        var yrBtn = document.getElementById('yr-btn');
        if (moBtn) moBtn.addEventListener('click', function() {
            switchBilling('monthly');
            var panel = document.getElementById('calc-panel');
            if (panel && !panel.classList.contains('hidden')) updateCalculator();
        });
        if (yrBtn) yrBtn.addEventListener('click', function() {
            switchBilling('yearly');
            var panel = document.getElementById('calc-panel');
            if (panel && !panel.classList.contains('hidden')) updateCalculator();
        });
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
            alert(PRICING_I18N.enterpriseSubmitFail ||'');
        });
    }
</script>
</body>
</html>
