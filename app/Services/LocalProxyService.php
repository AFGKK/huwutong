<?php

namespace App\Services;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\LocalProxyActivationLog;
use App\Models\LocalProxyCachedLicense;
use App\Models\LocalProxyConfig;
use App\Models\LocalProxyHeartbeat;
use App\Models\LocalProxyNode;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * 本地 License 代理模式 (M3-12)
 *
 * 面向大型企业内网无外网场景：
 * - 企业内网部署本地代理节点
 * - 内网设备的 License 验证请求不经过公网
 * - 代理节点完成离线验证或缓存验证
 * - 定期与云端同步（心跳+状态+撤销列表CRL）
 */
class LocalProxyService
{
    /**
     * 注册代理节点（首次注册）
     */
    public function registerNode(int $tenantId, array $data): array
    {
        $nodeId = Str::uuid()->toString();
        $registerToken = Str::random(64);
        $apiKey = 'lpk_' . Str::random(48);

        $node = LocalProxyNode::create([
            'tenant_id' => $tenantId,
            'name' => $data['name'],
            'node_id' => $nodeId,
            'register_token' => $registerToken,
            'api_key' => $apiKey,
            'base_url' => $data['base_url'] ?? null,
            'version' => $data['version'] ?? null,
            'os' => $data['os'] ?? null,
            'architecture' => $data['architecture'] ?? null,
            'capabilities' => $data['capabilities'] ?? ['offline_auth', 'heartbeat', 'crl_sync', 'cache'],
            'status' => 'pending',
            'registered_at' => now(),
        ]);

        // 创建默认配置
        LocalProxyConfig::create([
            'node_id' => $node->id,
            'sync_mode' => $data['sync_mode'] ?? 'poll',
            'sync_interval_seconds' => $data['sync_interval_seconds'] ?? 300,
            'heartbeat_interval_seconds' => $data['heartbeat_interval_seconds'] ?? 60,
            'cache_ttl_seconds' => $data['cache_ttl_seconds'] ?? 86400,
            'max_cached_licenses' => $data['max_cached_licenses'] ?? 1000,
            'allow_offline_activation' => $data['allow_offline_activation'] ?? true,
            'require_cloud_validation' => $data['require_cloud_validation'] ?? false,
            'allowed_actions' => $data['allowed_actions'] ?? ['validate', 'activate', 'deactivate'],
            'ip_whitelist' => $data['ip_whitelist'] ?? [],
        ]);

        return [
            'node_id' => $nodeId,
            'register_token' => $registerToken,
            'api_key' => $apiKey,
            'status' => 'pending',
        ];
    }

    /**
     * 确认激活代理节点（运营人员审批后使用 register_token 换取激活）
     */
    public function activateNode(int $tenantId, string $nodeId, string $registerToken): array
    {
        $node = LocalProxyNode::where('tenant_id', $tenantId)
            ->where('node_id', $nodeId)
            ->where('register_token', $registerToken)
            ->where('status', 'pending')
            ->firstOrFail();

        $node->update([
            'status' => 'active',
            'register_token' => null, // 清除注册令牌
        ]);

        return [
            'node_id' => $node->node_id,
            'status' => 'active',
            'api_key' => $node->api_key,
        ];
    }

    /**
     * 处理心跳
     */
    public function processHeartbeat(string $apiKey, array $data): array
    {
        $node = $this->findNodeByApiKey($apiKey);
        abort_unless($node && $node->isActive(), 403, '代理节点未激活');

        $heartbeat = LocalProxyHeartbeat::create([
            'node_id' => $node->id,
            'heartbeat_at' => now(),
            'metrics' => $data['metrics'] ?? null,
            'cache_stats' => $data['cache_stats'] ?? null,
            'status' => $data['status'] ?? 'healthy',
            'error_message' => $data['error_message'] ?? null,
        ]);

        $node->update(['last_heartbeat_at' => now()]);

        // 检查是否有待同步的配置更新
        $pendingSync = $this->getPendingSyncConfig($node);

        // 检查是否有新的撤销列表需要同步
        $pendingRevocations = $this->getPendingRevocations($node);

        return [
            'heartbeat_id' => $heartbeat->id,
            'accepted' => true,
            'next_heartbeat_seconds' => $node->config()->first()?->heartbeat_interval_seconds ?? 60,
            'pending_sync' => $pendingSync,
            'pending_revocations' => $pendingRevocations,
        ];
    }

    /**
     * 离线验证 License（代理调用）
     */
    public function proxyValidate(string $apiKey, string $licenseKey, ?string $fingerprint = null): array
    {
        $node = $this->findNodeByApiKey($apiKey);
        abort_unless($node && $node->isActive(), 403, '代理节点未激活');

        $config = $node->config()->first();

        // 如果强制云端验证，走在线验证
        if ($config && $config->require_cloud_validation) {
            $result = $this->onlineValidate($node, $licenseKey, $fingerprint);
            $this->logActivation($node, null, $licenseKey, $fingerprint, 'validate', $result['allowed'] ? 'allowed' : 'denied', $result['reason'] ?? null);
            return $result;
        }

        // 检查本地缓存
        $cached = LocalProxyCachedLicense::where('node_id', $node->id)
            ->where('license_key', $licenseKey)
            ->first();

        if ($cached && !$cached->isExpired()) {
            // 缓存有效 — 使用缓存的payload进行验证
            $cached->increment('verify_count');
            $cached->update(['last_verified_at' => now()]);

            $this->logActivation($node, $cached->license_id, $licenseKey, $fingerprint, 'validate', 'allowed');

            return [
                'valid' => true,
                'source' => 'cache',
                'license_key' => $licenseKey,
            ];
        }

        // 缓存过期或不存在 — 尝试在线验证
        $result = $this->onlineValidate($node, $licenseKey, $fingerprint);

        if ($result['valid']) {
            // 在线验证通过 — 更新缓存
            $this->cacheLicenseForNode($node->id, $result['license'], $config);
        }

        $this->logActivation($node, $result['license_id'] ?? null, $licenseKey, $fingerprint, 'validate',
            $result['valid'] ? 'allowed' : 'denied', $result['reason'] ?? null);

        return $result;
    }

    /**
     * 获取云端配置（代理轮询）
     */
    public function getNodeConfig(string $apiKey): array
    {
        $node = $this->findNodeByApiKey($apiKey);
        abort_unless($node, 403, '无效代理');

        $config = $node->config()->first();

        // 获取该租户下需要同步到代理的 License 列表
        $licenses = License::where('tenant_id', $node->tenant_id)
            ->whereIn('status', ['active', 'suspended'])
            ->select('id', 'license_key', 'type', 'status', 'expires_at', 'max_devices', 'seats')
            ->get();

        // 获取撤销列表
        $revokedLicenses = License::where('tenant_id', $node->tenant_id)
            ->whereIn('status', ['revoked', 'blacklisted'])
            ->pluck('license_key')
            ->toArray();

        $allowedActions = $config->allowed_actions ?? ['validate', 'activate', 'deactivate'];

        return [
            'node' => [
                'id' => $node->node_id,
                'name' => $node->name,
                'api_key' => $node->api_key,
            ],
            'config' => [
                'sync_mode' => $config->sync_mode ?? 'poll',
                'sync_interval_seconds' => $config->sync_interval_seconds ?? 300,
                'heartbeat_interval_seconds' => $config->heartbeat_interval_seconds ?? 60,
                'cache_ttl_seconds' => $config->cache_ttl_seconds ?? 86400,
                'max_cached_licenses' => $config->max_cached_licenses ?? 1000,
                'allow_offline_activation' => $config->allow_offline_activation ?? true,
                'require_cloud_validation' => $config->require_cloud_validation ?? false,
                'allowed_actions' => $allowedActions,
                'ip_whitelist' => $config->ip_whitelist ?? [],
            ],
            'licenses' => $licenses->toArray(),
            'revoked_license_keys' => $revokedLicenses,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * 同步激活日志到云端
     */
    public function syncActivationLogs(string $apiKey, array $logs): array
    {
        $node = $this->findNodeByApiKey($apiKey);
        abort_unless($node && $node->isActive(), 403, '代理节点未激活');

        $synced = 0;
        foreach ($logs as $log) {
            $license = License::where('license_key', $log['license_key'])->first();

            LocalProxyActivationLog::create([
                'node_id' => $node->id,
                'license_id' => $license?->id,
                'license_key' => $log['license_key'],
                'fingerprint' => $log['fingerprint'] ?? null,
                'action' => $log['action'],
                'result' => $log['result'],
                'reason' => $log['reason'] ?? null,
                'client_ip' => $log['client_ip'] ?? null,
                'metadata' => $log['metadata'] ?? null,
                'synced_at' => now(),
            ]);
            $synced++;
        }

        return [
            'synced_count' => $synced,
            'status' => 'ok',
        ];
    }

    /**
     * 挂起/停用代理节点
     */
    public function updateNodeStatus(int $tenantId, int $nodeId, string $status): LocalProxyNode
    {
        $node = LocalProxyNode::where('tenant_id', $tenantId)->findOrFail($nodeId);
        $node->update(['status' => $status]);
        return $node->fresh();
    }

    /**
     * 更新代理配置
     */
    public function updateNodeConfig(int $tenantId, int $nodeId, array $data): LocalProxyConfig
    {
        $node = LocalProxyNode::where('tenant_id', $tenantId)->findOrFail($nodeId);
        $config = $node->config()->firstOrNew();
        $config->fill($data);
        $config->node_id = $node->id;
        $config->save();
        return $config->fresh();
    }

    /**
     * 获取代理节点列表（含状态概要）
     */
    public function getNodes(int $tenantId): array
    {
        $nodes = LocalProxyNode::where('tenant_id', $tenantId)
            ->with(['config', 'heartbeats' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return $nodes->map(fn($node) => [
            'id' => $node->id,
            'name' => $node->name,
            'node_id' => $node->node_id,
            'base_url' => $node->base_url,
            'version' => $node->version,
            'os' => $node->os,
            'status' => $node->status,
            'is_healthy' => $node->isHealthy(),
            'last_heartbeat_at' => $node->last_heartbeat_at?->toIso8601String(),
            'registered_at' => $node->registered_at?->toIso8601String(),
            'cached_licenses_count' => $node->cachedLicenses()->count(),
            'activation_logs_count' => $node->activationLogs()->count(),
            'config' => $node->config()->first()?->toArray(),
            'last_heartbeat' => $node->heartbeats->first()?->toArray(),
        ])->toArray();
    }

    /**
     * 获取节点详情
     */
    public function getNodeDetail(int $tenantId, int $nodeId): ?array
    {
        $node = LocalProxyNode::where('tenant_id', $tenantId)
            ->with(['config', 'cachedLicenses.license'])
            ->findOrFail($nodeId);

        $heartbeats = LocalProxyHeartbeat::where('node_id', $node->id)
            ->latest()
            ->limit(50)
            ->get();

        $activationLogs = LocalProxyActivationLog::where('node_id', $node->id)
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->latest()
            ->limit(100)
            ->get();

        return [
            'node' => $node->toArray(),
            'config' => $node->config()->first()?->toArray(),
            'heartbeats' => $heartbeats->toArray(),
            'cached_licenses' => $node->cachedLicenses->map(fn($cl) => [
                'id' => $cl->id,
                'license_key' => $cl->license_key,
                'cached_at' => $cl->cached_at?->toIso8601String(),
                'expires_at' => $cl->expires_at?->toIso8601String(),
                'last_verified_at' => $cl->last_verified_at?->toIso8601String(),
                'verify_count' => $cl->verify_count,
                'license_status' => $cl->license?->status,
                'is_expired' => $cl->isExpired(),
            ])->toArray(),
            'activation_logs' => $activationLogs->toArray(),
        ];
    }

    /**
     * 获取仪表盘统计数据
     */
    public function getDashboardStats(int $tenantId): array
    {
        $totalNodes = LocalProxyNode::where('tenant_id', $tenantId)->count();
        $activeNodes = LocalProxyNode::where('tenant_id', $tenantId)->where('status', 'active')->count();
        $healthyNodes = LocalProxyNode::where('tenant_id', $tenantId)->get()
            ->filter(fn($n) => $n->isHealthy())->count();

        $totalCachedLicenses = LocalProxyCachedLicense::whereIn('node_id', function ($q) use ($tenantId) {
            $q->select('id')->from('local_proxy_nodes')->where('tenant_id', $tenantId);
        })->count();

        $recentActivations = LocalProxyActivationLog::whereIn('node_id', function ($q) use ($tenantId) {
            $q->select('id')->from('local_proxy_nodes')->where('tenant_id', $tenantId);
        })->where('created_at', '>=', Carbon::now()->subDays(7))
            ->count();

        $deniedActivations = LocalProxyActivationLog::whereIn('node_id', function ($q) use ($tenantId) {
            $q->select('id')->from('local_proxy_nodes')->where('tenant_id', $tenantId);
        })->where('created_at', '>=', Carbon::now()->subDays(7))
            ->where('result', 'denied')
            ->count();

        return [
            'total_nodes' => $totalNodes,
            'active_nodes' => $activeNodes,
            'healthy_nodes' => $healthyNodes,
            'offline_nodes' => $activeNodes - $healthyNodes,
            'cached_licenses' => $totalCachedLicenses,
            'recent_activations_7d' => $recentActivations,
            'denied_activations_7d' => $deniedActivations,
        ];
    }

    // ─── 内部方法 ───

    protected function findNodeByApiKey(string $apiKey): ?LocalProxyNode
    {
        return LocalProxyNode::where('api_key', $apiKey)->first();
    }

    protected function onlineValidate(LocalProxyNode $node, string $licenseKey, ?string $fingerprint): array
    {
        $license = License::where('license_key', $licenseKey)
            ->where('tenant_id', $node->tenant_id)
            ->first();

        if (!$license) {
            return ['valid' => false, 'reason' => 'license_not_found'];
        }

        $status = LicenseStatus::tryFrom($license->status);
        if (!$status || !$status->isUsable()) {
            return ['valid' => false, 'reason' => 'license_status_invalid', 'license_id' => $license->id];
        }

        if ($license->expires_at && $license->expires_at->isPast()) {
            return ['valid' => false, 'reason' => 'license_expired', 'license_id' => $license->id];
        }

        return [
            'valid' => true,
            'license_id' => $license->id,
            'license' => $license,
            'license_key' => $license->license_key,
            'source' => 'cloud',
        ];
    }

    protected function cacheLicenseForNode(int $nodeId, License $license, ?LocalProxyConfig $config): void
    {
        $ttl = $config->cache_ttl_seconds ?? 86400;
        $cacheKey = 'lp_cache_' . $nodeId . '_' . $license->id;

        LocalProxyCachedLicense::updateOrCreate(
            ['node_id' => $nodeId, 'license_id' => $license->id],
            [
                'license_key' => $license->license_key,
                'cache_key' => $cacheKey,
                'cached_payload' => base64_encode($license->toJson()),
                'cached_at' => now(),
                'expires_at' => now()->addSeconds($ttl),
                'last_verified_at' => now(),
            ]
        );
    }

    protected function logActivation(
        LocalProxyNode $node, ?int $licenseId, string $licenseKey,
        ?string $fingerprint, string $action, string $result, ?string $reason = null
    ): void {
        LocalProxyActivationLog::create([
            'node_id' => $node->id,
            'license_id' => $licenseId,
            'license_key' => $licenseKey,
            'fingerprint' => $fingerprint,
            'action' => $action,
            'result' => $result,
            'reason' => $reason,
        ]);
    }

    protected function getPendingSyncConfig(LocalProxyNode $node): array
    {
        $updatedSince = $node->last_heartbeat_at;
        $config = $node->config()->first();
        if (!$config || !$updatedSince) {
            return [];
        }

        $needsSync = $config->updated_at > $updatedSince;
        if ($needsSync) {
            return [
                'sync_mode' => $config->sync_mode,
                'sync_interval_seconds' => $config->sync_interval_seconds,
                'cache_ttl_seconds' => $config->cache_ttl_seconds,
                'allow_offline_activation' => $config->allow_offline_activation,
            ];
        }
        return [];
    }

    protected function getPendingRevocations(LocalProxyNode $node): array
    {
        // 获取该租户下自上次心跳以来被撤销的 License
        $since = $node->last_heartbeat_at ?? now()->subDay();
        return License::where('tenant_id', $node->tenant_id)
            ->whereIn('status', ['revoked', 'blacklisted'])
            ->where('updated_at', '>=', $since)
            ->pluck('license_key')
            ->toArray();
    }
}
