<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\AIOpsAnalystService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI 运营分析助手控制器 (M2-42)
 *
 * 提供自然语言运营分析能力，基于 Text-to-SQL 安全护栏。
 */
class AIOpsAnalystController extends Controller
{
    public function __construct(
        protected AIOpsAnalystService $aiOpsService,
    ) {
    }

    /**
     * 看板概览
     */
    public function dashboard(): JsonResponse
    {
        $metrics = $this->aiOpsService->getDashboardOverview();
        return ApiResponse::success($metrics);
    }

    /**
     * 预置分析模板
     */
    public function templates(Request $request): JsonResponse
    {
        $category = $request->input('category');
        $templates = $this->aiOpsService->getTemplatesByCategory($category);
        return ApiResponse::success($templates);
    }

    /**
     * 执行预置模板分析
     */
    public function runTemplate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => 'required|string',
            'days' => 'nullable|integer|min:1|max:365',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $result = $this->aiOpsService->queryTemplate($data['key'], $data);

        if (!$result['success']) {
            return ApiResponse::error('ANALYSIS_FAILED', $result['error'], 400);
        }

        return ApiResponse::success($result);
    }

    /**
     * 自然语言分析提问
     */
    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => 'required|string|max:2000',
        ]);

        $context = [
            'user' => [
                'user_id' => $request->user()?->id,
                'tenant_id' => $request->user()?->tenant_id,
                'role' => $request->user()?->getRoleNames()?->first(),
            ],
        ];

        $result = $this->aiOpsService->askQuestion($data['question'], $context);

        if (!$result['success']) {
            $code = isset($result['llm_error']) ? 503 : (isset($result['blocked']) ? 403 : 400);
            return ApiResponse::error('ANALYSIS_FAILED', $result['error'], $code);
        }

        return ApiResponse::success($result);
    }
}
