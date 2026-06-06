<?php

namespace App\Jobs;

use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 自动过期单个 License
 *
 * 由 AutoExpireBulkJob 批量调度，或由 LicenseService::expire() 触发。
 * 每个 Job 只处理一个 License，便于失败重试和单独追踪。
 */
class ExpireLicenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 15;

    public int $maxExceptions = 2;

    public array $backoff = [10, 30, 120];

    /**
     * @param int $licenseId
     * @param string|null $reason
     */
    public function __construct(
        protected int $licenseId,
        protected ?string $reason = null,
    ) {}

    public function handle(LicenseService $licenseService): void
    {
        $license = License::find($this->licenseId);

        if (! $license) {
            Log::warning('ExpireLicenseJob: License 不存在', ['license_id' => $this->licenseId]);
            return;
        }

        // 跳过已过期或终态的 License
        if (in_array($license->status, ['expired', 'revoked', 'refunded', 'blacklisted'])) {
            return;
        }

        $licenseService->expire($license, $this->reason ?? 'auto_expire');

        Log::info('License 已自动过期', [
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'tenant_id' => $license->tenant_id,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ExpireLicenseJob 失败', [
            'license_id' => $this->licenseId,
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['license', 'expire', 'license:' . $this->licenseId];
    }
}
