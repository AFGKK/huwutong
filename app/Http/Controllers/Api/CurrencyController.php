<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerCurrencyPreference;
use App\Models\ExchangeRate;
use App\Models\PricingPlan;
use App\Models\PricingPlanPrice;
use App\Models\Subscription;
use App\Services\CurrencyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * 获取所有受支持的货币
     */
    public function currencies(): JsonResponse
    {
        return response()->json([
            'data' => $this->currencyService->getSupportedCurrencies(),
        ]);
    }

    /**
     * 获取当前有效的汇率列表
     */
    public function rates(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        return response()->json([
            'data' => $this->currencyService->getActiveRates($tenantId),
        ]);
    }

    /**
     * 设置/更新汇率
     */
    public function setRate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'from_currency' => 'required|string|size:3',
            'to_currency' => 'required|string|size:3|different:from_currency',
            'rate' => 'required|numeric|min:0.00000001',
            'provider' => 'nullable|string|max:30',
            'effective_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after:effective_at',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $tenantId = $request->user()->tenant_id;
        $data = $validator->validated();

        $exchangeRate = $this->currencyService->setRate(
            $data['from_currency'],
            $data['to_currency'],
            (float) $data['rate'],
            $tenantId,
            $data['provider'] ?? 'manual',
            $data['effective_at'] ?? null,
            $data['expires_at'] ?? null,
        );

        return response()->json([
            'message' => __('app.controller_compat.currency_msg_77'),
            'data' => $exchangeRate,
        ], 201);
    }

    /**
     * 删除汇率
     */
    public function deleteRate(int $id): JsonResponse
    {
        $rate = ExchangeRate::findOrFail($id);
        $rate->delete();

        $this->currencyService->clearRateCache($rate->from_currency, $rate->to_currency);

        return response()->json(['message' => __('app.controller_compat.currency_msg_92')]);
    }

    /**
     * 货币转换
     */
    public function convert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;

        $result = $this->currencyService->convert(
            (float) $data['amount'],
            $data['from'],
            $data['to'],
            $tenantId
        );

        return response()->json(['data' => $result]);
    }

    /**
     * 批量货币转换
     */
    public function batchConvert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amounts' => 'required|array|min:1|max:100',
            'amounts.*' => 'numeric|min:0',
            'from' => 'required|string|size:3',
            'to' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;

        $results = $this->currencyService->batchConvert(
            $data['amounts'],
            $data['from'],
            $data['to'],
            $tenantId
        );

        return response()->json(['data' => $results]);
    }

    /**
     * 从外部同步汇率
     */
    public function syncRates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'provider' => 'nullable|string|in:ecb,manual',
        ]);

        $provider = $validator->validated()['provider'] ?? 'ecb';
        $tenantId = $request->user()->tenant_id;

        $count = $this->currencyService->syncRatesFromProvider($provider, $tenantId);

        return response()->json([
            'message' => "汇率同步完成，共 {$count} 条",
            'count' => $count,
        ]);
    }

    // ─── 定价计划管理 ───

    /**
     * 获取所有定价计划（含多币种价格）
     */
    public function pricingPlans(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $customerId = $request->get('customer_id');

        $customer = $customerId ? Customer::find($customerId) : null;

        $plans = $this->currencyService->getPricingPlansForCustomer($customer, $tenantId);

        return response()->json(['data' => $plans]);
    }

    /**
     * 创建定价计划
     */
    public function createPricingPlan(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'slug' => 'required|string|max:100|unique:pricing_plans,slug',
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'billing_period' => 'required|string|in:monthly,yearly,one_time',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'prices' => 'required|array|min:1',
            'prices.*.currency' => 'required|string|size:3',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.setup_fee' => 'nullable|numeric|min:0',
            'prices.*.trial_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $tenantId = $request->user()->tenant_id;

        $plan = PricingPlan::create([
            'tenant_id' => $tenantId,
            'product_id' => $data['product_id'] ?? null,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'billing_period' => $data['billing_period'],
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        foreach ($data['prices'] as $priceData) {
            $plan->prices()->create([
                'currency' => strtoupper($priceData['currency']),
                'price' => $priceData['price'],
                'setup_fee' => $priceData['setup_fee'] ?? 0,
                'trial_price' => $priceData['trial_price'] ?? null,
            ]);
        }

        $plan->load('prices');

        return response()->json([
            'message' => __('app.controller_compat.currency_msg_239'),
            'data' => $plan,
        ], 201);
    }

    /**
     * 更新定价计划
     */
    public function updatePricingPlan(Request $request, int $id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|exists:products,id',
            'slug' => "required|string|max:100|unique:pricing_plans,slug,{$id}",
            'name' => 'required|string|max:200',
            'description' => 'nullable|string',
            'billing_period' => 'required|string|in:monthly,yearly,one_time',
            'is_active' => 'boolean',
            'sort_order' => 'integer|min:0',
            'prices' => 'sometimes|array|min:1',
            'prices.*.currency' => 'required|string|size:3',
            'prices.*.price' => 'required|numeric|min:0',
            'prices.*.setup_fee' => 'nullable|numeric|min:0',
            'prices.*.trial_price' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();

        $plan->update([
            'product_id' => $data['product_id'] ?? $plan->product_id,
            'slug' => $data['slug'],
            'name' => $data['name'],
            'description' => $data['description'] ?? $plan->description,
            'billing_period' => $data['billing_period'],
            'is_active' => $data['is_active'] ?? $plan->is_active,
            'sort_order' => $data['sort_order'] ?? $plan->sort_order,
        ]);

        if (isset($data['prices'])) {
            $plan->prices()->delete();
            foreach ($data['prices'] as $priceData) {
                $plan->prices()->create([
                    'currency' => strtoupper($priceData['currency']),
                    'price' => $priceData['price'],
                    'setup_fee' => $priceData['setup_fee'] ?? 0,
                    'trial_price' => $priceData['trial_price'] ?? null,
                ]);
            }
        }

        $plan->load('prices');

        return response()->json([
            'message' => __('app.controller_compat.currency_msg_297'),
            'data' => $plan,
        ]);
    }

    /**
     * 删除定价计划
     */
    public function deletePricingPlan(int $id): JsonResponse
    {
        $plan = PricingPlan::findOrFail($id);
        $plan->prices()->delete();
        $plan->delete();

        return response()->json(['message' => __('app.controller_compat.currency_msg_311')]);
    }

    // ─── 客户货币偏好 ───

    /**
     * 获取客户货币偏好
     */
    public function customerPreference(Request $request): JsonResponse
    {
        $customerId = $request->get('customer_id', $request->user()->id);
        $tenantId = $request->user()->tenant_id;

        $pref = CustomerCurrencyPreference::where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$pref) {
            return response()->json([
                'data' => [
                    'preferred_currency' => 'CNY',
                    'display_currency' => 'CNY',
                    'accepted_currencies' => ['CNY', 'USD'],
                ],
            ]);
        }

        return response()->json(['data' => $pref]);
    }

    /**
     * 更新客户货币偏好
     */
    public function updateCustomerPreference(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'preferred_currency' => 'required|string|size:3',
            'display_currency' => 'nullable|string|size:3',
            'accepted_currencies' => 'nullable|array',
            'accepted_currencies.*' => 'string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $customer = Customer::findOrFail($data['customer_id']);

        $pref = $this->currencyService->setCustomerPreference(
            $customer,
            $data['preferred_currency'],
            $data['display_currency'] ?? null,
            $data['accepted_currencies'] ?? null,
        );

        return response()->json([
            'message' => __('app.controller_compat.currency_msg_369'),
            'data' => $pref,
        ]);
    }

    /**
     * 获取订阅在客户偏好货币下的显示金额
     */
    public function subscriptionDisplayAmount(int $subscriptionId): JsonResponse
    {
        $subscription = Subscription::with('customer')->findOrFail($subscriptionId);
        $display = $this->currencyService->getSubscriptionDisplayAmount($subscription);

        return response()->json(['data' => $display]);
    }
}
