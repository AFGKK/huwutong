<?php

namespace App\Services;

use App\Models\UpdateGrayRelease;
use App\Models\UpdatePackage;
use App\Models\UpdateRollback;
use App\Models\UpdateVerificationLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * M2-15 更新签名验证 + 回滚机制 + 区域灰度发布
 *
 * 增强 M2-14 自动更新系统的安全性和分发策略。
 */
class UpdateSignerService
{
    private const CACHE_KEY_PUBLIC_KEY = 'update:public_key:v%s';
    private const CACHE_TTL = 86400;

    /**
     * 对更新包进行签名
     */
    public function signPackage(UpdatePackage $package, string $algorithm = 'ed25519'): UpdatePackage
    {
        $fileHash = $package->file_hash;
        $signature = '';
        $publicKeyVersion = config('update-signer.signing.public_key_version', 1);

        try {
            if ($algorithm === 'ed25519') {
                $privateKey = config('update-signer.signing.ed25519_private_key');
                if ($privateKey) {
                    $signature = base64_encode(
                        sodium_crypto_sign_detached($fileHash, base64_decode($privateKey))
                    );
                }
            } elseif ($algorithm === 'rsa-sha256') {
                $privateKey = config('update-signer.signing.rsa_private_key');
                if ($privateKey) {
                    openssl_sign($fileHash, $signature, base64_decode($privateKey), OPENSSL_ALGO_SHA256);
                    $signature = base64_encode($signature);
                }
            }
        } catch (\Throwable $e) {
            Log::error('UpdateSigner: signing failed', [
                'package_id' => $package->id,
                'algorithm' => $algorithm,
                'error' => $e->getMessage(),
            ]);
        }

        $package->update([
            'signature' => $signature,
            'sign_algorithm' => $algorithm,
            'public_key_version' => (string) $publicKeyVersion,
        ]);

        return $package->fresh();
    }

    /**
     * 验证更新包签名
     */
    public function verifySignature(
        UpdatePackage $package,
        string $fileHash,
        ?string $signature = null,
        ?string $sdkInstanceId = null,
    ): array {
        $algorithm = $package->sign_algorithm ?: config('update-signer.signing.algorithm', 'ed25519');
        $signature = $signature ?: $package->signature;
        $verified = false;
        $errorMessage = null;

        try {
            if ($algorithm === 'ed25519') {
                $publicKey = config('update-signer.signing.ed25519_public_key');
                if ($publicKey) {
                    $verified = sodium_crypto_sign_verify_detached(
                        base64_decode($signature),
                        $fileHash,
                        base64_decode($publicKey)
                    );
                } else {
                    $errorMessage = 'Ed25519 public key not configured';
                }
            } elseif ($algorithm === 'rsa-sha256') {
                $publicKey = config('update-signer.signing.rsa_public_key');
                if ($publicKey) {
                    $result = openssl_verify(
                        $fileHash,
                        base64_decode($signature),
                        base64_decode($publicKey),
                        OPENSSL_ALGO_SHA256
                    );
                    $verified = $result === 1;
                    if ($result === -1) {
                        $errorMessage = 'RSA verification error: ' . openssl_error_string();
                    }
                } else {
                    $errorMessage = 'RSA public key not configured';
                }
            } else {
                $errorMessage = "Unsupported algorithm: {$algorithm}";
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        // 记录验证日志
        UpdateVerificationLog::create([
            'update_package_id' => $package->id,
            'sdk_instance_id' => $sdkInstanceId,
            'algorithm' => $algorithm,
            'verified' => $verified,
            'file_hash' => $fileHash,
            'expected_hash' => $package->file_hash,
            'signature' => $signature,
            'error_message' => $errorMessage,
        ]);

        // 检查是否需要自动触发回滚
        if (!$verified && config('update-signer.signing.verify_required', true)) {
            $this->checkAutoRollback($package, 'auto_failure', "签名验证失败率过高: {$errorMessage}");
        }

        return [
            'verified' => $verified,
            'algorithm' => $algorithm,
            'error_message' => $errorMessage,
            'public_key_version' => $package->public_key_version,
        ];
    }

    /**
     * 获取公钥（SDK 客户端拉取）
     */
    public function getPublicKey(?int $version = null): array
    {
        $version = $version ?: config('update-signer.signing.public_key_version', 1);
        $cacheKey = sprintf(self::CACHE_KEY_PUBLIC_KEY, $version);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($version) {
            $algorithm = config('update-signer.signing.algorithm', 'ed25519');

            if ($algorithm === 'ed25519') {
                $key = config('update-signer.signing.ed25519_public_key');
            } else {
                $key = config('update-signer.signing.rsa_public_key');
            }

            return [
                'version' => $version,
                'algorithm' => $algorithm,
                'public_key' => $key,
                'expires_at' => now()->addDays(30)->toIso8601String(),
            ];
        });
    }

    /**
     * 执行回滚
     */
    public function rollback(
        UpdatePackage $package,
        string $triggerType = 'manual',
        ?string $reason = null,
        ?int $userId = null,
        bool $requireApproval = true,
    ): UpdateRollback {
        // 查找可回滚到的版本
        $targetVersion = $package->prev_version;
        if (!$targetVersion) {
            throw new \RuntimeException("包 {$package->version} 没有可回滚的前置版本");
        }

        $status = $requireApproval ? 'pending' : 'approved';

        $rollback = UpdateRollback::create([
            'update_package_id' => $package->id,
            'from_version' => $package->version,
            'to_version' => $targetVersion,
            'trigger_type' => $triggerType,
            'status' => $status,
            'reason' => $reason ?? "从 {$package->version} 回滚到 {$targetVersion}",
            'rollback_metrics' => $this->getCurrentMetrics($package),
            'affected_instances' => 0,
            'created_by' => $userId,
        ]);

        Log::info('UpdateRollback: created', [
            'rollback_id' => $rollback->id,
            'package_id' => $package->id,
            'from' => $package->version,
            'to' => $targetVersion,
            'trigger' => $triggerType,
            'status' => $status,
        ]);

        return $rollback;
    }

    /**
     * 审批回滚
     */
    public function approveRollback(UpdateRollback $rollback, int $userId): UpdateRollback
    {
        $rollback->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);

        return $rollback->fresh();
    }

    /**
     * 执行已审批的回滚
     */
    public function executeRollback(UpdateRollback $rollback): UpdateRollback
    {
        if ($rollback->status !== 'approved') {
            throw new \RuntimeException('回滚未审批，无法执行');
        }

        $package = $rollback->package;
        $targetVersion = $rollback->to_version;

        // 查找目标版本的包
        $targetPackage = UpdatePackage::where('product_id', $package->product_id)
            ->where('version', $targetVersion)
            ->where('status', 'published')
            ->first();

        if (!$targetPackage) {
            $rollback->update(['status' => 'failed', 'rollback_result' => ['error' => '目标版本包未找到']]);
            return $rollback->fresh();
        }

        // 执行回滚：将当前包标记为回滚，发布新包指向旧版本
        $rollbackPackage = UpdatePackage::create([
            'product_id' => $package->product_id,
            'version' => $targetVersion . '-rollback-' . time(),
            'prev_version' => $package->version,
            'type' => $package->type,
            'file_path' => $targetPackage->file_path,
            'file_name' => $targetPackage->file_name,
            'file_size' => $targetPackage->file_size,
            'file_hash' => $targetPackage->file_hash,
            'signature' => $targetPackage->signature,
            'sign_algorithm' => $targetPackage->sign_algorithm,
            'checksums' => $targetPackage->checksums,
            'release_notes' => [
                'zh_CN' => "回滚到版本 {$targetVersion}",
                'en' => "Rollback to version {$targetVersion}",
            ],
            'metadata' => array_merge($targetPackage->metadata ?? [], [
                'rollback_from' => $package->version,
                'rollback_reason' => $rollback->reason,
            ]),
            'status' => 'published',
            'is_rollback' => true,
            'rollback_info' => [
                'original_package_id' => $package->id,
                'rollback_package_id' => $targetPackage->id,
                'rollback_version' => $targetVersion,
                'trigger_type' => $rollback->trigger_type,
            ],
            'published_at' => now(),
            'created_by' => $rollback->created_by,
        ]);

        $rollback->update([
            'status' => 'executed',
            'executed_at' => now(),
            'completed_at' => now(),
            'rollback_result' => [
                'rollback_package_id' => $rollbackPackage->id,
                'target_package_id' => $targetPackage->id,
                'version' => $targetVersion,
                'success' => true,
            ],
        ]);

        Log::info('UpdateRollback: executed', [
            'rollback_id' => $rollback->id,
            'from' => $package->version,
            'to' => $targetVersion,
        ]);

        return $rollback->fresh();
    }

    /**
     * 创建灰度发布规则
     */
    public function createGrayRelease(UpdatePackage $package, array $config): UpdateGrayRelease
    {
        $strategy = $config['strategy'] ?? 'percentage';
        $stage = $config['current_stage'] ?? 'canary';
        $percentage = $config['current_percentage'] ?? config("update-signer.gray_release.stages.{$stage}.percentage", 5);

        $release = UpdateGrayRelease::create([
            'update_package_id' => $package->id,
            'strategy' => $strategy,
            'current_stage' => $stage,
            'current_percentage' => $percentage,
            'target_regions' => $config['target_regions'] ?? null,
            'excluded_regions' => $config['excluded_regions'] ?? null,
            'whitelist_tenants' => $config['whitelist_tenants'] ?? null,
            'blacklist_tenants' => $config['blacklist_tenants'] ?? null,
            'tenant_tags' => $config['tenant_tags'] ?? null,
            'status' => 'pending',
            'stage_started_at' => null,
            'stage_ends_at' => null,
            'config' => $config['extras'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return $release;
    }

    /**
     * 启动灰度发布
     */
    public function startGrayRelease(UpdateGrayRelease $release): UpdateGrayRelease
    {
        $stageConfig = config("update-signer.gray_release.stages.{$release->current_stage}", []);
        $durationHours = $stageConfig['duration_hours'] ?? 24;

        $release->update([
            'status' => 'running',
            'stage_started_at' => now(),
            'stage_ends_at' => $durationHours > 0 ? now()->addHours($durationHours) : null,
        ]);

        Log::info('UpdateGrayRelease: started', [
            'release_id' => $release->id,
            'package_id' => $release->update_package_id,
            'stage' => $release->current_stage,
            'percentage' => $release->current_percentage,
        ]);

        return $release->fresh();
    }

    /**
     * 推进到下一灰度阶段
     */
    public function advanceGrayRelease(UpdateGrayRelease $release): ?UpdateGrayRelease
    {
        if (!$release->isEligibleForNextStage()) {
            return null;
        }

        $stages = array_keys(config('update-signer.gray_release.stages', []));
        $currentIndex = array_search($release->current_stage, $stages);

        if ($currentIndex === false || $currentIndex >= count($stages) - 1) {
            // 已经是全量发布
            $release->update([
                'status' => 'completed',
                'current_percentage' => 100,
            ]);
            return $release->fresh();
        }

        $nextStage = $stages[$currentIndex + 1];
        $nextConfig = config("update-signer.gray_release.stages.{$nextStage}", []);
        $nextPercentage = $nextConfig['percentage'] ?? 100;
        $nextDuration = $nextConfig['duration_hours'] ?? 0;

        $release->update([
            'current_stage' => $nextStage,
            'current_percentage' => $nextPercentage,
            'stage_started_at' => now(),
            'stage_ends_at' => $nextDuration > 0 ? now()->addHours($nextDuration) : null,
            'stage_metrics' => $this->getCurrentMetrics($release->package),
        ]);

        Log::info('UpdateGrayRelease: advanced', [
            'release_id' => $release->id,
            'stage' => $nextStage,
            'percentage' => $nextPercentage,
        ]);

        return $release->fresh();
    }

    /**
     * 暂停灰度发布
     */
    public function pauseGrayRelease(UpdateGrayRelease $release): UpdateGrayRelease
    {
        $release->update(['status' => 'paused']);
        return $release->fresh();
    }

    /**
     * 检查 SDK/租户是否有权限获取此更新
     */
    public function isEligibleForUpdate(UpdatePackage $package, ?string $region = null, ?string $tenantId = null): array
    {
        $grayRelease = UpdateGrayRelease::where('update_package_id', $package->id)
            ->where('status', 'running')
            ->first();

        if (!$grayRelease) {
            return ['eligible' => true, 'reason' => 'no_gray_release'];
        }

        // 检查黑名单
        if ($tenantId && $grayRelease->blacklist_tenants) {
            if (in_array($tenantId, $grayRelease->blacklist_tenants)) {
                return ['eligible' => false, 'reason' => 'tenant_blacklisted'];
            }
        }

        // 按策略检查
        switch ($grayRelease->strategy) {
            case 'whitelist':
                if ($tenantId && $grayRelease->whitelist_tenants) {
                    $eligible = in_array($tenantId, $grayRelease->whitelist_tenants);
                    return ['eligible' => $eligible, 'reason' => $eligible ? 'whitelisted' : 'not_whitelisted'];
                }
                return ['eligible' => false, 'reason' => 'whitelist_empty'];

            case 'region':
                if ($region && $grayRelease->target_regions) {
                    $eligible = in_array($region, $grayRelease->target_regions);
                    if ($eligible && $grayRelease->excluded_regions) {
                        $eligible = !in_array($region, $grayRelease->excluded_regions);
                    }
                    return ['eligible' => $eligible, 'reason' => $eligible ? 'region_matched' : 'region_excluded'];
                }
                return ['eligible' => true, 'reason' => 'no_region_filter'];

            case 'percentage':
                // 按 tenant_id 哈希取模决定是否命中灰度
                if ($tenantId) {
                    $hash = crc32($tenantId . $package->id);
                    $mod = abs($hash) % 100;
                    $eligible = $mod < $grayRelease->current_percentage;
                    return ['eligible' => $eligible, 'reason' => $eligible ? 'percentage_hit' : 'percentage_miss'];
                }
                return ['eligible' => true, 'reason' => 'no_tenant_id'];

            default:
                return ['eligible' => true, 'reason' => 'default_allow'];
        }
    }

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $totalVerifications = UpdateVerificationLog::count();
        $failedVerifications = UpdateVerificationLog::failed()->count();
        $passRate = $totalVerifications > 0
            ? round((($totalVerifications - $failedVerifications) / $totalVerifications) * 100, 1)
            : 100;

        $pendingRollbacks = UpdateRollback::pending()->count();
        $totalRollbacks = UpdateRollback::count();
        $executedRollbacks = UpdateRollback::executed()->count();

        $activeGrayReleases = UpdateGrayRelease::running()->count();
        $totalGrayReleases = UpdateGrayRelease::count();

        $recentVerifications = UpdateVerificationLog::orderByDesc('id')->limit(5)->get();

        return [
            'total_verifications' => $totalVerifications,
            'failed_verifications' => $failedVerifications,
            'pass_rate' => $passRate,
            'pending_rollbacks' => $pendingRollbacks,
            'total_rollbacks' => $totalRollbacks,
            'executed_rollbacks' => $executedRollbacks,
            'active_gray_releases' => $activeGrayReleases,
            'total_gray_releases' => $totalGrayReleases,
            'recent_verifications' => $recentVerifications,
        ];
    }

    /**
     * 获取回滚列表
     */
    public function getRollbacks(array $filters = [], int $perPage = 20): array
    {
        $query = UpdateRollback::query()->with(['package', 'approver:id,name', 'creator:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 获取灰度发布列表
     */
    public function getGrayReleases(array $filters = [], int $perPage = 20): array
    {
        $query = UpdateGrayRelease::query()->with(['package', 'creator:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['strategy'])) {
            $query->where('strategy', $filters['strategy']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 获取验证日志列表
     */
    public function getVerificationLogs(array $filters = [], int $perPage = 20): array
    {
        $query = UpdateVerificationLog::query()->with('package');

        if (!empty($filters['verified'])) {
            $query->where('verified', $filters['verified'] === 'true' || $filters['verified'] === true);
        }
        if (!empty($filters['algorithm'])) {
            $query->where('algorithm', $filters['algorithm']);
        }
        if (!empty($filters['update_package_id'])) {
            $query->where('update_package_id', $filters['update_package_id']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 自动检查并触发回滚
     */
    private function checkAutoRollback(UpdatePackage $package, string $triggerType, string $reason): void
    {
        if (!config('update-signer.rollback.auto_rollback', true)) {
            return;
        }

        // 统计最近N小时内验证失败率
        $windowHours = config('update-signer.rollback.window_hours', 48);
        $recentLogs = UpdateVerificationLog::where('update_package_id', $package->id)
            ->where('created_at', '>=', now()->subHours($windowHours))
            ->get();

        $total = $recentLogs->count();
        if ($total < 10) return; // 样本太少，不触发

        $failed = $recentLogs->where('verified', false)->count();
        $failureRate = $failed / $total;

        $threshold = config('update-signer.rollback.triggers.activation_failure_rate', 0.10);

        if ($failureRate >= $threshold) {
            $this->rollback(
                $package,
                $triggerType,
                "自动回滚: 验证失败率 {$failureRate}% 超过阈值 {$threshold}%",
                null,
                config('update-signer.rollback.require_approval', true)
            );
        }
    }

    /**
     * 获取当前指标快照
     */
    private function getCurrentMetrics(UpdatePackage $package): array
    {
        $recentLogs = UpdateVerificationLog::where('update_package_id', $package->id)
            ->where('created_at', '>=', now()->subDay())
            ->get();

        $total = $recentLogs->count();
        $failed = $recentLogs->where('verified', false)->count();

        return [
            'total_verifications' => $total,
            'failed_verifications' => $failed,
            'failure_rate' => $total > 0 ? round($failed / $total, 4) : 0,
            'snapshot_time' => now()->toIso8601String(),
        ];
    }
}
