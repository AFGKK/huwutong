<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $page->meta['title'] ?? $page->title }} - {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta name="description" content="{{ $page->meta['description'] ?? '' }}">
    @if(!empty($page->meta['keywords']))
    <meta name="keywords" content="{{ $page->meta['keywords'] }}">
    @endif
    <link rel="canonical" href="{{ url($canonicalPath ?? ('/page/' . $page->slug)) }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>
        .prose{max-width:65ch;color:#374151;font-size:1rem;line-height:1.75;margin-left:auto;margin-right:auto}
        .prose h2{font-size:1.5em;margin-top:2em;margin-bottom:1em;font-weight:700;color:#111827;padding-bottom:.5rem;border-bottom:1px solid #e5e7eb}
        .prose h3{font-size:1.25em;margin-top:1.6em;margin-bottom:0.6em;font-weight:600;color:#111827}
        .prose p{margin-bottom:1.25em;line-height:1.75;color:#4b5563}
        .prose ul{margin-bottom:1.25em;padding-left:1.5em;list-style-type:disc}
        .prose li{margin-bottom:0.5em}
        .prose strong{font-weight:600;color:#111827}
        .prose a{color:var(--pg-primary);text-decoration:underline}
    </style>
</head>
<body class="font-sans antialiased text-gray-800 bg-white">
    @include('public.partials.nav')
    <section class="pt-24 pb-16 md:pb-20 bg-gradient-to-br from-slate-50 via-white to-slate-100 relative overflow-hidden">
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-slate-300 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-slate-400 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative text-center">
            <h1 class="text-4xl md:text-5xl font-extrabold text-gray-900 mb-4">{{ $page->title }}</h1>
            @if(!empty($page->meta['subtitle']))
            <p class="text-lg text-gray-600">{{ $page->meta['subtitle'] }}</p>
            @endif
        </div>
    </section>
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-gray">
            {!! $page->content !!}
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
