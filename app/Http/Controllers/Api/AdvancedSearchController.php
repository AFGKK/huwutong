<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use App\Services\AdvancedSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdvancedSearchController extends Controller
{
    public function __construct(
        protected AdvancedSearchService $advancedSearchService,
    ) {}

    /**
     * 获取筛选器定义
     * GET /api/admin/advanced-search/filters/{page}
     */
    public function filterDefinitions(string $page): JsonResponse
    {
        if (!in_array($page, SavedSearch::$pages)) {
            return ApiResponse::error('INVALID_PAGE', "不支持的页面: {$page}", 422);
        }

        $definitions = $this->advancedSearchService->getFilterDefinitions($page);
        return ApiResponse::success([
            'page' => $page,
            'label' => SavedSearch::$pageLabels[$page] ?? $page,
            'icon' => SavedSearch::$pageIcons[$page] ?? 'Search',
            'filters' => $definitions,
        ]);
    }

    /**
     * 获取所有页面筛选器定义
     * GET /api/admin/advanced-search/filters
     */
    public function allFilterDefinitions(): JsonResponse
    {
        return ApiResponse::success(
            $this->advancedSearchService->getAllFilterDefinitions()
        );
    }

    /**
     * 高级筛选搜索
     * POST /api/admin/advanced-search/search/{page}
     */
    public function search(Request $request, string $page): JsonResponse
    {
        if (!in_array($page, SavedSearch::$pages)) {
            return ApiResponse::error('INVALID_PAGE', "不支持的页面: {$page}", 422);
        }

        $validator = Validator::make($request->all(), [
            'filters' => 'nullable|array',
            'sort' => 'nullable|array',
            'sort.field' => 'nullable|string',
            'sort.dir' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'columns' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;

        // Apply tenant scoping
        $filters = $request->input('filters', []);
        $filters['tenant_id'] = $tenantId;

        $result = $this->advancedSearchService->advancedSearch(
            $page,
            $filters,
            [
                'sort' => $request->input('sort', []),
                'page' => $request->input('page', 1),
                'per_page' => $request->input('per_page', 20),
                'columns' => $request->input('columns', ['*']),
            ]
        );

        return ApiResponse::success($result);
    }

    // ─── 保存搜索增强 ───

    /**
     * 获取我的保存搜索
     * GET /api/admin/advanced-search/saved
     */
    public function mySavedSearches(Request $request): JsonResponse
    {
        $page = $request->input('page');
        $searches = $this->advancedSearchService->getUserSearches(
            $request->user()->id,
            $page
        );

        return ApiResponse::success($searches);
    }

    /**
     * 创建保存搜索
     * POST /api/admin/advanced-search/saved
     */
    public function createSavedSearch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:200',
            'page' => 'required|string|in:' . implode(',', SavedSearch::$pages),
            'filters' => 'required|array',
            'columns' => 'nullable|array',
            'sort' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $search = $this->advancedSearchService->saveSearch(
            $request->user()->id,
            $validator->validated()
        );

        return ApiResponse::created($search, '搜索已保存');
    }

    /**
     * 更新保存搜索
     * PUT /api/admin/advanced-search/saved/{id}
     */
    public function updateSavedSearch(Request $request, int $id): JsonResponse
    {
        $search = SavedSearch::where('id', $id)->where('user_id', $request->user()->id)->first();
        if (!$search) {
            return ApiResponse::error('NOT_FOUND', '保存搜索未找到', 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:200',
            'filters' => 'sometimes|array',
            'columns' => 'nullable|array',
            'sort' => 'nullable|array',
            'is_shared' => 'nullable|boolean',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $search->update($validator->validated());
        return ApiResponse::success($search->fresh(), '搜索已更新');
    }

    /**
     * 删除保存搜索
     * DELETE /api/admin/advanced-search/saved/{id}
     */
    public function deleteSavedSearch(Request $request, int $id): JsonResponse
    {
        $search = SavedSearch::where('id', $id)->where('user_id', $request->user()->id)->first();
        if (!$search) {
            return ApiResponse::error('NOT_FOUND', '保存搜索未找到', 404);
        }

        $search->delete();
        return ApiResponse::success(null, '搜索已删除');
    }

    /**
     * 应用保存的搜索
     * POST /api/admin/advanced-search/saved/{id}/apply
     */
    public function applySavedSearch(Request $request, int $id): JsonResponse
    {
        $result = $this->advancedSearchService->applySavedSearch($id, $request->user()->id);
        if (!$result) {
            return ApiResponse::error('NOT_FOUND', '保存搜索未找到', 404);
        }

        return ApiResponse::success($result, '已应用搜索');
    }

    /**
     * 获取共享的搜索
     * GET /api/admin/advanced-search/saved/shared
     */
    public function sharedSearches(Request $request): JsonResponse
    {
        $page = $request->input('page');
        return ApiResponse::success(
            $this->advancedSearchService->getSharedSearches($page)
        );
    }

    /**
     * 获取常用搜索
     * GET /api/admin/advanced-search/saved/frequent
     */
    public function frequentSearches(Request $request): JsonResponse
    {
        return ApiResponse::success(
            SavedSearch::frequentlyUsed($request->user()->id, (int) $request->input('limit', 5))
        );
    }
}
