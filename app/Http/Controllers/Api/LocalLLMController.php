<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LlmProvider;
use App\Services\LocalLLMService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 本地大模型部署控制器 (M3-49)
 */
class LocalLLMController extends Controller
{
    public function __construct(
        protected LocalLLMService $localLLM,
    ) {}

    /**
     * 获取所有本地 LLM 实例状态
     */
    public function status(): JsonResponse
    {
        $this->authorize('viewAny', LlmProvider::class);

        $data = $this->localLLM->getStatus();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GPU 信息
     */
    public function gpuInfo(): JsonResponse
    {
        $gpu = $this->localLLM->getGpuInfo();

        return response()->json(['success' => true, 'data' => $gpu]);
    }

    /**
     * 硬件信息
     */
    public function hardwareInfo(): JsonResponse
    {
        $hw = $this->localLLM->getHardwareInfo();

        return response()->json(['success' => true, 'data' => $hw]);
    }

    /**
     * 下载模型
     */
    public function pullModel(Request $request): JsonResponse
    {
        $this->authorize('create', LlmProvider::class);

        $validated = $request->validate([
            'model_name' => ['required', 'string'],
        ]);

        $result = $this->localLLM->pullModel($validated['model_name']);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * 删除模型
     */
    public function deleteModel(Request $request): JsonResponse
    {
        $this->authorize('delete', LlmProvider::class);

        $validated = $request->validate([
            'model_name' => ['required', 'string'],
        ]);

        $result = $this->localLLM->deleteModel($validated['model_name']);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * 获取部署指引
     */
    public function deploymentGuide(): JsonResponse
    {
        $guide = $this->localLLM->getDeploymentGuide();

        return response()->json(['success' => true, 'data' => $guide]);
    }

    /**
     * 检查特定实例
     */
    public function checkInstance(int $providerId): JsonResponse
    {
        $provider = LlmProvider::findOrFail($providerId);

        $result = $this->localLLM->getStatus();

        $instance = collect($result['instances'])->firstWhere('id', $providerId);

        return response()->json([
            'success' => true,
            'data' => $instance ?? ['error' => 'Instance not found'],
        ]);
    }
}
