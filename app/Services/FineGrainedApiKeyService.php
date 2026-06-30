<?php

namespace App\Services;

use App\Models\ApiKey;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * 细粒度 API Key 权限服务 (M2-138)
 *
 * 提供：
 * - 端点级权限验证 (activate/validate/revoke/check)
 * - HTTP 方法级权限
 * - IP 白名单绑定
 * - 有效期精确到小时
 * - 用量配额检查
 * - SDK 端点元数据获取
 * - 管理后台配置校验
 */
class FineGrainedApiKeyService
{
    /**
     * 预定义的端点常量（与 ApiKey::SDK_ENDPOINTS 同步）
     */
    const ENDPOINTS = [
        'activate' => ['methods' => ['POST'], 'description' => '激活 License'],
        'validate' => ['methods' => ['GET'], 'description' => '验证 License 有效性'],
        'revoke' => ['methods' => ['POST'], 'description' => '吊销 License'],
        'check' => ['methods' => ['GET'], 'description' => '检查 License 状态'],
    ];

    /**
     * 全面验证 API Key 对指定端点的访问权限
     *
     * @return array{allowed: bool, reason: ?string}
     */
    public function checkEndpointAccess(ApiKey $apiKey, string $endpoint, string $method): array
    {
        // 1. 检查 Key 是否激活
        if (! $apiKey->is_active) {
            return ['allowed' => false, 'reason' => 'API Key 已被禁用'];
        }

        // 2. 检查有效期（精确到小时）
        if ($apiKey->expires_at) {
            $expiresAt = $apiKey->expires_at instanceof Carbon
                ? $apiKey->expires_at
                : Carbon::parse($apiKey->expires_at);

            if (now()->greaterThan($expiresAt)) {
                return ['allowed' => false, 'reason' => 'API Key 已过期'];
            }
        }

        // 3. 检查是否存在细粒度端点权限配置
        if (! empty($apiKey->endpoint_permissions)) {
            $allowedMethods = $apiKey->endpoint_permissions[$endpoint] ?? [];

            if (empty($allowedMethods)) {
                return ['allowed' => false, 'reason' => "无权访问端点: {$endpoint}"];
            }

            if (! in_array(strtoupper($method), array_map('strtoupper', $allowedMethods))) {
                return [
                    'allowed' => false,
                    'reason' => "端点 {$endpoint} 不允许 {$method} 方法",
                ];
            }

            return ['allowed' => true, 'reason' => null];
        }

        // 4. 回退到传统 allowed_endpoints 检查
        if (! empty($apiKey->allowed_endpoints)) {
            $hasEndpointAccess = false;
            foreach ($apiKey->allowed_endpoints as $pattern) {
                if (str_contains($pattern, $endpoint)) {
                    $hasEndpointAccess = true;
                    break;
                }
            }

            if (! $hasEndpointAccess) {
                return ['allowed' => false, 'reason' => "无权访问端点: {$endpoint}"];
            }

            // 检查方法权限
            if (! $apiKey->canMethod($method)) {
                return ['allowed' => false, 'reason' => "不允许 {$method} 方法"];
            }

            return ['allowed' => true, 'reason' => null];
        }

        // 5. 未配置端点限制，回退到权限级别的方法检查
        if (! $apiKey->canMethod($method)) {
            return ['allowed' => false, 'reason' => "权限级别不允许 {$method} 方法"];
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * 验证 API Key 对请求的 IP 访问
     */
    public function checkIpAccess(ApiKey $apiKey, string $ip): bool
    {
        return $apiKey->matchesIp($ip);
    }

    /**
     * 获取 SDK 端点元数据列表（供可视化配置和 SDK 自动适配）
     *
     * @return array<int, array{endpoint: string, methods: list<string>, description: string}>
     */
    public function getSdkEndpoints(): array
    {
        $result = [];
        foreach (self::ENDPOINTS as $name => $config) {
            $result[] = [
                'endpoint' => $name,
                'methods' => $config['methods'],
                'description' => $config['description'],
            ];
        }
        return $result;
    }

    /**
     * 获取指定 API Key 的端点权限配置
     */
    public function getKeyEndpointPermissions(ApiKey $apiKey): array
    {
        $endpointPermissions = $apiKey->endpoint_permissions ?? [];
        $sdkEndpoints = $this->getSdkEndpoints();

        $result = [];
        foreach ($sdkEndpoints as $ep) {
            $endpointName = $ep['endpoint'];
            $allowedMethods = $endpointPermissions[$endpointName] ?? [];

            $result[] = [
                'endpoint' => $endpointName,
                'methods' => $ep['methods'],
                'description' => $ep['description'],
                'allowed' => ! empty($allowedMethods),
                'allowed_methods' => $allowedMethods,
            ];
        }

        return $result;
    }

    /**
     * 更新 API Key 的端点级别权限
     *
     * @param array<string, list<string>> $permissions { "端点名": ["GET","POST"] }
     * @return array{success: bool, errors?: array<string, string>}
     */
    public function updateEndpointPermissions(ApiKey $apiKey, array $permissions): array
    {
        $errors = [];
        $validEndpoints = array_keys(self::ENDPOINTS);
        $validMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS'];

        foreach ($permissions as $endpoint => $methods) {
            // 验证端点名
            if (! in_array($endpoint, $validEndpoints)) {
                $errors[$endpoint] = "未知端点: {$endpoint}，有效端点: " . implode(', ', $validEndpoints);
                continue;
            }

            // 验证 HTTP 方法
            if (! is_array($methods)) {
                $errors[$endpoint] = '方法必须是数组';
                continue;
            }

            foreach ($methods as $method) {
                if (! in_array(strtoupper($method), $validMethods)) {
                    $errors[$endpoint] = "无效的 HTTP 方法: {$method}";
                    continue 2;
                }
            }
        }

        if (! empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // 清理：只保留有效端点的配置
        $cleaned = [];
        foreach ($validEndpoints as $endpoint) {
            if (isset($permissions[$endpoint]) && ! empty($permissions[$endpoint])) {
                $cleaned[$endpoint] = array_map('strtoupper', $permissions[$endpoint]);
            }
        }

        $apiKey->endpoint_permissions = ! empty($cleaned) ? $cleaned : null;
        $apiKey->save();

        return ['success' => true];
    }

    /**
     * 获取 API Key 的过期状态详情（精确到小时）
     *
     * @return array{expired: bool, expires_at: ?string, remaining_hours: ?int}
     */
    public function getExpiryStatus(ApiKey $apiKey): array
    {
        if ($apiKey->expires_at === null) {
            return [
                'expired' => false,
                'expires_at' => null,
                'remaining_hours' => null,
            ];
        }

        $expiresAt = $apiKey->expires_at instanceof Carbon
            ? $apiKey->expires_at
            : Carbon::parse($apiKey->expires_at);

        $remainingHours = max(0, (int) now()->diffInHours($expiresAt, false));

        return [
            'expired' => now()->greaterThan($expiresAt),
            'expires_at' => $expiresAt->toIso8601String(),
            'remaining_hours' => $remainingHours,
        ];
    }

    /**
     * 计算用量百分比
     */
    public function getUsagePercentage(ApiKey $apiKey): ?float
    {
        if ($apiKey->usage_quota === null || $apiKey->usage_quota === 0) {
            return null;
        }
        return round(($apiKey->usage_count / $apiKey->usage_quota) * 100, 1);
    }

    /**
     * 获取 Key 级别的用量配额快照
     *
     * @return array{usage_count: int, usage_quota: ?int, daily_usage: int, daily_quota: ?int, usage_percent: ?float, daily_usage_percent: ?float}
     */
    public function getQuotaSnapshot(ApiKey $apiKey): array
    {
        return [
            'usage_count' => $apiKey->usage_count,
            'usage_quota' => $apiKey->usage_quota,
            'daily_usage' => $apiKey->daily_usage,
            'daily_quota' => $apiKey->daily_quota,
            'usage_percent' => $this->getUsagePercentage($apiKey),
            'daily_usage_percent' => $apiKey->daily_quota
                ? round(($apiKey->daily_usage / $apiKey->daily_quota) * 100, 1)
                : null,
        ];
    }

    /**
     * 验证 API Key 是否有足够配额
     */
    public function checkQuota(ApiKey $apiKey): array
    {
        $exceeded = [];

        if (! $apiKey->hasQuota()) {
            $exceeded[] = '总配额已用完';
        }

        if (! $apiKey->hasDailyQuota()) {
            $exceeded[] = '每日配额已用完';
        }

        return [
            'allowed' => empty($exceeded),
            'exceeded' => $exceeded,
        ];
    }
}
