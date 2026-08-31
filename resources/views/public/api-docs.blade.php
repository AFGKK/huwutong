<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.api_docs_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.api_docs_page.meta_desc') }}">
    <link rel="canonical" href="{{ url('/api-docs') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.api_docs_page.title'),
        'heroSubtitle' => __('app.api_docs_page.subtitle'),
        'heroCrumb' => __('app.api_docs_page.crumb'),
    ])

    <section class="py-10 border-b border-slate-100 bg-slate-50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-wrap gap-2">
            <a href="/docs" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.docs_hub_page.crumb') }}</a>
            <a href="/docs/quickstart" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.sdk_doc_page.quickstart') }}</a>
            <a href="/sdk" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">SDK</a>
            <a href="/docs/error-codes" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">{{ __('app.error_codes_page.title') }}</a>
            <a href="/docs/webhooks" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-white text-slate-600 hover:bg-slate-100">Webhook</a>
        </div>
    </section>

    <section class="py-14">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <div class="rounded-2xl border border-slate-200 p-6 bg-white">
                <h2 class="text-lg font-bold text-slate-900 mb-2">{{ $auth['title'] ?? __('app.api_docs_page.auth') }}</h2>
                <p class="text-sm text-slate-500 mb-4">{{ $auth['description'] ?? '' }}</p>
                <div class="bg-slate-900 rounded-xl p-4 text-sm text-slate-200 font-mono overflow-x-auto">
                    <div>Base URL: {{ $baseUrl }}</div>
                    <div>{{ $auth['header'] ?? '' }}</div>
                    <div>Content-Type: {{ $auth['content_type'] ?? 'application/json' }}</div>
                </div>
            </div>

            @foreach($groups as $group)
                <div>
                    <h2 class="text-xl font-bold text-slate-900 mb-4">{{ $group['group_label'] }}</h2>
                    <div class="space-y-4">
                        @foreach($group['endpoints'] as $ep)
                            <article class="rounded-2xl border border-slate-200 overflow-hidden bg-white">
                                <div class="px-5 py-4 border-b border-slate-100 flex flex-wrap items-center gap-3">
                                    <span class="text-xs font-bold px-2 py-1 rounded bg-slate-900 text-white">{{ $ep['method'] }}</span>
                                    <code class="text-sm text-slate-800">{{ $ep['path'] }}</code>
                                    <span class="text-sm font-medium text-slate-700">{{ $ep['summary'] }}</span>
                                </div>
                                <div class="px-5 py-4 space-y-4">
                                    <p class="text-sm text-slate-600">{{ $ep['description'] }}</p>
                                    @if(!empty($ep['example_request']))
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">{{ __('app.api_docs_page.request') }}</div>
                                            <pre class="bg-slate-50 border border-slate-100 rounded-xl p-4 text-xs text-slate-800 overflow-x-auto"><code>{{ json_encode($ep['example_request'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</code></pre>
                                        </div>
                                    @endif
                                    @if(!empty($ep['example_response']))
                                        <div>
                                            <div class="text-xs uppercase tracking-wide text-slate-400 mb-2">{{ __('app.api_docs_page.response') }}</div>
                                            <pre class="bg-slate-900 rounded-xl p-4 text-xs text-slate-200 overflow-x-auto"><code>{{ json_encode($ep['example_response'], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</code></pre>
                                        </div>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.docs_hub_page.examples_title') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($examples as $ex)
                        <a href="{{ $ex['docs_url'] }}" class="rounded-xl border border-slate-200 p-4 hover:bg-slate-50 transition">
                            <div class="font-medium text-slate-900">{{ $ex['name'] }}</div>
                            <div class="text-xs text-slate-400 font-mono mt-1">{{ $ex['path'] }}</div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
