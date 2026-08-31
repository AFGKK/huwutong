<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $policy['program_name'] }} - {{ __('app.legal_page.security_title') }}</title>
    <meta name="description" content="{{ __('app.legal_page.security_meta') }}">
    <meta name="robots" content="all">
    @vite('resources/css/public.css')
    @include('public.partials.tracking')
    <style>
        .sec-wrap { max-width: 860px; margin: 0 auto; }
        .sec-card { background: #fff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 2.5rem; color: #1e293b; }
        .sec-card p, .sec-card li { color: #475569; line-height: 1.7; }
        .sec-card h2 { font-size: 1.25rem; margin: 2rem 0 .75rem; padding-bottom: .5rem; border-bottom: 1px solid #e2e8f0; color: var(--pg-primary); font-weight: 700; }
        .sec-card h3 { font-size: 1.05rem; margin: 1.5rem 0 .5rem; color: #334155; font-weight: 600; }
        .sec-card ul, .sec-card ol { padding-left: 1.5rem; margin: .75rem 0; }
        .sec-card li { margin-bottom: .4rem; }
        .sec-card table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        .sec-card th, .sec-card td { padding: .6rem .8rem; text-align: left; border: 1px solid #e2e8f0; }
        .sec-card th { background: var(--pg-primary); color: #fff; font-weight: 600; }
        .sec-card tr:nth-child(even) { background: #f8fafc; }
        .contact-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin: 1rem 0; }
        .contact-box code { display: block; padding: .3rem 0; color: #334155; }
        .safe-harbor { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #166534; }
        .disclosure-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #92400e; }
        .response-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #334155; }
        .badge { display: inline-block; padding: .15em .5em; font-size: .8rem; font-weight: 600; border-radius: 4px; text-transform: uppercase; }
        .badge-critical { background: #e74c3c; color: #fff; }
        .badge-high { background: #e67e22; color: #fff; }
        .badge-medium { background: #f1c40f; color: #333; }
        .badge-low { background: #64748b; color: #fff; }
        .badge-info { background: #94a3b8; color: #fff; }
        .sec-card a { color: var(--pg-primary); text-decoration: underline; }
        @media (max-width: 600px) { .sec-card { padding: 1.5rem; } }
    </style>
</head>
<body class="font-sans antialiased bg-white text-slate-800">
    @include('public.partials.nav')
    @include('public.partials.page-hero', [
        'heroTitle' => $policy['program_name'],
        'heroSubtitle' => __('app.legal_page.sec_updated', ['date' => $policy['last_updated']]),
        'heroCrumb' => __('app.legal_page.security_title'),
    ])
    <div class="sec-wrap px-4 sm:px-6 lg:px-8 py-12 md:py-16">
    <div class="sec-card">

        <p>{{ __('app.legal_page.sec_intro') }}</p>

        <h2>{{ __('app.legal_page.sec_scope') }}</h2>
        <p>{{ __('app.legal_page.sec_scope_in') }}</p>
        <ul>
            @foreach($policy['scope'] as $item)
                <li><code>{{ $item }}</code></li>
            @endforeach
        </ul>

        <h3>{{ __('app.legal_page.sec_scope_out') }}</h3>
        <ul>
            @foreach($policy['out_of_scope'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h2>{{ __('app.legal_page.sec_rewards') }}</h2>
        <p>{{ __('app.legal_page.sec_rewards_intro') }}</p>
        <table>
            <thead>
                <tr>
                    <th>{{ __('app.legal_page.sec_th_severity') }}</th>
                    <th>{{ __('app.legal_page.sec_th_class') }}</th>
                    <th>{{ __('app.legal_page.sec_th_range') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($policy['rewards'] as $severity => $reward)
                <tr>
                    <td><span class="badge badge-{{ $severity === 'informational' ? 'info' : $severity }}">{{ \App\Models\BugBountyReport::severityLabel($severity) }}</span></td>
                    <td>{{ ucfirst($severity) }}</td>
                    <td>{{ $reward['label'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <h2>{{ __('app.legal_page.sec_rules') }}</h2>
        <ol>
            @foreach($policy['rules'] as $rule)
                <li>{{ $rule }}</li>
            @endforeach
        </ol>

        <h2>{{ __('app.legal_page.sec_disclosure') }}</h2>
        <div class="disclosure-box">
            {{ $policy['disclosure_policy'] }}
        </div>

        <h2>{{ __('app.legal_page.sec_harbor') }}</h2>
        <div class="safe-harbor">
            {{ $policy['legal_safe_harbor'] }}
        </div>

        <h2>{{ __('app.legal_page.sec_response') }}</h2>
        <div class="response-box">
            {{ $policy['response_time'] }}
        </div>

        <h2>{{ __('app.legal_page.sec_contact') }}</h2>
        <div class="contact-box">
            <strong>{{ __('app.legal_page.sec_email') }}</strong> <code><a href="mailto:{{ $policy['contact']['email'] }}">{{ $policy['contact']['email'] }}</a></code>
            <strong>{{ __('app.legal_page.sec_pgp') }}</strong> <code>{{ $policy['contact']['pgp_fingerprint'] }}</code>
            <strong>{{ __('app.legal_page.sec_h1') }}</strong> <code><a href="{{ $policy['contact']['hackerone'] }}">{{ $policy['contact']['hackerone'] }}</a></code>
            <strong>{{ __('app.legal_page.sec_bugcrowd') }}</strong> <code><a href="{{ $policy['contact']['bugcrowd'] }}">{{ $policy['contact']['bugcrowd'] }}</a></code>
        </div>

        <h2>{{ __('app.legal_page.sec_submit') }}</h2>
        <p>{{ __('app.legal_page.sec_submit_intro') }}</p>
        <ul>
            <li>{!! str_replace(':email', '<a href="mailto:'.e($policy['contact']['email']).'">'.e($policy['contact']['email']).'</a>', e(__('app.legal_page.sec_submit_email'))) !!}</li>
            <li>{{ __('app.legal_page.sec_submit_h1') }} <a href="{{ $policy['contact']['hackerone'] }}">{{ $policy['contact']['hackerone'] }}</a></li>
            <li>{{ __('app.legal_page.sec_submit_bc') }} <a href="{{ $policy['contact']['bugcrowd'] }}">{{ $policy['contact']['bugcrowd'] }}</a></li>
            <li><a href="/security/report">{{ __('app.legal_page.sec_submit_form') }}</a></li>
        </ul>

    </div>
    </div>

    @include('public.partials.footer')
</body>
</html>
