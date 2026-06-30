<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $policy['program_name'] }} - Security Policy</title>
    <meta name="robots" content="all">
    @vite('resources/css/public.css')
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6; background: #f8f9fa; margin: 0; padding-top: 80px;
        }
        .container { max-width: 860px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); padding: 2.5rem; color: #1a1a2e; }
        .container p, .container li { color: #444; }
        h1 { font-size: 2rem; margin-bottom: .25rem; color: #e74c3c; }
        .subtitle { color: #666; margin-bottom: 2rem; font-size: .9rem; }
        h2 { font-size: 1.3rem; margin: 2rem 0 .75rem; padding-bottom: .5rem; border-bottom: 2px solid #e74c3c; color: #2c3e50; }
        h3 { font-size: 1.1rem; margin: 1.5rem 0 .5rem; color: #34495e; }
        p, li { color: #444; }
        ul, ol { padding-left: 1.5rem; margin: .75rem 0; }
        li { margin-bottom: .4rem; }
        table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
        th, td { padding: .6rem .8rem; text-align: left; border: 1px solid #dee2e6; }
        th { background: #e74c3c; color: #fff; font-weight: 600; }
        tr:nth-child(even) { background: #f8f9fa; }
        .contact-box { background: #f0f4f8; border-radius: 8px; padding: 1.25rem; margin: 1rem 0; }
        .contact-box code { display: block; padding: .3rem 0; color: #2c3e50; }
        .safe-harbor { background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #155724; }
        .disclosure-box { background: #fff3cd; border: 1px solid #ffeeba; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #856404; }
        .response-box { background: #cce5ff; border: 1px solid #b8daff; border-radius: 8px; padding: 1rem; margin: 1rem 0; color: #004085; }
        .badge { display: inline-block; padding: .15em .5em; font-size: .8rem; font-weight: 600; border-radius: 4px; text-transform: uppercase; }
        .badge-critical { background: #e74c3c; color: #fff; }
        .badge-high { background: #e67e22; color: #fff; }
        .badge-medium { background: #f1c40f; color: #333; }
        .badge-low { background: #3498db; color: #fff; }
        .badge-info { background: #95a5a6; color: #fff; }
        @media (max-width: 600px) { .container { padding: 1.5rem; } h1 { font-size: 1.5rem; } body { padding-top: 64px; } }
    </style>
</head>
<body>
    @include('public.partials.nav')
    <div class="container">
        <h1>🔒 {{ $policy['program_name'] }}</h1>
        <p class="subtitle">Last updated: {{ $policy['last_updated'] }}</p>

        <p>We take the security of our platform seriously. We welcome security researchers to help us keep HuWuTong safe for everyone. This document outlines our bug bounty program, scope, rewards, and rules.</p>

        <h2>Scope</h2>
        <p>The following properties are in scope:</p>
        <ul>
            @foreach($policy['scope'] as $item)
                <li><code>{{ $item }}</code></li>
            @endforeach
        </ul>

        <h3>Out of Scope</h3>
        <ul>
            @foreach($policy['out_of_scope'] as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>

        <h2>Rewards</h2>
        <p>Rewards are based on severity and impact. All rewards are in USD.</p>
        <table>
            <thead>
                <tr>
                    <th>Severity</th>
                    <th>Classification</th>
                    <th>Reward Range</th>
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

        <h2>Rules</h2>
        <ol>
            @foreach($policy['rules'] as $rule)
                <li>{{ $rule }}</li>
            @endforeach
        </ol>

        <h2>Disclosure Policy</h2>
        <div class="disclosure-box">
            {{ $policy['disclosure_policy'] }}
        </div>

        <h2>Safe Harbor</h2>
        <div class="safe-harbor">
            {{ $policy['legal_safe_harbor'] }}
        </div>

        <h2>Response Expectations</h2>
        <div class="response-box">
            {{ $policy['response_time'] }}
        </div>

        <h2>Contact</h2>
        <div class="contact-box">
            <strong>Email:</strong> <code><a href="mailto:{{ $policy['contact']['email'] }}">{{ $policy['contact']['email'] }}</a></code>
            <strong>PGP Fingerprint:</strong> <code>{{ $policy['contact']['pgp_fingerprint'] }}</code>
            <strong>HackerOne:</strong> <code><a href="{{ $policy['contact']['hackerone'] }}">{{ $policy['contact']['hackerone'] }}</a></code>
            <strong>Bugcrowd:</strong> <code><a href="{{ $policy['contact']['bugcrowd'] }}">{{ $policy['contact']['bugcrowd'] }}</a></code>
        </div>

        <h2>Submit a Report</h2>
        <p>To submit a vulnerability report, please use one of the following methods:</p>
        <ul>
            <li>Email us at <a href="mailto:{{ $policy['contact']['email'] }}">{{ $policy['contact']['email'] }}</a> (PGP encrypted preferred)</li>
            <li>Submit via HackerOne: <a href="{{ $policy['contact']['hackerone'] }}">{{ $policy['contact']['hackerone'] }}</a></li>
            <li>Submit via Bugcrowd: <a href="{{ $policy['contact']['bugcrowd'] }}">{{ $policy['contact']['bugcrowd'] }}</a></li>
            <li>Use our <a href="/security/report">online submission form</a></li>
        </ul>

    </div>

    @include('public.partials.footer')
</body>
</html>
