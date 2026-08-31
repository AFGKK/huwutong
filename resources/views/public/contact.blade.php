<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('app.seo.contact_title', ['app_name' => site_setting('site_name', __('app.app_name'))]) }}</title>
    <meta name="description" content="{{ __('app.contact_page.meta_desc') }}">
    <meta property="og:title" content="{{ __('app.contact_page.title') }} - {{ site_setting('site_name', __('app.app_name')) }}">
    <meta property="og:description" content="{{ __('app.contact_page.meta_desc') }}">
    <meta property="og:type" content="website">
    <link rel="canonical" href="{{ url('/contact') }}">
    @include('public.partials.tracking')
    @vite('resources/css/public.css')
</head>
<body class="font-sans antialiased text-slate-800 bg-white">
    @include('public.partials.nav')

    <section class="pt-24 pb-16 md:pb-20 bg-white relative overflow-hidden border-b border-slate-100">
        <div class="absolute inset-0 pointer-events-none opacity-[0.04]">
            <div class="absolute top-10 left-10 w-72 h-72 bg-slate-400 rounded-full blur-3xl"></div>
            <div class="absolute bottom-10 right-10 w-96 h-96 bg-slate-500 rounded-full blur-3xl"></div>
        </div>
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 relative">
            <nav class="flex items-center gap-1.5 text-sm mb-8 text-slate-500">
                <a href="{{ url('/') }}" class="hover:text-slate-900 transition flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    {{ __('app.contact_page.breadcrumb_home') }}
                </a>
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span class="text-slate-800 font-medium">{{ __('app.contact_page.title') }}</span>
            </nav>
            <div class="text-center">
                <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold text-slate-900 mb-4 tracking-tight">{{ __('app.contact_page.title') }}</h1>
                <p class="text-lg text-slate-600 max-w-2xl mx-auto">{{ __('app.contact_page.subtitle') }}</p>
            </div>
        </div>
    </section>

    <section class="py-16">
        <div class="max-w-4xl mx-auto px-4 grid md:grid-cols-2 gap-12">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-6 tracking-tight">{{ __('app.contact_page.methods') }}</h2>
                <div class="space-y-6">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg></div>
                        <div>
                            <h3 class="font-semibold text-slate-900">{{ __('app.contact_page.email') }}</h3>
                            @php $contactEmail = site_setting('contact_email'); @endphp
                            @if($contactEmail)
                            <a href="mailto:{{ $contactEmail }}" class="text-slate-600 hover:text-slate-900 transition">{{ $contactEmail }}</a>
                            @else
                            <p class="text-slate-400 text-sm">{{ __('app.contact_page.email_unset') }}</p>
                            @endif
                        </div>
                    </div>
                    @if(site_setting('contact_phone'))
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg></div>
                        <div><h3 class="font-semibold text-slate-900">{{ __('app.contact_page.phone') }}</h3><p class="text-slate-500">{{ site_setting('contact_phone') }}</p></div>
                    </div>
                    @endif
                    @if(site_setting('contact_address'))
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg></div>
                        <div><h3 class="font-semibold text-slate-900">{{ __('app.contact_page.address') }}</h3><p class="text-slate-500">{{ site_setting('contact_address') }}</p></div>
                    </div>
                    @endif
                    @if(site_setting('working_hours'))
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 bg-slate-100 rounded-lg flex items-center justify-center shrink-0"><svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></div>
                        <div><h3 class="font-semibold text-slate-900">{{ __('app.contact_page.hours') }}</h3><p class="text-slate-500">{{ site_setting('working_hours') }}</p></div>
                    </div>
                    @endif
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 mt-2">
                        <p class="text-xs font-semibold text-slate-700 mb-2">{{ __('app.landing.trust_kicker') }}</p>
                        <ul class="space-y-1.5 text-sm text-slate-600">
                            <li>{{ __('app.landing.trust_sig_crypto') }} · {{ __('app.landing.trust_sig_crypto_desc') }}</li>
                            <li>{{ __('app.landing.trust_sig_sdk') }} · {{ __('app.landing.trust_sig_sdk_desc') }}</li>
                            <li>{{ __('app.landing.trust_sig_deploy') }} · {{ __('app.landing.trust_sig_deploy_desc') }}</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div>
                <h2 class="text-2xl font-bold text-slate-900 mb-6 tracking-tight">{{ __('app.contact_page.form_title') }}</h2>
                <div id="contact-success" class="hidden bg-emerald-50 border border-emerald-200 rounded-xl p-6 text-center mb-6">
                    <h3 class="text-lg font-bold text-emerald-800 mb-1">{{ __('app.contact_page.success_title') }}</h3>
                    <p class="text-emerald-700 text-sm">{{ __('app.contact_page.success_body') }}</p>
                </div>
                <form id="contact-form" onsubmit="return submitContact(event)" class="space-y-4">
                    <input type="text" name="website_url" class="hidden" tabindex="-1" autocomplete="off">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.company') }}</label>
                        <input type="text" id="field-company" required class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.name') }}</label>
                        <input type="text" id="field-name" required class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.email_field') }}</label>
                            <input type="email" id="field-email" required class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.phone_field') }}</label>
                            <input type="tel" id="field-phone" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.employees') }}</label>
                            <select id="field-employees" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                                <option value="">{{ __('app.contact_page.choose') }}</option>
                                <option>1-10</option><option>11-50</option><option>51-200</option>
                                <option>201-1000</option><option>1000+</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.interest') }}</label>
                            <select id="field-interest" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm">
                                <option value="">{{ __('app.contact_page.choose') }}</option>
                                <option value="License">{{ __('app.contact_page.interest_license') }}</option>
                                <option value="SDK">{{ __('app.contact_page.interest_sdk') }}</option>
                                <option value="OEM">{{ __('app.contact_page.interest_oem') }}</option>
                                <option value="Enterprise">{{ __('app.contact_page.interest_enterprise') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ __('app.contact_page.message') }}</label>
                        <textarea id="field-message" rows="3" class="w-full px-4 py-2.5 border border-slate-200 rounded-lg focus:ring-2 focus:ring-slate-400 focus:border-transparent text-sm" placeholder="{{ __('app.contact_page.message_ph') }}"></textarea>
                    </div>
                    <button type="submit" id="contact-submit-btn" class="w-full bg-slate-900 text-white px-6 py-3 rounded-xl font-medium hover:bg-slate-800 transition text-sm">
                        {{ __('app.contact_page.submit') }}
                    </button>
                    <p id="contact-msg" class="text-sm hidden text-center"></p>
                </form>
            </div>
        </div>
    </section>

    <section class="py-16 md:py-20 bg-slate-50 border-t border-slate-100">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-2xl font-bold text-slate-900 text-center mb-10 tracking-tight">{{ __('app.contact_page.why_title') }}</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 mb-2">{{ __('app.contact_page.why_secure') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.contact_page.why_secure_desc') }}</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 mb-2">{{ __('app.contact_page.why_fast') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.contact_page.why_fast_desc') }}</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                    </div>
                    <h3 class="font-semibold text-slate-900 mb-2">{{ __('app.contact_page.why_compliance') }}</h3>
                    <p class="text-sm text-slate-500">{{ __('app.contact_page.why_compliance_desc') }}</p>
                </div>
            </div>
        </div>
    </section>

    <script>
    async function submitContact(e) {
        e.preventDefault();
        var btn = document.getElementById('contact-submit-btn');
        var msg = document.getElementById('contact-msg');
        var submitLabel = @json(__('app.contact_page.submit'));
        var submittingLabel = @json(__('app.contact_page.submitting'));
        btn.disabled = true;
        btn.textContent = submittingLabel;
        msg.classList.add('hidden');

        var data = {
            company_name: document.getElementById('field-company')?.value || '',
            contact_name: document.getElementById('field-name')?.value || '',
            email: document.getElementById('field-email')?.value || '',
            phone: document.getElementById('field-phone')?.value || '',
            employee_count: document.getElementById('field-employees')?.value || '',
            product_interest: document.getElementById('field-interest')?.value || '',
            message: document.getElementById('field-message')?.value || '',
            source: 'contact',
            website_url: document.querySelector('input[name="website_url"]')?.value || '',
        };

        try {
            var r = await fetch('/api/public/contact', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(data),
            });
            var res = await r.json();
            if (res.success) {
                document.getElementById('contact-success').classList.remove('hidden');
                document.getElementById('contact-form').classList.add('hidden');
            } else {
                msg.textContent = res.message || 'Error';
                msg.classList.remove('hidden');
                btn.disabled = false;
                btn.textContent = submitLabel;
            }
        } catch (err) {
            msg.textContent = 'Error';
            msg.classList.remove('hidden');
            btn.disabled = false;
            btn.textContent = submitLabel;
        }
    }
    </script>

    @include('public.partials.footer')
</body>
</html>
