<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * 自动备份管理 API
 *
 * M2-24 自动备份 — 备份/恢复/列表/统计
 */
class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService,
    ) {}

    /**
     * 备份统计概览
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->backupService->getStats(),
        ]);
    }

    /**
     * 备份记录列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = BackupRecord::withCount('fileIncludes')
            ->orderBy('created_at', 'desc');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * 执行数据库备份
     */
    public function backupDatabase(Request $request): JsonResponse
    {
        $name = $request->input('name', 'manual_db_' . now()->format('Y-m-d_Hi'));

        try {
            $record = $this->backupService->backupDatabase($name);

            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.database_backup_complete'),
                'data' => $record,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.backup_failed') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 执行文件备份
     */
    public function backupFiles(Request $request): JsonResponse
    {
        $name = $request->input('name', 'manual_files_' . now()->format('Y-m-d_Hi'));

        try {
            $record = $this->backupService->backupFiles($name);

            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.file_backup_complete'),
                'data' => $record,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.backup_failed') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 下载备份文件
     */
    public function download(BackupRecord $backupRecord)
    {
        try {
            $localPath = $this->backupService->download($backupRecord);

            return response()->download($localPath, $backupRecord->file_name, [
                'Content-Type' => 'application/gzip',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.download_failed') . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * 删除备份记录
     */
    public function destroy(BackupRecord $backupRecord): JsonResponse
    {
        // 删除存储文件
        if ($backupRecord->file_path) {
            $disk = $backupRecord->disk;
            if ($disk === 'local') {
                $localPath = storage_path("app/{$backupRecord->file_path}");
                if (file_exists($localPath)) {
                    @unlink($localPath);
                }
            } else {
                \Illuminate\Support\Facades\Storage::disk($disk)->delete($backupRecord->file_path);
            }
        }

        $backupRecord->delete();

        return response()->json([
            'success' => true,
            'message' => __('app.controller_compat.backup_record_deleted'),
        ]);
    }

    /**
     * 恢复数据库备份
     */
    public function restore(BackupRecord $backupRecord): JsonResponse
    {
        try {
            $this->backupService->restoreDatabase($backupRecord);

            return response()->json([
                'success' => true,
                'message' => __('app.controller_compat.database_restored'),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => __('app.controller_compat.restore_failed') . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * 备份配置信息
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'disk' => config('backup.disk'),
                'remote_disk' => config('backup.remote_disk'),
                'database' => [
                    'enabled' => config('backup.database.enabled'),
                    'retention_days' => config('backup.database.retention_days'),
                    'schedule' => config('backup.database.schedule'),
                    'exclude_tables' => config('backup.database.exclude_tables', []),
                ],
                'files' => [
                    'enabled' => config('backup.files.enabled'),
                    'retention_days' => config('backup.files.retention_days'),
                    'schedule' => config('backup.files.schedule'),
                    'include_paths' => config('backup.files.include_paths', []),
                    'max_size_mb' => config('backup.files.max_size_mb'),
                ],
                'cleanup' => [
                    'auto_cleanup' => config('backup.cleanup.auto_cleanup'),
                    'keep_recent' => config('backup.cleanup.keep_recent'),
                ],
            ],
        ]);
    }
}
