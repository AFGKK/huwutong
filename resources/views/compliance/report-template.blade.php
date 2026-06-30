<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>合规报告 - {{ $report->framework->name }} #{{ $report->id }}</title>
    <style>
        @page { margin: 2cm; }
        body { font-family: 'Noto Sans SC', 'Helvetica Neue', Arial, sans-serif; font-size: 11pt; color: #333; line-height: 1.6; }
        h1 { color: #1a1a2e; border-bottom: 3px solid #409eff; padding-bottom: 8px; font-size: 20pt; }
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
        .gap-table th { background: #409eff; color: white; padding: 8px 12px; text-align: left; }
        .gap-table td { padding: 8px 12px; border: 1px solid #dcdfe6; }
        .gap-table tr:nth-child(even) { background: #f5f7fa; }
        .evidence-stats { display: flex; gap: 16px; margin: 16px 0; }
        .evidence-stat { background: #ecf5ff; border-radius: 4px; padding: 12px 20px; text-align: center; flex: 1; }
        .evidence-stat .value { font-size: 18pt; font-weight: 700; color: #409eff; }
        .evidence-stat .label { font-size: 8pt; color: #909399; margin-top: 4px; }
        .footer { margin-top: 40px; padding-top: 16px; border-top: 1px solid #dcdfe6; font-size: 9pt; color: #909399; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $report->framework->name }} 合规评估报告</h1>
        <div class="subtitle">报告编号: {{ $report->id }} | 生成时间: {{ $generatedAt->format('Y-m-d H:i:s') }}</div>
    </div>

    <!-- 报告信息 -->
    <h2>报告信息</h2>
    <table class="meta-table">
        <tr><td>合规框架</td><td>{{ $report->framework->name }} ({{ $report->framework->code }})</td></tr>
        <tr><td>报告标题</td><td>{{ $report->title }}</td></tr>
        <tr><td>评估周期</td><td>{{ $report->period_start?->format('Y-m-d') ?? 'N/A' }} 至 {{ $report->period_end?->format('Y-m-d') ?? 'N/A' }}</td></tr>
        <tr><td>风险等级</td><td class="risk-{{ $report->risk_level }}">{{ strtoupper($report->risk_level) }}</td></tr>
        <tr><td>控制通过率</td><td>{{ $report->passed_count }}/{{ ($report->passed_count + $report->failed_count) }}</td></tr>
        <tr><td>生成人</td><td>{{ $report->generator?->name ?? 'System' }}</td></tr>
    </table>

    <!-- 摘要 -->
    <h2>执行摘要</h2>
    <div class="summary-box">
        <p>{{ $report->summary ?? '本次合规评估覆盖 ' . count($gaps) . ' 个控制域。请参考详细的差距分析结果。' }}</p>
    </div>

    <!-- 证据统计 -->
    <h2>证据收集统计</h2>
    <div class="evidence-stats">
        <div class="evidence-stat">
            <div class="value">{{ $evidenceStats['total'] ?? 0 }}</div>
            <div class="label">总证据数</div>
        </div>
        <div class="evidence-stat">
            <div class="value">{{ $evidenceStats['validated'] ?? 0 }}</div>
            <div class="label">已验证</div>
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

    <!-- 差距分析 -->
    <h2>控制域差距分析</h2>
    <table class="gap-table">
        <thead>
            <tr>
                <th>控制域</th>
                <th>控制名称</th>
                <th>风险等级</th>
                <th>当前状态</th>
                <th>整改状态</th>
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
            <tr><td colspan="5" style="text-align:center;color:#909399;">暂无差距分析数据</td></tr>
            @endforelse
        </tbody>
    </table>

    <!-- 整改建议 -->
    <h2>整改建议</h2>
    @php
        $highGaps = $gaps->where('priority', 'high');
        $mediumGaps = $gaps->where('priority', 'medium');
    @endphp
    @if($highGaps->count() > 0)
    <h3>🔴 高优先级 ({{ $highGaps->count() }} 项)</h3>
    <ul>
        @foreach($highGaps as $gap)
        <li><strong>{{ $gap->control_ref }}</strong>: {{ $gap->control_title }} — {{ $gap->remediation_plan }}</li>
        @endforeach
    </ul>
    @endif
    @if($mediumGaps->count() > 0)
    <h3>🟡 中优先级 ({{ $mediumGaps->count() }} 项)</h3>
    <ul>
        @foreach($mediumGaps as $gap)
        <li><strong>{{ $gap->control_ref }}</strong>: {{ $gap->control_title }} — {{ $gap->remediation_plan }}</li>
        @endforeach
    </ul>
    @endif

    <div class="footer">
        <p>HWT License - SOC 2 / ISO 27001 合规准备包</p>
        <p>本报告由系统自动生成，仅供参考。最终审计结果以认证机构评估为准。</p>
        <p>生成时间: {{ $generatedAt->format('Y-m-d H:i:s') }} | 页码: <span class="pageNumber"></span> / <span class="totalPages"></span></p>
    </div>
</body>
</html>
