<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChaosExperiment;
use App\Services\ChaosEngineeringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 混沌工程控制器 (M3-80)
 */
class ChaosEngineeringController extends Controller
{
    public function __construct(
        protected ChaosEngineeringService $chaosService,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        $data = $this->chaosService->getDashboard();

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * 实验列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = ChaosExperiment::query();

        if ($request->type) $query->byType($request->type);
        if ($request->status) $query->byStatus($request->status);

        $experiments = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'data' => $experiments]);
    }

    /**
     * 创建实验
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'experiment_type' => ['required', 'string', 'in:' . implode(',', array_keys(ChaosExperiment::TYPES))],
            'target_service' => ['required', 'string'],
            'target_namespace' => ['nullable', 'string'],
            'fault_config' => ['nullable', 'array'],
            'scope' => ['nullable', 'string'],
            'blast_radius' => ['nullable', 'string', 'in:low,medium,high,critical'],
            'scheduled_at' => ['nullable', 'date'],
            'expected_behavior' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $experiment = $this->chaosService->createExperiment($validated);

        return response()->json([
            'success' => true,
            'data' => $experiment,
            'message' => __('app.controller_compat.chaos_engineering_msg_70'),
        ], 201);
    }

    /**
     * 实验详情
     */
    public function show(int $id): JsonResponse
    {
        $experiment = ChaosExperiment::findOrFail($id);

        return response()->json(['success' => true, 'data' => $experiment]);
    }

    /**
     * 执行实验
     */
    public function execute(int $id): JsonResponse
    {
        $result = $this->chaosService->executeExperiment($id);

        return response()->json([
            'success' => true,
            'data' => $result,
            'message' => __('app.controller_compat.chaos_engineering_msg_94'),
        ]);
    }

    /**
     * 回滚实验
     */
    public function rollback(int $id): JsonResponse
    {
        $experiment = $this->chaosService->rollbackExperiment($id);

        return response()->json([
            'success' => true,
            'data' => $experiment,
            'message' => __('app.controller_compat.chaos_engineering_msg_108'),
        ]);
    }

    /**
     * 删除实验
     */
    public function destroy(int $id): JsonResponse
    {
        ChaosExperiment::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => __('app.controller_compat.chaos_engineering_msg_119')]);
    }

    /**
     * 韧性评分卡
     */
    public function scorecard(): JsonResponse
    {
        $scorecard = $this->chaosService->getResilienceScorecard();

        return response()->json(['success' => true, 'data' => $scorecard]);
    }

    /**
     * GameDay 计划
     */
    public function gameday(): JsonResponse
    {
        $plan = $this->chaosService->getGameDayPlan();

        return response()->json(['success' => true, 'data' => $plan]);
    }

    /**
     * 改进追踪
     */
    public function improvements(): JsonResponse
    {
        $items = $this->chaosService->getImprovements();

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * 实验类型列表
     */
    public function types(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ChaosExperiment::TYPES,
        ]);
    }
}
