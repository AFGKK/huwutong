<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\AgentTierDefinition;
use App\Models\CommissionSettlement;
use App\Models\EarningsAccount;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 代理商/经销商管理服务 (M3-04)
 *
 * 等级/分成比例/业绩报表/收益账户关联+佣金结算+提现入口
 */
class AgentManagerService
{
    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $total = Agent::count();
        $active = Agent::where('status', 'active')->count();
        $pending = Agent::where('status', 'pending')->count();

        $totalEarned = Agent::sum('total_earned');
        $totalWithdrawn = Agent::sum('total_withdrawn');
        $pendingCommission = CommissionSettlement::where('status', 'pending')->sum('amount');

        // 等级分布
        $byLevel = Agent::selectRaw('level, COUNT(*) as count')
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        // 月趋势 (近6月)
        $monthlyTrend = CommissionSettlement::selectRaw(
            db_date_format('created_at', '%Y-%m').' as month, SUM(amount) as total'
        )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        // 排行 Top 10
        $topAgents = Agent::where('status', 'active')
            ->orderBy('total_earned', 'desc')
            ->limit(10)
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'name' => $a->contact_name ?: $a->user?->name ?? 'N/A',
                'agent_code' => $a->agent_code,
                'level' => $a->level,
                'total_earned' => $a->total_earned,
                'total_withdrawn' => $a->total_withdrawn,
                'downline_count' => $a->downline_count,
            ])
            ->toArray();

        return [
            'total_agents' => $total,
            'active' => $active,
            'pending' => $pending,
            'total_earned' => $totalEarned,
            'total_withdrawn' => $totalWithdrawn,
            'pending_commission' => $pendingCommission,
            'by_level' => $byLevel,
            'monthly_trend' => $monthlyTrend,
            'top_agents' => $topAgents,
            'levels' => AgentTierDefinition::orderBy('sort_order')->get()->toArray(),
        ];
    }

    /**
     * 代理列表
     */
    public function listAgents(array $filters = [], int $perPage = 20): array
    {
        $query = Agent::with('user', 'parentAgent');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('agent_code', 'like', "%{$s}%")
                  ->orWhere('contact_name', 'like', "%{$s}%")
                  ->orWhere('company', 'like', "%{$s}%");
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->toArray();
    }

    /**
     * 创建代理
     */
    public function createAgent(array $data): Agent
    {
        $user = User::findOrFail($data['user_id']);

        // 创建收益账户
        $account = EarningsAccount::firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'active']
        );

        $agent = Agent::create([
            'user_id' => $user->id,
            'agent_code' => $this->generateCode(),
            'level' => $data['level'] ?? 'regular',
            'status' => $data['status'] ?? 'pending',
            'commission_rate' => $data['commission_rate'] ?? 5.0,
            'contact_name' => $data['contact_name'] ?? $user->name,
            'contact_phone' => $data['contact_phone'] ?? '',
            'company' => $data['company'] ?? '',
            'notes' => $data['notes'] ?? '',
            'parent_agent_id' => $data['parent_agent_id'] ?? null,
            'multi_level_rate' => $data['multi_level_rate'] ?? 0,
        ]);

        Log::info('Agent created', ['agent_id' => $agent->id, 'user_id' => $user->id]);

        return $agent;
    }

    /**
     * 更新代理
     */
    public function updateAgent(int $agentId, array $data): Agent
    {
        $agent = Agent::findOrFail($agentId);
        $agent->update($data);
        return $agent->fresh();
    }

    /**
     * 审核代理
     */
    public function approveAgent(int $agentId): Agent
    {
        $agent = Agent::findOrFail($agentId);
        $agent->update([
            'status' => 'active',
            'approved_at' => now(),
        ]);
        return $agent->fresh();
    }

    /**
     * 获取代理详情 (含收益和业绩)
     */
    public function getAgentDetail(int $agentId): array
    {
        $agent = Agent::with('user', 'parentAgent', 'childAgents')->findOrFail($agentId);

        // 收益账户
        $earnings = EarningsAccount::where('user_id', $agent->user_id)->first();

        // 结算记录
        $settlements = CommissionSettlement::where('agent_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->toArray();

        // 下级代理
        $downline = $agent->childAgents()->with('user')->get()->map(fn($a) => [
            'id' => $a->id,
            'name' => $a->contact_name ?: $a->user?->name ?? 'N/A',
            'level' => $a->level,
            'status' => $a->status,
            'total_earned' => $a->total_earned,
        ]);

        // 月度业绩
        $monthlyStats = CommissionSettlement::selectRaw(
            db_date_format('created_at', '%Y-%m').' as month, SUM(amount) as amount, COUNT(*) as count'
        )
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', now()->subMonths(12))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();

        return [
            'agent' => $agent->toArray(),
            'earnings_account' => $earnings,
            'recent_settlements' => $settlements,
            'downline_agents' => $downline,
            'monthly_performance' => $monthlyStats,
        ];
    }

    /**
     * 获取代理业绩报表
     */
    public function getPerformanceReport(int $agentId, string $period = 'monthly'): array
    {
        $groupBy = match ($period) {
            'daily' => db_date_format('created_at', '%Y-%m-%d'),
            'yearly' => db_date_format('created_at', '%Y'),
            default => db_date_format('created_at', '%Y-%m'),
        };

        $data = CommissionSettlement::selectRaw("{$groupBy} as period, SUM(amount) as amount, COUNT(*) as count")
            ->where('agent_id', $agentId)
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();

        $totals = CommissionSettlement::where('agent_id', $agentId)
            ->selectRaw('SUM(amount) as total, AVG(amount) as avg, COUNT(*) as count')
            ->first();

        return [
            'period' => $period,
            'data' => $data,
            'summary' => [
                'total' => $totals->total ?? 0,
                'average' => round($totals->avg ?? 0, 2),
                'count' => $totals->count ?? 0,
            ],
        ];
    }

    /**
     * 排行榜
     */
    public function getLeaderboard(string $metric = 'total_earned', int $limit = 20): array
    {
        $allowed = ['total_earned', 'total_withdrawn', 'downline_count', 'tier_referrals_total'];
        $metric = in_array($metric, $allowed) ? $metric : 'total_earned';

        return Agent::with('user')
            ->where('status', 'active')
            ->orderBy($metric, 'desc')
            ->limit($limit)
            ->get()
            ->map(fn($a, $i) => [
                'rank' => $i + 1,
                'id' => $a->id,
                'name' => $a->contact_name ?: $a->user?->name ?? 'N/A',
                'agent_code' => $a->agent_code,
                'level' => $a->level,
                'metric' => $a->$metric,
                'total_earned' => $a->total_earned,
                'downline_count' => $a->downline_count,
            ])
            ->toArray();
    }

    /**
     * 生成唯一代理编码
     */
    protected function generateCode(): string
    {
        do {
            $code = 'AGT-' . strtoupper(Str::random(6));
        } while (Agent::where('agent_code', $code)->exists());

        return $code;
    }
}
