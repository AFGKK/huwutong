<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('app.affiliate_landing_page.meta_desc', ['name' => $campaign->name]) }}">
    <meta property="og:title" content="{{ __('app.affiliate_landing_page.og_title', ['name' => $campaign->name, 'app' => site_setting('site_name', __('app.app_name'))]) }}">
    <meta property="og:description" content="{{ __('app.affiliate_landing_page.og_desc', ['amount' => number_format($campaign->reward_first, 0)]) }}">
    <title>{{ __('app.affiliate_landing_page.title_suffix', ['name' => $campaign->name, 'app' => site_setting('site_name', __('app.app_name'))]) }}</title>
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        .landing-card { backdrop-filter: blur(12px); background: rgba(255,255,255,0.85); border: 1px solid rgba(255,255,255,0.3); }
        .reward-amount { background: linear-gradient(135deg, #f59e0b, #ef4444); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .btn-primary { background: linear-gradient(135deg, var(--pg-primary), var(--pg-primary-800)); }
        .btn-primary:hover { background: linear-gradient(135deg, var(--pg-primary-800), var(--pg-primary)); transform: translateY(-1px); box-shadow: 0 8px 25px -5px rgba(var(--pg-primary-rgb), 0.25); }
        .step-number { width: 36px; height: 36px; background: linear-gradient(135deg, var(--pg-primary), var(--pg-primary-800)); display: flex; align-items: center; justify-content: center; border-radius: 50%; color: white; font-weight: 700; font-size: 14px; flex-shrink: 0; }
    </style>
</head>
<body class="bg-gray-50 font-sans antialiased min-h-screen">
    <div class="relative overflow-hidden">
        <!-- 背景装饰 -->
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute -top-40 -right-40 w-80 h-80 bg-slate-100 rounded-full opacity-40 blur-3xl"></div>
            <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-amber-100 rounded-full opacity-40 blur-3xl"></div>
        </div>

        <div class="relative max-w-3xl mx-auto px-4 py-12 md:py-20">
            <!-- 品牌 -->
            <div class="text-center mb-8">
                <a href="{{ url('/') }}" class="inline-flex items-center gap-2">
                    <div class="w-8 h-8 bg-gradient-to-br from-slate-800 to-slate-950 rounded-xl flex items-center justify-center shadow">
                        <span class="text-white font-bold text-sm">{{ mb_substr(site_setting('site_name', __('app.app_name')), 0, 1) }}</span>
                    </div>
                    <span class="font-bold text-lg text-gray-800">{{ site_setting('site_name', __('app.app_name')) }}</span>
                </a>
            </div>

            <!-- 活动卡片 -->
            <div class="landing-card rounded-2xl shadow-xl p-8 md:p-10">
                <!-- 标题 -->
                <div class="text-center mb-8">
                    <div class="inline-block px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full mb-3">{{ $typeLabel }}</div>
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">{{ $campaign->name }}</h1>
                    @if($campaign->description)
                        <p class="text-gray-500 mt-2">{{ $campaign->description }}</p>
                    @endif
                </div>

                <!-- 奖励展示 -->
                <div class="grid grid-cols-3 gap-4 mb-8">
                    <div class="text-center p-4 bg-amber-50 rounded-xl">
                        <div class="text-xs text-gray-500 mb-1">{{ __('app.affiliate_landing_page.reward_first') }}</div>
                        <div class="text-2xl md:text-3xl font-bold reward-amount">¥{{ number_format($campaign->reward_first, 0) }}</div>
                    </div>
                    <div class="text-center p-4 bg-slate-50 rounded-xl">
                        <div class="text-xs text-gray-500 mb-1">{{ __('app.affiliate_landing_page.reward_renewal') }}</div>
                        <div class="text-2xl md:text-3xl font-bold text-slate-800">¥{{ number_format($campaign->reward_renewal, 0) }}</div>
                    </div>
                    <div class="text-center p-4 bg-green-50 rounded-xl">
                        <div class="text-xs text-gray-500 mb-1">{{ __('app.affiliate_landing_page.reward_upgrade') }}</div>
                        <div class="text-2xl md:text-3xl font-bold text-green-600">¥{{ number_format($campaign->reward_upgrade, 0) }}</div>
                    </div>
                </div>

                <!-- 预算进度 -->
                @if($campaign->budget_total > 0)
                <div class="mb-8 p-4 bg-gray-50 rounded-xl">
                    <div class="flex justify-between text-sm text-gray-500 mb-1">
                        <span>{{ __('app.affiliate_landing_page.budget') }}</span>
                        <span>{{ __('app.affiliate_landing_page.budget_left', ['left' => number_format($remaining, 0), 'total' => number_format($campaign->budget_total, 0)]) }}</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        @php $pct = $campaign->budget_total > 0 ? min(100, ($campaign->budget_used ?? 0) / $campaign->budget_total * 100) : 0; @endphp
                        <div class="bg-gradient-to-r from-slate-600 to-slate-900 h-2 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
                @endif

                <!-- 活动时间 -->
                @if($campaign->starts_at || $campaign->ends_at)
                <div class="text-center text-sm text-gray-400 mb-6">
                    @if($campaign->starts_at && $campaign->ends_at)
                        {{ __('app.affiliate_landing_page.period', ['start' => \Carbon\Carbon::parse($campaign->starts_at)->format('Y/m/d'), 'end' => \Carbon\Carbon::parse($campaign->ends_at)->format('Y/m/d')]) }}
                    @elseif($campaign->starts_at)
                        {{ __('app.affiliate_landing_page.starts', ['date' => \Carbon\Carbon::parse($campaign->starts_at)->format('Y/m/d')]) }}
                    @elseif($campaign->ends_at)
                        {{ __('app.affiliate_landing_page.ends', ['date' => \Carbon\Carbon::parse($campaign->ends_at)->format('Y/m/d')]) }}
                    @endif
                </div>
                @endif

                <!-- 参与方式 -->
                <div class="mb-8">
                    <h3 class="font-semibold text-gray-800 mb-4 text-center">{{ __('app.affiliate_landing_page.how_title') }}</h3>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <span class="step-number">1</span>
                            <span class="text-sm text-gray-600">{{ __('app.affiliate_landing_page.step1') }}</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <span class="step-number">2</span>
                            <span class="text-sm text-gray-600">{{ __('app.affiliate_landing_page.step2') }}</span>
                        </div>
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <span class="step-number">3</span>
                            <span class="text-sm text-gray-600">{{ __('app.affiliate_landing_page.step3') }}</span>
                        </div>
                    </div>
                </div>

                <!-- CTA 按钮 -->
                <div class="text-center">
                    @php $registerUrl = url('/build/register?ref=' . $campaign->slug . ($referralCode ? '&referral_code=' . $referralCode : '') . '&redirect=/portal/affiliate'); @endphp
                    <a href="{{ $registerUrl }}"
                       class="btn-primary inline-flex items-center gap-2 text-white font-semibold px-8 py-3.5 rounded-xl shadow-lg transition-all duration-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ __('app.affiliate_landing_page.cta') }}
                    </a>
                    <p class="text-xs text-gray-400 mt-3">{{ __('app.affiliate_landing_page.has_account') }} <a href="{{ url('/build/login') }}" class="text-slate-700 hover:underline">{{ __('app.affiliate_landing_page.login') }}</a></p>
                </div>
            </div>

            <!-- 底部 -->
            @include('public.partials.footer')
        </div>
    </div>
</body>
</html>
