<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\Product;
use App\Models\RateLimitRule;
use App\Models\RateLimitStat;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * API 限流服务增强版（按产品/租户/API 分级限流）
 *
 * 支持多维度限流：
 * - IP: 按客户端 IP
 * - License: 按 License Key
 * - Product: 按产品（从请求中的 license_key 或 header 解析产品 ID）
 * - Tenant: 按租户
 * - API: 按具体 API 路径
 * - Global: 全局
 *
 * 支持配置多级限流组合（如：每 IP 60次/分钟 + 每产品 1000次/分钟）
 */
class EnhancedRateLimiter
{
    const CACHE_PREFIX = 'ratelimit_v2:';

    /**
     * 检查请求是否通过所有配置的限流规则
     *
     * @param Request $request
     * @param array|string $rules 限流规则数组或规则集名称（slug）
     * @return array ['allowed' => bool, 'headers' => array, 'retry_after' => int]
     */
    public function check(Request $request, array|string $rules = 'default'): array
    {
        // 如果是字符串，从 DB 加载规则
        $rules = is_string($rules) ? $this->loadRules($rules) : $rules;

        $headers = [];
        $retryAfter = 0;

        foreach ($rules as $rule) {
            $keyType = $rule['key_type'] ?? 'ip';
            $maxAttempts = (int) ($rule['max_attempts'] ?? 60);
            $windowSeconds = (int) ($rule['window_seconds'] ?? 60);

            $key = $this->buildKey($request, $keyType);

            if ($key === null) {
                continue;
            }

            $result = $this->checkSlidingWindow($key, $maxAttempts, $windowSeconds);

            $headers["X-RateLimit-Limit-{$keyType}"] = (string) $maxAttempts;
            $headers["X-RateLimit-Remaining-{$keyType}"] = (string) $result['remaining'];
            $headers["X-RateLimit-Reset-{$keyType}"] = (string) $result['reset_time'];

            if (! $result['allowed']) {
                $retryAfter = max($retryAfter, $result['retry_after']);

                Log::warning('增强限流触发', [
                    'key_type' => $keyType,
                    'key' => $key,
                    'max_attempts' => $maxAttempts,
                    'window_seconds' => $windowSeconds,
                    'current_count' => $result['current_count'],
                    'path' => $request->path(),
                ]);

                return [
                    'allowed' => false,
                    'headers' => $headers,
                    'retry_after' => $retryAfter,
                    'rule' => $rule,
                ];
            }
        }

        return [
            'allowed' => true,
            'headers' => $headers,
            'retry_after' => 0,
        ];
    }

    /**
     * 构建限流键
     */
    protected function buildKey(Request $request, string $keyType): ?string
    {
        return match ($keyType) {
            'ip' => self::CACHE_PREFIX . 'ip:' . ($request->ip() ?? 'unknown'),
            'license' => self::CACHE_PREFIX . 'license:' . ($request->header('X-License-Key')
                ?? $request->input('license_key')
                ?? 'unknown'),
            'product' => $this->buildProductKey($request),
            'tenant' => $this->buildTenantKey($request),
            'api_key' => $this->buildApiKeyKey($request),
            'api' => self::CACHE_PREFIX . 'api:' . str_replace('/', '_', trim($request->path(), '/')),
            'global' => self::CACHE_PREFIX . 'global',
            default => null,
        };
    }

    /**
     * 从请求中解析 API Key 并构建限流键
     */
    protected function buildApiKeyKey(Request $request): ?string
    {
        $apiKey = $request->header('X-Api-Key') ?? $request->bearerToken();
        if ($apiKey) {
            // 使用 API Key 的前 16 位作为标识
            $prefix = substr($apiKey, 0, 16);
            return self::CACHE_PREFIX . 'apikey:' . md5($prefix);
        }
        return null;
    }

    /**
     * 从请求中解析产品 ID 并构建限流键
     */
    protected function buildProductKey(Request $request): ?string
    {
        // 尝试从 license_key 解析产品
        $licenseKey = $request->header('X-License-Key') ?? $request->input('license_key');
        if ($licenseKey && $licenseKey !== 'unknown') {
            // 格式: HWT-{product_id}-XXXX
            if (preg_match('/^HWT-(\d+)/i', $licenseKey, $m)) {
                return self::CACHE_PREFIX . 'product:' . $m[1];
            }
        }

        // 尝试从 header 获取产品 ID
        $productId = $request->header('X-Product-Id');
        if ($productId) {
            return self::CACHE_PREFIX . 'product:' . $productId;
        }

        return null;
    }

    /**
     * 从请求中解析租户 ID 并构建限流键
     */
    protected function buildTenantKey(Request $request): ?string
    {
        // 尝试从 Header 获取
        $tenantId = $request->header('X-Tenant-Id');
        if ($tenantId) {
            return self::CACHE_PREFIX . 'tenant:' . $tenantId;
        }

        // 尝试从认证用户获取
        $user = $request->user();
        if ($user && $user->tenant_id) {
            return self::CACHE_PREFIX . 'tenant:' . $user->tenant_id;
        }

        return null;
    }

    /**
     * 滑动窗口限流检查
     */
    protected function checkSlidingWindow(string $key, int $maxAttempts, int $windowSeconds): array
    {
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        $requests = Cache::get($key, []);
        if (! is_array($requests)) {
            $requests = [];
        }

        // 移除窗口外的记录
        $requests = array_values(array_filter($requests, fn($ts) => $ts >= $windowStart));

        $currentCount = count($requests);
        $allowed = $currentCount < $maxAttempts;

        if ($allowed) {
            $requests[] = $now;
            $ttl = $windowSeconds * 2;
            Cache::put($key, $requests, $ttl);
        }

        // 计算需要等多久才能重试
        $retryAfter = 0;
        if (! $allowed && ! empty($requests)) {
            $oldestInWindow = $requests[0];
            $retryAfter = (int) ceil(($oldestInWindow + $windowSeconds) - $now);
            $retryAfter = max(1, $retryAfter);
        }

        return [
            'allowed' => $allowed,
            'remaining' => max(0, $maxAttempts - $currentCount),
            'reset_time' => (int) ($now + $windowSeconds),
            'current_count' => $currentCount,
            'retry_after' => $retryAfter,
        ];
    }

    /**
     * 清理过期数据
     */
    public function cleanExpired(string $key, int $windowSeconds): void
    {
        $now = microtime(true);
        $windowStart = $now - $windowSeconds;

        $requests = Cache::get($key, []);
        if (is_array($requests)) {
            $requests = array_values(array_filter($requests, fn($ts) => $ts >= $windowStart));
            Cache::put($key, $requests, $windowSeconds * 2);
        }
    }

    /**
     * 获取当前窗口的请求数（用于监控）
     */
    public function getCurrentCount(string $key): int
    {
        $requests = Cache::get($key, []);
        if (! is_array($requests)) {
            return 0;
        }

        $now = microtime(true);
        return count(array_filter($requests, fn($ts) => $ts >= $now - 60));
    }

    /**
     * 重置限流计数器（运维工具）
     */
    public function reset(string $key): void
    {
        Cache::forget($key);
    }

    /**
     * 获取默认的分级限流规则集
     *
     * 按 API 类型返回不同的限流组合：
     */
    public static function getDefaultRules(string $apiType = 'default'): array
    {
        return match ($apiType) {
            'activate' => [
                ['key_type' => 'ip', 'max_attempts' => 30, 'window_seconds' => 60],
                ['key_type' => 'product', 'max_attempts' => 500, 'window_seconds' => 3600],
                ['key_type' => 'license', 'max_attempts' => 10, 'window_seconds' => 60],
            ],
            'validate' => [
                ['key_type' => 'ip', 'max_attempts' => 60, 'window_seconds' => 60],
                ['key_type' => 'license', 'max_attempts' => 30, 'window_seconds' => 60],
            ],
            'api' => [
                ['key_type' => 'ip', 'max_attempts' => 100, 'window_seconds' => 60],
                ['key_type' => 'tenant', 'max_attempts' => 1000, 'window_seconds' => 60],
            ],
            'admin' => [
                ['key_type' => 'ip', 'max_attempts' => 200, 'window_seconds' => 60],
                ['key_type' => 'tenant', 'max_attempts' => 2000, 'window_seconds' => 60],
                ['key_type' => 'api', 'max_attempts' => 500, 'window_seconds' => 60],
            ],
            default => [
                ['key_type' => 'ip', 'max_attempts' => 60, 'window_seconds' => 60],
                ['key_type' => 'api', 'max_attempts' => 200, 'window_seconds' => 60],
            ],
        };
    }

    /**
     * 从数据库加载限流规则集
     *
     * @param string $slug 规则集标识
     * @return array
     */
    public function loadRules(string $slug): array
    {
        $rules = RateLimitRule::active()
            ->bySlug($slug)
            ->orderBy('priority')
            ->get();

        if ($rules->isNotEmpty()) {
            return $rules->map(fn($r) => $r->toLimiterRule())->toArray();
        }

        // DB 中未找到时回退到默认硬编码规则
        return self::getDefaultRules($slug);
    }

    /**
     * 记录限流命中统计
     */
    public function recordStat(string $ruleSlug, string $dimension, bool $blocked): void
    {
        try {
            $stat = RateLimitStat::firstOrCreate(
                [
                    'rule_slug' => $ruleSlug,
                    'dimension' => $dimension,
                    'window_start' => now()->startOfMinute(),
                    'window_end' => now()->endOfMinute(),
                ],
                [
                    'hit_count' => 0,
                    'blocked_count' => 0,
                ]
            );

            if ($blocked) {
                $stat->increment('blocked_count');
            } else {
                $stat->increment('hit_count');
            }
        } catch (\Throwable $e) {
            // 统计失败不影响主流程
            Log::warning('RateLimit stat record failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * 获取限流统计概览
     */
    public function getStats(array $filters = []): array
    {
        $query = RateLimitStat::query();

        if (! empty($filters['rule_slug'])) {
            $query->where('rule_slug', $filters['rule_slug']);
        }
        if (! empty($filters['dimension'])) {
            $query->where('dimension', $filters['dimension']);
        }
        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        return [
            'total_hits' => (clone $query)->sum('hit_count'),
            'total_blocked' => (clone $query)->sum('blocked_count'),
            'block_rate' => $this->calcBlockRate($query),
            'top_rules' => (clone $query)
                ->selectRaw('rule_slug, SUM(hit_count) as hits, SUM(blocked_count) as blocked')
                ->groupBy('rule_slug')
                ->orderByRaw('SUM(hit_count) DESC')
                ->limit(10)
                ->get(),
        ];
    }

    protected function calcBlockRate($query): float
    {
        $hits = (clone $query)->sum('hit_count');
        $blocked = (clone $query)->sum('blocked_count');
        $total = $hits + $blocked;

        return $total > 0 ? round($blocked / $total * 100, 2) : 0;
    }
}
