<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataAnonymizationRule;
use App\Services\DataAnonymizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataAnonymizationController extends Controller
{
    public function __construct(
        protected DataAnonymizationService $anonymizationService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->list($request)]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->create($request->all())], 201);
    }

    public function show(DataAnonymizationRule $rule): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->show($rule)]);
    }

    public function update(Request $request, DataAnonymizationRule $rule): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->update($rule, $request->all())]);
    }

    public function destroy(DataAnonymizationRule $rule): JsonResponse
    {
        $this->anonymizationService->delete($rule);
        return response()->json(['success' => true, 'message' => '已删除']);
    }

    /**
     * 执行匿名化导出
     */
    public function export(Request $request): JsonResponse
    {
        $tables = $request->input('tables');
        $result = $this->anonymizationService->runPipeline($tables);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 预览匿名化效果
     */
    public function preview(Request $request): JsonResponse
    {
        $table = $request->input('table');
        $limit = $request->input('limit', 5);
        $result = $this->anonymizationService->preview($table, $limit);
        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * 导出任务列表
     */
    public function tasks(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->getTasks($request)]);
    }

    /**
     * 任务详情
     */
    public function showTask(int $task): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->getTaskDetail($task)]);
    }

    /**
     * 重试任务
     */
    public function retryTask(int $task): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->retryTask($task)]);
    }

    /**
     * 可导出的表列表
     */
    public function tables(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->anonymizationService->getExportableTables()]);
    }

    /**
     * 匿名化规则列表
     */
    public function rules(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => DataAnonymizationRule::when($request->filled('table'), fn($q) => $q->where('table_name', $request->table))->get()]);
    }

    /**
     * 创建规则
     */
    public function storeRule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'table_name' => 'required|string|max:100',
            'field_name' => 'required|string|max:100',
            'method' => 'required|string|max:50',
            'is_active' => 'nullable|boolean',
            'description' => 'nullable|string',
        ]);
        return response()->json(['success' => true, 'data' => DataAnonymizationRule::create($validated)], 201);
    }

    /**
     * 删除规则
     */
    public function destroyRule(DataAnonymizationRule $rule): JsonResponse
    {
        $rule->delete();
        return response()->json(['success' => true, 'message' => '已删除']);
    }
}
