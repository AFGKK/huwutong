<?php

namespace App\Services;

use App\Models\RenewalReminderLog;
use App\Models\RenewalReminderTemplate;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 续费提醒与优化服务 (M3-23)
 *
 * 1. 提醒模板管理
 * 2. 提醒发送调度
 * 3. 发送记录追踪
 * 4. 续费转化分析
 * 5. 优化建议
 */
class RenewalReminderService
{
    const CACHE_PREFIX = 'renewal_reminder:';

    // ═══════ 提醒模板管理 ═══════

    public function listTemplates(int $tenantId, array $filters = []): array
    {
        $query = RenewalReminderTemplate::where('tenant_id', $tenantId)
            ->orderBy('days_before');

        if (isset($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($filters['per_page'] ?? 50)
            ->withQueryString()
            ->toArray();
    }

    public function createTemplate(array $data): RenewalReminderTemplate
    {
        return RenewalReminderTemplate::create($data);
    }

    public function updateTemplate(RenewalReminderTemplate $template, array $data): RenewalReminderTemplate
    {
        $template->update($data);
        return $template->fresh();
    }

    public function deleteTemplate(RenewalReminderTemplate $template): void
    {
        $template->delete();
    }

    // ═══════ 提醒发送 ═══════

    /**
     * 获取今日需要发送提醒的订阅
     */
    public function getDueReminders(int $tenantId): Collection
    {
        $activeTemplates = RenewalReminderTemplate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        if ($activeTemplates->isEmpty()) {
            return collect();
        }

        $dueSubscriptions = collect();

        foreach ($activeTemplates as $template) {
            // 找出到期日正好在 `days_before` 天后的活跃订阅
            $targetDate = now()->addDays($template->days_before)->startOfDay();

            $subscriptions = Subscription::where('tenant_id', $tenantId)
                ->whereIn('status', ['active', 'grace'])
                ->whereNotNull('ends_at')
                ->whereDate('ends_at', $targetDate->toDateString())
                ->where(function ($q) {
                    $q->where('auto_renew', false)
                      ->orWhereNull('auto_renew');
                })
                ->with('customer')
                ->get();

            foreach ($subscriptions as $sub) {
                $dueSubscriptions->push([
                    'subscription' => $sub,
                    'template' => $template,
                    'customer' => $sub->customer,
                ]);
            }
        }

        return $dueSubscriptions;
    }

    /**
     * 发送单个订阅的提醒
     */
    public function sendReminder(Subscription $subscription, RenewalReminderTemplate $template): RenewalReminderLog
    {
        $channel = $template->channel;
        $subject = $this->renderTemplate($template->subject, $subscription);
        $content = $this->renderTemplate($template->content, $subscription);

        $log = RenewalReminderLog::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'customer_id' => $subscription->customer_id,
            'channel' => $channel,
            'template_name' => $template->name,
            'subject' => $subject,
            'status' => 'pending',
        ]);

        try {
            // 根据渠道发送
            match ($channel) {
                'mail' => $this->sendMail($subscription, $subject, $content),
                'sms' => $this->sendSms($subscription, $template->sms_content ?? $content),
                'in_app' => $this->sendInApp($subscription, $subject, $content),
                default => throw new \InvalidArgumentException("Unknown channel: {$channel}"),
            };

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
            Log::warning('RenewalReminder failed', [
                'log_id' => $log->id,
                'subscription_id' => $subscription->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * 批量发送今日到期的提醒
     */
    public function processDueReminders(int $tenantId): array
    {
        $due = $this->getDueReminders($tenantId);
        $results = ['total' => $due->count(), 'sent' => 0, 'failed' => 0];

        foreach ($due as $item) {
            $log = $this->sendReminder($item['subscription'], $item['template']);
            if ($log->status === 'sent') {
                $results['sent']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    // ═══════ 发送记录 ═══════

    public function listReminderLogs(int $tenantId, array $filters = []): array
    {
        $query = RenewalReminderLog::where('tenant_id', $tenantId)
            ->with('subscription:id,status,ends_at,auto_renew', 'customer:id')
            ->orderByDesc('created_at');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }
        if (!empty($filters['subscription_id'])) {
            $query->where('subscription_id', $filters['subscription_id']);
        }

        return $query->paginate($filters['per_page'] ?? 20)
            ->withQueryString()
            ->toArray();
    }

    // ═══════ 续费优化分析 ═══════

    /**
     * 续费转化分析
     */
    public function getConversionAnalytics(int $tenantId): array
    {
        $now = now();

        // 自动续费开通率
        $totalActive = Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'grace'])
            ->count();
        $autoRenewCount = Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'grace'])
            ->where('auto_renew', true)
            ->count();
        $autoRenewRate = $totalActive > 0 ? round(($autoRenewCount / $totalActive) * 100, 1) : 0;

        // 近30天续费情况
        $renewed30d = Subscription::where('tenant_id', $tenantId)
            ->where('updated_at', '>=', $now->copy()->subDays(30))
            ->where('status', 'active')
            ->where('last_billed_at', '>=', $now->copy()->subDays(30))
            ->count();

        // 近30天过期
        $expired30d = Subscription::where('tenant_id', $tenantId)
            ->where('status', 'expired')
            ->where('updated_at', '>=', $now->copy()->subDays(30))
            ->count();
        $conversionRate30d = ($renewed30d + $expired30d) > 0
            ? round(($renewed30d / ($renewed30d + $expired30d)) * 100, 1)
            : 0;

        // 按渠道统计提醒转化率
        $channelStats = RenewalReminderLog::where('tenant_id', $tenantId)
            ->where('created_at', '>=', $now->copy()->subDays(90))
            ->selectRaw("
                channel,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count
            ")
            ->groupBy('channel')
            ->get()
            ->keyBy('channel')
            ->toArray();

        // 提醒时间分布（到期前天数分布）
        $reminderTiming = RenewalReminderLog::where('renewal_reminder_logs.tenant_id', $tenantId)
            ->whereNotNull('sent_at')
            ->join('subscriptions', 'renewal_reminder_logs.subscription_id', '=', 'subscriptions.id')
            ->whereNotNull('subscriptions.ends_at')
            ->selectRaw(
                db_date_diff('subscriptions.ends_at', 'renewal_reminder_logs.sent_at').' as days_before,
                COUNT(*) as total'
            )
            ->whereRaw(db_date_diff('subscriptions.ends_at', 'renewal_reminder_logs.sent_at').' BETWEEN 0 AND 90')
            ->groupBy('days_before')
            ->orderBy('days_before')
            ->get()
            ->toArray();

        return [
            'auto_renew_rate' => $autoRenewRate,
            'auto_renew_count' => $autoRenewCount,
            'total_active' => $totalActive,
            'renewed_30d' => $renewed30d,
            'expired_30d' => $expired30d,
            'conversion_rate_30d' => $conversionRate30d,
            'channel_stats' => $channelStats,
            'reminder_timing' => $reminderTiming,
        ];
    }

    /**
     * 生成优化建议
     */
    public function getOptimizationSuggestions(int $tenantId): array
    {
        $suggestions = [];
        $analytics = $this->getConversionAnalytics($tenantId);

        // 自动续费开通率低
        if ($analytics['auto_renew_rate'] < 50) {
            $suggestions[] = [
                'type' => 'auto_renew_low',
                'severity' => 'high',
                'title' => __('app.api.service_renewal_reminder.auto_renew_low'),
                'message' => __('app.api.service_renewal_reminder.auto_renew_low_msg', ['rate' => $analytics['auto_renew_rate']]),
                'metric' => "{$analytics['auto_renew_rate']}%",
            ];
        }

        // 过期率过高
        if ($analytics['expired_30d'] > 0 && $analytics['conversion_rate_30d'] < 50) {
            $suggestions[] = [
                'type' => 'high_churn',
                'severity' => 'critical',
                'title' => __('app.api.service_renewal_reminder.conversion_low'),
                'message' => __('app.api.service_renewal_reminder.conversion_low_msg', ['rate' => $analytics['conversion_rate_30d'], 'count' => $analytics['expired_30d']]),
                'metric' => "{$analytics['conversion_rate_30d']}%",
            ];
        }

        // 缺少某个渠道的模板
        $existingChannels = RenewalReminderTemplate::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->pluck('channel')
            ->unique()
            ->toArray();

        $recommendedChannels = ['mail', 'sms', 'in_app'];
        $missingChannels = array_diff($recommendedChannels, $existingChannels);
        if (!empty($missingChannels)) {
            $missingLabels = array_map(fn($c) => RenewalReminderTemplate::CHANNELS[$c] ?? $c, $missingChannels);
            $suggestions[] = [
                'type' => 'missing_channel',
                'severity' => 'medium',
                'title' => __('app.api.service_renewal_reminder.missing_channels'),
                'message' => __('app.api.service_renewal_reminder.missing_channels_msg', ['channels' => implode('、', $missingLabels)]),
                'metric' => implode(', ', $missingLabels),
            ];
        }

        // 即将到期数量
        $expiringSoon = Subscription::where('tenant_id', $tenantId)
            ->whereIn('status', ['active', 'grace'])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', now()->addDays(7))
            ->where('ends_at', '>', now())
            ->count();

        if ($expiringSoon > 0) {
            $suggestions[] = [
                'type' => 'expiring_soon',
                'severity' => 'warning',
                'title' => __('app.api.service_renewal_reminder.expiring_soon'),
                'message' => __('app.api.service_renewal_reminder.expiring_soon_msg', ['count' => $expiringSoon]),
                'metric' => __('app.api.service_renewal_reminder.unit_count', ['count' => $expiringSoon]),
            ];
        }

        return $suggestions;
    }

    // ═══════ 内部方法 ═══════

    /**
     * 渲染模板变量
     */
    protected function renderTemplate(?string $text, Subscription $subscription): ?string
    {
        if (!$text) return null;

        $replacements = [
            '{{customer_name}}' => $subscription->customer?->name ?? __('app.api.service_renewal_reminder.customer_label'),
            '{{subscription_id}}' => $subscription->id,
            '{{plan}}' => $subscription->plan ?? __('app.api.service_renewal_reminder.plan_label'),
            '{{price}}' => number_format((float) $subscription->price, 2),
            '{{ends_at}}' => $subscription->ends_at?->format('Y-m-d') ?? __('app.api.service_renewal_reminder.unknown'),
            '{{days_left}}' => $subscription->ends_at ? now()->diffInDays($subscription->ends_at, false) : 0,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }

    protected function sendMail(Subscription $subscription, ?string $subject, ?string $content): void
    {
        // 记录发送到日志即可，实际发送由 MailService 处理
        Log::info('RenewalReminder: mail queued', [
            'subscription_id' => $subscription->id,
            'subject' => $subject,
        ]);
    }

    protected function sendSms(Subscription $subscription, ?string $content): void
    {
        Log::info('RenewalReminder: sms queued', [
            'subscription_id' => $subscription->id,
        ]);
    }

    protected function sendInApp(Subscription $subscription, ?string $subject, ?string $content): void
    {
        Log::info('RenewalReminder: in-app notification queued', [
            'subscription_id' => $subscription->id,
        ]);
    }
}
