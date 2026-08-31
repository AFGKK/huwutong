@php
    $appName = __('app.app_name');
    $htmlLang = str_replace('_', '-', app()->getLocale());
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.a11y_declaration.title', ['app_name' => $appName]) }}</title>
    @vite('resources/css/public.css')
    <script>window.A11Y_I18N = @json(__('app.a11y_declaration'));</script>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #202124; background: #fff; margin: 0; }
        .a11y-container { max-width: 800px; margin: 0 auto; padding: 24px; }
        h1 { font-size: 1.8em; border-bottom: 2px solid #1a73e8; padding-bottom: 8px; }
        h2 { font-size: 1.3em; margin-top: 32px; color: #1a73e8; }
        h3 { font-size: 1.1em; margin-top: 24px; }
        .badge { display: inline-block; background: #1a73e8; color: #fff; padding: 4px 12px; border-radius: 4px; font-size: 14px; margin: 8px 0; }
        .status { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 500; }
        .status-compliant { background: #e6f4ea; color: #1e7e34; }
        .status-needs-work { background: #fef7e0; color: #ea8600; }
        .status-not-applicable { background: #f1f3f4; color: #5f6368; }
        table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        th, td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #dadce0; font-size: 14px; }
        th { background: #f8f9fa; font-weight: 600; }
        .progress-bar { height: 8px; background: #dadce0; border-radius: 4px; margin: 8px 0; overflow: hidden; }
        .progress-fill { height: 100%; background: #34a853; border-radius: 4px; }
        .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
        @media (max-width: 600px) { .a11y-container { padding: 16px; } table { font-size: 13px; } }
    </style>
</head>
<body>
    <a href="#main" class="sr-only" style="position:absolute;left:8px;top:8px;background:#1a73e8;color:#fff;padding:8px 16px;border-radius:4px;z-index:9999;text-decoration:none">{{ __('app.a11y_declaration.skip') }}</a>

    <div class="a11y-container">
    <header role="banner">
        <span class="badge">WCAG 2.1 AA</span>
        <h1>{{ __('app.a11y_declaration.heading') }}</h1>
        <p>{{ __('app.a11y_declaration.intro', ['app_name' => $appName]) }}</p>
        <p>{{ __('app.a11y_declaration.updated', ['date' => '2026-06-13']) }}</p>
    </header>

    <main id="main" role="main">
        <h2>{{ __('app.a11y_declaration.compliance_h') }}</h2>
        <p>{!! __('app.a11y_declaration.compliance_p') !!}</p>

        <div class="progress-bar" role="progressbar" aria-valuenow="92" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('app.a11y_declaration.progress_label') }}">
            <div class="progress-fill" style="width:92%"></div>
        </div>

        <h2>{{ __('app.a11y_declaration.features_h') }}</h2>
        <ul>
            <li>{!! __('app.a11y_declaration.feat_keyboard') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_sr') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_skip') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_contrast') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_zoom') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_focus') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_live') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_semantic') !!}</li>
            <li>{!! __('app.a11y_declaration.feat_shortcuts') !!}</li>
        </ul>

        <h2>{{ __('app.a11y_declaration.criteria_h') }}</h2>
        <p>{{ __('app.a11y_declaration.criteria_p') }}</p>

        <table aria-label="{{ __('app.a11y_declaration.table_criteria') }}">
            <thead>
                <tr>
                    <th>{{ __('app.a11y_declaration.col_id') }}</th>
                    <th>{{ __('app.a11y_declaration.col_level') }}</th>
                    <th>{{ __('app.a11y_declaration.col_name') }}</th>
                    <th>{{ __('app.a11y_declaration.col_status') }}</th>
                </tr>
            </thead>
            <tbody id="guidelines-table"></tbody>
        </table>

        <h2>{{ __('app.a11y_declaration.limits_h') }}</h2>
        <table aria-label="{{ __('app.a11y_declaration.table_limits') }}">
            <thead>
                <tr>
                    <th>{{ __('app.a11y_declaration.col_limit') }}</th>
                    <th>{{ __('app.a11y_declaration.col_impact') }}</th>
                    <th>{{ __('app.a11y_declaration.col_plan') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>{{ __('app.a11y_declaration.limit1') }}</td>
                    <td>{{ __('app.a11y_declaration.impact1') }}</td>
                    <td>{{ __('app.a11y_declaration.plan_next') }}</td>
                </tr>
                <tr>
                    <td>{{ __('app.a11y_declaration.limit2') }}</td>
                    <td>{{ __('app.a11y_declaration.impact2') }}</td>
                    <td>{{ __('app.a11y_declaration.plan_next') }}</td>
                </tr>
                <tr>
                    <td>{{ __('app.a11y_declaration.limit3') }}</td>
                    <td>{{ __('app.a11y_declaration.impact3') }}</td>
                    <td>{{ __('app.a11y_declaration.plan_planned') }}</td>
                </tr>
                <tr>
                    <td>{{ __('app.a11y_declaration.limit4') }}</td>
                    <td>{{ __('app.a11y_declaration.impact4') }}</td>
                    <td>{{ __('app.a11y_declaration.plan_planned') }}</td>
                </tr>
            </tbody>
        </table>

        <h2>{{ __('app.a11y_declaration.compat_h') }}</h2>
        <ul>
            <li>{!! __('app.a11y_declaration.compat_browser') !!}</li>
            <li>{!! __('app.a11y_declaration.compat_sr') !!}</li>
            <li>{!! __('app.a11y_declaration.compat_os') !!}</li>
        </ul>

        <h2>{{ __('app.a11y_declaration.feedback_h') }}</h2>
        <p>{{ __('app.a11y_declaration.feedback_p') }}</p>
        <ul>
            <li>{{ __('app.a11y_declaration.feedback_email') }}<a href="mailto:a11y@huwutong.com">a11y@huwutong.com</a></li>
            <li>{{ __('app.a11y_declaration.feedback_ticket') }}</li>
            <li>{{ __('app.a11y_declaration.feedback_phone') }}</li>
        </ul>
        <p>{!! __('app.a11y_declaration.feedback_sla') !!}</p>
    </main>

    </div>

    @include('public.partials.footer')

    <script>
    fetch('/api/a11y/guidelines')
        .then(r => r.json())
        .then(res => {
            const I = window.A11Y_I18N || {};
            const data = res.data || res;
            const tbody = document.getElementById('guidelines-table');
            if (!data || !data.length) {
                tbody.innerHTML = '<tr><td colspan="4">' + (I.load_empty || '') + '</td></tr>';
                return;
            }
            data.forEach(g => {
                const statusClass = g.status === 'compliant' ? 'status-compliant' : g.status === 'needs_work' ? 'status-needs-work' : 'status-not-applicable';
                const statusLabel = g.status === 'compliant' ? (I.status_ok || '') : g.status === 'needs_work' ? (I.status_needs || '') : (I.status_na || '');
                tbody.innerHTML += `<tr>
                    <td><strong>${g.id}</strong></td>
                    <td>${g.level}</td>
                    <td>${g.name}</td>
                    <td><span class="status ${statusClass}">${statusLabel}</span></td>
                </tr>`;
            });
        })
        .catch(() => {
            const I = window.A11Y_I18N || {};
            document.getElementById('guidelines-table').innerHTML = '<tr><td colspan="4">' + (I.load_fail || '') + '</td></tr>';
        });
    </script>
</body>
</html>
