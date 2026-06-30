<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Workflows\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 工作流可视化面板 (M2-137)
 *
 * 提供工作流运行状态、统计、实例详情等 API。
 */
class WorkflowDashboardController extends Controller
{
    protected WorkflowEngine $engine;

    public function __construct(WorkflowEngine $engine)
    {
        $this->engine = $engine;
    }

    /**
     * 工作流概览统计
     */
    public function overview(): JsonResponse
    {
        $definitions = WorkflowDefinition::where('is_active', true)->get();
        $instances = WorkflowInstance::selectRaw("
            workflow_name,
            status,
            COUNT(*) as count
        ")->groupBy('workflow_name', 'status')->get()->groupBy('workflow_name');

        $workflows = [];
        foreach ($definitions as $def) {
            $stats = $instances->get($def->name, collect());
            $workflows[] = [
                'name' => $def->name,
                'description' => $def->description,
                'steps' => $def->steps(),
                'is_active' => $def->is_active,
                'stats' => [
                    'running' => (int) $stats->where('status', 'running')->sum('count'),
                    'completed' => (int) $stats->where('status', 'completed')->sum('count'),
                    'failed' => (int) $stats->where('status', 'failed')->sum('count'),
                    'compensating' => (int) $stats->where('status', 'compensating')->sum('count'),
                    'cancelled' => (int) $stats->where('status', 'cancelled')->sum('count'),
                    'total' => (int) $stats->sum('count'),
                ],
            ];
        }

        $totalRunning = WorkflowInstance::whereIn('status', ['running', 'compensating'])->count();
        $totalCompleted = WorkflowInstance::where('status', 'completed')->count();
        $totalFailed = WorkflowInstance::where('status', 'failed')->count();
        $totalCancelled = WorkflowInstance::where('status', 'cancelled')->count();

        return ApiResponse::success([
            'workflows' => $workflows,
            'summary' => [
                'running' => $totalRunning,
                'completed' => $totalCompleted,
                'failed' => $totalFailed,
                'cancelled' => $totalCancelled,
                'total' => WorkflowInstance::count(),
            ],
        ]);
    }

    /**
     * 工作流实例列表
     */
    public function instances(Request $request): JsonResponse
    {
        $query = WorkflowInstance::with('workflowable');

        if ($request->filled('workflow_name')) {
            $query->where('workflow_name', $request->workflow_name);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $instances = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20))
            ->through(fn (WorkflowInstance $instance) => [
                'id' => $instance->id,
                'workflow_name' => $instance->workflow_name,
                'status' => $instance->status,
                'current_step' => $instance->current_step,
                'retry_count' => $instance->retry_count,
                'error_message' => $instance->error_message,
                'started_at' => $instance->started_at?->toIso8601String(),
                'completed_at' => $instance->completed_at?->toIso8601String(),
                'next_retry_at' => $instance->next_retry_at?->toIso8601String(),
                'workflowable_type' => $instance->workflowable_type,
                'workflowable_id' => $instance->workflowable_id,
                'created_at' => $instance->created_at?->toIso8601String(),
            ]);

        return ApiResponse::paginated($instances);
    }

    /**
     * 工作流实例详情（含步骤执行记录和时间线）
     */
    public function show(int $id): JsonResponse
    {
        $instance = WorkflowInstance::with(['stepExecutions', 'timers'])->findOrFail($id);

        $status = $this->engine->getStatus($instance);

        // 额外信息
        $status['workflowable_type'] = $instance->workflowable_type;
        $status['workflowable_id'] = $instance->workflowable_id;
        $status['context_snapshot'] = $instance->context;
        $status['timers'] = $instance->timers->map(fn ($t) => [
            'id' => $t->id,
            'type' => $t->timer_type,
            'fire_at' => $t->fire_at?->toIso8601String(),
            'fired' => $t->fired,
            'payload' => $t->payload,
        ]);

        return ApiResponse::success($status);
    }

    /**
     * 取消工作流
     */
    public function cancel(int $id): JsonResponse
    {
        $instance = WorkflowInstance::findOrFail($id);

        if (! $instance->isRunning()) {
            return ApiResponse::error('INVALID_STATE', '工作流不在运行状态', 422);
        }

        $this->engine->cancel($instance);

        return ApiResponse::success(null, '工作流已取消');
    }

    /**
     * 工作流定义列表
     */
    public function definitions(): JsonResponse
    {
        $definitions = WorkflowDefinition::all()->map(fn ($def) => [
            'name' => $def->name,
            'description' => $def->description,
            'steps' => $def->steps(),
            'is_active' => $def->is_active,
        ]);

        return ApiResponse::success($definitions);
    }

    /**
     * 工作流运行统计（按小时聚合）
     */
    public function stats(): JsonResponse
    {
        $now = now();

        // 按状态的实例数
        $statusCounts = WorkflowInstance::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // 按工作流名称的执行情况
        $byWorkflow = WorkflowInstance::selectRaw(
            "workflow_name,
             COUNT(*) as total,
             SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
             SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
             SUM(CASE WHEN status IN ('running','compensating') THEN 1 ELSE 0 END) as running"
        )->groupBy('workflow_name')->get();

        // 最近失败
        $recentFailures = WorkflowInstance::where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'workflow_name' => $i->workflow_name,
                'current_step' => $i->current_step,
                'error_message' => $i->error_message,
                'started_at' => $i->started_at?->toIso8601String(),
            ]);

        return ApiResponse::success([
            'status_counts' => $statusCounts,
            'by_workflow' => $byWorkflow,
            'recent_failures' => $recentFailures,
            'total' => WorkflowInstance::count(),
            'last_24h' => WorkflowInstance::where('created_at', '>=', $now->subDay())->count(),
            'success_rate' => $this->calculateSuccessRate(),
        ]);
    }

    /**
     * 计算工作流成功率
     */
    protected function calculateSuccessRate(): ?float
    {
        $total = WorkflowInstance::whereIn('status', ['completed', 'failed'])->count();
        if ($total === 0) {
            return null;
        }

        $completed = WorkflowInstance::where('status', 'completed')->count();
        return round(($completed / $total) * 100, 1);
    }

    /**
     * 工作流定义中的步骤定义
     */
    public function stepDefinitions(): JsonResponse
    {
        $stepDefs = WorkflowDefinition::all()->map(function ($def) {
            $steps = [];
            foreach ($def->steps() as $i => $stepDef) {
                $step = \App\Workflows\WorkflowEngine::getStep($def->name, $stepDef['name']);
                $steps[] = [
                    'index' => $i,
                    'name' => $stepDef['name'],
                    'description' => $step?->description() ?? $stepDef['name'],
                    'max_retries' => $step?->maxRetries(),
                    'timeout' => $step?->timeout(),
                ];
            }

            return [
                'name' => $def->name,
                'description' => $def->description,
                'steps' => $steps,
            ];
        });

        return ApiResponse::success($stepDefs);
    }
}
