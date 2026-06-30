<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\RevenueForecastAiService;
use App\Services\ChurnPredictionAiService;
use App\Services\AdaptiveSecurityAiService;
use App\Services\PricingOptimizerAiService;
use App\Services\SdkConfigGeneratorAiService;
use App\Services\TestCaseGeneratorAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI 智能套件 (M2-43 ~ M2-48)
 */
class AiIntelligenceController extends Controller
{
    public function __construct(
        protected RevenueForecastAiService $revenueForecast,
        protected ChurnPredictionAiService $churnPrediction,
        protected AdaptiveSecurityAiService $adaptiveSecurity,
        protected PricingOptimizerAiService $pricingOptimizer,
        protected SdkConfigGeneratorAiService $sdkGenerator,
        protected TestCaseGeneratorAiService $testGenerator,
    ) {}

    /**
     * M2-43 AI 收入预测
     */
    public function revenueForecast(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->revenueForecast->forecast($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * M2-44 AI 客户流失预警
     */
    public function churnPrediction(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->churnPrediction->analyze($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * M2-45 AI 自适应安全阈值 - 获取推荐配置
     */
    public function adaptiveSecurity(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->adaptiveSecurity->getRecommendedConfig($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * M2-45 清除自适应安全缓存
     */
    public function clearAdaptiveCache(Request $request): JsonResponse
    {
        $this->adaptiveSecurity->clearCache($request->user()->tenant_id);
        return ApiResponse::success(null, '缓存已清除');
    }

    /**
     * M2-46 AI 智能定价建议
     */
    public function pricingSuggestions(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->pricingOptimizer->suggestPricing($request->user()->tenant_id, $request->all())
        );
    }

    /**
     * M2-47 AI SDK 配置生成
     */
    public function generateSdkConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'nullable|string',
            'framework' => 'nullable|string',
            'package_manager' => 'nullable|string',
            'license_key' => 'nullable|string|max:500',
            'api_url' => 'nullable|url|max:500',
        ]);

        return ApiResponse::success(
            $this->sdkGenerator->generate($validated)
        );
    }

    /**
     * M2-47 获取支持的 SDK 选项
     */
    public function sdkOptions(): JsonResponse
    {
        return ApiResponse::success(
            $this->sdkGenerator->getSupportedOptions()
        );
    }

    /**
     * M2-48 AI 测试用例生成 - 单个端点
     */
    public function generateTests(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_id' => 'required|integer|exists:api_doc_endpoints,id',
            'language' => 'nullable|string',
            'framework' => 'nullable|string',
        ]);

        return ApiResponse::success(
            $this->testGenerator->generateForEndpoint($validated['endpoint_id'], $validated)
        );
    }

    /**
     * M2-48 批量生成测试
     */
    public function generateTestsBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint_ids' => 'required|array',
            'endpoint_ids.*' => 'integer|exists:api_doc_endpoints,id',
            'language' => 'nullable|string',
            'framework' => 'nullable|string',
        ]);

        return ApiResponse::success(
            $this->testGenerator->generateBatch($validated['endpoint_ids'], $validated)
        );
    }

    /**
     * M2-48 生成所有端点测试
     */
    public function generateAllTests(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->testGenerator->generateAll($request->all())
        );
    }

    /**
     * M2-48 获取支持的测试框架
     */
    public function testFrameworks(): JsonResponse
    {
        return ApiResponse::success(
            $this->testGenerator->getSupportedFrameworks()
        );
    }
}
