<?php

namespace App\Services;

use App\Models\IdsAlert;
use App\Models\IdsRule;
use App\Models\SecurityEvent;
use App\Services\SecurityCenterService;
use Illuminate\Support\Facades\Log;

/**
 * 入侵检测与防御 (IDS/IPS) 服务
 *
 * 管理检测规则、处理安全告警、自动响应编排
 */
class IntrusionDetectionService
{
    const CACHE_KEY_IP_HITS = 'ids:ip_hits:';
    const CACHE_TTL_SECONDS = 300; // 5 minutes

    public function __construct(
        protected SecurityCenterService $securityService
    ) {}

    // ─── 规则管理 ───

    /**
     * 获取规则列表
     */
    public function getRules(array $filters = [], int $perPage = 20): array
    {
        $query = IdsRule::query();

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['detection_type'])) {
            $query->where('detection_type', $filters['detection_type']);
        }
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                  ->orWhere('description', 'like', "%{$filters['search']}%");
            });
        }

        $rules = $query->orderBy('priority')->orderBy('name')
            ->paginate($perPage)
            ->toArray();

        return $rules;
    }

    /**
     * 获取单条规则
     */
    public function getRule(int $id): ?IdsRule
    {
        return IdsRule::find($id);
    }

    /**
     * 创建规则
     */
    public function createRule(array $data): IdsRule
    {
        if (empty($data['slug'])) {
            $slug = str($data['name'])->slug('_')->value();
            $data['slug'] = $slug ?: preg_replace('/[^a-zA-Z0-9_]+/', '_', $data['name']);
        }

        $rule = IdsRule::create($data);

        Log::info('[IDS] 规则创建', ['rule_id' => $rule->id, 'slug' => $rule->slug]);

        return $rule;
    }

    /**
     * 更新规则
     */
    public function updateRule(IdsRule $rule, array $data): IdsRule
    {
        // 系统规则只允许调整活跃状态和优先级
        if ($rule->is_system) {
            $allowed = ['is_active', 'priority', 'threshold_count', 'threshold_window_minutes'];
            $data = array_intersect_key($data, array_flip($allowed));
        }

        $rule->update($data);

        Log::info('[IDS] 规则更新', ['rule_id' => $rule->id]);

        return $rule->fresh();
    }

    /**
     * 删除规则
     */
    public function deleteRule(IdsRule $rule): bool
    {
        if ($rule->is_system) {
            return false;
        }

        Log::info('[IDS] 规则删除', ['rule_id' => $rule->id]);
        return $rule->delete();
    }

    /**
     * 播种系统默认规则
     */
    public function seedSystemRules(?int $tenantId = null): int
    {
        $count = 0;
        $rules = IdsRule::getSystemRules();

        foreach ($rules as $ruleData) {
            $exists = IdsRule::where('slug', $ruleData['slug'])
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->exists();

            if (!$exists) {
                $ruleData['tenant_id'] = $tenantId ?? 1;
                $ruleData['is_system'] = true;
                $this->createRule($ruleData);
                $count++;
            }
        }

        return $count;
    }

    /**
     * 获取规则统计
     */
    public function getRuleStats(?int $tenantId = null): array
    {
        $query = IdsRule::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        return [
            'total' => (clone $query)->count(),
            'active' => (clone $query)->where('is_active', true)->count(),
            'system' => (clone $query)->where('is_system', true)->count(),
            'by_type' => (clone $query)->selectRaw('detection_type, COUNT(*) as cnt')
                ->groupBy('detection_type')->pluck('cnt', 'detection_type')->toArray(),
        ];
    }

    // ─── 告警管理 ───

    /**
     * 获取告警列表
     */
    public function getAlerts(array $filters = [], int $perPage = 20): array
    {
        $query = IdsAlert::with('rule:id,name,slug,detection_type,severity');

        if (!empty($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['detection_type'])) {
            $query->where('detection_type', $filters['detection_type']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['source_ip'])) {
            $query->where('source_ip', $filters['source_ip']);
        }

        $alerts = $query->orderByDesc('created_at')
            ->paginate($perPage)
            ->toArray();

        return $alerts;
    }

    /**
     * 获取单个告警详情
     */
    public function getAlert(int $id): ?IdsAlert
    {
        return IdsAlert::with(['rule', 'sopExecution'])->find($id);
    }

    /**
     * 更新告警状态
     */
    public function updateAlertStatus(IdsAlert $alert, string $status, ?string $notes = null): IdsAlert
    {
        if (!array_key_exists($status, IdsAlert::STATUSES)) {
            throw new \InvalidArgumentException(__("app.intrusion_detection.msg_8074adc4"));
        }

        $data = ['status' => $status];

        if ($status === 'mitigated') {
            $data['mitigated_at'] = now();
        }
        if (in_array($status, ['false_positive', 'closed'])) {
            $data['closed_at'] = now();
        }

        $alert->update($data);

        Log::info('[IDS] 告警状态更新', [
            'alert_id' => $alert->id,
            'status' => $status,
        ]);

        return $alert->fresh();
    }

    // ─── 告警统计与概览 ───

    /**
     * 获取IDS/IPS概览(仪表盘)
     */
    public function getDashboard(?int $tenantId = null): array
    {
        $alertQuery = IdsAlert::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        $ruleQuery = IdsRule::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        $openAlerts = (clone $alertQuery)->open()->count();
        $totalAlerts = (clone $alertQuery)->count();
        $criticalAlerts = (clone $alertQuery)->where('severity', 'critical')->open()->count();

        // 今日告警数
        $todayAlerts = (clone $alertQuery)->whereDate('created_at', today())->count();

        // Top 攻击来源
        $topSources = (clone $alertQuery)
            ->selectRaw('source_ip, COUNT(*) as cnt')
            ->whereNotNull('source_ip')
            ->groupBy('source_ip')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get()
            ->toArray();

        // Top 检测类型
        $byType = (clone $alertQuery)
            ->selectRaw('detection_type, COUNT(*) as cnt')
            ->groupBy('detection_type')
            ->orderByDesc('cnt')
            ->get()
            ->toArray();

        // 最近告警
        $recentAlerts = (clone $alertQuery)
            ->with('rule:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->toArray();

        // 规则统计
        $ruleStats = $this->getRuleStats($tenantId);

        return [
            'open_alerts' => $openAlerts,
            'total_alerts' => $totalAlerts,
            'critical_alerts' => $criticalAlerts,
            'today_alerts' => $todayAlerts,
            'top_sources' => $topSources,
            'by_type' => $byType,
            'recent_alerts' => $recentAlerts,
            'rule_stats' => $ruleStats,
        ];
    }

    /**
     * 获取告警趋势数据(用于图表)
     */
    public function getAlertTrends(?int $tenantId = null, int $days = 7): array
    {
        $query = IdsAlert::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw("DATE(created_at) as date, severity, COUNT(*) as cnt")
            ->groupBy('date', 'severity')
            ->orderBy('date')
            ->get();

        $dates = collect();
        for ($i = $days - 1; $i >= 0; $i--) {
            $dates->push(now()->subDays($i)->format('Y-m-d'));
        }

        $trends = [];
        foreach ($dates as $date) {
            $info = 0;
            $warning = 0;
            $critical = 0;
            foreach ($query as $row) {
                if ($row->date === $date) {
                    switch ($row->severity) {
                        case 'critical': $critical = $row->cnt; break;
                        case 'warning': $warning = $row->cnt; break;
                        default: $info = $row->cnt; break;
                    }
                }
            }
            $trends[] = [
                'date' => $date,
                'info' => $info,
                'warning' => $warning,
                'critical' => $critical,
                'total' => $info + $warning + $critical,
            ];
        }

        return $trends;
    }

    // ─── 事件处理 ───

    /**
     * 处理安全事件 - IDS核心检测逻辑
     * 匹配规则 -> 生成告警 -> 触发SOP
     */
    public function processSecurityEvent(SecurityEvent $event): ?IdsAlert
    {
        // 查找匹配的活跃规则
        $rules = IdsRule::active()
            ->where('tenant_id', $event->tenant_id)
            ->where('detection_type', $this->getDetectionTypeForEvent($event))
            ->get();

        if ($rules->isEmpty()) {
            return null;
        }

        $matchedAlert = null;

        foreach ($rules as $rule) {
            $thresholdMet = $this->checkThreshold($rule, $event);
            if (!$thresholdMet) {
                continue;
            }

            // 生成告警
            $alert = IdsAlert::create([
                'tenant_id' => $event->tenant_id,
                'ids_rule_id' => $rule->id,
                'rule_slug' => $rule->slug,
                'rule_name' => $rule->name,
                'detection_type' => $rule->detection_type,
                'severity' => $rule->severity,
                'source_ip' => $event->ip_address,
                'source_user_id' => (string)$event->user_id,
                'evidence' => [
                    'event_id' => $event->id,
                    'event_type' => $event->event_type,
                    'user_agent' => $event->user_agent,
                    'metadata' => $event->metadata,
                ],
                'matched_conditions' => $rule->conditions,
                'status' => 'open',
            ]);

            // 更新规则命中统计
            $rule->increment('hit_count');
            $rule->update(['last_hit_at' => now()]);

            // 触发SOP自动响应
            try {
                $sopExecution = $this->securityService->matchAndExecuteSop($event);
                if ($sopExecution) {
                    $alert->update(['sop_execution_id' => $sopExecution->id]);
                }
            } catch (\Throwable $e) {
                Log::error('[IDS] SOP执行失败', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
            }

            Log::info('[IDS] 检测到入侵', [
                'rule' => $rule->slug,
                'source_ip' => $event->ip_address,
                'severity' => $rule->severity,
            ]);

            if ($matchedAlert === null) {
                $matchedAlert = $alert;
            }
        }

        return $matchedAlert;
    }

    /**
     * 获取事件类型对应的检测类型
     */
    protected function getDetectionTypeForEvent(SecurityEvent $event): string
    {
        return match ($event->event_type) {
            'login_failed' => 'brute_force',
            'geo_anomaly' => 'geo_anomaly',
            'ip_blocked' => 'suspicious_pattern',
            'suspicious_activity' => 'suspicious_pattern',
            default => 'suspicious_pattern',
        };
    }

    /**
     * 检查是否达到规则阈值
     */
    protected function checkThreshold(IdsRule $rule, SecurityEvent $event): bool
    {
        // 如果规则阈值为1，直接触发
        if ($rule->threshold_count <= 1 || $rule->threshold_window_minutes <= 0) {
            return true;
        }

        // 检查时间窗口内的同类事件数量
        $windowStart = now()->subMinutes($rule->threshold_window_minutes);

        $count = SecurityEvent::where('tenant_id', $event->tenant_id)
            ->where('event_type', $event->event_type);

        // 按IP分组
        $conditions = $rule->conditions ?? [];
        $groupBy = $conditions['group_by'] ?? 'ip_address';
        if ($groupBy === 'ip_address' && $event->ip_address) {
            $count->where('ip_address', $event->ip_address);
        }

        $count = $count->where('created_at', '>=', $windowStart)->count();

        return $count >= $rule->threshold_count;
    }

    /**
     * 构造IP黑名单理由
     */
    public function buildIpBlockReason(IdsAlert $alert): string
    {
        return sprintf(
            '[IDS] %s - %s (规则: %s, 来源IP: %s)',
            $alert->severity === 'critical' ? '严重' : '警告',
            $alert->rule_name ?? $alert->detection_type,
            $alert->rule_slug,
            $alert->source_ip ?? 'unknown'
        );
    }

    /**
     * 清空所有告警
     */
    public function clearAlerts(?int $tenantId = null, ?string $olderThan = null): int
    {
        $query = IdsAlert::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        if ($olderThan) {
            $query->where('created_at', '<', now()->sub($olderThan));
        }

        return $query->delete();
    }
}
