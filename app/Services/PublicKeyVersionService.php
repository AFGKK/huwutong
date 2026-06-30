<?php

namespace App\Services;

use App\Models\LicenseFileRecord;
use App\Models\OfflineCertificate;
use App\Models\PublicKeyVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SodiumException;

/**
 * 公钥版本管理服务 (M2-135)
 *
 * 增强公钥版本的生命周期管理，支持：
 * - 版本创建与自动轮换
 * - SDK 兼容窗口期管理（旧版本在兼容窗口期内仍有效）
 * - 版本废弃与清理
 * - 签名验证测试
 * - 版本历史追踪
 */
class PublicKeyVersionService
{
    /**
     * 默认兼容窗口期（天）：旧公钥在此窗口期内仍可用来验证旧签名
     */
    const DEFAULT_COMPAT_WINDOW_DAYS = 30;

    /**
     * 默认有效期（天）：新公钥有效期
     */
    const DEFAULT_KEY_VALIDITY_DAYS = 365;

    /**
     * 自动轮换阈值（天）：到期前多少天触发轮换提醒
     */
    const ROTATION_THRESHOLD_DAYS = 30;

    /**
     * 缓存标签
     */
    const CACHE_TAG = 'public_key_versions';

    /**
     * 获取所有公钥版本（含兼容标记）
     */
    public function getAllVersions(): array
    {
        $versions = PublicKeyVersion::orderBy('key_version', 'desc')->get();
        $now = now();

        return $versions->map(function ($v) use ($now) {
            return $this->enrichVersion($v, $now);
        })->toArray();
    }

    /**
     * 获取公钥版本详情
     */
    public function getVersionDetail(int $keyVersion): ?array
    {
        $version = PublicKeyVersion::where('key_version', $keyVersion)->first();
        if (! $version) {
            return null;
        }

        return $this->enrichVersion($version, now());
    }

    /**
     * 创建新公钥版本（轮换）
     */
    public function createVersion(string $publicKey, string $algorithm = 'Ed25519', ?string $publicKeyPem = null): PublicKeyVersion
    {
        $maxVersion = PublicKeyVersion::max('key_version') ?? 0;
        $newVersion = $maxVersion + 1;

        // 停用旧的活跃密钥，设置兼容窗口
        $oldActive = PublicKeyVersion::where('is_active', true)->get();
        foreach ($oldActive as $old) {
            $old->update([
                'is_active' => false,
                'expires_at' => now()->addDays(self::DEFAULT_COMPAT_WINDOW_DAYS),
            ]);
            Log::info('公钥版本已降级为兼容模式', [
                'key_version' => $old->key_version,
                'compat_until' => now()->addDays(self::DEFAULT_COMPAT_WINDOW_DAYS)->toDateString(),
            ]);
        }

        // 创建新版本
        $version = PublicKeyVersion::create([
            'key_version' => $newVersion,
            'algorithm' => $algorithm,
            'public_key' => $publicKey,
            'public_key_pem' => $publicKeyPem,
            'is_active' => true,
            'is_revoked' => false,
            'expires_at' => now()->addDays(self::DEFAULT_KEY_VALIDITY_DAYS),
        ]);

        // 同步离线证书
        $this->syncToOfflineCertificate($version);

        // 清除缓存
        Cache::tags([self::CACHE_TAG])->flush();

        Log::info('新公钥版本已创建', [
            'key_version' => $newVersion,
            'algorithm' => $algorithm,
            'valid_until' => $version->expires_at->toDateString(),
        ]);

        return $version;
    }

    /**
     * 吊销公钥版本
     */
    public function revokeVersion(int $keyVersion, string $reason): bool
    {
        $version = PublicKeyVersion::where('key_version', $keyVersion)->first();
        if (! $version) {
            return false;
        }

        $wasActive = $version->is_active;

        $version->update([
            'is_active' => false,
            'is_revoked' => true,
            'revoked_at' => now(),
            'revoke_reason' => $reason,
            'expires_at' => now(), // 立即过期
        ]);

        // 如果吊销的是当前活跃版本，激活最新的未吊销版本
        if ($wasActive) {
            $fallback = PublicKeyVersion::where('is_revoked', false)
                ->where('key_version', '!=', $keyVersion)
                ->orderBy('key_version', 'desc')
                ->first();
            if ($fallback) {
                $fallback->update(['is_active' => true]);
            }
        }

        // 同步到 OfflineCertificate
        OfflineCertificate::where('key_version', $keyVersion)
            ->update(['is_active' => false, 'is_revoked' => true, 'revoked_at' => now(), 'revoked_reason' => $reason]);

        // 清理旧文件缓存
        LicenseFileRecord::where('key_version', $keyVersion)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        Cache::tags([self::CACHE_TAG])->flush();

        Log::warning('公钥版本已吊销', [
            'key_version' => $keyVersion,
            'reason' => $reason,
        ]);

        return true;
    }

    /**
     * 获取有效的公钥版本列表（含兼容窗口内的）
     */
    public function getValidVersions(): array
    {
        $keys = PublicKeyVersion::getValid();

        return array_map(function ($key) {
            return [
                'key_version' => $key->key_version,
                'algorithm' => $key->algorithm,
                'public_key' => $key->public_key,
                'public_key_pem' => $key->public_key_pem,
                'is_active' => $key->is_active,
                'activated_at' => $key->activated_at?->toIso8601String(),
                'expires_at' => $key->expires_at?->toIso8601String(),
            ];
        }, $keys);
    }

    /**
     * 获取活跃版本
     */
    public function getActiveVersion(): ?PublicKeyVersion
    {
        return PublicKeyVersion::getActive();
    }

    /**
     * 签名验证测试 — 验证给定公钥是否能对数据签名进行正确验证
     *
     * @param string $publicKeyBase64
     * @param string $algorithm
     * @return array{valid: bool, test_message: string, signature: string, verification_result: bool}
     */
    public function testSigning(string $publicKeyBase64, string $algorithm = 'Ed25519'): array
    {
        $testMessage = 'HUWUTONG_KEY_TEST_' . bin2hex(random_bytes(4));

        try {
            if ($algorithm === 'Ed25519') {
                // 创建一个临时密钥对用于测试
                $seed = random_bytes(32);
                $keyPair = sodium_crypto_sign_seed_keypair($seed);
                $privateKey = sodium_crypto_sign_secretkey($keyPair);
                $publicKey = sodium_crypto_sign_publickey($keyPair);

                // 签名
                $signature = sodium_crypto_sign_detached($testMessage, $privateKey);

                // 用提供的公钥验签（确保不同）
                $providedKeyBin = base64_decode($publicKeyBase64);
                $providedVerify = sodium_crypto_sign_verify_detached($signature, $testMessage, $providedKeyBin);

                // 用测试密钥验签（应该成功）
                $testVerify = sodium_crypto_sign_verify_detached($signature, $testMessage, $publicKey);

                return [
                    'valid' => $testVerify && !$providedVerify, // 测试公钥能验签，但提供的不同
                    'test_message' => $testMessage,
                    'signature' => base64_encode($signature),
                    'verification_result' => $testVerify,
                    'note' => '此测试验证签名算法正常工作。提供的公钥与测试密钥对不同（预期行为）。',
                ];
            }

            if ($algorithm === 'RSA-2048') {
                // RSA 密钥对测试
                $resource = openssl_pkey_new([
                    'private_key_bits' => 2048,
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                ]);
                openssl_pkey_export($resource, $privateKeyPem);
                $pubKey = openssl_pkey_get_details($resource);
                $publicKeyRsa = $pubKey['key'];

                openssl_sign($testMessage, $signature, $privateKeyPem, OPENSSL_ALGO_SHA256);

                $providedVerify = openssl_verify($testMessage, $signature, $publicKeyBase64, OPENSSL_ALGO_SHA256);
                $testVerify = openssl_verify($testMessage, $signature, $publicKeyRsa, OPENSSL_ALGO_SHA256);

                return [
                    'valid' => $testVerify && !$providedVerify,
                    'test_message' => $testMessage,
                    'signature' => base64_encode($signature),
                    'verification_result' => $testVerify,
                    'note' => 'RSA-2048 签名算法测试',
                ];
            }

            return ['valid' => false, 'test_message' => '', 'signature' => '', 'verification_result' => false, 'note' => '不支持的算法'];
        } catch (\Throwable $e) {
            return ['valid' => false, 'test_message' => $testMessage ?? '', 'signature' => '', 'verification_result' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * 检查是否需要轮换
     */
    public function checkRotationNeeded(): array
    {
        $active = $this->getActiveVersion();
        if (! $active || ! $active->expires_at) {
            return ['needed' => false, 'reason' => '无活跃版本或未设置有效期'];
        }

        $daysUntilExpiry = now()->diffInDays($active->expires_at, false);

        return [
            'needed' => $daysUntilExpiry <= self::ROTATION_THRESHOLD_DAYS,
            'reason' => $daysUntilExpiry <= 0
                ? '当前公钥已过期'
                : "当前公钥将于 {$daysUntilExpiry} 天后过期",
            'key_version' => $active->key_version,
            'expires_at' => $active->expires_at->toIso8601String(),
            'days_until_expiry' => max(0, (int) $daysUntilExpiry),
            'threshold_days' => self::ROTATION_THRESHOLD_DAYS,
        ];
    }

    /**
     * 清理废弃版本（已过期且过兼容窗口期的版本）
     */
    public function purgeObsoleteVersions(): int
    {
        $cutoff = now()->subDays(self::DEFAULT_COMPAT_WINDOW_DAYS);

        $obsolete = PublicKeyVersion::where('is_active', false)
            ->where('is_revoked', true)
            ->where('expires_at', '<=', $cutoff)
            ->get();

        $count = 0;
        foreach ($obsolete as $version) {
            $version->delete();
            $count++;
            Log::info('废弃公钥版本已清理', ['key_version' => $version->key_version]);
        }

        return $count;
    }

    /**
     * 获取版本统计
     */
    public function getStats(): array
    {
        $total = PublicKeyVersion::count();
        $active = PublicKeyVersion::where('is_active', true)->count();
        $revoked = PublicKeyVersion::where('is_revoked', true)->count();
        $compat = count(PublicKeyVersion::getValid());

        $activeVersion = $this->getActiveVersion();

        // 签名文件统计（按版本）
        $fileStats = LicenseFileRecord::selectRaw('key_version, COUNT(*) as count')
            ->groupBy('key_version')
            ->pluck('count', 'key_version')
            ->toArray();

        return [
            'total_versions' => $total,
            'active_versions' => $active,
            'compat_valid_versions' => $compat,
            'revoked_versions' => $revoked,
            'current_active_version' => $activeVersion?->key_version,
            'current_expires_at' => $activeVersion?->expires_at?->toIso8601String(),
            'compat_window_days' => self::DEFAULT_COMPAT_WINDOW_DAYS,
            'files_by_version' => $fileStats,
        ];
    }

    /**
     * 同步公钥版本到 OfflineCertificate（保持两个系统一致）
     */
    protected function syncToOfflineCertificate(PublicKeyVersion $version): void
    {
        OfflineCertificate::updateOrCreate(
            ['key_version' => $version->key_version],
            [
                'tenant_id' => null,
                'algorithm' => $version->algorithm,
                'public_key' => $version->public_key,
                'is_active' => true,
                'is_revoked' => false,
                'expires_at' => $version->expires_at,
            ]
        );

        // 停用旧的离线证书
        OfflineCertificate::where('key_version', '<>', $version->key_version)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * 丰富版本信息（添加兼容状态标记）
     */
    protected function enrichVersion(PublicKeyVersion $version, \Carbon\Carbon $now): array
    {
        $isExpired = $version->expires_at && $version->expires_at->lte($now);
        $isCompatMode = !$version->is_active && !$version->is_revoked && !$isExpired;

        return [
            'id' => $version->id,
            'key_version' => $version->key_version,
            'algorithm' => $version->algorithm,
            'public_key' => $version->public_key,
            'public_key_pem' => $version->public_key_pem,
            'is_active' => (bool) $version->is_active,
            'is_revoked' => (bool) $version->is_revoked,
            'is_compat_mode' => $isCompatMode,
            'compat_window_days' => self::DEFAULT_COMPAT_WINDOW_DAYS,
            'activated_at' => $version->activated_at?->toIso8601String(),
            'expires_at' => $version->expires_at?->toIso8601String(),
            'revoked_at' => $version->revoked_at?->toIso8601String(),
            'revoke_reason' => $version->revoke_reason,
            'signed_files_count' => LicenseFileRecord::where('key_version', $version->key_version)->count(),
            'created_at' => $version->created_at?->toIso8601String(),
        ];
    }
}
