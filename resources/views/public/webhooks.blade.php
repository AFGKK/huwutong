<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.webhooks_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.webhooks_page.meta_desc') }}">
    <link rel="canonical" href="{{ url('/docs/webhooks') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.webhooks_page.title'),
        'heroSubtitle' => __('app.webhooks_page.subtitle'),
        'heroCrumb' => __('app.docs_hub_page.crumb'),
    ])

    <section class="py-14">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
            <p class="text-slate-600">{{ $webhooks['overview'] ?? '' }}</p>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.webhooks_page.setup') }}</h2>
                <ol class="space-y-3">
                    @foreach(($webhooks['setup_steps'] ?? []) as $i => $step)
                        <li class="flex gap-3 items-start">
                            <span class="w-7 h-7 rounded-lg bg-slate-900 text-white text-sm font-bold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                            <p class="text-slate-600 pt-0.5">{{ $step }}</p>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.webhooks_page.events') }}</h2>
                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-slate-500">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('app.webhooks_page.event') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('app.sdk_doc_page.desc') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(($webhooks['events'] ?? []) as $event)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-slate-900">{{ $event['name'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $event['desc'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.webhooks_page.payload') }}</h2>
                <pre class="bg-slate-900 rounded-2xl p-5 text-sm text-slate-200 overflow-x-auto"><code>{{ json_encode($webhooks['example_payload'] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) }}</code></pre>
                <p class="text-sm text-slate-500 mt-3">{{ $webhooks['verify_tip'] ?? '' }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <a href="/api-docs" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-medium">{{ __('app.api_docs_page.title') }}</a>
                <a href="/docs/error-codes" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-medium">{{ __('app.error_codes_page.title') }}</a>
            </div>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
