<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditArchivePolicy;
use App\Models\AuditExportSchedule;
use App\Models\AuditExportTask;
use App\Models\Log;
use App\Services\AuditExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditExportController extends Controller
{
    public function __construct(
        protected AuditExportService $auditExportService
    ) {}

    // ─── 看板 ───

    public function dashboard()
    {
        return ApiResponse::success($this->auditExportService->getDashboard());
    }

    // ─── 导出任务 ───

    public function exportTasks(Request $request)
    {
        $userId = $request->user()->id;
        return ApiResponse::success(
            $this->auditExportService->getExportTasks(
                $userId,
                $request->only(['status', 'search', 'per_page'])
            )
        );
    }

    public function createExportTask(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'format' => 'nullable|string|in:csv,json',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $task = $this->auditExportService->createExportTask(
            $request->user()->id,
            $request->input('name'),
            $request->input('format', 'csv'),
            $request->input('filters', [])
        );

        // 异步处理
        $this->auditExportService->processExportTask($task);

        return ApiResponse::success($task->fresh(), 201);
    }

    public function showExportTask(int $id)
    {
        $task = AuditExportTask::with('user')->findOrFail($id);
        return ApiResponse::success($task);
    }

    public function deleteExportTask(AuditExportTask $auditExportTask)
    {
        $this->auditExportService->deleteExportTask($auditExportTask);
        return ApiResponse::success(['deleted' => true]);
    }

    public function downloadExportFile(AuditExportTask $auditExportTask)
    {
        if (!$auditExportTask->file_path || !Storage::disk($auditExportTask->disk)->exists($auditExportTask->file_path)) {
            return ApiResponse::error('FILE_NOT_FOUND', '文件不存在或已过期', 404);
        }

        $fileName = $auditExportTask->file_name ?? ('export.' . $auditExportTask->format);

        return Storage::disk($auditExportTask->disk)->download(
            $auditExportTask->file_path,
            $fileName
        );
    }

    // ─── 实时导出（流式下载） ───

    public function streamExport(Request $request)
    {
        $format = $request->input('format', 'csv');
        $filters = $request->input('filters', []);
        $maxRows = min((int) ($request->input('max_rows', 50000)), 100000);

        if (!in_array($format, ['csv', 'json'])) {
            return ApiResponse::success(['error' => '不支持的文件格式'], 422);
        }

        $query = Log::query();

        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['action'])) $query->where('action', $filters['action']);
        if (!empty($filters['action_prefix'])) $query->ofActionPrefix($filters['action_prefix']);
        if (!empty($filters['tenant_id'])) $query->where('tenant_id', $filters['tenant_id']);
        if (!empty($filters['user_id'])) $query->where('user_id', $filters['user_id']);
        if (!empty($filters['date_from'])) $query->whereDate('created_at', '>=', $filters['date_from']);
        if (!empty($filters['date_to'])) $query->whereDate('created_at', '<=', $filters['date_to']);
        if (!empty($filters['search'])) $query->search($filters['search']);

        $total = $query->count();
        $query->limit($maxRows);

        $fileName = 'audit_export_' . now()->format('Ymd_His') . '.' . $format;

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ];

            return response()->stream(function () use ($query) {
                $output = fopen('php://output', 'w');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, ['ID', '时间', '类型', '操作', '描述', '用户', 'IP', '租户ID', '资源ID']);

                $query->chunk(500, function ($logs) use ($output) {
                    foreach ($logs as $log) {
                        fputcsv($output, [
                            $log->id,
                            $log->created_at?->toDateTimeString(),
                            $log->type,
                            $log->action,
                            $log->description,
                            $log->user?->name ?? $log->user?->email ?? 'N/A',
                            $log->ip_address ?? 'N/A',
                            $log->tenant_id ?? 'N/A',
                            $log->license_id ?? $log->customer_id ?? 'N/A',
                        ]);
                    }
                });

                fclose($output);
            }, 200, $headers);
        }

        // JSON
        $headers = [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
        ];

        return response()->stream(function () use ($query) {
            $output = fopen('php://output', 'w');
            fwrite($output, "{\n\"exported_at\": \"" . now()->toIso8601String() . "\",\n");
            fwrite($output, "\"total\": {$query->count()},\n");
            fwrite($output, "\"logs\": [\n");
            $first = true;

            $query->chunk(500, function ($logs) use ($output, &$first) {
                foreach ($logs as $log) {
                    if (!$first) fwrite($output, ",\n");
                    fwrite($output, json_encode([
                        'id' => $log->id,
                        'time' => $log->created_at?->toDateTimeString(),
                        'type' => $log->type,
                        'action' => $log->action,
                        'description' => $log->description,
                        'user' => $log->user?->name,
                        'user_id' => $log->user_id,
                        'ip' => $log->ip_address,
                        'tenant_id' => $log->tenant_id,
                        'license_id' => $log->license_id,
                        'customer_id' => $log->customer_id,
                        'payload' => $log->payload,
                    ], JSON_UNESCAPED_UNICODE));
                    $first = false;
                }
            });

            fwrite($output, "\n]\n}");
            fclose($output);
        }, 200, $headers);
    }

    // ─── 定时导出计划 ───

    public function schedules(Request $request)
    {
        return ApiResponse::success(
            $this->auditExportService->getSchedules($request->only(['is_active', 'per_page']))
        );
    }

    public function storeSchedule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:200',
            'cron_expression' => 'required|string|max:100',
            'format' => 'nullable|string|in:csv,json',
            'filters' => 'nullable|array',
            'notification_emails' => 'nullable|array',
            'notification_emails.*' => 'email',
            'max_records' => 'nullable|integer|min:100|max:100000',
            'compression' => 'nullable|string|in:none,gzip,zip',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['user_id'] = $request->user()->id;

        return ApiResponse::success($this->auditExportService->createSchedule($data), 201);
    }

    public function updateSchedule(Request $request, AuditExportSchedule $auditExportSchedule)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'cron_expression' => 'nullable|string|max:100',
            'format' => 'nullable|string|in:csv,json',
            'filters' => 'nullable|array',
            'notification_emails' => 'nullable|array',
            'notification_emails.*' => 'email',
            'max_records' => 'nullable|integer|min:100|max:100000',
            'compression' => 'nullable|string|in:none,gzip,zip',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->auditExportService->updateSchedule($auditExportSchedule, $request->all()));
    }

    public function destroySchedule(AuditExportSchedule $auditExportSchedule)
    {
        $this->auditExportService->deleteSchedule($auditExportSchedule);
        return ApiResponse::success(['deleted' => true]);
    }

    public function toggleSchedule(AuditExportSchedule $auditExportSchedule)
    {
        return ApiResponse::success($this->auditExportService->toggleSchedule($auditExportSchedule));
    }

    // ─── 归档策略 ───

    public function archivePolicies()
    {
        return ApiResponse::success($this->auditExportService->getArchivePolicies());
    }

    public function upsertArchivePolicy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|string|in:audit,security,error,system',
            'name' => 'required|string|max:200',
            'archive_after_days' => 'nullable|integer|min:1|max:3650',
            'delete_after_days' => 'nullable|integer|min:1|max:3650',
            'archive_disk' => 'nullable|string',
            'compress_archive' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        if (!isset($data['delete_after_days']) || $data['delete_after_days'] <= $data['archive_after_days']) {
            $data['delete_after_days'] = $data['archive_after_days'] + 90;
        }

        return ApiResponse::success($this->auditExportService->createOrUpdateArchivePolicy($data));
    }

    public function updateArchivePolicy(Request $request, AuditArchivePolicy $auditArchivePolicy)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'archive_after_days' => 'nullable|integer|min:1|max:3650',
            'delete_after_days' => 'nullable|integer|min:1|max:3650',
            'archive_disk' => 'nullable|string',
            'compress_archive' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->auditExportService->updateArchivePolicy($auditArchivePolicy, $request->all()));
    }

    public function destroyArchivePolicy(AuditArchivePolicy $auditArchivePolicy)
    {
        $this->auditExportService->deleteArchivePolicy($auditArchivePolicy);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 归档记录 ───

    public function archiveRecords(Request $request)
    {
        $query = \App\Models\AuditArchiveRecord::with('policy');

        if ($request->filled('policy_id')) {
            $query->where('policy_id', $request->input('policy_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return ApiResponse::success(
            $query->orderByDesc('created_at')
                ->paginate(min((int) ($request->input('per_page', 20)), 100))
                ->toArray()
        );
    }
}
