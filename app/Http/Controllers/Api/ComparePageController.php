<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\ComparePageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     * 获取可编辑原始配置（管理端）
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'config' => $this->compareService->rawConfig(),
            'source' => $this->compareService->getComparison()['source'] ?? 'config',
        ]);
    }

    /**
     * 保存对比页配置（管理端）
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'competitors' => 'sometimes|array',
            'dimensions' => 'sometimes|array',
            'comparison_data' => 'sometimes|array',
            'seo' => 'sometimes|array',
            'seo.title' => 'nullable|string|max:200',
            'seo.description' => 'nullable|string|max:500',
            'seo.keywords' => 'nullable|string|max:300',
        ]);

        $saved = $this->compareService->update($validated);

        return ApiResponse::success([
            'config' => $saved,
            'comparison' => $this->compareService->getComparison(),
        ], __('app.compare_page.comparison_saved'));
    }

    /**
     * 从 config 文件重置到数据库
     */
    public function resetFromConfig(): JsonResponse
    {
        $saved = $this->compareService->syncFromConfigFile(true);

        return ApiResponse::success([
            'config' => $saved,
            'comparison' => $this->compareService->getComparison(),
        ], __('app.compare_page.reset_to_default'));
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
