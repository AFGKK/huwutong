<?php

namespace App\Services;

use App\Models\ChurnIntervention;
use App\Models\ChurnPrediction;
use Illuminate\Support\Facades\DB;

/**
 * 客户流失分析与干预服务 (M3-25)
 *
 * 扩展 HealthScoreService 的流失预测能力：
 * 1. 流失预测清单（带客户、健康分、信号）
 * 2. 干预管理（CRUD）
 * 3. 趋势分析
 * 4. 专有仪表盘
 */
class ChurnPredictionService
{
    /**
     * 获取高流失风险客户列表
     */
    public function getChurnList(int $tenantId, array $filters = []): array
    {
        $query = ChurnPrediction::where('tenant_id', $tenantId)
            ->with('customer')
            ->orderByDesc('predicted_at');

        // 风险等级过滤
        if (!empty($filters['risk_level'])) {
            $levels = explode(',', $filters['risk_level']);
            $query->where(function ($q) use ($levels) {
                foreach ($levels as $level) {
                    $q->orWhere('risk_level', $level);
                }
            });
        }

        // 搜索（通过with关系过滤）
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('id', 'like', "%{$search}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;

        $results = $query->paginate($perPage)->withQueryString();

        $transformed = $results->through(function ($row) {
            $customer = $row->customer;
            return [
                'id' => $row->id,
                'customer_id' => $row->customer_id,
                'customer_name' => $customer ? "#{$customer->id}" : '已删除',
                'customer_status' => $customer ? $customer->status : null,
                'churn_score' => $row->churn_score,
                'churn_probability' => $row->churn_probability,
                'risk_level' => $row->risk_level ?? $row->churn_risk,
                'active_interventions' => ChurnIntervention::where('tenant_id', $row->tenant_id)
                    ->where('customer_id', $row->customer_id)
                    ->where('status', 'in_progress')
                    ->count(),
                'signals' => $this->parseSignals($row),
                'recommendations' => $this->parseRecommendations($row),
                'predicted_at' => $row->predicted_at,
            ];
        });

        return $transformed->toArray();
    }

    // ═══════ 干预管理 ═══════

    public function listInterventions(int $tenantId, array $filters = []): array
    {
        $query = ChurnIntervention::where('tenant_id', $tenantId)
            ->with('customer')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        $results = $query->paginate($filters['per_page'] ?? 20)->withQueryString();

        $transformed = $results->through(function ($row) {
            $customer = $row->customer;
            return [
                'id' => $row->id,
                'tenant_id' => $row->tenant_id,
                'customer_id' => $row->customer_id,
                'customer_name' => $customer ? "#{$customer->id}" : '已删除',
                'type' => $row->type,
                'title' => $row->title,
                'description' => $row->description,
                'assigned_to' => $row->assigned_to,
                'status' => $row->status,
                'result' => $row->result,
                'outcome' => $row->outcome,
                'scheduled_at' => $row->scheduled_at,
                'completed_at' => $row->completed_at,
                'created_at' => $row->created_at,
            ];
        });

        return $transformed->toArray();
    }

    public function createIntervention(array $data): ChurnIntervention
    {
        return ChurnIntervention::create($data);
    }

    public function updateIntervention(ChurnIntervention $intervention, array $data): ChurnIntervention
    {
        $intervention->update($data);

        if (($data['status'] ?? null) === 'completed' && !$intervention->completed_at) {
            $intervention->update(['completed_at' => now()]);
        }

        return $intervention->fresh();
    }

    public function deleteIntervention(ChurnIntervention $intervention): void
    {
        $intervention->delete();
    }

    // ═══════ 仪表盘 ═══════

    public function getDashboard(int $tenantId): array
    {
        $riskStats = ChurnPrediction::where('tenant_id', $tenantId)
            ->selectRaw("COALESCE(risk_level, 'unknown') as level, COUNT(*) as total")
            ->groupBy('level')
            ->get()
            ->pluck('total', 'level')
            ->toArray();

        $interventionStats = ChurnIntervention::where('tenant_id', $tenantId)
            ->selectRaw("status, COUNT(*) as total")
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();

        $interventionByType = ChurnIntervention::where('tenant_id', $tenantId)
            ->selectRaw("type, COUNT(*) as total")
            ->groupBy('type')
            ->get()
            ->pluck('total', 'type')
            ->toArray();

        $outcomeStats = ChurnIntervention::where('tenant_id', $tenantId)
            ->whereNotNull('outcome')
            ->selectRaw("outcome, COUNT(*) as total")
            ->groupBy('outcome')
            ->get()
            ->pluck('total', 'outcome')
            ->toArray();

        return [
            'churn_by_risk' => $riskStats,
            'total_at_risk' => array_sum(array_diff_key($riskStats, ['low' => 0, 'unknown' => 0])),
            'total_low_risk' => ($riskStats['low'] ?? 0) + ($riskStats['unknown'] ?? 0),
            'interventions' => [
                'total' => array_sum($interventionStats),
                'pending' => $interventionStats['pending'] ?? 0,
                'in_progress' => $interventionStats['in_progress'] ?? 0,
                'completed' => $interventionStats['completed'] ?? 0,
                'by_type' => $interventionByType,
            ],
            'outcomes' => $outcomeStats,
            'positive_rate' => $this->calculatePositiveRate($outcomeStats),
        ];
    }

    public function getTrend(int $tenantId, int $months = 12): array
    {
        $results = ChurnPrediction::where('tenant_id', $tenantId)
            ->where('predicted_at', '>=', now()->subMonths($months))
            ->selectRaw(db_date_format('predicted_at', '%Y-%m')." as month, COALESCE(risk_level, 'unknown') as level, COUNT(*) as total")
            ->groupBy('month', 'level')
            ->orderBy('month')
            ->get();

        $trend = [];
        foreach ($results as $row) {
            $m = $row['month'];
            if (!isset($trend[$m])) {
                $trend[$m] = ['month' => $m, 'critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
            }
            $trend[$m][$row['level']] = $row['total'];
        }

        return array_values($trend);
    }

    // ═══════ 辅助方法 ═══════

    protected function parseSignals($row): array
    {
        $raw = $row->top_signals ?? $row->signals ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [$raw];
        }
        return (array) $raw;
    }

    protected function parseRecommendations($row): array
    {
        $raw = $row->recommendations ?? $row->recommended_action ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [['action' => $raw]];
        }
        return (array) ($raw ?? []);
    }

    protected function calculatePositiveRate(array $outcomeStats): float
    {
        $total = array_sum($outcomeStats);
        if ($total === 0) return 0;
        $positive = ($outcomeStats['positive'] ?? 0) + ($outcomeStats['neutral'] ?? 0);
        return round(($positive / $total) * 100, 1);
    }
}
