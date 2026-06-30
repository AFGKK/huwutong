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

        $updates = [];
        foreach ($validated['settings'] as $item) {
            SiteSetting::where('key', $item['key'])->update(['value' => $item['value'] ?? '']);
            $updates[$item['key']] = $item['value'] ?? '';
        }

        // ── AI 配置自动同步到 llm_providers ──
        $this->syncLlmProviders($updates);

        // ── Cookie 同意配置自动同步 ──
        if (array_key_exists('legal_gdpr_enabled', $updates)) {
            try {
                $cookieService = app(\App\Services\CookieConsentService::class);
                $cookieService->updateConfig([
                    'is_active' => $updates['legal_gdpr_enabled'] === '1',
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Cookie consent sync failed: ' . $e->getMessage());
            }
        }

        // 清除缓存（如果有）
        if (cache()->has('site_settings_all')) {
            cache()->forget('site_settings_all');
        }

        return ApiResponse::success(null, '设置保存成功');
    }

    /**
     * AI/大模型配置变更时自动同步到 llm_providers 表
     */
    private function syncLlmProviders(array $updates): void
    {
        $mapping = [
            'deepseek_api_key' => ['slug' => 'deepseek', 'field' => 'api_key'],
            'deepseek_api_base' => ['slug' => 'deepseek', 'field' => 'api_base'],
            'openai_api_key' => ['slug' => 'openai', 'field' => 'api_key'],
            'openai_api_base' => ['slug' => 'openai', 'field' => 'api_base'],
            'claude_api_key' => ['slug' => 'claude', 'field' => 'api_key'],
            'claude_api_base' => ['slug' => 'claude', 'field' => 'api_base'],
        ];

        foreach ($mapping as $settingKey => $target) {
            if (array_key_exists($settingKey, $updates)) {
                \Illuminate\Support\Facades\DB::table('llm_providers')
                    ->where('slug', $target['slug'])
                    ->update([$target['field'] => $updates[$settingKey]]);
            }
        }
    }

    /**
     * 上传站点图片（Logo/Favicon等）
     */
    public function uploadImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,gif,webp,svg|max:2048',
            'key' => 'required|string|exists:site_settings,key',
        ]);

        $file = $request->file('image');
        $key = $request->input('key');

        // 删除旧文件（如果有）
        $old = SiteSetting::where('key', $key)->value('value');
        if ($old && str_starts_with($old, '/storage/settings/')) {
            $oldPath = public_path($old);
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $path = $file->store('settings', 'public');

        // 更新设置值
        $url = '/storage/' . $path;
        SiteSetting::where('key', $key)->update(['value' => $url]);

        // 清除缓存
        if (cache()->has('site_settings_all')) {
            cache()->forget('site_settings_all');
        }

        return ApiResponse::success([
            'key' => $key,
            'value' => $url,
            'url' => $url,
        ], '图片上传成功');
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
            'type' => 'required|in:text,textarea,image,color,switch,select,password',
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
            'mail' => '邮件(SMTP)配置',
            'payment' => '支付网关配置',
            'storage' => '存储配置',
            'sms' => '短信配置',
            'ai' => 'AI / 大模型配置',
            'security' => '安全策略',
            'maintenance' => '系统维护',
            'registration' => '注册设置',
            'localization' => '时区/本地化',
            'notification' => '通知设置',
            'backup' => '备份设置',
            'logging' => '日志设置',
            'interface' => '界面设置',
            'api' => 'API 配置',
            'service' => '客服设置',
            'legal' => '法律/隐私',
            'oauth' => 'OAuth 登录',
        ];
        return $labels[$group] ?? $group;
    }
}
