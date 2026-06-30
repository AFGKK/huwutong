<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\WorkflowDefinition;
use App\Models\WorkflowInstance;
use App\Models\WorkflowStepExecution;
use App\Services\TemporalWorkflowService;
use App\Workflows\WorkflowEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * M2-137 Temporal 工作流引擎 - 监控与管理
 */
class WorkflowController extends Controller
{
    public function __construct(
        protected WorkflowEngine           $workflowEngine,
        protected TemporalWorkflowService  $temporalService,
    ) {}

    /**
     * 仪表盘总览
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->temporalService->dashboard());
    }

    /**
     * 工作流趋势
     */
    public function trend(Request $request): JsonResponse
    {
        $days = $request->input('days', 14);
        return ApiResponse::success($this->temporalService->trend((int) $days));
    }

    /**
     * 工作流定义列表
     */
    public function definitions(): JsonResponse
    {
        $definitions = WorkflowDefinition::all(['id', 'name', 'description', 'is_active', 'steps_definition']);
        return ApiResponse::success($definitions);
    }

    /**
     * 工作流实例列表
     */
    public function instances(Request $request): JsonResponse
    {
        $query = WorkflowInstance::withCount('stepExecutions');

        if ($request->filled('workflow_name')) {
            $query->where('workflow_name', $request->input('workflow_name'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($q) use ($s) {
                $q->where('id', $s)
                  ->orWhere('workflow_name', 'like', "%{$s}%")
                  ->orWhere('error_message', 'like', "%{$s}%");
            });
        }

        $instances = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return ApiResponse::paginated($instances);
    }

    /**
     * 工作流详情（Temporal queryWorkflow）
     */
    public function show(WorkflowInstance $instance): JsonResponse
    {
        $detail = $this->temporalService->queryWorkflow($instance->id, 'status');
        return ApiResponse::success($detail);
    }

    /**
     * 工作流进度详情（Gantt 视图数据）
     */
    public function progress(WorkflowInstance $instance): JsonResponse
    {
        $progress = $this->temporalService->queryWorkflow($instance->id, 'progress');
        return ApiResponse::success($progress);
    }

    /**
     * Saga 状态查询
     */
    public function sagaStatus(WorkflowInstance $instance): JsonResponse
    {
        $saga = $this->temporalService->queryWorkflow($instance->id, 'saga');
        return ApiResponse::success($saga);
    }

    /**
     * 取消工作流 (Temporal: terminateWorkflow)
     */
    public function cancel(WorkflowInstance $instance): JsonResponse
    {
        $this->temporalService->terminateWorkflow($instance->id, '管理后台取消');
        return ApiResponse::success(null, '工作流已取消');
    }

    /**
     * 重试失败的工作流
     */
    public function retry(WorkflowInstance $instance): JsonResponse
    {
        if (! $instance->isFailed()) {
            return ApiResponse::error('WORKFLOW_NOT_FAILED', '只能重试失败的工作流', 422);
        }

        $instance->update([
            'status' => 'running',
            'retry_count' => 0,
            'error_message' => null,
            'completed_at' => null,
        ]);

        $instance->timers()->where('fired', false)->delete();
        $instance->stepExecutions()->where('status', 'failed')->delete();

        $this->workflowEngine->continue($instance->fresh());

        return ApiResponse::success(null, '工作流已重新开始');
    }

    /**
     * 批量重试失败工作流
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'nullable|array',
            'ids.*' => 'integer',
            'workflow_name' => 'nullable|string',
        ]);

        $results = $this->temporalService->batchRetry(
            $validated['ids'] ?? [],
            $validated['workflow_name'] ?? null,
        );

        return ApiResponse::success($results, '批量重试完成');
    }

    /**
     * 统计总览
     */
    public function stats(): JsonResponse
    {
        $dashboard = $this->temporalService->dashboard();
        return ApiResponse::success($dashboard['stats']);
    }

    /**
     * 失败的步骤列表
     */
    public function failedSteps(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            $this->temporalService->failedSteps((int) $request->input('per_page', 20))
        );
    }

    /**
     * 工作流配置信息
     */
    public function config(): JsonResponse
    {
        return ApiResponse::success([
            'driver' => config('temporal.engine.driver', 'temporal'),
            'namespace' => config('temporal.engine.temporal.namespace', 'huwutong'),
            'task_queue' => config('temporal.engine.temporal.task_queue', 'license-workflows'),
            'max_concurrent' => config('temporal.execution.max_concurrent', 10),
            'heartbeat_seconds' => config('temporal.execution.heartbeat_seconds', 30),
            'timeout_minutes' => config('temporal.execution.timeout_minutes', 60),
            'workflows' => config('temporal.workflows', []),
        ]);
    }

    /**
     * 启动工作流（手动触发）
     */
    public function startWorkflow(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workflow_name' => 'required|string',
            'input' => 'nullable|array',
        ]);

        $instance = $this->temporalService->startWorkflow(
            $validated['workflow_name'],
            $validated['input'] ?? [],
        );

        return ApiResponse::success($instance, '工作流已启动');
    }
}
