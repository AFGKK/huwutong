<?php

namespace App\Services;

use App\Models\OemSubscription;
use App\Models\OemSubscriptionChange;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OEM 白标系统 (M3-03)
 *
 * 管理 OEM 套餐、品牌化、自定义域名、品牌登录页
 */
class OemService
{
    /**
     * 获取租户的 OEM 订阅
     */
    public function getSubscription(int $tenantId): ?OemSubscription
    {
        return OemSubscription::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->latest()
            ->first();
    }

    /**
     * 创建或更新租户 OEM 订阅
     */
    public function subscribe(int $tenantId, string $tier, array $options = []): OemSubscription
    {
        $config = config("oem.tiers.{$tier}");
        if (!$config) {
            throw new \InvalidArgumentException("无效的 OEM 套餐: {$tier}");
        }

        $billingPeriod = $options['billing_period'] ?? 'monthly';
        $price = $billingPeriod === 'yearly' ? $config['price_yearly'] : $config['price_monthly'];
        $features = $config['features'] ?? [];

        // 提取功能键中的 true 值作为活跃功能列表
        $activeFeatures = array_keys(array_filter($features, fn($v) => $v === true));

        $existing = $this->getSubscription($tenantId);
        $oldTier = $existing?->tier;

        DB::beginTransaction();
        try {
            $subscription = OemSubscription::updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'is_active' => true,
                ],
                [
                    'tier' => $tier,
                    'billing_period' => $billingPeriod,
                    'price' => $price,
                    'active_features' => $activeFeatures,
                    'starts_at' => $existing?->starts_at ?? now(),
                    'expires_at' => $options['expires_at'] ?? now()->addMonth(),
                    'is_active' => true,
                    'status' => 'active',
                    'max_domains' => $features['max_domains'] ?? 0,
                    'max_themes' => $features['max_themes'] ?? 1,
                ]
            );

            // 记录变更
            OemSubscriptionChange::create([
                'oem_subscription_id' => $subscription->id,
                'change_type' => $existing ? 'upgrade' : 'activate',
                'from_tier' => $oldTier,
                'to_tier' => $tier,
                'price' => $price,
                'reason' => $options['reason'] ?? null,
                'operated_by' => $options['operated_by'] ?? null,
            ]);

            DB::commit();

            return $subscription->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OEM subscribe failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 取消 OEM 订阅
     */
    public function cancel(int $tenantId, string $reason = null): bool
    {
        $subscription = $this->getSubscription($tenantId);
        if (!$subscription) {
            return false;
        }

        DB::beginTransaction();
        try {
            $subscription->update([
                'status' => 'cancelled',
                'is_active' => false,
            ]);

            OemSubscriptionChange::create([
                'oem_subscription_id' => $subscription->id,
                'change_type' => 'cancel',
                'from_tier' => $subscription->tier,
                'to_tier' => null,
                'reason' => $reason,
            ]);

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('OEM cancel failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * 检查租户是否有权使用某功能
     */
    public function canUseFeature(int $tenantId, string $feature): bool
    {
        $subscription = $this->getSubscription($tenantId);
        if (!$subscription || !$subscription->isValid()) {
            return false;
        }

        // basic 套餐始终拥有基础品牌功能
        if (in_array($feature, ['custom_logo', 'brand_colors', 'brand_name_customization', 'custom_favicon'])) {
            return true;
        }

        return $subscription->hasFeature($feature);
    }

    /**
     * 获取 OEM 仪表盘数据
     */
    public function getDashboard(int $tenantId): array
    {
        $subscription = $this->getSubscription($tenantId);
        $tierConfig = $subscription ? config("oem.tiers.{$subscription->tier}") : config('oem.tiers.basic');

        // 域名统计
        $domainCount = \App\Models\CustomDomain::where('tenant_id', $tenantId)->count();
        $verifiedDomains = \App\Models\CustomDomain::where('tenant_id', $tenantId)
            ->where('verified', true)->count();

        // 品牌配置
        $brandingConfig = \App\Models\PortalBrandingConfig::where('tenant_id', $tenantId)
            ->where('is_active', true)->first();

        return [
            'subscription' => $subscription,
            'tier_config' => $tierConfig,
            'stats' => [
                'domains_total' => $domainCount,
                'domains_verified' => $verifiedDomains,
                'domains_remaining' => ($subscription->max_domains ?? 0) - $domainCount,
                'has_branding' => !is_null($brandingConfig),
                'has_logo' => $brandingConfig && $brandingConfig->logo_url,
                'has_custom_domain' => $verifiedDomains > 0,
            ],
            'available_tiers' => config('oem.tiers'),
        ];
    }

    /**
     * 获取所有 OEM 套餐定义
     */
    public function getTiers(): array
    {
        return config('oem.tiers');
    }

    /**
     * 初始化默认 OEM 订阅 (为所有租户创建 basic 套餐)
     */
    public function initializeDefaults(): void
    {
        $tenants = Tenant::all();
        foreach ($tenants as $tenant) {
            $existing = OemSubscription::where('tenant_id', $tenant->id)->exists();
            if (!$existing) {
                $this->subscribe($tenant->id, 'basic', [
                    'billing_period' => 'monthly',
                    'reason' => '系统默认初始化',
                ]);
            }
        }
    }

    /**
     * 获取变更历史
     */
    public function getChangeHistory(int $tenantId): array
    {
        $subscription = $this->getSubscription($tenantId);
        if (!$subscription) {
            return [];
        }

        return $subscription->changes()
            ->with('operator')
            ->latest()
            ->get()
            ->toArray();
    }
}
