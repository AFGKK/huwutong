<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Log;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * 审计日志列表（分页+筛选）
     *
     * GET /api/audit-logs
     * ?filter[action]=license.status_changed
     * &filter[type]=audit
     * &filter[license_id]=1
     * &filter[customer_id]=1
     * &date_from=2026-01-01&date_to=2026-06-30
     * &sort=-created_at
     */
    public function index(Request $request): JsonResponse
    {
        $query = Log::query()->with(['user', 'license', 'customer', 'device']);

        // 租户隔离
        if ($tenantId = $request->user()?->tenant_id) {
            $query->where('tenant_id', $tenantId);
        }

        // 筛选
        if ($request->has('filter')) {
            foreach ($request->input('filter') as $field => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }
                if (in_array($field, ['action', 'type', 'license_id', 'customer_id', 'device_id', 'user_id'])) {
                    $query->where($field, $value);
                }
            }
        }

        // 日期范围
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to') . ' 23:59:59');
        }

        // 搜索（描述模糊搜索）
        if ($request->filled('search')) {
            $query->where('description', 'like', '%' . $request->input('search') . '%');
        }

        // 排序
        $sortField = $request->input('sort', '-created_at');
        if (str_starts_with($sortField, '-')) {
            $query->orderBy(substr($sortField, 1), 'desc');
        } else {
            $query->orderBy($sortField, 'asc');
        }

        $perPage = min((int) $request->input('per_page', 15), 100);

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
        ];

        return ApiResponse::success($stats);
    }
}
