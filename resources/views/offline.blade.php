@php
    $appName = config('app.name', 'HWT');
    $htmlLang = str_replace('_', '-', app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.offline_page.title', ['app_name' => $appName]) }}</title>
    <link rel="icon" href="/images/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#0f172a">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #0f172a;
            color: #cbd5e1;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .offline-card {
            text-align: center;
            max-width: 400px;
            padding: 40px;
            background: #1e293b;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        .offline-icon { font-size: 64px; margin-bottom: 16px; }
        h1 { font-size: 24px; margin-bottom: 8px; color: #e6e6e6; }
        p { font-size: 14px; line-height: 1.6; color: #909399; margin-bottom: 24px; }
        .btn {
            display: inline-block;
            padding: 10px 24px;
            background: #334155;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
        }
        .btn:hover { background: #475569; }
        .hint { font-size: 12px; color: #606266; margin-top: 16px; }
    </style>
</head>
<body>
    <div class="offline-card">
        <div class="offline-icon" aria-hidden="true">📡</div>
        <h1>{{ __('app.offline_page.heading') }}</h1>
        <p>{!! __('app.offline_page.body') !!}</p>
        <button class="btn" type="button" onclick="window.location.reload()">{{ __('app.offline_page.retry') }}</button>
        <div class="hint">{{ __('app.offline_page.hint', ['app_name' => $appName]) }}</div>
    </div>
</body>
</html>
