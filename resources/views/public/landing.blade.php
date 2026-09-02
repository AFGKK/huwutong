<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ site_setting('site_description', __('app.seo.home_description')) }}">
    <meta name="keywords" content="{{ site_setting('site_keywords') }}">
    <meta property="og:title" content="{{ site_setting('site_name') }} - {{ __('app.app_slogan') }}">
    <meta property="og:description" content="{{ __('app.app_description') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/') }}">
    @php
        $ogImage = site_setting('seo_og_image_default') ?: site_setting('logo_url') ?: asset('images/logo.svg');
        if ($ogImage && ! str_starts_with($ogImage, 'http')) {
            $ogImage = url($ogImage);
        }
    @endphp
    @if($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ site_setting('site_name') }} - {{ __('app.app_slogan') }}">
    <meta name="twitter:description" content="{{ __('app.app_description') }}">
    <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    <link rel="canonical" href="{{ url('/') }}">
    <link rel="alternate" hreflang="zh-CN" href="{{ url('/?lang=zh_CN') }}">
    <link rel="alternate" hreflang="en" href="{{ url('/?lang=en') }}">
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preload" as="image" href="{{ asset('images/hero-console.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@500;700;800&display=swap" rel="stylesheet">

    @include('public.partials.tracking')

    <title>{{ __('app.seo.home_title') }}</title>

    @php
        $jsonLdPlans = collect($landingPlans ?? []);
        $jsonLdLowPrice = $jsonLdPlans->min('price_monthly');
        $jsonLdOfferCount = $jsonLdPlans->count();
        $jsonLdLogo = site_setting('logo_url') ?: asset('images/logo.svg');
        if ($jsonLdLogo && ! str_starts_with($jsonLdLogo, 'http')) {
            $jsonLdLogo = url($jsonLdLogo);
        }
    @endphp
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => site_setting('site_name', __('app.app_name')),
        'description' => __('app.app_description'),
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Linux, Windows, macOS',
        'url' => url('/'),
        'offers' => $jsonLdOfferCount > 0 ? [
            '@type' => 'AggregateOffer',
            'lowPrice' => number_format((float) ($jsonLdLowPrice ?? 0), 2, '.', ''),
            'priceCurrency' => 'CNY',
            'offerCount' => $jsonLdOfferCount,
            'url' => url('/pricing'),
        ] : [
            '@type' => 'Offer',
            'price' => '0',
            'priceCurrency' => 'CNY',
            'description' => __('app.landing.pricing_start_free'),
            'url' => url('/pricing'),
        ],
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": @json(site_setting('site_name', __('app.app_name'))),
        "url": "{{ url('/') }}",
        "inLanguage": ["zh-CN", "en"],
        "potentialAction": {
            "@type": "SearchAction",
            "target": {
                "@type": "EntryPoint",
                "urlTemplate": "{{ url('/products?search={search_term_string}') }}"
            },
            "query-input": "required name=search_term_string"
        }
    }
    </script>
    <script type="application/ld+json">
    {!! json_encode(array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => site_setting('site_name', __('app.app_name')),
        'url' => url('/'),
        'description' => __('app.app_description'),
        'logo' => $jsonLdLogo,
        'email' => site_setting('contact_email') ?: null,
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'addressCountry' => 'CN',
            'addressLocality' => site_setting('company_address') ?: null,
        ]),
    ]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
    <script type="application/ld+json">
    {!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect([1,2,3,4,5])->map(fn ($n) => [
            '@type' => 'Question',
            'name' => __('app.landing.faq_'.$n.'_q'),
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => __('app.landing.faq_'.$n.'_a'),
            ],
        ])->values()->all(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    @vite('resources/css/public.css')
    <style>
        html { scroll-behavior: smooth; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px -8px rgba(var(--pg-primary-rgb), 0.12); }
        .product-card { transition: all 0.25s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.22); }
        .aspect-square { aspect-ratio: 1 / 1 !important; }
        .plan-card { transition: all 0.25s ease; border-color: #e2e8f0; }
        .plan-card.popular { border-color: var(--pg-primary); }
        .plan-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.25); }
        /* Logo 墙自动轮播*/
        .logo-track { overflow: hidden; width: 100%; }
        .logo-slide { display: flex; gap: 24px; width: max-content; animation: scrollLeft 30s linear infinite; }
        .logo-track-reverse .logo-slide { animation: scrollRight 30s linear infinite; }
        .logo-item { flex-shrink: 0; width: 140px; }
        .logo-placeholder { width: 140px; height: 72px; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 2px; transition: transform 0.3s; }
        .logo-placeholder:hover { transform: scale(1.08); }
        @keyframes scrollLeft {
            from { transform: translateX(0); }
            to { transform: translateX(-50%); }
        }
        @keyframes scrollRight {
            from { transform: translateX(-50%); }
            to { transform: translateX(0); }
        }
        /* Hero B：左右分栏 */
        .hero-b { --hero-ink: #f8fafc; --hero-muted: rgba(248,250,252,0.78); }
        .hero-b-bg {
            background: linear-gradient(
                145deg,
                color-mix(in srgb, var(--pg-primary) 88%, #020617) 0%,
                var(--pg-primary) 48%,
                color-mix(in srgb, var(--pg-primary) 65%, #334155) 100%
            );
        }
        .hero-b-brand {
            font-family: "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
            letter-spacing: 0.06em;
        }
        .hero-b-title {
            font-family: "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
            letter-spacing: -0.02em;
        }
        .logo-track:hover .logo-slide { animation-play-state: paused; }
        .trust-proof {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 1rem;
            padding: 1.1rem 1.25rem;
            text-align: center;
        }
        .trust-proof-value {
            font-family: "Noto Sans SC", "PingFang SC", "Microsoft YaHei", sans-serif;
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--pg-primary);
            letter-spacing: -0.02em;
            line-height: 1.2;
        }
        .how-code-panel {
            background: linear-gradient(160deg, var(--pg-primary) 0%, color-mix(in srgb, var(--pg-primary) 75%, #020617) 100%);
            border: 1px solid rgba(148, 163, 184, 0.25);
            box-shadow: 0 24px 48px -20px rgba(var(--pg-primary-rgb), 0.45);
        }
        @keyframes heroFadeUp {
            from { opacity: 0; transform: translateY(18px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes heroPanelIn {
            from { opacity: 0; transform: translateY(28px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes heroGlow {
            0%, 100% { opacity: 0.45; }
            50% { opacity: 0.7; }
        }
        .hero-anim-1 { animation: heroFadeUp 0.7s ease-out both; }
        .hero-anim-2 { animation: heroFadeUp 0.7s ease-out 0.12s both; }
        .hero-anim-3 { animation: heroFadeUp 0.7s ease-out 0.24s both; }
        .hero-anim-4 { animation: heroFadeUp 0.7s ease-out 0.36s both; }
        .hero-panel { animation: heroPanelIn 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both; }
        .hero-glow { animation: heroGlow 6s ease-in-out infinite; }
        .hero-console {
            background: linear-gradient(160deg, var(--pg-primary) 0%, var(--pg-primary-800) 55%, var(--pg-primary) 100%);
            border: 1px solid rgba(148, 163, 184, 0.22);
            box-shadow:
                0 30px 60px -20px rgba(var(--pg-primary-rgb), 0.55),
                0 0 0 1px rgba(255,255,255,0.04) inset;
        }
        .hero-cta-primary:hover { transform: translateY(-2px); box-shadow: 0 14px 28px -10px rgba(var(--pg-primary-rgb), 0.45); }
        .hero-cta-secondary:hover { background: rgba(255,255,255,0.14); }
        .hero-stat-strip { border-top: 1px solid rgba(var(--pg-primary-rgb), 0.06); }
        .trust-chip {
            flex-shrink: 0;
            min-width: 120px;
            height: 52px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #fff;
            color: #475569;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.02em;
        }
        .section-kicker {
            display: inline-block;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 12px;
        }
        .feature-card {
            border-color: #e2e8f0;
            background: #fff;
        }
        .feature-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 12px 28px -16px rgba(var(--pg-primary-rgb), 0.2);
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            background: #f1f5f9;
            color: var(--pg-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.1rem;
        }
        .feature-card-centered {
            text-align: center;
        }
        .how-step {
            text-align: center;
        }
        .how-step-number {
            line-height: 1;
        }
        @media (prefers-reduced-motion: reduce) {
            .hero-anim-1, .hero-anim-2, .hero-anim-3, .hero-anim-4, .hero-panel, .hero-glow, .logo-slide {
                animation: none !important;
            }
        }
</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] focus:bg-white focus:px-4 focus:py-2 focus:rounded-lg focus:shadow">{{ __('app.skip_to_content') }}</a>
    @include('public.partials.nav')

    <main id="main-content">

    <!-- ─── Hero 区域（B2B 左右分栏）─── -->
    @php
        $heroBrand = site_setting('site_name', __('app.app_name'));
        $heroLogo = site_setting('logo_url');
    @endphp
    <section class="hero-b relative overflow-hidden pt-24 md:pt-28 pb-16 md:pb-20">
        <div class="absolute inset-0 hero-b-bg"></div>
        <div class="absolute inset-0 opacity-[0.12]" style="background-image: linear-gradient(rgba(255,255,255,0.06) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.06) 1px, transparent 1px); background-size: 48px 48px;"></div>
        <div class="hero-glow absolute -right-24 top-10 w-[36rem] h-[36rem] rounded-full bg-white/10 blur-[100px] pointer-events-none"></div>
        <div class="absolute -left-20 bottom-0 w-[28rem] h-[28rem] rounded-full bg-black/20 blur-[90px] pointer-events-none"></div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-10 lg:gap-8 items-center lg:min-h-[32rem]">
                {{-- 左：品牌 + 标题 + CTA --}}
                <div class="lg:col-span-5 text-left">
                    <div class="hero-anim-1 flex items-center gap-3 mb-6">
                        @if($heroLogo)
                        <img src="{{ $heroLogo }}" alt="{{ $heroBrand }}" class="w-11 h-11 rounded-xl object-contain bg-white/10 p-1 ring-1 ring-white/15">
                        @endif
                        <span class="hero-b-brand text-2xl md:text-3xl font-bold text-white">{{ $heroBrand }}</span>
                    </div>

                    <h1 class="hero-anim-2 hero-b-title text-3xl sm:text-4xl md:text-5xl font-extrabold text-white leading-[1.15] mb-5">
                        {{ __('app.landing.hero_title') }}
                    </h1>

                    <p class="hero-anim-3 text-base md:text-lg text-slate-200/85 max-w-md leading-relaxed mb-8">
                        {{ __('app.landing.hero_subtitle') }}
                    </p>

                    <div class="hero-anim-4 flex flex-col sm:flex-row gap-3 sm:gap-4">
                        <a href="/build/register" class="hero-cta-primary inline-flex items-center justify-center gap-2 bg-white text-slate-900 px-7 py-3.5 rounded-xl font-bold transition-all duration-300 shadow-lg">
                            {{ __('app.landing.hero_cta_primary') }}
                            <span aria-hidden="true">→</span>
                        </a>
                        <a href="/pricing" class="hero-cta-secondary inline-flex items-center justify-center gap-2 bg-white/10 text-white px-7 py-3.5 rounded-xl font-medium transition-all duration-300 border border-white/20 backdrop-blur-sm">
                            {{ __('app.landing.hero_cta_secondary') }}
                        </a>
                    </div>
                </div>

                {{-- 右：产品主视觉（控制台示意图） --}}
                <div class="lg:col-span-7 relative">
                    <div class="absolute -inset-3 rounded-[1.4rem] bg-gradient-to-br from-white/10 via-transparent to-slate-400/10 blur-xl pointer-events-none"></div>
                    <a href="/build/register" class="hero-panel hero-console relative block ml-0 lg:ml-4 lg:-mr-4 xl:-mr-8 group focus:outline-none focus-visible:ring-2 focus-visible:ring-white/40 rounded-2xl p-1.5" aria-label="{{ __('app.landing.hero_cta_primary') }}">
                        <img
                            src="{{ asset('images/hero-console.svg') }}"
                            alt="{{ __('app.landing.hero_visual_alt', ['brand' => $heroBrand]) }}"
                            width="1120"
                            height="680"
                            class="w-full h-auto rounded-xl shadow-2xl ring-1 ring-white/10 transition duration-300 group-hover:ring-white/25"
                            loading="eager"
                            decoding="async"
                            fetchpriority="high"
                        >
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 信任带：能力证明 + 行业覆盖 ─── -->
    <section class="hero-stat-strip bg-white py-12 md:py-14 border-b border-slate-100" aria-label="{{ __('app.landing.trust_kicker') }}">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center mb-8">
                <span class="section-kicker">{{ __('app.landing.trust_kicker') }}</span>
                <h2 class="text-2xl md:text-3xl font-bold text-slate-900 tracking-tight">{{ __('app.landing.trust_title') }}</h2>
                <p class="text-slate-600 mt-2">{{ __('app.landing.trust_subtitle') }}</p>
            </div>

            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-10 max-w-6xl mx-auto">
                <div class="trust-proof">
                    <div class="trust-proof-value">{{ __('app.landing.trust_proof_1_value') }}</div>
                    <div class="text-sm font-semibold text-slate-900 mt-1">{{ __('app.landing.trust_proof_1_label') }}</div>
                    <div class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('app.landing.trust_proof_1_desc') }}</div>
                </div>
                <div class="trust-proof">
                    <div class="trust-proof-value">{{ __('app.landing.trust_proof_2_value') }}</div>
                    <div class="text-sm font-semibold text-slate-900 mt-1">{{ __('app.landing.trust_proof_2_label') }}</div>
                    <div class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('app.landing.trust_proof_2_desc') }}</div>
                </div>
                <div class="trust-proof">
                    <div class="trust-proof-value">{{ __('app.landing.trust_proof_3_value') }}</div>
                    <div class="text-sm font-semibold text-slate-900 mt-1">{{ __('app.landing.trust_proof_3_label') }}</div>
                    <div class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('app.landing.trust_proof_3_desc') }}</div>
                </div>
                <div class="trust-proof">
                    <div class="trust-proof-value">{{ __('app.landing.trust_proof_4_value') }}</div>
                    <div class="text-sm font-semibold text-slate-900 mt-1">{{ __('app.landing.trust_proof_4_label') }}</div>
                    <div class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('app.landing.trust_proof_4_desc') }}</div>
                </div>
            </div>

            <p class="text-center text-sm text-slate-500 mb-5">{{ __('app.landing.trust_industries') }}</p>
            <div class="logo-track" aria-hidden="true">
                <div class="logo-slide gap-3">
                    @php $industries = array_merge(__('app.landing.industries'), __('app.landing.industries')); @endphp
                    @foreach($industries as $industry)
                    <div class="logo-item" style="width:auto"><div class="trust-chip">{{ $industry }}</div></div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 产品特性 ─── -->
    <section id="features" class="py-20 md:py-28 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center mb-14">
                <span class="section-kicker">{{ __('app.landing.features_kicker') }}</span>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.landing.features_title') }}</h2>
                <p class="text-lg text-slate-600 leading-relaxed">{{ __('app.landing.features_subtitle') }}</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5 md:gap-6 max-w-6xl mx-auto">
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_secure_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_secure_desc') }}</p>
                </div>
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_perf_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_perf_desc') }}</p>
                </div>
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_sdk_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_sdk_desc') }}</p>
                </div>
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_deploy_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_deploy_desc') }}</p>
                </div>
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_ops_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_ops_desc') }}</p>
                </div>
                <div class="feature-card feature-card-centered p-7 rounded-2xl border transition-all">
                    <div class="feature-icon">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.landing.feat_compliance_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed text-[15px]">{{ __('app.landing.feat_compliance_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 工作原理（B2B 主路径：紧随能力区）─── -->
    <section id="how-it-works" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center mb-14">
                <span class="section-kicker">{{ __('app.landing.how_kicker') }}</span>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.landing.how_title') }}</h2>
                <p class="text-lg text-slate-600 leading-relaxed">{{ __('app.landing.how_subtitle') }}</p>
            </div>
            <div class="grid md:grid-cols-3 gap-8 md:gap-10 max-w-5xl mx-auto">
                <div class="how-step relative">
                    <div class="how-step-number text-5xl font-extrabold text-slate-100 mb-3 select-none">01</div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">{{ __('app.landing.how_1_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed max-w-xs mx-auto">{{ __('app.landing.how_1_desc') }}</p>
                </div>
                <div class="how-step relative">
                    <div class="how-step-number text-5xl font-extrabold text-slate-100 mb-3 select-none">02</div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">{{ __('app.landing.how_2_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed max-w-xs mx-auto">{{ __('app.landing.how_2_desc') }}</p>
                </div>
                <div class="how-step relative">
                    <div class="how-step-number text-5xl font-extrabold text-slate-100 mb-3 select-none">03</div>
                    <h3 class="text-xl font-semibold text-slate-900 mb-2">{{ __('app.landing.how_3_title') }}</h3>
                    <p class="text-slate-600 leading-relaxed max-w-xs mx-auto">{{ __('app.landing.how_3_desc') }}</p>
                </div>
            </div>
            <div class="mt-14 how-code-panel rounded-2xl p-6 md:p-8 max-w-3xl mx-auto">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-2.5 h-2.5 bg-rose-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
                    <span class="text-slate-400 text-sm ml-2">{{ __('app.landing.how_code_label') }}</span>
                </div>
@verbatim
                <pre class="text-sm text-slate-200 font-mono leading-relaxed overflow-x-auto"><code>// composer require huwutong/huwutong-sdk-php
<span class="text-sky-300">$client</span> = <span class="text-violet-300">new</span> <span class="text-emerald-300">HWTClient</span>(<span class="text-amber-300">'your_api_key'</span>);
<span class="text-sky-300">$result</span> = <span class="text-sky-300">$client</span>-><span class="text-yellow-200">validate</span>(<span class="text-amber-300">'HWT-ENT-XXXX-XXXX'</span>);

<span class="text-slate-400">if</span> (<span class="text-sky-300">$result</span>-><span class="text-yellow-200">isValid</span>()) {
    <span class="text-slate-400">echo</span> <span class="text-emerald-300">"License valid"</span>;
}</code></pre>
@endverbatim
            </div>
        </div>
    </section>

    <!-- ─── 开放平台（次级能力，收敛营销卡）─── -->
    <section class="py-20 md:py-24 bg-white border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-12 gap-10 lg:gap-14 items-start">
                <div class="lg:col-span-5">
                    <span class="section-kicker">{{ __('app.landing.open_kicker') }}</span>
                    <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.landing.open_title') }}</h2>
                    <p class="text-lg text-slate-600 leading-relaxed mb-8">{{ __('app.landing.open_subtitle') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ url('/docs') }}" class="inline-flex items-center gap-2 bg-slate-900 text-white px-6 py-3 rounded-xl font-semibold hover:bg-slate-800 transition">{{ __('app.landing.open_cta') }}</a>
                        <a href="{{ url('/marketplace') }}" class="inline-flex items-center gap-2 border border-slate-300 text-slate-700 px-6 py-3 rounded-xl font-medium hover:border-slate-400 hover:bg-white transition">{{ __('app.landing.open_market') }}</a>
                    </div>
                </div>
                <div class="lg:col-span-7 grid sm:grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ __('app.landing.open_dev_title') }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ __('app.landing.open_dev_desc') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ __('app.landing.open_version_title') }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ __('app.landing.open_version_desc') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ __('app.landing.open_settle_title') }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ __('app.landing.open_settle_desc') }}</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900 mb-1">{{ __('app.landing.open_mod_title') }}</h3>
                        <p class="text-sm text-slate-600 leading-relaxed">{{ __('app.landing.open_mod_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 定价预览 ─── -->
    <section class="py-20 md:py-28 bg-slate-50 border-y border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center mb-12">
                <span class="section-kicker">{{ __('app.landing.pricing_kicker') }}</span>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.landing.pricing_title') }}</h2>
                <p class="text-lg text-slate-600 leading-relaxed">{{ __('app.landing.pricing_subtitle') }}</p>
            </div>
            <div class="flex justify-center mb-8">
                <div id="landing-billing-toggle" class="inline-flex items-center bg-white border border-slate-200 rounded-xl p-1">
                    <button onclick="switchLandingBilling('monthly')" id="landing-mo-btn" class="px-5 py-2 rounded-lg font-medium transition bg-slate-900 text-white shadow-sm">{{ __('app.landing.pricing_monthly') }}</button>
                    <button onclick="switchLandingBilling('yearly')" id="landing-yr-btn" class="px-5 py-2 rounded-lg font-medium text-slate-500 hover:text-slate-900">{{ __('app.landing.pricing_yearly') }}<span class="ml-1 text-xs text-emerald-600 font-medium">{{ __('app.landing.pricing_save') }}</span></button>
                </div>
            </div>
            <div id="landing-plans" class="grid md:grid-cols-3 lg:grid-cols-4 gap-5 max-w-6xl mx-auto min-h-[22rem]" aria-busy="false">
                @forelse(($landingPlans ?? []) as $plan)
                    @php
                        $monthly = (float) ($plan['price_monthly'] ?? 0);
                        $yearly = (float) ($plan['price_yearly'] ?? 0);
                        $isFree = $monthly === 0.0 && $yearly === 0.0;
                        $isPopular = ($plan['badge'] ?? '') === 'popular';
                        $isBest = ($plan['badge'] ?? '') === 'best_value';
                        $features = is_array($plan['features'] ?? null) ? $plan['features'] : [];
                    @endphp
                    <div class="plan-card rounded-xl border {{ $isPopular ? 'border-slate-900 popular' : 'border-slate-200' }} bg-white p-6 flex flex-col"
                         data-price-monthly="{{ $monthly }}"
                         data-price-yearly="{{ $yearly }}"
                         data-plan-id="{{ $plan['id'] ?? '' }}">
                        @if($isPopular)
                        <div class="text-xs font-semibold text-slate-800 bg-slate-100 rounded-full px-3 py-1 mb-3 inline-block self-start">{{ __('app.landing.pricing_popular') }}</div>
                        @elseif($isBest)
                        <div class="text-xs font-semibold text-slate-700 bg-slate-100 rounded-full px-3 py-1 mb-3 inline-block self-start">{{ __('app.landing.pricing_best_value') }}</div>
                        @endif
                        <h3 class="text-lg font-bold text-slate-900">{{ $plan['name'] ?? '' }}</h3>
                        <p class="text-sm text-slate-500 mt-1 mb-4">{{ $plan['description'] ?? '' }}</p>
                        <div class="mb-4 min-h-[3.5rem]">
                            <span class="text-3xl font-bold text-slate-900 landing-plan-price">¥{{ $isFree ? '0' : number_format($monthly, $monthly == floor($monthly) ? 0 : 2) }}</span>
                            <span class="text-slate-500 text-sm landing-plan-period">{{ __('app.landing.pricing_per_month') }}</span>
                            <div class="text-xs text-emerald-600 mt-1 landing-plan-hint invisible min-h-[1rem]" aria-hidden="true">&nbsp;</div>
                        </div>
                        <ul class="space-y-2 text-sm text-slate-600 flex-1 mb-6">
                            @foreach($features as $feature)
                            <li class="flex items-start gap-2">
                                <svg class="w-4 h-4 text-slate-700 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                {{ $feature }}
                            </li>
                            @endforeach
                        </ul>
                        <a href="{{ !empty($plan['id']) ? url('/build/subscribe/'.$plan['id']).'?period=monthly' : url('/pricing') }}"
                           class="landing-plan-cta block w-full text-center py-3 rounded-xl font-semibold transition {{ $isFree ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-slate-900 text-white hover:bg-slate-800' }} text-sm">
                            {{ $isFree ? __('app.landing.pricing_start_free') : __('app.landing.pricing_subscribe') }}
                        </a>
                    </div>
                @empty
                    @for($i = 0; $i < 4; $i++)
                    <div class="rounded-xl border border-slate-200 bg-white p-6 animate-pulse" aria-hidden="true">
                        <div class="h-4 w-16 bg-slate-200 rounded mb-4"></div>
                        <div class="h-5 w-24 bg-slate-200 rounded mb-2"></div>
                        <div class="h-3 w-full bg-slate-100 rounded mb-1"></div>
                        <div class="h-3 w-4/5 bg-slate-100 rounded mb-6"></div>
                        <div class="h-8 w-28 bg-slate-200 rounded mb-6"></div>
                        <div class="space-y-2 mb-6">
                            <div class="h-3 w-full bg-slate-100 rounded"></div>
                            <div class="h-3 w-5/6 bg-slate-100 rounded"></div>
                            <div class="h-3 w-4/5 bg-slate-100 rounded"></div>
                        </div>
                        <div class="h-10 w-full bg-slate-200 rounded-xl"></div>
                    </div>
                    @endfor
                @endforelse
            </div>
            <script type="application/json" id="landing-plans-data">@json($landingPlans ?? [])</script>
            <div class="mt-10 text-center">
                <a href="/pricing" class="inline-flex items-center gap-2 border border-slate-300 bg-white text-slate-800 px-7 py-3 rounded-xl font-semibold hover:border-slate-400 transition">
                    {{ __('app.landing.pricing_full') }}
                </a>
            </div>
        </div>
    </section>

    <!-- ─── 精选产品─── -->
    <section id="featured-products" class="py-20 md:py-28 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto text-center mb-12">
                <span class="section-kicker">{{ __('app.landing.catalog_kicker') }}</span>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.landing.catalog_title') }}</h2>
                <p class="text-lg text-slate-600 leading-relaxed">{{ __('app.landing.catalog_subtitle') }}</p>
            </div>
            <div id="landing-products" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                @forelse($featuredProducts as $product)
                    <div class="product-card bg-white rounded-2xl border border-slate-200 overflow-hidden flex flex-col group relative hover:border-slate-300 transition">
                        <a href="{{ url('/products/'.$product->slug) }}" class="block flex flex-col flex-1">
                        <div class="aspect-[4/3] bg-slate-100 relative overflow-hidden">
                            @if($product->image_url)
                                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-[1.03] transition duration-300" loading="lazy">
                            @else
                                <div class="h-full flex flex-col items-center justify-center p-6 text-center">
                                    <div class="w-12 h-12 rounded-xl bg-slate-200/80 flex items-center justify-center mb-3">
                                        <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                                    </div>
                                    <span class="text-sm text-slate-500 line-clamp-2">{{ $product->name }}</span>
                                </div>
                            @endif
                            <div class="absolute top-2.5 left-2.5 flex flex-wrap gap-1.5 max-w-[70%]">
                                @if($product->is_new ?? false)
                                    <span class="px-2 py-0.5 bg-slate-900/90 text-white text-[11px] font-medium rounded-md">{{ __('app.landing.catalog_new') }}</span>
                                @endif
                                @if($product->demo_enabled ?? false)
                                    <span class="px-2 py-0.5 bg-white/95 text-slate-700 text-[11px] font-medium rounded-md border border-slate-200">{{ __('app.landing.catalog_demo') }}</span>
                                @endif
                            </div>
                            <button type="button" onclick="toggleWishlist(event, {{ $product->id }})"
                                class="absolute top-2.5 right-2.5 w-8 h-8 bg-white/90 rounded-lg flex items-center justify-center shadow-sm hover:bg-white transition-all z-10 wishlist-btn border border-slate-200/80"
                                data-product-id="{{ $product->id }}"
                                aria-pressed="false"
                                title="{{ __('app.landing.catalog_wishlist') }}">
                                <svg class="w-4 h-4 text-slate-400 transition-colors wishlist-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="p-4 flex flex-col flex-1">
                            <div class="flex items-start justify-between gap-2 mb-2">
                                <h3 class="text-base font-semibold text-slate-900 group-hover:text-slate-700 transition line-clamp-1">{{ $product->name }}</h3>
                                @if($product->version)
                                    <span class="text-[11px] bg-slate-100 text-slate-500 px-1.5 py-0.5 rounded shrink-0">v{{ $product->version }}</span>
                                @endif
                            </div>
                            @if($product->category)
                                <span class="text-[11px] text-slate-600 bg-slate-100 px-2 py-0.5 rounded inline-block w-fit mb-2">{{ $product->category->name }}</span>
                            @endif
                            <p class="text-sm text-slate-500 line-clamp-2 mb-3 flex-1">{{ $product->description ?: __('app.landing.catalog_fallback_desc') }}</p>
                            <div class="flex items-end justify-between pt-3 border-t border-slate-100 mt-auto">
                                <div>
                                    @if($product->lowest_price)
                                        <span class="text-lg font-bold text-slate-900">¥{{ number_format($product->lowest_price, 2) }}</span>
                                        @if($product->highest_price && $product->highest_price > $product->lowest_price)
                                            <span class="text-xs text-slate-400">{{ __('app.landing.catalog_from') }}</span>
                                        @endif
                                        <span class="text-xs text-slate-400 ml-0.5">{{ __('app.landing.catalog_per_month') }}</span>
                                    @else
                                        <span class="text-sm text-slate-400">{{ __('app.landing.catalog_inquire') }}</span>
                                    @endif
                                </div>
                                <div class="text-right text-xs text-slate-400">
                                    <div>{{ __('app.landing.catalog_licenses', ['count' => $product->licenses_count ?? 0]) }}</div>
                                </div>
                            </div>
                        </div>
                        </a>
                    </div>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-slate-200 p-8 flex items-center justify-center min-h-[160px]">
                        <p class="text-slate-400 text-sm text-center">{{ __('app.landing.catalog_empty') }}<br><a href="/products" class="text-slate-800 hover:underline font-medium">{{ __('app.landing.catalog_empty_link') }}</a></p>
                    </div>
                @endforelse
            </div>
            <div class="mt-10 text-center">
                <a href="/products" class="inline-flex items-center gap-2 border border-slate-300 bg-white text-slate-800 px-7 py-3 rounded-xl font-semibold hover:border-slate-400 transition">
                    {{ __('app.landing.catalog_all') }}
                </a>
            </div>
        </div>
    </section>

    <!-- ─── 生态（FAQ / CTA 之前，避免打断转化）─── -->
    <section class="py-12 md:py-14 bg-slate-50 border-t border-slate-100" id="ecosystem">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-8">
                <div>
                    <span class="section-kicker">{{ __('app.landing.eco_kicker') }}</span>
                    <h2 class="text-xl md:text-2xl font-bold text-slate-900 tracking-tight">{{ __('app.landing.eco_title') }}</h2>
                    <p class="text-sm text-slate-500 mt-1">{{ __('app.landing.eco_subtitle') }}</p>
                </div>
                <div class="flex flex-wrap gap-4 text-sm">
                    <a href="/build/channels" class="font-medium text-slate-700 hover:text-slate-900 transition">{{ __('app.landing.eco_channels') }}</a>
                    <a href="/build/community" class="font-medium text-slate-500 hover:text-slate-800 transition">{{ __('app.landing.eco_community') }}</a>
                </div>
            </div>
            <div id="channels-grid" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="text-center text-slate-400 py-6 col-span-full text-sm">{{ __('app.landing.eco_loading') }}</div>
            </div>
        </div>
    </section>

    <!-- ─── FAQ ─── -->
    <section id="faq" class="py-20 md:py-28 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mx-auto text-center mb-12">
                <span class="section-kicker">{{ __('app.landing.faq_kicker') }}</span>
                <h2 class="text-3xl md:text-4xl font-bold text-slate-900 tracking-tight">{{ __('app.landing.faq_title') }}</h2>
            </div>
            <div class="space-y-3">
                @foreach([1,2,3,4,5] as $faqN)
                <div class="bg-slate-50 rounded-xl border border-slate-200 p-5 md:p-6">
                    <button type="button" onclick="toggleFaq(this)" class="flex items-center justify-between w-full text-left gap-4"
                            aria-expanded="false" aria-controls="faq-panel-{{ $faqN }}" id="faq-btn-{{ $faqN }}">
                        <span class="font-semibold text-slate-900">{{ __('app.landing.faq_'.$faqN.'_q') }}</span>
                        <svg class="w-5 h-5 text-slate-400 transition shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div id="faq-panel-{{ $faqN }}" role="region" aria-labelledby="faq-btn-{{ $faqN }}" class="faq-content hidden mt-4 text-slate-600 leading-relaxed">
                        {{ __('app.landing.faq_'.$faqN.'_a') }}
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ─── CTA ─── -->
    <section class="py-20" style="background: var(--pg-primary);">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold text-white mb-4 tracking-tight">{{ __('app.landing.cta_title') }}</h2>
            <p class="text-lg text-slate-300 mb-8 max-w-2xl mx-auto">{{ __('app.landing.cta_subtitle') }}</p>
            <a href="/build/register" class="hero-cta-primary inline-flex items-center gap-2 bg-white text-slate-900 px-10 py-4 rounded-xl font-bold text-lg transition-all shadow-xl">
                {{ __('app.landing.cta_button') }}<span aria-hidden="true">→</span>
            </a>
            <p class="text-slate-400 text-sm mt-4">{{ __('app.landing.cta_note') }}</p>
        </div>
    </section>
    </main>

    <script>
    (async function() {
        var i18n = {
            empty: @json(__('app.landing.eco_empty')),
            failed: @json(__('app.landing.eco_failed')),
            official: @json(__('app.landing.eco_official')),
            followers: @json(__('app.landing.eco_followers')),
        };
        try {
            var r = await fetch('/api/official-accounts?per_page=4&sort=followers');
            var d = await r.json();
            var accounts = d.data?.data || d.data || [];
            var container = document.getElementById('channels-grid');
            if (!accounts.length) { container.innerHTML = '<div class="text-center text-slate-300 py-6 col-span-full text-sm">'+i18n.empty+'</div>'; return; }
            container.innerHTML = accounts.map(function(a) {
                var initial = (a.name||'?').charAt(0);
                var avatar = a.avatar ? '<img src="'+a.avatar+'" class="w-full h-full object-cover" alt="" />' : '<span class="text-sm font-bold">'+initial+'</span>';
                var followers = i18n.followers.replace(':count', a.followers_count||0);
                return '<div class="bg-white rounded-xl border border-slate-200 p-4 hover:border-slate-300 transition cursor-pointer" onclick="window.open(\'/build/channels\',\'_blank\')" role="link" tabindex="0">'
                    +'<div class="flex items-center gap-3">'
                    +'<div class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-700 overflow-hidden shrink-0">'+avatar+'</div>'
                    +'<div class="flex-1 min-w-0">'
                    +'<div class="text-sm font-semibold text-slate-900 truncate">'+(a.name||i18n.official)+'</div>'
                    +'<div class="text-xs text-slate-400 truncate">'+(a.description||followers)+'</div>'
                    +'</div></div></div>';
            }).join('');
        } catch(e) { document.getElementById('channels-grid').innerHTML = '<div class="text-center text-slate-300 py-6 col-span-full text-sm">'+i18n.failed+'</div>'; }
    })();
    </script>

    <!-- ─── Footer ─── -->
    @include('public.partials.footer')

    <script>
    // FAQ toggle
    function toggleFaq(btn) {
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('svg');
        content.classList.toggle('hidden');
        const isOpen = !content.classList.contains('hidden');
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        if (icon) icon.classList.toggle('rotate-180', isOpen);
    }

    // Pricing plans — 服务端已 SSR，JS 仅做月/年切换，避免空窗 CLS
    const LANDING_I18N = {
        popular: @json(__('app.landing.pricing_popular')),
        bestValue: @json(__('app.landing.pricing_best_value')),
        perMonth: @json(__('app.landing.pricing_per_month')),
        perMonthYearly: @json(__('app.landing.pricing_per_month_yearly')),
        yearlyHint: @json(__('app.landing.pricing_yearly_hint')),
        startFree: @json(__('app.landing.pricing_start_free')),
        subscribe: @json(__('app.landing.pricing_subscribe')),
    };
    let landingBilling = 'monthly';
    let landingPlans = [];
    try {
        const raw = document.getElementById('landing-plans-data');
        landingPlans = raw ? JSON.parse(raw.textContent || '[]') : [];
    } catch (e) {
        landingPlans = [];
    }

    function formatLandingPrice(n) {
        const num = Number(n) || 0;
        return num.toLocaleString(undefined, { maximumFractionDigits: 2 });
    }

    /** price_yearly 可能是「年总额」或「年付折合月价」 */
    function resolveLandingYearly(monthly, yearly) {
        const m = Number(monthly) || 0;
        const y = Number(yearly) || 0;
        if (y <= 0) {
            return { display: m, period: 'month', hint: '' };
        }
        if (y < m) {
            const saved = Math.max(0, (m - y) * 12);
            return {
                display: y,
                period: 'month_yearly',
                hint: saved > 0
                    ? LANDING_I18N.yearlyHint.replace(':annual', formatLandingPrice(y * 12)).replace(':saved', formatLandingPrice(saved))
                    : '',
            };
        }
        const monthlyEquiv = Math.round((y / 12) * 100) / 100;
        const saved = Math.max(0, m * 12 - y);
        return {
            display: monthlyEquiv,
            period: 'month_yearly',
            hint: saved > 0
                ? LANDING_I18N.yearlyHint.replace(':annual', formatLandingPrice(y)).replace(':saved', formatLandingPrice(saved))
                : '',
        };
    }

    function updateLandingPlanPrices() {
        const isYearly = landingBilling === 'yearly';
        document.querySelectorAll('#landing-plans .plan-card').forEach(function (card) {
            const monthly = parseFloat(card.dataset.priceMonthly) || 0;
            const yearly = parseFloat(card.dataset.priceYearly) || 0;
            const planId = card.dataset.planId || '';
            const isFree = monthly === 0 && yearly === 0;
            const resolved = isYearly && !isFree
                ? resolveLandingYearly(monthly, yearly)
                : { display: monthly, period: 'month', hint: '' };
            const priceEl = card.querySelector('.landing-plan-price');
            const periodEl = card.querySelector('.landing-plan-period');
            const hintEl = card.querySelector('.landing-plan-hint');
            const ctaEl = card.querySelector('.landing-plan-cta');
            if (priceEl) {
                priceEl.textContent = '¥' + formatLandingPrice(isFree ? 0 : resolved.display);
            }
            if (periodEl) {
                periodEl.textContent = resolved.period === 'month_yearly'
                    ? LANDING_I18N.perMonthYearly
                    : LANDING_I18N.perMonth;
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
            if (ctaEl && planId) {
                ctaEl.setAttribute('href', '/build/subscribe/' + planId + (isYearly ? '?period=yearly' : '?period=monthly'));
            }
        });
    }

    function switchLandingBilling(period) {
        landingBilling = period === 'yearly' ? 'yearly' : 'monthly';
        const moBtn = document.getElementById('landing-mo-btn');
        const yrBtn = document.getElementById('landing-yr-btn');
        const active = ['bg-slate-900', 'text-white', 'shadow-sm'];
        const idle = ['text-slate-500'];
        moBtn.classList.remove(...active, ...idle, 'bg-white', 'text-gray-900', 'text-gray-500');
        yrBtn.classList.remove(...active, ...idle, 'bg-white', 'text-gray-900', 'text-gray-500');
        if (landingBilling === 'monthly') {
            moBtn.classList.add(...active);
            yrBtn.classList.add(...idle);
        } else {
            yrBtn.classList.add(...active);
            moBtn.classList.add(...idle);
        }
        updateLandingPlanPrices();
    }

    // 无 SSR 数据时回退拉取（极少见）
    if (!landingPlans.length && !document.querySelector('#landing-plans .plan-card[data-price-monthly]')) {
        const grid = document.getElementById('landing-plans');
        if (grid) grid.setAttribute('aria-busy', 'true');
        fetch('/api/public/pricing-plans')
            .then(r => r.json())
            .then(data => {
                landingPlans = Array.isArray(data.data) ? data.data : [];
                if (!landingPlans.length || !grid) return;
                grid.innerHTML = landingPlans.map(function (plan) {
                    const monthly = Number(plan.price_monthly) || 0;
                    const yearly = Number(plan.price_yearly) || 0;
                    const isFree = monthly === 0 && yearly === 0;
                    const features = (plan.features || []).map(function (f) {
                        return '<li class="flex items-start gap-2"><svg class="w-4 h-4 text-slate-700 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>'+f+'</li>';
                    }).join('');
                    const badge = plan.badge === 'popular'
                        ? '<div class="text-xs font-semibold text-slate-800 bg-slate-100 rounded-full px-3 py-1 mb-3 inline-block self-start">'+LANDING_I18N.popular+'</div>'
                        : (plan.badge === 'best_value'
                            ? '<div class="text-xs font-semibold text-slate-700 bg-slate-100 rounded-full px-3 py-1 mb-3 inline-block self-start">'+LANDING_I18N.bestValue+'</div>'
                            : '');
                    return '<div class="plan-card rounded-xl border '+(plan.badge === 'popular' ? 'border-slate-900 popular' : 'border-slate-200')+' bg-white p-6 flex flex-col"'
                        + ' data-price-monthly="'+monthly+'" data-price-yearly="'+yearly+'" data-plan-id="'+(plan.id || '')+'">'
                        + badge
                        + '<h3 class="text-lg font-bold text-slate-900">'+(plan.name || '')+'</h3>'
                        + '<p class="text-sm text-slate-500 mt-1 mb-4">'+(plan.description || '')+'</p>'
                        + '<div class="mb-4 min-h-[3.5rem]"><span class="text-3xl font-bold text-slate-900 landing-plan-price">¥'+formatLandingPrice(monthly)+'</span>'
                        + '<span class="text-slate-500 text-sm landing-plan-period">'+LANDING_I18N.perMonth+'</span>'
                        + '<div class="text-xs text-emerald-600 mt-1 landing-plan-hint invisible min-h-[1rem]" aria-hidden="true">&nbsp;</div></div>'
                        + '<ul class="space-y-2 text-sm text-slate-600 flex-1 mb-6">'+features+'</ul>'
                        + '<a href="'+(plan.id ? ('/build/subscribe/'+plan.id+'?period=monthly') : '/pricing')+'" class="landing-plan-cta block w-full text-center py-3 rounded-xl font-semibold transition '
                        + (isFree ? 'bg-slate-100 text-slate-700 hover:bg-slate-200' : 'bg-slate-900 text-white hover:bg-slate-800')
                        + ' text-sm">'+(isFree ? LANDING_I18N.startFree : LANDING_I18N.subscribe)+'</a></div>';
                }).join('');
                grid.setAttribute('aria-busy', 'false');
            })
            .catch(function () {
                if (grid) grid.setAttribute('aria-busy', 'false');
            });
    }

    // ─── 收藏夹功能───
    const wishlistedIds = new Set();
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    // 加载已收藏的产品 ID
    function loadWishlist() {
        fetch('/api/wishlist/my/product-ids', {
            headers: { 'Accept': 'application/json' },
            credentials: 'include',
        })
        .then(r => r.ok ? r.json() : Promise.reject())
        .then(data => {
            (data.data?.product_ids || []).forEach(id => wishlistedIds.add(parseInt(id, 10)));
            document.querySelectorAll('.wishlist-btn').forEach(btn => {
                const id = parseInt(btn.dataset.productId, 10);
                if (wishlistedIds.has(id)) setWishlisted(btn, true);
            });
        })
        .catch(() => {});
    }

    // 设置收藏图标状态
    function setWishlisted(btn, state) {
        const icon = btn.querySelector('.wishlist-icon');
        if (!icon) return;
        if (state) {
            icon.classList.add('text-red-500', 'fill-current');
            icon.classList.remove('text-slate-400', 'text-gray-400');
            btn.classList.add('opacity-100');
            btn.setAttribute('aria-pressed', 'true');
        } else {
            icon.classList.remove('text-red-500', 'fill-current');
            icon.classList.add('text-slate-400');
            btn.setAttribute('aria-pressed', 'false');
        }
    }

    // 切换收藏 — 以接口返回为准（有 item.id = 已收藏）
    function toggleWishlist(e, productId) {
        e.preventDefault();
        e.stopPropagation();

        const btn = e.currentTarget;
        if (btn.dataset.loading === '1') return;
        productId = parseInt(productId, 10);
        if (!productId) return;

        btn.dataset.loading = '1';
        btn.setAttribute('aria-busy', 'true');

        fetch('/api/wishlist/toggle', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'include',
            body: JSON.stringify({ product_id: productId }),
        })
        .then(r => {
            if (r.status === 401) {
                window.location.href = '/build/login?redirect=' + encodeURIComponent(window.location.href);
                return null;
            }
            return r.json();
        })
        .then(data => {
            if (!data || data.success === false) return;
            // 优先用 wishlisted 字段；兼容旧响应（有 id = 已收藏）
            const wishlisted = typeof data.data?.wishlisted === 'boolean'
                ? data.data.wishlisted
                : !!(data.data && data.data.id);
            if (wishlisted) {
                wishlistedIds.add(productId);
                setWishlisted(btn, true);
                btn.classList.add('animate-bounce');
                setTimeout(() => btn.classList.remove('animate-bounce'), 300);
            } else {
                wishlistedIds.delete(productId);
                setWishlisted(btn, false);
            }
        })
        .catch(() => {})
        .finally(() => {
            btn.dataset.loading = '0';
            btn.removeAttribute('aria-busy');
        });
    }

    // 页面加载后加载收藏状态
    document.addEventListener('DOMContentLoaded', loadWishlist);
    </script>
</body>
</html>
