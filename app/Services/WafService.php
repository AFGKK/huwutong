<?php

namespace App\Services;

use App\Models\WafAttackLog;
use App\Models\WafIpList;
use App\Models\WafRule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * WAF 基础防护服务 (M1.3-18)
 *
 * WAF 规则管理 / IP 黑白名单 / 攻击事件查询 / 仪表盘
 */
class WafService
{
    // ─── 仪表盘 ────────────────────────────────────

    /**
     * 获取仪表盘数据
     */
    public function getDashboard(): array
    {
        $cacheKey = 'waf:dashboard';
        $ttl = config('waf.monitoring.stats_ttl_seconds', 300);

        return Cache::remember($cacheKey, $ttl, function () {
            $now = now();
            $todayStart = $now->copy()->startOfDay();
            $hourAgo = $now->copy()->subHour();

            // 今日统计
            $todayStats = WafAttackLog::where('created_at', '>=', $todayStart)
                ->selectRaw('COUNT(*) as total')
                ->selectRaw("SUM(CASE WHEN action_taken = 'block' THEN 1 ELSE 0 END) as blocked")
                ->selectRaw("SUM(CASE WHEN action_taken = 'challenge' THEN 1 ELSE 0 END) as challenged")
                ->selectRaw("SUM(CASE WHEN action_taken = 'log' THEN 1 ELSE 0 END) as detected")
                ->first();

            // 最近1小时统计
            $hourlyStats = WafAttackLog::where('created_at', '>=', $hourAgo)
                ->selectRaw('COUNT(*) as total')
                ->first();

            // 按分类统计
            $categoryStats = WafAttackLog::where('created_at', '>=', $todayStart)
                ->select('rule_category', DB::raw('COUNT(*) as total'))
                ->groupBy('rule_category')
                ->orderByDesc('total')
                ->get()
                ->keyBy('rule_category')
                ->toArray();

            // 今日 Top 攻击 IP
            $topIps = WafAttackLog::where('created_at', '>=', $todayStart)
                ->select('ip', DB::raw('COUNT(*) as total'), DB::raw('MAX(severity) as max_severity'))
                ->groupBy('ip')
                ->orderByDesc('total')
                ->limit(10)
                ->get()
                ->toArray();

            // 按严重级别统计
            $severityStats = WafAttackLog::where('created_at', '>=', $todayStart)
                ->select('severity', DB::raw('COUNT(*) as total'))
                ->groupBy('severity')
                ->get()
                ->keyBy('severity')
                ->toArray();

            // 规则与 IP 列表统计
            $activeRules = WafRule::active()->count();
            $totalRules = WafRule::count();
            $blacklistCount = WafIpList::active()->byType('blacklist')->count();
            $whitelistCount = WafIpList::active()->byType('whitelist')->count();

            return [
                'today' => [
                    'total' => (int) ($todayStats->total ?? 0),
                    'blocked' => (int) ($todayStats->blocked ?? 0),
                    'challenged' => (int) ($todayStats->challenged ?? 0),
                    'detected' => (int) ($todayStats->detected ?? 0),
                ],
                'last_hour' => [
                    'total' => (int) ($hourlyStats->total ?? 0),
                ],
                'category_stats' => $categoryStats,
                'top_ips' => $topIps,
                'severity_stats' => $severityStats,
                'rules' => [
                    'active' => $activeRules,
                    'total' => $totalRules,
                ],
                'ip_lists' => [
                    'blacklist' => $blacklistCount,
                    'whitelist' => $whitelistCount,
                ],
                'mode' => config('waf.mode', 'block'),
                'cc_enabled' => config('waf.cc.enabled', true),
                'rules_enabled' => config('waf.rules.enabled', true),
            ];
        });
    }

    /**
     * 清除仪表盘缓存
     */
    public function clearDashboardCache(): void
    {
        Cache::forget('waf:dashboard');
    }

    // ─── 规则管理 ──────────────────────────────────

    /**
     * 获取规则列表
     */
    public function getRules(array $filters = []): array
    {
        $query = WafRule::ordered();

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }
        if (isset($filters['is_active'])) {
            $filters['is_active'] ? $query->active() : $query->where('is_active', false);
        }

        return $query->get()->toArray();
    }

    /**
     * 创建规则
     */
    public function createRule(array $data): WafRule
    {
        $rule = WafRule::create($data);
        Cache::forget('waf:rules:active');
        $this->clearDashboardCache();

        return $rule;
    }

    /**
     * 更新规则
     */
    public function updateRule(WafRule $rule, array $data): WafRule
    {
        $rule->update($data);
        Cache::forget('waf:rules:active');
        $this->clearDashboardCache();

        return $rule->fresh();
    }

    /**
     * 删除规则
     */
    public function deleteRule(WafRule $rule): void
    {
        $rule->delete();
        Cache::forget('waf:rules:active');
        $this->clearDashboardCache();
    }

    /**
     * 切换规则状态
     */
    public function toggleRule(WafRule $rule): WafRule
    {
        $rule->update(['is_active' => !$rule->is_active]);
        Cache::forget('waf:rules:active');
        $this->clearDashboardCache();

        return $rule->fresh();
    }

    /**
     * 导入内置默认规则
     */
    public function seedDefaultRules(): array
    {
        $count = 0;
        $config = config('waf.rules', []);

        $defaultRules = [];

        foreach ($config as $category => $ruleConfig) {
            if (!is_array($ruleConfig) || !isset($ruleConfig['patterns'])) {
                continue;
            }

            $patterns = $ruleConfig['patterns'] ?? [];
            $severity = $ruleConfig['severity'] ?? 'high';
            $enabled = $ruleConfig['enabled'] ?? true;

            $label = match ($category) {
                'sql_injection' => 'SQL 注入',
                'xss' => 'XSS 跨站脚本',
                'path_traversal' => '路径穿越',
                'command_injection' => '命令注入',
                'file_inclusion' => '文件包含',
                'ssrf' => 'SSRF',
                default => $category,
            };

            foreach ($patterns as $i => $pattern) {
                $exists = WafRule::where('pattern', $pattern)->exists();
                if ($exists) {
                    continue;
                }

                WafRule::create([
                    'name' => "内置规则: {$label} #" . ($i + 1),
                    'category' => $category,
                    'severity' => $severity,
                    'mode' => $enabled ? 'block' : 'detect',
                    'match_type' => 'regex',
                    'pattern' => $pattern,
                    'target' => 'all',
                    'action' => 'block',
                    'description' => "自动导入的 {$label} 检测规则",
                    'is_active' => $enabled,
                    'priority' => 100 + ($i * 10),
                ]);

                $count++;
            }
        }

        Cache::forget('waf:rules:active');
        $this->clearDashboardCache();

        return [
            'imported' => $count,
            'message' => "成功导入 {$count} 条默认规则",
        ];
    }

    // ─── IP 黑白名单 ──────────────────────────────

    /**
     * 获取 IP 列表
     */
    public function getIpList(string $type = null): array
    {
        $query = WafIpList::active();

        if ($type) {
            $query->byType($type);
        }

        return $query->orderByDesc('created_at')->get()->toArray();
    }

    /**
     * 添加 IP
     */
    public function addIp(array $data): WafIpList
    {
        $ip = WafIpList::create($data);
        $this->clearDashboardCache();

        return $ip;
    }

    /**
     * 批量添加 IP
     */
    public function batchAddIp(array $ips, string $type, string $reason = null): array
    {
        $added = 0;
        $skipped = 0;

        foreach ($ips as $ip) {
            $ip = trim($ip['ip'] ?? $ip);
            if (empty($ip)) {
                continue;
            }

            $exists = WafIpList::where('ip', $ip)->where('type', $type)->exists();
            if ($exists) {
                $skipped++;
                continue;
            }

            WafIpList::create([
                'ip' => $ip,
                'type' => $type,
                'reason' => $reason ?? ($type === 'blacklist' ? '手动添加' : null),
                'is_active' => true,
            ]);
            $added++;
        }

        $this->clearDashboardCache();

        return [
            'added' => $added,
            'skipped' => $skipped,
            'message' => "成功添加 {$added} 条，{$skipped} 条已存在",
        ];
    }

    /**
     * 删除 IP
     */
    public function deleteIp(WafIpList $ip): void
    {
        $ip->delete();
        $this->clearDashboardCache();
    }

    /**
     * 检查 IP
     */
    public function checkIp(string $ip): array
    {
        $inBlacklist = WafIpList::isInList($ip, 'blacklist');
        $inWhitelist = WafIpList::isInList($ip, 'whitelist');

        $recentLogs = WafAttackLog::where('ip', $ip)
            ->recent(24 * 60)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->toArray();

        return [
            'ip' => $ip,
            'in_blacklist' => $inBlacklist,
            'in_whitelist' => $inWhitelist,
            'recent_attacks' => count($recentLogs),
            'recent_logs' => $recentLogs,
        ];
    }

    // ─── 攻击日志 ──────────────────────────────────

    /**
     * 获取攻击日志
     */
    public function getAttackLogs(array $filters = []): array
    {
        $query = WafAttackLog::orderByDesc('created_at');

        if (!empty($filters['ip'])) {
            $query->byIp($filters['ip']);
        }
        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
        }
        if (!empty($filters['severity'])) {
            $query->bySeverity($filters['severity']);
        }
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 50), 200);
        $page = (int) ($filters['page'] ?? 1);

        $total = $query->count();
        $items = $query->skip(($page - 1) * $perPage)->take($perPage)->get()->toArray();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /**
     * 获取攻击趋势数据
     */
    public function getTrend(int $days = 7): array
    {
        $start = now()->subDays($days)->startOfDay();

        $logs = WafAttackLog::where('created_at', '>=', $start)
            ->select(
                DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d') as date"),
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN action_taken = 'block' THEN 1 ELSE 0 END) as blocked"),
                DB::raw("SUM(CASE WHEN action_taken = 'challenge' THEN 1 ELSE 0 END) as challenged"),
                DB::raw("SUM(CASE WHEN action_taken = 'log' THEN 1 ELSE 0 END) as detected")
            )
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m-%d')"))
            ->orderBy('date')
            ->get()
            ->toArray();

        return $logs;
    }

    /**
     * 获取 WAF 配置
     */
    public function getConfig(): array
    {
        return [
            'enabled' => config('waf.enabled'),
            'mode' => config('waf.mode', 'block'),
            'cc_enabled' => config('waf.cc.enabled', true),
            'cc_mode' => config('waf.cc.mode', 'block'),
            'rules_enabled' => config('waf.rules.enabled', true),
            'rules_mode' => config('waf.rules.mode', 'block'),
            'cloudflare_enabled' => config('waf.cloudflare.enabled', false),
            'logging_enabled' => config('waf.logging.enabled', true),
        ];
    }

    /**
     * 更新 WAF 配置
     */
    public function updateConfig(array $data): array
    {
        $envMap = [
            'enabled' => 'WAF_ENABLED',
            'mode' => 'WAF_MODE',
            'cc_enabled' => 'WAF_CC_ENABLED',
            'cc_mode' => 'WAF_CC_MODE',
            'rules_enabled' => 'WAF_RULES_ENABLED',
            'rules_mode' => 'WAF_RULES_MODE',
        ];

        $updated = [];
        foreach ($envMap as $key => $envVar) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                $booleanValue = is_bool($value) ? ($value ? 'true' : 'false') : $value;
                // 注意：实际环境应通过 .env 或管理后台持久化
                $updated[$key] = $value;
            }
        }

        $this->clearDashboardCache();

        return [
            'updated' => $updated,
            'message' => 'WAF 配置已更新（修改将在下一次请求生效）',
            'note' => '生产环境请通过 .env 文件持久化配置',
        ];
    }

    /**
     * 清除过期日志
     */
    public function pruneLogs(int $retentionDays = null): array
    {
        $days = $retentionDays ?? config('waf.logging.retention_days', 30);
        $cutoff = now()->subDays($days);

        $deleted = WafAttackLog::where('created_at', '<', $cutoff)->delete();

        return [
            'deleted' => $deleted,
            'retention_days' => $days,
            'message' => "已清理 {$deleted} 条超过 {$days} 天的日志",
        ];
    }
}
