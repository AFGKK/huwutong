<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#409eff">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="HWT License">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="HWT License">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/build/assets/pwa-icon-192.png">
    <link rel="apple-touch-startup-image" href="/build/assets/pwa-icon-512.png">
    <title>{{ $title ?? 'HWT License 管理后台' }}</title>
    @if(config('cloud-storage.cdn_domain'))
        @cdnAssets
    @else
        @vite(['resources/css/app.css', 'resources/js/admin.js'])
    @endif
</head>
<body>
    <!-- WCAG: 跳过导航链接 -->
    <a href="#main-content" class="skip-link" tabindex="1">跳转到主内容</a>

    <div id="admin-app">
        <div class="app-loading" role="status" aria-live="polite">
            <div class="loading-spinner" aria-hidden="true"></div>
            <p>加载中...</p>
        </div>
    </div>

    <!-- WCAG: 屏幕阅读器实时通告区域 -->
    <div id="a11y-announcer-polite" class="sr-only" aria-live="polite" aria-atomic="true" role="status"></div>
    <div id="a11y-announcer-assertive" class="sr-only" aria-live="assertive" aria-atomic="true" role="alert"></div>
</body>
</html>
