<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomEmoji;
use App\Services\EmojiService;
use Illuminate\Http\Request;

class EmojiController extends Controller
{
    /**
     * 获取所有自定义 emoji
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $query = CustomEmoji::with('uploader:id,name');

        if ($cat = $request->input('category')) {
            $query->byCategory($cat);
        }
        if ($q = $request->input('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('shortcode', 'like', "%{$q}%")
                    ->orWhere('aliases', 'like', "%{$q}%");
            });
        }

        return ApiResponse::success(
            $query->orderBy('sort_order')->orderBy('shortcode')->paginate(50)
        );
    }

    /**
     * 获取所有启用的 emoji（供聊天使用，含映射）
     */
    public function all(): \Illuminate\Http\JsonResponse
    {
        $emojis = CustomEmoji::active()->orderBy('sort_order')->orderBy('shortcode')->get([
            'id', 'shortcode', 'image_url', 'category', 'aliases',
        ]);

        $grouped = $emojis->groupBy('category');

        return ApiResponse::success([
            'list' => $emojis,
            'grouped' => $grouped,
            'map' => (new EmojiService)->getEmojiMap(),
        ]);
    }

    /**
     * 获取分类列表
     */
    public function categories(): \Illuminate\Http\JsonResponse
    {
        $cats = CustomEmoji::select('category')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('category')
            ->orderBy('category')
            ->get();

        return ApiResponse::success($cats);
    }

    /**
     * 创建自定义 emoji
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'shortcode' => 'required|string|max:50|regex:/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/|unique:custom_emojis,shortcode',
            'image_url' => 'required|string|max:500',
            'category' => 'nullable|string|max:50',
            'aliases' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $emoji = CustomEmoji::create([
            'shortcode' => $validated['shortcode'],
            'image_url' => $validated['image_url'],
            'category' => $validated['category'] ?? 'general',
            'aliases' => $validated['aliases'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'uploaded_by' => $request->user()->id,
        ]);

        EmojiService::clearCache();

        return ApiResponse::success($emoji, '自定义表情已添加', 201);
    }

    /**
     * 更新 emoji
     */
    public function update(int $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $emoji = CustomEmoji::findOrFail($id);

        $validated = $request->validate([
            'shortcode' => 'sometimes|string|max:50|regex:/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/|unique:custom_emojis,shortcode,' . $id,
            'image_url' => 'sometimes|string|max:500',
            'category' => 'nullable|string|max:50',
            'aliases' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $emoji->update($validated);

        EmojiService::clearCache();

        return ApiResponse::success($emoji->fresh(), '已更新');
    }

    /**
     * 删除 emoji
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        CustomEmoji::findOrFail($id)->delete();
        EmojiService::clearCache();
        return ApiResponse::success(null, '已删除');
    }

    /**
     * 批量导入 emoji
     */
    public function import(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'emojis' => 'required|array|max:500',
            'emojis.*.shortcode' => 'required|string|max:50',
            'emojis.*.image_url' => 'required|string|max:500',
            'emojis.*.category' => 'nullable|string|max:50',
        ]);

        $imported = 0;
        $skipped = 0;
        foreach ($validated['emojis'] as $item) {
            try {
                CustomEmoji::firstOrCreate(
                    ['shortcode' => $item['shortcode']],
                    [
                        'image_url' => $item['image_url'],
                        'category' => $item['category'] ?? 'general',
                        'uploaded_by' => $request->user()->id,
                    ]
                );
                $imported++;
            } catch (\Exception $e) {
                $skipped++;
            }
        }

        EmojiService::clearCache();

        return ApiResponse::success([
            'imported' => $imported,
            'skipped' => $skipped,
        ], "成功导入 {$imported} 个，跳过 {$skipped} 个");
    }

    /**
     * 表情使用计数递增
     */
    public function trackUsage(int $id): \Illuminate\Http\JsonResponse
    {
        $emoji = CustomEmoji::findOrFail($id);
        $emoji->incrementUsage();
        return ApiResponse::success(['usage_count' => $emoji->fresh()->usage_count]);
    }

    /**
     * 表情使用统计
     */
    public function stats(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::success([
            'total' => CustomEmoji::count(),
            'active' => CustomEmoji::where('is_active', true)->count(),
            'total_usage' => CustomEmoji::sum('usage_count'),
            'top_used' => CustomEmoji::orderByDesc('usage_count')->take(10)->get(['shortcode', 'image_url', 'usage_count']),
            'categories' => CustomEmoji::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')->pluck('count', 'category'),
        ]);
    }
}
