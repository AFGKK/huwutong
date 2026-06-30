<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SavedSearchController extends Controller
{
    /**
     * 获取当前用户的保存搜索列表
     */
    public function index(Request $request): JsonResponse
    {
        $page = $request->input('page');

        $query = SavedSearch::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderByDesc('created_at');

        if ($page && in_array($page, SavedSearch::$pages)) {
            $query->where('page', $page);
        }

        $searches = $query->get();

        return ApiResponse::success($searches);
    }

    /**
     * 保存新搜索
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'page' => 'required|string|in:' . implode(',', SavedSearch::$pages),
            'filters' => 'required|array',
            'is_shared' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();
        $data['user_id'] = $request->user()->id;

        $search = SavedSearch::create($data);

        return ApiResponse::created($search, '搜索已保存');
    }

    /**
     * 更新保存搜索
     */
    public function update(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== $request->user()->id) {
            return ApiResponse::error('FORBIDDEN', '无权修改此搜索', 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:100',
            'filters' => 'sometimes|array',
            'is_shared' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $savedSearch->update($validator->validated());

        return ApiResponse::success($savedSearch->fresh(), '搜索已更新');
    }

    /**
     * 删除保存搜索
     */
    public function destroy(Request $request, SavedSearch $savedSearch): JsonResponse
    {
        if ($savedSearch->user_id !== $request->user()->id) {
            return ApiResponse::error('FORBIDDEN', '无权删除此搜索', 403);
        }

        $savedSearch->delete();

        return ApiResponse::success(null, '搜索已删除');
    }
}
