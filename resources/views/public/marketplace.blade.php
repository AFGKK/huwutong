<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.marketplace_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ __('app.marketplace_page.subtitle') }}">
    <link rel="canonical" href="{{ url('/marketplace') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
@include('public.partials.nav')

<section class="pt-28 pb-10 bg-slate-900 text-white">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">{{ __('app.marketplace_page.kicker') }}</p>
        <h1 class="text-3xl md:text-4xl font-bold mb-3">{{ __('app.marketplace_page.title') }}</h1>
        <p class="text-slate-300 max-w-2xl">{{ __('app.marketplace_page.subtitle') }}</p>
        <form method="get" action="{{ url('/marketplace') }}" class="mt-8 flex flex-wrap gap-3">
            <input type="search" name="q" value="{{ $search }}" placeholder="{{ __('app.marketplace_page.search_ph') }}"
                   class="flex-1 min-w-[220px] rounded-xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm text-white placeholder:text-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-500">
            @if($activeCategory !== '')
                <input type="hidden" name="category" value="{{ $activeCategory }}">
            @endif
            <button type="submit" class="rounded-xl bg-white text-slate-900 px-5 py-2.5 text-sm font-semibold hover:bg-slate-100 transition">{{ __('app.marketplace_page.search') }}</button>
        </form>
    </div>
</section>

<section class="py-10">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($categories->isNotEmpty())
            <div class="flex flex-wrap gap-2 mb-8">
                <a href="{{ url('/marketplace'.($search !== '' ? '?q='.urlencode($search) : '')) }}"
                   class="px-3 py-1.5 rounded-lg text-sm border {{ $activeCategory === '' ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400' }}">
                    {{ __('app.marketplace_page.all') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url('/marketplace?category='.urlencode($cat).($search !== '' ? '&q='.urlencode($search) : '')) }}"
                       class="px-3 py-1.5 rounded-lg text-sm border {{ $activeCategory === $cat ? 'bg-slate-900 text-white border-slate-900' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-400' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        @endif

        @if($apps->isEmpty())
            <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center">
                <p class="text-slate-700 font-medium mb-2">{{ __('app.marketplace_page.empty_title') }}</p>
                <p class="text-sm text-slate-500 mb-6">{{ __('app.marketplace_page.empty_desc') }}</p>
                <a href="{{ url('/docs') }}" class="inline-flex items-center rounded-xl bg-slate-900 text-white px-5 py-2.5 text-sm font-semibold hover:bg-slate-800 transition">{{ __('app.marketplace_page.docs_cta') }}</a>
            </div>
        @else
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                @foreach($apps as $app)
                    <a href="{{ url('/marketplace/'.$app->slug) }}" class="block rounded-2xl border border-slate-200 bg-white p-5 hover:border-slate-400 transition">
                        <div class="flex items-start gap-3 mb-3">
                            @if($app->icon_url)
                                <img src="{{ $app->icon_url }}" alt="" class="w-12 h-12 rounded-xl object-cover bg-slate-100">
                            @else
                                <div class="w-12 h-12 rounded-xl bg-slate-100 flex items-center justify-center text-slate-500 text-lg font-bold">{{ mb_substr($app->name, 0, 1) }}</div>
                            @endif
                            <div class="min-w-0">
                                <h2 class="font-semibold text-slate-900 truncate">{{ $app->name }}</h2>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $app->category }} · {{ $app->pricing_type === 'free' ? __('app.marketplace_page.free') : '¥'.number_format((float) $app->price, 2) }}</p>
                            </div>
                        </div>
                        <p class="text-sm text-slate-600 line-clamp-2">{{ $app->short_description ?: Str::limit(strip_tags((string) $app->description), 100) }}</p>
                        <div class="mt-4 flex items-center justify-between text-xs text-slate-400">
                            <span>{{ $app->developer?->display_name ?: ($app->developer?->company_name ?: __('app.marketplace_page.unknown_dev')) }}</span>
                            <span>★ {{ number_format((float) ($app->avg_rating ?: 0), 1) }} · {{ (int) $app->install_count }}</span>
                        </div>
                    </a>
                @endforeach
            </div>
            <div class="mt-10">
                {{ $apps->links() }}
            </div>
        @endif
    </div>
</section>

@include('public.partials.footer')
</body>
</html>
