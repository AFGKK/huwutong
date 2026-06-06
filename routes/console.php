<?php

use Illuminate\Support\Facades\Schedule;

// Trial 过期检查（每日 02:00）
Schedule::command('hwt:check-trials')->dailyAt('02:00');

// License 过期提醒（每日 08:00 发送 7/3/1 天前提醒）
Schedule::command('hwt:send-expiry-reminders')->dailyAt('08:00');

// License 自动过期（每日 00:30）
Schedule::command('hwt:auto-expire-licenses')->dailyAt('00:30');

// 订阅自动续费处理（每日 03:00）
Schedule::command('billing:process-renewals')->dailyAt('03:00');

// 订阅宽限期处理（每日 04:00）
Schedule::command('billing:process-grace-period')->dailyAt('04:00');

// 续费失败重试（每日 10:00 和 14:00）
Schedule::command('billing:process-retries')->twiceDaily(10, 14);
