<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'HWT License 管理后台' }}</title>
    @vite(['resources/css/app.css', 'resources/js/admin.js'])
</head>
<body>
    <div id="admin-app">
        <div class="app-loading">
            <div class="loading-spinner"></div>
            <p>加载中...</p>
        </div>
    </div>
</body>
</html>
