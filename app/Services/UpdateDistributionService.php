<?php

namespace App\Services;

use App\Contracts\CloudStorage;
use App\Models\Product;
use App\Models\UpdatePackage;
use App\Models\UpdatePackageDownload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 自动更新包云分发 + CDN 加速服务
 *
 * 管理软件更新包的：上传 → 存储 → 签名 → CDN分发 → 下载追踪
 * 支持：全量包、增量补丁、热修复包
 */
class UpdateDistributionService
{
    protected const CDN_PATH_PREFIX = 'updates';

    public function __construct(
        protected CloudStorage $storage,
    ) {}

    /**
     * 上传并创建更新包
     *
     * @param Product $product
     * @param string $version
     * @param UploadedFile $file
     * @param array $options
     * @return UpdatePackage
     */
    public function uploadPackage(Product $product, string $version, UploadedFile $file, array $options = []): UpdatePackage
    {
        return DB::transaction(function () use ($product, $version, $file, $options) {
            $type = $options['type'] ?? 'full';
            $prevVersion = $options['prev_version'] ?? null;
            $releaseNotes = $options['release_notes'] ?? [];
            $metadata = $options['metadata'] ?? [];

            // 计算文件哈希
            $fileHash = hash_file('sha256', $file->path());

            // 生成云存储路径
            $storagePath = $this->buildStoragePath($product, $version, $file);

            // 上传到云存储
            $fileContent = file_get_contents($file->path());
            $this->storage->upload($storagePath, $fileContent, [
                'visibility' => 'public',
                'contentType' => $file->getMimeType() ?: 'application/octet-stream',
            ]);

            // 生成 Ed25519 签名
            $signature = $this->signPackage($fileHash);

            // 创建数据库记录
            $package = UpdatePackage::create([
                'product_id' => $product->id,
                'version' => $version,
                'prev_version' => $prevVersion,
                'type' => $type,
                'file_path' => $storagePath,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'file_hash' => $fileHash,
                'signature' => $signature,
                'checksums' => $this->computeChunkChecksums($file->path()),
                'release_notes' => $releaseNotes,
                'metadata' => $metadata,
                'status' => 'draft',
                'created_by' => auth()->id() ?? 1,
            ]);

            Log::info('UpdatePackage: uploaded', [
                'package_id' => $package->id,
                'product_id' => $product->id,
                'version' => $version,
                'type' => $type,
                'size' => $file->getSize(),
            ]);

            return $package;
        });
    }

    /**
     * 发布更新包
     */
    public function publishPackage(UpdatePackage $package): UpdatePackage
    {
        $package->publish();
        Log::info('UpdatePackage: published', [
            'package_id' => $package->id,
            'version' => $package->version,
        ]);
        return $package->fresh();
    }

    /**
     * 废弃更新包
     */
    public function deprecatePackage(UpdatePackage $package): UpdatePackage
    {
        $package->deprecate();
        Log::info('UpdatePackage: deprecated', [
            'package_id' => $package->id,
            'version' => $package->version,
        ]);
        return $package->fresh();
    }

    /**
     * 删除更新包（同时删除云存储文件）
     */
    public function deletePackage(UpdatePackage $package): bool
    {
        DB::transaction(function () use ($package) {
            // 删除云存储文件
            $this->storage->delete($package->file_path);
            $package->downloads()->delete();
            $package->delete();
        });

        Log::info('UpdatePackage: deleted', [
            'package_id' => $package->id,
        ]);

        return true;
    }

    /**
     * 记录下载事件
     */
    public function recordDownload(UpdatePackage $package, ?string $clientIp = null, ?string $userAgent = null): UpdatePackageDownload
    {
        $tenantId = tenant('id');

        return UpdatePackageDownload::create([
            'update_package_id' => $package->id,
            'tenant_id' => $tenantId,
            'client_ip' => $clientIp ?? request()->ip(),
            'user_agent' => $userAgent ?? request()->userAgent(),
            'downloaded_at' => now(),
        ]);
    }

    /**
     * 获取下载签名的 URL（带 CDN 加速）
     */
    public function getDownloadUrl(UpdatePackage $package, int $expires = 3600): string
    {
        return $this->storage->url($package->file_path, $expires);
    }

    /**
     * 获取产品的最新版本
     */
    public function getLatestVersion(Product $product, ?string $currentVersion = null): ?UpdatePackage
    {
        $query = UpdatePackage::where('product_id', $product->id)
            ->where('status', 'published');

        if ($currentVersion) {
            // 查找比当前版本更新的版本
            $query->where('version', '>', $currentVersion);
        }

        return $query->orderBy('id', 'desc')->first();
    }

    /**
     * 检查是否有可用更新
     */
    public function hasUpdate(Product $product, string $currentVersion): bool
    {
        return $this->getLatestVersion($product, $currentVersion) !== null;
    }

    /**
     * 生成存储路径
     */
    protected function buildStoragePath(Product $product, string $version, UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $prefix = config('cloud-storage.path_prefix', '');
        $productSlug = Str::slug($product->name);

        return trim($prefix, '/') . '/' . self::CDN_PATH_PREFIX . '/' . $productSlug . '/' . $version . '/package.' . $ext;
    }

    /**
     * 对包文件进行签名
     *
     * 使用 Ed25519 私钥对包文件哈希签名
     */
    protected function signPackage(string $fileHash): string
    {
        $privateKey = config('license.ed25519_private_key');

        if (!$privateKey) {
            Log::warning('UpdatePackage: signing skipped, no private key configured');
            return '';
        }

        try {
            $keyPair = sodium_crypto_sign_secretkey($privateKey);
            $signature = sodium_crypto_sign_detached($fileHash, $privateKey);
            return base64_encode($signature);
        } catch (\Throwable $e) {
            Log::error('UpdatePackage: signing failed', ['error' => $e->getMessage()]);
            return '';
        }
    }

    /**
     * 计算分块校验和（用于断点续传）
     */
    protected function computeChunkChecksums(string $filePath): array
    {
        $chunkSize = 1024 * 1024; // 1MB 分块
        $checksums = [];
        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return [];
        }

        $index = 0;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk !== false && strlen($chunk) > 0) {
                $checksums[] = [
                    'index' => $index,
                    'offset' => $index * $chunkSize,
                    'hash' => hash('sha256', $chunk),
                ];
                $index++;
            }
        }

        fclose($handle);
        return $checksums;
    }
}
