<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CookieConsentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CookieConsentController extends Controller
{
    public function __construct(
        protected CookieConsentService $cookieService,
    ) {}

    /**
     * 获取 Cookie 配置（公开端点，供横幅渲染）
     */
    public function config(): JsonResponse
    {
        $config = $this->cookieService->getConfig();

        return ApiResponse::success([
            'is_active' => $config->is_active,
            'position' => $config->position,
            'title' => $config->title,
            'description' => $config->description,
            'accept_all_text' => $config->accept_all_text,
            'reject_all_text' => $config->reject_all_text,
            'customize_text' => $config->customize_text,
            'privacy_policy_url' => $config->privacy_policy_url,
            'privacy_policy_text' => $config->privacy_policy_text,
            'categories' => $config->categories ?? CookieConsentConfig::defaultCategories(),
            'consent_lifetime_days' => $config->consent_lifetime_days,
            'theme' => $config->theme,
            'layout' => $config->layout,
        ]);
    }

    /**
     * 记录用户同意
     */
    public function consent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:accepted,rejected,customized',
            'selected_categories' => 'nullable|array',
            'selected_categories.*' => 'string',
        ]);

        $this->cookieService->recordConsent(
            userId: $request->user()?->id,
            ip: $request->ip(),
            action: $validated['action'],
            selectedCategories: $validated['selected_categories'] ?? null,
            userAgent: $request->userAgent(),
        );

        return ApiResponse::success(null, '同意记录已保存');
    }

    /**
     * 获取配置完整信息（管理用）
     */
    public function showConfig(): JsonResponse
    {
        $config = $this->cookieService->getConfig();

        return ApiResponse::success($config);
    }

    /**
     * 更新配置
     */
    public function updateConfig(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'is_active' => 'sometimes|boolean',
            'position' => 'sometimes|in:top,bottom,center',
            'title' => 'sometimes|string|max:200',
            'description' => 'nullable|string',
            'accept_all_text' => 'sometimes|string|max:100',
            'reject_all_text' => 'sometimes|string|max:100',
            'customize_text' => 'sometimes|string|max:100',
            'privacy_policy_url' => 'nullable|string|max:500',
            'privacy_policy_text' => 'sometimes|string|max:100',
            'categories' => 'nullable|array',
            'categories.*.id' => 'required|string',
            'categories.*.name' => 'required|string',
            'categories.*.description' => 'nullable|string',
            'categories.*.required' => 'required|boolean',
            'categories.*.default' => 'required|boolean',
            'consent_lifetime_days' => 'sometimes|integer|min:1|max:1825',
            'theme' => 'sometimes|in:light,dark,auto',
            'layout' => 'sometimes|in:bar,modal,floating',
        ]);

        $config = $this->cookieService->updateConfig($validated);

        return ApiResponse::success($config, 'Cookie 配置已更新');
    }

    /**
     * 获取同意日志
     */
    public function logs(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 20), 100);
        $logs = $this->cookieService->getLogs($perPage);

        return ApiResponse::paginated($logs);
    }

    /**
     * 获取统计
     */
    public function stats(): JsonResponse
    {
        $stats = $this->cookieService->getStats();

        return ApiResponse::success($stats);
    }
}
