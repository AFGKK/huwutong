<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    /**
     * 审计日志列表（分页+高级筛选）
     *
     * GET /api/audit-logs
     * ?filter[action]=license.status_changed
     * &filter[type]=audit
     * &filter[action_prefix]=license.
     * &filter[license_id]=1
     * &filter[customer_id]=1
     * &filter[user_id]=1
     * &filter[payload->license_key]=abc
     * &date_from=2026-01-01&date_to=2026-06-30
     * &search=关键词
     * &sort=-created_at
     */
    public function index(Request $request): JsonResponse
    {
        $query = Log::query()->with(['user', 'license', 'customer', 'device']);

        // 租户隔离
        if ($tenantId = $request->user()?->tenant_id) {
            $query->byTenant($tenantId);
        }

        // 筛选
        if ($request->has('filter')) {
            foreach ($request->input('filter') as $field => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                match (true) {
                    $field === 'action' => $query->ofAction($value),
                    $field === 'type' => $query->ofType($value),
                    $field === 'action_prefix' => $query->ofActionPrefix($value),
                    in_array($field, ['license_id', 'customer_id', 'device_id', 'product_id', 'user_id'])
                        => $query->where($field, $value),
                    str_starts_with($field, 'payload->') => $query->wherePayload(substr($field, 8), $value),
                    default => null,
                };
            }
        }

        // 日期范围
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // 全文搜索
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        if (str_starts_with($sortField, '-')) {
            $query->orderBy(substr($sortField, 1), 'desc');
        } else {
            $query->orderBy($sortField, 'asc');
        }

        $perPage = min((int) $request->input('per_page', config('audit.pagination.per_page', 20)), config('audit.pagination.max_per_page', 100));

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 审计日志详情
     *
     * GET /api/audit-logs/{id}
     */
    public function show(int $id): JsonResponse
    {
        $log = Log::with(['user', 'license', 'customer', 'device'])->findOrFail($id);

        return ApiResponse::success($log);
    }

    /**
     * 获取审计统计概览
     *
     * GET /api/audit-logs/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $query = Log::query();

        if ($tenantId = $request->user()?->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        $stats = [
            'total' => (clone $query)->count(),
            'by_type' => (clone $query)->selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
            'today' => (clone $query)->whereDate('created_at', today())->count(),
            'top_actions' => (clone $query)->selectRaw('action, count(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(10)
                ->pluck('count', 'action'),
            'by_date' => (clone $query)
                ->selectRaw('DATE(created_at) as date, count(*) as count')
                ->where('created_at', '>=', now()->subDays(30))
                ->groupBy('date')
                ->orderBy('date')
                ->get()
                ->toArray(),
        ];

        return ApiResponse::success($stats);
    }

    /**
     * 导出审计日志
     *
     * GET /api/audit-logs/export?format=csv|json
     * 支持与 index 相同的所有筛选参数
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $format = $request->input('format', 'csv');
        if (! in_array($format, config('audit.export.allowed_formats', ['csv', 'json']))) {
            return ApiResponse::error('INVALID_FORMAT', '不支持的导出格式', 422);
        }

        $maxRows = config('audit.export.max_rows', 50000);

        $query = Log::query()->with(['user', 'license', 'customer', 'device']);

        // 租户隔离
        if ($tenantId = $request->user()?->tenant_id) {
            $query->byTenant($tenantId);
        }

        // 复用 index 的筛选逻辑
        if ($request->has('filter')) {
            foreach ($request->input('filter') as $field => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                match (true) {
                    $field === 'action' => $query->ofAction($value),
                    $field === 'type' => $query->ofType($value),
                    $field === 'action_prefix' => $query->ofActionPrefix($value),
                    in_array($field, ['license_id', 'customer_id', 'device_id', 'product_id', 'user_id'])
                        => $query->where($field, $value),
                    str_starts_with($field, 'payload->') => $query->wherePayload(substr($field, 8), $value),
                    default => null,
                };
            }
        }

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $query->orderBy('created_at', 'desc');

        if ($format === 'json') {
            return $this->exportJson($query, $maxRows);
        }

        return $this->exportCsv($query, $maxRows);
    }

    /**
     * 导出为 CSV（StreamedResponse，支持大文件）
     */
    protected function exportCsv($query, int $maxRows): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($query, $maxRows) {
            if (config('audit.export.csv_encode_bom', true)) {
                echo "\xEF\xBB\xBF";
            }

            $delimiter = config('audit.export.csv_delimiter', ',');
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'ID', '时间', '类型', '动作', '描述',
                '用户', '用户ID', '租户ID',
                'License ID', '客户 ID', '设备 ID', '产品 ID',
                'IP 地址', 'User-Agent',
                'Payload',
            ], $delimiter);

            $count = 0;
            $query->chunk(500, function ($logs) use ($output, $delimiter, &$count, $maxRows) {
                foreach ($logs as $log) {
                    if ($count >= $maxRows) {
                        return false; // 停止 chunk
                    }
                    fputcsv($output, [
                        $log->id,
                        $log->created_at,
                        $log->type,
                        $log->action,
                        $log->description,
                        $log->user?->name ?? $log->user?->email ?? '-',
                        $log->user_id,
                        $log->tenant_id,
                        $log->license_id,
                        $log->customer_id,
                        $log->device_id,
                        $log->product_id,
                        $log->ip_address,
                        $log->user_agent,
                        json_encode($log->payload, JSON_UNESCAPED_UNICODE),
                    ], $delimiter);
                    $count++;
                }
                return true;
            });

            fclose($output);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="audit-logs-' . now()->format('Ymd-His') . '.csv"');

        return $response;
    }

    /**
     * 导出为 JSON
     */
    protected function exportJson($query, int $maxRows): JsonResponse
    {
        $logs = $query->limit($maxRows)->get()->map(function ($log) {
            return [
                'id' => $log->id,
                'created_at' => $log->created_at,
                'type' => $log->type,
                'action' => $log->action,
                'description' => $log->description,
                'user' => $log->user?->name ?? $log->user?->email ?? null,
                'user_id' => $log->user_id,
                'tenant_id' => $log->tenant_id,
                'license_id' => $log->license_id,
                'customer_id' => $log->customer_id,
                'device_id' => $log->device_id,
                'product_id' => $log->product_id,
                'ip_address' => $log->ip_address,
                'user_agent' => $log->user_agent,
                'payload' => $log->payload,
            ];
        });

        return response()->json([
            'exported_at' => now()->toIso8601String(),
            'total' => $logs->count(),
            'data' => $logs,
        ], 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => 'attachment; filename="audit-logs-' . now()->format('Ymd-His') . '.json"',
        ]);
    }
}
