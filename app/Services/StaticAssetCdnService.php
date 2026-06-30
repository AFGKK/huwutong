<?php

namespace App\Services;

use App\Contracts\CloudStorage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 静态资源 CDN 加速服务 (M2-133)
 *
 * 负责前端构建产物的版本管理、CDN 推送、缓存刷新和版本回滚。
 * 支持 Vite 构建后的 JS/CSS/字体/图片等静态资源的 CDN 加速。
 */
class StaticAssetCdnService
{
    /**
     * CDN 存储路径前缀
     */
    const STORAGE_PREFIX = 'assets';

    /**
     * 缓存版本 Key
     */
    const CACHE_VERSION_KEY = 'static_asset:version';

    /**
     * 缓存控制 TTL（秒）— 7天
     */
    const CACHE_TTL = 604800;

    /**
     * 构建产物目录
     */
    const BUILD_DIR = 'public/build';

    public function __construct(
        protected CloudStorage $cloudStorage,
    ) {}

    /**
     * 获取当前 CDN 部署的版本号
     */
    public function getCurrentVersion(): string
    {
        return Cache::get(self::CACHE_VERSION_KEY, '1');
    }

    /**
     * 部署前端构建产物到 CDN
     *
     * @param string|null $version 版本号（默认使用时间戳）
     * @param string|null $buildDir 构建产物目录（默认 public/build）
     * @return array{version: string, files: array, failed: int, base_url: string}
     */
    public function deploy(?string $version = null, ?string $buildDir = null): array
    {
        $version = $version ?? now()->format('YmdHis');
        $buildDir = $buildDir ?? base_path(self::BUILD_DIR);

        if (! File::isDirectory($buildDir)) {
            throw new \RuntimeException("构建产物目录不存在: {$buildDir}");
        }

        $files = File::allFiles($buildDir);
        $uploaded = [];
        $failed = 0;

        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $remotePath = self::STORAGE_PREFIX . "/{$version}/{$relativePath}";

            try {
                $contentType = $this->getMimeType($file->getExtension());
                $url = $this->cloudStorage->upload($remotePath, $file->getContents(), [
                    'contentType' => $contentType,
                    'visibility' => 'public',
                ]);

                $uploaded[] = [
                    'local_path' => $relativePath,
                    'remote_path' => $remotePath,
                    'size' => $file->getSize(),
                    'mime' => $contentType,
                    'cdn_url' => $url,
                ];
            } catch (\Throwable $e) {
                Log::error('静态资源 CDN 上传失败', [
                    'file' => $relativePath,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        // 更新缓存版本
        Cache::forever(self::CACHE_VERSION_KEY, $version);

        // 生成 CDN base URL 供前端引用
        $baseUrl = $this->getAssetBaseUrl($version);

        Log::info('静态资源已部署到 CDN', [
            'version' => $version,
            'total' => count($uploaded),
            'failed' => $failed,
            'base_url' => $baseUrl,
        ]);

        return [
            'version' => $version,
            'files' => $uploaded,
            'total' => count($uploaded),
            'failed' => $failed,
            'base_url' => $baseUrl,
        ];
    }

    /**
     * 刷新 CDN 缓存（通过构建资源的版本号变更实现）
     * 实际 CDN 缓存通过 URL 版本号自然失效
     *
     * @param string|null $version 要激活的版本号
     */
    public function activateVersion(?string $version = null): array
    {
        if (! $version) {
            // 读取 build 目录下的最新版本
            $versions = $this->listDeployedVersions();
            $version = $versions[0] ?? null;
            if (! $version) {
                throw new \RuntimeException('没有已部署的版本');
            }
        }

        // 检查版本是否存在
        $versionPath = self::STORAGE_PREFIX . "/{$version}";
        if (! $this->cloudStorage->exists($versionPath . '/manifest.json')
            && ! $this->isDirectoryNotEmpty($versionPath)) {
            throw new \RuntimeException("版本 {$version} 在 CDN 上不存在");
        }

        Cache::forever(self::CACHE_VERSION_KEY, $version);

        Log::info('CDN 版本已激活', ['version' => $version]);

        return [
            'version' => $version,
            'base_url' => $this->getAssetBaseUrl($version),
            'activated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * 回滚到指定版本
     */
    public function rollback(string $version): array
    {
        $versions = $this->listDeployedVersions();
        $versionKeys = array_column($versions, 'version');

        if (! in_array($version, $versionKeys)) {
            throw new \RuntimeException("版本 {$version} 不存在");
        }

        Cache::forever(self::CACHE_VERSION_KEY, $version);

        Log::warning('CDN 版本已回滚', [
            'version' => $version,
        ]);

        return [
            'version' => $version,
            'base_url' => $this->getAssetBaseUrl($version),
        ];
    }

    /**
     * 获取静态资源 CDN base URL
     */
    public function getAssetBaseUrl(?string $version = null): string
    {
        $version = $version ?? $this->getCurrentVersion();
        $cdnDomain = config('cloud-storage.cdn_domain');

        if ($cdnDomain) {
            return "https://{$cdnDomain}/" . self::STORAGE_PREFIX . "/{$version}";
        }

        // 如果没有 CDN 域名，返回本地 URL
        $baseUrl = rtrim(config('app.url'), '/');
        return "{$baseUrl}/build";
    }

    /**
     * 获取 Vite 构建 manifest 内容
     */
    public function getManifest(?string $version = null): ?array
    {
        $version = $version ?? $this->getCurrentVersion();
        $manifestPath = self::STORAGE_PREFIX . "/{$version}/manifest.json";

        try {
            $content = $this->cloudStorage->download($manifestPath);
            return json_decode($content, true);
        } catch (\Throwable $e) {
            // 本地模式
            $localManifest = base_path(self::BUILD_DIR . '/manifest.json');
            if (File::exists($localManifest)) {
                return json_decode(File::get($localManifest), true);
            }
            return null;
        }
    }

    /**
     * 获取已部署的版本列表（降序）
     */
    public function listDeployedVersions(): array
    {
        try {
            $directories = $this->cloudStorage->listFiles(self::STORAGE_PREFIX, true);
            $versions = [];

            foreach ($directories as $path) {
                if (preg_match('#^' . preg_quote(self::STORAGE_PREFIX, '#') . '/(\d{14}|[^/]+)/#', $path, $m)) {
                    $versions[$m[1]] = $m[1];
                }
            }

            $result = array_keys($versions);
            rsort($result); // 最新版本在前

            return array_map(function ($v) {
                $currentVersion = $this->getCurrentVersion();
                return [
                    'version' => $v,
                    'is_current' => $v === $currentVersion,
                    'deployed_at' => $this->formatVersionDate($v),
                    'file_count' => $this->countVersionFiles($v),
                ];
            }, $result);
        } catch (\Throwable $e) {
            Log::warning('获取 CDN 版本列表失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 删除旧版本
     */
    public function deleteVersion(string $version): bool
    {
        $current = $this->getCurrentVersion();
        if ($version === $current) {
            throw new \RuntimeException('不能删除当前激活的版本');
        }

        $prefix = self::STORAGE_PREFIX . "/{$version}/";

        try {
            $files = $this->cloudStorage->listFiles($prefix, true);
            foreach ($files as $file) {
                $this->cloudStorage->delete($file);
            }
            Log::info('CDN 版本已删除', ['version' => $version, 'files' => count($files)]);
            return true;
        } catch (\Throwable $e) {
            Log::error('删除 CDN 版本失败', ['version' => $version, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 获取 CDN 部署统计
     */
    public function getStats(): array
    {
        $currentVersion = $this->getCurrentVersion();
        $versions = $this->listDeployedVersions();

        $totalSize = 0;
        $totalFiles = 0;

        foreach ($versions as $v) {
            $totalFiles += $v['file_count'];
            $totalSize += $this->getVersionTotalSize($v['version']);
        }

        $cdnDomain = config('cloud-storage.cdn_domain');

        return [
            'current_version' => $currentVersion,
            'total_versions' => count($versions),
            'total_files' => $totalFiles,
            'total_size_bytes' => $totalSize,
            'total_size_mb' => $totalSize > 0 ? round($totalSize / 1048576, 2) : 0,
            'cdn_domain' => $cdnDomain,
            'cdn_configured' => ! empty($cdnDomain),
            'base_url' => $this->getAssetBaseUrl(),
            'versions' => $versions,
        ];
    }

    /**
     * 获取构建产物的文件列表
     */
    public function getBuildFiles(): array
    {
        $buildDir = base_path(self::BUILD_DIR);
        if (! File::isDirectory($buildDir)) {
            return [];
        }

        $files = File::allFiles($buildDir);
        $result = [];

        foreach ($files as $file) {
            $result[] = [
                'path' => $file->getRelativePathname(),
                'size' => $file->getSize(),
                'extension' => $file->getExtension(),
                'modified_at' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }

        return $result;
    }

    /**
     * 根据文件扩展名获取 MIME 类型
     */
    protected function getMimeType(string $extension): string
    {
        return match (strtolower($extension)) {
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html', 'htm' => 'text/html',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'json' => 'application/json',
            'map' => 'application/json',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }

    /**
     * 格式化版本号为日期字符串
     */
    protected function formatVersionDate(string $version): string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $version, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]} {$m[4]}:{$m[5]}:{$m[6]}";
        }
        return $version;
    }

    /**
     * 统计版本文件数
     */
    protected function countVersionFiles(string $version): int
    {
        try {
            $prefix = self::STORAGE_PREFIX . "/{$version}/";
            $files = $this->cloudStorage->listFiles($prefix, true);
            return count($files);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 获取版本总大小
     */
    protected function getVersionTotalSize(string $version): int
    {
        try {
            $prefix = self::STORAGE_PREFIX . "/{$version}/";
            $files = $this->cloudStorage->listFiles($prefix, true);
            $total = 0;
            foreach ($files as $file) {
                try {
                    $meta = $this->cloudStorage->getMetadata($file);
                    $total += $meta['size'];
                } catch (\Throwable $e) {
                    // skip
                }
            }
            return $total;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * 检查目录是否非空
     */
    protected function isDirectoryNotEmpty(string $path): bool
    {
        try {
            $files = $this->cloudStorage->listFiles($path, true);
            return count($files) > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
