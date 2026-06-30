<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\QueueMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 队列死信监控面板控制器 (M2-82)
 */
class QueueMonitorController extends Controller
{
    public function __construct(
        protected QueueMonitorService $monitor,
    ) {}

    /**
     * 仪表盘
     * GET /api/admin/queue-monitor/dashboard
     */
    public function dashboard(): JsonResponse
    {
        return ApiResponse::success($this->monitor->getDashboard());
    }

    /**
     * 失败任务列表
     * GET /api/admin/queue-monitor/failed-jobs
     */
    public function failedJobs(Request $request): JsonResponse
    {
        $filters = $request->only(['queue', 'search']);
        return ApiResponse::success($this->monitor->getFailedJobs($filters));
    }

    /**
     * 死信队列列表
     * GET /api/admin/queue-monitor/dead-letters
     */
    public function deadLetters(Request $request): JsonResponse
    {
        $filters = $request->only(['queue', 'status']);
        return ApiResponse::success($this->monitor->getDeadLetters($filters));
    }

    /**
     * 重试死信
     * POST /api/admin/queue-monitor/dead-letters/{id}/retry
     */
    public function retryDeadLetter(int $id): JsonResponse
    {
        $letter = $this->monitor->retryDeadLetter($id);
        return ApiResponse::success($letter, '任务已重新加入队列');
    }

    /**
     * 批量重试死信
     * POST /api/admin/queue-monitor/dead-letters/batch-retry
     */
    public function batchRetry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:50',
            'ids.*' => 'integer',
        ]);

        $results = $this->monitor->batchRetryDeadLetters($validated['ids']);
        return ApiResponse::success($results, "重试完成: {$results['success']} 成功, {$results['failed']} 失败");
    }

    /**
     * 忽略死信
     * POST /api/admin/queue-monitor/dead-letters/{id}/ignore
     */
    public function ignoreDeadLetter(int $id): JsonResponse
    {
        $letter = $this->monitor->ignoreDeadLetter($id);
        return ApiResponse::success($letter, '已忽略');
    }

    /**
     * 趋势数据
     * GET /api/admin/queue-monitor/trend
     */
    public function trend(Request $request): JsonResponse
    {
        $queue = $request->input('queue');
        $hours = $request->integer('hours', 24);
        return ApiResponse::success($this->monitor->getTrend($queue, $hours));
    }

    /**
     * 清理旧记录
     * POST /api/admin/queue-monitor/cleanup
     */
    public function cleanup(): JsonResponse
    {
        $deleted = $this->monitor->cleanup();
        return ApiResponse::success(['deleted' => $deleted], "已清理 {$deleted} 条记录");
    }
}
