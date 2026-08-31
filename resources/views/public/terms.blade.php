<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.seo.terms_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}</title>
    <meta name="description" content="{{ __('app.legal_page.terms_meta') }}">
    <meta property="og:title" content="{{ __('app.legal_page.terms_title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.legal_page.terms_meta') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/terms') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
    <style>.prose h2 { font-size:1.5rem; font-weight:700; margin-top:2rem; margin-bottom:0.75rem; padding-bottom:0.5rem; border-bottom:1px solid #e2e8f0; } .prose p { margin-bottom:1rem; line-height:1.75; color:#475569; }</style>
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.legal_page.terms_title'),
        'heroSubtitle' => __('app.legal_page.terms_updated'),
    ])
    <section class="py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 prose prose-slate">
            <h2>{{ __('app.legal_page.terms_h_service') }}</h2>
            <p>{{ __('app.legal_page.terms_p_service') }}</p>
            <h2>{{ __('app.legal_page.terms_h_account') }}</h2>
            <p>{{ __('app.legal_page.terms_p_account') }}</p>
            <h2>{{ __('app.legal_page.terms_h_limits') }}</h2>
            <p>{{ __('app.legal_page.terms_p_limits') }}</p>
            <h2>{{ __('app.legal_page.terms_h_billing') }}</h2>
            <p>{{ __('app.legal_page.terms_p_billing') }}</p>
            <h2>{{ __('app.legal_page.terms_h_sla') }}</h2>
            <p>{{ __('app.legal_page.terms_p_sla') }}</p>
            <h2>{{ __('app.legal_page.terms_h_disclaimer') }}</h2>
            <p>{{ __('app.legal_page.terms_p_disclaimer') }}</p>
        </div>
    </section>
    @include('public.partials.footer')
</body>
</html>
