<?php

namespace App\Services;

use App\Models\License;
use App\Models\Customer;
use App\Models\Product;
use App\Models\LicenseActivation;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Zapier/Make 无代码集成服务 (M3-43)
 *
 * 提供 Zapier 和 Make.com 平台的 API 端点
 * - 触发器：新License/即将到期/新客户/激活
 * - 动作：创建License/挂起/通知/吊销
 * - 搜索：查找License
 * - 预建工作流模板
 */
class ZapierIntegrationService
{
    /**
     * ─── 触发器 ───
     */

    /**
     * 新 License 列表 (轮询)
     */
    public function newLicenses(int $offset = 0, int $limit = 50): array
    {
        $licenses = License::with(['product', 'customer'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take(min($limit, 200))
            ->get()
            ->map(fn($l) => $this->formatLicense($l))
            ->toArray();

        return $licenses;
    }

    /**
     * 即将到期 License
     */
    public function expiringLicenses(int $days = 30, int $offset = 0, int $limit = 50): array
    {
        $licenses = License::with('customer')
            ->where('status', 'active')
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addDays($days)])
            ->orderBy('expires_at')
            ->skip($offset)
            ->take(min($limit, 200))
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'license_key' => $l->license_key,
                'customer_name' => $l->customer?->name ?? 'N/A',
                'expires_at' => $l->expires_at->toIso8601String(),
                'days_until_expiry' => now()->diffInDays($l->expires_at, false),
            ])
            ->toArray();

        return $licenses;
    }

    /**
     * 新客户列表
     */
    public function newCustomers(int $offset = 0, int $limit = 50): array
    {
        return Customer::orderBy('created_at', 'desc')
            ->skip($offset)
            ->take(min($limit, 200))
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'email' => $c->email,
                'plan' => $c->plan ?? 'standard',
                'created_at' => $c->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * License 激活列表
     */
    public function licenseActivations(int $offset = 0, int $limit = 50): array
    {
        return LicenseActivation::with('license')
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take(min($limit, 200))
            ->get()
            ->map(fn($a) => [
                'id' => $a->id,
                'license_key' => $a->license?->license_key ?? 'N/A',
                'device_fingerprint' => $a->device_fingerprint ?? 'N/A',
                'platform' => $a->platform ?? 'unknown',
                'activated_at' => $a->created_at->toIso8601String(),
            ])
            ->toArray();
    }

    /**
     * ─── 动作 ───
     */

    /**
     * 创建 License
     */
    public function createLicense(array $data): array
    {
        $product = Product::findOrFail($data['product_id']);
        $customer = Customer::findOrFail($data['customer_id']);
        $expiresAt = isset($data['expires_in_days'])
            ? now()->addDays((int)$data['expires_in_days'])
            : now()->addYear();

        $license = License::create([
            'license_key' => 'HWT-' . strtoupper(Str::random(12)),
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'type' => $data['type'] ?? 'standard',
            'status' => 'active',
            'seats' => $data['seats'] ?? 1,
            'expires_at' => $expiresAt,
        ]);

        Log::info('Zapier: License created via integration', [
            'license_id' => $license->id,
            'product' => $product->name,
            'customer' => $customer->name,
        ]);

        return [
            'id' => $license->id,
            'license_key' => $license->license_key,
            'status' => $license->status,
            'created_at' => $license->created_at->toIso8601String(),
        ];
    }

    /**
     * 挂起 License
     */
    public function suspendLicense(string $licenseKey, ?string $reason = null): array
    {
        $license = License::where('license_key', $licenseKey)->firstOrFail();
        $license->update(['status' => 'suspended']);

        Log::info('Zapier: License suspended via integration', [
            'license_key' => $licenseKey,
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'license_key' => $licenseKey,
            'status' => 'suspended',
        ];
    }

    /**
     * 吊销 License
     */
    public function revokeLicense(string $licenseKey): array
    {
        $license = License::where('license_key', $licenseKey)->firstOrFail();
        $license->update(['status' => 'expired']);

        return [
            'success' => true,
            'license_key' => $licenseKey,
            'status' => 'expired',
        ];
    }

    /**
     * ─── 搜索 ───
     */

    /**
     * 查找 License
     */
    public function findLicense(string $query): array
    {
        $licenses = License::with('customer')
            ->where('license_key', 'LIKE', "%{$query}%")
            ->orWhereHas('customer', fn($q) => $q->where('name', 'LIKE', "%{$query}%"))
            ->limit(10)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'license_key' => $l->license_key,
                'status' => $l->status,
                'customer_name' => $l->customer?->name ?? 'N/A',
            ])
            ->toArray();

        return $licenses;
    }

    /**
     * ─── 资源列表 (用于动态下拉) ───
     */

    public function listProducts(): array
    {
        return Product::where('is_active', true)
            ->get()
            ->map(fn($p) => ['id' => $p->id, 'name' => $p->name])
            ->toArray();
    }

    public function listCustomers(): array
    {
        return Customer::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
            ->toArray();
    }

    /**
     * ─── 工具方法 ───
     */

    protected function formatLicense(License $license): array
    {
        return [
            'id' => $license->id,
            'license_key' => $license->license_key,
            'type' => $license->type,
            'status' => $license->status,
            'product_name' => $license->product?->name ?? 'N/A',
            'customer_name' => $license->customer?->name ?? 'N/A',
            'seats' => $license->seats,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'created_at' => $license->created_at->toIso8601String(),
        ];
    }

    /**
     * 获取预建工作流模板
     */
    public function getWorkflowTemplates(): array
    {
        return config('zapier.workflow_templates', []);
    }
}
