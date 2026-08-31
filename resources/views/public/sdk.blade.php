<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.sdk_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.sdk_page.meta_desc') }}">
    <meta property="og:title" content="{{ __('app.sdk_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.sdk_page.subtitle') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/sdk') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>
        .sdk-card { transition: all 0.25s ease; }
        .sdk-card:hover { transform: translateY(-3px); box-shadow: 0 14px 28px -16px rgba(var(--pg-primary-rgb), 0.18); }
    </style>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @php
        $sdks = config('sdk-docs.sdks', []);
    @endphp
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.sdk_page.title'),
        'heroSubtitle' => __('app.sdk_page.subtitle'),
        'heroCrumb' => __('app.nav.sdk'),
    ])

    <section class="py-16 md:py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($sdks as $sdk)
                <div class="sdk-card bg-white rounded-2xl border border-slate-200 p-6">
                    <div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center mb-4">
                        <span class="text-lg font-bold text-slate-800">{{ $sdk['lang_label'] }}</span>
                    </div>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">{{ $sdk['name'] }}</h3>
                    <p class="text-sm text-slate-500 mb-4">{{ $sdk['frameworks'] ?? $sdk['description'] }}</p>
                    <div class="bg-slate-50 rounded-lg p-3 mb-4 border border-slate-100">
                        <code class="text-sm text-slate-800 break-all whitespace-pre-wrap">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $sdk['install_command']), 90) }}</code>
                    </div>
                    <a href="{{ $sdk['docs_url'] }}" class="text-slate-800 font-medium hover:text-slate-950 transition">{{ __('app.sdk_page.docs') }}</a>
                </div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-16 md:py-20 bg-slate-50 border-t border-slate-100" id="examples">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-6 tracking-tight">{{ __('app.sdk_page.examples') }}</h2>
            <div class="flex flex-wrap justify-center gap-2 mb-8" id="code-tabs">
                @foreach($sdks as $sdk)
                    @if(!empty($sdk['example_tab']))
                    <button type="button"
                            class="code-tab px-4 py-2 text-sm font-medium rounded-lg border transition {{ $loop->first ? 'bg-slate-900 text-white border-slate-900' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-100' }}"
                            data-lang="{{ $sdk['id'] }}"
                            onclick="switchCode('{{ $sdk['id'] }}')">{{ $sdk['lang_label'] }}</button>
                    @endif
                @endforeach
            </div>
            <div class="bg-slate-900 rounded-2xl p-6 md:p-8 shadow-xl border border-slate-800">
                <div class="flex items-center gap-2 mb-4">
                    <span class="w-2.5 h-2.5 bg-rose-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span>
                    <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
                    <span id="code-lang-label" class="text-slate-400 text-sm ml-2">{{ $sdks['php']['lang_label'] ?? 'PHP' }}</span>
                </div>
                <pre class="text-sm text-slate-200 font-mono leading-relaxed overflow-x-auto"><code id="code-snippet">{{ $sdks['php']['example'] ?? '' }}</code></pre>
            </div>
            <p class="text-center text-sm text-slate-500 mt-6">
                <a href="/docs/quickstart" class="text-slate-800 font-medium hover:underline">{{ __('app.sdk_page.quickstart_link') }}</a>
            </p>
        </div>
    </section>
    <script>
    const CODES = @json(collect($sdks)->mapWithKeys(fn ($s) => [$s['id'] => ['label' => $s['lang_label'], 'code' => $s['example']]]));

    function switchCode(lang) {
        document.querySelectorAll('.code-tab').forEach(function(t) {
            var active = t.dataset.lang === lang;
            t.className = active
                ? 'code-tab px-4 py-2 text-sm font-medium rounded-lg bg-slate-900 text-white border border-slate-900 transition'
                : 'code-tab px-4 py-2 text-sm font-medium rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-100 transition';
        });
        var item = CODES[lang] || CODES.php;
        document.getElementById('code-lang-label').textContent = item.label;
        document.getElementById('code-snippet').textContent = item.code;
    }
    </script>
    @include('public.partials.footer')
</body>
</html>
