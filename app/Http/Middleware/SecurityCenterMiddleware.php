<?php

namespace App\Http\Middleware;

use App\Models\IpWhitelist;
use App\Models\LoginPolicy;
use App\Models\SecurityEvent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SecurityCenterMiddleware
{
    /**
     * 安全中心中间件
     *
     * 在每个请求中检查：
     * 1. IP 白名单/黑名单
     * 2. 登录策略（会话超时等）
     */
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $tenantId = $request->user()?->tenant_id;

        // ─── 1. IP 白名单/黑名单检查 ───
        if ($tenantId && $request->user()) {
            $enforced = Cache::remember("tenant:{$tenantId}:ip_enforced", 300, function () use ($tenantId) {
                $policy = LoginPolicy::where('tenant_id', $tenantId)
                    ->where('policy_key', 'ip_whitelist_enforced')
                    ->where('is_enabled', true)
                    ->first();
                return $policy && $policy->value === 'true';
            });

            if ($enforced) {
                $whitelisted = Cache::remember("tenant:{$tenantId}:whitelist", 60, function () use ($tenantId) {
                    return IpWhitelist::where('tenant_id', $tenantId)
                        ->where('type', 'whitelist')
                        ->where('is_active', true)
                        ->pluck('ip_address')
                        ->toArray();
                });

                // 检查黑名单（优先）
                $blacklisted = Cache::remember("tenant:{$tenantId}:blacklist", 60, function () use ($tenantId) {
                    return IpWhitelist::where('tenant_id', $tenantId)
                        ->where('type', 'blacklist')
                        ->where('is_active', true)
                        ->pluck('ip_address')
                        ->toArray();
                });

                $isBlocked = $this->matchIp($ip, $blacklisted);

                if ($isBlocked) {
                    IpWhitelist::where('tenant_id', $tenantId)
                        ->where('ip_address', $ip)
                        ->increment('hit_count');

                    SecurityEvent::create([
                        'user_id' => $request->user()->id,
                        'tenant_id' => $tenantId,
                        'event_type' => 'ip_blocked',
                        'severity' => 'warning',
                        'ip_address' => $ip,
                        'user_agent' => $request->userAgent(),
                        'description' => "IP {$ip} 被黑名单拦截",
                    ]);

                    return response()->json([
                        'message' => __('app.middleware.ip_in_blacklist'),
                        'code' => 'IP_BLOCKED',
                    ], 403);
                }

                // 有白名单则检查
                if (!empty($whitelisted)) {
                    $isAllowed = $this->matchIp($ip, $whitelisted);

                    if (!$isAllowed) {
                        SecurityEvent::create([
                            'user_id' => $request->user()->id,
                            'tenant_id' => $tenantId,
                            'event_type' => 'ip_blocked',
                            'severity' => 'warning',
                            'ip_address' => $ip,
                            'user_agent' => $request->userAgent(),
                            'description' => "IP {$ip} 不在白名单中",
                        ]);

                        return response()->json([
                            'message' => __('app.middleware.ip_not_in_whitelist'),
                            'code' => 'IP_NOT_WHITELISTED',
                        ], 403);
                    }

                    // 命中计数
                    IpWhitelist::where('tenant_id', $tenantId)
                        ->where('ip_address', $ip)
                        ->increment('hit_count');
                }
            }
        }

        return $next($request);
    }

    /**
     * 匹配 IP（支持 CIDR）
     */
    protected function matchIp(string $ip, array $list): bool
    {
        foreach ($list as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->cidrMatch($ip, $entry)) return true;
            } else {
                if ($ip === $entry) return true;
            }
        }
        return false;
    }

    protected function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr, 2);
        $bits = (int) $bits;

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return false; // IPv6 CIDR 简单跳过
        }

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);

        return ($ip & $mask) === ($subnet & $mask);
    }
}
