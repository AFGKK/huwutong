<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="{{ __('app.app_name') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="{{ __('app.app_name') }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/pwa-icon-192.png">
    <link rel="apple-touch-startup-image" href="/images/pwa-icon-512.png">
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    <title>{{ $title ?? __('app.admin.title') }}</title>
    @if(config('cloud-storage.cdn_domain'))
        @cdnAssets
    @else
        @vite(['resources/css/app.css', 'resources/js/admin.js'])
    @endif
</head>
<body>
    <!-- WCAG: 跳过导航链接 -->
    <a href="#main-content" class="skip-link" tabindex="1">{{ __('app.skip_to_content') }}</a>

    <div id="admin-app">
        <div class="app-loading" role="status" aria-live="polite">
            <div class="loading-spinner" aria-hidden="true"></div>
            <p>{{ __('app.actions.loading') }}</p>
        </div>
    </div>

    <!-- WCAG: 屏幕阅读器实时通告区域 -->
    <div id="a11y-announcer-polite" class="sr-only" aria-live="polite" aria-atomic="true" role="status"></div>
    <div id="a11y-announcer-assertive" class="sr-only" aria-live="assertive" aria-atomic="true" role="alert"></div>
</body>
</html>
