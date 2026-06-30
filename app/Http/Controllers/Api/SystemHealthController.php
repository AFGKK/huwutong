<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SystemHealthThreshold;
use App\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SystemHealthController extends Controller
{
    public function __construct(
        protected SystemHealthService $healthService,
    ) {}

    /**
     * 系统健康仪表盘
     * GET /api/admin/system-health/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->healthService->getDashboard());
    }

    /**
     * 实时健康检查
     * GET /api/admin/system-health/check
     */
    public function check(): JsonResponse
    {
        return ApiResponse::success($this->healthService->performFullCheck());
    }

    /**
     * 健康趋势数据
     * GET /api/admin/system-health/trend?period=24h|7d|30d|90d
     */
    public function trend(Request $request): JsonResponse
    {
        $period = $request->input('period', '24h');
        if (!in_array($period, ['24h', '7d', '30d', '90d'])) {
            $period = '24h';
        }
        return ApiResponse::success($this->healthService->getTrend($period));
    }

    /**
     * 手动创建健康快照
     * POST /api/admin/system-health/snapshot
     */
    public function snapshot(): JsonResponse
    {
        $log = $this->healthService->snapshot();
        return ApiResponse::created($log, '健康快照已记录');
    }

    /**
     * 获取阈值配置
     * GET /api/admin/system-health/thresholds
     */
    public function thresholds(): JsonResponse
    {
        return ApiResponse::success(
            SystemHealthThreshold::where('is_active', true)->get()
        );
    }

    /**
     * 更新阈值配置
     * PUT /api/admin/system-health/thresholds/{id}
     */
    public function updateThreshold(Request $request, int $id): JsonResponse
    {
        $threshold = SystemHealthThreshold::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'warning_threshold' => 'nullable|numeric',
            'critical_threshold' => 'nullable|numeric',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $threshold->update($validator->validated());
        return ApiResponse::success($threshold->fresh(), '阈值已更新');
    }

    /**
     * 获取失败任务列表
     * GET /api/admin/system-health/failed-jobs
     */
    public function failedJobs(): JsonResponse
    {
        try {
            $jobs = \Illuminate\Support\Facades\DB::table('failed_jobs')
                ->orderByDesc('failed_at')
                ->limit(50)
                ->get();
            return ApiResponse::success($jobs);
        } catch (\Throwable $e) {
            return ApiResponse::success([]);
        }
    }
}
