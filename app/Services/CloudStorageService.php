<?php

namespace App\Services;

use App\Contracts\CloudStorage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * 云存储统一适配层
 *
 * 支持：阿里云OSS / 腾讯云COS / 华为云OBS / Amazon S3 / Cloudflare R2 / Backblaze B2
 *
 * 配置在 config/filesystems.php 的 disks 中添加各驱动配置，
 * 通过 STORAGE_DISK 环境变量切换默认存储后端。
 */
class CloudStorageService implements CloudStorage
{
    /**
     * 存储驱动名称 → Laravel disk 映射
     */
    protected array $driverToDisk = [];

    protected string $currentDriver;

    protected ?string $cdnDomain = null;

    public function __construct()
    {
        $this->currentDriver = config('cloud-storage.default', 's3');
        $this->driverToDisk = config('cloud-storage.drivers', []);
        $this->cdnDomain = config('cloud-storage.cdn_domain');
    }

    /**
     * 获取当前使用的 Laravel Storage disk 实例
     */
    protected function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        $diskName = $this->driverToDisk[$this->currentDriver] ?? $this->currentDriver;
        return Storage::disk($diskName);
    }

    public function upload(string $path, mixed $contents, array $options = []): string
    {
        $visibility = $options['visibility'] ?? 'public';
        $contentType = $options['contentType'] ?? null;

        $config = [];
        if ($contentType) {
            $config['ContentType'] = $contentType;
        }
        if ($visibility === 'private') {
            $config['visibility'] = 'private';
        }

        $this->disk()->put($path, $contents, $config);

        Log::info('CloudStorage: file uploaded', [
            'driver' => $this->currentDriver,
            'path' => $path,
            'visibility' => $visibility,
            'size' => is_string($contents) ? strlen($contents) : 0,
        ]);

        return $this->url($path);
    }

    public function download(string $path): string
    {
        return $this->disk()->get($path);
    }

    public function stream(string $path): mixed
    {
        return $this->disk()->readStream($path);
    }

    public function delete(string $path): bool
    {
        $result = $this->disk()->delete($path);

        if ($result) {
            Log::info('CloudStorage: file deleted', [
                'driver' => $this->currentDriver,
                'path' => $path,
            ]);
        }

        return $result;
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    public function url(string $path, int $expires = 0): string
    {
        if ($expires > 0) {
            return $this->temporaryUrl($path, $expires);
        }

        $baseUrl = $this->disk()->url($path);

        // 如果配置了 CDN 域名，替换 URL
        if ($this->cdnDomain) {
            $parsed = parse_url($baseUrl);
            $baseUrl = ($parsed['scheme'] ?? 'https') . '://' . $this->cdnDomain . ($parsed['path'] ?? '');
            if (!empty($parsed['query'])) {
                $baseUrl .= '?' . $parsed['query'];
            }
        }

        return $baseUrl;
    }

    public function temporaryUrl(string $path, int $expires): string
    {
        try {
            return $this->disk()->temporaryUrl($path, now()->addSeconds($expires));
        } catch (\Exception $e) {
            // 如果 disk 不支持临时 URL，回退到公开 URL
            Log::warning('CloudStorage: temporaryUrl not supported, fallback to public url', [
                'driver' => $this->currentDriver,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            return $this->url($path, 0);
        }
    }

    public function copy(string $from, string $to): bool
    {
        return $this->disk()->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->disk()->move($from, $to);
    }

    public function deleteMultiple(array $paths): int
    {
        $successCount = 0;
        foreach ($paths as $path) {
            if ($this->delete($path)) {
                $successCount++;
            }
        }
        return $successCount;
    }

    public function listFiles(string $directory = '', bool $recursive = false): array
    {
        $method = $recursive ? 'allFiles' : 'files';
        return $this->disk()->{$method}($directory);
    }

    public function getMetadata(string $path): array
    {
        return [
            'size' => $this->size($path),
            'mimeType' => $this->disk()->mimeType($path),
            'lastModified' => $this->disk()->lastModified($path),
        ];
    }

    public function size(string $path): int
    {
        return $this->disk()->size($path);
    }

    public function driver(): string
    {
        return $this->currentDriver;
    }

    public function setDriver(string $driver): self
    {
        if (!isset($this->driverToDisk[$driver]) && !in_array($driver, array_keys(config('filesystems.disks', [])))) {
            throw new InvalidArgumentException("Unsupported cloud storage driver: {$driver}");
        }

        $this->currentDriver = $driver;
        return $this;
    }
}
