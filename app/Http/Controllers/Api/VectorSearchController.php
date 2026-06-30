<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\VectorSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VectorSearchController extends Controller
{
    protected VectorSearchService $service;

    public function __construct(VectorSearchService $service)
    {
        $this->service = $service;
    }

    /**
     * 统一搜索
     */
    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:500',
            'types' => 'nullable|array',
            'types.*' => 'in:kb,rag,conversation',
            'limit' => 'nullable|integer|min:1|max:50',
            'hybrid' => 'nullable|boolean',
        ]);

        $results = $this->service->search($validated['query'], [
            'types' => $validated['types'] ?? ['kb', 'rag'],
            'limit' => $validated['limit'] ?? 10,
            'hybrid' => $validated['hybrid'] ?? true,
        ]);

        return ApiResponse::success($results);
    }

    /**
     * 重建搜索索引
     */
    public function rebuild(Request $request): JsonResponse
    {
        $force = $request->boolean('force', false);
        $result = $this->service->rebuildEmbeddings($force);

        return ApiResponse::success($result, "已更新 {$result['updated']}/{$result['total']} 条索引");
    }

    /**
     * 搜索统计
     */
    public function stats(): JsonResponse
    {
        return ApiResponse::success($this->service->getStats());
    }
}
