<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $seo['title'] }}</title>
    <meta name="description" content="{{ $seo['description'] }}">
    <meta name="keywords" content="{{ $seo['keywords'] }}">
    <meta property="og:title" content="{{ $seo['title'] }}">
    <meta property="og:description" content="{{ $seo['description'] }}">
    <meta property="og:type" content="website">
    <link rel="canonical" <hr>
    @vite('resources/css/public.css')
    <style>
        html { scroll-behavior: smooth; }
        .compare-table th { position: sticky; top: 64px; background: #fff; z-index: 10; }
        .compare-table td, .compare-table th { padding: 14px 16px; border-bottom: 1px solid #f0f0f0; }
        .compare-table tr:hover td { background: #fafafa; }
        .compare-table .huwutong-row td { background: #eff6ff; }
        .compare-table .huwutong-row:hover td { background: #dbeafe; }
        .badge-yes { background: #dcfce7; color: #16a34a; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
        .badge-no { background: #fef2f2; color: #dc2626; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .badge-win { background: #dcfce7; color: #16a34a; }
        .badge-equal { background: #f3f4f6; color: #6b7280; }
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-gray-50">
    @include('public.partials.nav')

    <!-- ─── Hero ─── -->
    <section class="pt-28 pb-12 bg-gradient-to-br from-primary-600 via-primary-700 to-blue-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h1 class="text-3xl md:text-5xl font-extrabold text-white mb-4">{{ $seo['title'] }}</h1>
            <p class="text-lg text-white/80 max-w-3xl mx-auto">{{ $seo['description'] }}</p>
        </div>
    </section>

    <!-- ─── 竞品概览 ─── -->
    <section class="py-8 bg-white">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($competitors as $key => $comp)
                <div class="border border-gray-200 rounded-xl p-5 text-center hover:shadow-lg transition">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-gray-100 flex items-center justify-center text-lg font-bold text-gray-400">{{ strtoupper(substr($comp['name'], 0, 2)) }}</div>
                    <h3 class="font-semibold text-gray-900">{{ $comp['name'] }}</h3>
                    <p class="text-xs text-gray-500 mt-1">{{ $comp['description'] }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ─── 对比表格 ─── -->
    <section class="py-12">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="compare-table w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b-2 border-gray-200">
                            <th class="text-left font-semibold text-gray-700 min-w-[140px]">对比维度</th>
                            <th class="text-center font-bold text-primary-600 min-w-[120px]">
                                <div class="flex items-center justify-center gap-1">
                                    <span class="w-5 h-5 rounded bg-primary-600 text-white text-xs flex items-center justify-center">产</span>
                                    互物通
                                </div>
                            </th>
                            @foreach($competitors as $key => $comp)
                            <th class="text-center font-medium text-gray-600 min-w-[120px]">{{ $comp['name'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $dimensionKeys = array_keys($dimensions);
                            $competitorKeys = array_keys($competitors);
                            $comparisonData = $comparison_data;
                        @endphp
                        @foreach($dimensions as $dimKey => $dim)
                        <tr class="hover:bg-gray-50 transition">
                            <td class="font-medium text-gray-700">{{ $dim['label'] }}</td>
                            @foreach(array_merge(['huwutong'], $competitorKeys) as $compKey)
                            @php
                                $val = $comparisonData[$dimKey][$compKey] ?? '—';
                                $isHuwutong = $compKey === 'huwutong';
                            @endphp
                            <td class="text-center {{ $isHuwutong ? 'bg-primary-50/50' : '' }}">
                                @if($val === true)
                                    <span class="badge-yes">✅ 支持</span>
                                @elseif($val === false)
                                    <span class="badge-no">✕ 不支持</span>
                                @else
                                    <span class="text-gray-700 text-xs">{{ $val }}</span>
                                @endif
                            </td>
                            @endforeach
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- 总分统计 -->
            @php
                $scores = [];
                foreach(array_merge(['huwutong'], $competitorKeys) as $compKey) {
                    $score = 0;
                    foreach($dimensions as $dimKey => $dim) {
                        $val = $comparisonData[$dimKey][$compKey] ?? null;
                        if ($val === true) $score++;
                    }
                    $scores[$compKey] = $score;
                }
                arsort($scores);
                $topScore = reset($scores);
            @endphp
            <div class="mt-8 grid grid-cols-1 md:grid-cols-5 gap-3">
                @foreach($scores as $compKey => $score)
                @php $comp = $competitors[$compKey] ?? ['name' => '互物通', 'description' => '']; @endphp
                <div class="border rounded-xl p-4 text-center {{ $compKey === 'huwutong' ? 'border-primary-500 bg-primary-50 ring-2 ring-primary-200' : 'border-gray-200' }}">
                    @if($compKey === 'huwutong')
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-primary-600 text-white font-bold flex items-center justify-center">H</div>
                    @else
                    <div class="w-10 h-10 mx-auto mb-2 rounded-full bg-gray-100 text-gray-400 font-bold flex items-center justify-center">{{ strtoupper(substr($comp['name'], 0, 2)) }}</div>
                    @endif
                    <div class="text-lg font-bold {{ $compKey === 'huwutong' ? 'text-primary-600' : 'text-gray-700' }}">{{ $score }}/{{ count($dimensions) }}</div>
                    <div class="text-xs {{ $compKey === 'huwutong' ? 'text-primary-500' : 'text-gray-500' }}">{{ $comp['name'] }}</div>
                    @if($score === $topScore && $compKey === 'huwutong')
                        <div class="mt-1 text-xs font-bold text-green-600">🏆 领先</div>
                    @endif
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ─── 优势总结 ─── -->
    <section class="py-12 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-center mb-8">为什么选择互物通？</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="p-6 rounded-xl border border-gray-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-green-50 flex items-center justify-center text-2xl">🛡︀</div>
                    <h3 class="font-semibold mb-2">离线授权 + 设备指纹</h3>
                    <p class="text-sm text-gray-500">唯一同时支持离线激活、Ed25519 签名、设备指纹和 CRL 吊销列表的平台，气隙环境也能用。</p>
                </div>
                <div class="p-6 rounded-xl border border-gray-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-blue-50 flex items-center justify-center text-2xl">🌐</div>
                    <h3 class="font-semibold mb-2">中国优化 + 全球合规</h3>
                    <p class="text-sm text-gray-500">内置 PIPL/GDPR 合规，国冀CDN 加速，微信/支付宝支付，更适合中国企业。</p>
                </div>
                <div class="p-6 rounded-xl border border-gray-100 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-purple-50 flex items-center justify-center text-2xl">🤖</div>
                    <h3 class="font-semibold mb-2">AI 智能 + 全功能</h3>
                    <p class="text-sm text-gray-500">AI 风控、智能分析、自动化运营，多语言 SDK、席位池、OEM 白标、GraphQL 一应俱全。</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── CTA ─── -->
    <section class="py-16 bg-gradient-to-r from-primary-600 to-blue-700">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4">准备好切换了吗？</h2>
            <p class="text-primary-100 mb-6">免费注册，一分钟完成集成。提例Cryptlex/Localazy 迁移工具。</p>
            <a href="/build/register" class="inline-block bg-white text-primary-600 px-8 py-3 rounded-xl font-bold hover:bg-primary-50 transition shadow-lg">免费开始使用→</a>
        </div>
    </section>

    @include('public.partials.footer')

    <script>
    // ─── Token 登录检浀───
    (function() {
        const token = localStorage.getItem('auth_token');
        if (!token || document.querySelector('#session-user-section')) return;
        fetch('/api/user', {
            headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' },
        }).then(r => r.json()).then(res => {
            if (!res.data) return;
            const u = res.data;
            const guestLinks = document.querySelector('.guest-links-desktop');
            if (guestLinks) {
                guestLinks.innerHTML =
                    '<a href="/build/cart">购物车</a>' +
                    '<div class="flex items-center gap-2 pl-4 border-l border-gray-200">' +
                        '<span class="text-sm font-medium text-gray-700">' + u.name + '</span>' +
                        '<a href="/build/logout" id="logout-link-compare">退出</a>' +
                    '</div>';
            }
        }).catch(() => {});
    })();
    </script>
</body>
</html>
