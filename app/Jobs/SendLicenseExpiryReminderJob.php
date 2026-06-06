<?php

namespace App\Jobs;

use App\Models\License;
use App\Notifications\LicenseExpiryNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * 发送 License 过期提醒
 *
 * 由定时任务 hwt:send-expiry-reminders 触发。
 * 支持不同提前天数（7 天 / 3 天 / 1 天）。
 * 未来可将通知发送逻辑委派给 App\Notifications 类。
 */
class SendLicenseExpiryReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;

    public int $maxExceptions = 2;

    public array $backoff = [10, 60];

    /**
     * @param int $licenseId
     * @param string $level 提醒级别: 7_days, 3_days, 1_day
     */
    public function __construct(
        protected int $licenseId,
        protected string $level,
    ) {}

    public function handle(): void
    {
        $license = License::with('tenant')->find($this->licenseId);

        if (! $license) {
            Log::warning('SendLicenseExpiryReminderJob: License 不存在', [
                'license_id' => $this->licenseId,
            ]);
            return;
        }

        if ($license->status !== 'active') {
            return;
        }

        $daysLeft = $license->expires_at ? now()->diffInDays($license->expires_at, false) : null;

        if ($daysLeft === null || $daysLeft < 0) {
            return;
        }

        $levelDays = match ($this->level) {
            '7_days' => 7,
            '3_days' => 3,
            '1_day' => 1,
            default => null,
        };

        if ($levelDays === null) {
            Log::warning('SendLicenseExpiryReminderJob: 未知的提醒级别', [
                'level' => $this->level,
            ]);
            return;
        }

        // 确保实际剩余天数与提醒级别匹配，避免重复发送
        if ((int) $daysLeft !== $levelDays) {
            return;
        }

        Log::info('License 过期提醒', [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'tenant_id' => $license->tenant_id,
            'expires_at' => $license->expires_at?->toDateString(),
            'days_left' => $daysLeft,
            'level' => $this->level,
        ]);

        // 发送通知
        $notification = new LicenseExpiryNotification($license, $this->level);
        $tenant = $license->tenant;

        if ($tenant && $tenant->owner) {
            $tenant->owner->notify($notification);
        }

        // 如果有管理员通知设置，也发送
        if ($license->assigned_user_id) {
            $assignedUser = $license->assignedUser;
            if ($assignedUser) {
                $assignedUser->notify($notification);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendLicenseExpiryReminderJob 失败', [
            'license_id' => $this->licenseId,
            'level' => $this->level,
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['license', 'reminder', 'level:' . $this->level, 'license:' . $this->licenseId];
    }
}
