<?php

namespace App\Services;

use App\Models\License;
use App\Models\OfflineActivation;
use App\Models\OfflineCertificate;
use App\Models\OfflineCrlEntry;
use App\Models\OfflineVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CRL（证书吊销列表）服务 (M1.3-03)
 *
 * 核心职责：
 * 1. CRL 管理 — 吊销/恢复 License，查询吊销状态
 * 2. CRL 分发 — 客户端拉取增量/全量吊销列表
 * 3. 网络恢复自动补全验证 — 离线期间激活的 License，网络恢复后自动重新校验 CRL
 */
class CrlService
{
    const CRL_CHECK_CACHE_TTL = 60;       // CRL 检查缓存 TTL（秒）
    const CRL_FULL_CACHE_TTL = 300;       // CRL 全量缓存 TTL（秒）
    const CRL_MAX_ENTRIES = 10000;        // CRL 单次返回最大条目数

    // ────────────────────────────────── CRUD ──────────────────────────────────

    /**
     * 吊销 License
     */
    public function revoke(string $licenseKey, string $reason = '管理员吊销'): void
    {
        $certificate = OfflineCertificate::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();

        if (! $certificate) {
            throw new \RuntimeException(__("app.crl.no_active_cert_cannot_revoke_license"));
        }

        OfflineCrlEntry::firstOrCreate([
            'offline_certificate_id' => $certificate->id,
            'license_key' => $licenseKey,
        ], [
            'reason' => $reason,
            'revoked_at' => now(),
        ]);

        $this->clearCache($licenseKey);

        Log::channel('crl')->info('License 已加入吊销列表', [
            'license_key' => $licenseKey,
            'reason' => $reason,
        ]);
    }

    /**
     * 从 CRL 移除（恢复）
     */
    public function restore(string $licenseKey): void
    {
        OfflineCrlEntry::where('license_key', $licenseKey)->delete();
        $this->clearCache($licenseKey);

        Log::channel('crl')->info('License 已移出吊销列表', ['license_key' => $licenseKey]);
    }

    /**
     * 批量吊销
     */
    public function batchRevoke(array $licenseKeys, string $reason = '批量吊销'): array
    {
        $results = ['revoked' => 0, 'failed' => 0, 'errors' => []];

        foreach ($licenseKeys as $key) {
            try {
                $this->revoke($key, $reason);
                $results['revoked']++;
            } catch (\Throwable $e) {
                $results['failed']++;
                $results['errors'][] = ['key' => $key, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    // ────────────────────────────────── 检查 ──────────────────────────────────

    /**
     * 检查 License 是否在吊销列表中
     */
    public function isRevoked(string $licenseKey): bool
    {
        $cacheKey = 'crl_check:' . $licenseKey;
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            return $cached['valid'] === false;
        }

        $entry = OfflineCrlEntry::where('license_key', $licenseKey)->first();

        $result = [
            'valid' => $entry === null,
            'reason' => $entry?->reason,
            'revoked_at' => $entry?->revoked_at?->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, self::CRL_CHECK_CACHE_TTL);

        return $result['valid'] === false;
    }

    /**
     * 获取吊销详情
     */
    public function getRevocationInfo(string $licenseKey): ?array
    {
        $entry = OfflineCrlEntry::where('license_key', $licenseKey)->first();
        if (! $entry) {
            return null;
        }

        return [
            'license_key' => $entry->license_key,
            'reason' => $entry->reason,
            'revoked_at' => $entry->revoked_at->toIso8601String(),
            'key_version' => $entry->certificate?->key_version,
        ];
    }

    // ────────────────────────────────── 分发 ──────────────────────────────────

    /**
     * 获取 CRL（供客户端拉取）
     *
     * @param int|null $since 上次拉取时间戳，null 返回全量
     */
    public function getCrl(?int $since = null): array
    {
        $query = OfflineCrlEntry::with('certificate');

        if ($since) {
            $query->where('created_at', '>', Carbon::createFromTimestamp($since));
        }

        $entries = $query->orderBy('created_at', 'desc')
            ->limit(self::CRL_MAX_ENTRIES)
            ->get();

        return [
            'version' => time(),
            'count' => $entries->count(),
            'since' => $since,
            'entries' => $entries->map(fn($e) => [
                'license_key' => $e->license_key,
                'reason' => $e->reason,
                'revoked_at' => $e->revoked_at->toIso8601String(),
                'key_version' => $e->certificate?->key_version,
            ]),
        ];
    }

    /**
     * CRL 统计
     */
    public function getStats(): array
    {
        $totalRevoked = OfflineCrlEntry::count();
        $recentRevoked = OfflineCrlEntry::where('created_at', '>=', now()->subDays(7))->count();
        $pendingAutoVerify = OfflineActivation::whereNull('crl_verified_at')
            ->where('result', 'valid')
            ->count();

        return [
            'total_revoked' => $totalRevoked,
            'recent_7d_revoked' => $recentRevoked,
            'pending_auto_verify' => $pendingAutoVerify,
        ];
    }

    // ─────────────────────────── 网络恢复自动补全验证 ───────────────────────────

    /**
     * 网络恢复自动补全验证
     *
     * 离线期间验证通过的 License，在网络恢复后自动重新检查 CRL，
     * 确保离线期间被吊销的 License 能被及时发现。
     *
     * @param int $batchSize 每批处理数
     * @return array{processed: int, revoked_found: int, revoked_keys: array}
     */
    public function autoCompleteVerification(int $batchSize = 100): array
    {
        $processed = 0;
        $revokedFound = 0;
        $revokedKeys = [];

        // 获取尚未进行 CRL 补全验证的离线激活/验证记录
        $pendingVerifications = OfflineActivation::whereNull('crl_verified_at')
            ->where('result', 'valid')
            ->limit($batchSize)
            ->get();

        foreach ($pendingVerifications as $activation) {
            $processed++;

            if ($this->isRevoked($activation->license_key)) {
                $activation->update([
                    'crl_verified_at' => now(),
                    'crl_result' => 'revoked',
                ]);
                $revokedFound++;
                $revokedKeys[] = $activation->license_key;
            } else {
                $activation->update([
                    'crl_verified_at' => now(),
                    'crl_result' => 'clean',
                ]);
            }
        }

        // 同时也检查 offline_verifications 表
        $pendingVerifications2 = OfflineVerification::whereNull('crl_verified_at')
            ->where('result', 'valid')
            ->limit($batchSize)
            ->get();

        foreach ($pendingVerifications2 as $verification) {
            $processed++;

            if ($this->isRevoked($verification->license_key)) {
                $verification->update([
                    'crl_verified_at' => now(),
                    'crl_result' => 'revoked',
                ]);
                $revokedFound++;
                $revokedKeys[] = $verification->license_key;
            } else {
                $verification->update([
                    'crl_verified_at' => now(),
                    'crl_result' => 'clean',
                ]);
            }
        }

        if ($revokedFound > 0) {
            Log::channel('crl')->warning('网络恢复自动补全验证：发现被吊销 License', [
                'processed' => $processed,
                'revoked_found' => $revokedFound,
                'revoked_keys' => $revokedKeys,
            ]);
        }

        return [
            'processed' => $processed,
            'revoked_found' => $revokedFound,
            'revoked_keys' => $revokedKeys,
        ];
    }

    /**
     * 获取待补全验证的数量
     */
    public function getPendingAutoVerifyCount(): int
    {
        return OfflineActivation::whereNull('crl_verified_at')
            ->where('result', 'valid')
            ->count()
            + OfflineVerification::whereNull('crl_verified_at')
            ->where('result', 'valid')
            ->count();
    }

    // ────────────────────────────────── 工具 ──────────────────────────────────

    protected function clearCache(string $licenseKey): void
    {
        Cache::forget('crl_check:' . $licenseKey);
        Cache::forget('crl_full');
    }
}
