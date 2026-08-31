<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignLog;
use App\Models\MarketingCampaignStep;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 多渠道营销自动化服务 (M3-20)
 *
 * 提供：
 * 1. 营销活动管理（创建/启动/暂停/完成）
 * 2. 多步骤营销流程
 * 3. 目标受众筛选与发送
 * 4. A/B测试
 * 5. 效果分析与仪表盘
 */
class MarketingCampaignService
{
    /**
     * 列示营销活动
     */
    public function listCampaigns(int $tenantId, array $filters = []): array
    {
        $query = MarketingCampaign::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%");
            });
        }

        $perPage = $filters['per_page'] ?? 20;
        return $query->paginate($perPage)->withQueryString()->through(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'description' => $c->description,
                'status' => $c->status,
                'type' => $c->type,
                'is_ab_test' => $c->is_ab_test,
                'scheduled_at' => $c->scheduled_at?->toIso8601String(),
                'started_at' => $c->started_at?->toIso8601String(),
                'ended_at' => $c->ended_at?->toIso8601String(),
                'target_count' => $c->target_count,
                'sent_count' => $c->sent_count,
                'delivered_count' => $c->delivered_count,
                'opened_count' => $c->opened_count,
                'clicked_count' => $c->clicked_count,
                'converted_count' => $c->converted_count,
                'bounced_count' => $c->bounced_count,
                'unsubscribed_count' => $c->unsubscribed_count,
                'budget' => $c->budget,
                'cost_spent' => $c->cost_spent,
                'created_by_name' => $c->creator?->name,
                'created_at' => $c->created_at?->toIso8601String(),
                'step_count' => $c->steps()->count(),
            ];
        })->toArray();
    }

    /**
     * 创建营销活动
     */
    public function createCampaign(int $tenantId, int $userId, array $data): MarketingCampaign
    {
        $campaign = MarketingCampaign::create([
            'tenant_id' => $tenantId,
            'created_by' => $userId,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? str()->slug($data['name']),
            'description' => $data['description'] ?? null,
            'status' => 'draft',
            'type' => $data['type'] ?? 'email',
            'audience_type' => $data['audience_type'] ?? 'all',
            'segment_id' => $data['segment_id'] ?? null,
            'audience_filter' => $data['audience_filter'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'channel_config' => $data['channel_config'] ?? null,
            'budget' => $data['budget'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // 如果有步骤数据，一起创建
        if (!empty($data['steps']) && is_array($data['steps'])) {
            foreach ($data['steps'] as $order => $step) {
                $campaign->steps()->create([
                    'step_order' => $order + 1,
                    'action_type' => $step['action_type'],
                    'config' => $step['config'] ?? null,
                    'delay_type' => $step['delay_type'] ?? 'immediate',
                    'delay_minutes' => $step['delay_minutes'] ?? null,
                    'conditions' => $step['conditions'] ?? null,
                ]);
            }
        }

        return $campaign;
    }

    /**
     * 更新营销活动
     */
    public function updateCampaign(int $tenantId, int $campaignId, array $data): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);

        if ($campaign->status !== 'draft') {
            // 非草稿状态只能更新部分字段
            $allowed = ['name', 'description', 'scheduled_at', 'timezone', 'budget'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        $campaign->update($data);
        return $campaign;
    }

    /**
     * 启动营销活动 — 计算目标受众
     */
    public function launchCampaign(int $tenantId, int $campaignId): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);

        if ($campaign->status !== 'draft') {
            throw new \RuntimeException(__("app.marketing_campaign.msg_f9f4e012"));
        }

        // 计算目标受众数量
        $targetCount = $this->countTargetAudience($tenantId, $campaign);

        $campaign->update([
            'status' => 'active',
            'started_at' => now(),
            'target_count' => $targetCount,
        ]);

        return $campaign;
    }

    /**
     * 暂停/继续活动
     */
    public function toggleCampaign(int $tenantId, int $campaignId): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);

        if ($campaign->status === 'active') {
            $campaign->update(['status' => 'paused']);
        } elseif ($campaign->status === 'paused') {
            $campaign->update(['status' => 'active']);
        }

        return $campaign;
    }

    /**
     * 完成/取消活动
     */
    public function completeCampaign(int $tenantId, int $campaignId): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);
        $campaign->update(['status' => 'completed', 'ended_at' => now()]);
        return $campaign;
    }

    public function cancelCampaign(int $tenantId, int $campaignId): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);
        $campaign->update(['status' => 'cancelled', 'ended_at' => now()]);
        return $campaign;
    }

    /**
     * 删除活动
     */
    public function deleteCampaign(int $tenantId, int $campaignId): void
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);
        $campaign->delete();
    }

    // ─── 步骤管理 ───

    /**
     * 更新活动的步骤
     */
    public function updateSteps(int $tenantId, int $campaignId, array $steps): MarketingCampaign
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);

        // 删除旧步骤
        $campaign->steps()->delete();

        // 创建新步骤
        foreach ($steps as $order => $step) {
            $campaign->steps()->create([
                'step_order' => $order + 1,
                'action_type' => $step['action_type'],
                'config' => $step['config'] ?? null,
                'delay_type' => $step['delay_type'] ?? 'immediate',
                'delay_minutes' => $step['delay_minutes'] ?? null,
                'conditions' => $step['conditions'] ?? null,
            ]);
        }

        return $campaign->load('steps');
    }

    // ─── 受众统计 ───

    /**
     * 计算目标受众数量
     */
    public function countTargetAudience(int $tenantId, MarketingCampaign $campaign): int
    {
        $query = Customer::where('tenant_id', $tenantId)->where('status', 'active');

        if ($campaign->audience_type === 'segment' && $campaign->segment_id) {
            $segment = CustomerSegment::find($campaign->segment_id);
            if ($segment) {
                $customerIds = $segment->customers()->pluck('customers.id');
                $query->whereIn('id', $customerIds);
            }
        } elseif ($campaign->audience_type === 'custom' && $campaign->audience_filter) {
            $filter = $campaign->audience_filter;
            if (!empty($filter['levels'])) {
                $query->whereIn('level', (array) $filter['levels']);
            }
            if (!empty($filter['types'])) {
                $query->whereIn('type', (array) $filter['types']);
            }
            if (!empty($filter['created_since'])) {
                $query->where('created_at', '>=', $filter['created_since']);
            }
            if (!empty($filter['has_subscription'])) {
                $query->whereHas('subscriptions', function ($q) {
                    $q->whereIn('status', ['active', 'grace']);
                });
            }
        }

        return $query->count();
    }

    // ─── 发送模拟 ───

    /**
     * 模拟发送（构建日志记录，实际发送使用现有渠道服务）
     */
    public function simulateSend(int $tenantId, int $campaignId, array $options = []): array
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);
        $steps = $campaign->steps;

        // 获取目标客户（取前50个模拟）
        $customers = $this->getTargetCustomers($tenantId, $campaign, 50);

        $sentCount = 0;
        foreach ($customers as $customer) {
            foreach ($steps as $step) {
                if (in_array($step->action_type, ['send_email', 'send_sms', 'send_notification'])) {
                    $channel = match ($step->action_type) {
                        'send_email' => 'email',
                        'send_sms' => 'sms',
                        'send_notification' => 'in_app',
                        default => 'email',
                    };

                    if ($channel === 'email' && !$customer->user?->email) {
                        continue;
                    }
                    if ($channel === 'sms' && !$customer->phone) {
                        continue;
                    }
                    $recipient = $channel === 'email' ? $customer->user->email : $customer->phone;

                    $abVariant = null;
                    if ($campaign->is_ab_test) {
                        $abVariant = $this->getAbVariant($customer->id, $campaign->ab_test_split);
                    }

                    MarketingCampaignLog::create([
                        'campaign_id' => $campaign->id,
                        'step_id' => $step->id,
                        'customer_id' => $customer->id,
                        'channel' => $channel,
                        'recipient' => $recipient,
                        'status' => 'sent',
                        'ab_variant' => $abVariant,
                        'sent_at' => now(),
                    ]);

                    $sentCount++;
                }
            }
        }

        // 更新统计
        $campaign->increment('sent_count', $sentCount);

        return ['sent' => $sentCount, 'total_customers' => count($customers)];
    }

    /**
     * 获取目标客户
     */
    protected function getTargetCustomers(int $tenantId, MarketingCampaign $campaign, int $limit = 0): \Illuminate\Support\Collection
    {
        $query = Customer::where('tenant_id', $tenantId)->where('status', 'active')
            ->with('user:id,email,marketing_opt_in,locale');

        if ($campaign->audience_type === 'segment' && $campaign->segment_id) {
            $segment = CustomerSegment::find($campaign->segment_id);
            if ($segment) {
                $customerIds = $segment->customers()->pluck('customers.id');
                $query->whereIn('id', $customerIds);
            }
        } elseif ($campaign->audience_type === 'custom' && $campaign->audience_filter) {
            $filter = $campaign->audience_filter;
            $levelColumn = 'level';
            if (!empty($filter['levels'])) {
                $query->whereIn($levelColumn, (array) $filter['levels']);
            }
            if (!empty($filter['types'])) {
                $query->whereIn('type', (array) $filter['types']);
            }
        }

        // D-24: 只发送给已同意营销的用户
        $query->whereHas('user', function ($q) {
            $q->where('marketing_opt_in', true);
        });

        if ($limit > 0) {
            return $query->limit($limit)->get();
        }

        return $query->get();
    }

    /**
     * D-24: 实际执行营销活动发送
     */
    public function sendCampaign(int $tenantId, int $campaignId, array $options = []): array
    {
        $campaign = MarketingCampaign::where('tenant_id', $tenantId)->findOrFail($campaignId);
        $steps = $campaign->steps;

        $batchSize = $options['batch_size'] ?? 100;
        $customers = $this->getTargetCustomers($tenantId, $campaign, $batchSize);

        $sentCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($customers as $customer) {
            $user = $customer->user;
            if (!$user || !$user->email) {
                continue;
            }

            foreach ($steps as $step) {
                try {
                    $result = $this->sendStep($step, $campaign, $customer, $user);
                    if ($result) {
                        $sentCount++;
                        $results[] = $result;
                    }
                } catch (\Throwable $e) {
                    $failedCount++;
                    Log::warning('营销活动发送失败', [
                        'campaign' => $campaign->id,
                        'step' => $step->id,
                        'customer' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $campaign->increment('sent_count', $sentCount);

        return [
            'sent' => $sentCount,
            'failed' => $failedCount,
            'total_customers' => count($customers),
            'remaining' => max(0, ($campaign->target_count ?? 0) - $campaign->sent_count),
        ];
    }

    /**
     * D-24: 发送给单个用户（用于事件触发场景）
     */
    public function sendToUser(MarketingCampaign $campaign, \App\Models\User $user): bool
    {
        if (!$user->marketing_opt_in || !$user->email) {
            return false;
        }

        $customer = $user->customer;
        if (!$customer) {
            return false;
        }

        $steps = $campaign->steps;
        $sent = false;

        foreach ($steps as $step) {
            try {
                $result = $this->sendStep($step, $campaign, $customer, $user);
                if ($result) {
                    $sent = true;
                    $campaign->increment('sent_count');
                }
            } catch (\Throwable $e) {
                Log::warning('营销活动单用户发送失败', [
                    'campaign' => $campaign->id,
                    'step' => $step->id,
                    'user' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }

    /**
     * 发送单一步骤
     */
    protected function sendStep(MarketingCampaignStep $step, MarketingCampaign $campaign, $customer, \App\Models\User $user): ?array
    {
        $channel = match ($step->action_type) {
            'send_email' => 'email',
            'send_sms' => 'sms',
            'send_notification' => 'in_app',
            default => null,
        };

        if (!$channel) {
            return null;
        }

        $abVariant = null;
        if ($campaign->is_ab_test) {
            $abVariant = $this->getAbVariant($customer->id, $campaign->ab_test_split);
        }

        $config = $step->config ?? [];
        $subject = $config['subject'] ?? ($campaign->name . ' - ' . $step->name);
        $content = $config['content'] ?? '';

        $replacements = [
            '{user_name}' => $user->name,
            '{user_email}' => $user->email,
            '{customer_name}' => $customer->name ?? $user->name,
            '{site_name}' => config('app.name'),
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);

        $recipient = $channel === 'email' ? $user->email : ($customer->phone ?? '');

        // 安全净化：email 内容去 HTML 标签防 XSS（允许用 strip_tags 保留基本格式）
        if ($channel === 'email') {
            $content = strip_tags($content, '<p><br><b><strong><i><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><img><table><tr><td><th><blockquote><span><div>');
        }

        $log = MarketingCampaignLog::create([
            'campaign_id' => $campaign->id,
            'step_id' => $step->id,
            'customer_id' => $customer->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => 'sent',
            'ab_variant' => $abVariant,
            'sent_at' => now(),
        ]);

        // 实际发送邮件
        if ($channel === 'email' && $recipient) {
            try {
                \Illuminate\Support\Facades\Mail::to($recipient)->send(
                    new \App\Mail\GenericNotification($subject, $content, [
                        'campaign' => $campaign->name,
                        'locale' => $user->locale ?? app()->getLocale(),
                    ])
                );
                $log->update(['status' => 'delivered', 'delivered_at' => now()]);
                $campaign->increment('delivered_count');
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed']);
                throw $e;
            }
        }

        // 应用内通知
        if ($channel === 'in_app') {
            try {
                $user->notifications()->create([
                    'type' => 'marketing',
                    'data' => [
                        'title' => $subject,
                        'body' => strip_tags($content),
                        'campaign_id' => $campaign->id,
                    ],
                ]);
                $log->update(['status' => 'delivered', 'delivered_at' => now()]);
                $campaign->increment('delivered_count');
            } catch (\Throwable $e) {
                $log->update(['status' => 'failed']);
                throw $e;
            }
        }

        return [
            'log_id' => $log->id,
            'channel' => $channel,
            'recipient' => $recipient,
            'status' => 'sent',
        ];
    }

    // ─── 仪表盘与分析 ───

    /**
     * 获取营销仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        // 活动概览
        $totalCampaigns = MarketingCampaign::where('tenant_id', $tenantId)->count();
        $activeCampaigns = MarketingCampaign::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $draftCampaigns = MarketingCampaign::where('tenant_id', $tenantId)->where('status', 'draft')->count();
        $completedCampaigns = MarketingCampaign::where('tenant_id', $tenantId)->where('status', 'completed')->count();

        // 渠道分布
        $channelStats = MarketingCampaignLog::whereHas('campaign', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->selectRaw("channel, COUNT(*) as total, SUM(CASE WHEN status IN ('delivered','opened','clicked') THEN 1 ELSE 0 END) as success")
            ->groupBy('channel')
            ->get();

        // 整体效果
        $totalSent = MarketingCampaign::where('tenant_id', $tenantId)->sum('sent_count');
        $totalDelivered = MarketingCampaign::where('tenant_id', $tenantId)->sum('delivered_count');
        $totalOpened = MarketingCampaign::where('tenant_id', $tenantId)->sum('opened_count');
        $totalClicked = MarketingCampaign::where('tenant_id', $tenantId)->sum('clicked_count');
        $totalConverted = MarketingCampaign::where('tenant_id', $tenantId)->sum('converted_count');

        // 按类型分布
        $typeDistribution = MarketingCampaign::where('tenant_id', $tenantId)
            ->selectRaw("type, COUNT(*) as total")
            ->groupBy('type')
            ->get()
            ->pluck('total', 'type')
            ->toArray();

        // 最近活动
        $recentCampaigns = MarketingCampaign::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'name', 'type', 'status', 'sent_count', 'opened_count', 'created_at']);

        return [
            'total_campaigns' => $totalCampaigns,
            'active_campaigns' => $activeCampaigns,
            'draft_campaigns' => $draftCampaigns,
            'completed_campaigns' => $completedCampaigns,
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_opened' => $totalOpened,
            'total_clicked' => $totalClicked,
            'total_converted' => $totalConverted,
            'delivery_rate' => $totalSent > 0 ? round(($totalDelivered / $totalSent) * 100, 1) : 0,
            'open_rate' => $totalDelivered > 0 ? round(($totalOpened / $totalDelivered) * 100, 1) : 0,
            'click_rate' => $totalDelivered > 0 ? round(($totalClicked / $totalDelivered) * 100, 1) : 0,
            'channel_stats' => $channelStats,
            'type_distribution' => $typeDistribution,
            'recent_campaigns' => $recentCampaigns,
        ];
    }

    /**
     * 获取单个活动的详细分析
     */
    public function getCampaignAnalytics(int $tenantId, int $campaignId): array
    {
        $campaign = MarketingCampaign::with('steps')
            ->where('tenant_id', $tenantId)
            ->findOrFail($campaignId);

        // 渠道明细
        $channelBreakdown = MarketingCampaignLog::where('campaign_id', $campaignId)
            ->selectRaw("channel, status, COUNT(*) as total")
            ->groupBy('channel', 'status')
            ->get()
            ->groupBy('channel')
            ->map(function ($items) {
                return $items->pluck('total', 'status')->toArray();
            })
            ->toArray();

        // 时间趋势（每天）
        $dailyTrend = MarketingCampaignLog::where('campaign_id', $campaignId)
            ->whereNotNull('sent_at')
            ->selectRaw("DATE(sent_at) as date, COUNT(*) as sent, SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened")
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // A/B 测试结果
        $abResults = null;
        if ($campaign->is_ab_test) {
            $abResults = MarketingCampaignLog::where('campaign_id', $campaignId)
                ->whereNotNull('ab_variant')
                ->selectRaw("ab_variant, COUNT(*) as sent, SUM(CASE WHEN opened_at IS NOT NULL THEN 1 ELSE 0 END) as opened, SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) as clicked")
                ->groupBy('ab_variant')
                ->get();
        }

        // 步骤执行统计
        $stepStats = $campaign->steps->map(function ($step) use ($campaignId) {
            $logCount = MarketingCampaignLog::where('campaign_id', $campaignId)
                ->where('step_id', $step->id)
                ->count();
            $successCount = MarketingCampaignLog::where('campaign_id', $campaignId)
                ->where('step_id', $step->id)
                ->whereIn('status', ['delivered', 'opened', 'clicked'])
                ->count();
            return [
                'step_id' => $step->id,
                'order' => $step->step_order,
                'action' => $step->action_type,
                'total' => $logCount,
                'success' => $successCount,
            ];
        });

        return [
            'campaign' => $campaign,
            'channel_breakdown' => $channelBreakdown,
            'daily_trend' => $dailyTrend,
            'ab_results' => $abResults,
            'step_stats' => $stepStats,
        ];
    }

    /**
     * 确定性 A/B 分组（基于客户 ID 哈希）
     */
    protected function getAbVariant(int $customerId, int $splitPercent): string
    {
        return (crc32((string) $customerId) % 100) < $splitPercent ? 'A' : 'B';
    }
}
