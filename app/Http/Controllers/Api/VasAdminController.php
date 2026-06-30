<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\VasService;
use App\Models\VasSubscription;
use App\Services\VasAdminService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VasAdminController extends Controller
{
    public function __construct(
        protected VasAdminService $vasService
    ) {}

    // ─── 服务目录 ───

    public function services(Request $request)
    {
        return ApiResponse::success(
            $this->vasService->listServices($request->user()->tenant_id, $request->only(['category', 'is_active', 'is_public', 'search']))
        );
    }

    public function showService(int $id)
    {
        return ApiResponse::success($this->vasService->getService($id));
    }

    public function storeService(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'nullable|string|max:80|unique:vas_services,code',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:feature,support,storage,api,ai',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'billing_mode' => 'nullable|string|in:flat,usage,tiered',
            'metered_config' => 'nullable|array',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        $data = $request->all();
        $data['tenant_id'] = $request->user()->tenant_id;

        return ApiResponse::success($this->vasService->createService($data), 201);
    }

    public function updateService(Request $request, VasService $vasService)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'category' => 'nullable|string|in:feature,support,storage,api,ai',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_yearly' => 'nullable|numeric|min:0',
            'billing_mode' => 'nullable|string|in:flat,usage,tiered',
            'metered_config' => 'nullable|array',
            'features' => 'nullable|array',
            'limits' => 'nullable|array',
            'is_public' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        return ApiResponse::success($this->vasService->updateService($vasService, $request->all()));
    }

    public function destroyService(VasService $vasService)
    {
        $this->vasService->deleteService($vasService);
        return ApiResponse::success(['deleted' => true]);
    }

    // ─── 开通管理 ───

    public function subscriptions(Request $request)
    {
        return ApiResponse::success(
            $this->vasService->listSubscriptions(
                $request->user()->tenant_id,
                $request->only(['status', 'vas_service_id']),
                $request->input('per_page', 20)
            )
        );
    }

    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'vas_service_id' => 'required|integer|exists:vas_services,id',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'billing_period' => 'nullable|string|in:monthly,yearly,one_time',
            'customer_id' => 'nullable|integer|exists:customers,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::success(['errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->vasService->subscribe(
                $request->user()->tenant_id,
                $request->input('vas_service_id'),
                $request->only(['billing_period', 'subscription_id', 'customer_id', 'end_date']),
            );
            return ApiResponse::success($result, 201);
        } catch (\RuntimeException $e) {
            return ApiResponse::success(['error' => $e->getMessage()], 409);
        }
    }

    public function cancelSubscription(Request $request, VasSubscription $vasSubscription)
    {
        return ApiResponse::success(
            $this->vasService->cancelSubscription($vasSubscription->id, $request->input('reason'))
        );
    }

    // ─── 统计 ───

    public function stats(Request $request)
    {
        return ApiResponse::success($this->vasService->getStats($request->user()->tenant_id));
    }

    // ─── 门户：市场 ───

    public function marketplace(Request $request)
    {
        return ApiResponse::success($this->vasService->getMarketplace($request->user()->tenant_id));
    }

    // ─── 元数据 ───

    public function categories()
    {
        return ApiResponse::success(VasService::CATEGORIES);
    }

    public function billingModes()
    {
        return ApiResponse::success(VasService::BILLING_MODE_LABELS);
    }
}
