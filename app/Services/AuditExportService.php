<?php

namespace App\Services;

use App\Models\AuditArchivePolicy;
use App\Models\AuditArchiveRecord;
use App\Models\AuditExportSchedule;
use App\Models\AuditExportTask;
use App\Models\Log;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 审计日志导出增强服务
 *
 * 提供：
 * - 异步导出任务（CSV/JSON）
 * - 定时导出计划（Cron）
 * - 归档策略（自动归档、自动清理）
 * - 文件管理（过期清理）
 */
class AuditExportService
{
    // ─── 导出任务 ───

    public function createExportTask(int $userId, string $name, string $format, array $filters = []): AuditExportTask
    {
        return AuditExportTask::create([
            'user_id' => $userId,
            'name' => $name,
            'format' => in_array($format, ['csv', 'json']) ? $format : 'csv',
            'filters' => $filters,
            'status' => 'pending',
        ]);
    }

    public function getExportTasks(int $userId, array $filters = []): array
    {
        $query = AuditExportTask::where('user_id', $userId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $q = $filters['search'];
            $query->where('name', 'like', "%{$q}%");
        }

        return $query->orderByDesc('created_at')
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100))
            ->toArray();
    }

    public function processExportTask(AuditExportTask $task): void
    {
        $task->update(['status' => 'processing']);

        try {
            $query = $this->buildFilterQuery($task->filters ?? []);
            $format = $task->format;
            $total = $query->count();
            $task->update(['total_records' => $total]);

            if ($total === 0) {
                $task->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'file_name' => "{$task->name}_empty.{$format}",
                ]);
                return;
            }

            $fileName = Str::slug($task->name) . '_' . now()->format('Ymd_His') . ".{$format}";
            $disk = 'local';
            $directory = 'exports/audit/' . date('Y/m');
            Storage::disk($disk)->makeDirectory($directory);

            $filePath = "{$directory}/{$fileName}";

            if ($format === 'csv') {
                $this->exportCsv($query, $disk, $filePath, $task);
            } else {
                $this->exportJson($query, $disk, $filePath, $task);
            }

            $fullPath = Storage::disk($disk)->path($filePath);
            $fileSize = file_exists($fullPath) ? filesize($fullPath) : 0;

            $task->update([
                'status' => 'completed',
                'file_path' => $filePath,
                'file_name' => $fileName,
                'disk' => $disk,
                'file_size_bytes' => $fileSize,
                'exported_records' => $total,
                'completed_at' => now(),
                'expires_at' => now()->addDays(30),
            ]);
        } catch (\Exception $e) {
            $task->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            LogFacade::error("[AuditExport] Task {$task->id} failed: " . $e->getMessage());
        }
    }

    protected function exportCsv($query, string $disk, string $filePath, AuditExportTask $task): void
    {
        $stream = Storage::disk($disk)->writeStream($filePath, '');
        if (!$stream) {
            // Fall back to put
            $handle = fopen('php://temp', 'r+');

            // BOM for Excel UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            // Header
            fputcsv($handle, ['ID', '时间', '类型', '操作', '描述', '用户', 'IP', '租户', '资源类型', '资源ID']);

            $query->chunk(500, function ($logs) use ($handle, $task) {
                foreach ($logs as $log) {
                    fputcsv($handle, [
                        $log->id,
                        $log->created_at?->toDateTimeString(),
                        $log->type,
                        $log->action,
                        $log->description,
                        $log->user?->name ?? $log->user?->email ?? '—',
                        $log->ip_address ?? '—',
                        $log->tenant_id ?? '—',
                        $log->license_id ? 'license' : ($log->customer_id ? 'customer' : '—'),
                        $log->license_id ?? $log->customer_id ?? '—',
                    ]);
                }
            });

            rewind($handle);
            Storage::disk($disk)->put($filePath, stream_get_contents($handle));
            fclose($handle);
        }
    }

    protected function exportJson($query, string $disk, string $filePath, AuditExportTask $task): void
    {
        $first = true;
        $streamPath = Storage::disk($disk)->path($filePath);
        $handle = fopen($streamPath, 'w');
        fwrite($handle, "[\n");

        $query->chunk(500, function ($logs) use ($handle, &$first) {
            foreach ($logs as $log) {
                if (!$first) {
                    fwrite($handle, ",\n");
                }
                fwrite($handle, json_encode([
                    'id' => $log->id,
                    'time' => $log->created_at?->toDateTimeString(),
                    'type' => $log->type,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user?->name ?? $log->user?->email,
                    'user_id' => $log->user_id,
                    'ip' => $log->ip_address,
                    'tenant_id' => $log->tenant_id,
                    'license_id' => $log->license_id,
                    'customer_id' => $log->customer_id,
                    'device_id' => $log->device_id,
                    'payload' => $log->payload,
                ], JSON_UNESCAPED_UNICODE));
                $first = false;
            }
        });

        fwrite($handle, "\n]");
        fclose($handle);
    }

    protected function buildFilterQuery(array $filters): \Illuminate\Database\Eloquent\Builder
    {
        $query = Log::query();

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['action_prefix'])) {
            $query->ofActionPrefix($filters['action_prefix']);
        }
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        if (!empty($filters['license_id'])) {
            $query->where('license_id', $filters['license_id']);
        }
        if (!empty($filters['customer_id'])) {
            $query->where('customer_id', $filters['customer_id']);
        }

        return $query;
    }

    public function deleteExportTask(AuditExportTask $task): void
    {
        if ($task->file_path && Storage::disk($task->disk)->exists($task->file_path)) {
            Storage::disk($task->disk)->delete($task->file_path);
        }
        $task->delete();
    }

    // ─── 定时导出计划 ───

    public function getSchedules(array $filters = []): array
    {
        $query = AuditExportSchedule::query();

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active'] === 'yes');
        }

        return $query->orderByDesc('created_at')
            ->paginate(min((int) ($filters['per_page'] ?? 50), 100))
            ->toArray();
    }

    public function createSchedule(array $data): AuditExportSchedule
    {
        return AuditExportSchedule::create($data);
    }

    public function updateSchedule(AuditExportSchedule $schedule, array $data): AuditExportSchedule
    {
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function deleteSchedule(AuditExportSchedule $schedule): void
    {
        $schedule->delete();
    }

    public function toggleSchedule(AuditExportSchedule $schedule): AuditExportSchedule
    {
        $schedule->update(['is_active' => !$schedule->is_active]);
        return $schedule->fresh();
    }

    /**
     * 执行到期的定时导出任务
     */
    public function processDueSchedules(): array
    {
        $processed = [];

        $dueSchedules = AuditExportSchedule::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('next_run_at')
                    ->orWhere('next_run_at', '<=', now());
            })
            ->get();

        foreach ($dueSchedules as $schedule) {
            try {
                $task = $this->createExportTask(
                    $schedule->user_id ?? 1,
                    "[Auto] {$schedule->name}",
                    $schedule->format,
                    $schedule->filters ?? []
                );

                $this->processExportTask($task);

                // 计算下一次执行时间（简化版 cron 计算）
                $nextRun = $this->calculateNextCronRun($schedule->cron_expression);

                $schedule->update([
                    'last_run_at' => now(),
                    'next_run_at' => $nextRun,
                    'run_count' => $schedule->run_count + 1,
                ]);

                $processed[] = [
                    'schedule_id' => $schedule->id,
                    'task_id' => $task->id,
                    'status' => $task->status,
                ];
            } catch (\Exception $e) {
                LogFacade::error("[AuditExport] Schedule {$schedule->id} failed: " . $e->getMessage());
                $processed[] = [
                    'schedule_id' => $schedule->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $processed;
    }

    protected function calculateNextCronRun(string $expression): ?\DateTime
    {
        // 简化实现：仅处理常见的每小时/每天/每周模式
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) < 5) return now()->addHour();

        [$minute, $hour, $dayOfMonth, $month, $dayOfWeek] = $parts;

        $now = now();

        // 每小时执行 (如 0 * * * *)
        if ($minute !== '*' && $hour === '*' && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            $next = clone $now;
            $next->setMinutes((int) $minute);
            if ($next <= $now) $next->addHour();
            return $next;
        }

        // 每天执行 (如 0 2 * * *)
        if ($hour !== '*' && $dayOfMonth === '*' && $month === '*' && $dayOfWeek === '*') {
            $next = clone $now;
            $next->setTime((int) $hour, (int) $minute, 0);
            if ($next <= $now) $next->addDay();
            return $next;
        }

        // 每周执行 (如 0 2 * * 1)
        if ($dayOfWeek !== '*') {
            $next = clone $now;
            $next->setTime((int) $hour, (int) $minute, 0);
            $targetDay = (int) $dayOfWeek;
            while ((int) $next->format('N') !== $targetDay || $next <= $now) {
                $next->addDay();
            }
            return $next;
        }

        return now()->addDay();
    }

    // ─── 归档策略 ───

    public function getArchivePolicies(): array
    {
        return AuditArchivePolicy::withCount('archiveRecords')->orderBy('type')->get()->all();
    }

    public function createOrUpdateArchivePolicy(array $data): AuditArchivePolicy
    {
        return AuditArchivePolicy::updateOrCreate(
            ['type' => $data['type']],
            $data
        );
    }

    public function updateArchivePolicy(AuditArchivePolicy $policy, array $data): AuditArchivePolicy
    {
        $policy->update($data);
        return $policy->fresh();
    }

    public function deleteArchivePolicy(AuditArchivePolicy $policy): void
    {
        $policy->archiveRecords()->delete();
        $policy->delete();
    }

    /**
     * 执行归档策略检查
     */
    public function executeArchivePolicies(): array
    {
        $results = [];
        $policies = AuditArchivePolicy::where('is_active', true)->get();

        foreach ($policies as $policy) {
            try {
                $result = $this->archiveByPolicy($policy);
                $results[] = $result;
            } catch (\Exception $e) {
                LogFacade::error("[AuditArchive] Policy {$policy->id} failed: " . $e->getMessage());
                $results[] = ['policy_id' => $policy->id, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    protected function archiveByPolicy(AuditArchivePolicy $policy): array
    {
        $type = $policy->type;
        $archiveDate = now()->subDays($policy->archive_after_days);
        $deleteDate = now()->subDays($policy->delete_after_days);

        // 计算需要归档和删除的记录
        $toArchive = Log::where('type', $type)
            ->where('created_at', '<', $archiveDate)
            ->count();

        $toDelete = Log::where('type', $type)
            ->where('created_at', '<', $deleteDate)
            ->count();

        $record = AuditArchiveRecord::create([
            'policy_id' => $policy->id,
            'type' => $type,
            'status' => 'processing',
            'total_logs' => $toArchive,
            'archive_date_from' => null,
            'archive_date_to' => $archiveDate->toDateString(),
        ]);

        $archivedCount = 0;
        $deletedCount = 0;
        $archiveFile = null;

        if ($toArchive > 0) {
            // 生成归档文件
            $fileName = "audit_{$type}_" . $archiveDate->format('Ymd') . '.json';
            $disk = $policy->archive_disk;
            $directory = 'archives/audit/' . date('Y');
            Storage::disk($disk)->makeDirectory($directory);
            $filePath = "{$directory}/{$fileName}";

            $handle = fopen(Storage::disk($disk)->path($filePath), 'w');
            fwrite($handle, "[\n");
            $first = true;

            Log::where('type', $type)
                ->where('created_at', '<', $archiveDate)
                ->chunk(200, function ($logs) use ($handle, &$first, &$archivedCount) {
                    foreach ($logs as $log) {
                        if (!$first) fwrite($handle, ",\n");
                        fwrite($handle, json_encode($log->toArray(), JSON_UNESCAPED_UNICODE));
                        $first = false;
                        $archivedCount++;
                    }
                });

            fwrite($handle, "\n]");
            fclose($handle);

            $archiveFile = $filePath;
        }

        if ($toDelete > 0) {
            Log::where('type', $type)
                ->where('created_at', '<', $deleteDate)
                ->chunk(200, function ($logs) use (&$deletedCount) {
                    $ids = $logs->pluck('id')->toArray();
                    Log::whereIn('id', $ids)->delete();
                    $deletedCount += count($ids);
                });
        }

        $fileSize = $archiveFile ? (file_exists(Storage::disk($policy->archive_disk)->path($archiveFile))
            ? filesize(Storage::disk($policy->archive_disk)->path($archiveFile)) : 0) : 0;

        $record->update([
            'status' => 'completed',
            'archived_logs' => $archivedCount,
            'deleted_logs' => $deletedCount,
            'archive_file' => $archiveFile,
            'file_size_bytes' => $fileSize,
            'executed_at' => now(),
        ]);

        $policy->increment('execution_count');
        $policy->update(['last_executed_at' => now()]);

        return [
            'policy_id' => $policy->id,
            'type' => $type,
            'archived' => $archivedCount,
            'deleted' => $deletedCount,
            'archive_file' => $archiveFile,
        ];
    }

    // ─── 看板 ───

    public function getDashboard(): array
    {
        $totalLogs = Log::count();
        $todayLogs = Log::whereDate('created_at', today())->count();
        $pendingTasks = AuditExportTask::where('status', 'pending')->count();
        $processingTasks = AuditExportTask::where('status', 'processing')->count();
        $totalExports = AuditExportTask::count();
        $activeSchedules = AuditExportSchedule::where('is_active', true)->count();
        $activePolicies = AuditArchivePolicy::where('is_active', true)->count();
        $totalArchived = AuditArchiveRecord::sum('archived_logs');
        $totalDeleted = AuditArchiveRecord::sum('deleted_logs');

        $byType = Log::selectRaw('type, COUNT(*) as cnt')
            ->groupBy('type')->get()->pluck('cnt', 'type')->toArray();

        $recentTasks = AuditExportTask::with('user')
            ->orderByDesc('created_at')->limit(5)->get();

        return [
            'stats' => [
                'total_logs' => $totalLogs,
                'today_logs' => $todayLogs,
                'pending_exports' => $pendingTasks,
                'processing_exports' => $processingTasks,
                'total_exports' => $totalExports,
                'active_schedules' => $activeSchedules,
                'active_archives' => $activePolicies,
                'total_archived' => $totalArchived,
                'total_deleted' => $totalDeleted,
            ],
            'by_type' => $byType,
            'recent_tasks' => $recentTasks,
        ];
    }

    // ─── 文件管理 ───

    public function cleanupExpiredFiles(): int
    {
        $cleaned = 0;

        AuditExportTask::where('status', 'completed')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->chunk(50, function ($tasks) use (&$cleaned) {
                foreach ($tasks as $task) {
                    if ($task->file_path && Storage::disk($task->disk)->exists($task->file_path)) {
                        Storage::disk($task->disk)->delete($task->file_path);
                        $cleaned++;
                    }
                    $task->update(['file_path' => null, 'file_name' => null, 'file_size_bytes' => 0]);
                }
            });

        return $cleaned;
    }
}
