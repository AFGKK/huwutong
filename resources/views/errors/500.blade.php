<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('app.errors.500_doc_title') }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#0f172a">
    @vite(['resources/css/public.css'])
    <style>
        .error-page { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f8fafc; padding:2rem; }
        .error-card { text-align:center; max-width:480px; }
        .error-code { font-size:6rem; font-weight:800; color:#e74c3c; line-height:1; margin-bottom:1rem; }
        .error-icon { font-size:3rem; margin-bottom:1rem; }
        .error-title { font-size:1.5rem; font-weight:600; color:#1a202c; margin-bottom:.5rem; }
        .error-desc { color:#718096; margin-bottom:2rem; line-height:1.6; }
        .error-btn { display:inline-block; padding:.75rem 2rem; background:#0f172a; color:#fff; border-radius:8px; text-decoration:none; font-weight:500; transition:background .2s; }
        .error-btn:hover { background:#1e293b; }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-card">
            <div class="error-icon">⚠️</div>
            <div class="error-code">500</div>
            <div class="error-title">{{ __('app.errors.500_title') }}</div>
            <div class="error-desc">{{ __('app.errors.500_message') }}</div>
            <a href="{{ url('/') }}" class="error-btn">{{ __('app.errors.go_home') }}</a>
        </div>
    </div>
</body>
</html>
