<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    @vite('resources/css/public.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <main class="min-h-screen flex items-center justify-center px-4 py-16">
        <div class="max-w-lg w-full text-center">
            <p class="text-xs font-semibold tracking-widest uppercase text-slate-400 mb-3">{{ __('app.maintenance_page.kicker') }}</p>
            <h1 class="text-3xl font-bold text-slate-900 mb-4">{{ $title }}</h1>
            <p class="text-slate-600 leading-relaxed mb-8">{{ $message }}</p>
            @if(!empty($scheduledEndAt))
                <p class="text-sm text-slate-500 mb-6">{{ __('app.maintenance_page.eta', ['time' => $scheduledEndAt]) }}</p>
            @endif
            <p class="text-xs text-slate-400">{{ __('app.maintenance_page.retry', ['seconds' => $retryAfter]) }}</p>
            <div class="mt-10">
                <a href="{{ url('/api/maintenance/status') }}" class="text-sm text-slate-700 underline hover:text-slate-900">{{ __('app.maintenance_page.status_link') }}</a>
            </div>
        </div>
    </main>
</body>
</html>
