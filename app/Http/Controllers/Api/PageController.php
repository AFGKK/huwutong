<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * 页面列表
     */
    public function index(Request $request): JsonResponse
    {
        $query = Page::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('filter.status')) {
            $query->where('status', $request->input('filter.status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->orderBy('slug')->paginate($perPage));
    }

    /**
     * 页面详情
     */
    public function show(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        return ApiResponse::success($page);
    }

    /**
     * 创建页面
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'slug' => 'required|string|max:100|unique:pages,slug',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'locale' => 'sometimes|string|max:10',
            'status' => 'sometimes|in:draft,published',
            'meta' => 'nullable|array',
            'meta.title' => 'nullable|string',
            'meta.description' => 'nullable|string',
            'meta.keywords' => 'nullable|string',
        ]);

        $page = Page::create([
            'slug' => $validated['slug'],
            'title' => $validated['title'],
            'content' => $validated['content'] ?? '',
            'locale' => $validated['locale'] ?? 'zh-CN',
            'status' => $validated['status'] ?? 'draft',
            'meta' => $validated['meta'] ?? null,
            'version' => 1,
        ]);

        return ApiResponse::created($page, '页面创建成功');
    }

    /**
     * 更新页面
     */
    public function update(int $id, Request $request): JsonResponse
    {
        $page = Page::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'nullable|string',
            'locale' => 'sometimes|string|max:10',
            'meta' => 'nullable|array',
            'meta.title' => 'nullable|string',
            'meta.description' => 'nullable|string',
            'meta.keywords' => 'nullable|string',
        ]);

        $page->update($validated);

        // 如果有内容更新，版本号 +1
        if ($request->has('content')) {
            $page->increment('version');
        }

        return ApiResponse::success($page->fresh(), '页面更新成功');
    }

    /**
     * 发布页面
     */
    public function publish(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->publish();
        return ApiResponse::success($page->fresh(), '页面已发布');
    }

    /**
     * 撤回为草稿
     */
    public function draft(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->draft();
        return ApiResponse::success($page->fresh(), '已撤回为草稿');
    }

    /**
     * 删除页面
     */
    public function destroy(int $id): JsonResponse
    {
        $page = Page::findOrFail($id);
        $page->delete();
        return ApiResponse::success(null, '页面已删除');
    }

    /**
     * 公开页面（按 slug 获取已发布的页面）
     */
    public function showBySlug(string $slug): JsonResponse
    {
        $page = Page::where('slug', $slug)->where('status', 'published')->firstOrFail();
        return ApiResponse::success($page);
    }
}
