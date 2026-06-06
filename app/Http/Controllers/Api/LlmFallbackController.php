<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LlmFallbackService;
use Illuminate\Http\JsonResponse;

class LlmFallbackController extends Controller
{
    public function __construct(
        protected LlmFallbackService $fallbackService,
    ) {}

    /**
     * 获取熔断器状态概览
     */
    public function status(): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\LlmProvider::class);

        return response()->json([
            'success' => true,
            'data' => $this->fallbackService->getCircuitStatus(),
        ]);
    }

    /**
     * 重置所有熔断器
     */
    public function reset(): JsonResponse
    {
        $this->authorize('update', \App\Models\LlmProvider::class);

        $this->fallbackService->resetAllCircuits();

        return response()->json([
            'success' => true,
            'message' => '所有 LLM 熔断器已重置',
        ]);
    }
}
