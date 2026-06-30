<?php

namespace App\Services;

use App\Models\BackupRecord;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 自动备份服务
 *
 * M2-24 核心服务 — 数据库/文件备份、恢复、清理、远程上传
 */
class BackupService
{
    const CACHE_LOCK_KEY = 'backup:running_lock';

    /**
     * 执行数据库备份
     */
    public function backupDatabase(?string $customName = null): BackupRecord
    {
        $this->acquireLock();

        $record = BackupRecord::create([
            'name' => $customName ?? 'db_backup_' . now()->format('Y-m-d_Hi'),
            'type' => 'database',
            'status' => 'running',
            'database' => config('database.connections.mysql.database'),
            'excluded_tables' => config('backup.database.exclude_tables', []),
        ]);

        $startTime = microtime(true);

        try {
            $config = config('backup.database');
            $connection = config('database.connections.mysql');

            $host = $connection['host'];
            $port = $connection['port'] ?? 3306;
            $db = $connection['database'];
            $user = $connection['username'];
            $pass = $connection['password'];

            // 排除表
            $excludeTables = $config['exclude_tables'] ?? [];
            $ignoreArgs = '';
            foreach ($excludeTables as $table) {
                if (! empty($table)) {
                    $ignoreArgs .= " --ignore-table={$db}.{$table}";
                }
            }

            // 压缩等级
            $compress = (int) ($config['compression_level'] ?? 6);

            // 生成临时文件名
            $timestamp = now()->format('Ymd_His');
            $fileName = "db_backup_{$db}_{$timestamp}.sql.gz";
            $tempPath = storage_path("app/backups/{$fileName}");

            // 确保备份目录存在
            $backupDir = dirname($tempPath);
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // 构建 mysqldump 命令
            $cmd = sprintf(
                '%s --host=%s --port=%s --user=%s --password=%s %s --single-transaction --quick --skip-lock-tables %s | gzip -%d > %s',
                escapeshellcmd($config['mysqldump_path']),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($db),
                $ignoreArgs,
                $compress,
                escapeshellarg($tempPath)
            );

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('mysqldump 执行失败: ' . implode("\n", $output));
            }

            if (! file_exists($tempPath)) {
                throw new \RuntimeException('备份文件未生成');
            }

            $fileSize = filesize($tempPath);
            $checksum = hash_file('sha256', $tempPath);

            // 上传到远程存储
            $disk = config('backup.disk', 'local');
            $remotePath = "backups/{$fileName}";

            if ($disk !== 'local') {
                Storage::disk($disk)->put($remotePath, fopen($tempPath, 'r'));
                // 上传后删除本地临时文件
                @unlink($tempPath);
                $storedPath = $remotePath;
            } else {
                $storedPath = "backups/{$fileName}";
            }

            $duration = (int) (microtime(true) - $startTime);

            $record->update([
                'status' => 'completed',
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'disk' => $disk,
                'checksum' => $checksum,
                'duration_seconds' => $duration,
                'completed_at' => now(),
                'expires_at' => now()->addDays((int) config('backup.database.retention_days', 30)),
            ]);

            $this->cleanupExpired('database');

            return $record->fresh();

        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'duration_seconds' => (int) (microtime(true) - $startTime),
            ]);

            Log::error('数据库备份失败', [
                'record_id' => $record->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 执行文件备份
     */
    public function backupFiles(?string $customName = null): BackupRecord
    {
        $this->acquireLock();

        $record = BackupRecord::create([
            'name' => $customName ?? 'files_backup_' . now()->format('Y-m-d_Hi'),
            'type' => 'files',
            'status' => 'running',
        ]);

        $startTime = microtime(true);

        try {
            $config = config('backup.files');
            $includePaths = $config['include_paths'] ?? [];
            $excludePatterns = $config['exclude_patterns'] ?? [];
            $maxSizeBytes = (($config['max_size_mb'] ?? 500) * 1024 * 1024);

            $projectRoot = base_path();
            $timestamp = now()->format('Ymd_His');
            $fileName = "files_backup_{$timestamp}.tar.gz";
            $tempPath = storage_path("app/backups/{$fileName}");

            $backupDir = dirname($tempPath);
            if (! is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // 构建 tar 排除参数
            $excludeArgs = '';
            foreach ($excludePatterns as $pattern) {
                $excludeArgs .= ' --exclude=' . escapeshellarg($pattern);
            }

            // 构建 tar 包含路径
            $pathArgs = '';
            $included = [];
            foreach ($includePaths as $path) {
                $fullPath = $projectRoot . '/' . $path;
                if (file_exists($fullPath)) {
                    $pathArgs .= ' ' . escapeshellarg($path);
                    $included[] = $path;
                }
            }

            if (empty($included)) {
                throw new \RuntimeException('没有可备份的文件目录');
            }

            // 执行 tar 打包
            $cmd = sprintf(
                'tar -czf %s %s -C %s %s',
                escapeshellarg($tempPath),
                $excludeArgs,
                escapeshellarg($projectRoot),
                $pathArgs
            );

            $output = [];
            $returnCode = 0;
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('tar 打包执行失败');
            }

            if (! file_exists($tempPath)) {
                throw new \RuntimeException('备份文件未生成');
            }

            $fileSize = filesize($tempPath);

            if ($maxSizeBytes > 0 && $fileSize > $maxSizeBytes) {
                @unlink($tempPath);
                throw new \RuntimeException('备份文件超过大小限制: ' . round($fileSize / 1024 / 1024, 2) . 'MB / ' . $config['max_size_mb'] . 'MB');
            }

            $checksum = hash_file('sha256', $tempPath);

            // 上传
            $disk = config('backup.disk', 'local');
            $remotePath = "backups/{$fileName}";

            if ($disk !== 'local') {
                Storage::disk($disk)->put($remotePath, fopen($tempPath, 'r'));
                @unlink($tempPath);
                $storedPath = $remotePath;
            } else {
                $storedPath = "backups/{$fileName}";
            }

            $duration = (int) (microtime(true) - $startTime);

            $record->update([
                'status' => 'completed',
                'file_path' => $storedPath,
                'file_name' => $fileName,
                'file_size' => $fileSize,
                'disk' => $disk,
                'checksum' => $checksum,
                'duration_seconds' => $duration,
                'completed_at' => now(),
                'expires_at' => now()->addDays((int) config('backup.files.retention_days', 14)),
            ]);

            // 记录备份的目录
            foreach ($included as $path) {
                $record->fileIncludes()->create([
                    'path' => $path,
                    'file_count' => $this->countFiles(base_path($path)),
                ]);
            }

            $this->cleanupExpired('files');

            return $record->fresh();

        } catch (\Throwable $e) {
            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
                'duration_seconds' => (int) (microtime(true) - $startTime),
            ]);

            Log::error('文件备份失败', ['record_id' => $record->id, 'error' => $e->getMessage()]);

            throw $e;
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * 下载备份文件到本地
     */
    public function download(BackupRecord $record): string
    {
        if ($record->status !== 'completed') {
            throw new \RuntimeException('备份未完成');
        }

        $disk = $record->disk;

        if ($disk === 'local') {
            $localPath = storage_path("app/{$record->file_path}");
            if (! file_exists($localPath)) {
                throw new \RuntimeException('备份文件不存在');
            }
            return $localPath;
        }

        // 远程存储，下载到临时目录
        $tempPath = storage_path("app/backups/downloads/{$record->file_name}");
        $dir = dirname($tempPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $content = Storage::disk($disk)->get($record->file_path);
        file_put_contents($tempPath, $content);

        return $tempPath;
    }

    /**
     * 清理过期备份
     */
    public function cleanupExpired(string $type = 'database'): int
    {
        $count = 0;
        $keepRecent = (int) config('backup.cleanup.keep_recent', 5);

        // 获取过期的备份
        $expired = BackupRecord::byType($type)
            ->completed()
            ->where('expires_at', '<=', now())
            ->orderBy('completed_at', 'desc')
            ->get();

        // 保留最近的 N 个（即使过期）
        $keep = $expired->take($keepRecent);
        $toDelete = $expired->slice($keepRecent);

        foreach ($toDelete as $record) {
            try {
                // 删除存储文件
                if ($record->file_path) {
                    $disk = $record->disk;
                    if ($disk === 'local') {
                        $localPath = storage_path("app/{$record->file_path}");
                        if (file_exists($localPath)) {
                            @unlink($localPath);
                        }
                    } else {
                        Storage::disk($disk)->delete($record->file_path);
                    }
                }

                // 更新状态或软删除
                $record->update(['status' => 'expired']);
                $count++;
            } catch (\Throwable $e) {
                Log::warning('清理备份记录失败', [
                    'record_id' => $record->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * 恢复数据库备份
     */
    public function restoreDatabase(BackupRecord $record): bool
    {
        if ($record->type !== 'database' || $record->status !== 'completed') {
            throw new \RuntimeException('备份不可用');
        }

        $filePath = $this->getLocalPath($record);

        if (! $filePath || ! file_exists($filePath)) {
            throw new \RuntimeException('备份文件不存在');
        }

        $connection = config('database.connections.mysql');
        $host = $connection['host'];
        $port = $connection['port'] ?? 3306;
        $db = $connection['database'];
        $user = $connection['username'];
        $pass = $connection['password'];

        // 解压并导入
        $cmd = sprintf(
            'gunzip -c %s | mysql --host=%s --port=%s --user=%s --password=%s %s',
            escapeshellarg($filePath),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($pass),
            escapeshellarg($db)
        );

        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \RuntimeException('数据库恢复失败: ' . implode("\n", $output));
        }

        Log::warning('数据库已从备份恢复', [
            'backup_id' => $record->id,
            'backup_name' => $record->name,
        ]);

        return true;
    }

    /**
     * 获取备份统计概览
     */
    public function getStats(): array
    {
        $dbCompleted = BackupRecord::byType('database')->completed()->count();
        $dbTotal = BackupRecord::byType('database')->count();
        $dbSize = BackupRecord::byType('database')->completed()->sum('file_size');

        $fileCompleted = BackupRecord::byType('files')->completed()->count();
        $fileTotal = BackupRecord::byType('files')->count();
        $fileSize = BackupRecord::byType('files')->completed()->sum('file_size');

        $disk = config('backup.disk', 'local');
        $diskUsage = 0;
        if ($disk === 'local') {
            $diskUsage = $this->getDirectorySize(storage_path('app/backups'));
        }

        $lastDbBackup = BackupRecord::byType('database')->completed()->latest()->first();
        $lastFileBackup = BackupRecord::byType('files')->completed()->latest()->first();

        return [
            'database' => [
                'total' => $dbTotal,
                'completed' => $dbCompleted,
                'failed' => BackupRecord::byType('database')->where('status', 'failed')->count(),
                'total_size' => $dbSize,
                'total_size_formatted' => $this->formatBytes($dbSize),
                'last_backup' => $lastDbBackup ? [
                    'id' => $lastDbBackup->id,
                    'name' => $lastDbBackup->name,
                    'size' => $lastDbBackup->formatted_size,
                    'completed_at' => $lastDbBackup->completed_at,
                    'duration' => $lastDbBackup->duration_seconds,
                ] : null,
            ],
            'files' => [
                'total' => $fileTotal,
                'completed' => $fileCompleted,
                'failed' => BackupRecord::byType('files')->where('status', 'failed')->count(),
                'total_size' => $fileSize,
                'total_size_formatted' => $this->formatBytes($fileSize),
                'last_backup' => $lastFileBackup ? [
                    'id' => $lastFileBackup->id,
                    'name' => $lastFileBackup->name,
                    'size' => $lastFileBackup->formatted_size,
                    'completed_at' => $lastFileBackup->completed_at,
                    'duration' => $lastFileBackup->duration_seconds,
                ] : null,
            ],
            'disk_usage' => $this->formatBytes($diskUsage),
            'disk' => $disk,
        ];
    }

    /**
     * 获取备份记录的本地路径
     */
    protected function getLocalPath(BackupRecord $record): ?string
    {
        if ($record->disk === 'local') {
            $path = storage_path("app/{$record->file_path}");
            if (file_exists($path)) {
                return $path;
            }
        }

        // 远程存储下载到临时目录
        try {
            $content = Storage::disk($record->disk)->get($record->file_path);
            $tempPath = storage_path("app/backups/restore/{$record->file_name}");
            $dir = dirname($tempPath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($tempPath, $content);
            return $tempPath;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 获取锁（防止并发备份）
     */
    protected function acquireLock(): void
    {
        $locked = Cache::lock(self::CACHE_LOCK_KEY, 600)->get();
        if (! $locked) {
            throw new \RuntimeException('备份任务已在运行中');
        }
    }

    protected function releaseLock(): void
    {
        Cache::lock(self::CACHE_LOCK_KEY)->forceRelease();
    }

    protected function countFiles(string $dir): int
    {
        $count = 0;
        if (! is_dir($dir)) {
            return 0;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }
        return $count;
    }

    protected function getDirectorySize(string $dir): int
    {
        $size = 0;
        if (! is_dir($dir)) {
            return 0;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        return $size;
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
