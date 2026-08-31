<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SlowQueryMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 慢查询监控面板 (M2-118)
 */
class SlowQueryMonitorController extends Controller
{
    public function __construct(
        protected SlowQueryMonitorService $monitor,
    ) {}

    /**
     * 看板总览
     */
    public function dashboard(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);
        return response()->json([
            'success' => true,
            'data' => $this->monitor->dashboard($minutes),
        ]);
    }

    /**
     * Top 慢查询（聚合）
     */
    public function topSlowQueries(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);
        $sortBy = $request->input('sort_by', 'avg_duration_ms');
        $sortDir = $request->input('sort_dir', 'desc');

        return response()->json([
            'success' => true,
            'data' => $this->monitor->topSlowQueries($minutes, $sortBy, $sortDir),
        ]);
    }

    /**
     * 慢查询明细列表
     */
    public function list(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->monitor->slowQueryList($request),
        ]);
    }

    /**
     * 慢查询详情
     */
    public function show(int $id): JsonResponse
    {
        try {
            $log = $this->monitor->showDetail($id);
            return response()->json(['success' => true, 'data' => $log]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.record_not_found')], 404);
        }
    }

    /**
     * 执行 EXPLAIN 并生成优化建议
     */
    public function explain(int $id): JsonResponse
    {
        try {
            $result = $this->monitor->explainAndSuggest($id);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    /**
     * 标记为已处理
     */
    public function resolve(int $id, Request $request): JsonResponse
    {
        try {
            $log = $this->monitor->markResolved($id, $request->user()->id);
            return response()->json(['success' => true, 'data' => $log]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => __('app.controller_compat.record_not_found')], 404);
        }
    }

    /**
     * 批量标记已处理
     */
    public function batchResolve(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $count = $this->monitor->batchResolve($request->ids);
        return response()->json(['success' => true, 'data' => ['processed' => $count]]);
    }

    /**
     * 按 API 路径下钻
     */
    public function byRoute(Request $request): JsonResponse
    {
        $minutes = min((int) $request->input('minutes', 60), 1440);
        return response()->json([
            'success' => true,
            'data' => $this->monitor->byRoute($minutes),
        ]);
    }

    /**
     * 告警检查
     */
    public function checkAlert(): JsonResponse
    {
        $alert = $this->monitor->checkAlert();
        return response()->json([
            'success' => true,
            'data' => ['alert' => $alert],
        ]);
    }

    /**
     * 清理过期数据
     */
    public function prune(): JsonResponse
    {
        $deleted = $this->monitor->prune();
        return response()->json([
            'success' => true,
            'data' => ['deleted' => $deleted],
        ]);
    }
}
