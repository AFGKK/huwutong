<?php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Workflows\WorkflowEngine;
use Illuminate\Console\Command;

class TriggerSubscriptionRenewals extends Command
{
    protected $signature = 'workflow:trigger-renewals
                            {--dry-run : 仅显示要处理的订阅}';

    protected $description = '检测需要续费的订阅并启动续费工作流';

    public function handle(WorkflowEngine $engine): int
    {
        $dryRun = $this->option('dry-run');

        $subscriptions = Subscription::where('auto_renew', true)
            ->where('status', 'active')
            ->whereNotNull('next_billing_at')
            ->where('next_billing_at', '<=', now())
            ->get();

        $this->info("发现 {$subscriptions->count()} 个需要续费的订阅");

        if ($dryRun) {
            foreach ($subscriptions as $sub) {
                $this->line("  [{$sub->id}] plan={$sub->plan}, next_billing={$sub->next_billing_at}");
            }
            return Command::SUCCESS;
        }

        $started = 0;
        foreach ($subscriptions as $subscription) {
            $existing = \App\Models\WorkflowInstance::where('workflowable_type', Subscription::class)
                ->where('workflowable_id', $subscription->id)
                ->where('workflow_name', 'renewal_pipeline')
                ->whereIn('status', ['running', 'compensating'])
                ->exists();

            if ($existing) {
                continue;
            }

            $engine->start(
                workflowName: 'renewal_pipeline',
                workflowable: $subscription,
                initialContext: [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->plan,
                    'amount' => $subscription->price,
                    'triggered_by' => 'command:trigger-renewals',
                ],
            );
            $started++;
        }

        $this->info("已启动 {$started} 个续费工作流");

        return Command::SUCCESS;
    }
}
