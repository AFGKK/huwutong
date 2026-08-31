<?php

namespace App\Services;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceAppInstallation;
use App\Models\MarketplacePushCampaign;
use App\Models\MarketplacePushDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MarketplacePushService
{
    protected NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * 创建推送活动
     */
    public function createCampaign(array $data, int $userId): MarketplacePushCampaign
    {
        if (!Schema::hasTable('marketplace_push_campaigns')) {
            throw new \RuntimeException(__("app.marketplace_push.msg_740007bc"));
        }

        $campaign = MarketplacePushCampaign::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'type' => $data['type'] ?? 'marketing',
            'target_type' => $data['target_type'] ?? 'all',
            'target_app_id' => $data['target_app_id'] ?? null,
            'target_category' => $data['target_category'] ?? null,
            'link_type' => $data['link_type'] ?? null,
            'link_value' => $data['link_value'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'status' => 'draft',
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'created_by' => $userId,
        ]);

        // 计算目标用户数
        $count = $this->countTargetUsers($campaign);
        $campaign->update(['target_count' => $count]);

        return $campaign->fresh();
    }

    /**
     * 更新推送活动
     */
    public function updateCampaign(int $id, array $data): MarketplacePushCampaign
    {
        $campaign = MarketplacePushCampaign::findOrFail($id);

        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException(__("app.marketplace_push.msg_138bce49"));
        }

        $campaign->update($data);

        if (isset($data['target_type']) || isset($data['target_app_id']) || isset($data['target_category'])) {
            $campaign->update(['target_count' => $this->countTargetUsers($campaign->fresh())]);
        }

        return $campaign->fresh();
    }

    /**
     * 发送推送
     */
    public function sendCampaign(int $id): MarketplacePushCampaign
    {
        $campaign = MarketplacePushCampaign::findOrFail($id);

        if ($campaign->status === 'sent') {
            throw new \RuntimeException(__("app.marketplace_push.msg_5d333576"));
        }

        // 如果是定时发送，标记为 scheduled
        if ($campaign->scheduled_at && $campaign->scheduled_at->isFuture()) {
            $campaign->update(['status' => 'scheduled']);
            return $campaign->fresh();
        }

        return $this->executeSend($campaign);
    }

    /**
     * 立即执行发送
     */
    public function executeSend(MarketplacePushCampaign $campaign): MarketplacePushCampaign
    {
        $users = $this->getTargetUsers($campaign);

        DB::transaction(function () use ($campaign, $users) {
            $campaign->update([
                'status' => 'sending',
                'sent_at' => now(),
            ]);

            $sentCount = 0;
            foreach ($users as $user) {
                try {
                    // 写入投递记录
                    MarketplacePushDelivery::create([
                        'campaign_id' => $campaign->id,
                        'user_id' => $user->id,
                        'channel' => 'in_app',
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    // 通过 NotificationService 发送站内通知
                    $this->notificationService->send(
                        userId: $user->id,
                        type: 'marketplace_push',
                        title: $campaign->title,
                        content: $campaign->content,
                        payload: [
                            'campaign_id' => $campaign->id,
                            'link_type' => $campaign->link_type,
                            'link_value' => $campaign->link_value,
                            'type' => $campaign->type,
                        ]
                    );

                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error("Push send failed for user {$user->id}: " . $e->getMessage());
                }
            }

            $campaign->update([
                'status' => 'sent',
                'sent_count' => $sentCount,
                'completed_at' => now(),
            ]);
        });

        return $campaign->fresh();
    }

    /**
     * 取消推送
     */
    public function cancelCampaign(int $id): MarketplacePushCampaign
    {
        $campaign = MarketplacePushCampaign::findOrFail($id);
        if (!in_array($campaign->status, ['draft', 'scheduled'])) {
            throw new \RuntimeException(__("app.marketplace_push.msg_b6554ae1"));
        }
        $campaign->update(['status' => 'cancelled']);
        return $campaign->fresh();
    }

    /**
     * 统计目标用户数
     */
    public function countTargetUsers(MarketplacePushCampaign $campaign): int
    {
        return $this->buildTargetQuery($campaign)->count();
    }

    /**
     * 获取目标用户列表
     */
    public function getTargetUsers(MarketplacePushCampaign $campaign)
    {
        return $this->buildTargetQuery($campaign)->get();
    }

    /**
     * 构建目标用户查询
     */
    protected function buildTargetQuery(MarketplacePushCampaign $campaign)
    {
        $query = User::where('status', 'active');

        switch ($campaign->target_type) {
            case 'installed_app':
                $appIds = MarketplaceAppInstallation::where('status', 'active')
                    ->when($campaign->target_app_id, fn($q) => $q->where('app_id', $campaign->target_app_id))
                    ->pluck('user_id');
                $query->whereIn('id', $appIds);
                break;

            case 'category':
                $appIds = MarketplaceApp::where('status', 'published')
                    ->where('category', $campaign->target_category)
                    ->pluck('id');
                $userIds = MarketplaceAppInstallation::whereIn('app_id', $appIds)
                    ->where('status', 'active')
                    ->pluck('user_id');
                $query->whereIn('id', $userIds);
                break;

            case 'specific_app':
                $userIds = MarketplaceAppInstallation::where('app_id', $campaign->target_app_id)
                    ->where('status', 'active')
                    ->pluck('user_id');
                $query->whereIn('id', $userIds);
                break;

            case 'all':
            default:
                // 所有活跃用户
                break;
        }

        return $query;
    }

    /**
     * 获取推送活动列表
     */
    public function getCampaigns(array $filters = [], int $perPage = 20)
    {
        if (!Schema::hasTable('marketplace_push_campaigns')) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        $query = MarketplacePushCampaign::with('creator:id,name')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->paginate($perPage);
    }

    /**
     * 获取推送统计
     */
    public function getStats(): array
    {
        if (!Schema::hasTable('marketplace_push_campaigns')) {
            return ['total_campaigns' => 0, 'total_sent' => 0, 'total_read' => 0, 'draft_count' => 0, 'scheduled_count' => 0, 'recent_campaigns' => []];
        }

        return [
            'total_campaigns' => MarketplacePushCampaign::count(),
            'total_sent' => MarketplacePushCampaign::sum('sent_count'),
            'total_read' => MarketplacePushCampaign::sum('read_count'),
            'draft_count' => MarketplacePushCampaign::where('status', 'draft')->count(),
            'scheduled_count' => MarketplacePushCampaign::where('status', 'scheduled')->count(),
            'recent_campaigns' => MarketplacePushCampaign::with('creator:id,name')
                ->orderByDesc('created_at')->take(5)->get(),
        ];
    }

    /**
     * 处理定时发送（由命令调用）
     */
    public function processScheduledCampaigns(): int
    {
        $campaigns = MarketplacePushCampaign::scheduled()
            ->where('scheduled_at', '<=', now())
            ->get();

        $sent = 0;
        foreach ($campaigns as $campaign) {
            try {
                $this->executeSend($campaign);
                $sent++;
            } catch (\Exception $e) {
                Log::error("Scheduled push #{$campaign->id} failed: " . $e->getMessage());
            }
        }

        return $sent;
    }
}
