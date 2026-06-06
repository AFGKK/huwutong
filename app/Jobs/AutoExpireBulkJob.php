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
 * 批量查找并过期所有到期的 License
 *
 * 由定时任务 hwt:auto-expire-licenses 触发。
 * 不直接批量更新——为每个要过期的 License
 * 单独调度 ExpireLicenseJob，便于重试和追踪。
 */
class AutoExpireBulkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $maxExceptions = 2;

    public array $backoff = [30, 120];

    /**
     * @param int|null $tenantId 可选：限定特定租户
     */
    public function __construct(
        protected ?int $tenantId = null,
    ) {}

    public function handle(LicenseService $licenseService): void
    {
        $query = License::whereIn('status', ['active', 'suspended', 'frozen'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now());

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        $expiredLicenses = $query->get();

        if ($expiredLicenses->isEmpty()) {
            Log::info('AutoExpireBulkJob: 没有需要过期的 License');
            return;
        }

        $dispatched = 0;
        foreach ($expiredLicenses as $license) {
            ExpireLicenseJob::dispatch($license->id, 'auto_expire_bulk')
                ->onQueue('licenses');
            $dispatched++;
        }

        Log::info('AutoExpireBulkJob: 批量调度过期', [
            'tenant_id' => $this->tenantId ?? 'all',
            'count' => $dispatched,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoExpireBulkJob 失败', [
            'tenant_id' => $this->tenantId ?? 'all',
            'error' => $e->getMessage(),
        ]);
    }

    public function tags(): array
    {
        return ['license', 'expire_bulk', $this->tenantId ? 'tenant:' . $this->tenantId : 'all'];
    }
}
