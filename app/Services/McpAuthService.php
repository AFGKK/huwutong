<?php

namespace App\Services;

use App\Models\McpServer;
use App\Models\AiAgent;
use App\Models\AiTokenUsage;
use Illuminate\Support\Str;

/**
 * M3-15 MCP Server 授权 + AI Agent 授权
 */
class McpAuthService
{
    /**
     * 注册 MCP Server
     */
    public function registerServer(int $tenantId, array $data): McpServer
    {
        return McpServer::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'server_id' => 'mcp_' . Str::random(32),
            'protocol' => $data['protocol'] ?? 'sse',
            'endpoint' => $data['endpoint'] ?? null,
            'capabilities' => $data['capabilities'] ?? ['tools'],
            'api_key' => 'mcp_' . Str::random(48),
            'status' => 'active',
        ]);
    }

    /**
     * 注册 AI Agent
     */
    public function registerAgent(int $tenantId, array $data): AiAgent
    {
        return AiAgent::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'agent_id' => 'agent_' . Str::random(32),
            'framework' => $data['framework'] ?? 'custom',
            'capabilities' => $data['capabilities'] ?? [],
            'api_key' => 'ag_' . Str::random(48),
            'monthly_token_quota' => $data['monthly_token_quota'] ?? config('mcp-auth.ai_agent.token_quota.default_monthly', 1000000),
            'status' => 'active',
            'quota_reset_at' => now()->addMonth(),
        ]);
    }

    /**
     * 记录 Token 用量
     */
    public function recordTokenUsage(string $type, int $id, int $tokens, string $model = null): void
    {
        AiTokenUsage::create([
            'usage_type' => $type,
            'usage_id' => $id,
            'model' => $model,
            'tokens' => $tokens,
            'requests' => 1,
            'recorded_at' => now(),
        ]);

        if ($type === 'ai_agent') {
            AiAgent::where('id', $id)->increment('tokens_used', $tokens);
        }
    }

    /**
     * 检查 AI Agent Token 配额
     */
    public function checkTokenQuota(int $agentId): array
    {
        $agent = AiAgent::find($agentId);
        if (!$agent || $agent->status !== 'active') {
            return ['allowed' => false, 'reason' => 'Agent不可用'];
        }

        $used = $agent->tokens_used;
        $limit = $agent->monthly_token_quota;

        if ($used >= $limit) {
            return ['allowed' => false, 'reason' => 'Token配额已用完', 'used' => $used, 'limit' => $limit];
        }

        return ['allowed' => true, 'used' => $used, 'limit' => $limit, 'remaining' => $limit - $used];
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $servers = McpServer::where('tenant_id', $tenantId)->count();
        $agents = AiAgent::where('tenant_id', $tenantId)->count();
        $activeServers = McpServer::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $activeAgents = AiAgent::where('tenant_id', $tenantId)->where('status', 'active')->count();

        $totalTokens = AiTokenUsage::whereIn('usage_id', function ($q) use ($tenantId) {
            $q->select('id')->from('ai_agents')->where('tenant_id', $tenantId);
        })->where('usage_type', 'ai_agent')->sum('tokens');

        return compact('servers', 'agents', 'activeServers', 'activeAgents', 'totalTokens');
    }
}
