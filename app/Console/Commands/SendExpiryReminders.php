<?php

namespace App\Console\Commands;

use App\Jobs\SendLicenseExpiryReminderJob;
use App\Models\License;
use Illuminate\Console\Command;

class SendExpiryReminders extends Command
{
    protected $signature = 'hwt:send-expiry-reminders
        {--level=7_days : 提醒级别: 7_days, 3_days, 1_day}
        {--tenant= : 限定特定租户 ID}';

    protected $description = '发送 License 过期提醒（调度队列任务执行）';

    public function handle(): int
    {
        $level = $this->option('level');
        $tenantId = $this->option('tenant');
        $tenantId = $tenantId ? (int) $tenantId : null;

        if (! in_array($level, ['7_days', '3_days', '1_day'])) {
            $this->error("无效的提醒级别: {$level}，可选: 7_days, 3_days, 1_day");
            return Command::FAILURE;
        }

        $daysMap = ['7_days' => 7, '3_days' => 3, '1_day' => 1];
        $targetDays = $daysMap[$level];

        $query = License::where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', now()->addDays($targetDays)->toDateString());

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $licenses = $query->get();

        if ($licenses->isEmpty()) {
            $this->info("没有需要发送 {$level} 提醒的 License");
            return Command::SUCCESS;
        }

        $dispatched = 0;
        foreach ($licenses as $license) {
            SendLicenseExpiryReminderJob::dispatch($license->id, $level)
                ->onQueue('licenses');
            $dispatched++;
        }

        $this->info("已调度 {$dispatched} 个 {$level} 提醒任务");

        return Command::SUCCESS;
    }
}
