<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MemoryExtractionService;
use App\Services\MemoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MemoryController extends Controller
{
    protected MemoryService $memoryService;
    protected MemoryExtractionService $extractionService;

    public function __construct(MemoryService $memoryService, MemoryExtractionService $extractionService)
    {
        $this->memoryService = $memoryService;
        $this->extractionService = $extractionService;
    }

    /**
     * 仪表盘：记忆统计概览
     */
    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $this->memoryService->getDashboard($userId);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 记忆列表
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $filters = $request->only(['type', 'category', 'source', 'search', 'sort', 'sort_dir']);
        $perPage = (int) $request->input('per_page', 20);

        $memories = $this->memoryService->list($userId, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => $memories->items(),
            'meta' => [
                'current_page' => $memories->currentPage(),
                'last_page' => $memories->lastPage(),
                'per_page' => $memories->perPage(),
                'total' => $memories->total(),
            ],
        ]);
    }

    /**
     * 查看单条记忆
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $memory = $this->memoryService->find($id, $userId);

        if (! $memory) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MEMORY_NOT_FOUND', 'message' => '记忆不存在'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $memory,
        ]);
    }

    /**
     * 手动创建记忆
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
            'type' => 'sometimes|string|in:preference,fact,context,insight,behavior',
            'category' => 'sometimes|nullable|string|max:50',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'priority' => 'sometimes|integer|between:0,255',
            'expires_at' => 'sometimes|nullable|date|after:now',
        ]);

        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;

        $memory = $this->memoryService->store(
            userId: $userId,
            key: 'manual_' . uniqid(),
            content: $validated['content'],
            type: $validated['type'] ?? 'fact',
            source: 'manual',
            confidence: 1.0,
            priority: $validated['priority'] ?? 5,
            category: $validated['category'] ?? null,
            tags: $validated['tags'] ?? null,
            tenantId: $tenantId,
            expiresAt: $validated['expires_at'] ?? null,
        );

        return response()->json([
            'success' => true,
            'data' => $memory,
            'message' => '记忆已保存',
        ], 201);
    }

    /**
     * 更新记忆
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'sometimes|string|max:2000',
            'type' => 'sometimes|string|in:preference,fact,context,insight,behavior',
            'category' => 'sometimes|nullable|string|max:50',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
            'priority' => 'sometimes|integer|between:0,255',
            'confidence' => 'sometimes|numeric|between:0,1',
            'expires_at' => 'sometimes|nullable|date',
        ]);

        $userId = $request->user()->id;
        $memory = $this->memoryService->update($id, $validated);

        if (! $memory) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MEMORY_NOT_FOUND', 'message' => '记忆不存在'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $memory,
            'message' => '记忆已更新',
        ]);
    }

    /**
     * 删除记忆
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;

        if (! $this->memoryService->forget($id, $userId)) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MEMORY_NOT_FOUND', 'message' => '记忆不存在'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => '记忆已遗忘',
        ]);
    }

    /**
     * 批量删除记忆
     */
    public function batchDestroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer|exists:ai_memories,id',
        ]);

        $userId = $request->user()->id;
        $count = $this->memoryService->forgetBatch($validated['ids'], $userId);

        return response()->json([
            'success' => true,
            'message' => "已遗忘 {$count} 条记忆",
            'data' => ['forgotten_count' => $count],
        ]);
    }

    /**
     * 确认记忆（提升置信度）
     */
    public function confirm(Request $request, int $id): JsonResponse
    {
        $userId = $request->user()->id;
        $memory = $this->memoryService->confirm($id, $userId);

        if (! $memory) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MEMORY_NOT_FOUND', 'message' => '记忆不存在'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $memory,
            'message' => '记忆已确认',
        ]);
    }

    /**
     * 纠正记忆内容
     */
    public function correct(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'content' => 'required|string|max:2000',
        ]);

        $userId = $request->user()->id;
        $memory = $this->memoryService->correct($id, $userId, $validated['content']);

        if (! $memory) {
            return response()->json([
                'success' => false,
                'error' => ['code' => 'MEMORY_NOT_FOUND', 'message' => '记忆不存在'],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $memory,
            'message' => '记忆已纠正',
        ]);
    }

    /**
     * AI 从文本中提取记忆
     */
    public function extract(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'text' => 'required|string|min:10|max:10000',
        ]);

        $userId = $request->user()->id;
        $tenantId = $request->user()->tenant_id;

        $stored = $this->extractionService->extractFromText(
            $validated['text'],
            $userId,
            $tenantId,
        );

        return response()->json([
            'success' => true,
            'data' => $stored,
            'message' => '提取完成，新增 ' . count($stored) . ' 条记忆',
        ]);
    }

    /**
     * 清空所有记忆（用户主动操作）
     */
    public function clearAll(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $count = \App\Models\AiMemory::byUser($userId)->active()->delete();

        return response()->json([
            'success' => true,
            'message' => "已清空 {$count} 条记忆",
        ]);
    }

    /**
     * 获取可用分类和类型列表（用于前端筛选）
     */
    public function options(): JsonResponse
    {
        $categories = config('ai-memory.categories', []);
        $types = \App\Enums\AiMemoryType::options();
        $sources = \App\Enums\AiMemorySource::cases();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => $categories,
                'types' => $types,
                'sources' => collect($sources)->mapWithKeys(fn ($s) => [$s->value => $s->label()]),
            ],
        ]);
    }
}
