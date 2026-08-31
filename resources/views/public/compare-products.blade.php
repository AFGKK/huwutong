<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.compare_products_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.compare_products_page.meta_desc') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        .compare-table th, .compare-table td { padding: 12px 16px; text-align: center; border-bottom: 1px solid #f3f4f6; }
        .compare-table th { background: #f9fafb; font-weight: 600; color: #374151; position: sticky; top: 0; z-index: 10; }
        .compare-table tr:hover td { background: #fafafa; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    <!-- 导航 -->
    @include('public.partials.nav')

    <section class="pt-24 pb-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- 面包屑 -->
            <div class="text-sm text-slate-500 flex items-center gap-2 mb-6">
                <a href="{{ url('/') }}" class="hover:text-slate-900 transition">{{ __('app.products_page.breadcrumb_home') }}</a>
                <span>/</span>
                <a href="{{ url('/products') }}" class="hover:text-slate-900 transition">{{ __('app.nav.products') }}</a>
                <span>/</span>
                <span class="text-slate-900 font-medium">{{ __('app.nav.compare') }}</span>
            </div>
            <!-- 标题 -->
            <div class="text-center mb-12">
                <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ __('app.nav.compare') }}</h1>
                <p class="text-slate-500 mt-2">{{ __('app.compare_products_page.subtitle') }}</p>
            </div>

            @if($products->count() < 2)
                <div class="text-center py-20">
                    <svg class="w-20 h-20 mx-auto mb-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <p class="text-lg text-slate-500 mb-2">{{ __('app.compare_products_page.empty_title') }}</p>
                    <p class="text-sm text-slate-400 mb-6">{{ __('app.compare_products_page.empty_hint') }}</p>
                    <a href="/products" class="inline-block bg-slate-900 text-white px-6 py-2.5 rounded-xl font-medium hover:bg-slate-800 transition">{{ __('app.nav.products') }}</a>
                </div>
            @else
                <!-- 对比表格 -->
                <div class="overflow-x-auto bg-white rounded-2xl shadow-sm border border-gray-100">
                    <table class="compare-table w-full text-sm">
                        <thead>
                            <tr>
                                <th class="text-left w-40 bg-white sticky left-0 z-20" style="min-width:140px">{{ __('app.compare_products_page.col_item') }}</th>
                                @foreach($products as $p)
                                    <th class="min-w-[200px]">
                                        <div class="flex flex-col items-center gap-2 py-2">
                                            <div class="w-16 h-16 rounded-xl bg-gray-50 overflow-hidden flex items-center justify-center">
                                                @if($p->image_url)
                                                    <img src="{{ $p->image_url }}" alt="{{ $p->name }}" class="w-full h-full object-cover">
                                                @else
                                                    <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                                @endif
                                            </div>
                                            <a href="{{ url('/products/'.$p->slug) }}" class="font-semibold text-gray-900 hover:text-slate-900">{{ $p->name }}</a>
                                        </div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <!-- 价格 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.price') }}</td>
                                @foreach($products as $p)
                                    <td>
                                        <span class="text-lg font-bold text-red-500">
                                            @if($p->lowest_price)
                                                ¥{{ number_format($p->lowest_price, 2) }}
                                                @if($p->highest_price && $p->highest_price != $p->lowest_price)
                                                    ~¥{{ number_format($p->highest_price, 2) }}
                                                @endif
                                            @else
                                                <span class="text-gray-400">{{ __('app.compare_products_page.negotiable') }}</span>
                                            @endif
                                        </span>
                                    </td>
                                @endforeach
                            </tr>
                            <!-- 评分 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.rating') }}</td>
                                @foreach($products as $p)
                                    <td>
                                        <span class="text-yellow-400">{{ str_repeat('★', round($p->avg_rating ?: 0)) }}{{ str_repeat('★', 5 - round($p->avg_rating ?: 0)) }}</span>
                                        <span class="text-gray-500 ml-1">{{ number_format($p->avg_rating ?: 0, 1) }}</span>
                                        <span class="text-gray-400 text-xs">{{ __('app.compare_products_page.reviews_n', ['n' => $p->review_count]) }}</span>
                                    </td>
                                @endforeach
                            </tr>
                            <!-- 已售 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.sold') }}</td>
                                @foreach($products as $p)
                                    <td class="text-gray-700">{{ $p->sold_total }}</td>
                                @endforeach
                            </tr>
                            <!-- 分类 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.category') }}</td>
                                @foreach($products as $p)
                                    <td>
                                        @if($p->category)
                                            <span class="text-slate-700 bg-slate-100 px-3 py-1 rounded-full text-xs">{{ $p->category->name }}</span>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <!-- 功能特性-->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.features') }}</td>
                                @foreach($products as $p)
                                    <td>
                                        @if($p->featureFlags && $p->featureFlags->count() > 0)
                                            <div class="flex flex-wrap gap-1.5 justify-center">
                                                @foreach($p->featureFlags as $flag)
                                                    <span class="inline-flex items-center gap-1 text-xs text-green-700 bg-green-50 px-2.5 py-1 rounded-full border border-green-100">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                                        {{ $flag->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <!-- SKU 方案 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ __('app.compare_products_page.plans') }}</td>
                                @foreach($products as $p)
                                    <td>
                                        @if($p->skus && $p->skus->count() > 0)
                                            <div class="space-y-1.5">
                                                @foreach($p->skus as $sku)
                                                    <div class="text-xs text-gray-600 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                                                        <span class="font-medium">{{ $sku->name }}</span>
                                                        <span class="text-red-500 ml-2">¥{{ number_format($sku->price, 2) }}</span>
                                                        @if($sku->compare_at_price && $sku->compare_at_price > $sku->price)
                                                            <span class="text-gray-400 line-through ml-1">¥{{ number_format($sku->compare_at_price, 2) }}</span>
                                                        @endif
                                                        <span class="text-gray-400 ml-2">{{ $sku->billing_cycle === 'yearly' ? __('app.compare_products_page.per_year') : ($sku->billing_cycle === 'monthly' ? __('app.compare_products_page.per_month') : '') }}</span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                            <!-- 规格参数 -->
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0" colspan="{{ $products->count() + 1 }}">
                                    <span class="text-base">{{ __('app.compare_products_page.specs') }}</span>
                                </td>
                            </tr>
                            @php
                                $_allSpecs = [];
                                foreach ($products as $p) {
                                    if ($p->specGroups) {
                                        foreach ($p->specGroups as $g) {
                                            if ($g->specs) {
                                                foreach ($g->specs as $s) {
                                                    $_allSpecs[$s->name] = true;
                                                }
                                            }
                                        }
                                    }
                                }
                            @endphp
                            @foreach(array_keys($_allSpecs) as $specName)
                            <tr>
                                <td class="text-left font-medium text-gray-700 bg-gray-50 sticky left-0">{{ $specName }}</td>
                                @foreach($products as $p)
                                    <td class="text-gray-600">
                                        @php
                                            $_found = null;
                                            if ($p->specGroups) {
                                                foreach ($p->specGroups as $g) {
                                                    if ($g->specs) {
                                                        foreach ($g->specs as $s) {
                                                            if ($s->name === $specName) { $_found = $s->value; break; }
                                                        }
                                                    }
                                                }
                                            }
                                        @endphp
                                        {{ $_found ?: '-' }}
                                    </td>
                                @endforeach
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 操作按钮 -->
                <div class="flex justify-center gap-4 mt-10">
                    <a href="/products" class="inline-block bg-slate-900 text-white px-5 py-2 rounded-lg font-medium hover:bg-slate-800 transition">{{ __('app.compare_products_page.continue') }}</a>
                    <button onclick="clearCompare()" class="px-8 py-3 rounded-xl border border-red-200 text-red-500 hover:bg-red-50 transition font-medium text-sm">{{ __('app.compare_products_page.clear') }}</button>
                </div>
            @endif
        </div>
    </section>

    <!-- Footer -->
    @include('public.partials.footer')

    <script>
    const COMPARE_KEY = 'huwutong_compare_products';

    function clearCompare() {
        try { localStorage.removeItem(COMPARE_KEY); } catch {}
        try { sessionStorage.removeItem('compare_items'); } catch {}
        window.location.href = '/products';
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.compare-table tbody tr').forEach(function(row) {
            var cells = row.querySelectorAll('td:not(:first-child)');
            if (cells.length < 2) return;
            var values = [];
            cells.forEach(function(cell) { values.push(cell.textContent.trim()); });
            var allSame = values.every(function(v) { return v === values[0]; });
            if (!allSame) {
                cells.forEach(function(cell) {
                    cell.classList.add('bg-amber-50/50');
                });
            }
        });
    });
    </script>
</body>
</html>
