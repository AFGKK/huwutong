<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\Subscription;
use App\Workflows\WorkflowEngine;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DetectExpiringLicenses extends Command
{
    protected $signature = 'workflow:detect-expiring
                            {--days=7 : 过期前多少天触发通知工作流}
                            {--dry-run : 仅显示要处理的 License}';

    protected $description = '检测即将过期的 License 并启动生命周期工作流';

    public function handle(WorkflowEngine $engine): int
    {
        $days = (int) $this->option('days');
        $dryRun = $this->option('dry-run');
        $threshold = now()->addDays($days);

        $licenses = License::whereIn('status', ['active', 'suspended', 'frozen'])
            ->where('expires_at', '<=', $threshold)
            ->where('expires_at', '>', now())
            ->get();

        $this->info("发现 {$licenses->count()} 个即将过期的 License");

        if ($dryRun) {
            foreach ($licenses as $license) {
                $this->line("  [{$license->id}] {$license->license_key} -> expires at {$license->expires_at}");
            }
            return Command::SUCCESS;
        }

        $started = 0;
        foreach ($licenses as $license) {
            // 检查是否已有进行中的工作流
            $existing = \App\Models\WorkflowInstance::where('workflowable_type', License::class)
                ->where('workflowable_id', $license->id)
                ->where('workflow_name', 'license_lifecycle')
                ->whereIn('status', ['running', 'compensating'])
                ->exists();

            if ($existing) {
                continue;
            }

            $engine->start(
                workflowName: 'license_lifecycle',
                workflowable: $license,
                initialContext: [
                    'license_id' => $license->id,
                    'expires_at' => $license->expires_at?->toIso8601String(),
                    'triggered_by' => 'command:detect-expiring',
                ],
            );
            $started++;
        }

        $this->info("已启动 {$started} 个 License 生命周期工作流");

        return Command::SUCCESS;
    }
}
