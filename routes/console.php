<?php

use Illuminate\Support\Facades\Schedule;

// ─── 所有定时任务统一注册在此文件 ───
// Laravel 11 使用 routes/console.php 作为调度的标准方式
// 服务器 cron 需添加: * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

// ── License 自动过期 ──
// 每小时检查一次过期 License（及时清理）
Schedule::command('hwt:auto-expire-licenses')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

// ── 发送过期提醒 ──
// 每天早 9 点发送 7 天、3 天、1 天前的提醒
Schedule::command('hwt:send-expiry-reminders', ['--level' => '7_days'])
    ->dailyAt('09:00')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

Schedule::command('hwt:send-expiry-reminders', ['--level' => '3_days'])
    ->dailyAt('09:05')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

Schedule::command('hwt:send-expiry-reminders', ['--level' => '1_day'])
    ->dailyAt('09:10')
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-license.log'));

// ── 试用期检查 ──
// 每小时检查过期试用
Schedule::command('hwt:check-trials')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-trial.log'));

// ── 订阅计费 ──
// 每天凌晨 2 点处理到期续费
Schedule::command('billing:process-renewals')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// 每天凌晨 3 点重试失败续费
Schedule::command('billing:process-retries')
    ->dailyAt('03:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// 每天凌晨 4 点处理宽限期到期的订阅
Schedule::command('billing:process-grace-period')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-billing.log'));

// ── SSL 证书检查 ──
// 每天凌晨 5 点检查并自动续期
Schedule::command('ssl:check', ['--renew' => true])
    ->dailyAt('05:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-ssl.log'));

// ── 依赖安全扫描 ──
// 每周日凌晨 6 点扫描
Schedule::command('deps:scan')
    ->weeklyOn(0, '06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-deps.log'));

// ── 客户健康度评分 ──
Schedule::command('hwt:calculate-health-scores')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-health-score.log'));

// ── API 版本生命周期管理 ──
// 每天凌晨检查过期的废弃版本，自动进入 sunset/retire 流程
Schedule::command('hwt:process-expired-api-versions')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scheduler-api-versions.log'));
