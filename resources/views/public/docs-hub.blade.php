<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.docs_hub_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.docs_hub_page.meta_desc') }}">
    <link rel="canonical" href="{{ url('/docs') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.docs_hub_page.title'),
        'heroSubtitle' => __('app.docs_hub_page.subtitle'),
        'heroCrumb' => __('app.docs_hub_page.crumb'),
    ])

    <section class="py-14 md:py-16">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($links as $link)
                    <a href="{{ $link['path'] }}" class="block rounded-2xl border border-slate-200 p-6 hover:border-slate-400 hover:shadow-lg transition bg-white">
                        <h2 class="text-lg font-bold text-slate-900 mb-2">{{ $link['title'] }}</h2>
                        <p class="text-sm text-slate-500">{{ $link['desc'] }}</p>
                    </a>
                @endforeach
            </div>

            <div class="mt-14">
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.docs_hub_page.sdk_title') }}</h2>
                <div class="flex flex-wrap gap-2">
                    @foreach($sdks as $sdk)
                        <a href="{{ $sdk['docs_url'] }}" class="px-3 py-1.5 text-sm rounded-lg border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700">{{ $sdk['lang_label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div class="mt-14">
                <h2 class="text-xl font-bold text-slate-900 mb-4">{{ __('app.docs_hub_page.examples_title') }}</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    @foreach($examples as $ex)
                        <div class="rounded-2xl border border-slate-200 p-5 bg-white">
                            <h3 class="font-bold text-slate-900 mb-2">{{ $ex['name'] }}</h3>
                            <p class="text-xs text-slate-400 mb-3 font-mono">{{ $ex['path'] }}</p>
                            <a href="{{ $ex['docs_url'] }}" class="text-sm font-medium text-slate-800 hover:underline">{{ __('app.sdk_page.docs') }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
