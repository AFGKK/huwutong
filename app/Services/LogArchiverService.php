<?php

namespace App\Services;

use App\Contracts\CloudStorage;
use App\Models\AuditArchivePolicy;
use App\Models\AuditArchiveRecord;
use App\Models\AuditArchiveRestoreRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * M2-73 审计日志归档至低成本存储
 *
 * S3 Glacier Deep Archive / B2 冷存储归档 + 合规长期留存 + 按需取回。
 * 依赖 M1.3-16 CloudStorage 统一适配层。
 */
class LogArchiverService
{
    private const CACHE_KEY_STATS = 'log_archiver:stats';

    public function __construct(
        private readonly CloudStorage $storage,
    ) {}

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $policies = AuditArchivePolicy::withCount('archiveRecords')->get();
        $totalArchived = AuditArchiveRecord::count();
        $totalSize = AuditArchiveRecord::sum('file_size_bytes') ?? 0;
        $pendingRestores = AuditArchiveRestoreRequest::pending()->count();
        $availableRestores = AuditArchiveRestoreRequest::available()->count();

        $byTier = [
            'hot' => 0, 'warm' => 0, 'cold' => 0, 'frozen' => 0,
        ];
        foreach ($policies as $p) {
            $tier = $p->storage_tier ?? 'cold';
            $byTier[$tier] = ($byTier[$tier] ?? 0) + $p->archive_records_count;
        }

        $recentRecords = AuditArchiveRecord::with('policy')
            ->orderByDesc('id')->limit(10)->get();

        return [
            'total_policies' => $policies->count(),
            'total_archived_records' => $totalArchived,
            'total_size_bytes' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'pending_restores' => $pendingRestores,
            'available_restores' => $availableRestores,
            'by_tier' => $byTier,
            'recent_records' => $recentRecords,
        ];
    }

    /**
     * 获取归档策略列表
     */
    public function getPolicies(): array
    {
        return AuditArchivePolicy::withCount('archiveRecords')
            ->orderBy('type')
            ->get()
            ->toArray();
    }

    /**
     * 创建或更新归档策略
     */
    public function upsertPolicy(array $data): AuditArchivePolicy
    {
        $policy = AuditArchivePolicy::updateOrCreate(
            ['id' => $data['id'] ?? null] ?: ['type' => $data['type']],
            [
                'name' => $data['name'] ?? $data['type'],
                'type' => $data['type'],
                'archive_after_days' => $data['archive_after_days'] ?? 90,
                'delete_after_days' => $data['delete_after_days'] ?? 365,
                'archive_disk' => $data['archive_disk'] ?? config('log-archiver.strategy.default_archive_disk', 's3'),
                'storage_tier' => $data['storage_tier'] ?? 'cold',
                'compress_archive' => $data['compress_archive'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'description' => $data['description'] ?? null,
            ]
        );

        return $policy->fresh();
    }

    /**
     * 执行归档
     */
    public function archive(AuditArchivePolicy $policy): AuditArchiveRecord
    {
        $type = $policy->type;
        $archiveAfterDays = $policy->archive_after_days;
        $archiveDate = now()->subDays($archiveAfterDays);
        $tier = $policy->storage_tier ?? 'cold';
        $tierConfig = config("log-archiver.tiers.{$tier}", []);
        $storageClass = $tierConfig['storage_class'] ?? 'STANDARD';
        $compress = $policy->compress_archive;

        // 统计需要归档的日志
        $logModel = $this->getLogModelForType($type);
        $toArchive = $logModel::where('created_at', '<', $archiveDate)->count();

        if ($toArchive === 0) {
            return AuditArchiveRecord::create([
                'policy_id' => $policy->id,
                'type' => $type,
                'status' => 'skipped',
                'total_logs' => 0,
                'archived_logs' => 0,
                'archive_date_to' => $archiveDate->toDateString(),
                'storage_class' => $storageClass,
            ]);
        }

        $record = AuditArchiveRecord::create([
            'policy_id' => $policy->id,
            'type' => $type,
            'status' => 'processing',
            'total_logs' => $toArchive,
            'archive_date_from' => null,
            'archive_date_to' => $archiveDate->toDateString(),
            'storage_class' => $storageClass,
        ]);

        try {
            $result = DB::transaction(function () use ($logModel, $policy, $type, $archiveDate, $compress, $storageClass, $record) {
                $timestamp = now()->format('Ymd_His');
                $fileName = "audit_{$type}_{$timestamp}";
                $tempPath = storage_path("app/temp_archives/{$fileName}.json");
                $archiveName = $compress ? "{$fileName}.json.gz" : "{$fileName}.json";

                // 确保临时目录存在
                $tempDir = dirname($tempPath);
                if (!is_dir($tempDir)) {
                    mkdir($tempDir, 0755, true);
                }

                $handle = fopen($tempPath, 'w');
                fwrite($handle, "[\n");
                $first = true;
                $archivedCount = 0;

                $logModel::where('created_at', '<', $archiveDate)
                    ->chunk(config('log-archiver.strategy.chunk_size', 500), function ($logs) use ($handle, &$first, &$archivedCount) {
                        foreach ($logs as $log) {
                            if (!$first) fwrite($handle, ",\n");
                            fwrite($handle, json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));
                            $first = false;
                            $archivedCount++;
                        }
                    });

                fwrite($handle, "\n]");
                fclose($handle);

                // 计算校验和
                $checksum = hash_file('sha256', $tempPath);

                // 压缩
                $finalPath = $tempPath;
                if ($compress) {
                    $gzPath = "{$tempPath}.gz";
                    $gzHandle = gzopen($gzPath, 'w9');
                    gzwrite($gzHandle, file_get_contents($tempPath));
                    gzclose($gzHandle);
                    unlink($tempPath);
                    $finalPath = $gzPath;
                }

                // 归档路径
                $prefix = config('log-archiver.strategy.path_prefix', 'archives/audit');
                $year = now()->format('Y');
                $month = now()->format('m');
                $storagePath = "{$prefix}/{$year}/{$month}/{$archiveName}";

                // 上传到云存储
                $fileContents = file_get_contents($finalPath);
                $this->storage->upload($storagePath, $fileContents, [
                    'visibility' => 'private',
                    'storage_class' => $storageClass,
                    'content_type' => $compress ? 'application/gzip' : 'application/json',
                ]);

                $fileSize = filesize($finalPath);
                unlink($finalPath);

                // 删除已归档的原始日志
                $deletedCount = $logModel::where('created_at', '<', $archiveDate)->delete();

                $record->update([
                    'status' => 'completed',
                    'archived_logs' => $archivedCount,
                    'deleted_logs' => $deletedCount,
                    'archive_file' => $storagePath,
                    'file_size_bytes' => $fileSize,
                    'checksum' => $checksum,
                    'is_encrypted' => config('log-archiver.strategy.encrypt_archive', false),
                    'is_compressed' => $compress,
                    'original_filename' => $archiveName,
                    'executed_at' => now(),
                ]);

                // 更新策略统计
                $policy->increment('execution_count');
                $policy->increment('total_archived_count', $archivedCount);
                $policy->increment('archived_size_bytes', $fileSize);
                $policy->update(['last_executed_at' => now()]);

                return $record->fresh();
            });

            return $result;
        } catch (\Throwable $e) {
            Log::error("[LogArchiver] Archive failed for policy {$policy->id}: {$e->getMessage()}");
            $record->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            return $record->fresh();
        }
    }

    /**
     * 取回归档文件
     */
    public function requestRestore(AuditArchiveRecord $record, string $reason, ?int $userId = null): AuditArchiveRestoreRequest
    {
        // 检查是否有待处理的取回请求
        $existing = AuditArchiveRestoreRequest::where('archive_record_id', $record->id)
            ->whereIn('status', ['pending', 'restoring'])
            ->first();

        if ($existing) {
            throw new \RuntimeException('该归档已有待处理的取回请求');
        }

        $tempDays = config('log-archiver.retrieval.temp_storage_days', 3);
        $expiresDays = config('log-archiver.retrieval.request_expires_days', 7);

        $request = AuditArchiveRestoreRequest::create([
            'archive_record_id' => $record->id,
            'requester_type' => 'admin',
            'reason' => $reason,
            'status' => 'pending',
            'requested_at' => now(),
            'available_until' => now()->addDays($tempDays),
            'expires_at' => now()->addDays($expiresDays),
            'requested_by' => $userId,
        ]);

        return $request;
    }

    /**
     * 执行取回
     */
    public function executeRestore(AuditArchiveRestoreRequest $request): AuditArchiveRestoreRequest
    {
        $record = $request->archiveRecord;
        if (!$record || !$record->archive_file) {
            $request->update(['status' => 'failed', 'error_message' => '归档文件不存在']);
            return $request->fresh();
        }

        try {
            $request->update(['status' => 'restoring']);

            // 从云存储下载
            $fileContent = $this->storage->download($record->archive_file);

            // 保存到临时路径
            $tempDir = storage_path('app/temp_restores');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempFileName = 'restore_' . $record->id . '_' . time() . '_' . ($record->original_filename ?? 'archive.json');
            if ($record->is_compressed && !str_ends_with($tempFileName, '.gz')) {
                $tempFileName .= '.gz';
            }
            $tempPath = "{$tempDir}/{$tempFileName}";
            file_put_contents($tempPath, $fileContent);

            // 验证校验和
            if ($record->checksum) {
                $actualChecksum = hash_file('sha256', $tempPath);
                if ($actualChecksum !== $record->checksum) {
                    unlink($tempPath);
                    throw new \RuntimeException("校验和不匹配: 期望 {$record->checksum}, 实际 {$actualChecksum}");
                }
            }

            $request->update([
                'status' => 'available',
                'temp_file_path' => $tempPath,
            ]);
        } catch (\Throwable $e) {
            Log::error("[LogArchiver] Restore failed: {$e->getMessage()}");
            $request->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $request->fresh();
    }

    /**
     * 获取归档记录列表
     */
    public function getRecords(array $filters = [], int $perPage = 20): array
    {
        $query = AuditArchiveRecord::with('policy');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['storage_class'])) {
            $query->where('storage_class', $filters['storage_class']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 获取取回请求列表
     */
    public function getRestoreRequests(array $filters = [], int $perPage = 20): array
    {
        $query = AuditArchiveRestoreRequest::with(['archiveRecord', 'requester:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['archive_record_id'])) {
            $query->where('archive_record_id', $filters['archive_record_id']);
        }

        $query->orderByDesc('id');

        return $query->paginate(min($perPage, 100))->toArray();
    }

    /**
     * 取消取回请求
     */
    public function cancelRestoreRequest(int $id): ?AuditArchiveRestoreRequest
    {
        $request = AuditArchiveRestoreRequest::findOrFail($id);
        if (in_array($request->status, ['available', 'expired', 'cancelled'])) {
            return null;
        }

        // 清理临时文件
        if ($request->temp_file_path && file_exists($request->temp_file_path)) {
            unlink($request->temp_file_path);
        }

        $request->update(['status' => 'cancelled', 'temp_file_path' => null]);
        return $request->fresh();
    }

    /**
     * 处理过期请求
     */
    public function processExpiredRequests(): int
    {
        $expired = AuditArchiveRestoreRequest::whereIn('status', ['pending', 'restoring', 'available'])
            ->where('expires_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($expired as $request) {
            if ($request->temp_file_path && file_exists($request->temp_file_path)) {
                unlink($request->temp_file_path);
            }
            $request->update(['status' => 'expired', 'temp_file_path' => null]);
            $count++;
        }

        return $count;
    }

    /**
     * 获取存储层级配置
     */
    public function getTierConfig(): array
    {
        return config('log-archiver.tiers', []);
    }

    /**
     * 获取归档统计
     */
    public function getArchiveStats(): array
    {
        $totalRecords = AuditArchiveRecord::count();
        $totalSize = AuditArchiveRecord::sum('file_size_bytes') ?? 0;
        $totalLogs = AuditArchiveRecord::sum('archived_logs') ?? 0;

        $monthlyStats = AuditArchiveRecord::selectRaw(
            db_date_format('created_at', '%Y-%m').' as month, COUNT(*) as count, SUM(file_size_bytes) as size, SUM(archived_logs) as logs'
        )->groupBy('month')->orderByDesc('month')->limit(12)->get();

        return [
            'total_records' => $totalRecords,
            'total_size_bytes' => $totalSize,
            'total_size_human' => $this->formatBytes($totalSize),
            'total_archived_logs' => $totalLogs,
            'monthly' => $monthlyStats,
        ];
    }

    /**
     * 格式化字节
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
    }

    /**
     * 获取日志模型类名
     */
    private function getLogModelForType(string $type): string
    {
        return match ($type) {
            'audit' => \App\Models\AuditLog::class,
            'security' => \App\Models\SecurityLog::class,
            'error' => \App\Models\ErrorLog::class,
            'system' => \App\Models\SystemLog::class,
            default => \App\Models\AuditLog::class,
        };
    }
}
