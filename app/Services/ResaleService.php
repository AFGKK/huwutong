<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\License;
use App\Models\ResaleListing;
use App\Models\ResaleTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * License 转售/二级市场服务
 *
 * M3-81
 * 管理 License 二级市场：挂牌、浏览、购买、交易执行、佣金结算
 */
class ResaleService
{
    protected OwnershipTransferService $transferService;

    public function __construct(OwnershipTransferService $transferService)
    {
        $this->transferService = $transferService;
    }

    public function generateReference(): string
    {
        return 'RSL-' . strtoupper(Str::random(10));
    }

    // ──────────────────────────────────────────────
    //  挂牌管理
    // ──────────────────────────────────────────────

    /**
     * 创建挂牌
     */
    public function createListing(int $tenantId, int $licenseId, int $sellerCustomerId, array $data): ResaleListing
    {
        $license = License::where('tenant_id', $tenantId)
            ->where('id', $licenseId)
            ->firstOrFail();

        // 验证 License 可转让
        if (!$this->canBeResold($license)) {
            throw new \RuntimeException('该 License 不符合转售条件');
        }

        // 验证 License 属于卖家
        if ($license->customer_id !== $sellerCustomerId) {
            throw new \RuntimeException('该 License 不属于当前客户');
        }

        // 检查是否已挂牌
        $existing = ResaleListing::where('tenant_id', $tenantId)
            ->where('license_id', $licenseId)
            ->whereIn('status', [ResaleListing::STATUS_DRAFT, ResaleListing::STATUS_PUBLISHED, ResaleListing::STATUS_PENDING_REVIEW, ResaleListing::STATUS_ACTIVE])
            ->first();

        if ($existing) {
            throw new \RuntimeException('该 License 已有进行中的挂牌');
        }

        return ResaleListing::create([
            'tenant_id' => $tenantId,
            'license_id' => $licenseId,
            'seller_customer_id' => $sellerCustomerId,
            'reference' => $this->generateReference(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'asking_price' => $data['asking_price'],
            'currency' => $data['currency'] ?? 'CNY',
            'commission_rate' => $data['commission_rate'] ?? 5.00,
            'status' => ResaleListing::STATUS_DRAFT,
            'expires_at' => $data['expires_at'] ?? now()->addDays(90),
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * 更新挂牌
     */
    public function updateListing(int $listingId, array $data): ResaleListing
    {
        $listing = ResaleListing::findOrFail($listingId);

        if ($listing->status !== ResaleListing::STATUS_DRAFT) {
            throw new \RuntimeException('仅草稿状态的挂牌可以编辑');
        }

        $listing->update($data);
        return $listing->fresh();
    }

    /**
     * 发布挂牌（提交审核）
     */
    public function publishListing(int $listingId): ResaleListing
    {
        $listing = ResaleListing::findOrFail($listingId);

        if ($listing->status !== ResaleListing::STATUS_DRAFT) {
            throw new \RuntimeException('仅草稿状态的挂牌可以发布');
        }

        // 可以配置是否需要审核（按租户配置）
        $requiresReview = true; // 默认需要审核

        $listing->update([
            'status' => $requiresReview ? ResaleListing::STATUS_PENDING_REVIEW : ResaleListing::STATUS_ACTIVE,
            'listed_at' => $requiresReview ? null : now(),
        ]);

        return $listing->fresh();
    }

    /**
     * 审核挂牌
     */
    public function reviewListing(int $listingId, int $reviewerId, string $action, ?string $notes = null): ResaleListing
    {
        $listing = ResaleListing::findOrFail($listingId);

        if ($listing->status !== ResaleListing::STATUS_PENDING_REVIEW) {
            throw new \RuntimeException('该挂牌不在待审核状态');
        }

        if ($action === 'approve') {
            $listing->update([
                'status' => ResaleListing::STATUS_ACTIVE,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
                'listed_at' => now(),
            ]);
        } elseif ($action === 'reject') {
            $listing->update([
                'status' => ResaleListing::STATUS_DRAFT,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now(),
                'review_notes' => $notes,
            ]);
        } else {
            throw new \InvalidArgumentException('操作必须为 approve 或 reject');
        }

        return $listing->fresh();
    }

    /**
     * 取消挂牌
     */
    public function cancelListing(int $listingId): ResaleListing
    {
        $listing = ResaleListing::findOrFail($listingId);

        if (in_array($listing->status, [ResaleListing::STATUS_SOLD, ResaleListing::STATUS_CANCELLED])) {
            throw new \RuntimeException('该挂牌已结束，无法取消');
        }

        $listing->update(['status' => ResaleListing::STATUS_CANCELLED]);
        return $listing->fresh();
    }

    // ──────────────────────────────────────────────
    //  市场浏览/搜索
    // ──────────────────────────────────────────────

    /**
     * 浏览市场挂牌列表
     */
    public function browseMarketplace(int $tenantId, array $filters = [], int $perPage = 20): array
    {
        $query = ResaleListing::with(['license.product', 'sellerCustomer.user'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [ResaleListing::STATUS_ACTIVE]);

        if (!empty($filters['search'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('title', 'like', "%{$filters['search']}%")
                    ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['min_price'])) {
            $query->where('asking_price', '>=', $filters['min_price']);
        }

        if (!empty($filters['max_price'])) {
            $query->where('asking_price', '<=', $filters['max_price']);
        }

        if (!empty($filters['currency'])) {
            $query->where('currency', $filters['currency']);
        }

        if (!empty($filters['product_id'])) {
            $query->whereHas('license', function (Builder $q) use ($filters) {
                $q->where('product_id', $filters['product_id']);
            });
        }

        $sortField = $filters['sort'] ?? 'created_at';
        $sortDir = $filters['order'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        $paginator = $query->paginate($perPage);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取卖家的挂牌列表
     */
    public function getSellerListings(int $tenantId, int $sellerCustomerId, ?string $status = null, int $perPage = 20): array
    {
        $query = ResaleListing::with(['license.product', 'transactions'])
            ->where('tenant_id', $tenantId)
            ->where('seller_customer_id', $sellerCustomerId);

        if ($status) {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return [
            'items' => $paginator->items(),
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    /**
     * 获取挂牌详情
     */
    public function getListingDetail(int $listingId): ResaleListing
    {
        return ResaleListing::with([
            'license.product',
            'license.customer',
            'sellerCustomer.user',
            'transactions.buyerCustomer.user',
        ])->findOrFail($listingId);
    }

    // ──────────────────────────────────────────────
    //  交易流程
    // ──────────────────────────────────────────────

    /**
     * 购买挂牌 License（创建交易）
     */
    public function purchaseListing(int $listingId, int $buyerCustomerId): ResaleTransaction
    {
        $listing = ResaleListing::findOrFail($listingId);

        if (!$listing->isAvailable()) {
            throw new \RuntimeException('该挂牌暂不可购买');
        }

        return DB::transaction(function () use ($listing, $buyerCustomerId) {
            $commissionAmount = round($listing->asking_price * $listing->commission_rate / 100, 2);
            $sellerPayout = round($listing->asking_price - $commissionAmount, 2);

            $transaction = ResaleTransaction::create([
                'tenant_id' => $listing->tenant_id,
                'listing_id' => $listing->id,
                'buyer_customer_id' => $buyerCustomerId,
                'reference' => 'RST-' . strtoupper(Str::random(10)),
                'agreed_price' => $listing->asking_price,
                'commission_amount' => $commissionAmount,
                'seller_payout' => $sellerPayout,
                'currency' => $listing->currency,
                'status' => ResaleTransaction::STATUS_PENDING_PAYMENT,
            ]);

            // 更新挂牌状态
            $listing->update(['status' => ResaleListing::STATUS_SOLD, 'sold_at' => now()]);

            return $transaction;
        });
    }

    /**
     * 确认付款
     */
    public function confirmPayment(int $transactionId, string $paymentMethod, string $paymentReference): ResaleTransaction
    {
        $transaction = ResaleTransaction::findOrFail($transactionId);

        if ($transaction->status !== ResaleTransaction::STATUS_PENDING_PAYMENT) {
            throw new \RuntimeException('该交易不在待付款状态');
        }

        $transaction->update([
            'status' => ResaleTransaction::STATUS_PAID,
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ]);

        return $transaction->fresh();
    }

    /**
     * 卖家确认（进入转移阶段）
     */
    public function sellerConfirm(int $transactionId, int $sellerUserId): ResaleTransaction
    {
        $transaction = ResaleTransaction::with('listing')->findOrFail($transactionId);

        if ($transaction->status !== ResaleTransaction::STATUS_PAID) {
            throw new \RuntimeException('该交易未完成付款');
        }

        if ($transaction->confirmed_by_seller) {
            throw new \RuntimeException('卖家已确认');
        }

        $transaction->update([
            'status' => ResaleTransaction::STATUS_PENDING_TRANSFER,
            'confirmed_by_seller' => $sellerUserId,
            'seller_confirmed_at' => now(),
        ]);

        return $transaction->fresh();
    }

    /**
     * 执行 License 转移（管理员操作）
     */
    public function executeTransfer(int $transactionId, int $adminUserId): ResaleTransaction
    {
        return DB::transaction(function () use ($transactionId, $adminUserId) {
            $transaction = ResaleTransaction::with('listing.license', 'listing.sellerCustomer')
                ->findOrFail($transactionId);

            if ($transaction->status !== ResaleTransaction::STATUS_PENDING_TRANSFER) {
                throw new \RuntimeException('该交易未进入转移阶段');
            }

            $listing = $transaction->listing;
            $license = $listing->license;
            $sourceCustomerId = $listing->seller_customer_id;
            $targetCustomerId = $transaction->buyer_customer_id;

            // 使用 OwnershipTransferService 执行 License 所有权转移
            $transferRequest = $this->transferService->createRequest(
                tenantId: $listing->tenant_id,
                transferableType: 'license',
                transferableId: $license->id,
                sourceCustomerId: $sourceCustomerId,
                targetCustomerId: $targetCustomerId,
                requestedBy: $adminUserId,
                notes: "二级市场交易 #{$transaction->reference}",
            );

            // 自动确认并执行（管理员操作）
            $this->transferService->confirmBySource($transferRequest->id, $adminUserId);
            $this->transferService->confirmByTarget($transferRequest->id, $adminUserId);
            $this->transferService->approveAndExecute($transferRequest->id, $adminUserId);

            // 记录审计日志
            $auditEntry = [
                'action' => 'resale_transfer_executed',
                'transaction_reference' => $transaction->reference,
                'transfer_request_id' => $transferRequest->id,
                'license_key' => $license->license_key,
                'from_customer_id' => $sourceCustomerId,
                'to_customer_id' => $targetCustomerId,
                'executed_by' => $adminUserId,
                'executed_at' => now()->toDateTimeString(),
            ];

            $auditLog = $transaction->audit_log ?? [];
            $auditLog[] = $auditEntry;

            $transaction->update([
                'status' => ResaleTransaction::STATUS_COMPLETED,
                'executed_by' => $adminUserId,
                'executed_at' => now(),
                'audit_log' => $auditLog,
            ]);

            return $transaction->fresh();
        });
    }

    /**
     * 取消交易（退款处理）
     */
    public function cancelTransaction(int $transactionId, int $userId): ResaleTransaction
    {
        return DB::transaction(function () use ($transactionId, $userId) {
            $transaction = ResaleTransaction::with('listing')->findOrFail($transactionId);

            if (in_array($transaction->status, [ResaleTransaction::STATUS_COMPLETED, ResaleTransaction::STATUS_REFUNDED, ResaleTransaction::STATUS_CANCELLED])) {
                throw new \RuntimeException('该交易已结束，无法取消');
            }

            $transaction->update(['status' => ResaleTransaction::STATUS_CANCELLED]);

            // 恢复挂牌为激活状态
            $listing = $transaction->listing;
            if ($listing->status === ResaleListing::STATUS_SOLD) {
                $listing->update([
                    'status' => ResaleListing::STATUS_ACTIVE,
                    'sold_at' => null,
                ]);
            }

            return $transaction->fresh();
        });
    }

    // ──────────────────────────────────────────────
    //  License 可转让性判断
    // ──────────────────────────────────────────────

    /**
     * 检查 License 是否可以转售
     */
    public function canBeResold(License $license): bool
    {
        // 不可以是已过期/已吊销/已删除的 License
        if (in_array($license->status, ['expired', 'revoked', 'suspended'])) {
            return false;
        }

        return true;
    }

    /**
     * 获取可挂牌的 License 列表（卖给家的）
     */
    public function getSellableLicenses(int $tenantId, int $sellerCustomerId): array
    {
        $licenses = License::where('tenant_id', $tenantId)
            ->where('customer_id', $sellerCustomerId)
            ->whereNotIn('status', ['expired', 'revoked', 'suspended'])
            ->get();

        // 过滤掉已经在挂牌中的
        $listedLicenseIds = ResaleListing::where('tenant_id', $tenantId)
            ->where('seller_customer_id', $sellerCustomerId)
            ->whereIn('status', [
                ResaleListing::STATUS_DRAFT,
                ResaleListing::STATUS_PUBLISHED,
                ResaleListing::STATUS_PENDING_REVIEW,
                ResaleListing::STATUS_ACTIVE,
            ])
            ->pluck('license_id')
            ->toArray();

        return $licenses->reject(fn ($l) => in_array($l->id, $listedLicenseIds))->values()->toArray();
    }

    // ──────────────────────────────────────────────
    //  统计
    // ──────────────────────────────────────────────

    /**
     * 获取市场统计
     */
    public function getMarketStats(int $tenantId): array
    {
        $activeListings = ResaleListing::where('tenant_id', $tenantId)
            ->where('status', ResaleListing::STATUS_ACTIVE)->count();

        $totalSold = ResaleListing::where('tenant_id', $tenantId)
            ->where('status', ResaleListing::STATUS_SOLD)->count();

        $totalRevenue = ResaleTransaction::where('tenant_id', $tenantId)
            ->where('status', ResaleTransaction::STATUS_COMPLETED)
            ->sum('commission_amount');

        $avgPrice = ResaleTransaction::where('tenant_id', $tenantId)
            ->where('status', ResaleTransaction::STATUS_COMPLETED)
            ->avg('agreed_price');

        return [
            'active_listings' => $activeListings,
            'total_sold' => $totalSold,
            'total_commission_revenue' => round($totalRevenue, 2),
            'average_sale_price' => round($avgPrice ?? 0, 2),
        ];
    }

    /**
     * 获取卖家统计
     */
    public function getSellerStats(int $tenantId, int $sellerCustomerId): array
    {
        $totalListings = ResaleListing::where('tenant_id', $tenantId)
            ->where('seller_customer_id', $sellerCustomerId)->count();

        $activeListings = ResaleListing::where('tenant_id', $tenantId)
            ->where('seller_customer_id', $sellerCustomerId)
            ->where('status', ResaleListing::STATUS_ACTIVE)->count();

        $totalSold = ResaleListing::where('tenant_id', $tenantId)
            ->where('seller_customer_id', $sellerCustomerId)
            ->where('status', ResaleListing::STATUS_SOLD)->count();

        $totalEarnings = ResaleTransaction::where('tenant_id', $tenantId)
            ->whereHas('listing', fn ($q) => $q->where('seller_customer_id', $sellerCustomerId))
            ->where('status', ResaleTransaction::STATUS_COMPLETED)
            ->sum('seller_payout');

        return [
            'total_listings' => $totalListings,
            'active_listings' => $activeListings,
            'total_sold' => $totalSold,
            'total_earnings' => round($totalEarnings, 2),
        ];
    }
}
