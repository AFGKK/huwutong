<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $app->name }} - {{ __('app.marketplace_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ $app->short_description ?: Str::limit(strip_tags((string) $app->description), 140) }}">
    <link rel="canonical" href="{{ url('/marketplace/'.$app->slug) }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
@include('public.partials.nav')

<section class="pt-28 pb-16">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <a href="{{ url('/marketplace') }}" class="text-sm text-slate-500 hover:text-slate-800 transition">← {{ __('app.marketplace_page.back') }}</a>

        <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 md:p-8">
            <div class="flex flex-wrap items-start gap-4 mb-6">
                @if($app->icon_url)
                    <img src="{{ $app->icon_url }}" alt="" class="w-16 h-16 rounded-2xl object-cover bg-slate-100">
                @else
                    <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center text-slate-500 text-2xl font-bold">{{ mb_substr($app->name, 0, 1) }}</div>
                @endif
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl md:text-3xl font-bold text-slate-900">{{ $app->name }}</h1>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ $app->category }}
                        · {{ $app->pricing_type === 'free' ? __('app.marketplace_page.free') : '¥'.number_format((float) $app->price, 2) }}
                        · v{{ $app->current_version ?: '1.0.0' }}
                    </p>
                    <p class="text-sm text-slate-500 mt-1">{{ $app->developer?->display_name ?: ($app->developer?->company_name ?: __('app.marketplace_page.unknown_dev')) }}</p>
                </div>
            </div>

            @if($app->short_description)
                <p class="text-lg text-slate-700 mb-6">{{ $app->short_description }}</p>
            @endif

            <div class="prose prose-slate max-w-none text-slate-700 leading-relaxed whitespace-pre-line">{{ $app->description }}</div>

            <div class="mt-8 flex flex-wrap gap-3">
                @if($app->documentation_url)
                    <a href="{{ $app->documentation_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex rounded-xl bg-slate-900 text-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-800 transition">{{ __('app.marketplace_page.docs') }}</a>
                @else
                    <a href="{{ url('/docs') }}" class="inline-flex rounded-xl bg-slate-900 text-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-800 transition">{{ __('app.marketplace_page.docs_cta') }}</a>
                @endif
                <a href="{{ url('/build/register') }}" class="inline-flex rounded-xl border border-slate-300 text-slate-700 px-5 py-2.5 text-sm font-medium hover:bg-slate-50 transition">{{ __('app.marketplace_page.install_cta') }}</a>
            </div>
        </div>
    </div>
</section>

@include('public.partials.footer')
</body>
</html>
