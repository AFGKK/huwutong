<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SearchBookmark;
use App\Models\SearchIndex;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GlobalSearchController extends Controller
{
    public function __construct(
        protected GlobalSearchService $searchService,
    ) {}

    // ─── 统一搜索 ───

    /**
     * 全局搜索
     * GET /api/admin/search?q=xxx&types[]=license&page=1
     */
    public function search(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|max:200',
            'types' => 'nullable|array',
            'types.*' => 'string|in:' . implode(',', SearchIndex::RESOURCE_TYPES),
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:50',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('搜索参数错误', $validator->errors()->toArray());
        }

        $result = $this->searchService->search(
            $request->user()->tenant_id,
            $request->user()->id,
            $request->input('q'),
            $request->only(['types', 'page', 'per_page', 'filters'])
        );

        return ApiResponse::success($result);
    }

    /**
     * 搜索建议
     * GET /api/admin/search/suggestions?q=xxx
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = $request->input('q', '');
        if (strlen($query) < 1) {
            return ApiResponse::success([]);
        }

        $results = $this->searchService->suggestions(
            $request->user()->tenant_id,
            $query,
            (int) $request->input('limit', 8)
        );

        return ApiResponse::success($results);
    }

    // ─── 索引管理 ───

    /**
     * 重建搜索索引
     * POST /api/admin/search/rebuild?type=license
     */
    public function rebuild(Request $request): JsonResponse
    {
        $type = $request->input('type', 'all');

        if ($type === 'all') {
            $results = $this->searchService->rebuildAll($request->user()->tenant_id);
            return ApiResponse::success($results, '所有索引已重建');
        }

        if (!in_array($type, SearchIndex::RESOURCE_TYPES)) {
            return ApiResponse::error('INVALID_TYPE', "不支持的类型: {$type}", 422);
        }

        $count = $this->searchService->rebuildIndex($type, $request->user()->tenant_id);
        return ApiResponse::success(['type' => $type, 'count' => $count], "{$type} 索引已重建，共 {$count} 条");
    }

    /**
     * 索引状态
     * GET /api/admin/search/index-status
     */
    public function indexStatus(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $byType = SearchIndex::where('tenant_id', $tenantId)
            ->selectRaw('resource_type, COUNT(*) as cnt')
            ->groupBy('resource_type')
            ->orderByDesc('cnt')
            ->get();

        $total = $byType->sum('cnt');

        return ApiResponse::success([
            'total' => $total,
            'by_type' => $byType,
        ]);
    }

    // ─── 最近搜索 ───

    /**
     * 最近搜索
     * GET /api/admin/search/recent
     */
    public function recent(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->searchService->getRecentSearches($request->user()->id)
        );
    }

    /**
     * 清除最近搜索
     * DELETE /api/admin/search/recent
     */
    public function clearRecent(Request $request): JsonResponse
    {
        $this->searchService->clearRecentSearches($request->user()->id);
        return ApiResponse::success(null, '最近搜索已清除');
    }

    /**
     * 删除单条搜索记录
     * DELETE /api/admin/search/recent/{id}
     */
    public function deleteRecent(int $id, Request $request): JsonResponse
    {
        $this->searchService->deleteRecentSearch($id, $request->user()->id);
        return ApiResponse::success(null, '已删除');
    }

    // ─── 搜索收藏 ───

    /**
     * 收藏列表
     * GET /api/admin/search/bookmarks
     */
    public function bookmarks(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->searchService->getBookmarks(
                $request->user()->id,
                $request->user()->tenant_id
            )
        );
    }

    /**
     * 切换收藏
     * POST /api/admin/search/bookmarks/toggle
     */
    public function toggleBookmark(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'resource_type' => 'required|string|in:' . implode(',', SearchIndex::RESOURCE_TYPES),
            'resource_id' => 'required|integer',
            'label' => 'nullable|string|max:200',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $result = $this->searchService->toggleBookmark(
            $request->user()->id,
            $request->user()->tenant_id,
            $request->input('resource_type'),
            (int) $request->input('resource_id'),
            $request->input('label')
        );

        return ApiResponse::success($result, $result['message']);
    }

    /**
     * 删除收藏
     * DELETE /api/admin/search/bookmarks/{id}
     */
    public function deleteBookmark(int $id, Request $request): JsonResponse
    {
        $this->searchService->deleteBookmark($id, $request->user()->id);
        return ApiResponse::success(null, '收藏已删除');
    }

    // ─── 搜索偏好 ───

    /**
     * 获取偏好
     * GET /api/admin/search/preferences
     */
    public function preferences(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->searchService->getPreferences($request->user()->id)
        );
    }

    /**
     * 更新偏好
     * PUT /api/admin/search/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'results_per_page' => 'nullable|integer|min:5|max:100',
            'show_recent' => 'nullable|boolean',
            'show_suggestions' => 'nullable|boolean',
            'enable_shortcuts' => 'nullable|boolean',
            'favorite_types' => 'nullable|array',
            'excluded_types' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('参数错误', $validator->errors()->toArray());
        }

        $prefs = $this->searchService->updatePreferences(
            $request->user()->id,
            $validator->validated()
        );

        return ApiResponse::success($prefs, '偏好已更新');
    }

    // ─── 仪表盘 ───

    /**
     * 搜索仪表盘
     * GET /api/admin/search/dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $this->searchService->getDashboard(
                $request->user()->id,
                $request->user()->tenant_id
            )
        );
    }
}
