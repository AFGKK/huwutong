<?php

namespace App\Services;

use App\Models\EarningsAccount;
use App\Models\ForumPostEarning;
use App\Models\ForumPostPurchase;
use App\Models\OaArticleEarning;
use App\Models\OaArticlePurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 内容付费结算服务
 *
 * 负责将 pending 状态的付费购买确认为 completed，
 * 并将收益转入作者的 EarningsAccount，使其可提现。
 *
 * 同时支持 OA 文章和社区帖子两种付费类型。
 */
class ContentPurchaseService
{
    /**
     * 确认 OA 文章购买（支付成功后调用）
     */
    public function confirmOaPurchase(int $purchaseId): array
    {
        $purchase = OaArticlePurchase::findOrFail($purchaseId);

        if ($purchase->status === 'completed') {
            return ['success' => true, 'message' => __('app.common.confirmed')];
        }
        if ($purchase->price_type !== 'money') {
            return ['success' => false, 'message' => __('app.common.only_amount_payment_requires_settlement')];
        }

        return DB::transaction(function () use ($purchase) {
            $purchase->update(['status' => 'completed']);

            // 更新收益记录
            $earning = OaArticleEarning::where('purchase_table', 'oa_article_purchases')
                ->where('purchase_id', $purchase->id)
                ->where('status', 'pending')
                ->first();

            if ($earning) {
                $earning->update(['status' => 'settled']);

                // 入账到作者收益账户（与门户收益账户统一，用户可在 /portal/earnings 查看提现）
                $account = EarningsAccount::firstOrCreate(
                    ['user_id' => $earning->author_id, 'type' => 'agent'],
                    ['pending_balance' => 0, 'available_balance' => 0, 'total_withdrawn' => 0, 'status' => 'active']
                );
                $account->increment('available_balance', $earning->net_amount);
                $account->increment('lifetime_settled', $earning->net_amount);

                Log::info('[ContentPurchase] OA文章结算成功', [
                    'purchase_id' => $purchase->id,
                    'article_id'  => $purchase->article_id,
                    'author_id'   => $earning->author_id,
                    'amount'      => $earning->net_amount,
                ]);

                return [
                    'success'  => true,
                    'message'  => __('app.common.settlement_success'),
                    'author_id' => $earning->author_id,
                    'amount'   => (float) $earning->net_amount,
                ];
            }

            return ['success' => true, 'message' => __('app.common.purchase_confirmed_no_pending_settlement')];
        });
    }

    /**
     * 确认社区帖子购买（支付成功后调用）
     */
    public function confirmForumPurchase(int $purchaseId): array
    {
        $purchase = ForumPostPurchase::findOrFail($purchaseId);

        if ($purchase->status === 'completed') {
            return ['success' => true, 'message' => __('app.common.confirmed')];
        }
        if ($purchase->price_type !== 'money') {
            return ['success' => false, 'message' => __('app.common.only_amount_payment_requires_settlement')];
        }

        return DB::transaction(function () use ($purchase) {
            $purchase->update(['status' => 'completed', 'paid_at' => now()]);

            // 更新收益记录
            $earning = ForumPostEarning::where('purchase_table', 'forum_post_purchases')
                ->where('purchase_id', $purchase->id)
                ->where('status', 'pending')
                ->first();

            if ($earning) {
                $earning->update(['status' => 'settled']);

                // 入账到作者收益账户（与门户收益账户统一）
                $account = EarningsAccount::firstOrCreate(
                    ['user_id' => $earning->author_id, 'type' => 'agent'],
                    ['pending_balance' => 0, 'available_balance' => 0, 'total_withdrawn' => 0, 'status' => 'active']
                );
                $account->increment('available_balance', $earning->net_amount);
                $account->increment('lifetime_settled', $earning->net_amount);

                Log::info('[ContentPurchase] 社区帖子结算成功', [
                    'purchase_id' => $purchase->id,
                    'post_id'     => $purchase->post_id,
                    'author_id'   => $earning->author_id,
                    'amount'      => $earning->net_amount,
                ]);

                return [
                    'success'   => true,
                    'message'   => __('app.common.settlement_success'),
                    'author_id' => $earning->author_id,
                    'amount'    => (float) $earning->net_amount,
                ];
            }

            return ['success' => true, 'message' => __('app.common.purchase_confirmed_no_pending_settlement')];
        });
    }

    /**
     * 批量结算所有待处理的金额支付
     */
    public function settleAllPending(): array
    {
        $results = ['oa' => 0, 'forum' => 0, 'errors' => []];

        // OA 文章
        $oaPurchases = OaArticlePurchase::where('status', 'pending')
            ->where('price_type', 'money')
            ->get();

        foreach ($oaPurchases as $purchase) {
            try {
                $this->confirmOaPurchase($purchase->id);
                $results['oa']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "OA #{$purchase->id}: {$e->getMessage()}";
            }
        }

        // 社区帖子
        $forumPurchases = ForumPostPurchase::where('status', 'pending')
            ->where('price_type', 'money')
            ->get();

        foreach ($forumPurchases as $purchase) {
            try {
                $this->confirmForumPurchase($purchase->id);
                $results['forum']++;
            } catch (\Throwable $e) {
                $results['errors'][] = "论坛 #{$purchase->id}: {$e->getMessage()}";
            }
        }

        return $results;
    }
}
