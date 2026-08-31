<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.error_codes_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.error_codes_page.meta_desc') }}">
    <link rel="canonical" href="{{ url('/docs/error-codes') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.error_codes_page.title'),
        'heroSubtitle' => __('app.error_codes_page.subtitle'),
        'heroCrumb' => __('app.docs_hub_page.crumb'),
    ])

    <section class="py-14">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-sm text-slate-500 mb-6">{{ __('app.error_codes_page.hint') }}</p>
            <div class="overflow-hidden rounded-2xl border border-slate-200">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-3 font-medium">{{ __('app.error_codes_page.code') }}</th>
                            <th class="px-4 py-3 font-medium">HTTP</th>
                            <th class="px-4 py-3 font-medium">{{ __('app.error_codes_page.message') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($codes as $row)
                            <tr>
                                <td class="px-4 py-3 font-mono text-slate-900">{{ $row['code'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['http'] }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $row['message'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="/api-docs" class="px-5 py-2.5 rounded-xl bg-slate-900 text-white text-sm font-medium">{{ __('app.api_docs_page.title') }}</a>
                <a href="/docs/webhooks" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-medium">Webhook</a>
            </div>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
