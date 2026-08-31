<?php

namespace App\Console\Commands;

use App\Models\MarketingCampaign;
use App\Services\MarketingCampaignService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * D-24: 自动化营销活动处理命令
 *
 * 定期处理待发送的营销活动。
 *
 * php artisan marketing:process-campaigns
 * php artisan marketing:process-campaigns --campaign=12
 * php artisan marketing:process-campaigns --batch=50
 */
class ProcessMarketingCampaignsCommand extends Command
{
    protected $signature = 'marketing:process-campaigns
        {--campaign= : 指定活动 ID（可选）}
        {--batch=100 : 每批处理数量}';

    protected $description = 'D-24: 处理待发送的自动化营销活动';

    public function handle(MarketingCampaignService $service): int
    {
        $this->info('=== 自动化营销处理 ===');

        $query = MarketingCampaign::where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('started_at')
                    ->orWhere('started_at', '<=', now());
            });

        if ($campaignId = $this->option('campaign')) {
            $query->where('id', $campaignId);
        }

        $batchSize = (int) $this->option('batch');
        $campaigns = $query->get();

        if ($campaigns->isEmpty()) {
            $this->warn('没有待处理的营销活动');
            return self::SUCCESS;
        }

        $totalSent = 0;
        $totalFailed = 0;

        foreach ($campaigns as $campaign) {
            $this->line("处理活动: [{$campaign->id}] {$campaign->name}");

            if ($campaign->scheduled_at && Carbon::parse($campaign->scheduled_at)->isFuture()) {
                $this->line("  跳过（调度时间未到: {$campaign->scheduled_at}）");
                continue;
            }

            try {
                $result = $service->sendCampaign(
                    $campaign->tenant_id,
                    $campaign->id,
                    ['batch_size' => $batchSize]
                );

                $totalSent += $result['sent'];
                $totalFailed += $result['failed'];

                $this->line("  已发送: {$result['sent']}, 失败: {$result['failed']}, 剩余: {$result['remaining']}");
            } catch (\Throwable $e) {
                $this->error("  处理失败: {$e->getMessage()}");
                $totalFailed++;
            }
        }

        $this->newLine();
        $this->info("处理完成: 总计发送 {$totalSent}, 失败 {$totalFailed}");

        return self::SUCCESS;
    }
}
