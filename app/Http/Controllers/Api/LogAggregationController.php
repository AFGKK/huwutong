<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LogAggregationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogAggregationController extends Controller
{
    public function __construct(
        protected LogAggregationService $logService,
    ) {}

    /**
     * 仪表盘
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->dashboard(),
        ]);
    }

    /**
     * 日志搜索
     */
    public function search(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:200',
            'level' => 'nullable|string|in:debug,info,warning,error,critical',
            'source' => 'nullable|string|max:50',
            'channel' => 'nullable|string|max:50',
            'tenant_id' => 'nullable|integer',
            'user_id' => 'nullable|integer',
            'trace_id' => 'nullable|string|max:64',
            'method' => 'nullable|string|in:GET,POST,PUT,DELETE,PATCH',
            'path' => 'nullable|string|max:200',
            'status_code' => 'nullable|integer',
            'duration_min' => 'nullable|numeric|min:0',
            'time_from' => 'nullable|date',
            'time_to' => 'nullable|date',
            'sort' => 'nullable|string|max:20',
            'per_page' => 'nullable|integer|min:10|max:200',
            'page' => 'nullable|integer|min:1',
        ]);

        return response()->json([
            'data' => $this->logService->search($filters),
        ]);
    }

    /**
     * 日志详情
     */
    public function show(int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->getEntry($id),
        ]);
    }

    /**
     * 日志级别统计
     */
    public function levelStats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->getLevelStats($request->only(['time_from', 'time_to'])),
        ]);
    }

    /**
     * 慢查询 Top
     */
    public function slowQueries(): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->getSlowQueries(),
        ]);
    }

    /**
     * 请求路径统计
     */
    public function pathStats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->getPathStats($request->only(['time_from', 'time_to'])),
        ]);
    }

    /**
     * 清理过期日志
     */
    public function prune(): JsonResponse
    {
        $deleted = $this->logService->prune();
        return response()->json([
            'message' => "已清理 {$deleted} 条过期日志",
            'data' => ['deleted' => $deleted],
        ]);
    }

    // ─── 保存搜索 ───

    public function saveSearch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'filters' => 'required|array',
            'is_shared' => 'boolean',
        ]);
        $data['created_by'] = $request->user()->id;

        return response()->json([
            'data' => $this->logService->saveSearch($data),
        ], 201);
    }

    public function listSavedSearches(): JsonResponse
    {
        return response()->json([
            'data' => $this->logService->listSavedSearches(),
        ]);
    }

    public function deleteSavedSearch(int $id): JsonResponse
    {
        $this->logService->deleteSavedSearch($id);
        return response()->json(['message' => __('app.common.deleted')]);
    }
}
