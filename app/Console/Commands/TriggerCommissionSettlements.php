<?php

namespace App\Console\Commands;

use App\Models\CommissionSettlement;
use App\Workflows\WorkflowEngine;
use Illuminate\Console\Command;

class TriggerCommissionSettlements extends Command
{
    protected $signature = 'workflow:process-commission-settlements
                            {--dry-run : 仅显示要处理的结算}';

    protected $description = '检测需要解冻的佣金结算并启动解冻工作流';

    public function handle(WorkflowEngine $engine): int
    {
        $dryRun = $this->option('dry-run');

        $settlements = CommissionSettlement::where('status', 'pending')
            ->where('released_at', '<=', now())
            ->get();

        $this->info("发现 {$settlements->count()} 个待解冻的佣金结算");

        if ($dryRun) {
            foreach ($settlements as $s) {
                $this->line("  [{$s->id}] amount={$s->amount}, released_at={$s->released_at}");
            }
            return Command::SUCCESS;
        }

        $started = 0;
        foreach ($settlements as $settlement) {
            $existing = \App\Models\WorkflowInstance::where('workflowable_type', CommissionSettlement::class)
                ->where('workflowable_id', $settlement->id)
                ->where('workflow_name', 'commission_settlement')
                ->whereIn('status', ['running', 'compensating'])
                ->exists();

            if ($existing) {
                continue;
            }

            $engine->start(
                workflowName: 'commission_settlement',
                workflowable: $settlement,
                initialContext: [
                    'settlement_id' => $settlement->id,
                    'amount' => $settlement->amount,
                    'released_at' => $settlement->released_at?->toIso8601String(),
                    'triggered_by' => 'command:process-commission-settlements',
                ],
            );
            $started++;
        }

        $this->info("已启动 {$started} 个佣金结算工作流");

        return Command::SUCCESS;
    }
}
