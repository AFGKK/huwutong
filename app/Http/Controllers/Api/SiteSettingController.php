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

        // ── 键名别名镜像（公安备案 / 工作时间）──
        $this->mirrorSettingAliases($updates);

        // ── 维护开关：与 MaintenanceConfig 双向同步 ──
        if (array_key_exists('maintenance_enabled', $updates)) {
            try {
                $svc = app(\App\Services\MaintenanceModeService::class);
                if (($updates['maintenance_enabled'] ?? '0') === '1') {
                    if (! $svc->getConfig()) {
                        $svc->enable([
                            'title' => __('app.api.site_setting.maint_title'),
                            'message' => $updates['maintenance_message']
                                ?? (string) site_setting('maintenance_message', __('app.api.site_setting.maint_message')),
                        ]);
                    } else {
                        $svc->syncSiteSettingFlag(true, $updates['maintenance_message'] ?? null);
                    }
                } else {
                    $svc->disable();
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Maintenance sync failed: '.$e->getMessage());
            }
        }

        // 清除缓存（如果有）
        cache()->forget('site_settings_all');

        // 支付/短信配置立即叠加到 runtime
        \App\Services\SiteSettingRuntimeOverlay::apply();

        return ApiResponse::success(null, __('app.api.site_setting.saved'));
    }

    /**
     * 后台保存时同步历史别名键，避免前台读不到
     */
    private function mirrorSettingAliases(array $updates): void
    {
        $pairs = [
            'police_beian' => 'gongan_beian',
            'gongan_beian' => 'police_beian',
            'police_beian_url' => 'gongan_beian_url',
            'gongan_beian_url' => 'police_beian_url',
            'service_work_hours' => 'working_hours',
            'working_hours' => 'service_work_hours',
            'primary_color' => 'page_primary_color',
            'page_primary_color' => 'primary_color',
        ];

        foreach ($pairs as $source => $target) {
            if (! array_key_exists($source, $updates)) {
                continue;
            }
            $sourceRow = SiteSetting::where('key', $source)->first();
            $targetRow = SiteSetting::where('key', $target)->first();
            $defaultGroup = 'brand';

            SiteSetting::updateOrCreate(
                ['key' => $target],
                [
                    'group' => $targetRow?->group ?: ($sourceRow?->group ?: $defaultGroup),
                    'value' => $updates[$source] ?? '',
                    'type' => $targetRow?->type ?: ($sourceRow?->type ?: 'text'),
                    'is_public' => true,
                ]
            );
        }
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
        ], __('app.api.site_setting.image_uploaded'));
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

        return ApiResponse::created($setting, __('app.api.site_setting.item_created'));
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
            'general' => __('app.api.site_setting.group_general'),
            'brand' => __('app.api.site_setting.group_brand'),
            'contact' => __('app.api.site_setting.group_contact'),
            'seo' => __('app.api.site_setting.group_seo'),
            'social' => __('app.api.site_setting.group_social'),
            'mail' => __('app.api.site_setting.group_mail'),
            'payment' => __('app.api.site_setting.group_payment'),
            'storage' => __('app.api.site_setting.group_storage'),
            'sms' => __('app.api.site_setting.group_sms'),
            'ai' => __('app.api.site_setting.group_ai'),
            'security' => __('app.api.site_setting.group_security'),
            'maintenance' => __('app.api.site_setting.group_maintenance'),
            'registration' => __('app.api.site_setting.group_registration'),
            'localization' => __('app.api.site_setting.group_localization'),
            'notification' => __('app.api.site_setting.group_notification'),
            'backup' => __('app.api.site_setting.group_backup'),
            'logging' => __('app.api.site_setting.group_logging'),
            'interface' => __('app.api.site_setting.group_interface'),
            'api' => __('app.api.site_setting.group_api'),
            'service' => __('app.api.site_setting.group_service'),
            'legal' => __('app.api.site_setting.group_legal'),
            'oauth' => __('app.api.site_setting.group_oauth'),
            'wechat' => __('app.api.site_setting.group_wechat'),
        ];
        return $labels[$group] ?? $group;
    }
}
