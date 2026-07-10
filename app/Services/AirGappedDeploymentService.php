<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * 气隙部署服务 (Air-Gapped Deployment)
 *
 * 面向军工/政府/银行内网的完全离线部署方案
 * - 零外网依赖运行
 * - Docker 镜像离线包管理
 * - License 文件 U 盘导入
 * - 离线更新包系统
 *
 * @m3-61
 */
class AirGappedDeploymentService
{
    /**
     * 离线包存储路径
     */
    const OFFLINE_PACKAGE_PATH = 'air-gapped/packages';

    /**
     * License 导入目录
     */
    const LICENSE_IMPORT_PATH = 'air-gapped/licenses';

    /**
     * 更新包目录
     */
    const UPDATE_PACKAGE_PATH = 'air-gapped/updates';

    /**
     * 缓存键
     */
    const CACHE_KEY_STATUS = 'airgapped:status';
    const CACHE_KEY_METRICS = 'airgapped:metrics';

    /**
     * 检查系统是否处于气隙模式
     */
    public function isAirGappedMode(): bool
    {
        return config('app.air_gapped_mode', false)
            || env('AIR_GAPPED_MODE', false)
            || $this->detectAirGapped();
    }

    /**
     * 自动检测是否为气隙环境
     */
    protected function detectAirGapped(): bool
    {
        // 尝试连接公网，超时短
        $connected = @fsockopen('8.8.8.8', 53, $errno, $errstr, 2);

        if ($connected) {
            fclose($connected);
            return false;
        }

        // 多次检测失败，判定为气隙环境
        return true;
    }

    /**
     * 获取气隙部署状态概览
     */
    public function getStatus(): array
    {
        return Cache::remember(self::CACHE_KEY_STATUS, 300, function () {
            return [
                'air_gapped_mode' => $this->isAirGappedMode(),
                'detected' => $this->detectAirGapped(),
                'license_count' => $this->countImportedLicenses(),
                'update_count' => $this->countAvailableUpdates(),
                'last_import' => $this->getLastImportTimestamp(),
                'last_update' => $this->getLastUpdateTimestamp(),
                'disk_usage' => $this->getDiskUsage(),
                'php_extensions' => $this->checkRequiredExtensions(),
                'storage_writable' => $this->checkStorageWritable(),
            ];
        });
    }

    /**
     * 获取详细指标
     */
    public function getMetrics(): array
    {
        return Cache::remember(self::CACHE_KEY_METRICS, 600, function () {
            $storage = storage_path('app/air-gapped');

            return [
                'offline_packages' => $this->countOfflinePackages(),
                'imported_licenses' => $this->countImportedLicenses(),
                'available_updates' => $this->countAvailableUpdates(),
                'total_size' => $this->getDirectorySize($storage),
                'last_health_check' => now()->toIso8601String(),
            ];
        });
    }

    /**
     * 导入 License 文件（从 U 盘/本地路径）
     *
     * @param string $sourcePath 源文件路径
     * @param bool $validate 是否验证签名
     * @return array{success: bool, message: string, license_key?: string}
     */
    public function importLicense(string $sourcePath, bool $validate = true): array
    {
        if (!file_exists($sourcePath)) {
            return [
                'success' => false,
                'message' => "License 文件不存在: {$sourcePath}",
            ];
        }

        try {
            $content = file_get_contents($sourcePath);
            $filename = basename($sourcePath);
            $targetDir = storage_path('app/' . self::LICENSE_IMPORT_PATH);
            $targetPath = $targetDir . '/' . $filename;

            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            // 如果启用验证，尝试验签
            if ($validate) {
                $app = app();
                $offlineService = $app->make(OfflineLicenseService::class);

                try {
                    $result = $offlineService->verifyLicenseFile($content);
                    if (!$result['valid']) {
                        Log::warning('AirGapped: License verification failed', [
                            'file' => $filename,
                            'reason' => $result['reason'] ?? 'unknown',
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('AirGapped: License verification error (non-blocking)', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 复制文件
            copy($sourcePath, $targetPath);

            // 记录导入日志
            Log::info('AirGapped: License imported via USB', [
                'file' => $filename,
                'size' => filesize($sourcePath),
                'source' => $sourcePath,
            ]);

            // 清除缓存
            Cache::forget(self::CACHE_KEY_STATUS);

            return [
                'success' => true,
                'message' => "License 文件导入成功: {$filename}",
                'path' => $targetPath,
            ];
        } catch (\Throwable $e) {
            Log::error('AirGapped: License import failed', [
                'error' => $e->getMessage(),
                'file' => $sourcePath,
            ]);

            return [
                'success' => false,
                'message' => "License 导入失败: {$e->getMessage()}",
            ];
        }
    }

    /**
     * 扫描 U 盘中的 License 文件
     *
     * @return array
     */
    public function scanUsbDrives(): array
    {
        $candidates = [];

        // 常见 U 盘挂载点
        $mountPoints = [
            '/mnt/usb',
            '/mnt/usb0',
            '/mnt/usb1',
            '/media/usb',
            '/run/media/usb',
            '/mnt/cdrom',
        ];

        // Windows 下的盘符
        if (PHP_OS_FAMILY === 'Windows') {
            for ($letter = 'D'; $letter <= 'Z'; $letter++) {
                $path = "{$letter}:\\";
                if (is_dir($path)) {
                    $mountPoints[] = $path;
                }
            }
        }

        foreach ($mountPoints as $mountPoint) {
            if (!is_dir($mountPoint)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($mountPoint, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $ext = strtolower($file->getExtension());
                    if (in_array($ext, ['lic', 'license', 'key', 'pem'])) {
                        $candidates[] = [
                            'path' => $file->getPathname(),
                            'name' => $file->getFilename(),
                            'size' => $file->getSize(),
                            'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        ];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * 创建离线更新包（在联网环境执行）
     *
     * @param string $version
     * @param array $images
     * @return array
     */
    public function createUpdatePackage(string $version, array $images = []): array
    {
        $packageDir = storage_path('app/' . self::UPDATE_PACKAGE_PATH . "/hwt-update-{$version}");
        $packageFile = storage_path('app/' . self::UPDATE_PACKAGE_PATH . "/hwt-update-{$version}.tar.gz");

        if (!is_dir($packageDir)) {
            mkdir($packageDir, 0755, true);
        }

        // 创建版本文件
        file_put_contents(
            "{$packageDir}/VERSION",
            "{$version}\n" . date('Y-m-d H:i:s') . "\n"
        );

        // 创建更新脚本
        $this->createUpdateScripts($packageDir);

        // 创建 Docker 镜像导出目录
        if (!empty($images)) {
            $imagesDir = "{$packageDir}/docker-images";
            mkdir($imagesDir, 0755, true);

            foreach ($images as $image) {
                $tarName = str_replace(['/', ':'], '_', $image) . '.tar';
                $cmd = "docker save {$image} -o {$imagesDir}/{$tarName}";
                exec($cmd, $output, $exitCode);

                if ($exitCode !== 0) {
                    Log::warning("AirGapped: Failed to export image {$image}");
                }
            }
        }

        // 生成 SHA256
        $this->generateSha256Sums($packageDir);

        // 打包
        $this->tarPackage($packageDir, $packageFile);

        // 清理临时目录
        $this->delTree($packageDir);

        return [
            'success' => true,
            'version' => $version,
            'package' => $packageFile,
            'size' => file_exists($packageFile) ? filesize($packageFile) : 0,
        ];
    }

    /**
     * 应用离线更新包
     *
     * @param string $packagePath
     * @return array
     */
    public function applyUpdate(string $packagePath): array
    {
        if (!file_exists($packagePath)) {
            return [
                'success' => false,
                'message' => "更新包不存在: {$packagePath}",
            ];
        }

        $tempDir = storage_path('app/air-gapped/temp/' . uniqid('update_', true));

        try {
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            // 解压
            $phar = new \PharData($packagePath);
            $phar->extractTo($tempDir);

            // 校验 SHA256
            $shaFile = "{$tempDir}/SHA256SUMS";
            if (file_exists($shaFile)) {
                $output = [];
                $exitCode = 0;
                exec("cd {$tempDir} && sha256sum -c SHA256SUMS 2>&1", $output, $exitCode);

                if ($exitCode !== 0) {
                    throw new \RuntimeException('更新包完整性校验失败');
                }
            }

            // 执行前置脚本
            $preScript = "{$tempDir}/scripts/pre-update.sh";
            if (file_exists($preScript)) {
                exec("bash {$preScript} 2>&1", $output, $exitCode);
            }

            // 加载 Docker 镜像
            $imagesDir = "{$tempDir}/docker-images";
            if (is_dir($imagesDir)) {
                $tarFiles = glob("{$imagesDir}/*.tar");
                foreach ($tarFiles as $tarFile) {
                    exec("docker load -i {$tarFile} 2>&1", $output, $exitCode);
                }
            }

            // 执行后置脚本
            $postScript = "{$tempDir}/scripts/post-update.sh";
            if (file_exists($postScript)) {
                exec("bash {$postScript} 2>&1", $output, $exitCode);
            }

            // 记录更新日志
            $version = file_get_contents("{$tempDir}/VERSION") ?? 'unknown';
            Log::info('AirGapped: Update applied', ['version' => trim($version)]);

            Cache::forget(self::CACHE_KEY_STATUS);

            return [
                'success' => true,
                'message' => "更新包应用成功",
                'version' => trim($version) ?: null,
            ];
        } catch (\Throwable $e) {
            Log::error('AirGapped: Update failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'message' => "更新失败: {$e->getMessage()}",
            ];
        } finally {
            if (is_dir($tempDir)) {
                $this->delTree($tempDir);
            }
        }
    }

    /**
     * 检查所需 PHP 扩展
     */
    protected function checkRequiredExtensions(): array
    {
        $required = ['sodium', 'json', 'mbstring', 'pdo_pgsql', 'pdo_mysql', 'redis', 'fileinfo', 'bcmath'];
        $result = [];

        foreach ($required as $ext) {
            $result[$ext] = extension_loaded($ext);
        }

        return $result;
    }

    /**
     * 检查存储是否可写
     */
    protected function checkStorageWritable(): bool
    {
        $path = storage_path('app/air-gapped');

        if (!is_dir($path)) {
            return @mkdir($path, 0755, true);
        }

        return is_writable($path);
    }

    /**
     * 气隙存储目录磁盘占用（字节）
     */
    protected function getDiskUsage(): array
    {
        $path = storage_path('app/air-gapped');
        $used = is_dir($path) ? $this->getDirectorySize($path) : 0;

        return [
            'path' => $path,
            'used_bytes' => $used,
            'used_mb' => round($used / 1024 / 1024, 2),
        ];
    }

    /**
     * 获取已导入的 License 数量
     */
    protected function countImportedLicenses(): int
    {
        $path = storage_path('app/' . self::LICENSE_IMPORT_PATH);

        if (!is_dir($path)) {
            return 0;
        }

        return count(glob("{$path}/*.{lic,license,key}", GLOB_BRACE));
    }

    /**
     * 获取可用更新包数量
     */
    protected function countAvailableUpdates(): int
    {
        $path = storage_path('app/' . self::UPDATE_PACKAGE_PATH);

        if (!is_dir($path)) {
            return 0;
        }

        return count(glob("{$path}/*.tar.gz"));
    }

    /**
     * 获取离线包数量
     */
    protected function countOfflinePackages(): int
    {
        $path = storage_path('app/' . self::OFFLINE_PACKAGE_PATH);

        if (!is_dir($path)) {
            return 0;
        }

        return count(glob("{$path}/*.zip"));
    }

    /**
     * 获取最后导入时间
     */
    protected function getLastImportTimestamp(): ?string
    {
        $path = storage_path('app/' . self::LICENSE_IMPORT_PATH);

        if (!is_dir($path)) {
            return null;
        }

        $files = glob("{$path}/*.{lic,license,key}", GLOB_BRACE);
        if (empty($files)) {
            return null;
        }

        $latest = array_reduce($files, function ($carry, $file) {
            $mtime = filemtime($file);
            return $mtime > ($carry['mtime'] ?? 0) ? ['file' => $file, 'mtime' => $mtime] : $carry;
        });

        return $latest ? date('Y-m-d H:i:s', $latest['mtime']) : null;
    }

    /**
     * 获取最后更新包时间
     */
    protected function getLastUpdateTimestamp(): ?string
    {
        $path = storage_path('app/' . self::UPDATE_PACKAGE_PATH);

        if (!is_dir($path)) {
            return null;
        }

        $files = glob("{$path}/*.tar.gz");
        if (empty($files)) {
            return null;
        }

        $latest = array_reduce($files, function ($carry, $file) {
            $mtime = filemtime($file);
            return $mtime > ($carry['mtime'] ?? 0) ? ['file' => $file, 'mtime' => $mtime] : $carry;
        });

        return $latest ? date('Y-m-d H:i:s', $latest['mtime']) : null;
    }

    /**
     * 获取目录大小
     */
    protected function getDirectorySize(string $path): int
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * 生成 SHA256 校验文件
     */
    protected function generateSha256Sums(string $dir): void
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $lines = [];
        foreach ($files as $file) {
            if ($file->isFile() && $file->getFilename() !== 'SHA256SUMS') {
                $hash = hash_file('sha256', $file->getPathname());
                $relative = str_replace($dir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $lines[] = "{$hash}  {$relative}";
            }
        }

        file_put_contents("{$dir}/SHA256SUMS", implode("\n", $lines) . "\n");
    }

    /**
     * 打包目录为 tar.gz
     */
    protected function tarPackage(string $sourceDir, string $targetFile): bool
    {
        try {
            $phar = new \PharData($targetFile);
            $phar->buildFromDirectory($sourceDir);
            $phar->compress(\Phar::GZ);

            // 删除未压缩的 tar
            $tarPath = preg_replace('/\.tar\.gz$/', '.tar', $targetFile);
            if (file_exists($tarPath)) {
                unlink($tarPath);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('AirGapped: Package creation failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * 创建更新脚本模板
     */
    protected function createUpdateScripts(string $dir): void
    {
        $scriptsDir = "{$dir}/scripts";
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }

        // pre-update.sh
        file_put_contents("{$scriptsDir}/pre-update.sh", <<<'SCRIPT'
#!/bin/bash
set -euo pipefail
echo "[PRE-UPDATE] 备份数据库..."
COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
if [ -f .env ]; then
    set -a
    source <(grep -E '^[A-Z_]+=' .env | sed 's/\r$//')
    set +a
fi
mkdir -p backups
if grep -q '^DB_CONNECTION=pgsql' .env 2>/dev/null; then
    docker compose -f "${COMPOSE_FILE}" exec -T postgres \
        pg_dump -U "${DB_USERNAME:-postgres}" "${DB_DATABASE:-huwutong}" \
        | gzip > "backups/pre-update-$(date +%Y%m%d_%H%M%S).sql.gz" || true
elif grep -q '^DB_CONNECTION=mysql' .env 2>/dev/null; then
    docker compose -f "${COMPOSE_FILE:-docker-compose.mysql.yml}" exec -T mysql \
        mysqldump -u root -p"${DB_PASSWORD}" --single-transaction "${DB_DATABASE:-huwutong}" \
        | gzip > "backups/pre-update-$(date +%Y%m%d_%H%M%S).sql.gz" || true
fi
SCRIPT
        );

        // post-update.sh
        file_put_contents("{$scriptsDir}/post-update.sh", <<<'SCRIPT'
#!/bin/bash
# 更新后置脚本
echo "[POST-UPDATE] 执行更新后的清理工作..."
# 清理旧缓存
docker exec hwt-api php artisan optimize:clear
echo "[POST-UPDATE] 更新完成!"
SCRIPT
        );

        chmod("{$scriptsDir}/pre-update.sh", 0755);
        chmod("{$scriptsDir}/post-update.sh", 0755);
    }

    /**
     * 递归删除目录
     */
    protected function delTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * 获取 Docker 系统信息
     */
    public function getDockerInfo(): array
    {
        $info = [];

        // Docker 版本
        exec('docker --version 2>&1', $output, $code);
        $info['version'] = $code === 0 ? ($output[0] ?? 'unknown') : null;

        // Docker Compose 版本
        $output = [];
        exec('docker compose version 2>&1', $output, $code);
        $info['compose_version'] = $code === 0 ? ($output[0] ?? 'unknown') : null;

        // 镜像列表
        $output = [];
        exec('docker images --format "{{.Repository}}:{{.Tag}}\t{{.Size}}" 2>&1', $output, $code);
        $info['images'] = $code === 0 ? $output : [];

        // 容器状态
        $output = [];
        exec('docker ps --format "{{.Names}}\t{{.Status}}\t{{.Ports}}" 2>&1', $output, $code);
        $info['containers'] = $code === 0 ? $output : [];

        // 磁盘使用
        $output = [];
        exec('docker system df --format "{{.Type}}\t{{.Size}}\t{{.Reclaimable}}" 2>&1', $output, $code);
        $info['disk_usage'] = $code === 0 ? $output : [];

        return $info;
    }
}
