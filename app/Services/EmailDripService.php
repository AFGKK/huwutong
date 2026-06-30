<?php

namespace App\Services;

use App\Models\EmailDripCampaign;
use App\Models\EmailDripSequence;
use App\Models\EmailDripRecipient;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 邮件营销 Drip 序列服务 (M2-102)
 */
class EmailDripService
{
    /**
     * 创建营销活动
     */
    public function createCampaign(int $tenantId, array $data): EmailDripCampaign
    {
        return EmailDripCampaign::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'trigger_event' => $data['trigger_event'],
            'status' => 'draft',
            'description' => $data['description'] ?? null,
            'target_filters' => $data['target_filters'] ?? null,
        ]);
    }

    /**
     * 添加序列步骤
     */
    public function addSequence(int $campaignId, array $data): EmailDripSequence
    {
        $maxSort = EmailDripSequence::where('campaign_id', $campaignId)->max('sort_order') ?? 0;

        return EmailDripSequence::create([
            'campaign_id' => $campaignId,
            'name' => $data['name'],
            'delay_days' => $data['delay_days'],
            'subject' => $data['subject'],
            'content' => $data['content'],
            'template_id' => $data['template_id'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxSort + 1),
            'ab_test' => $data['ab_test'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    /**
     * 启动营销活动
     */
    public function activateCampaign(int $campaignId): EmailDripCampaign
    {
        $campaign = EmailDripCampaign::findOrFail($campaignId);
        $campaign->update(['status' => 'active', 'started_at' => now()]);
        return $campaign->fresh();
    }

    /**
     * 暂停/停止
     */
    public function pauseCampaign(int $campaignId): EmailDripCampaign
    {
        $campaign = EmailDripCampaign::findOrFail($campaignId);
        $campaign->update(['status' => 'paused']);
        return $campaign->fresh();
    }

    /**
     * 统计看板
     */
    public function getDashboard(int $tenantId): array
    {
        $campaigns = EmailDripCampaign::where('tenant_id', $tenantId)->withCount('sequences')->get();

        $totalSent = EmailDripRecipient::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->where('status', 'sent')->count();
        $totalOpened = EmailDripRecipient::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('opened_at')->count();
        $totalClicked = EmailDripRecipient::whereHas('campaign', fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('clicked_at')->count();

        $openRate = $totalSent > 0 ? round(($totalOpened / $totalSent) * 100, 1) : 0;
        $clickRate = $totalOpened > 0 ? round(($totalClicked / $totalOpened) * 100, 1) : 0;

        return [
            'total_campaigns' => $campaigns->count(),
            'active_campaigns' => $campaigns->where('status', 'active')->count(),
            'total_sent' => $totalSent,
            'open_rate' => $openRate,
            'click_rate' => $clickRate,
            'recent_campaigns' => $campaigns->sortByDesc('id')->take(5)->values(),
        ];
    }

    /**
     * 获取活动列表
     */
    public function listCampaigns(int $tenantId, array $filters = []): array
    {
        $query = EmailDripCampaign::where('tenant_id', $tenantId)->withCount('sequences');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->orderByDesc('id')->paginate($perPage)->withQueryString()->toArray();
    }

    /**
     * 获取活动详情
     */
    public function getCampaign(int $campaignId): array
    {
        $campaign = EmailDripCampaign::with('sequences')->findOrFail($campaignId);

        $stats = [];
        foreach ($campaign->sequences as $seq) {
            $total = $seq->recipients()->count();
            $sent = $seq->recipients()->where('status', 'sent')->count();
            $opened = $seq->recipients()->whereNotNull('opened_at')->count();
            $clicked = $seq->recipients()->whereNotNull('clicked_at')->count();

            $stats[] = [
                'sequence_id' => $seq->id,
                'name' => $seq->name,
                'total' => $total,
                'sent' => $sent,
                'opened' => $opened,
                'clicked' => $clicked,
                'open_rate' => $sent > 0 ? round(($opened / $sent) * 100, 1) : 0,
                'click_rate' => $opened > 0 ? round(($clicked / $opened) * 100, 1) : 0,
            ];
        }

        return [
            'campaign' => $campaign,
            'sequence_stats' => $stats,
        ];
    }

    /**
     * 处理触发事件（定时任务调用）
     */
    public function processTrigger(string $triggerEvent, int $customerId, int $tenantId): void
    {
        $campaigns = EmailDripCampaign::where('tenant_id', $tenantId)
            ->where('trigger_event', $triggerEvent)
            ->where('status', 'active')
            ->get();

        foreach ($campaigns as $campaign) {
            foreach ($campaign->sequences as $seq) {
                $sendAt = now()->addDays($seq->delay_days);

                EmailDripRecipient::create([
                    'campaign_id' => $campaign->id,
                    'sequence_id' => $seq->id,
                    'customer_id' => $customerId,
                    'email' => Customer::find($customerId)?->email ?? '',
                    'status' => 'pending',
                ]);

                Log::info('Drip序列已入队', [
                    'campaign' => $campaign->name,
                    'sequence' => $seq->name,
                    'customer_id' => $customerId,
                    'send_at' => $sendAt,
                ]);
            }
        }
    }

    /**
     * 追踪打开
     */
    public function trackOpen(int $recipientId): void
    {
        EmailDripRecipient::where('id', $recipientId)
            ->whereNull('opened_at')
            ->update(['opened_at' => now(), 'status' => 'opened']);
    }

    /**
     * 追踪点击
     */
    public function trackClick(int $recipientId): void
    {
        EmailDripRecipient::where('id', $recipientId)
            ->whereNull('clicked_at')
            ->update(['clicked_at' => now(), 'status' => 'clicked']);
    }
}
