<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Subscription;
use App\Models\VasService;
use App\Models\VasSubscription;
use Illuminate\Support\Facades\DB;

/**
 * 增值服务 (VAS) 管理服务
 *
 * 管理增值服务产品目录、开通、取消、统计等生命周期。
 * 复用已有的 MeteredBilling + Pricing 基础设施进行价格计算。
 */
class VasAdminService
{
    // ─── 服务目录管理 ───

    public function listServices(int $tenantId, array $filters = [])
    {
        $query = VasService::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (!empty($filters['category'])) $query->where('category', $filters['category']);
        if (!empty($filters['is_active'])) $query->where('is_active', $filters['is_active'] === 'true');
        if (!empty($filters['is_public'])) $query->where('is_public', $filters['is_public'] === 'true');
        if (!empty($filters['search'])) $query->where(function ($q) use ($filters) {
            $q->where('name', 'like', "%{$filters['search']}%")
              ->orWhere('code', 'like', "%{$filters['search']}%");
        });

        return $query->get();
    }

    public function createService(array $data): VasService
    {
        if (empty($data['code'])) {
            $data['code'] = 'vas_' . str()->random(8);
        }
        return VasService::create($data);
    }

    public function updateService(VasService $service, array $data): VasService
    {
        $service->update($data);
        return $service->fresh();
    }

    public function deleteService(VasService $service): void
    {
        $service->subscriptions()->delete();
        $service->delete();
    }

    public function getService(int $id): VasService
    {
        return VasService::withCount('activeSubscriptions')->findOrFail($id);
    }

    // ─── 开通管理 ───

    public function subscribe(int $tenantId, int $vasServiceId, array $params): VasSubscription
    {
        $service = VasService::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->findOrFail($vasServiceId);

        $billingPeriod = $params['billing_period'] ?? 'monthly';
        $price = $billingPeriod === 'yearly' ? $service->price_yearly : $service->price_monthly;
        $subscriptionId = $params['subscription_id'] ?? null;

        // 检查是否已开通
        $existing = VasSubscription::where('vas_service_id', $service->id)
            ->where('tenant_id', $tenantId)
            ->where('subscription_id', $subscriptionId)
            ->whereIn('status', ['active', 'suspended'])
            ->first();

        if ($existing) {
            throw new \RuntimeException(__("app.vas_admin.vas_already_subscribed"));
        }

        return VasSubscription::create([
            'tenant_id' => $tenantId,
            'vas_service_id' => $service->id,
            'subscription_id' => $subscriptionId,
            'customer_id' => $params['customer_id'] ?? null,
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => $params['end_date'] ?? null,
            'billing_period' => $billingPeriod,
            'price' => $price,
            'currency' => $service->currency,
            'applied_features' => $service->features,
            'usage_limits' => $service->limits,
        ]);
    }

    public function cancelSubscription(int $id, string $reason = null): VasSubscription
    {
        $sub = VasSubscription::findOrFail($id);
        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);
        return $sub->fresh(['vasService']);
    }

    // ─── 查询 ───

    public function listSubscriptions(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = VasSubscription::with(['vasService:id,code,name,category'])
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['vas_service_id'])) $query->where('vas_service_id', $filters['vas_service_id']);

        return $query->paginate($perPage);
    }

    // ─── 统计 ───

    public function getStats(int $tenantId): array
    {
        $query = fn($q) => $q->where('tenant_id', $tenantId);

        $totalServices = VasService::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $totalSubs = VasSubscription::where('tenant_id', $tenantId)->count();
        $activeSubs = VasSubscription::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $revenue = VasSubscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active'])
            ->sum('price');

        $byCategory = VasService::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount('activeSubscriptions')
            ->get()
            ->groupBy('category')
            ->map(fn($items, $cat) => [
                'category' => $cat,
                'count' => $items->count(),
                'subscriptions' => $items->sum('active_subscriptions_count'),
            ])
            ->values();

        $topServices = VasService::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount('activeSubscriptions')
            ->orderByDesc('active_subscriptions_count')
            ->limit(5)
            ->get();

        return [
            'total_services' => $totalServices,
            'total_subscriptions' => $totalSubs,
            'active_subscriptions' => $activeSubs,
            'monthly_revenue' => round($revenue, 2),
            'by_category' => $byCategory,
            'top_services' => $topServices,
        ];
    }

    // ─── 门户：公开市场 ───

    public function getMarketplace(int $tenantId): array
    {
        $services = VasService::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $activeCodes = VasSubscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active'])
            ->pluck('vas_service_id')
            ->toArray();

        return [
            'services' => $services->toArray(),
            'active_service_ids' => $activeCodes,
        ];
    }
}
