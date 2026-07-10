<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MeteredTieredPricing;
use App\Models\MeteredTierPricingTier;
use App\Models\MeteredBillingAlert;
use App\Models\MeteredAlertHistory;
use App\Models\MeteredAutoSwitchRule;
use App\Services\MeteredBillingService;
use Illuminate\Http\Request;

class MeteredBillingDeepController extends Controller
{
    public function __construct(protected MeteredBillingService $meteredBilling) {}

    // ── 分层定价 ──

    public function tieredPricings()
    {
        $pricings = MeteredTieredPricing::with('tiers')->orderByDesc('created_at')->get();
        return response()->json(['success' => true, 'data' => $pricings]);
    }

    public function storeTieredPricing(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'metric_key' => 'required|string|max:100',
            'product_id' => 'nullable|integer|exists:products,id',
            'billing_period' => 'required|in:monthly,yearly,one_time',
            'tier_type' => 'required|in:volume,graduated',
            'tiers' => 'required|array|min:1',
            'tiers.*.from_unit' => 'required|integer|min:0',
            'tiers.*.to_unit' => 'nullable|integer|gte:tiers.*.from_unit',
            'tiers.*.unit_price' => 'required|numeric|min:0',
            'tiers.*.price_model' => 'required|in:per_unit,flat',
            'tiers.*.flat_fee' => 'nullable|numeric|min:0',
        ]);

        $pricing = MeteredTieredPricing::create([
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'product_id' => $validated['product_id'] ?? null,
            'metric_key' => $validated['metric_key'],
            'name' => $validated['name'],
            'billing_period' => $validated['billing_period'],
            'tier_type' => $validated['tier_type'],
        ]);

        foreach ($validated['tiers'] as $tier) {
            $pricing->tiers()->create($tier);
        }

        return response()->json(['success' => true, 'data' => $pricing->load('tiers')], 201);
    }

    public function updateTieredPricing(Request $request, MeteredTieredPricing $meteredTieredPricing)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'is_active' => 'boolean',
        ]);
        $meteredTieredPricing->update($validated);
        return response()->json(['success' => true, 'data' => $meteredTieredPricing->load('tiers')]);
    }

    public function destroyTieredPricing(MeteredTieredPricing $meteredTieredPricing)
    {
        $meteredTieredPricing->tiers()->delete();
        $meteredTieredPricing->delete();
        return response()->json(['success' => true]);
    }

    // ── 超额预警 ──

    public function alerts()
    {
        $alerts = MeteredBillingAlert::with('subscription:id,pricing_plan_slug')
            ->withCount('histories')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $alerts]);
    }

    public function storeAlert(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'metric_key' => 'required|string|max:100',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'threshold_value' => 'required|numeric|min:0',
            'threshold_type' => 'required|in:quantity,amount,percentage',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'direction' => 'required|in:above,below',
            'window_type' => 'required|in:billing_period,daily,monthly',
            'notify_channels' => 'nullable|array',
            'notify_channels.*' => 'string|in:email,sms,webhook',
        ]);

        $alert = MeteredBillingAlert::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
        ]));

        return response()->json(['success' => true, 'data' => $alert], 201);
    }

    public function updateAlert(Request $request, MeteredBillingAlert $meteredBillingAlert)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'threshold_value' => 'sometimes|numeric|min:0',
            'percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'notify_channels' => 'nullable|array',
        ]);
        $meteredBillingAlert->update($validated);
        return response()->json(['success' => true, 'data' => $meteredBillingAlert]);
    }

    public function destroyAlert(MeteredBillingAlert $meteredBillingAlert)
    {
        $meteredBillingAlert->histories()->delete();
        $meteredBillingAlert->delete();
        return response()->json(['success' => true]);
    }

    public function alertHistories(MeteredBillingAlert $meteredBillingAlert)
    {
        $histories = $meteredBillingAlert->histories()->orderByDesc('triggered_at')->paginate(20);
        return response()->json(['success' => true, 'data' => $histories]);
    }

    // ── 自动切换套餐 ──

    public function autoSwitchRules()
    {
        $rules = MeteredAutoSwitchRule::with('subscription:id,pricing_plan_slug')
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $rules]);
    }

    public function storeAutoSwitchRule(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'metric_key' => 'required|string|max:100',
            'subscription_id' => 'nullable|integer|exists:subscriptions,id',
            'condition_type' => 'required|in:usage_consecutive,usage_average,spend_threshold',
            'condition_value' => 'required|numeric|min:0',
            'condition_days' => 'required|integer|min:1|max:90',
            'action' => 'required|in:upgrade,downgrade',
            'target_plan_slug' => 'required|string|max:100',
            'require_confirmation' => 'boolean',
        ]);

        $rule = MeteredAutoSwitchRule::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id ?? 1,
            'require_confirmation' => $validated['require_confirmation'] ?? true,
        ]));

        return response()->json(['success' => true, 'data' => $rule], 201);
    }

    public function updateAutoSwitchRule(Request $request, MeteredAutoSwitchRule $meteredAutoSwitchRule)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:100',
            'condition_value' => 'sometimes|numeric|min:0',
            'condition_days' => 'sometimes|integer|min:1|max:90',
            'is_active' => 'boolean',
            'require_confirmation' => 'boolean',
        ]);
        $meteredAutoSwitchRule->update($validated);
        return response()->json(['success' => true, 'data' => $meteredAutoSwitchRule]);
    }

    public function destroyAutoSwitchRule(MeteredAutoSwitchRule $meteredAutoSwitchRule)
    {
        $meteredAutoSwitchRule->delete();
        return response()->json(['success' => true]);
    }

    // ── 执行操作 ──

    public function evaluateAlerts(Request $request)
    {
        $result = $this->meteredBilling->evaluateAlerts($request->user()->tenant_id ?? 1);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function evaluateAutoSwitch(Request $request)
    {
        $result = $this->meteredBilling->evaluateAutoSwitchRules($request->user()->tenant_id ?? 1);
        return response()->json(['success' => true, 'data' => $result]);
    }

    public function stats()
    {
        $tenantId = request()->user()->tenant_id ?? 1;

        return response()->json([
            'success' => true,
            'data' => [
                'total_pricings' => MeteredTieredPricing::where('tenant_id', $tenantId)->count(),
                'active_pricings' => MeteredTieredPricing::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'total_alerts' => MeteredBillingAlert::where('tenant_id', $tenantId)->count(),
                'active_alerts' => MeteredBillingAlert::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'total_switch_rules' => MeteredAutoSwitchRule::where('tenant_id', $tenantId)->count(),
                'active_switch_rules' => MeteredAutoSwitchRule::where('tenant_id', $tenantId)->where('is_active', true)->count(),
                'total_alert_histories' => MeteredAlertHistory::where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }
}
