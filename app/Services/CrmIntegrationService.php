<?php

namespace App\Services;

use App\Models\CrmConnection;
use App\Models\CrmEntityMapping;
use App\Models\CrmSyncLog;
use App\Models\Customer;
use App\Models\License;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * M3-42 CRM 集成服务
 */
class CrmIntegrationService
{
    /**
     * 连接 CRM
     */
    public function connect(int $tenantId, string $provider, array $credentials): CrmConnection
    {
        $connection = CrmConnection::updateOrCreate(
            ['tenant_id' => $tenantId, 'provider' => $provider],
            [
                'is_connected' => false,
                'status' => 'connecting',
                'config' => $credentials,
            ]
        );

        try {
            $result = match ($provider) {
                'hubspot' => $this->connectHubSpot($connection, $credentials),
                'salesforce' => $this->connectSalesforce($connection, $credentials),
                default => throw new \InvalidArgumentException("不支持的CRM: {$provider}"),
            };

            $connection->update([
                'is_connected' => true,
                'status' => 'connected',
                'last_success_at' => now(),
                'last_error' => null,
            ]);
        } catch (\Exception $e) {
            $connection->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $connection->fresh();
    }

    /**
     * 断开连接
     */
    public function disconnect(CrmConnection $connection): void
    {
        $connection->update([
            'is_connected' => false,
            'status' => 'disconnected',
            'access_token' => null,
            'refresh_token' => null,
        ]);
    }

    /**
     * 推送到 CRM（本地→CRM）
     */
    public function pushToCrm(CrmConnection $connection, string $entityType, array $ids = []): CrmSyncLog
    {
        $log = CrmSyncLog::create([
            'crm_connection_id' => $connection->id,
            'sync_type' => 'push',
            'entity_type' => $entityType,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $success = 0;
        $failed = 0;

        try {
            $query = $entityType === 'customer'
                ? Customer::where('tenant_id', $connection->tenant_id)
                : License::where('tenant_id', $connection->tenant_id);

            if (!empty($ids)) {
                $query->whereIn('id', $ids);
            }

            $entities = $query->limit(50)->get();

            foreach ($entities as $entity) {
                try {
                    $mapping = CrmEntityMapping::where('provider', $connection->provider)
                        ->where('entity_type', $entityType)
                        ->where('local_id', $entity->id)
                        ->first();

                    if ($mapping) {
                        $this->updateRemote($connection, $entityType, $entity, $mapping->remote_id);
                    } else {
                        $remoteId = $this->createRemote($connection, $entityType, $entity);
                        CrmEntityMapping::create([
                            'tenant_id' => $connection->tenant_id,
                            'provider' => $connection->provider,
                            'entity_type' => $entityType,
                            'local_id' => $entity->id,
                            'remote_id' => $remoteId,
                            'last_synced_at' => now(),
                        ]);
                    }
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                    Log::warning("CRM push failed for {$entityType}#{$entity->id}", ['error' => $e->getMessage()]);
                }
            }

            $log->update([
                'status' => $failed > 0 ? 'partial' : 'success',
                'total' => $success + $failed,
                'success' => $success,
                'failed' => $failed,
                'completed_at' => now(),
            ]);

            $connection->update(['last_sync_at' => now(), 'last_success_at' => now()]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * 从 CRM 拉取（CRM→本地）
     */
    public function pullFromCrm(CrmConnection $connection, string $entityType): CrmSyncLog
    {
        $log = CrmSyncLog::create([
            'crm_connection_id' => $connection->id,
            'sync_type' => 'pull',
            'entity_type' => $entityType,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $remoteEntities = $this->fetchRemote($connection, $entityType);
            $success = 0;
            $failed = 0;

            foreach ($remoteEntities as $remote) {
                try {
                    $mapping = CrmEntityMapping::where('provider', $connection->provider)
                        ->where('entity_type', $entityType)
                        ->where('remote_id', $remote['id'])
                        ->first();

                    if ($mapping) {
                        $this->updateLocal($entityType, $mapping->local_id, $remote);
                    } else {
                        $localId = $this->createLocal($connection->tenant_id, $entityType, $remote);
                        CrmEntityMapping::create([
                            'tenant_id' => $connection->tenant_id,
                            'provider' => $connection->provider,
                            'entity_type' => $entityType,
                            'local_id' => $localId,
                            'remote_id' => $remote['id'],
                            'last_synced_at' => now(),
                        ]);
                    }
                    $success++;
                } catch (\Exception $e) {
                    $failed++;
                }
            }

            $log->update([
                'status' => $failed > 0 ? 'partial' : 'success',
                'total' => $success + $failed,
                'success' => $success,
                'failed' => $failed,
                'completed_at' => now(),
            ]);

            $connection->update(['last_sync_at' => now(), 'last_success_at' => now()]);
        } catch (\Exception $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * 获取仪表盘
     */
    public function getDashboard(int $tenantId): array
    {
        $connections = CrmConnection::where('tenant_id', $tenantId)->get()->keyBy('provider');

        $stats = [];
        foreach (['hubspot', 'salesforce'] as $provider) {
            $conn = $connections->get($provider);
            $stats[$provider] = [
                'connected' => $conn?->is_connected ?? false,
                'status' => $conn?->status ?? 'disconnected',
                'last_sync_at' => $conn?->last_sync_at,
                'last_success_at' => $conn?->last_success_at,
                'last_error' => $conn?->last_error,
                'sync_count' => CrmSyncLog::where('crm_connection_id', $conn?->id)->count() ?? 0,
                'mapped_customers' => CrmEntityMapping::where('tenant_id', $tenantId)
                    ->where('provider', $provider)->where('entity_type', 'customer')->count(),
                'mapped_licenses' => CrmEntityMapping::where('tenant_id', $tenantId)
                    ->where('provider', $provider)->where('entity_type', 'license')->count(),
            ];
        }

        return $stats;
    }

    // ─── CRM 适配器 ───

    protected function connectHubSpot(CrmConnection $connection, array $credentials): array
    {
        $apiKey = $credentials['api_key'] ?? config('crm-integration.providers.hubspot.api_key');
        $response = Http::withToken($apiKey)->get('https://api.hubapi.com/crm/v3/objects/contacts');

        if (!$response->successful()) {
            throw new \RuntimeException('HubSpot 连接失败: ' . $response->body());
        }

        $connection->update([
            'access_token' => $apiKey,
            'portal_id' => $credentials['portal_id'] ?? '',
        ]);

        return ['status' => 'connected'];
    }

    protected function connectSalesforce(CrmConnection $connection, array $credentials): array
    {
        $response = Http::asForm()->post('https://login.salesforce.com/services/oauth2/token', [
            'grant_type' => 'password',
            'client_id' => $credentials['client_id'] ?? config('crm-integration.providers.salesforce.client_id'),
            'client_secret' => $credentials['client_secret'] ?? config('crm-integration.providers.salesforce.client_secret'),
            'username' => $credentials['username'] ?? config('crm-integration.providers.salesforce.username'),
            'password' => ($credentials['password'] ?? config('crm-integration.providers.salesforce.password'))
                . ($credentials['security_token'] ?? config('crm-integration.providers.salesforce.security_token', '')),
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException('Salesforce 连接失败: ' . $response->body());
        }

        $data = $response->json();
        $connection->update([
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'token_expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'instance_url' => $data['instance_url'] ?? '',
        ]);

        return $data;
    }

    protected function createRemote(CrmConnection $connection, string $entityType, $entity): string
    {
        return match ($connection->provider) {
            'hubspot' => $this->hubSpotCreate($connection, $entityType, $entity),
            'salesforce' => $this->salesforceCreate($connection, $entityType, $entity),
            default => throw new \InvalidArgumentException("Unsupported provider"),
        };
    }

    protected function updateRemote(CrmConnection $connection, string $entityType, $entity, string $remoteId): void
    {
        // 实际实现
    }

    protected function fetchRemote(CrmConnection $connection, string $entityType): array
    {
        return []; // 实际从CRM API获取
    }

    protected function createLocal(int $tenantId, string $entityType, array $remote): int
    {
        return 0; // 实际创建本地记录
    }

    protected function updateLocal(string $entityType, int $localId, array $remote): void
    {
        // 实际更新本地记录
    }

    protected function hubSpotCreate(CrmConnection $connection, string $entityType, $entity): string
    {
        if ($entityType === 'customer') {
            $response = Http::withToken($connection->access_token)->post(
                'https://api.hubapi.com/crm/v3/objects/contacts',
                ['properties' => ['email' => $entity->email, 'firstname' => $entity->name]]
            );
            return $response->json('id') ?? '';
        }
        return '';
    }

    protected function salesforceCreate(CrmConnection $connection, string $entityType, $entity): string
    {
        $objectType = $entityType === 'customer' ? 'Contact' : 'Opportunity';
        $response = Http::withToken($connection->access_token)
            ->withHeaders(['Sforce-Auto-Assign' => 'FALSE'])
            ->post("{$connection->instance_url}/services/data/v58.0/sobjects/{$objectType}", [
                'Name' => $entity->name ?? $entity->license_key,
            ]);
        return $response->json('id') ?? '';
    }
}
