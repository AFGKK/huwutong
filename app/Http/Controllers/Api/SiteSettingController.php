<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteSettingController extends Controller
{
    /**
     * 获取所有设置（按分组）
     */
    public function index(): JsonResponse
    {
        $settings = SiteSetting::orderBy('group')->orderBy('key')->get();
        return ApiResponse::success($settings);
    }

    /**
     * 获取分组后的设置（前端表单展示用）
     */
    public function grouped(): JsonResponse
    {
        $settings = SiteSetting::orderBy('group')->orderBy('key')->get();
        $grouped = $settings->groupBy('group');

        $groups = [];
        foreach ($grouped as $group => $items) {
            $groups[] = [
                'group' => $group,
                'label' => $this->groupLabel($group),
                'settings' => $items,
            ];
        }

        return ApiResponse::success($groups);
    }

    /**
     * 批量更新设置
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string|exists:site_settings,key',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $item) {
            SiteSetting::where('key', $item['key'])->update(['value' => $item['value'] ?? '']);
        }

        // 清除缓存（如果有）
        if (cache()->has('site_settings')) {
            cache()->forget('site_settings');
        }

        return ApiResponse::success(null, '设置保存成功');
    }

    /**
     * 添加新设置（仅供超管）
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'group' => 'required|string|max:50',
            'key' => 'required|string|max:100|unique:site_settings,key',
            'value' => 'nullable',
            'type' => 'required|in:text,textarea,image,color,switch,select',
            'options' => 'nullable|array',
            'description' => 'nullable|string',
            'is_public' => 'boolean',
        ]);

        $setting = SiteSetting::create($validated);

        return ApiResponse::created($setting, '设置项已创建');
    }

    /**
     * 获取公开设置（前端渲染用）
     */
    public function publicSettings(): JsonResponse
    {
        $settings = SiteSetting::getPublic();
        return ApiResponse::success($settings);
    }

    private function groupLabel(string $group): string
    {
        $labels = [
            'general' => '基本信息',
            'brand' => '品牌设置',
            'contact' => '联系方式',
            'seo' => 'SEO 优化',
            'social' => '社交媒体',
        ];
        return $labels[$group] ?? $group;
    }
}
