<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2-07b 客户支付方式管理服务
 *
 * 提供支付方式的统一管理，包括 CRUD、默认设置、
 * 过期检测、统计等功能。
 */
class PaymentMethodService
{
    /**
     * 获取客户的所有支付方式
     */
    public function getCustomerMethods(Customer $customer): array
    {
        return PaymentMethod::where('customer_id', $customer->id)
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * 获取客户的默认支付方式
     */
    public function getDefaultMethod(Customer $customer): ?PaymentMethod
    {
        return PaymentMethod::where('customer_id', $customer->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 添加支付方式
     */
    public function addMethod(Customer $customer, array $data): PaymentMethod
    {
        $maxMethods = config('payment-method.limits.max_per_customer', 10);
        $existingCount = PaymentMethod::where('customer_id', $customer->id)->count();

        if ($existingCount >= $maxMethods) {
            throw new \RuntimeException("支付方式数量已达上限（{$maxMethods}个）");
        }

        $data['tenant_id'] = $customer->tenant_id;
        $data['customer_id'] = $customer->id;

        if ($existingCount === 0) {
            $data['is_default'] = true;
        }

        $method = PaymentMethod::create($data);

        if (!empty($data['is_default'])) {
            $method->setAsDefault();
        }

        return $method->fresh();
    }

    /**
     * 设为默认支付方式
     */
    public function setDefault(Customer $customer, PaymentMethod $method): PaymentMethod
    {
        if ($method->customer_id !== $customer->id) {
            throw new \RuntimeException('无权操作此支付方式');
        }

        $method->setAsDefault();
        return $method->fresh();
    }

    /**
     * 删除（软删除）支付方式
     */
    public function deleteMethod(Customer $customer, PaymentMethod $method): void
    {
        if ($method->customer_id !== $customer->id) {
            throw new \RuntimeException('无权操作此支付方式');
        }

        $wasDefault = $method->is_default;
        $method->update(['is_active' => false]);

        if ($wasDefault) {
            $nextDefault = PaymentMethod::where('customer_id', $customer->id)
                ->where('id', '!=', $method->id)
                ->where('is_active', true)
                ->first();

            if ($nextDefault) {
                $nextDefault->setAsDefault();
            }
        }
    }

    /**
     * 获取仪表盘统计（管理端）
     */
    public function getAdminStats(): array
    {
        $total = PaymentMethod::count();
        $active = PaymentMethod::active()->count();
        $defaultCount = PaymentMethod::where('is_default', true)->count();

        // 按品牌统计
        $brandStats = PaymentMethod::active()
            ->select('card_brand', DB::raw('count(*) as count'))
            ->groupBy('card_brand')
            ->pluck('count', 'card_brand')
            ->toArray();

        // 即将过期（30天内）
        $expiringSoon = PaymentMethod::active()
            ->where('expiry_year', '<=', now()->addDays(30)->year)
            ->count();

        return [
            'total_methods' => $total,
            'active_methods' => $active,
            'customers_with_default' => $defaultCount,
            'brand_distribution' => $brandStats,
            'expiring_soon' => $expiringSoon,
            'avg_per_customer' => $active > 0 ? round($total / max(PaymentMethod::distinct('customer_id')->count('customer_id'), 1), 1) : 0,
        ];
    }

    /**
     * 检查支付方式是否即将过期
     */
    public function isExpiringSoon(PaymentMethod $method, ?int $days = null): bool
    {
        $days = $days ?? config('payment-method.expiry_reminder.days_before', 30);

        if (!$method->expiry_year || !$method->expiry_month) {
            return false;
        }

        $expiryDate = \Carbon\Carbon::createFromDate($method->expiry_year, $method->expiry_month, 1)->endOfMonth();
        return $expiryDate->isFuture() && $expiryDate->lte(now()->addDays($days));
    }

    /**
     * 获取客户支付方式统计
     */
    public function getCustomerStats(Customer $customer): array
    {
        $methods = PaymentMethod::where('customer_id', $customer->id);

        return [
            'total' => (clone $methods)->count(),
            'active' => (clone $methods)->active()->count(),
            'has_default' => (clone $methods)->where('is_default', true)->exists(),
            'expiring_soon' => (clone $methods)->active()->get()->filter(fn ($m) => $this->isExpiringSoon($m))->count(),
        ];
    }
}
