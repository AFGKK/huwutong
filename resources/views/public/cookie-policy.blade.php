<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('app.legal_page.cookie_meta') }}">
    <meta name="robots" content="index, follow">
    <title>{{ __('app.legal_page.cookie_title') }} | {{ site_setting('site_name', __('app.app_name')) }}</title>
    <meta property="og:title" content="{{ __('app.legal_page.cookie_title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.legal_page.cookie_meta') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/cookie-policy') }}">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
</head>
<body class="font-sans antialiased text-slate-800 bg-white min-h-screen flex flex-col">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => __('app.legal_page.cookie_title'),
        'heroSubtitle' => __('app.legal_page.cookie_updated'),
    ])

    <main class="flex-1 py-16 md:py-20">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-2xl border border-slate-200 p-8 md:p-10 space-y-8 text-slate-600 leading-relaxed">

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h1') }}</h2>
                    <p>{{ __('app.legal_page.cookie_p1') }}</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h2') }}</h2>
                    <p class="mb-4">{{ __('app.legal_page.cookie_p2') }}</p>
                    <div class="space-y-4">
                        <div class="rounded-xl p-4 border border-slate-200 bg-slate-50">
                            <h3 class="font-semibold text-slate-900">{{ __('app.legal_page.cookie_cat_necessary') }}</h3>
                            <p class="text-sm text-slate-600 mt-1">{{ __('app.legal_page.cookie_cat_necessary_desc') }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ __('app.legal_page.cookie_always_on') }}</p>
                        </div>
                        <div class="rounded-xl p-4 border border-slate-200">
                            <h3 class="font-semibold text-slate-900">{{ __('app.legal_page.cookie_cat_functional') }}</h3>
                            <p class="text-sm text-slate-600 mt-1">{{ __('app.legal_page.cookie_cat_functional_desc') }}</p>
                        </div>
                        <div class="rounded-xl p-4 border border-slate-200">
                            <h3 class="font-semibold text-slate-900">{{ __('app.legal_page.cookie_cat_analytics') }}</h3>
                            <p class="text-sm text-slate-600 mt-1">{{ __('app.legal_page.cookie_cat_analytics_desc') }}</p>
                        </div>
                        <div class="rounded-xl p-4 border border-slate-200">
                            <h3 class="font-semibold text-slate-900">{{ __('app.legal_page.cookie_cat_marketing') }}</h3>
                            <p class="text-sm text-slate-600 mt-1">{{ __('app.legal_page.cookie_cat_marketing_desc') }}</p>
                        </div>
                    </div>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h3') }}</h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm border-collapse">
                            <thead>
                                <tr class="bg-slate-50">
                                    <th class="text-left px-4 py-3 border-b border-slate-200 font-semibold text-slate-700">{{ __('app.legal_page.cookie_th_name') }}</th>
                                    <th class="text-left px-4 py-3 border-b border-slate-200 font-semibold text-slate-700">{{ __('app.legal_page.cookie_th_cat') }}</th>
                                    <th class="text-left px-4 py-3 border-b border-slate-200 font-semibold text-slate-700">{{ __('app.legal_page.cookie_th_purpose') }}</th>
                                    <th class="text-left px-4 py-3 border-b border-slate-200 font-semibold text-slate-700">{{ __('app.legal_page.cookie_th_ttl') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-800">XSRF-TOKEN</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs">{{ __('app.legal_page.cookie_badge_necessary') }}</span></td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_purpose_csrf') }}</td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_ttl_session') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-800">laravel_session</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs">{{ __('app.legal_page.cookie_badge_necessary') }}</span></td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_purpose_session') }}</td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_ttl_session') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-800">cookie_consent</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs">{{ __('app.legal_page.cookie_badge_necessary') }}</span></td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_purpose_consent') }}</td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_ttl_365') }}</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs text-slate-800">cookie_consent_given</td>
                                    <td class="px-4 py-3"><span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded text-xs">{{ __('app.legal_page.cookie_badge_necessary') }}</span></td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_purpose_consent_detail') }}</td>
                                    <td class="px-4 py-3">{{ __('app.legal_page.cookie_ttl_365') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-slate-400 mt-2">{{ __('app.legal_page.cookie_list_note') }}</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h4') }}</h2>
                    <p class="mb-3">{{ __('app.legal_page.cookie_p4') }}</p>
                    <ul class="list-disc pl-6 space-y-2">
                        <li><strong class="text-slate-800">{{ __('app.legal_page.cookie_manage_panel') }}</strong> — {{ __('app.legal_page.cookie_manage_panel_desc') }}</li>
                        <li><strong class="text-slate-800">{{ __('app.legal_page.cookie_manage_browser') }}</strong> — {{ __('app.legal_page.cookie_manage_browser_desc') }}</li>
                    </ul>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h5') }}</h2>
                    <p>{{ __('app.legal_page.cookie_p5') }}</p>
                    <ul class="list-disc pl-6 space-y-1 mt-2">
                        <li>{{ __('app.legal_page.cookie_third_analytics') }}</li>
                        <li>{{ __('app.legal_page.cookie_third_support') }}</li>
                    </ul>
                    <p class="mt-3">{{ __('app.legal_page.cookie_p5b') }}</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h6') }}</h2>
                    <p>{{ __('app.legal_page.cookie_p6') }}</p>
                </section>

                <section>
                    <h2 class="text-xl font-semibold text-slate-900 mb-3">{{ __('app.legal_page.cookie_h7') }}</h2>
                    <p>{{ __('app.legal_page.cookie_p7') }}</p>
                    <ul class="list-disc pl-6 space-y-1 mt-2">
                        <li>{{ __('app.legal_page.cookie_contact_email') }} <a href="mailto:support@huwutong.com" class="text-slate-900 underline">support@huwutong.com</a></li>
                        <li>{{ __('app.legal_page.cookie_contact_chat') }}</li>
                    </ul>
                </section>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
</body>
</html>
