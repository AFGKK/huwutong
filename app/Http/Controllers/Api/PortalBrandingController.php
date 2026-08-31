<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\CustomDomain;
use App\Services\PortalBrandingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PortalBrandingController extends Controller
{
    public function __construct(
        protected PortalBrandingService $brandingService
    ) {}

    /**
     * 获取当前品牌配置
     */
    public function show(Request $request)
    {
        $data = $this->brandingService->getBrandingData(
            $request->user()->tenant_id,
            $request->input('locale', 'zh-CN')
        );

        return ApiResponse::success($data);
    }

    /**
     * 更新品牌配置
     */
    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'brand_name' => 'nullable|string|max:200',
            'brand_slogan' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500',
            'favicon_url' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:20',
            'secondary_color' => 'nullable|string|max:20',
            'background_color' => 'nullable|string|max:20',
            'text_color' => 'nullable|string|max:20',
            'link_color' => 'nullable|string|max:20',
            'header_bg_color' => 'nullable|string|max:20',
            'sidebar_bg_color' => 'nullable|string|max:20',
            'sidebar_text_color' => 'nullable|string|max:20',
            'button_radius' => 'nullable|string|max:10',
            'font_family' => 'nullable|string|max:200',
            'custom_css' => 'nullable|string',
            'header_html' => 'nullable|string',
            'footer_html' => 'nullable|string',
            'login_page_title' => 'nullable|string|max:200',
            'login_page_subtitle' => 'nullable|string|max:500',
            'login_bg_image' => 'nullable|string|max:500',
            'footer_text' => 'nullable|string|max:500',
            'links' => 'nullable|array',
            'social_links' => 'nullable|array',
            'locale' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__("app.portal_branding.msg_e441b11e"), $validator->errors()->toArray());
        }

        $data = $validator->validated();
        $locale = $data['locale'] ?? 'zh-CN';

        $config = $this->brandingService->updateConfig(
            $request->user()->tenant_id,
            $locale,
            $validator->safe()->except(['locale'])
        );

        return ApiResponse::success($config, __("app.portal_branding.msg_009a1589"));
    }

    /**
     * 重置为默认配置
     */
    public function reset(Request $request)
    {
        $config = $this->brandingService->resetToDefault(
            $request->user()->tenant_id,
            $request->input('locale', 'zh-CN')
        );

        return ApiResponse::success($config, __("app.portal_branding.msg_0fd47041"));
    }

    /**
     * 获取主题模板列表
     */
    public function themeTemplates()
    {
        return ApiResponse::success($this->brandingService->getThemeTemplates());
    }

    /**
     * 应用主题模板
     */
    public function applyTheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'theme_id' => 'required|string',
            'locale' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError(__('app.common.validation_failed'), $validator->errors()->toArray());
        }

        $templates = $this->brandingService->getThemeTemplates();
        $theme = collect($templates)->firstWhere('id', $request->input('theme_id'));

        if (!$theme) {
            return ApiResponse::error('INVALID_THEME', __("app.portal_branding.msg_6fe887db"), 400);
        }

        $config = $this->brandingService->updateConfig(
            $request->user()->tenant_id,
            $request->input('locale', 'zh-CN'),
            [
                'primary_color' => $theme['primary_color'],
                'secondary_color' => $theme['secondary_color'],
                'background_color' => $theme['background_color'],
                'text_color' => $theme['text_color'],
                'sidebar_bg_color' => $theme['sidebar_bg_color'],
            ]
        );

        return ApiResponse::success($config, __("app.portal_branding.msg_06734c9d"));
    }

    /**
     * 公开获取品牌数据（无需认证，按域名解析tenant）
     */
    public function publicBranding(Request $request)
    {
        $domain = $request->input('domain', $request->getHost());
        $tenantId = null;

        // 通过自定义域名解析租户
        $customDomain = CustomDomain::where('domain', $domain)
            ->where('verified', true)
            ->where('is_active', true)
            ->first();

        if ($customDomain) {
            $tenantId = $customDomain->tenant_id;
        }

        $data = $this->brandingService->getBrandingData(
            $tenantId,
            $request->input('locale', 'zh-CN'),
        );

        return ApiResponse::success($data);
    }
}
