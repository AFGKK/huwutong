<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ $sdk['name'] }} {{ __('app.sdk_doc_page.guide') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ $sdk['description'] }}">
    <meta property="og:title" content="{{ $sdk['name'] }} - {{ __('app.sdk_doc_page.guide') }}">
    <meta property="og:description" content="{{ $sdk['description'] }}">
    <meta property="og:type" content="article">
    <link rel="canonical" href="{{ url($sdk['docs_url']) }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => $sdk['name'],
        'heroSubtitle' => $sdk['description'],
        'heroCrumb' => __('app.nav.sdk'),
    ])

    <section class="py-10 border-b border-slate-100 bg-slate-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2">
                @foreach($allSdks as $item)
                    <a href="{{ $item['docs_url'] }}"
                       class="px-3 py-1.5 text-sm rounded-lg border transition {{ $item['id'] === $sdk['id'] ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100' }}">
                        {{ $item['lang_label'] }}
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="py-14 md:py-16">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 p-5 bg-white">
                    <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">{{ __('app.sdk_doc_page.package') }}</div>
                    <code class="text-sm text-slate-800 break-all">{{ $sdk['package'] }}</code>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5 bg-white">
                    <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">{{ __('app.sdk_doc_page.version') }}</div>
                    <div class="text-sm font-medium text-slate-800">{{ $sdk['version'] }}</div>
                </div>
                <div class="rounded-2xl border border-slate-200 p-5 bg-white">
                    <div class="text-xs uppercase tracking-wide text-slate-400 mb-1">{{ __('app.sdk_doc_page.requires') }}</div>
                    <div class="text-sm font-medium text-slate-800">{{ $sdk['requires'] }}</div>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.sdk_doc_page.steps_title') }}</h2>
                <ol class="space-y-3">
                    @foreach($sdk['steps'] as $i => $step)
                        <li class="flex gap-3 items-start">
                            <span class="w-7 h-7 rounded-lg bg-slate-900 text-white text-sm font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                            <p class="text-slate-600 pt-0.5">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.sdk_doc_page.install_title') }}</h2>
                <div class="bg-slate-900 rounded-2xl p-5 border border-slate-800">
                    <pre class="text-sm text-slate-200 font-mono leading-relaxed overflow-x-auto whitespace-pre-wrap"><code>{{ $sdk['install_command'] }}</code></pre>
                </div>
                @if(!empty($sdk['install_alt']))
                    <div class="mt-3 bg-slate-50 rounded-xl p-4 border border-slate-100">
                        <div class="text-xs text-slate-400 mb-1">Gradle</div>
                        <code class="text-sm text-slate-800">{{ $sdk['install_alt'] }}</code>
                    </div>
                @endif
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.sdk_doc_page.example_title') }}</h2>
                <div class="bg-slate-900 rounded-2xl p-5 md:p-6 border border-slate-800 shadow-xl">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="w-2.5 h-2.5 bg-rose-400 rounded-full"></span>
                        <span class="w-2.5 h-2.5 bg-amber-400 rounded-full"></span>
                        <span class="w-2.5 h-2.5 bg-emerald-400 rounded-full"></span>
                        <span class="text-slate-400 text-sm ml-2">{{ $sdk['lang_label'] }}</span>
                    </div>
                    <pre class="text-sm text-slate-200 font-mono leading-relaxed overflow-x-auto"><code>{{ $sdk['example'] }}</code></pre>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.sdk_doc_page.api_title') }}</h2>
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('app.sdk_doc_page.method') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('app.sdk_doc_page.desc') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($sdk['methods'] as $method)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-slate-900">{{ $method['name'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $method['desc'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <a href="/sdk" class="inline-flex items-center px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-medium hover:bg-slate-800 transition">{{ __('app.sdk_doc_page.back_sdk') }}</a>
                <a href="/docs/quickstart" class="inline-flex items-center px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition">{{ __('app.sdk_doc_page.quickstart') }}</a>
                <a href="/help" class="inline-flex items-center px-5 py-2.5 rounded-xl border border-slate-200 text-slate-700 text-sm font-medium hover:bg-slate-50 transition">{{ __('app.sdk_doc_page.help') }}</a>
            </div>
        </div>
    </section>

    @include('public.partials.footer')
</body>
</html>
