<?php

namespace App\Services;

use App\Models\License;
use App\Models\LicenseListing;
use App\Models\LicenseTransaction;
use App\Models\LicenseDispute;
use App\Models\SellerRating;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * License 二级市场服务 (M3-81)
 *
 * 闲置License挂牌转让 + 审核 + 抽成 + 交易历史 + 纠纷仲裁
 */
class LicenseMarketplaceService
{
    // ═══════ 挂牌管理 ═══════

    public function listListings(int $tenantId, array $filters = []): array
    {
        $query = LicenseListing::where('tenant_id', $tenantId)
            ->with(['license:id,license_key', 'seller:id,name']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $query->whereHas('license', fn($q) => $q->where('license_key', 'like', "%{$filters['search']}%"));
        }
        if (!empty($filters['seller_id'])) {
            $query->where('seller_customer_id', $filters['seller_id']);
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->orderByDesc('id')->paginate($perPage)->withQueryString()->toArray();
    }

    public function createListing(int $tenantId, int $licenseId, int $sellerCustomerId, float $price, ?string $notes = null): LicenseListing
    {
        $license = License::findOrFail($licenseId);

        if (!in_array($license->status, config('license-marketplace.listing.allowed_statuses', ['active', 'suspended']))) {
            throw new \InvalidArgumentException('该 License 状态不允许转让');
        }

        $activeCount = LicenseListing::where('seller_customer_id', $sellerCustomerId)
            ->whereIn('status', ['pending', 'approved'])
            ->count();
        $maxActive = config('license-marketplace.listing.max_active_listings_per_tenant', 20);
        if ($activeCount >= $maxActive) {
            throw new \RuntimeException("已达到最大活跃挂牌数({$maxActive})");
        }

        $commission = round($price * config('license-marketplace.commission.rate', 0.05), 2);
        $minFee = config('license-marketplace.commission.min_fee', 1);
        $commission = max($commission, $minFee);
        $maxFee = config('license-marketplace.commission.max_fee', 1000);
        $commission = min($commission, $maxFee);

        $status = config('license-marketplace.listing.require_approval', true) ? 'pending' : 'approved';
        $expiresAt = now()->addDays(config('license-marketplace.listing.auto_expire_days', 90));

        return LicenseListing::create([
            'license_id' => $licenseId,
            'seller_customer_id' => $sellerCustomerId,
            'tenant_id' => $tenantId,
            'price' => $price,
            'commission' => $commission,
            'status' => $status,
            'notes' => $notes,
            'expires_at' => $expiresAt,
        ]);
    }

    public function approveListing(int $listingId, int $userId, ?string $reviewNotes = null): LicenseListing
    {
        $listing = LicenseListing::findOrFail($listingId);
        $listing->update([
            'status' => 'approved',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);
        return $listing->fresh();
    }

    public function rejectListing(int $listingId, int $userId, string $reason): LicenseListing
    {
        $listing = LicenseListing::findOrFail($listingId);
        $listing->update([
            'status' => 'rejected',
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'review_notes' => $reason,
        ]);
        return $listing->fresh();
    }

    public function cancelListing(int $listingId): LicenseListing
    {
        $listing = LicenseListing::findOrFail($listingId);
        $listing->update(['status' => 'cancelled']);
        return $listing->fresh();
    }

    // ═══════ 交易执行 ═══════

    public function executePurchase(int $listingId, int $buyerCustomerId): LicenseTransaction
    {
        return DB::transaction(function () use ($listingId, $buyerCustomerId) {
            $listing = LicenseListing::where('id', $listingId)
                ->whereIn('status', ['approved'])
                ->lockForUpdate()
                ->firstOrFail();

            $license = $listing->license;

            // 计算实收
            $commissionCollectedFrom = config('license-marketplace.commission.collect_from', 'seller');
            $sellerPayout = $commissionCollectedFrom === 'seller'
                ? $listing->price - $listing->commission
                : $listing->price;

            $transaction = LicenseTransaction::create([
                'listing_id' => $listing->id,
                'buyer_customer_id' => $buyerCustomerId,
                'license_id' => $license->id,
                'tenant_id' => $listing->tenant_id,
                'price' => $listing->price,
                'commission' => $listing->commission,
                'seller_payout' => $sellerPayout,
                'status' => 'completed',
                'transaction_id' => 'TXN-' . strtoupper(Str::random(16)),
                'snapshot' => [
                    'license_key' => $license->license_key,
                    'original_customer_id' => $license->customer_id,
                    'seller_customer_id' => $listing->seller_customer_id,
                    'buyer_customer_id' => $buyerCustomerId,
                    'price' => $listing->price,
                    'commission' => $listing->commission,
                ],
                'completed_at' => now(),
            ]);

            // 转移 License 所有权
            $license->update(['customer_id' => $buyerCustomerId]);

            // 标记挂牌完成
            $listing->update(['status' => 'sold']);

            return $transaction;
        });
    }

    // ═══════ 纠纷仲裁 ═══════

    public function raiseDispute(int $transactionId, int $customerId, string $type, string $description, ?array $evidence = null): LicenseDispute
    {
        $maxDisputes = config('license-marketplace.dispute.max_disputes_per_transaction', 1);
        $existingDisputes = LicenseDispute::where('transaction_id', $transactionId)->count();
        if ($existingDisputes >= $maxDisputes) {
            throw new \RuntimeException('该交易已达到最大纠纷次数');
        }

        $timeout = config('license-marketplace.dispute.resolution_timeout_hours', 72);
        return LicenseDispute::create([
            'transaction_id' => $transactionId,
            'raised_by' => $customerId,
            'type' => $type,
            'description' => $description,
            'evidence' => $evidence,
            'status' => 'open',
            'auto_resolve_at' => now()->addDays(config('license-marketplace.dispute.auto_resolve_days', 14)),
        ]);
    }

    public function resolveDispute(int $disputeId, int $userId, string $resolution, string $notes): LicenseDispute
    {
        $dispute = LicenseDispute::findOrFail($disputeId);
        $dispute->update([
            'status' => 'resolved',
            'resolution' => $resolution,
            'resolution_notes' => $notes,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        // 如果是退款给买家，更新交易状态
        if (in_array($resolution, ['refund_buyer', 'partial_refund'])) {
            $dispute->transaction->update(['status' => 'refunded']);
            // 恢复 License 给卖家
            $license = $dispute->transaction->license;
            $listing = $dispute->transaction->listing;
            $license->update(['customer_id' => $listing->seller_customer_id]);
            $listing->update(['status' => 'approved']);
        }

        return $dispute->fresh();
    }

    // ═══════ 信用评分 ═══════

    public function rateSeller(int $transactionId, int $buyerCustomerId, int $rating, ?string $comment = null): SellerRating
    {
        $transaction = LicenseTransaction::findOrFail($transactionId);
        $listing = $transaction->listing;

        return SellerRating::create([
            'transaction_id' => $transactionId,
            'seller_customer_id' => $listing->seller_customer_id,
            'buyer_customer_id' => $buyerCustomerId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }

    public function getSellerScore(int $customerId): array
    {
        $ratings = SellerRating::where('seller_customer_id', $customerId);
        $total = (clone $ratings)->count();
        $avgRating = $total > 0 ? round((clone $ratings)->avg('rating'), 1) : 0;

        $salesCount = LicenseListing::where('seller_customer_id', $customerId)
            ->where('status', 'sold')->count();

        $disputeCount = LicenseDispute::whereHas('transaction.listing', fn($q) => $q->where('seller_customer_id', $customerId))
            ->count();

        $score = config('license-marketplace.credit.initial_score', 100)
            + ($salesCount * config('license-marketplace.credit.bonus_successful_sale', 5))
            - ($disputeCount * config('license-marketplace.credit.penalty_dispute_lost', 20));

        return [
            'total_sales' => $salesCount,
            'total_ratings' => $total,
            'average_rating' => $avgRating,
            'dispute_count' => $disputeCount,
            'credit_score' => max(0, $score),
        ];
    }

    // ═══════ 仪表盘 ═══════

    public function getDashboard(int $tenantId): array
    {
        $activeListings = LicenseListing::where('tenant_id', $tenantId)->where('status', 'approved')->count();
        $pendingApproval = LicenseListing::where('tenant_id', $tenantId)->where('status', 'pending')->count();
        $totalTransactions = LicenseTransaction::where('tenant_id', $tenantId)->count();
        $totalRevenue = LicenseTransaction::where('tenant_id', $tenantId)->sum('commission');
        $openDisputes = LicenseDispute::whereHas('transaction.listing', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'open')->count();

        $recentTransactions = LicenseTransaction::where('tenant_id', $tenantId)
            ->with(['listing.license:id,license_key', 'buyer:id,name'])
            ->orderByDesc('id')->take(5)->get();

        $statusBreakdown = LicenseListing::where('tenant_id', $tenantId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')->pluck('count', 'status')->toArray();

        return compact(
            'activeListings', 'pendingApproval', 'totalTransactions',
            'totalRevenue', 'openDisputes', 'recentTransactions', 'statusBreakdown'
        );
    }
}
