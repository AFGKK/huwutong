<?php

namespace App\Services;

use App\Models\CdnDistribution;
use App\Models\CertificateRevocationList;
use App\Models\License;
use App\Models\LicenseFileRecord;
use App\Models\OfflineCertificate;
use App\Models\PublicKeyVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CdnDistributionService
{
    const CDN_CACHE_TTL = 86400; // CDN 缓存 1 天
    const PUBLIC_KEY_COMPAT_WINDOW = 30; // 旧公钥保留 30 天兼容窗口

    /**
     * 生成 License 文件并上传到存储
     */
    public function generateAndDistribute(License $license): LicenseFileRecord
    {
        $offlineService = app(OfflineLicenseService::class);

        // 获取活跃密钥
        $certificate = OfflineCertificate::where('is_active', true)
            ->where('is_revoked', false)
            ->orderBy('key_version', 'desc')
            ->first();

        if (! $certificate) {
            throw new \RuntimeException('没有活跃的离线签名证书');
        }

        $keyPair = $offlineService->getActiveKeyPair();

        // 生成文件
        $result = $offlineService->generateLicenseFile(
            $license,
            $keyPair['private_key'],
            $certificate->public_key,
            $certificate->algorithm,
        );

        $fileBin = base64_decode($result['file_content']);
        $fileHash = hash('sha256', $fileBin);

        // 构建存储路径
        $fileKey = "licenses/{$license->license_key}/{$license->license_key}.license";

        // 存储文件
        $stored = Storage::disk(config('license.cdn.disk', 'local'))->put($fileKey, $fileBin);
        if (! $stored) {
            throw new \RuntimeException('License 文件存储失败');
        }

        // 构建 CDN URL
        $cdnUrl = $this->buildCdnUrl($fileKey);

        // 保存记录
        $record = LicenseFileRecord::create([
            'license_id' => $license->id,
            'file_key' => $fileKey,
            'original_filename' => "{$license->license_key}.license",
            'mime_type' => 'application/octet-stream',
            'file_size' => strlen($fileBin),
            'file_hash' => $fileHash,
            'signature' => $result['signature'],
            'key_version' => OfflineLicenseService::KEY_VERSION,
            'algorithm' => $certificate->algorithm,
            'payload_snapshot' => $result['payload'],
            'storage_driver' => config('license.cdn.disk', 'local'),
            'cdn_url' => $cdnUrl,
            'status' => 'active',
            'expires_at' => $license->expires_at,
        ]);

        return $record;
    }

    /**
     * 批量分发
     */
    public function batchDistribute(array $licenseIds): array
    {
        $results = [];
        $licenses = License::whereIn('id', $licenseIds)->get();

        foreach ($licenses as $license) {
            try {
                $record = $this->generateAndDistribute($license);
                $results[] = [
                    'license_key' => $license->license_key,
                    'success' => true,
                    'record_id' => $record->id,
                    'cdn_url' => $record->cdn_url,
                ];
            } catch (\Throwable $e) {
                Log::error('License 文件分发失败', [
                    'license_key' => $license->license_key,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'license_key' => $license->license_key,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * 提供 License 文件下载（支持 CDN 回源）
     */
    public function serveFile(string $licenseKey, Request $request): array
    {
        $record = LicenseFileRecord::whereHas('license', function ($q) use ($licenseKey) {
            $q->where('license_key', $licenseKey);
        })->where('status', 'active')->latest()->first();

        if (! $record) {
            throw new \RuntimeException('License 文件不存在或已失效', 404);
        }

        // 记录分发日志
        $this->logDistribution($record, $request);

        // 返回文件信息（实际响应由 Controller 处理流式返回）
        $disk = Storage::disk(config('license.cdn.disk', 'local'));

        if (! $disk->exists($record->file_key)) {
            // 尝试重新生成
            $record = $this->generateAndDistribute($record->license);
        }

        return [
            'record' => $record,
            'file_path' => $record->file_key,
            'file_content' => $disk->get($record->file_key),
            'file_hash' => $record->file_hash,
            'mime_type' => $record->mime_type,
            'original_filename' => $record->original_filename,
        ];
    }

    /**
     * 吊销 License 文件
     */
    public function revokeFile(string $licenseKey, ?string $reason = null): bool
    {
        $record = LicenseFileRecord::whereHas('license', function ($q) use ($licenseKey) {
            $q->where('license_key', $licenseKey);
        })->where('status', 'active')->first();

        if ($record) {
            $record->update(['status' => 'revoked']);
        }

        // 加入 CRL
        CertificateRevocationList::create([
            'license_file_record_id' => $record?->id,
            'license_key' => $licenseKey,
            'key_version' => OfflineLicenseService::KEY_VERSION,
            'reason' => $reason ?? '管理员吊销',
            'revoked_at' => now(),
        ]);

        return true;
    }

    /**
     * 重新分发（更新文件）
     */
    public function redistribute(License $license): LicenseFileRecord
    {
        // 先吊销旧文件
        LicenseFileRecord::where('license_id', $license->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        return $this->generateAndDistribute($license);
    }

    /**
     * 获取公钥版本列表（供客户端 CDN 拉取）
     */
    public function getPublicKeyList(): array
    {
        $keys = PublicKeyVersion::getValid();

        return array_map(function ($key) {
            return [
                'key_version' => $key->key_version,
                'algorithm' => $key->algorithm,
                'public_key' => $key->public_key,
                'public_key_pem' => $key->public_key_pem,
                'activated_at' => $key->activated_at?->toIso8601String(),
            ];
        }, $keys);
    }

    /**
     * 初始化/轮换公钥版本（对接密钥轮换）
     */
    public function rotatePublicKey(string $publicKeyBase64, string $algorithm = 'Ed25519'): PublicKeyVersion
    {
        $maxVersion = PublicKeyVersion::max('key_version') ?? 0;
        $newVersion = $maxVersion + 1;

        // 停用旧密钥
        PublicKeyVersion::where('is_active', true)->update(['is_active' => false]);

        // 旧密钥设置 30 天过期窗口
        PublicKeyVersion::where('is_revoked', false)
            ->where('is_active', false)
            ->whereNull('expires_at')
            ->update(['expires_at' => now()->addDays(self::PUBLIC_KEY_COMPAT_WINDOW)]);

        // 创建新密钥
        $key = PublicKeyVersion::create([
            'key_version' => $newVersion,
            'algorithm' => $algorithm,
            'public_key' => $publicKeyBase64,
            'is_active' => true,
            'is_revoked' => false,
            'expires_at' => now()->addYear(),
        ]);

        return $key;
    }

    /**
     * 获取分发统计
     */
    public function getStats(): array
    {
        $totalFiles = LicenseFileRecord::count();
        $activeFiles = LicenseFileRecord::where('status', 'active')->count();
        $totalDownloads = CdnDistribution::count();
        $recentDownloads = CdnDistribution::where('downloaded_at', '>=', now()->subDay())->count();
        $totalBytes = CdnDistribution::sum('bytes_served');

        // 热门下载文件 TOP 10
        $topFiles = LicenseFileRecord::withCount('distributions')
            ->having('distributions_count', '>', 0)
            ->orderBy('distributions_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($f) {
                return [
                    'id' => $f->id,
                    'license_key' => $f->license->license_key ?? 'N/A',
                    'file_size' => $f->file_size,
                    'downloads' => $f->distributions_count,
                    'status' => $f->status,
                    'cdn_url' => $f->cdn_url,
                ];
            });

        return [
            'total_files' => $totalFiles,
            'active_files' => $activeFiles,
            'total_downloads' => $totalDownloads,
            'recent_downloads_24h' => $recentDownloads,
            'total_bytes_served' => $totalBytes,
            'top_files' => $topFiles,
        ];
    }

    /**
     * 记录分发日志
     */
    private function logDistribution(LicenseFileRecord $record, Request $request): void
    {
        try {
            CdnDistribution::create([
                'license_file_record_id' => $record->id,
                'client_ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'referer' => $request->header('referer'),
                'country' => $this->getCountryFromIp($request->ip()),
                'response_code' => 200,
                'bytes_served' => $record->file_size,
                'downloaded_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('记录分发日志失败', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 构建 CDN URL
     */
    private function buildCdnUrl(string $fileKey): string
    {
        $cdnDomain = config('license.cdn.domain', config('app.url'));

        return rtrim($cdnDomain, '/') . '/' . ltrim(config('license.cdn.prefix', 'storage'), '/') . '/' . $fileKey;
    }

    /**
     * IP 转国家（简化版，生产接入 GeoIP 服务）
     */
    private function getCountryFromIp(string $ip): string
    {
        // 内网 IP
        if (in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
            return 'LOCAL';
        }

        if (str_starts_with($ip, '192.168.') || str_starts_with($ip, '10.')) {
            return 'PRIVATE';
        }

        return 'UNKNOWN';
    }
}
