<?php

namespace App\Console\Commands;

use App\Services\ContentPurchaseService;
use Illuminate\Console\Command;

class SettleContentEarnings extends Command
{
    protected $signature = 'content-earnings:settle
        {--dry-run : 仅模拟，不实际结算}
        {--type= : 仅结算指定类型 (oa/forum)}';

    protected $description = '批量结算待处理的金额付费内容收益，转入作者 EarningsAccount';

    public function handle(ContentPurchaseService $service): int
    {
        if ($this->option('dry-run')) {
            $pendingOa = \App\Models\OaArticlePurchase::where('status', 'pending')
                ->where('price_type', 'money')->count();
            $pendingForum = \App\Models\ForumPostPurchase::where('status', 'pending')
                ->where('price_type', 'money')->count();
            $this->info("[DRY RUN] 待结算: OA {$pendingOa} 笔, 论坛 {$pendingForum} 笔");
            return self::SUCCESS;
        }

        $type = $this->option('type');
        $results = ['oa' => 0, 'forum' => 0, 'errors' => []];

        if (!$type || $type === 'oa') {
            $this->info('处理 OA 文章待结算...');
            $oaPurchases = \App\Models\OaArticlePurchase::where('status', 'pending')
                ->where('price_type', 'money')->get();
            foreach ($oaPurchases as $purchase) {
                try {
                    $service->confirmOaPurchase($purchase->id);
                    $results['oa']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = "OA #{$purchase->id}: {$e->getMessage()}";
                }
            }
        }

        if (!$type || $type === 'forum') {
            $this->info('处理社区帖子待结算...');
            $forumPurchases = \App\Models\ForumPostPurchase::where('status', 'pending')
                ->where('price_type', 'money')->get();
            foreach ($forumPurchases as $purchase) {
                try {
                    $service->confirmForumPurchase($purchase->id);
                    $results['forum']++;
                } catch (\Throwable $e) {
                    $results['errors'][] = "论坛 #{$purchase->id}: {$e->getMessage()}";
                }
            }
        }

        $this->info("结算完成: OA {$results['oa']} 笔, 论坛 {$results['forum']} 笔");
        foreach ($results['errors'] as $err) {
            $this->error($err);
        }

        return self::SUCCESS;
    }
}
