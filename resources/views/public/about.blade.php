<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.seo.about_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}</title>
    <meta name="description" content="{{ __('app.about_page.meta_desc') }}">
    <meta property="og:title" content="{{ __('app.about_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.about_page.subtitle') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/about') }}">
    @include('public.partials.tracking')
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": @json(site_setting('site_name', __('app.app_name'))),
        "url": "{{ url('/') }}",
        "description": @json(__('app.app_description')),
        "email": @json(site_setting('contact_email', '')),
        "address": { "@type": "PostalAddress", "addressCountry": "CN" }
    }
    </script>
    @vite('resources/css/public.css')
    <style>
        .value-card { transition: all 0.25s ease; }
        .value-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.18); }
        .stat-item { transition: all 0.25s ease; }
        .timeline-line { position: relative; }
        .timeline-line::before { content: ''; position: absolute; left: 15px; top: 0; bottom: 0; width: 2px; background: linear-gradient(to bottom, var(--pg-primary), #cbd5e1); }
        .timeline-dot { min-width: 32px; height: 32px; padding: 0 4px; border-radius: 999px; background: var(--pg-primary); display: flex; align-items: center; justify-content: center; color: white; font-size: 11px; font-weight: bold; position: relative; z-index: 1; flex-shrink: 0; }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')

    <!-- ─── Hero ─── -->
    <section class="pt-24 pb-16 md:pb-20 bg-white relative overflow-hidden border-b border-slate-100">
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-slate-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-slate-500 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <nav class="flex items-center gap-1.5 text-sm mb-8 text-slate-500">
                <a href="{{ url('/') }}" class="hover:text-slate-900 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('app.about_page.breadcrumb_home') }}
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-slate-800 font-medium">{{ __('app.nav.about') }}</span>
            </nav>
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 mb-4 tracking-tight">{{ __('app.about_page.title') }}</h1>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">{{ __('app.about_page.subtitle') }}</p>
            </div>
        </div>
    </section>

    <!-- ─── 能力信号（非虚荣数字）─── -->
    <section class="py-12 bg-white border-b border-slate-100">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-6">
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-5 text-left">
                    <div class="text-lg md:text-xl font-bold text-slate-900 tracking-tight">{{ __('app.about_page.sig_crypto') }}</div>
                    <div class="text-sm text-slate-500 mt-1.5">{{ __('app.about_page.sig_crypto_desc') }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-5 text-left">
                    <div class="text-lg md:text-xl font-bold text-slate-900 tracking-tight">{{ __('app.about_page.sig_offline') }}</div>
                    <div class="text-sm text-slate-500 mt-1.5">{{ __('app.about_page.sig_offline_desc') }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-5 text-left">
                    <div class="text-lg md:text-xl font-bold text-slate-900 tracking-tight">{{ __('app.about_page.sig_sdk') }}</div>
                    <div class="text-sm text-slate-500 mt-1.5">{{ __('app.about_page.sig_sdk_desc') }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-slate-50/80 px-5 py-5 text-left">
                    <div class="text-lg md:text-xl font-bold text-slate-900 tracking-tight">{{ __('app.about_page.sig_deploy') }}</div>
                    <div class="text-sm text-slate-500 mt-1.5">{{ __('app.about_page.sig_deploy_desc') }}</div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 使命 ─── -->
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.about_page.mission_title') }}</h2>
                <p class="text-lg text-slate-600 max-w-3xl mx-auto leading-relaxed">
                    {{ __('app.about_page.mission_body') }}
                </p>
            </div>
        </div>
    </section>

    <!-- ─── 核心原则 ─── -->
    <section class="py-16 md:py-20 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.about_page.values_title') }}</h2>
                <p class="text-lg text-slate-600">{{ __('app.about_page.values_subtitle') }}</p>
            </div>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="value-card p-8 rounded-2xl border border-slate-100 bg-white text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.about_page.value_1_title') }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ __('app.about_page.value_1_desc') }}</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-slate-100 bg-white text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.about_page.value_2_title') }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ __('app.about_page.value_2_desc') }}</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-slate-100 bg-white text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.about_page.value_3_title') }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ __('app.about_page.value_3_desc') }}</p>
                </div>
                <div class="value-card p-8 rounded-2xl border border-slate-100 bg-white text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">{{ __('app.about_page.value_4_title') }}</h3>
                    <p class="text-sm text-slate-500 leading-relaxed">{{ __('app.about_page.value_4_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── 交付路径（能力，非虚假时间线）─── -->
    <section class="py-16 md:py-20 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900 mb-4 tracking-tight">{{ __('app.about_page.path_title') }}</h2>
                <p class="text-lg text-slate-600">{{ __('app.about_page.path_subtitle') }}</p>
            </div>
            <div class="space-y-8 timeline-line pl-10">
                <div class="flex items-start gap-4">
                    <div class="timeline-dot text-xs">{{ __('app.about_page.path_1_label') }}</div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('app.about_page.path_1_title') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('app.about_page.path_1_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot text-xs">{{ __('app.about_page.path_2_label') }}</div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('app.about_page.path_2_title') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('app.about_page.path_2_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot text-xs">{{ __('app.about_page.path_3_label') }}</div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('app.about_page.path_3_title') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('app.about_page.path_3_desc') }}</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <div class="timeline-dot text-xs">{{ __('app.about_page.path_4_label') }}</div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">{{ __('app.about_page.path_4_title') }}</h3>
                        <p class="text-sm text-slate-500 mt-1">{{ __('app.about_page.path_4_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ─── CTA ─── -->
    <section class="py-16 bg-slate-900">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-2xl md:text-3xl font-bold text-white mb-4 tracking-tight">{{ __('app.landing.cta_title') }}</h2>
            <p class="text-slate-300 mb-8">{{ __('app.landing.cta_subtitle') }}</p>
            <a href="/build/register" class="inline-block bg-white text-slate-900 px-8 py-3 rounded-xl font-bold hover:bg-slate-100 transition shadow-lg">{{ __('app.landing.cta_button') }} →</a>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
