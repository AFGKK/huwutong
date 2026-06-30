<?php

namespace App\Services;

use App\Models\ServerlessFunction;
use App\Models\ServerlessInvocation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * M3-16 云函数授权（Serverless 短时授权/API配额QPS管控）
 */
class ServerlessAuthService
{
    /**
     * 注册云函数
     */
    public function registerFunction(int $tenantId, array $data): ServerlessFunction
    {
        return ServerlessFunction::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'function_id' => 'sl_' . Str::random(24),
            'runtime' => $data['runtime'] ?? 'nodejs',
            'qps_limit' => $data['qps_limit'] ?? config('serverless-auth.quota.default_qps', 10),
            'monthly_invocation_limit' => $data['monthly_invocation_limit'] ?? config('serverless-auth.quota.default_monthly_invocations', 100000),
            'timeout_seconds' => $data['timeout_seconds'] ?? 30,
            'status' => 'active',
            'auth_config' => $data['auth_config'] ?? ['type' => 'api_key'],
        ]);
    }

    /**
     * 生成短时授权 Token
     */
    public function generateToken(ServerlessFunction $function): array
    {
        $token = 'slt_' . Str::random(48);
        $ttl = config('serverless-auth.serverless.short_lived_token_ttl_seconds', 3600);

        Cache::put("serverless_token:{$token}", [
            'function_id' => $function->id,
            'tenant_id' => $function->tenant_id,
            'qps_limit' => $function->qps_limit,
        ], $ttl);

        return [
            'token' => $token,
            'expires_in' => $ttl,
            'function_id' => $function->function_id,
        ];
    }

    /**
     * 验证调用请求（QPS + 配额检查）
     */
    public function authorizeInvocation(string $token): array
    {
        $data = Cache::get("serverless_token:{$token}");
        if (!$data) {
            return ['allowed' => false, 'reason' => 'Token无效或已过期', 'status_code' => 401];
        }

        $function = ServerlessFunction::find($data['function_id']);
        if (!$function || $function->status !== 'active') {
            return ['allowed' => false, 'reason' => '函数不可用', 'status_code' => 403];
        }

        // QPS 检查
        $cacheKey = "serverless_qps:{$function->id}";
        $count = Cache::increment($cacheKey);
        if ($count === 1) {
            Cache::expire($cacheKey, 1);
        }
        if ($count > $function->qps_limit) {
            return ['allowed' => false, 'reason' => 'QPS超限', 'status_code' => 429];
        }

        // 月度配额检查
        if ($function->invocations_used >= $function->monthly_invocation_limit) {
            return ['allowed' => false, 'reason' => '月度调用配额已用完', 'status_code' => 429];
        }

        return ['allowed' => true, 'function' => $function];
    }

    /**
     * 记录调用
     */
    public function recordInvocation(ServerlessFunction $function, array $data): ServerlessInvocation
    {
        $function->increment('invocations_used');
        $function->update(['last_invoked_at' => now()]);

        return ServerlessInvocation::create([
            'serverless_function_id' => $function->id,
            'invocation_id' => $data['invocation_id'] ?? ('inv_' . Str::random(24)),
            'token_id' => $data['token_id'] ?? null,
            'source_ip' => $data['source_ip'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? 0,
            'status_code' => $data['status_code'] ?? 200,
            'status' => $data['status'] ?? 'success',
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $total = ServerlessFunction::where('tenant_id', $tenantId)->count();
        $active = ServerlessFunction::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $totalInvocations = ServerlessFunction::where('tenant_id', $tenantId)->sum('invocations_used');
        $totalLimit = ServerlessFunction::where('tenant_id', $tenantId)->sum('monthly_invocation_limit');

        return compact('total', 'active', 'totalInvocations', 'totalLimit');
    }
}
