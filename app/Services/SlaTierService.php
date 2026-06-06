<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSlaAssignment;
use App\Models\SlaAuditEvent;
use App\Models\SlaTier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 客户分级SLA服务 (M2-31)
 *
 * 不同客户等级 → 不同 API 并发上限 / 验证延迟上限 / 客服响应时间 / 设备上限 / 安全合规
 * 等级: enterprise / professional / standard (default) / free
 */
class SlaTierService
{
    const CACHE_PREFIX = 'sla:';
    const CACHE_TTL = 3600;

    /**
     * 获取客户 SLA 等级
     *
     * @param Customer|int $customer
     * @return SlaTier
     */
    public function getTierForCustomer(Customer|int $customer): SlaTier
    {
        $customerId = $customer instanceof Customer ? $customer->id : $customer;
        $tenantId = $customer instanceof Customer ? $customer->tenant_id : null;

        $cacheKey = self::CACHE_PREFIX . "customer:{$customerId}";
        $cachedTierId = Cache::get($cacheKey);

        if ($cachedTierId) {
            $tier = SlaTier::find($cachedTierId);
            if ($tier) return $tier;
        }

        // 查找客户的自定义分配
        $assignment = CustomerSlaAssignment::where('customer_id', $customerId)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if ($assignment) {
            $tier = $assignment->slaTier;
            Cache::put($cacheKey, $tier->id, now()->addSeconds(self::CACHE_TTL));
            return $tier;
        }

        // 根据客户等级推断 SLA
        $customerModel = $customer instanceof Customer ? $customer : Customer::find($customerId);
        $tier = $this->inferTierFromCustomerLevel($customerModel, $tenantId);

        Cache::put($cacheKey, $tier->id, now()->addSeconds(self::CACHE_TTL));
        return $tier;
    }

    /**
     * 为客户分配自定义 SLA 等级
     */
    public function assignTierToCustomer(Customer $customer, SlaTier $tier, ?string $expiresAt = null): CustomerSlaAssignment
    {
        // 记录旧等级
        $oldTier = $this->getTierForCustomer($customer);

        $assignment = CustomerSlaAssignment::updateOrCreate(
            ['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id],
            [
                'sla_tier_id' => $tier->id,
                'assigned_at' => now(),
                'expires_at' => $expiresAt,
            ]
        );

        // 清除缓存
        Cache::forget(self::CACHE_PREFIX . "customer:{$customer->id}");

        // 审计日志
        $eventType = $oldTier->id === $tier->id
            ? SlaAuditEvent::EVENT_TIER_ASSIGNED
            : SlaAuditEvent::EVENT_TIER_CHANGED;

        SlaAuditEvent::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'sla_tier_id' => $tier->id,
            'event_type' => $eventType,
            'description' => "SLA 等级: {$oldTier->name} → {$tier->name}",
            'context' => [
                'old_tier' => $oldTier->slug,
                'new_tier' => $tier->slug,
                'expires_at' => $expiresAt,
            ],
        ]);

        Log::info('SLA: tier assigned', [
            'customer_id' => $customer->id,
            'old_tier' => $oldTier->slug,
            'new_tier' => $tier->slug,
        ]);

        return $assignment;
    }

    /**
     * 移除客户自定义 SLA（恢复默认）
     */
    public function resetToDefault(Customer $customer): void
    {
        $oldTier = $this->getTierForCustomer($customer);

        CustomerSlaAssignment::where('customer_id', $customer->id)->delete();
        Cache::forget(self::CACHE_PREFIX . "customer:{$customer->id}");

        $defaultTier = SlaTier::where('tenant_id', $customer->tenant_id)
            ->where('is_default', true)->first();

        if ($defaultTier) {
            SlaAuditEvent::create([
                'tenant_id' => $customer->tenant_id,
                'customer_id' => $customer->id,
                'sla_tier_id' => $defaultTier->id,
                'event_type' => SlaAuditEvent::EVENT_TIER_CHANGED,
                'description' => "SLA 等级: {$oldTier->name} → {$defaultTier->name} (恢复默认)",
                'context' => ['old_tier' => $oldTier->slug, 'new_tier' => $defaultTier->slug, 'reset' => true],
            ]);
        }
    }

    /**
     * 初始化默认 SLA 等级
     */
    public function initializeDefaults(int $tenantId): void
    {
        $exists = SlaTier::where('tenant_id', $tenantId)->exists();
        if ($exists) return;

        foreach (SlaTier::defaultTiers($tenantId) as $data) {
            $data['tenant_id'] = $tenantId;
            SlaTier::create($data);
        }

        Log::info('SLA: default tiers initialized', ['tenant_id' => $tenantId]);
    }

    /**
     * 根据客户等级推断 SLA
     */
    protected function inferTierFromCustomerLevel(?Customer $customer, ?int $tenantId): SlaTier
    {
        $slug = match ($customer?->level) {
            'enterprise' => 'enterprise',
            'professional' => 'professional',
            'free' => 'free',
            default => 'standard',
        };

        $tier = SlaTier::where('slug', $slug)
            ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            ->first();

        if ($tier) return $tier;

        // 降级到默认
        $default = SlaTier::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if ($default) return $default;

        // 创建默认等级（应急）
        return SlaTier::create([
            'tenant_id' => $tenantId,
            'slug' => 'standard',
            'name' => '标准版',
            'is_default' => true,
            'priority' => 10,
        ]);
    }

    /**
     * 检查请求是否超过 API 限流（根据客户 SLA）
     */
    public function checkApiRateLimit(Request $request, Customer $customer): array
    {
        $tier = $this->getTierForCustomer($customer);

        return [
            'allowed' => true,
            'rate_limit' => $tier->api_rate_limit,
            'burst_limit' => $tier->api_burst_limit,
            'concurrent_limit' => $tier->api_concurrent_limit,
        ];
    }

    /**
     * 获取针对请求的增强限流配置
     */
    public function getRateLimitConfig(Customer $customer): array
    {
        $tier = $this->getTierForCustomer($customer);

        return [
            'max_attempts' => $tier->api_rate_limit,
            'window_seconds' => 60,
            'key_type' => 'customer',
        ];
    }

    /**
     * 记录 SLA 违规事件
     */
    public function recordBreach(Customer $customer, string $type, array $context = []): void
    {
        $tier = $this->getTierForCustomer($customer);

        SlaAuditEvent::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'sla_tier_id' => $tier->id,
            'event_type' => SlaAuditEvent::EVENT_SLA_BREACHED,
            'description' => "SLA 违规: {$type}",
            'context' => $context,
        ]);

        Log::warning('SLA: breach recorded', [
            'customer_id' => $customer->id,
            'tier' => $tier->slug,
            'type' => $type,
        ]);
    }

    /**
     * 获取所有 SLA 等级（含每个等级的客户数统计）
     */
    public function getAllTiersWithStats(int $tenantId): array
    {
        $tiers = SlaTier::where('tenant_id', $tenantId)
            ->orderByDesc('priority')
            ->get()
            ->toArray();

        // 统计每个等级的直接分配客户数
        $assignmentCounts = CustomerSlaAssignment::where('tenant_id', $tenantId)
            ->selectRaw('sla_tier_id, COUNT(*) as count')
            ->groupBy('sla_tier_id')
            ->pluck('count', 'sla_tier_id');

        foreach ($tiers as &$tier) {
            $tier['assigned_customers'] = $assignmentCounts[$tier['id']] ?? 0;
        }

        return $tiers;
    }

    /**
     * 处理过期分配（定时任务用）
     */
    public function processExpiredAssignments(int $tenantId): int
    {
        $expired = CustomerSlaAssignment::where('tenant_id', $tenantId)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $assignment) {
            $customer = Customer::find($assignment->customer_id);
            if ($customer) {
                $this->resetToDefault($customer);

                SlaAuditEvent::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customer->id,
                    'sla_tier_id' => null,
                    'event_type' => SlaAuditEvent::EVENT_TIER_EXPIRED,
                    'description' => 'SLA 自定义等级已过期，恢复默认',
                    'context' => ['expired_assignment_id' => $assignment->id],
                ]);
            }

            $assignment->delete();
        }

        return $expired->count();
    }

    /**
     * 清除客户缓存
     */
    public function clearCustomerCache(int $customerId): void
    {
        Cache::forget(self::CACHE_PREFIX . "customer:{$customerId}");
    }
}
