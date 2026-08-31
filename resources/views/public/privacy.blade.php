<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.seo.privacy_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}</title>
    <meta name="description" content="{{ __('app.legal_page.privacy_meta') }}">
    <meta property="og:title" content="{{ __('app.legal_page.privacy_title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.legal_page.privacy_meta') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/privacy') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>.prose h2 { font-size:1.5rem; font-weight:700; margin-top:2rem; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0; } .prose p { margin-bottom:1rem; line-height:1.75; color:#475569; } .prose a { color:var(--pg-primary); text-decoration:underline; }</style>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.legal_page.privacy_title'),
        'heroSubtitle' => __('app.legal_page.privacy_updated'),
    ])
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-slate">
            <h2>{{ __('app.legal_page.privacy_h_collect') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_collect') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_use') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_use') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_share') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_share') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_security') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_security') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_rights') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_rights') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_cookie') }}</h2>
            <p>{{ __('app.legal_page.privacy_p_cookie') }}</p>
            <h2>{{ __('app.legal_page.privacy_h_contact') }}</h2>
            <p>{!! str_replace(':link', '<a href="/contact">' . e(__('app.legal_page.privacy_contact_link')) . '</a>', e(__('app.legal_page.privacy_p_contact'))) !!}</p>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
