<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AutoRenewalPlan;
use App\Models\AutoRenewalSubscription;
use App\Services\AutoRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutoRenewalController extends Controller
{
    public function __construct(protected AutoRenewalService $service) {}

    public function dashboard(Request $request): JsonResponse
    {
        return ApiResponse::success($this->service->getDashboard($request->user()->tenant_id));
    }

    public function plans(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            AutoRenewalPlan::with('product')
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function storePlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:200',
            'billing_period' => 'required|in:monthly,quarterly,semi_annually,annually',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'trial_days' => 'nullable|integer|min:0|max:90',
            'grace_days' => 'nullable|integer|min:0|max:30',
            'upgrade_paths' => 'nullable|array',
            'downgrade_paths' => 'nullable|array',
        ]);

        $validated['tenant_id'] = $request->user()->tenant_id;
        $plan = AutoRenewalPlan::create($validated);
        return ApiResponse::created($plan, __("app.auto_renewal.msg_b3f4deb8"));
    }

    public function updatePlan(Request $request, AutoRenewalPlan $plan): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:200',
            'price' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
            'upgrade_paths' => 'nullable|array',
            'downgrade_paths' => 'nullable|array',
        ]);

        $plan->update($validated);
        return ApiResponse::success($plan, __("app.auto_renewal.msg_f7fca7cf"));
    }

    public function subscriptions(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            AutoRenewalSubscription::with(['plan', 'customer', 'license'])
                ->where('tenant_id', $request->user()->tenant_id)
                ->orderByDesc('created_at')
                ->paginate($request->input('per_page', 20))
        );
    }

    public function renew(Request $request, AutoRenewalSubscription $subscription): JsonResponse
    {
        $result = $this->service->renew($subscription);
        return $result['success']
            ? ApiResponse::success($result, __("app.auto_renewal.msg_d0b819cf"))
            : ApiResponse::error('RENEW_FAILED', $result['message'], 400);
    }

    public function upgrade(Request $request, AutoRenewalSubscription $subscription): JsonResponse
    {
        $request->validate(['target_plan_id' => 'required|integer|exists:auto_renewal_plans,id']);
        $result = $this->service->upgrade($subscription, $request->target_plan_id);
        return $result['success']
            ? ApiResponse::success($result, __("app.auto_renewal.msg_e64d788d"))
            : ApiResponse::error('UPGRADE_FAILED', $result['message'], 400);
    }

    public function downgrade(Request $request, AutoRenewalSubscription $subscription): JsonResponse
    {
        $request->validate(['target_plan_id' => 'required|integer|exists:auto_renewal_plans,id']);
        $result = $this->service->downgrade($subscription, $request->target_plan_id);
        return $result['success']
            ? ApiResponse::success($result, __("app.auto_renewal.msg_e6a78b6a"))
            : ApiResponse::error('DOWNGRADE_FAILED', $result['message'], 400);
    }

    public function cancel(AutoRenewalSubscription $subscription): JsonResponse
    {
        $this->service->cancel($subscription);
        return ApiResponse::success(null, __("app.auto_renewal.msg_4222360d"));
    }

    public function pause(AutoRenewalSubscription $subscription): JsonResponse
    {
        $this->service->pause($subscription);
        return ApiResponse::success(null, __("app.auto_renewal.msg_fa3cfb12"));
    }

    public function resume(AutoRenewalSubscription $subscription): JsonResponse
    {
        $this->service->resume($subscription);
        return ApiResponse::success(null, __("app.auto_renewal.msg_aad72f05"));
    }

    public function attempts(AutoRenewalSubscription $subscription): JsonResponse
    {
        return ApiResponse::success(
            $subscription->attempts()->latest()->get()
        );
    }
}
