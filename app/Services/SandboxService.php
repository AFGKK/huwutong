<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;
use App\Models\SandboxProduct;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SandboxService
{
    /**
     * 沙箱最大 License 数量
     */
    const MAX_LICENSES = 5;

    /**
     * 沙箱 API 限速（请求/分钟）
     */
    const RATE_LIMIT = 60;

    /**
     * 创建沙箱环境（注册时自动调用）
     */
    public function create(User $user): Tenant
    {
        return DB::transaction(function () use ($user) {
            // 检查是否已有沙箱
            $existing = Tenant::where('id', $user->tenant_id)->where('is_sandbox', true)->first();
            if ($existing) {
                return $existing;
            }

            // 获取或创建沙箱租户
            $tenant = Tenant::create([
                'name' => $user->name . ' 的开发沙箱',
                'status' => 'active',
                'data_region' => 'cn',
                'is_sandbox' => true,
                'sandbox_expires_at' => now()->addYears(10), // 永久免费
                'subscription_plan' => 'sandbox',
            ]);

            // 关联用户到沙箱租户
            $user->update(['tenant_id' => $tenant->id]);

            // 创建沙箱产品（仅当没有真实产品时）
            $sandboxProduct = SandboxProduct::getDefault();

            // 创建沙箱产品关联（在产品表中创建或关联）
            $product = Product::firstOrCreate(
                ['slug' => $sandboxProduct->slug],
                [
                    'name' => $sandboxProduct->name,
                    'description' => $sandboxProduct->description,
                    'version' => $sandboxProduct->version,
                    'is_active' => true,
                    'modules' => $sandboxProduct->modules,
                ]
            );

            // 创建 5 个测试 License
            for ($i = 0; $i < self::MAX_LICENSES; $i++) {
                License::create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'license_key' => $this->generateSandboxKey($i + 1),
                    'type' => 'sandbox',
                    'status' => 'active',
                    'expires_at' => now()->addYears(10),
                    'metadata' => [
                        'sandbox' => true,
                        'purpose' => '开发者集成测试',
                        'max_devices' => 3,
                    ],
                ]);
            }

            // 设置限速缓存
            $this->setRateLimit($tenant->id);

            return $tenant;
        });
    }

    /**
     * 重置沙箱（清除所有激活记录和设备绑定，恢复 License 状态）
     */
    public function reset(Tenant $tenant): bool
    {
        if (! $tenant->is_sandbox) {
            return false;
        }

        DB::transaction(function () use ($tenant) {
            // 清除所有激活记录
            $tenant->licenses()->each(function (License $license) {
                $license->activations()->delete();
                $license->update([
                    'status' => 'active',
                    'metadata' => array_merge($license->metadata ?? [], ['reset_at' => now()->toIso8601String()]),
                ]);
            });

            // 清除租户下所有设备
            $tenant->devices()->delete();
        });

        return true;
    }

    /**
     * 获取沙箱状态
     */
    public function status(Tenant $tenant): array
    {
        $licenseCount = $tenant->licenses()->count();
        $activeLicenseCount = $tenant->licenses()->where('status', 'active')->count();
        $deviceCount = $tenant->devices()->count();

        return [
            'is_sandbox' => $tenant->is_sandbox,
            'tenant_name' => $tenant->name,
            'created_at' => $tenant->created_at?->toIso8601String(),
            'expires_at' => $tenant->sandbox_expires_at?->toIso8601String(),
            'license_limit' => self::MAX_LICENSES,
            'licenses_created' => $licenseCount,
            'licenses_active' => $activeLicenseCount,
            'devices_bound' => $deviceCount,
            'rate_limit' => self::RATE_LIMIT . '/min',
            'remaining_licenses' => max(0, self::MAX_LICENSES - $licenseCount),
        ];
    }

    /**
     * 检查是否为沙箱租户
     */
    public function isSandbox(?int $tenantId): bool
    {
        if (! $tenantId) return false;

        return Cache::remember("sandbox:{$tenantId}", 3600, function () use ($tenantId) {
            return Tenant::where('id', $tenantId)->where('is_sandbox', true)->exists();
        });
    }

    /**
     * 生成沙箱 License Key
     */
    private function generateSandboxKey(int $index): string
    {
        $prefix = 'SANDBOX';
        $random = strtoupper(Str::random(8));
        return "{$prefix}-{$index}-{$random}";
    }

    /**
     * 设置限速
     */
    private function setRateLimit(int $tenantId): void
    {
        Cache::put("sandbox_rate_limit:{$tenantId}", self::RATE_LIMIT, now()->addDay());
    }
}
