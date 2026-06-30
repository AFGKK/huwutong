<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\DeepResearchTask;
use App\Services\DeepResearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeepResearchController extends Controller
{
    protected DeepResearchService $service;

    public function __construct(DeepResearchService $service)
    {
        $this->service = $service;
    }

    /**
     * 发起深度研究
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
        ]);

        $task = $this->service->start(auth()->id(), $validated['query']);

        return ApiResponse::success([
            'id' => $task->id,
            'query' => $task->query,
            'status' => $task->status,
            'report' => $task->report,
            'sub_questions' => $task->sub_questions,
            'source_count' => $task->source_count,
            'total_tokens' => $task->total_tokens,
            'progress' => $task->progress,
            'created_at' => $task->created_at,
        ], $task->status === 'completed' ? '研究完成' : '研究已启动');
    }

    /**
     * 获取研究历史列表
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $tasks = $this->service->getUserTasks(auth()->id(), $perPage);

        return ApiResponse::success($tasks);
    }

    /**
     * 获取研究详情
     */
    public function show(int $id): JsonResponse
    {
        $task = $this->service->getTaskDetail($id, auth()->id());
        if (!$task) {
            return ApiResponse::error('研究记录不存在', 404);
        }

        return ApiResponse::success($task);
    }

    /**
     * 删除研究记录
     */
    public function destroy(int $id): JsonResponse
    {
        $task = DeepResearchTask::byUser(auth()->id())->find($id);
        if (!$task) {
            return ApiResponse::error('研究记录不存在', 404);
        }

        $task->delete();
        return ApiResponse::success(null, '已删除');
    }
}
