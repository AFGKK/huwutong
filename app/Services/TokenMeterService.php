<?php

namespace App\Services;

use App\Models\TokenAlert;
use App\Models\TokenBudget;
use App\Models\TokenConsumptionRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TokenMeterService
{
    /** 记录 Token 消耗 */
    public function record(array $data): TokenConsumptionRecord
    {
        $model = $data['model'] ?? 'unknown';
        $pricing = config("token-meter.models.{$model}", [
            'cost_per_1k_input' => 0,
            'cost_per_1k_output' => 0,
            'provider' => 'unknown',
        ]);

        $inputTokens = $data['input_tokens'] ?? 0;
        $outputTokens = $data['output_tokens'] ?? 0;
        $cost = ($inputTokens / 1000) * $pricing['cost_per_1k_input']
              + ($outputTokens / 1000) * $pricing['cost_per_1k_output'];

        $record = TokenConsumptionRecord::create([
            'tenant_id' => $data['tenant_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'model' => $model,
            'provider' => $pricing['provider'],
            'feature' => $data['feature'] ?? null,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'cost' => round($cost, 6),
            'currency' => 'USD',
            'session_id' => $data['session_id'] ?? null,
            'request_id' => $data['request_id'] ?? null,
            'cached' => $data['cached'] ?? false,
        ]);

        // 检查预算告警
        $this->checkBudgetAlerts($record->tenant_id);

        return $record;
    }

    /** 批量记录 */
    public function batchRecord(array $records): int
    {
        $inserted = 0;
        foreach ($records as $data) {
            $this->record($data);
            $inserted++;
        }
        return $inserted;
    }

    /** 仪表盘统计 */
    public function getDashboard(): array
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $todayStart = $now->copy()->startOfDay();

        $monthly = TokenConsumptionRecord::where('created_at', '>=', $monthStart);
        $daily = TokenConsumptionRecord::where('created_at', '>=', $todayStart);

        $totalMonthlyCost = (clone $monthly)->sum('cost');
        $totalDailyCost = (clone $daily)->sum('cost');
        $totalMonthlyTokens = (clone $monthly)->sum('total_tokens');
        $totalRequests = (clone $monthly)->count();
        $activeTenants = (clone $monthly)->distinct('tenant_id')->count('tenant_id');

        // 按模型统计
        $byModel = (clone $monthly)
            ->select('model', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as requests'))
            ->groupBy('model')
            ->orderByDesc('cost')
            ->get();

        // 按功能统计
        $byFeature = (clone $monthly)
            ->select('feature', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as requests'))
            ->whereNotNull('feature')
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get();

        // 按提供商统计
        $byProvider = (clone $monthly)
            ->select('provider', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'))
            ->groupBy('provider')
            ->orderByDesc('cost')
            ->get();

        // 最近 30 天趋势
        $dailyTrend = TokenConsumptionRecord::where('created_at', '>=', $now->copy()->subDays(30))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // 按租户统计（top 10）
        $byTenant = (clone $monthly)
            ->select('tenant_id', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as requests'))
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->orderByDesc('cost')
            ->limit(10)
            ->get();

        return compact(
            'totalMonthlyCost', 'totalDailyCost', 'totalMonthlyTokens', 'totalRequests', 'activeTenants',
            'byModel', 'byFeature', 'byProvider', 'dailyTrend', 'byTenant'
        );
    }

    /** 获取详细记录 */
    public function getRecords(array $params = [])
    {
        $query = TokenConsumptionRecord::query();

        if (!empty($params['tenant_id'])) {
            $query->where('tenant_id', $params['tenant_id']);
        }
        if (!empty($params['model'])) {
            $query->where('model', $params['model']);
        }
        if (!empty($params['feature'])) {
            $query->where('feature', $params['feature']);
        }
        if (!empty($params['date_from'])) {
            $query->where('created_at', '>=', $params['date_from']);
        }
        if (!empty($params['date_to'])) {
            $query->where('created_at', '<=', $params['date_to'] . ' 23:59:59');
        }

        $query->orderByDesc('created_at');
        $perPage = min((int)($params['per_page'] ?? 25), 100);
        return $query->paginate($perPage);
    }

    // ─── 预算管理 ───

    /** 创建/更新预算 */
    public function upsertBudget(array $data): TokenBudget
    {
        $attrs = [
            'tenant_id' => $data['tenant_id'] ?? null,
            'period' => $data['period'] ?? 'monthly',
        ];

        return TokenBudget::updateOrCreate($attrs, [
            'budget_limit' => $data['budget_limit'],
            'alert_threshold_1' => $data['alert_threshold_1'] ?? 50,
            'alert_threshold_2' => $data['alert_threshold_2'] ?? 80,
            'alert_threshold_3' => $data['alert_threshold_3'] ?? 90,
            'hard_cap' => $data['hard_cap'] ?? false,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /** 获取预算列表 */
    public function getBudgets(?int $tenantId = null)
    {
        $query = TokenBudget::with('tenant:id,name');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        return $query->orderByDesc('created_at')->get();
    }

    /** 获取本月已用额度 */
    public function getCurrentSpend(?int $tenantId = null): float
    {
        $query = TokenConsumptionRecord::where('created_at', '>=', now()->startOfMonth());
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        return (float) $query->sum('cost');
    }

    /** 检查预算告警 */
    public function checkBudgetAlerts(?int $tenantId): array
    {
        $alerts = [];

        // 检查全局预算
        $globalBudget = TokenBudget::whereNull('tenant_id')->where('is_active', true)->first();
        if ($globalBudget) {
            $alerts = array_merge($alerts, $this->checkSingleBudget($globalBudget, null));
        }

        // 检查租户预算
        if ($tenantId) {
            $tenantBudget = TokenBudget::where('tenant_id', $tenantId)->where('is_active', true)->first();
            if ($tenantBudget) {
                $alerts = array_merge($alerts, $this->checkSingleBudget($tenantBudget, $tenantId));
            }
        }

        return $alerts;
    }

    private function checkSingleBudget(TokenBudget $budget, ?int $tenantId): array
    {
        $alerts = [];
        $currentSpend = $this->getCurrentSpend($tenantId);

        if ($budget->budget_limit <= 0) {
            return $alerts;
        }

        $pct = ($currentSpend / $budget->budget_limit) * 100;
        $thresholds = [$budget->alert_threshold_1, $budget->alert_threshold_2, $budget->alert_threshold_3, 100];

        foreach ($thresholds as $threshold) {
            if ($pct >= $threshold) {
                $exists = TokenAlert::where('token_budget_id', $budget->id)
                    ->where('type', 'threshold_exceeded')
                    ->where('threshold_pct', $threshold)
                    ->whereNull('resolved_at')
                    ->exists();

                if (!$exists) {
                    $alert = TokenAlert::create([
                        'tenant_id' => $tenantId,
                        'token_budget_id' => $budget->id,
                        'type' => $threshold >= 100 ? 'hard_cap_reached' : 'threshold_exceeded',
                        'threshold_pct' => $threshold,
                        'current_spend' => $currentSpend,
                        'budget_limit' => $budget->budget_limit,
                    ]);
                    $alerts[] = $alert;
                }
            }
        }

        return $alerts;
    }

    /** 获取告警列表 */
    public function getAlerts(?int $tenantId = null)
    {
        $query = TokenAlert::with('budget');
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        return $query->orderByDesc('created_at')->limit(50)->get();
    }

    /** 解决告警 */
    public function resolveAlert(int $id): bool
    {
        return TokenAlert::where('id', $id)->update(['resolved_at' => now()]);
    }

    /** 获取模型定价列表 */
    public function getModelPricing(): array
    {
        return config('token-meter.models', []);
    }

    /** 获取功能配置 */
    public function getFeatures(): array
    {
        return config('token-meter.features', []);
    }

    /** 租户月度报告 */
    public function getTenantReport(int $tenantId, ?string $month = null): array
    {
        $date = $month ? Carbon::parse($month) : now();
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $records = TokenConsumptionRecord::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$start, $end]);

        $totalCost = (clone $records)->sum('cost');
        $totalTokens = (clone $records)->sum('total_tokens');
        $totalRequests = (clone $records)->count();

        $byModel = (clone $records)
            ->select('model', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'), DB::raw('COUNT(*) as requests'))
            ->groupBy('model')->get();

        $byFeature = (clone $records)
            ->select('feature', DB::raw('SUM(total_tokens) as tokens'), DB::raw('SUM(cost) as cost'))
            ->whereNotNull('feature')->groupBy('feature')->get();

        $dailyTrend = (clone $records)
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('SUM(cost) as cost'))
            ->groupBy('date')->orderBy('date')->get();

        return compact('totalCost', 'totalTokens', 'totalRequests', 'byModel', 'byFeature', 'dailyTrend');
    }

    // ─── 成本分摊 (M2-77 Cost Allocation) ───

    /** 成本分摊报告 — 按租户×功能矩阵 */
    public function getCostAllocationReport(?string $month = null): array
    {
        $date = $month ? Carbon::parse($month) : now();
        $start = $date->copy()->startOfMonth();
        $end = $date->copy()->endOfMonth();

        $records = TokenConsumptionRecord::whereBetween('created_at', [$start, $end]);

        $totalCost = (float) (clone $records)->sum('cost');
        $totalTokens = (int) (clone $records)->sum('total_tokens');
        $totalRequests = (int) (clone $records)->count();

        // 按租户分摊
        $byTenant = (clone $records)
            ->select('tenant_id',
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('COUNT(*) as requests'),
                DB::raw('SUM(input_tokens) as input_tokens'),
                DB::raw('SUM(output_tokens) as output_tokens'))
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')
            ->orderByDesc('cost')
            ->get()
            ->map(function ($item) use ($totalCost) {
                return [
                    'tenant_id' => $item->tenant_id,
                    'tokens' => (int) $item->tokens,
                    'cost' => (float) $item->cost,
                    'requests' => (int) $item->requests,
                    'input_tokens' => (int) $item->input_tokens,
                    'output_tokens' => (int) $item->output_tokens,
                    'pct' => $totalCost > 0 ? round(($item->cost / $totalCost) * 100, 2) : 0,
                ];
            });

        // 按功能分摊
        $byFeature = (clone $records)
            ->select('feature',
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('COUNT(*) as requests'))
            ->whereNotNull('feature')
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get()
            ->map(function ($item) use ($totalCost) {
                return [
                    'feature' => $item->feature,
                    'feature_label' => config("token-meter.features.{$item->feature}", $item->feature),
                    'tokens' => (int) $item->tokens,
                    'cost' => (float) $item->cost,
                    'requests' => (int) $item->requests,
                    'pct' => $totalCost > 0 ? round(($item->cost / $totalCost) * 100, 2) : 0,
                ];
            });

        // 按模型分摊
        $byModel = (clone $records)
            ->select('model', 'provider',
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'),
                DB::raw('COUNT(*) as requests'))
            ->groupBy('model', 'provider')
            ->orderByDesc('cost')
            ->get()
            ->map(function ($item) use ($totalCost) {
                return [
                    'model' => $item->model,
                    'provider' => $item->provider,
                    'tokens' => (int) $item->tokens,
                    'cost' => (float) $item->cost,
                    'requests' => (int) $item->requests,
                    'pct' => $totalCost > 0 ? round(($item->cost / $totalCost) * 100, 2) : 0,
                ];
            });

        // 租户×功能矩阵（热力图数据）
        $tenantFeatureMatrix = (clone $records)
            ->select('tenant_id', 'feature',
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'))
            ->whereNotNull('tenant_id')
            ->whereNotNull('feature')
            ->groupBy('tenant_id', 'feature')
            ->orderBy('tenant_id')
            ->get();

        // 租户×模型矩阵
        $tenantModelMatrix = (clone $records)
            ->select('tenant_id', 'model',
                DB::raw('SUM(total_tokens) as tokens'),
                DB::raw('SUM(cost) as cost'))
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id', 'model')
            ->orderBy('tenant_id')
            ->get();

        return compact(
            'totalCost', 'totalTokens', 'totalRequests',
            'byTenant', 'byFeature', 'byModel',
            'tenantFeatureMatrix', 'tenantModelMatrix'
        );
    }

    /** 导出成本分摊 CSV */
    public function exportCostAllocationCsv(?string $month = null): string
    {
        $report = $this->getCostAllocationReport($month);
        $date = $month ? Carbon::parse($month) : now();
        $period = $date->format('Y-m');

        $csv = [];
        $csv[] = ['Token 成本分摊报告', $period, '', '', '', ''];
        $csv[] = ['总额', $report['totalCost'], 'USD', '', '', ''];
        $csv[] = ['总 Token', $report['totalTokens'], '', '', '', ''];
        $csv[] = [''];

        // 按租户
        $csv[] = ['--- 按租户分摊 ---', '', '', '', '', ''];
        $csv[] = ['租户 ID', 'Token', '费用 (USD)', '占比 (%)', '请求数', ''];
        foreach ($report['byTenant'] as $row) {
            $csv[] = [$row['tenant_id'], $row['tokens'], $row['cost'], $row['pct'], $row['requests'], ''];
        }

        $csv[] = [''];
        // 按功能
        $csv[] = ['--- 按功能分摊 ---', '', '', '', '', ''];
        $csv[] = ['功能', 'Token', '费用 (USD)', '占比 (%)', '请求数', ''];
        foreach ($report['byFeature'] as $row) {
            $csv[] = [$row['feature_label'], $row['tokens'], $row['cost'], $row['pct'], $row['requests'], ''];
        }

        $csv[] = [''];
        // 按模型
        $csv[] = ['--- 按模型分摊 ---', '', '', '', '', ''];
        $csv[] = ['模型', '提供商', 'Token', '费用 (USD)', '占比 (%)', '请求数'];
        foreach ($report['byModel'] as $row) {
            $csv[] = [$row['model'], $row['provider'], $row['tokens'], $row['cost'], $row['pct'], $row['requests']];
        }

        $stream = fopen('php://temp', 'r+');
        foreach ($csv as $line) {
            fputcsv($stream, $line);
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return $content;
    }

    /** 获取分摊摘要 — 供仪表盘展示 */
    public function getAllocationSummary(?string $month = null): array
    {
        $report = $this->getCostAllocationReport($month);

        // Top 5 租户
        $topTenants = collect($report['byTenant'])->take(5)->values();

        // Top 5 功能
        $topFeatures = collect($report['byFeature'])->take(5)->values();

        // Top 5 模型
        $topModels = collect($report['byModel'])->take(5)->values();

        // 费用集中度 (Top 3 租户占比)
        $top3TenantPct = collect($report['byTenant'])->take(3)->sum('pct');
        $top3FeaturePct = collect($report['byFeature'])->take(3)->sum('pct');

        // 租户数
        $tenantCount = count($report['byTenant']);

        return compact(
            'topTenants', 'topFeatures', 'topModels',
            'top3TenantPct', 'top3FeaturePct', 'tenantCount'
        );
    }
}
