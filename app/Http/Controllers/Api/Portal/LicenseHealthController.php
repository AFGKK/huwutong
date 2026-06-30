<?php

namespace App\Http\Controllers\Api\Portal;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\LicenseHealthScoreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * License 健康评分 (M2-110)
 *
 * 客户门户展示 License 健康分、改进建议。
 */
class LicenseHealthController extends Controller
{
    public function __construct(protected LicenseHealthScoreService $healthService) {}

    /**
     * 仪表盘总览（聚合数据）
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customerId = $request->user()->customer_id;

        return ApiResponse::success(
            $this->healthService->getDashboard($tenantId, $customerId)
        );
    }

    /**
     * 所有 License 健康评分列表
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customerId = $request->user()->customer_id;

        return ApiResponse::success(
            $this->healthService->getAllForCustomer($tenantId, $customerId)
        );
    }

    /**
     * 单个 License 健康评分
     */
    public function show(Request $request, int $licenseId): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $result = $this->healthService->getForLicense($licenseId, $tenantId);

        if (!$result) {
            return ApiResponse::error('License 不存在', 404);
        }

        return ApiResponse::success($result);
    }
}
