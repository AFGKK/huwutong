<?php

namespace App\Services;

use App\Models\License;
use App\Models\Product;
use App\Models\StagingEnvironment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StagingService
{
    const MAX_LICENSES = 10;
    const DEFAULT_RATE_LIMIT = 120;

    /**
     * 租户申请 Staging 环境
     */
    public function create(Tenant $tenant, array $params = []): StagingEnvironment
    {
        return DB::transaction(function () use ($tenant, $params) {
            if ($tenant->has_staging) {
                $existing = StagingEnvironment::where('tenant_id', $tenant->id)->first();
                if ($existing) {
                    return $existing;
                }
            }

            $subdomain = $params['subdomain'] ?? $this->generateSubdomain($tenant);

            $env = StagingEnvironment::create([
                'tenant_id' => $tenant->id,
                'name' => $params['name'] ?? $tenant->name . ' 的集成测试环境',
                'subdomain' => $subdomain,
                'status' => 'active',
                'rate_limit' => $params['rate_limit'] ?? self::DEFAULT_RATE_LIMIT,
                'api_base_url' => "https://{$subdomain}.staging.huwutong.com",
                'config' => [
                    'allow_origins' => ['*'],
                    'max_devices_per_license' => 5,
                    'data_isolation' => true,
                    'auto_reset_days' => 30,
                ],
                'expires_at' => now()->addYear(),
            ]);

            $tenant->update(['has_staging' => true]);

            // 创建 10 个专用的 Staging License
            $product = Product::firstOrCreate(
                ['slug' => 'staging-test'],
                [
                    'name' => 'Staging Test License',
                    'description' => '集成测试环境专用 License',
                    'version' => '1.0.0',
                    'is_active' => true,
                    'modules' => ['activation', 'validation', 'device-binding', 'offline', 'floating'],
                ]
            );

            for ($i = 0; $i < self::MAX_LICENSES; $i++) {
                License::create([
                    'tenant_id' => $tenant->id,
                    'product_id' => $product->id,
                    'license_key' => $this->generateKey($i + 1),
                    'type' => 'staging',
                    'status' => 'active',
                    'expires_at' => $env->expires_at,
                    'metadata' => [
                        'staging' => true,
                        'staging_env_id' => $env->id,
                        'purpose' => '集成测试',
                        'max_devices' => 5,
                    ],
                ]);
            }

            $this->setRateLimit($env);

            return $env;
        });
    }

    /**
     * 重置 Staging 环境
     */
    public function reset(StagingEnvironment $env): bool
    {
        return DB::transaction(function () use ($env) {
            // 清除所有激活和设备
            $env->tenant->licenses()
                ->where('type', 'staging')
                ->each(function (License $license) {
                    $license->activations()->delete();
                    $license->update([
                        'status' => 'active',
                        'metadata' => array_merge($license->metadata ?? [], ['reset_at' => now()->toIso8601String()]),
                    ]);
                });

            $env->tenant->devices()->delete();

            $env->update([
                'last_reset_at' => now(),
                'status' => 'active',
            ]);

            $this->setRateLimit($env);

            return true;
        });
    }

    /**
     * 获取环境状态
     */
    public function status(StagingEnvironment $env): array
    {
        $licenses = $env->tenant->licenses()->where('type', 'staging');
        $total = $licenses->count();
        $active = $licenses->where('status', 'active')->count();
        $deviceCount = $env->tenant->devices()->count();

        return [
            'id' => $env->id,
            'name' => $env->name,
            'subdomain' => $env->subdomain,
            'status' => $env->status,
            'api_base_url' => $env->api_base_url,
            'rate_limit' => $env->rate_limit . '/min',
            'created_at' => $env->created_at?->toIso8601String(),
            'expires_at' => $env->expires_at?->toIso8601String(),
            'last_reset_at' => $env->last_reset_at?->toIso8601String(),
            'license_limit' => self::MAX_LICENSES,
            'licenses_total' => $total,
            'licenses_active' => $active,
            'devices_bound' => $deviceCount,
            'config' => $env->config,
        ];
    }

    /**
     * 检查租户是否有权限访问（middleware 用）
     */
    public function hasAccess(Tenant $tenant): bool
    {
        return $tenant->has_staging
            && StagingEnvironment::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->exists();
    }

    /**
     * 获取/刷新限速信息
     */
    public function getRateLimit(StagingEnvironment $env): int
    {
        return Cache::remember("staging_rate_limit:{$env->id}", 3600, fn() => $env->rate_limit);
    }

    private function setRateLimit(StagingEnvironment $env): void
    {
        Cache::put("staging_rate_limit:{$env->id}", $env->rate_limit, now()->addDay());
    }

    private function generateSubdomain(Tenant $tenant): string
    {
        $base = Str::slug($tenant->name, '-');
        $suffix = strtolower(Str::random(4));
        return "staging-{$base}-{$suffix}";
    }

    private function generateKey(int $index): string
    {
        return 'STAGING-' . $index . '-' . strtoupper(Str::random(8));
    }
}
