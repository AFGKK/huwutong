<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Models\AgentMonthlySnapshot;
use App\Models\CommissionSettlement;
use Illuminate\Console\Command;

class GenerateAgentMonthlySnapshots extends Command
{
    protected $signature = 'agent:snapshot {--year-month= : 格式 YYYY-MM，默认上月}';
    protected $description = '生成代理商月度业绩快照';

    public function handle(): int
    {
        $yearMonth = $this->option('year-month') ?? now()->subMonth()->format('Y-m');

        $this->info("正在生成 {$yearMonth} 月度快照...");

        $agents = Agent::where('status', 'active')->get();
        $count = 0;

        foreach ($agents as $agent) {
            $revenue = CommissionSettlement::where('agent_id', $agent->id)
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$yearMonth])
                ->sum('amount');

            $countCommissions = CommissionSettlement::where('agent_id', $agent->id)
                ->whereRaw("DATE_FORMAT(created_at, '%Y-%m') = ?", [$yearMonth])
                ->count();

            AgentMonthlySnapshot::updateOrCreate(
                ['agent_id' => $agent->id, 'year_month' => $yearMonth],
                [
                    'revenue' => $revenue,
                    'commission_earned' => $revenue * ($agent->commission_rate / 100),
                    'new_subscriptions' => $agent->tier_subscriptions_total,
                    'new_referrals' => $agent->tier_referrals_total,
                    'new_downline' => $agent->downline_count,
                    'conversion_rate' => $countCommissions > 0 ? min(100, ($countCommissions / max(1, $agent->tier_subscriptions_total)) * 100) : 0,
                ]
            );

            $count++;
        }

        $this->info("已生成 {$count} 个代理商的月度快照");
        return self::SUCCESS;
    }
}
