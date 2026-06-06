<?php

namespace App\Services;

use App\Models\License;
use App\Models\OfflineCertificate;
use App\Models\OfflineCrlEntry;
use App\Models\OfflineVerification as OfflineVerificationModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 离线验证器
 *
 * 核心职责：
 * 1. 公钥验签 — 验证离线 License 文件的数字签名
 * 2. 有效期保护 — 验证 License 是否在有效期内
 * 3. 防时间回滚 — 检测客户端系统时间篡改
 * 4. 吊销列表检查 — 检查 License 是否被吊销
 *
 * 离线验证流程：
 *   客户端提交 .license 文件内容（Base64）→
 *   服务端解析二进制格式 →
 *   提取签名并验证 →
 *   检查有效期 → 检查 CRL → 返回验证结果
 */
class OfflineVerifier
{
    /**
     * 允许的时间偏差（秒）— 客户端与服务端时间差容忍度
     */
    const TIME_DRIFT_TOLERANCE = 300;

    /**
     * 防回滚窗口（秒）— 记录上次成功验证时间，新验证必须 ≥ 上次时间 - 容忍偏差
     */
    const ANTI_ROLLBACK_WINDOW = 3600;

    /**
     * 不支持算法列表（策略变更后标记）
     */
    const DEPRECATED_ALGORITHMS = [];

    /**
     * 验证离线 License 文件
     *
     * @param string $fileContentBase64 Base64 编码的 .license 文件内容
     * @param string|null $clientIp 客户端 IP（审计用）
     * @return OfflineVerificationResult
     */
    public function verify(string $fileContentBase64, ?string $clientIp = null): OfflineVerificationResult
    {
        try {
            // 1. 解析文件
        $parsed = $this->parseFile($fileContentBase64);
        if (! $parsed) {
            return OfflineVerificationResult::invalid('FILE_PARSE_ERROR', '离线文件格式解析失败');
        }

        $payloadJson = $parsed['payload'];
        $payload = $parsed['parsed_payload'];
        $signature = $parsed['signature'];
        $algorithm = $parsed['algorithm'];

        // 2. 验签
        $verifyResult = $this->verifySignature($payloadJson, $signature, $algorithm, $payload['kid'] ?? null);
            if (! $verifyResult['valid']) {
                return OfflineVerificationResult::invalid('SIGNATURE_INVALID', $verifyResult['reason']);
            }

            $publicKey = $verifyResult['public_key'];

            // 3. 检查算法是否已废弃
            if (in_array($algorithm, self::DEPRECATED_ALGORITHMS, true)) {
                return OfflineVerificationResult::invalid('ALGORITHM_DEPRECATED', "签名算法 {$algorithm} 已废弃，请更新客户端");
            }

            // 4. 检查有效期
            $expiresAt = isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null;
            if ($expiresAt && $expiresAt->isPast()) {
                return OfflineVerificationResult::expired('离线 License 已过期', [
                    'expires_at' => $expiresAt->toIso8601String(),
                ]);
            }

            // 5. 防时间回滚
            $antiRollback = $this->checkAntiRollback($payload['lic_key'], $payload);
            if (! $antiRollback['passed']) {
                return OfflineVerificationResult::invalid('TIME_ROLLBACK_DETECTED', $antiRollback['reason']);
            }

            // 6. 检查吊销列表
            $crlCheck = $this->checkCrl($payload['lic_key']);
            if (! $crlCheck['valid']) {
                return OfflineVerificationResult::revoked($crlCheck['reason'] ?? 'License 已被吊销');
            }

            // 7. 记录成功验证
            $this->recordVerification($payload['lic_key'], 'valid', $clientIp);

            return OfflineVerificationResult::valid(
                '离线验证通过',
                $payload,
                [
                    'algorithm' => $algorithm,
                    'key_version' => $payload['kid'] ?? null,
                    'public_key' => $publicKey,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('离线验证异常', [
                'error' => $e->getMessage(),
                'client_ip' => $clientIp,
            ]);
            return OfflineVerificationResult::invalid('VERIFICATION_ERROR', '离线验证过程中发生异常');
        }
    }

    /**
     * 解析 .license 文件
     *
     * 二进制格式：
     * [魔数 5B] [版本 2B] [算法 1B] [签名 64B] [JSON 载荷]
     */
    protected function parseFile(string $fileContentBase64): ?array
    {
        $binary = base64_decode($fileContentBase64, true);
        if ($binary === false || strlen($binary) < 72) {
            return null;
        }

        // 检查魔数
        $magic = substr($binary, 0, 5);
        if ($magic !== OfflineLicenseService::FILE_MAGIC) {
            return null;
        }

        // 解析版本
        $version = unpack('n', substr($binary, 5, 2))[1];
        if ($version < 1 || $version > OfflineLicenseService::FILE_VERSION) {
            return null;
        }

        // 解析算法
        $algByte = substr($binary, 7, 1);
        $algorithm = match ($algByte) {
            "\x01" => OfflineLicenseService::ALGORITHM_ED25519,
            "\x02" => OfflineLicenseService::ALGORITHM_RSA2048,
            default => null,
        };
        if (! $algorithm) {
            return null;
        }

        // 提取签名
        $sigLength = $algorithm === OfflineLicenseService::ALGORITHM_ED25519 ? 64 : 256;
        $signature = base64_encode(substr($binary, 8, $sigLength));

        // 提取 JSON 载荷
        $payloadJson = substr($binary, 8 + $sigLength);
        $payload = json_decode($payloadJson, true);
        if (! $payload || ! isset($payload['lic_key'])) {
            return null;
        }

        return [
            'payload' => $payloadJson,
            'signature' => $signature,
            'algorithm' => $algorithm,
            'parsed_payload' => $payload,
        ];
    }

    /**
     * 验证签名
     */
    protected function verifySignature(string $payloadJson, string $signature, string $algorithm, ?int $keyVersion): array
    {
        $certificate = $this->resolveCertificate($algorithm, $keyVersion);

        if (! $certificate) {
            return ['valid' => false, 'reason' => '找不到对应的签名公钥证书', 'public_key' => null];
        }

        $publicKey = base64_decode($certificate->public_key);
        $signatureBin = base64_decode($signature);

        try {
            $valid = match ($algorithm) {
                OfflineLicenseService::ALGORITHM_ED25519 => sodium_crypto_sign_verify_detached($signatureBin, $payloadJson, $publicKey),
                OfflineLicenseService::ALGORITHM_RSA2048 => $this->verifyRsaSignature($payloadJson, $signatureBin, $publicKey),
                default => false,
            };

            if (! $valid) {
                return ['valid' => false, 'reason' => '签名验证失败', 'public_key' => null];
            }

            return ['valid' => true, 'reason' => null, 'public_key' => $certificate->public_key];
        } catch (\Throwable $e) {
            return ['valid' => false, 'reason' => '签名验证异常: ' . $e->getMessage(), 'public_key' => null];
        }
    }

    /**
     * RSA-2048 兼容模式验签
     */
    protected function verifyRsaSignature(string $data, string $signature, string $publicKeyBin): bool
    {
        if (! extension_loaded('openssl')) {
            throw new \RuntimeException('OpenSSL 扩展未安装，无法验证 RSA 签名');
        }

        $publicKey = openssl_pkey_get_public($publicKeyBin);
        if (! $publicKey) {
            return false;
        }

        $result = openssl_verify($data, $signature, $publicKey, OPENSSL_ALGO_SHA256);
        openssl_free_key($publicKey);

        return $result === 1;
    }

    /**
     * 解析证书
     */
    protected function resolveCertificate(string $algorithm, ?int $keyVersion): ?OfflineCertificate
    {
        $cacheKey = 'offline_cert:' . ($keyVersion ?? 'latest');

        return Cache::remember($cacheKey, 300, function () use ($algorithm, $keyVersion) {
            $query = OfflineCertificate::where('algorithm', $algorithm)
                ->where('is_active', true)
                ->where('is_revoked', false);

            if ($keyVersion) {
                $query->where('key_version', $keyVersion);
            }

            return $query->orderBy('key_version', 'desc')->first();
        });
    }

    /**
     * 防时间回滚检查
     *
     * 原理：记录每个 License 最近一次离线验证成功的时间戳，
     * 新验证的 expires_at 必须 ≥ 上次记录的 expires_at（容忍偏差内）
     */
    protected function checkAntiRollback(string $licenseKey, array $payload): array
    {
        $cacheKey = 'offline_last_verify:' . $licenseKey;
        $lastVerify = Cache::get($cacheKey);

        if (! $lastVerify) {
            // 首次验证，记录当前时间
            $now = now()->toIso8601String();
            Cache::put($cacheKey, [
                'last_expires_at' => $payload['expires_at'] ?? null,
                'verified_at' => $now,
            ], self::ANTI_ROLLBACK_WINDOW * 2);

            return ['passed' => true];
        }

        // 检查是否回滚
        if ($payload['expires_at'] && $lastVerify['last_expires_at']) {
            $currentExpires = Carbon::parse($payload['expires_at']);
            $lastExpires = Carbon::parse($lastVerify['last_expires_at']);

            // 新的 expire_at 比上次记录的还早很多 → 可能是时间回滚攻击
            $drift = $lastExpires->diffInSeconds($currentExpires, false);
            if ($drift < -self::TIME_DRIFT_TOLERANCE) {
                Log::warning('防回滚检测: 检测到有效期的回退', [
                    'license_key' => $licenseKey,
                    'last_expires_at' => $lastExpires->toIso8601String(),
                    'current_expires_at' => Carbon::parse($payload['expires_at'])->toIso8601String(),
                    'drift_seconds' => $drift,
                ]);

                return [
                    'passed' => false,
                    'reason' => '检测到系统时间回滚，请校准系统时间后重试',
                ];
            }
        }

        // 更新验证记录
        Cache::put($cacheKey, [
            'last_expires_at' => $payload['expires_at'] ?? $lastVerify['last_expires_at'],
            'verified_at' => now()->toIso8601String(),
        ], self::ANTI_ROLLBACK_WINDOW * 2);

        return ['passed' => true];
    }

    /**
     * 检查吊销列表
     */
    protected function checkCrl(string $licenseKey): array
    {
        $cacheKey = 'crl_check:' . $licenseKey;

        // 缓存检查结果（1分钟缓存，减少 DB 查询）
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $entry = OfflineCrlEntry::where('license_key', $licenseKey)->first();

        $result = [
            'valid' => $entry === null,
            'reason' => $entry?->reason,
            'revoked_at' => $entry?->revoked_at?->toIso8601String(),
        ];

        Cache::put($cacheKey, $result, 60);

        return $result;
    }

    /**
     * 记录验证结果
     */
    protected function recordVerification(string $licenseKey, string $result, ?string $clientIp): void
    {
        try {
            $license = License::where('license_key', $licenseKey)->first();

            OfflineVerificationModel::create([
                'license_id' => $license?->id,
                'license_key' => $licenseKey,
                'result' => $result,
                'client_ip' => $clientIp,
            ]);

            // 更新 CRL 缓存（如果有新的吊销，确保缓存失效）
            Cache::forget('crl_check:' . $licenseKey);
        } catch (\Throwable $e) {
            Log::error('记录离线验证失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取 CRL（供客户端拉取）
     *
     * 返回增量或全量吊销列表
     *
     * @param int|null $since 上次拉取的时间戳
     * @return array
     */
    public function getCrl(?int $since = null): array
    {
        $cacheKey = 'crl_full';
        $ttl = 300; // 5 分钟缓存

        $query = OfflineCrlEntry::with('certificate');

        if ($since) {
            $query->where('created_at', '>', Carbon::createFromTimestamp($since));
        }

        $entries = $query->orderBy('created_at', 'desc')->limit(10000)->get();

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
     * 吊销一个 License（加入 CRL）
     */
    public function revokeLicense(string $licenseKey, string $reason = '管理员吊销'): void
    {
        $certificate = OfflineCertificate::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();

        if (! $certificate) {
            throw new \RuntimeException('没有活跃的离线证书，无法吊销 License');
        }

        OfflineCrlEntry::firstOrCreate([
            'offline_certificate_id' => $certificate->id,
            'license_key' => $licenseKey,
        ], [
            'reason' => $reason,
            'revoked_at' => now(),
        ]);

        // 清除缓存
        Cache::forget('crl_check:' . $licenseKey);
        Cache::forget('crl_full');

        Log::info('离线 CRL: License 已加入吊销列表', [
            'license_key' => $licenseKey,
            'reason' => $reason,
        ]);
    }

    /**
     * 从 CRL 移除（恢复 License）
     */
    public function restoreLicense(string $licenseKey): void
    {
        OfflineCrlEntry::where('license_key', $licenseKey)->delete();

        Cache::forget('crl_check:' . $licenseKey);
        Cache::forget('crl_full');

        Log::info('离线 CRL: License 已移出吊销列表', ['license_key' => $licenseKey]);
    }

    /**
     * 获取证书公钥（供客户端下载）
     */
    public function getPublicKey(int $keyVersion): ?array
    {
        $certificate = OfflineCertificate::where('key_version', $keyVersion)
            ->where('is_active', true)
            ->first();

        if (! $certificate) {
            return null;
        }

        return [
            'key_version' => $certificate->key_version,
            'algorithm' => $certificate->algorithm,
            'public_key' => $certificate->public_key,
            'expires_at' => $certificate->expires_at?->toIso8601String(),
        ];
    }
}
