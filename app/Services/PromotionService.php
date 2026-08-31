<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\Customer;
use App\Models\EnterpriseContract;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionService
{
    // ═══════════════ 促销活动 ═══════════════

    public function listPromotions(array $filters = [], int $perPage = 20)
    {
        $query = Promotion::with('creator:id,name')->orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('slug', 'like', "%{$filters['search']}%");
            });
        }
        return $query->paginate($perPage);
    }

    public function createPromotion(array $data): Promotion
    {
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(4);
        $data['created_by'] = auth()->id();
        return Promotion::create($data);
    }

    public function updatePromotion(Promotion $promotion, array $data): Promotion
    {
        $promotion->update($data);
        return $promotion->fresh();
    }

    public function publishPromotion(Promotion $promotion): Promotion
    {
        if (!in_array($promotion->status, ['draft', 'paused'])) {
            throw new \RuntimeException(__("app.promotion.cannot_publish_in_current_state"));
        }
        $promotion->update([
            'status' => 'active',
            'published_at' => now(),
        ]);
        return $promotion->fresh();
    }

    public function pausePromotion(Promotion $promotion): Promotion
    {
        $promotion->update(['status' => 'paused']);
        return $promotion->fresh();
    }

    public function getActivePromotionsForCustomer(?int $customerId = null): array
    {
        $promotions = Promotion::active()
            ->where(function ($q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(fn($p) => $p->hasBudget() && $p->hasUsageLeft())
            ->values();

        $coupons = Coupon::active()
            ->with('promotion')
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->get();

        if ($customerId) {
            $coupons = $coupons->filter(fn($c) => !$c->hasReachedUserLimit($customerId));
        }

        return [
            'promotions' => $promotions,
            'coupons' => $coupons->values(),
        ];
    }

    public function redeemPromotion(Promotion $promotion, Customer $customer, float $amount, ?int $invoiceId = null, array $context = []): array
    {
        if (!$promotion->isActive()) {
            throw new \RuntimeException(__("app.promotion.promotion_inactive_or_expired"));
        }
        if (!$promotion->hasBudget()) {
            throw new \RuntimeException(__("app.promotion.promotion_budget_exhausted"));
        }
        if (!$promotion->hasUsageLeft()) {
            throw new \RuntimeException(__("app.promotion.promotion_usage_limit_reached"));
        }
        if ($promotion->min_order_amount && $amount < $promotion->min_order_amount) {
            throw new \RuntimeException(__("app.promotion.min_order_amount_not_met"));
        }

        $discount = $promotion->calculateDiscount($amount);
        if ($discount <= 0) {
            throw new \RuntimeException(__("app.promotion.invalid_discount_amount"));
        }

        // Check if the discount exceeds remaining budget
        if ($promotion->budget && ($promotion->budget_spent + $discount) > $promotion->budget) {
            throw new \RuntimeException(__("app.promotion.promotion_budget_insufficient"));
        }

        DB::transaction(function () use ($promotion, $customer, $invoiceId, $discount, $context) {
            $promotion->increment('usage_count');
            $promotion->increment('budget_spent', $discount);

            PromotionRedemption::create([
                'promotion_id' => $promotion->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoiceId,
                'promotion_type' => $promotion->type,
                'discount_amount' => $discount,
                'currency' => 'CNY',
                'context' => $context,
                'ip_address' => request()->ip(),
            ]);
        });

        return [
            'original_amount' => $amount,
            'discount' => $discount,
            'final_amount' => max(0, $amount - $discount),
            'promotion_name' => $promotion->name,
        ];
    }

    public function getPromotionStats(): array
    {
        return [
            'total' => Promotion::count(),
            'active' => Promotion::where('status', 'active')->count(),
            'draft' => Promotion::where('status', 'draft')->count(),
            'paused' => Promotion::where('status', 'paused')->count(),
            'expired' => Promotion::where('status', 'expired')->count(),
            'today_active' => Promotion::active()
                ->whereDate('starts_at', '<=', now())
                ->where(function ($q) {
                    $q->whereNull('ends_at')->orWhereDate('ends_at', '>=', now());
                })->count(),
            'total_redemptions' => PromotionRedemption::count(),
            'total_discount_given' => PromotionRedemption::sum('discount_amount'),
            'active_coupons' => Coupon::active()->count(),
        ];
    }

    // ═══════════════ 企业年框合同 ═══════════════

    public function listContracts(array $filters = [], int $perPage = 20)
    {
        $query = EnterpriseContract::with(['customer:id,user_id', 'customer.user:id,name', 'creator:id,name'])
            ->orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['customer_id'])) $query->where('customer_id', $filters['customer_id']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('contract_number', 'like', "%{$filters['search']}%");
            });
        }
        return $query->paginate($perPage);
    }

    public function createContract(array $data): EnterpriseContract
    {
        $data['contract_number'] = $data['contract_number'] ?? 'CT-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
        $data['created_by'] = auth()->id();
        return EnterpriseContract::create($data);
    }

    public function updateContract(EnterpriseContract $contract, array $data): EnterpriseContract
    {
        $contract->update($data);
        return $contract->fresh();
    }

    public function approveContract(EnterpriseContract $contract, string $status, ?string $notes = null): EnterpriseContract
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \RuntimeException(__("app.promotion.invalid_approval_status"));
        }
        $contract->update([
            'approval_status' => $status,
            'status' => $status === 'approved' ? 'active' : 'draft',
            'approved_by' => auth()->id(),
            'approved_at' => $status === 'approved' ? now() : null,
            'approval_notes' => $notes,
        ]);
        return $contract->fresh();
    }

    public function getContractStats(): array
    {
        return [
            'total' => EnterpriseContract::count(),
            'active' => EnterpriseContract::where('status', 'active')->count(),
            'expiring_soon' => EnterpriseContract::where('status', 'active')
                ->whereDate('end_date', '<=', now()->addDays(30))
                ->whereDate('end_date', '>=', now())
                ->count(),
            'expired' => EnterpriseContract::where('status', 'expired')->count(),
            'pending_approval' => EnterpriseContract::where('status', 'pending_approval')->count(),
            'total_value' => EnterpriseContract::where('status', 'active')->sum('total_value'),
        ];
    }

    // ═══════════════ 优惠券管理增强 ═══════════════

    public function listCoupons(array $filters = [], int $perPage = 20)
    {
        $query = Coupon::orderBy('created_at', 'desc');
        if (!empty($filters['status'])) $query->where('status', $filters['status']);
        if (!empty($filters['type'])) $query->where('type', $filters['type']);
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'like', "%{$filters['search']}%")
                  ->orWhere('name', 'like', "%{$filters['search']}%");
            });
        }
        return $query->paginate($perPage);
    }

    public function createCoupon(array $data): Coupon
    {
        if (empty($data['code'])) {
            $data['code'] = strtoupper(Str::random(8));
        }
        return Coupon::create($data);
    }

    public function getCustomerCoupons(int $customerId): array
    {
        $coupons = Coupon::active()
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(fn($c) => !$c->hasReachedUserLimit($customerId))
            ->values();

        return $coupons->toArray();
    }

    public function validateAndRedeemCoupon(string $code, Customer $customer, float $amount, ?int $invoiceId = null): array
    {
        $coupon = Coupon::where('code', $code)->first();
        if (!$coupon) {
            throw new \RuntimeException(__("app.promotion.coupon_not_found"));
        }

        if (!$coupon->isValid($amount, $customer->id)) {
            throw new \RuntimeException(__("app.promotion.coupon_unavailable"));
        }

        $discount = $coupon->calculateDiscount($amount);

        DB::transaction(function () use ($coupon, $customer, $invoiceId, $discount, $amount) {
            $redemption = CouponRedemption::create([
                'coupon_id' => $coupon->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoiceId,
                'original_amount' => $amount,
                'discount_amount' => $discount,
                'final_amount' => max(0, $amount - $discount),
                'currency' => $coupon->currency ?? 'CNY',
                'metadata' => [
                    'customer_email' => $customer->email,
                    'ip_address' => request()->ip(),
                ],
            ]);
            $coupon->recordRedemption($redemption);
            if ($coupon->budget) {
                $coupon->increment('budget_spent', $discount);
            }
        });

        return [
            'original_amount' => $amount,
            'discount' => $discount,
            'final_amount' => max(0, $amount - $discount),
            'coupon_code' => $coupon->code,
            'coupon_name' => $coupon->name,
        ];
    }
}
