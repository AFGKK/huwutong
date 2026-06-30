<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AnomalyDetectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-04 异常检测控制器
 */
class AnomalyDetectionController extends Controller
{
    public function __construct(
        protected AnomalyDetectionService $anomalyService,
    ) {}

    /**
     * 仪表盘统计
     */
    public function dashboard(): JsonResponse
    {
        return response()->json($this->anomalyService->getStats());
    }

    /**
     * 异常列表
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 20), 100);
        return response()->json($this->anomalyService->getList($request->all(), $perPage));
    }

    /**
     * 执行全部检测
     */
    public function detect(): JsonResponse
    {
        $results = $this->anomalyService->detectAll();
        return response()->json(['results' => $results]);
    }

    /**
     * 标记为已处理
     */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $this->anomalyService->resolve($id, $request->input('note'));
        return response()->json(['message' => '异常已标记为已处理']);
    }

    /**
     * 获取检测规则
     */
    public function rules(): JsonResponse
    {
        return response()->json(['data' => $this->anomalyService->getRules()]);
    }
}
