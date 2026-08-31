<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
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

    <section class="py-10 border-b border-slate-100 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap gap-2">
            <a href="/docs" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.docs_hub_page.crumb') }}</a>
            <a href="/sdk" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">SDK</a>
            <a href="/api-docs" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.api_docs_page.title') }}</a>
            <a href="/docs/error-codes" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.error_codes_page.title') }}</a>
            <a href="/docs/webhooks" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">Webhook</a>
        </div>
    </section>

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
                        <p class="text-gray-600 mb-4">{{ __('app.quickstart_page.step2_desc') }}</p>
                        <div class="space-y-3">
                            @foreach($sdks as $sdk)
                                <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                                        <span class="text-sm font-semibold text-slate-900">{{ $sdk['name'] }}</span>
                                        <a href="{{ $sdk['docs_url'] }}" class="text-xs font-medium text-slate-700 hover:underline">{{ __('app.sdk_page.docs') }}</a>
                                    </div>
                                    <code class="text-sm text-gray-800 break-all whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $sdk['install_command']), 120) }}</code>
                                </div>
                            @endforeach
                        </div>
                        <a href="/sdk" class="inline-block mt-4 text-slate-800 font-medium hover:text-slate-900 transition text-sm">{{ __('app.quickstart_page.view_sdk') }}</a>
                    </div>
                </div>

                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">3</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step3_title') }}</h2>
                        <p class="text-gray-600 mb-4">{{ __('app.quickstart_page.step3_desc') }}</p>
                        <div class="flex flex-wrap gap-2 mb-4" id="qs-tabs">
                            @foreach($sdks as $i => $sdk)
                                <button type="button"
                                        class="qs-tab px-3 py-1.5 text-sm rounded-lg border {{ $i === 0 ? 'bg-slate-900 text-white border-slate-900' : 'bg-white border-slate-200 text-slate-600' }}"
                                        data-lang="{{ $sdk['id'] }}"
                                        onclick="switchQs('{{ $sdk['id'] }}')">{{ $sdk['lang_label'] }}</button>
                            @endforeach
                        </div>
                        <div class="bg-gray-900 rounded-2xl p-6 shadow-xl">
                            <pre class="text-sm text-gray-200 font-mono leading-relaxed overflow-x-auto"><code id="qs-snippet">{{ $sdks[0]['example'] ?? '' }}</code></pre>
                        </div>
                    </div>
                </div>

                <div class="step-card flex gap-6 p-6 rounded-2xl border border-gray-100 bg-white">
                    <div class="w-12 h-12 bg-slate-900 rounded-xl flex items-center justify-center shrink-0"><span class="text-white font-bold text-lg">4</span></div>
                    <div class="flex-1">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ __('app.quickstart_page.step4_title') }}</h2>
                        <p class="text-gray-600 mb-3">{{ __('app.quickstart_page.step4_desc') }}</p>
                        <a href="/api-docs" class="text-slate-800 font-medium hover:text-slate-900 transition text-sm">{{ __('app.api_docs_page.title') }} →</a>
                    </div>
                </div>
            </div>

            <div class="mt-14">
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.docs_hub_page.examples_title') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($examples as $ex)
                        <div class="rounded-2xl border border-slate-200 p-5 bg-white">
                            <h3 class="font-bold text-slate-900 mb-2">{{ $ex['name'] }}</h3>
                            <pre class="text-xs text-slate-600 bg-slate-50 rounded-lg p-3 overflow-x-auto mb-3"><code>{{ implode("\n", $ex['commands']) }}</code></pre>
                            <a href="{{ $ex['docs_url'] }}" class="text-sm font-medium text-slate-800 hover:underline">{{ __('app.sdk_page.docs') }}</a>
                        </div>
                    @endforeach
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

    <script>
    const QS = @json(collect($sdks)->mapWithKeys(fn ($s) => [$s['id'] => $s['example']]));
    function switchQs(lang) {
        document.querySelectorAll('.qs-tab').forEach(function (t) {
            var on = t.dataset.lang === lang;
            t.className = on
                ? 'qs-tab px-3 py-1.5 text-sm rounded-lg border bg-slate-900 text-white border-slate-900'
                : 'qs-tab px-3 py-1.5 text-sm rounded-lg border bg-white border-slate-200 text-slate-600';
        });
        document.getElementById('qs-snippet').textContent = QS[lang] || '';
    }
    </script>
    @include('public.partials.footer')
</body>
</html>
