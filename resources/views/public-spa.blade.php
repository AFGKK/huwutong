<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? site_setting('site_description') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <link rel="manifest" href="/manifest.json">
    @include('public.partials.theme-vars')
    <link rel="icon" href="{{ site_setting('favicon_url') ?: '/images/favicon.svg' }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ site_setting('logo_url') ?: '/images/pwa-icon-192.png' }}">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $title ?? __('app.nav.community') . ' - ' . __('app.app_name') }}">
    <meta property="og:description" content="{{ $description ?? site_setting('site_description', __('app.app_description')) }}">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    <meta property="og:type" content="{{ $og_type ?? 'website' }}">
    <meta property="og:site_name" content="{{ __('app.app_name') }}">
    <meta property="og:locale" content="{{ str_replace('_', '-', app()->getLocale()) }}">
    @if(isset($og_image) && $og_image)
    <meta property="og:image" content="{{ $og_image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? __('app.nav.community') . ' - ' . __('app.app_name') }}">
    <meta name="twitter:description" content="{{ $description ?? site_setting('site_description', __('app.app_description')) }}">
    @if(isset($og_image) && $og_image)
    <meta name="twitter:image" content="{{ $og_image }}">
    @endif

    {{-- Article 结构化数据 (JSON-LD) --}}
    @if(isset($og_type) && $og_type === 'article')
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Article",
        "headline": {{ json_encode($title) }},
        "description": {{ json_encode($description) }},
        @if(isset($og_image) && $og_image)
        "image": {{ json_encode($og_image) }},
        @endif
        @if(isset($article_published_time) && $article_published_time)
        "datePublished": {{ json_encode($article_published_time) }},
        @endif
        @if(isset($article_author) && $article_author)
        "author": { "@type": "Person", "name": {{ json_encode($article_author) }} },
        @endif
        @if(isset($article_section) && $article_section)
        "publisher": { "@type": "Organization", "name": {{ json_encode($article_section) }} },
        @endif
        @if(isset($article_tags) && count($article_tags))
        "keywords": {{ json_encode($article_tags) }},
        @endif
        "mainEntityOfPage": { "@type": "WebPage", "@id": "{{ $canonical ?? url()->current() }}" }
    }
    </script>
    @endif

    <title>{{ $title ?? (__('app.nav.community') . ' - ' . __('app.app_name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    <a href="#main-content" class="skip-link" tabindex="1">{{ __('app.skip_to_content') }}</a>

    @include('public.partials.nav')

    <div id="admin-app">
        <div class="app-loading" role="status" aria-live="polite">
            <div class="loading-spinner" aria-hidden="true"></div>
            <p>{{ __('app.actions.loading') }}</p>
        </div>
    </div>

    @include('public.partials.footer')

    <div id="a11y-announcer-polite" class="sr-only" aria-live="polite" aria-atomic="true" role="status"></div>
    <div id="a11y-announcer-assertive" class="sr-only" aria-live="assertive" aria-atomic="true" role="alert"></div>
</body>
</html>
