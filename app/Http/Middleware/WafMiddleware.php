<?php

namespace App\Http\Middleware;

use App\Models\WafAttackLog;
use App\Models\WafIpList;
use App\Models\WafRule;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * WAF (Web Application Firewall) 中间件
 *
 * 应用层 WAF 防护，M1.3-18
 * 职责：OWASP Top 10 检测、CC 攻击防护、IP 黑白名单
 *
 * 与网关层 (Cloudflare/AWS WAF) 互补：
 * - 网关：全局限流、IP 黑名单、SSL、DDoS 缓解
 * - 应用层：OWASP 规则引擎、CC 行为分析、业务级防护
 */
class WafMiddleware
{
    /**
     * 配置缓存
     */
    protected array $config;

    /**
     * 请求指纹（IP+UA 哈希）
     */
    protected string $fingerprint;

    public function __construct()
    {
        $this->config = config('waf');
    }

    public function handle(Request $request, Closure $next): Response
    {
        // WAF 全局开关
        if (! ($this->config['enabled'] ?? true)) {
            return $next($request);
        }

        $ip = $request->ip();
        $this->fingerprint = md5($ip.'|'.($request->userAgent() ?? 'unknown'));

        // ─── 步骤 1: IP 白名单检查 ───
        if ($this->isWhitelisted($ip)) {
            return $next($request);
        }

        // ─── 步骤 2: IP 黑名单检查 ───
        $blacklistAction = $this->checkBlacklist($ip);
        if ($blacklistAction !== null) {
            return $this->blockResponse($request, 'ip_blacklist', "IP {$ip} 在黑名单中");
        }

        // ─── 步骤 3: 请求校验（方法/大小/头） ───
        $inspectionResult = $this->inspectRequest($request);
        if ($inspectionResult !== null) {
            return $inspectionResult;
        }

        // ─── 步骤 4: CC 攻击检测 ───
        $ccResult = $this->checkCcAttack($request);
        if ($ccResult instanceof Response) {
            return $ccResult;
        }

        // ─── 步骤 5: OWASP 规则引擎检查 ───
        $ruleResult = $this->checkRules($request);
        if ($ruleResult instanceof Response) {
            return $ruleResult;
        }

        return $next($request);
    }

    // ─── IP 黑白名单 ──────────────────────────────

    /**
     * 检查是否在白名单中
     */
    protected function isWhitelisted(string $ip): bool
    {
        // 检查动态白名单
        if (WafIpList::isInList($ip, 'whitelist')) {
            return true;
        }

        // 检查信任 CDN IP 段
        $trusted = $this->config['ip_list']['trusted_cidr'] ?? [];
        foreach ($trusted as $provider => $cidrs) {
            foreach ($cidrs as $cidr) {
                if (WafIpList::ipInCidr($ip, $cidr)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 检查黑名单
     */
    protected function checkBlacklist(string $ip): ?string
    {
        if (WafIpList::isInList($ip, 'blacklist')) {
            return $this->config['ip_list']['blacklist_mode'] ?? 'block';
        }

        return null;
    }

    // ─── 请求校验 ──────────────────────────────────

    /**
     * 基础请求校验
     */
    protected function inspectRequest(Request $request): ?Response
    {
        $inspection = $this->config['inspection'] ?? [];

        // 禁止的 HTTP 方法
        $method = strtoupper($request->method());
        if (in_array($method, $inspection['blocked_methods'] ?? [])) {
            $this->logAttack($request, 'inspection', "禁止的 HTTP 方法: {$method}", 'high', 'block');
            return response('Method Not Allowed', 405);
        }

        // 最大 URL 长度
        $url = $request->fullUrl();
        $maxUrl = $inspection['max_url_length'] ?? 2048;
        if (strlen($url) > $maxUrl) {
            $this->logAttack($request, 'inspection', "URL 超长: " . strlen($url), 'medium', 'block');
            return response('Request URI Too Long', 414);
        }

        // 请求头数量限制
        $headerCount = count(getallheaders());
        $maxHeaders = $inspection['max_headers'] ?? 50;
        if ($headerCount > $maxHeaders) {
            $this->logAttack($request, 'inspection', "请求头过多: {$headerCount}", 'medium', 'block');
            return response('Too Many Headers', 400);
        }

        // User-Agent 黑名单
        $ua = $request->userAgent() ?? '';
        $blockedUA = $inspection['blocked_user_agents'] ?? [];
        foreach ($blockedUA as $badUA) {
            if (stripos($ua, $badUA) !== false) {
                $this->logAttack($request, 'inspection', "禁止的 User-Agent: {$badUA}", 'high', 'block');
                return $this->blockResponse($request, 'bad_ua', "请求被拒绝");
            }
        }

        return null;
    }

    // ─── CC 攻击防护 ───────────────────────────────

    /**
     * CC 攻击检测
     */
    protected function checkCcAttack(Request $request): ?Response
    {
        $cc = $this->config['cc'] ?? [];
        if (! ($cc['enabled'] ?? true)) {
            return null;
        }

        $ip = $request->ip();
        $rateLimit = $cc['rate_limit'] ?? [];
        $window = $rateLimit['window_seconds'] ?? 60;
        $maxRequests = $rateLimit['max_requests'] ?? 300;
        $banDuration = $rateLimit['ban_duration'] ?? 300;

        // 检查是否已被封禁
        $cacheKey = "waf:blocked:{$this->fingerprint}";
        if (Cache::get($cacheKey)) {
            $this->logAttack($request, 'cc', "IP 已被封禁: {$ip}", 'high', 'block');
            return $this->blockResponse($request, 'cc_blocked', '请求频率过高，IP 已被临时封禁');
        }

        // 请求计数
        $counterKey = "waf:counter:{$this->fingerprint}:{$window}";
        $count = Cache::get($counterKey, 0);
        $count++;
        Cache::put($counterKey, $count, now()->addSeconds($window));

        if ($count >= $maxRequests) {
            // 封禁
            Cache::put($cacheKey, true, now()->addSeconds($banDuration));

            $this->logAttack($request, 'cc',
                "CC 攻击检测: {$ip} 在 {$window}s 内请求 {$count} 次（阈值: {$maxRequests}）",
                'critical', 'block'
            );

            return $this->blockResponse($request, 'rate_limited', '请求频率过高，请稍后再试');
        }

        // 行为分析
        $behavior = $cc['behavior'] ?? [];
        if ($behavior['enabled'] ?? true) {
            $aggressiveThreshold = $behavior['aggressive_threshold'] ?? 100;
            $pathSpreadThreshold = $behavior['path_spread_threshold'] ?? 20;

            // 攻击性请求检测
            if ($count >= $aggressiveThreshold) {
                // 记录但暂不封禁
                $this->logAttack($request, 'cc_behavior',
                    "攻击性请求: {$ip} 请求数 {$count}",
                    'warning', 'log'
                );
            }

            // 路径分布检测（扫描行为）
            $pathKey = "waf:paths:{$this->fingerprint}:{$window}";
            $paths = Cache::get($pathKey, []);
            $path = $request->path();
            if (!in_array($path, $paths)) {
                $paths[] = $path;
                Cache::put($pathKey, $paths, now()->addSeconds($window));
            }

            if (count($paths) >= $pathSpreadThreshold) {
                Cache::put($cacheKey, true, now()->addSeconds($banDuration));

                $this->logAttack($request, 'cc_scan',
                    "扫描行为: {$ip} 访问了 " . count($paths) . " 个不同路径",
                    'high', 'block'
                );

                return $this->blockResponse($request, 'scan_detected', '检测到扫描行为');
            }

            // 空 User-Agent 拦截
            if (($behavior['user_agent_empty_block'] ?? true) && empty($request->userAgent())) {
                return $this->blockResponse($request, 'empty_ua', '请求头缺少 User-Agent');
            }
        }

        return null;
    }

    // ─── OWASP 规则引擎 ────────────────────────────

    /**
     * 规则检查
     */
    protected function checkRules(Request $request): ?Response
    {
        $rulesConfig = $this->config['rules'] ?? [];
        if (! ($rulesConfig['enabled'] ?? true)) {
            return null;
        }

        $mode = $rulesConfig['mode'] ?? 'block';

        // 获取可检查的输入值
        $inputs = $this->getInputs($request);

        // 尝试从数据库加载自定义规则
        $customRules = $this->loadCustomRules();

        // 对每个输入值进行规则检查
        foreach ($inputs as $target => $values) {
            foreach ($values as $value) {
                if (empty($value) || !is_string($value)) {
                    continue;
                }

                // 检查内置规则
                foreach ($rulesConfig as $category => $ruleConfig) {
                    if (!is_array($ruleConfig) || !($ruleConfig['enabled'] ?? true)) {
                        continue;
                    }

                    $patterns = $ruleConfig['patterns'] ?? [];
                    $severity = $ruleConfig['severity'] ?? 'high';

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $value) === 1) {
                            return $this->handleRuleMatch(
                                $request, $category, $mode,
                                $pattern, $value, $target, $severity
                            );
                        }
                    }
                }

                // 检查自定义规则
                foreach ($customRules as $rule) {
                    if ($rule->matches($value)) {
                        return $this->handleRuleMatch(
                            $request, $rule->category, $rule->mode,
                            $rule->pattern, $value, $target, $rule->severity
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * 处理规则匹配
     */
    protected function handleRuleMatch(
        Request $request,
        string $category,
        string $mode,
        string $pattern,
        string $value,
        string $target,
        string $severity
    ): ?Response {
        $categoryLabels = [
            'sql_injection' => 'SQL 注入',
            'xss' => 'XSS 跨站脚本',
            'path_traversal' => '路径穿越',
            'command_injection' => '命令注入',
            'file_inclusion' => '文件包含',
            'ssrf' => 'SSRF',
        ];

        $label = $categoryLabels[$category] ?? $category;

        $this->logAttack($request, $category,
            "{$label} 检测: {$target} 包含恶意内容",
            $severity, $mode === 'block' ? 'block' : 'log'
        );

        if ($mode === 'block') {
            return $this->blockResponse($request, $category, "请求被 WAF 拦截: {$label}");
        }

        // detect/simulate 模式不拦截
        return null;
    }

    /**
     * 获取待检查的输入值
     */
    protected function getInputs(Request $request): array
    {
        $inputs = [];

        // URI 路径
        $inputs['uri'][] = $request->path();
        $inputs['uri'][] = urldecode($request->path());

        // 查询参数
        foreach ($request->query() as $key => $value) {
            $inputs['query'][] = $key;
            if (is_string($value)) {
                $inputs['query'][] = $value;
                $inputs['query'][] = urldecode($value);
            }
        }

        // 请求体
        $body = $request->all();
        array_walk_recursive($body, function ($v) use (&$inputs) {
            if (is_string($v)) {
                $inputs['body'][] = $v;
                $inputs['body'][] = urldecode($v);
            }
        });

        // Headers
        foreach ($request->header() as $key => $values) {
            $inputs['headers'][] = $key;
            foreach ($values as $v) {
                if (is_string($v)) {
                    $inputs['headers'][] = $v;
                }
            }
        }

        // Cookies
        foreach ($request->cookie() as $key => $value) {
            $inputs['cookies'][] = $key;
            if (is_string($value)) {
                $inputs['cookies'][] = $value;
            }
        }

        return $inputs;
    }

    /**
     * 从数据库加载自定义规则（缓存）
     */
    protected function loadCustomRules(): array
    {
        return Cache::remember('waf:rules:active', 300, function () {
            return WafRule::active()->ordered()->get()->all();
        });
    }

    // ─── 日志与响应 ────────────────────────────────

    /**
     * 记录攻击日志
     */
    protected function logAttack(
        Request $request,
        string $category,
        string $message,
        string $severity = 'high',
        string $action = 'block'
    ): void {
        if (! ($this->config['logging']['enabled'] ?? true)) {
            return;
        }

        try {
            $logConfig = $this->config['logging'] ?? [];

            WafAttackLog::create([
                'event_id' => 'waf_'.Str::random(32),
                'ip' => $request->ip(),
                'country' => $request->header('CF-IPCountry'),
                'method' => $request->method(),
                'uri' => Str::limit($request->fullUrl(), 500),
                'rule_category' => $category,
                'rule_name' => $message,
                'matched_pattern' => Str::limit($message, 200),
                'target' => $category,
                'severity' => $severity,
                'action_taken' => $action,
                'user_agent' => Str::limit($request->userAgent(), 500),
                'headers' => ($logConfig['log_headers'] ?? true) ? $this->sanitizeHeaders($request->header()) : null,
                'request_body' => ($logConfig['log_request_body'] ?? false)
                    ? Str::limit($request->getContent(), 1000) : null,
                'user_id' => $request->user()?->id,
                'is_whitelisted' => false,
                'is_trusted_ip' => false,
            ]);

            // 达到告警阈值时记录系统日志
            $threshold = $logConfig['alert_threshold'] ?? 100;
            $recentCount = WafAttackLog::recent(1)->count();
            if ($recentCount >= $threshold && $recentCount % $threshold === 0) {
                Log::warning("WAF 告警: 最近1分钟攻击次数已达 {$recentCount}");
            }
        } catch (\Exception $e) {
            Log::error('WAF 日志写入失败: ' . $e->getMessage());
        }
    }

    /**
     * 生成拦截响应
     */
    protected function blockResponse(Request $request, string $reason, string $message): Response
    {
        $acceptJson = $request->expectsJson() || $request->is('api/*');

        if ($acceptJson) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'error' => [
                    'code' => 'REQUEST_BLOCKED',
                    'reason' => $reason,
                ],
            ], 403);
        }

        return response($this->blockPage($reason, $message), 403)
            ->header('Content-Type', 'text/html; charset=utf-8');
    }

    /**
     * WAF 拦截页面
     */
    protected function blockPage(string $reason, string $message): string
    {
        $ref = $reason === 'rate_limited' || $reason === 'cc_blocked' ? 5 : 0;

        return <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>请求被拦截</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
               display: flex; justify-content: center; align-items: center;
               height: 100vh; margin: 0; background: #f5f7fa; color: #333; }
        .card { text-align: center; padding: 48px; background: #fff;
                border-radius: 12px; box-shadow: 0 4px 24px rgba(0,0,0,.08); max-width: 480px; }
        .icon { font-size: 64px; margin-bottom: 16px; }
        h1 { font-size: 20px; margin: 0 0 8px; }
        p { color: #666; margin: 0 0 24px; font-size: 14px; line-height: 1.6; }
        .btn { display: inline-block; padding: 10px 24px; background: #409eff; color: #fff;
               border-radius: 6px; text-decoration: none; font-size: 14px; }
        .ref { margin-top: 16px; font-size: 12px; color: #999; }
    </style>
    {$this->metaRefresh($ref)}
</head>
<body>
    <div class="card">
        <div class="icon">🛡️</div>
        <h1>请求被拦截</h1>
        <p>{$message}</p>
        <a href="/" class="btn">返回首页</a>
        <p class="ref">如果这是误拦截，请联系管理员并提供事件编号</p>
    </div>
</body>
</html>
HTML;
    }

    protected function metaRefresh(int $seconds): string
    {
        return $seconds > 0
            ? '<meta http-equiv="refresh" content="' . $seconds . '">'
            : '';
    }

    /**
     * 脱敏请求头
     */
    protected function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'api-key', 'token'];
        $result = [];

        foreach ($headers as $key => $values) {
            if (in_array(strtolower($key), $sensitive)) {
                $result[$key] = ['***'];
            } else {
                $result[$key] = $values;
            }
        }

        return $result;
    }
}
