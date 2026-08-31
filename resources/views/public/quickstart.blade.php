<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ __('app.quickstart_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
<meta name="description" content="{{ __('app.quickstart_page.meta_desc') }}">
<meta property="og:title" content="{{ __('app.quickstart_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
<meta property="og:description" content="{{ __('app.quickstart_page.subtitle') }}">
<meta property="og:type" content="website">
<link rel="canonical" href="{{ url('/docs/quickstart') }}">
@include('public.partials.tracking')
@vite('resources/css/public.css')
<style>.step-card:hover { transform: translateX(4px); } .step-card { transition: all 0.3s ease; }</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.quickstart_page.title'),
        'heroSubtitle' => __('app.quickstart_page.subtitle'),
    ])
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-8">
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">1</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step1_title') }}</h2>
                        <p class="text-gray-600 mb-3">{{ __('app.quickstart_page.step1_desc') }}</p>
                        <a href="/build/register" class="text-slate-800 font-medium hover:text-slate-900 transition text-sm">{{ __('app.quickstart_page.register') }}</a>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">2</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step2_title') }}</h2>
                        <p class="text-gray-600 mb-3">{{ __('app.quickstart_page.step2_desc') }}</p>
                        <div class="bg-gray-50 rounded-xl p-4 mb-3"><code class="text-sm text-gray-800">composer require huwutong/sdk</code></div>
                        <a href="/sdk" class="text-slate-800 font-medium hover:text-slate-900 transition text-sm">{{ __('app.quickstart_page.view_sdk') }}</a>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">3</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step3_title') }}</h2>
                        <p class="text-gray-600 mb-3">{{ __('app.quickstart_page.step3_desc') }}</p>
                        <div class="bg-gray-900 rounded-2xl p-6 shadow-xl">
                            <pre class="text-sm text-gray-200 font-mono leading-relaxed overflow-x-auto"><code><span class="text-blue-400">$client</span> = <span class="text-purple-400">new</span> <span class="text-green-400">HWT\Client</span>(<span class="text-orange-400">'your_api_key'</span>);
<span class="text-blue-400">$result</span> = <span class="text-blue-400">$client</span>-><span class="text-yellow-300">validate</span>(<span class="text-orange-400">'HWT-ENT-XXXX-XXXX'</span>);

<span class="text-gray-400">if</span> (<span class="text-blue-400">$result</span>-><span class="text-yellow-300">isValid</span>()) {
    <span class="text-gray-400">echo</span> <span class="text-green-400">"{{ __('app.quickstart_page.verify_ok') }}"</span>;
}</code></pre>
                        </div>
                    </div>
                </div>
                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">4</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step4_title') }}</h2>
                        <p class="text-gray-600">{{ __('app.quickstart_page.step4_desc') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <section class="py-16 md:py-20 text-white text-center bg-slate-900">
        <div class="max-w-3xl mx-auto px-4">
            <h2 class="text-2xl md:text-3xl font-bold mb-4 tracking-tight">{{ __('app.quickstart_page.cta_title') }}</h2>
            <a href="/build/register" class="inline-block bg-white text-slate-900 px-8 py-3 rounded-xl font-bold hover:bg-slate-50 transition shadow-lg">{{ __('app.quickstart_page.cta_button') }}</a>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
