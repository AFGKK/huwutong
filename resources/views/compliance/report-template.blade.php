@php
    $appName = __('app.app_name');
    $htmlLang = str_replace('_', '-', app()->getLocale());
    $generated = $generatedAt->format('Y-m-d H:i:s');
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('app.compliance_report.title', ['name' => $report->framework->name, 'id' => $report->id]) }}</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: 'Noto Sans SC', 'Helvetica Neue', Arial, sans-serif; font-size: 11pt; color: #333; line-height: 1.6; }
        h1 { color: #1a1a2e; border-bottom: 3px solid #0f172a; padding-bottom: 8px; font-size: 20pt; }
        h2 { color: #303133; font-size: 14pt; margin-top: 24px; }
        h3 { color: #606266; font-size: 12pt; }
        .header { text-align: center; margin-bottom: 30px; padding: 20px; border-bottom: 2px solid #eee; }
        .header h1 { border-bottom: none; }
        .header .subtitle { color: #909399; font-size: 10pt; }
        .meta-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .meta-table td { padding: 6px 12px; border: 1px solid #dcdfe6; }
        .meta-table td:first-child { width: 160px; font-weight: 600; background: #f5f7fa; }
        .risk-low { color: #67c23a; }
        .risk-medium { color: #e6a23c; }
        .risk-high { color: #f56c6c; }
        .risk-critical { color: #f56c6c; font-weight: bold; }
        .summary-box { background: #f0f9eb; border: 1px solid #e1f3d8; border-radius: 4px; padding: 16px; margin: 16px 0; }
        .gap-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .gap-table th { background: #0f172a; color: white; padding: 8px 12px; text-align: left; }
        .gap-table td { padding: 8px 12px; border: 1px solid #dcdfe6; }
        .gap-table tr:nth-child(even) { background: #f5f7fa; }
        .evidence-stats { display: flex; gap: 16px; margin: 16px 0; }
        .evidence-stat { background: #f1f5f9; border-radius: 4px; padding: 12px 20px; text-align: center; flex: 1; }
        .evidence-stat .value { font-size: 18pt; font-weight: 700; color: #0f172a; }
        .evidence-stat .label { font-size: 8pt; color: #909399; margin-top: 4px; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #dcdfe6; font-size: 9pt; color: #909399; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('app.compliance_report.heading', ['name' => $report->framework->name]) }}</h1>
        <div class="subtitle">{{ __('app.compliance_report.subtitle', ['id' => $report->id, 'time' => $generated]) }}</div>
    </div>

    <h2>{{ __('app.compliance_report.info_h') }}</h2>
    <table class="meta-table">
        <tr><td>{{ __('app.compliance_report.framework') }}</td><td>{{ $report->framework->name }} ({{ $report->framework->code }})</td></tr>
        <tr><td>{{ __('app.compliance_report.report_title') }}</td><td>{{ $report->title }}</td></tr>
        <tr>
            <td>{{ __('app.compliance_report.period') }}</td>
            <td>{{ $report->period_start?->format('Y-m-d') ?? 'N/A' }} {{ __('app.compliance_report.period_to') }} {{ $report->period_end?->format('Y-m-d') ?? 'N/A' }}</td>
        </tr>
        <tr><td>{{ __('app.compliance_report.risk') }}</td><td class="risk-{{ $report->risk_level }}">{{ strtoupper($report->risk_level) }}</td></tr>
        <tr><td>{{ __('app.compliance_report.pass_rate') }}</td><td>{{ $report->passed_count }}/{{ ($report->passed_count + $report->failed_count) }}</td></tr>
        <tr><td>{{ __('app.compliance_report.generator') }}</td><td>{{ $report->generator?->name ?? 'System' }}</td></tr>
    </table>

    <h2>{{ __('app.compliance_report.summary_h') }}</h2>
    <div class="summary-box">
        <p>{{ $report->summary ?? __('app.compliance_report.summary_default', ['n' => count($gaps)]) }}</p>
    </div>

    <h2>{{ __('app.compliance_report.evidence_h') }}</h2>
    <div class="evidence-stats">
        <div class="evidence-stat">
            <div class="value">{{ $evidenceStats['total'] ?? 0 }}</div>
            <div class="label">{{ __('app.compliance_report.evidence_total') }}</div>
        </div>
        <div class="evidence-stat">
            <div class="value">{{ $evidenceStats['validated'] ?? 0 }}</div>
            <div class="label">{{ __('app.compliance_report.evidence_validated') }}</div>
        </div>
        @if(!empty($evidenceStats['by_type']))
            @foreach($evidenceStats['by_type'] as $type => $count)
            <div class="evidence-stat">
                <div class="value">{{ $count }}</div>
                <div class="label">{{ $type }}</div>
            </div>
            @endforeach
        @endif
    </div>

    <h2>{{ __('app.compliance_report.gaps_h') }}</h2>
    <table class="gap-table">
        <thead>
            <tr>
                <th>{{ __('app.compliance_report.col_domain') }}</th>
                <th>{{ __('app.compliance_report.col_control') }}</th>
                <th>{{ __('app.compliance_report.col_risk') }}</th>
                <th>{{ __('app.compliance_report.col_state') }}</th>
                <th>{{ __('app.compliance_report.col_remediation') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($gaps as $gap)
            <tr>
                <td><strong>{{ $gap->control_ref }}</strong></td>
                <td>{{ $gap->control_title }}</td>
                <td class="risk-{{ $gap->risk_level }}">{{ strtoupper($gap->risk_level) }}</td>
                <td>{{ $gap->current_state }}</td>
                <td>{{ $gap->remediation_status }}</td>
            </tr>
            @empty
            <tr><td colspan="5" style="text-align:center;color:#909399;">{{ __('app.compliance_report.gaps_empty') }}</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>{{ __('app.compliance_report.remediation_h') }}</h2>
    @php
        $highGaps = $gaps->where('priority', 'high');
        $mediumGaps = $gaps->where('priority', 'medium');
    @endphp
    @if($highGaps->count() > 0)
    <h3>{{ __('app.compliance_report.priority_high', ['n' => $highGaps->count()]) }}</h3>
    <ul>
        @foreach($highGaps as $gap)
        <li><strong>{{ $gap->control_ref }}</strong>: {{ $gap->control_title }} — {{ $gap->remediation_plan }}</li>
        @endforeach
    </ul>
    @endif
    @if($mediumGaps->count() > 0)
    <h3>{{ __('app.compliance_report.priority_medium', ['n' => $mediumGaps->count()]) }}</h3>
    <ul>
        @foreach($mediumGaps as $gap)
        <li><strong>{{ $gap->control_ref }}</strong>: {{ $gap->control_title }} — {{ $gap->remediation_plan }}</li>
        @endforeach
    </ul>
    @endif

    <div class="footer">
        <p>{{ __('app.compliance_report.footer_brand', ['app_name' => $appName]) }}</p>
        <p>{{ __('app.compliance_report.footer_disclaimer') }}</p>
        <p>{{ __('app.compliance_report.footer_meta', ['time' => $generated]) }}<span class="pageNumber"></span> / <span class="totalPages"></span></p>
    </div>
</body>
</html>
