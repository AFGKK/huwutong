<?php

namespace App\Console;

use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 定义调度任务
     */
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
    {
        // 每 6 小时检查域名健康状态
        $schedule->command('domain:check-health')
            ->everySixHours()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/domain-health.log'));

        // 定时发布广场帖子 - 每分钟检查
        $schedule->command('plaza:publish-scheduled')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/plaza-scheduler.log'));

        // 私信留存清理 - 每天凌晨执行
        $schedule->command('dm:prune-retention')
            ->dailyAt('03:30')
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/dm-retention.log'));
    }

    /**
     * 注册 Artisan 命令
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
