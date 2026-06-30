<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ComparePageService;
use Illuminate\Http\JsonResponse;

/**
 * 竞品对比页控制器 (M2-100)
 */
class ComparePageController extends Controller
{
    public function __construct(
        protected ComparePageService $compareService,
    ) {}

    /**
     * 获取完整对比数据（公开）
     */
    public function comparison(): JsonResponse
    {
        return ApiResponse::success($this->compareService->getComparison());
    }

    /**
     * 获取优势摘要（公开）
     */
    public function advantages(): JsonResponse
    {
        return ApiResponse::success([
            'advantages' => $this->compareService->getAdvantages(),
        ]);
    }

    /**
     * 获取竞品列表（公开）
     */
    public function competitors(): JsonResponse
    {
        return ApiResponse::success([
            'competitors' => $this->compareService->getCompetitors(),
        ]);
    }
}
