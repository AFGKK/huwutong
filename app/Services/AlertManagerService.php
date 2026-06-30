<?php

namespace App\Services;

use App\Models\AlertAggregationLog;
use App\Models\AlertEvent;
use App\Models\AlertFatigueSetting;
use App\Models\AlertRule;
use App\Models\AlertSilenceRule;
use App\Models\AlertNotificationLog;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * 告警聚合与疲劳管理服务 (M2-119)
 *
 * 构建于现有 AlertEngineService 之上，提供：
 * - 告警聚合去重（按规则/来源/内容智能合并）
 * - 告警疲劳检测（重复率分析/衰减曲线/自动降级）
 * - 静默/抑制规则管理（定时静默/条件静默）
 * - 告警摘要聚合
 * - 噪音评分与自动降级
 */
class AlertManagerService
{
    /**
     * 看板总览
     */
    public function dashboard(): array
    {
        $now = now();

        return [
            'total_rules' => AlertRule::count(),
            'active_rules' => AlertRule::where('is_active', true)->count(),
            'firing_events' => AlertEvent::where('status', 'firing')->count(),
            'acknowledged' => AlertEvent::where('status', 'acknowledged')->count(),
            'resolved_today' => AlertEvent::where('status', 'resolved')
                ->where('updated_at', '>=', $now->startOfDay())->count(),
            'active_silences' => AlertSilenceRule::where('is_active', true)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)->count(),
            'aggregated_events' => AlertAggregationLog::where('created_at', '>=', $now->subDay())
                ->distinct('parent_event_id')->count('parent_event_id'),
            'fatigue_settings' => AlertFatigueSetting::where('is_active', true)->count(),
            'severity_distribution' => AlertEvent::selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')->pluck('count', 'severity'),
        ];
    }

    // ═══════════════════════════════════════
    //  告警聚合引擎
    // ═══════════════════════════════════════

    /**
     * 执行告警聚合
     * 将相似告警事件合并到父事件下，减少通知数量
     */
    public function aggregateEvents(): array
    {
        if (!config('alert-manager.aggregation.enabled', true)) {
            return ['aggregated' => 0, 'message' => '聚合已禁用'];
        }

        $window = config('alert-manager.aggregation.window_minutes', 60);
        $since = now()->subMinutes($window);
        $aggregated = 0;

        // 获取未聚合且状态为 firing 的事件
        $events = AlertEvent::where('status', 'firing')
            ->where('created_at', '>=', $since)
            ->whereDoesntHave('parentAggregations')
            ->orderBy('created_at')
            ->get();

        // 按规则分组
        $groups = $events->groupBy(function ($e) {
            $rule = $e->rule;
            $groupBy = $rule ? ($rule->aggregation_group_by ?? 'rule_id,severity') : 'rule_id';
            $parts = [];
            foreach (explode(',', $groupBy) as $field) {
                $field = trim($field);
                $parts[] = match ($field) {
                    'rule_id' => $e->alert_rule_id ?? 'null',
                    'severity' => $e->severity ?? 'unknown',
                    'source_type' => $e->source_type ?? 'unknown',
                    default => $e->$field ?? 'unknown',
                };
            }
            return implode(':', $parts);
        });

        foreach ($groups as $groupKey => $groupEvents) {
            if ($groupEvents->count() < 2) continue;

            // 第一个作为父事件
            $parent = $groupEvents->shift();

            foreach ($groupEvents as $child) {
                AlertAggregationLog::firstOrCreate([
                    'parent_event_id' => $parent->id,
                    'child_event_id' => $child->id,
                ], [
                    'group_key' => $groupKey,
                    'reason' => 'similar_rule',
                ]);
                $aggregated++;
            }
        }

        return ['aggregated' => $aggregated, 'groups' => $groups->count()];
    }

    /**
     * 获取聚合组列表
     */
    public function aggregationGroups(int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $groups = AlertAggregationLog::where('created_at', '>=', $since)
            ->selectRaw('group_key, COUNT(DISTINCT parent_event_id) as parent_count, COUNT(*) as total_children')
            ->groupBy('group_key')
            ->orderByDesc('total_children')
            ->limit(30)
            ->get();

        $result = [];
        foreach ($groups as $g) {
            $sample = AlertAggregationLog::where('group_key', $g->group_key)
                ->with(['parentEvent:id,alert_rule_id,severity,message,status,created_at',
                        'parentEvent.rule:id,name,metric_type'])
                ->first();

            $result[] = [
                'group_key' => $g->group_key,
                'parent_count' => $g->parent_count,
                'total_children' => $g->total_children,
                'sample_parent' => $sample ? [
                    'id' => $sample->parentEvent->id,
                    'rule_name' => $sample->parentEvent->rule->name ?? '—',
                    'metric_type' => $sample->parentEvent->rule->metric_type ?? '—',
                    'severity' => $sample->parentEvent->severity,
                    'message' => $sample->parentEvent->message,
                    'status' => $sample->parentEvent->status,
                    'created_at' => $sample->parentEvent->created_at->toDateTimeString(),
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * 查看聚合组的子事件
     */
    public function aggregationDetail(string $groupKey, int $limit = 50): array
    {
        return AlertAggregationLog::where('group_key', $groupKey)
            ->with(['childEvent:id,alert_rule_id,severity,message,status,source_type,created_at',
                    'childEvent.rule:id,name'])
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->childEvent->id,
                    'rule_name' => $log->childEvent->rule->name ?? '—',
                    'severity' => $log->childEvent->severity,
                    'message' => $log->childEvent->message,
                    'status' => $log->childEvent->status,
                    'source_type' => $log->childEvent->source_type,
                    'created_at' => $log->childEvent->created_at->toDateTimeString(),
                ];
            })
            ->toArray();
    }

    // ═══════════════════════════════════════
    //  静默规则
    // ═══════════════════════════════════════

    public function listSilenceRules(Request $request): array
    {
        $query = AlertSilenceRule::query();
        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }
        $total = $query->count();
        $items = $query->orderByDesc('id')->paginate(20)->items();
        return ['items' => $items, 'total' => $total];
    }

    public function storeSilenceRule(array $data): AlertSilenceRule
    {
        return AlertSilenceRule::create($data);
    }

    public function updateSilenceRule(int $id, array $data): AlertSilenceRule
    {
        $rule = AlertSilenceRule::findOrFail($id);
        $rule->update($data);
        return $rule->fresh();
    }

    public function deleteSilenceRule(int $id): void
    {
        AlertSilenceRule::findOrFail($id)->delete();
    }

    public function toggleSilenceRule(int $id): AlertSilenceRule
    {
        $rule = AlertSilenceRule::findOrFail($id);
        $rule->update(['is_active' => !$rule->is_active]);
        return $rule->fresh();
    }

    /**
     * 检查告警是否被静默
     */
    public function isSilenced(array $context): bool
    {
        $silences = AlertSilenceRule::where('is_active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->get();

        foreach ($silences as $silence) {
            if ($silence->matchesRule($context)) return true;
        }
        return false;
    }

    // ═══════════════════════════════════════
    //  疲劳检测
    // ═══════════════════════════════════════

    /**
     * 检查告警疲劳状态
     */
    public function checkFatigue(int $ruleId): array
    {
        $rule = AlertRule::findOrFail($ruleId);
        $threshold = $rule->fatigue_threshold > 0
            ? $rule->fatigue_threshold
            : config('alert-manager.fatigue.repetition_threshold', 5);

        // 计算过去 N 小时内告警事件的频次
        $window = config('alert-manager.aggregation.window_minutes', 60);
        $since = now()->subMinutes($window);

        $eventCount = AlertEvent::where('alert_rule_id', $ruleId)
            ->where('created_at', '>=', $since)
            ->count();

        $noiseScore = $this->calculateNoiseScore($ruleId, $eventCount);
        $isFatigued = $noiseScore >= config('alert-manager.fatigue.noise_score_threshold', 50);

        return [
            'rule_id' => $ruleId,
            'rule_name' => $rule->name,
            'event_count_window' => $eventCount,
            'threshold' => $threshold,
            'noise_score' => $noiseScore,
            'is_fatigued' => $isFatigued,
            'auto_downgrade' => $isFatigued && config('alert-manager.fatigue.auto_downgrade', true),
        ];
    }

    /**
     * 计算噪音评分
     */
    protected function calculateNoiseScore(int $ruleId, int $eventCount): float
    {
        $threshold = config('alert-manager.fatigue.repetition_threshold', 5);
        $decay = config('alert-manager.fatigue.decay_factor', 0.5);

        if ($eventCount <= $threshold) return 0;

        $excess = $eventCount - $threshold;
        return round(min(100, $excess * (1 - $decay) * 20), 2);
    }

    /**
     * 自动告警降级
     */
    public function autoDowngrade(): array
    {
        if (!config('alert-manager.fatigue.auto_downgrade', true)) {
            return ['downgraded' => 0];
        }

        $downgraded = 0;
        $rules = AlertRule::where('is_active', true)->where('fatigue_threshold', '>', 0)->get();

        foreach ($rules as $rule) {
            $fatigue = $this->checkFatigue($rule->id);
            if ($fatigue['is_fatigued']) {
                // 将 firing 事件标记为噪音
                $affected = AlertEvent::where('alert_rule_id', $rule->id)
                    ->where('status', 'firing')
                    ->where('created_at', '>=', now()->subHours(1))
                    ->update(['severity' => 'info']); // 降级为 info
                $downgraded += $affected;
            }
        }

        return ['downgraded' => $downgraded];
    }

    // ═══════════════════════════════════════
    //  疲劳设置管理
    // ═══════════════════════════════════════

    public function listFatigueSettings(): array
    {
        return AlertFatigueSetting::all()->toArray();
    }

    public function storeFatigueSetting(array $data): AlertFatigueSetting
    {
        return AlertFatigueSetting::create($data);
    }

    public function updateFatigueSetting(int $id, array $data): AlertFatigueSetting
    {
        $setting = AlertFatigueSetting::findOrFail($id);
        $setting->update($data);
        return $setting->fresh();
    }

    public function deleteFatigueSetting(int $id): void
    {
        AlertFatigueSetting::findOrFail($id)->delete();
    }

    // ═══════════════════════════════════════
    //  通知摘要
    // ═══════════════════════════════════════

    /**
     * 生成告警摘要
     */
    public function generateDigest(): array
    {
        if (!config('alert-manager.digest.enabled', true)) {
            return ['digest' => null, 'message' => '摘要已禁用'];
        }

        $since = now()->subMinutes(config('alert-manager.digest.interval_minutes', 30));
        $maxItems = config('alert-manager.digest.max_digest_items', 50);

        $events = AlertEvent::where('created_at', '>=', $since)
            ->with('rule:id,name,severity')
            ->orderByDesc('severity')
            ->limit($maxItems)
            ->get();

        $summary = [
            'total' => $events->count(),
            'critical' => $events->where('severity', 'critical')->count(),
            'warning' => $events->where('severity', 'warning')->count(),
            'info' => $events->where('severity', 'info')->count(),
            'by_rule' => $events->groupBy(fn($e) => $e->rule->name ?? 'unknown')
                ->map(fn($g) => $g->count())
                ->toArray(),
            'generated_at' => now()->toDateTimeString(),
            'events' => $events->map(fn($e) => [
                'id' => $e->id,
                'rule' => $e->rule->name ?? '—',
                'severity' => $e->severity,
                'message' => $e->message,
                'status' => $e->status,
                'time' => $e->created_at->toDateTimeString(),
            ]),
        ];

        return $summary;
    }

    // ═══════════════════════════════════════
    //  噪音分析
    // ═══════════════════════════════════════

    /**
     * 噪音分析报告
     */
    public function noiseAnalysis(int $days = 7): array
    {
        $since = now()->subDays($days);

        $rules = AlertRule::withCount(['events' => function ($q) use ($since) {
            $q->where('created_at', '>=', $since);
        }])->where('is_active', true)->get();

        $analysis = [];
        foreach ($rules as $rule) {
            $noiseScore = $this->calculateNoiseScore($rule->id, $rule->events_count);
            $analysis[] = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'metric_type' => $rule->metric_type,
                'total_events' => $rule->events_count,
                'noise_score' => $noiseScore,
                'is_noisy' => $noiseScore >= config('alert-manager.fatigue.noise_score_threshold', 50),
                'severity' => $rule->severity,
                'suggested_action' => $noiseScore >= 50 ? '考虑调整阈值或启用静默' : '正常',
            ];
        }

        usort($analysis, fn($a, $b) => $b['noise_score'] <=> $a['noise_score']);

        return [
            'period_days' => $days,
            'total_noisy_rules' => count(array_filter($analysis, fn($a) => $a['is_noisy'])),
            'rules' => array_slice($analysis, 0, 30),
        ];
    }

    // ═══════════════════════════════════════
    //  通知日志统计
    // ═══════════════════════════════════════

    public function notificationStats(int $days = 7): array
    {
        $since = now()->subDays($days);

        $daily = AlertNotificationLog::where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, channel_type, COUNT(*) as count')
            ->groupBy('date', 'channel_type')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        $totalByChannel = AlertNotificationLog::where('created_at', '>=', $since)
            ->selectRaw('channel_type, COUNT(*) as count')
            ->groupBy('channel_type')
            ->pluck('count', 'channel_type');

        return [
            'total' => AlertNotificationLog::where('created_at', '>=', $since)->count(),
            'by_channel' => $totalByChannel,
            'daily' => $daily->map(fn($items) => [
                'total' => $items->sum('count'),
                'channels' => $items->pluck('count', 'channel_type'),
            ]),
        ];
    }
}
