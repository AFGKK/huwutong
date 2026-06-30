<?php

namespace App\Services;

use App\Models\PreSaleCampaign;
use App\Models\PreSaleOrder;
use App\Models\PreSaleUpdate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PreSaleService
{
    public function __construct(
        protected PreSalePaymentService $paymentService,
    ) {}

    // ─── 活动管理 ───

    /**
     * 列表
     */
    public function listCampaigns(array $filters = [])
    {
        $query = PreSaleCampaign::with(['product:id,name,slug'])->withCount('orders');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhereHas('product', fn($pq) => $pq->where('name', 'like', "%{$s}%"));
            });
        }

        $sort = $filters['sort'] ?? 'latest';
        match ($sort) {
            'oldest' => $query->oldest(),
            'ending_soon' => $query->where('status', 'active')->orderBy('end_at'),
            'most_raised' => $query->orderByDesc('raised_amount'),
            default => $query->latest(),
        };

        $perPage = (int) ($filters['per_page'] ?? 20);
        return $query->paginate(min($perPage, 100));
    }

    /**
     * 公开活动列表
     */
    public function listPublishedCampaigns(array $filters = [])
    {
        $query = PreSaleCampaign::with(['product:id,name,slug'])
            ->whereIn('status', ['active', 'success'])
            ->withCount('orders');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * 创建活动
     */
    public function createCampaign(array $data): PreSaleCampaign
    {
        return DB::transaction(function () use ($data) {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6);
            $data['status'] = 'draft';
            $data['raised_amount'] = 0;
            $data['current_backers'] = 0;

            return PreSaleCampaign::create($data);
        });
    }

    /**
     * 更新活动
     */
    public function updateCampaign(int $id, array $data): PreSaleCampaign
    {
        $campaign = PreSaleCampaign::findOrFail($id);

        if ($campaign->status !== 'draft' && $campaign->status !== 'pending') {
            // 已开始的活动只允许修改部分字段
            $allowed = ['description', 'images', 'estimated_delivery_at', 'settings'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        $campaign->update($data);
        return $campaign->fresh()->load(['product:id,name,slug']);
    }

    /**
     * 发布活动
     */
    public function publishCampaign(int $id): PreSaleCampaign
    {
        $campaign = PreSaleCampaign::findOrFail($id);
        throw_unless($campaign->status === 'draft', \RuntimeException::class, '只有草稿状态的活动可以发布');

        $campaign->update(['status' => 'active']);
        return $campaign->fresh();
    }

    /**
     * 取消活动
     */
    public function cancelCampaign(int $id, ?string $reason = null): PreSaleCampaign
    {
        $campaign = PreSaleCampaign::findOrFail($id);
        throw_unless(in_array($campaign->status, ['draft', 'pending', 'active']), \RuntimeException::class, '该状态不允许取消');

        DB::transaction(function () use ($campaign, $reason) {
            $campaign->update([
                'status' => 'cancelled',
                'fail_reason' => $reason,
            ]);

            // 退还未完成的订单
            $pendingOrders = $campaign->orders()
                ->whereIn('payment_status', ['deposit_paid', 'final_pending', 'final_paid'])
                ->get();

            foreach ($pendingOrders as $order) {
                if (in_array($order->payment_status, ['deposit_paid', 'final_paid'], true)) {
                    $this->paymentService->refundOrder($order, $reason ?? '活动已取消');
                } else {
                    $order->update(['payment_status' => 'refunded']);
                }
            }
        });

        return $campaign->fresh();
    }

    /**
     * 检查并更新活动状态（众筹结束/达目标）
     */
    public function checkCampaignStatus(int $id): PreSaleCampaign
    {
        $campaign = PreSaleCampaign::findOrFail($id);

        if ($campaign->status !== 'active') {
            return $campaign;
        }

        if (!$campaign->hasEnded()) {
            return $campaign;
        }

        $newStatus = $campaign->hasReachedTarget() ? 'success' : 'failed';
        $campaign->update(['status' => $newStatus]);

        if ($newStatus === 'failed' && ($campaign->settings['refund_on_fail'] ?? true)) {
            $this->refundFailedCampaignOrders($campaign);
        }

        return $campaign->fresh();
    }

    /**
     * 完成活动（发货/交付）
     */
    public function completeCampaign(int $id): PreSaleCampaign
    {
        $campaign = PreSaleCampaign::findOrFail($id);
        throw_unless($campaign->status === 'success', \RuntimeException::class, '只有成功的活动可以完成');

        DB::transaction(function () use ($campaign) {
            $campaign->update(['status' => 'completed']);

            // 标记所有已付尾款的订单为已处理
            $campaign->orders()->where('payment_status', 'final_paid')
                ->update(['fulfillment_status' => 'processing']);
        });

        return $campaign->fresh();
    }

    /**
     * 获取活动统计
     */
    public function getStats(): array
    {
        $total = PreSaleCampaign::count();
        $active = PreSaleCampaign::where('status', 'active')->count();
        $success = PreSaleCampaign::where('status', 'success')->count();
        $failed = PreSaleCampaign::where('status', 'failed')->count();

        $totalRaised = PreSaleCampaign::whereIn('status', ['active', 'success', 'completed'])
            ->sum('raised_amount');

        $totalBackers = PreSaleCampaign::whereIn('status', ['active', 'success', 'completed'])
            ->sum('current_backers');

        return compact('total', 'active', 'success', 'failed', 'totalRaised', 'totalBackers');
    }

    /**
     * 删除活动
     */
    public function deleteCampaign(int $id): void
    {
        $campaign = PreSaleCampaign::findOrFail($id);
        throw_unless(in_array($campaign->status, ['draft', 'failed', 'cancelled']), \RuntimeException::class, '只有草稿/失败/已取消的活动可以删除');
        $campaign->delete();
    }

    // ─── 订单管理 ───

    /**
     * 参与预售/众筹
     */
    public function placeOrder(array $data): PreSaleOrder
    {
        return DB::transaction(function () use ($data) {
            $campaign = PreSaleCampaign::findOrFail($data['campaign_id']);
            throw_unless($campaign->isActive(), \RuntimeException::class, '该活动当前不可参与');

            $orderNo = 'PS' . date('Ymd') . Str::random(10);

            $orderData = [
                'campaign_id' => $campaign->id,
                'tenant_id' => $campaign->tenant_id,
                'customer_id' => $data['customer_id'] ?? null,
                'user_id' => $data['user_id'],
                'order_no' => $orderNo,
                'tier_index' => $data['tier_index'] ?? null,
                'tier_name' => $data['tier_name'] ?? '',
                'quantity' => $data['quantity'] ?? 1,
                'currency' => $campaign->currency,
                'payment_status' => 'deposit_pending',
                'notes' => $data['notes'] ?? null,
            ];

            // 计算金额
            if ($campaign->deposit_amount > 0) {
                $deposit = $campaign->deposit_amount * $orderData['quantity'];
                $total = ($data['total_amount'] ?? 0) ?: $campaign->product?->licenses()->count() * 100;
            } else {
                $total = ($data['total_amount'] ?? 0) ?: $campaign->product?->licenses()->count() * 100;
                $deposit = $total * ($campaign->deposit_rate / 100);
            }

            $orderData['total_amount'] = $total;
            $orderData['deposit_paid'] = 0;
            $orderData['final_payment'] = $total - $deposit;
            $orderData['final_paid'] = 0;

            $order = PreSaleOrder::create($orderData);

            // 更新活动统计
            $campaign->increment('current_backers');

            return $order;
        });
    }

    /**
     * 支付定金
     */
    public function payDeposit(int $orderId, ?string $paymentMethod = null): PreSaleOrder
    {
        return DB::transaction(function () use ($orderId, $paymentMethod) {
            $order = PreSaleOrder::with('campaign')->findOrFail($orderId);
            throw_unless($order->payment_status === 'deposit_pending', \RuntimeException::class, '该订单状态不允许支付定金');

            $deposit = $this->paymentService->calculateDepositAmount($order);
            $this->paymentService->chargeDeposit($order, $paymentMethod);

            $order->update([
                'deposit_paid' => $deposit,
                'deposit_paid_at' => now(),
                'payment_status' => 'deposit_paid',
            ]);

            $order->campaign->increment('raised_amount', $deposit);

            return $order->fresh();
        });
    }

    /**
     * 支付尾款
     */
    public function payFinal(int $orderId, ?string $paymentMethod = null): PreSaleOrder
    {
        return DB::transaction(function () use ($orderId, $paymentMethod) {
            $order = PreSaleOrder::with('campaign')->findOrFail($orderId);
            throw_unless($order->payment_status === 'deposit_paid', \RuntimeException::class, '该订单状态不允许支付尾款');
            throw_unless(in_array($order->campaign->status, ['active', 'success']), \RuntimeException::class, '该活动已结束，无法支付尾款');

            $finalAmount = max(0, (float) $order->total_amount - (float) $order->deposit_paid);
            $this->paymentService->chargeFinal($order, $paymentMethod);

            $order->update([
                'final_paid' => $finalAmount,
                'final_paid_at' => now(),
                'payment_status' => 'final_paid',
            ]);

            if ($finalAmount > 0) {
                $order->campaign->increment('raised_amount', $finalAmount);
            }

            return $order->fresh();
        });
    }

    /**
     * 众筹失败自动退款
     */
    protected function refundFailedCampaignOrders(PreSaleCampaign $campaign): void
    {
        $orders = $campaign->orders()
            ->whereIn('payment_status', ['deposit_paid', 'final_paid'])
            ->get();

        foreach ($orders as $order) {
            try {
                $this->paymentService->refundOrder($order, '众筹未达标，自动退款');
            } catch (\Throwable $e) {
                Log::error('PreSale: auto refund failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 订单列表
     */
    public function listOrders(array $filters = [])
    {
        $query = PreSaleOrder::with(['campaign:id,name,type,status', 'user:id,name,email']);

        if (!empty($filters['campaign_id'])) {
            $query->where('campaign_id', (int) $filters['campaign_id']);
        }
        if (!empty($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }
        if (!empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        return $query->latest()->paginate(20);
    }

    /**
     * 更新发货状态
     */
    public function updateFulfillmentStatus(int $orderId, string $status): PreSaleOrder
    {
        $order = PreSaleOrder::findOrFail($orderId);
        $order->update([
            'fulfillment_status' => $status,
            'fulfilled_at' => $status === 'delivered' ? now() : $order->fulfilled_at,
        ]);
        return $order->fresh();
    }

    // ─── 活动更新 ───

    /**
     * 发布活动更新
     */
    public function postUpdate(int $campaignId, array $data): PreSaleUpdate
    {
        return PreSaleUpdate::create([
            'campaign_id' => $campaignId,
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'] ?? 'update',
            'is_pinned' => $data['is_pinned'] ?? false,
        ]);
    }

    /**
     * 获取活动更新
     */
    public function getUpdates(int $campaignId): Collection
    {
        return PreSaleUpdate::where('campaign_id', $campaignId)
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * 删除更新
     */
    public function deleteUpdate(int $updateId): void
    {
        PreSaleUpdate::findOrFail($updateId)->delete();
    }
}
