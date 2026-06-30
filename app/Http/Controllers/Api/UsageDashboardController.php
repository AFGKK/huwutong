<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\UsageDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 客户用量看板 (M2-97)
 *
 * 客户门户内查看 API 调用趋势、端点分布、功能使用排行
 */
class UsageDashboardController extends Controller
{
    public function __construct(protected UsageDashboardService $usageDashboard) {}

    /**
     * 概览统计（门户卡片数据）
     */
    public function overview(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->usageDashboard->getOverview(
                $request->user()->tenant_id,
                $request->user()->customer_id
            )
        );
    }

    /**
     * API 调用趋势
     */
    public function apiCalls(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:month,last_month,quarter,7d,30d',
            'days' => 'nullable|integer|min:1|max:90',
        ]);

        $data = $this->usageDashboard->getApiCalls(
            $request->user()->tenant_id,
            $request->user()->customer_id,
            $validated['period'] ?? 'month',
            $validated['days'] ?? 7
        );

        return ApiResponse::success($data);
    }

    /**
     * 各端点调用统计
     */
    public function endpointStats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:month,last_month,quarter',
        ]);

        return ApiResponse::success(
            $this->usageDashboard->getEndpointStats(
                $request->user()->tenant_id,
                $request->user()->customer_id,
                $validated['period'] ?? 'month'
            )
        );
    }

    /**
     * 功能使用排行
     */
    public function features(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'nullable|string|in:month,last_month,quarter',
        ]);

        return ApiResponse::success(
            $this->usageDashboard->getFeatureUsage(
                $request->user()->tenant_id,
                $request->user()->customer_id,
                $validated['period'] ?? 'month'
            )
        );
    }
}
