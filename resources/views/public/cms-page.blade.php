<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta['title'] ?? $page->title }} - 互物通| 企业级授权管理系统</title>
    <meta name="description" content="{{ $page->meta['description'] ?? '' }}">
    @if(!empty($page->meta['keywords']))
    <meta name="keywords" content="{{ $page->meta['keywords'] }}">
    @endif
    <link rel="canonical" <hr>slug) }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>.prose{max-width:65ch;color:#374151;font-size:1rem;line-height:1.75}.prose h2{font-size:1.5em;margin-top:2em;margin-bottom:1em;font-weight:700;color:#111827}.prose h3{font-size:1.25em;margin-top:1.6em;margin-bottom:0.6em;font-weight:600;color:#111827}.prose p{margin-bottom:1.25em}.prose ul{margin-bottom:1.25em;padding-left:1.5em;list-style-type:disc}.prose li{margin-bottom:0.5em}.prose strong{font-weight:600;color:#111827}.prose a{color:#2563eb;text-decoration:underline}</style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')
    <section class="pt-28 pb-16 bg-gradient-to-br from-primary-50 to-white">
        <div class="max-w-4xl mx-auto px-4 text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">{{ $page->title }}</h1>
            @if(!empty($page->meta['subtitle']))
            <p class="text-lg text-gray-600">{{ $page->meta['subtitle'] }}</p>
            @endif
        </div>
    </section>
    <section class="py-16">
        <div class="max-w-4xl mx-auto px-4 prose prose-gray">
            {!! $page->content !!}
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
