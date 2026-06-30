<?php

namespace App\Services;

use App\Models\EdgeNode;
use App\Models\AiTokenQuota;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * M3-17 边缘计算授权 + AI Token 配额授权
 */
class EdgeAuthService
{
    /**
     * 注册边缘节点
     */
    public function registerNode(int $tenantId, array $data): EdgeNode
    {
        return EdgeNode::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'node_id' => 'edge_' . Str::random(24),
            'node_type' => $data['node_type'] ?? 'cloudflare',
            'region' => $data['region'] ?? null,
            'api_key' => 'edge_' . Str::random(48),
            'status' => 'active',
            'geo_allowed' => $data['geo_allowed'] ?? null,
            'config' => $data['config'] ?? null,
        ]);
    }

    /**
     * 验证边缘节点请求
     */
    public function authorizeRequest(string $nodeId, string $region = null): array
    {
        $node = EdgeNode::where('node_id', $nodeId)->where('status', 'active')->first();
        if (!$node) {
            return ['allowed' => false, 'reason' => '节点未注册或已禁用'];
        }

        // Geo 限制检查
        if ($region && !empty($node->geo_allowed)) {
            if (!in_array($region, $node->geo_allowed)) {
                return ['allowed' => false, 'reason' => "地区{$region}未被授权"];
            }
        }

        return ['allowed' => true, 'node' => $node];
    }

    /**
     * 记录心跳
     */
    public function heartbeat(string $nodeId): array
    {
        $node = EdgeNode::where('node_id', $nodeId)->first();
        if (!$node) {
            return ['success' => false, 'reason' => '节点未找到'];
        }

        $node->update(['last_heartbeat_at' => now()]);
        return ['success' => true, 'timestamp' => now()->toIso8601String()];
    }

    /**
     * 设置 AI Token 配额
     */
    public function setTokenQuota(string $type, int $id, int $monthlyLimit): AiTokenQuota
    {
        return AiTokenQuota::updateOrCreate(
            ['quotable_type' => $type, 'quotable_id' => $id],
            [
                'monthly_token_limit' => $monthlyLimit,
                'quota_reset_at' => now()->addMonth(),
            ]
        );
    }

    /**
     * 检查 AI Token 配额
     */
    public function checkTokenQuota(string $type, int $id): array
    {
        $quota = AiTokenQuota::where('quotable_type', $type)
            ->where('quotable_id', $id)
            ->first();

        if (!$quota) {
            return ['allowed' => true, 'reason' => '无限额'];
        }

        $remaining = $quota->monthly_token_limit - $quota->tokens_used;
        if ($remaining <= 0) {
            return ['allowed' => false, 'reason' => 'Token配额已用完'];
        }

        return ['allowed' => true, 'remaining' => $remaining, 'limit' => $quota->monthly_token_limit];
    }

    /**
     * 消耗 Token
     */
    public function consumeTokens(string $type, int $id, int $tokens): array
    {
        $quota = AiTokenQuota::where('quotable_type', $type)
            ->where('quotable_id', $id)
            ->first();

        if (!$quota) {
            return ['success' => false, 'reason' => '未设置配额'];
        }

        $quota->increment('tokens_used', $tokens);
        return ['success' => true, 'tokens_used' => $quota->tokens_used];
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $nodes = EdgeNode::where('tenant_id', $tenantId)->count();
        $activeNodes = EdgeNode::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $totalTokens = AiTokenQuota::where('tenant_id', $tenantId)->sum('tokens_used');
        $totalLimit = AiTokenQuota::where('tenant_id', $tenantId)->sum('monthly_token_limit');

        $healthyNodes = EdgeNode::where('tenant_id', $tenantId)
            ->where('last_heartbeat_at', '>=', now()->subMinutes(10))
            ->count();

        return compact('nodes', 'activeNodes', 'healthyNodes', 'totalTokens', 'totalLimit');
    }
}
