<?php

namespace App\Console\Commands\License;

use App\Enums\LicenseStatus;
use App\Events\LicenseAboutToExpire;
use App\Models\License;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendExpiryReminders extends Command
{
    protected $signature = 'hwt:send-expiry-reminders
                            {--level=all : 提醒级别 (7_days|3_days|1_day|all)}';

    protected $description = '发送 License 即将过期提醒（7/3/1天前）';

    public function handle(): int
    {
        $levels = match ($this->option('level')) {
            '7_days' => [7],
            '3_days' => [3],
            '1_day' => [1],
            default => [7, 3, 1],
        };

        $totalSent = 0;

        foreach ($levels as $days) {
            // 查找 N 天后过期且状态为 active/suspended 的 License
            $targetDate = now()->addDays($days)->startOfDay();
            $nextDate = (clone $targetDate)->addDay();

            $licenses = License::whereIn('status', [
                    LicenseStatus::Active->value,
                    LicenseStatus::Suspended->value,
                ])
                ->where('expires_at', '>=', $targetDate)
                ->where('expires_at', '<', $nextDate)
                ->cursor();

            $levelLabel = match ($days) {
                7 => '7_days',
                3 => '3_days',
                1 => '1_day',
            };

            foreach ($licenses as $license) {
                try {
                    event(new LicenseAboutToExpire($license, $days, $levelLabel));
                    $totalSent++;
                } catch (\Throwable $e) {
                    Log::error('过期提醒发送失败', [
                        'license_id' => $license->id,
                        'license_key' => $license->license_key,
                        'days' => $days,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $this->info("已发送 {$totalSent} 条过期提醒");

        return Command::SUCCESS;
    }
}
