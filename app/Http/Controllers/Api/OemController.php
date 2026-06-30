<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\OemService;
use App\Services\PortalBrandingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * OEM 白标系统 API (M3-03)
 */
class OemController extends Controller
{
    public function __construct(
        protected OemService $oemService,
        protected PortalBrandingService $brandingService,
    ) {}

    /**
     * OEM 仪表盘
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->oemService->getDashboard($tenantId));
    }

    /**
     * 获取套餐列表
     */
    public function tiers(): JsonResponse
    {
        return ApiResponse::success($this->oemService->getTiers());
    }

    /**
     * 订阅/升级 OEM 套餐
     */
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tier' => 'required|string|in:basic,business,enterprise',
            'billing_period' => 'nullable|string|in:monthly,yearly',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;

        try {
            $subscription = $this->oemService->subscribe($tenantId, $request->tier, [
                'billing_period' => $request->billing_period ?? 'monthly',
                'reason' => $request->reason,
                'operated_by' => $request->user()->id,
            ]);

            return ApiResponse::success($subscription, '套餐订阅成功');
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_TIER', $e->getMessage(), 400);
        } catch (\Exception $e) {
            return ApiResponse::error('SUBSCRIBE_FAILED', '套餐订阅失败', 500);
        }
    }

    /**
     * 取消 OEM 套餐
     */
    public function cancel(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $reason = $request->input('reason');

        $success = $this->oemService->cancel($tenantId, $reason);

        return $success
            ? ApiResponse::success(null, '套餐已取消')
            : ApiResponse::error('NO_SUBSCRIPTION', '未找到活跃套餐', 404);
    }

    /**
     * 获取变更历史
     */
    public function history(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success($this->oemService->getChangeHistory($tenantId));
    }

    /**
     * 检查功能权限
     */
    public function checkFeature(Request $request): JsonResponse
    {
        $request->validate(['feature' => 'required|string']);

        $tenantId = $request->user()->tenant_id;
        $canUse = $this->oemService->canUseFeature($tenantId, $request->feature);

        return ApiResponse::success([
            'feature' => $request->feature,
            'can_use' => $canUse,
        ]);
    }

    /**
     * 保存品牌化登录页配置 (M3-47)
     */
    public function saveBrandedLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'login_page_title' => 'nullable|string|max:200',
            'login_page_subtitle' => 'nullable|string|max:500',
            'login_bg_image' => 'nullable|string|max:500',
            'logo_url' => 'nullable|string|max:500',
            'primary_color' => 'nullable|string|max:20',
            'locale' => 'nullable|string|max:10',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError('验证失败', $validator->errors()->toArray());
        }

        $tenantId = $request->user()->tenant_id;
        $locale = $request->input('locale', 'zh-CN');

        $config = $this->brandingService->updateConfig($tenantId, $locale, $validator->safe()->except(['locale']));

        return ApiResponse::success($config, '登录页配置已更新');
    }

    /**
     * 获取品牌化登录页配置 (公开 — 用于自定义域名下的登录页)
     */
    public function getBrandedLogin(Request $request): JsonResponse
    {
        $domain = $request->getHost();

        // 通过域名查找租户
        $customDomain = \App\Models\CustomDomain::where('domain', $domain)
            ->where('verified', true)
            ->where('is_active', true)
            ->first();

        if (!$customDomain || !$customDomain->tenant_id) {
            // 返回平台默认配置
            $branding = $this->brandingService->getBrandingData(null);
            return ApiResponse::success($branding);
        }

        $branding = $this->brandingService->getBrandingData($customDomain->tenant_id);
        return ApiResponse::success($branding);
    }
}
