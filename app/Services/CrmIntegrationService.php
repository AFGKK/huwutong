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
                default => throw new \InvalidArgumentException(__("app.crm_integration.msg_eeb71b10")),
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
            throw new \RuntimeException(__("app.crm_integration.hubspot_connection_failed") . $response->body());
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
            throw new \RuntimeException(__("app.crm_integration.salesforce_connection_failed") . $response->body());
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
        match ($connection->provider) {
            'hubspot' => $this->hubSpotUpdate($connection, $entityType, $entity, $remoteId),
            'salesforce' => $this->salesforceUpdate($connection, $entityType, $entity, $remoteId),
            default => throw new \InvalidArgumentException("Unsupported provider"),
        };
    }

    protected function fetchRemote(CrmConnection $connection, string $entityType): array
    {
        return match ($connection->provider) {
            'hubspot' => $this->hubSpotFetch($connection, $entityType),
            'salesforce' => $this->salesforceFetch($connection, $entityType),
            default => throw new \InvalidArgumentException("Unsupported provider"),
        };
    }

    protected function createLocal(int $tenantId, string $entityType, array $remote): int
    {
        if ($entityType === 'customer') {
            $customer = Customer::create([
                'tenant_id' => $tenantId,
                'name' => $remote['name'] ?? $remote['firstname'] ?? ($remote['firstName'] ?? ''),
                'email' => $remote['email'] ?? '',
                'phone' => $remote['phone'] ?? '',
                'company' => $remote['company'] ?? '',
            ]);
            return $customer->id;
        }
        return 0;
    }

    protected function updateLocal(string $entityType, int $localId, array $remote): void
    {
        if ($entityType === 'customer') {
            $customer = Customer::find($localId);
            if ($customer) {
                $customer->update([
                    'name' => $remote['name'] ?? $remote['firstname'] ?? $customer->name,
                    'email' => $remote['email'] ?? $customer->email,
                    'phone' => $remote['phone'] ?? $customer->phone,
                ]);
            }
        }
    }

    protected function hubSpotCreate(CrmConnection $connection, string $entityType, $entity): string
    {
        $token = $connection->access_token;
        $base = config('crm-integration.providers.hubspot.api_base', 'https://api.hubapi.com');

        if ($entityType === 'customer') {
            $properties = [
                'email' => $entity->email ?? '',
                'firstname' => $entity->name ?? '',
                'phone' => $entity->phone ?? '',
                'company' => $entity->company ?? '',
            ];
            $response = Http::withToken($token)->post("{$base}/crm/v3/objects/contacts", [
                'properties' => array_filter($properties),
            ]);
            if ($response->successful()) return $response->json('id') ?? '';
            Log::error('HubSpot create failed', ['response' => $response->body()]);
            throw new \RuntimeException('HubSpot create failed: ' . $response->body());
        }

        if ($entityType === 'license') {
            // 将 License 创建为 HubSpot Deal
            $response = Http::withToken($token)->post("{$base}/crm/v3/objects/deals", [
                'properties' => [
                    'dealname' => 'License: ' . ($entity->license_key ?? ''),
                    'amount' => (string)($entity->price ?? 0),
                    'dealstage' => 'closedwon',
                    'pipeline' => 'default',
                ],
            ]);
            if ($response->successful()) return $response->json('id') ?? '';
            throw new \RuntimeException('HubSpot deal create failed: ' . $response->body());
        }

        return '';
    }

    protected function hubSpotUpdate(CrmConnection $connection, string $entityType, $entity, string $remoteId): void
    {
        $token = $connection->access_token;
        $base = config('crm-integration.providers.hubspot.api_base', 'https://api.hubapi.com');
        $objectType = $entityType === 'customer' ? 'contacts' : 'deals';

        $properties = [];
        if ($entityType === 'customer') {
            $properties = [
                'email' => $entity->email ?? '',
                'firstname' => $entity->name ?? '',
                'phone' => $entity->phone ?? '',
            ];
        } elseif ($entityType === 'license') {
            $properties = [
                'dealname' => 'License: ' . ($entity->license_key ?? ''),
                'amount' => (string)($entity->price ?? 0),
            ];
        }

        $response = Http::withToken($token)->patch("{$base}/crm/v3/objects/{$objectType}/{$remoteId}", [
            'properties' => array_filter($properties),
        ]);

        if (!$response->successful()) {
            Log::error('HubSpot update failed', ['response' => $response->body()]);
        }
    }

    protected function hubSpotFetch(CrmConnection $connection, string $entityType): array
    {
        $token = $connection->access_token;
        $base = config('crm-integration.providers.hubspot.api_base', 'https://api.hubapi.com');
        $objectType = $entityType === 'customer' ? 'contacts' : 'deals';

        $response = Http::withToken($token)->get("{$base}/crm/v3/objects/{$objectType}", [
            'limit' => 50,
            'properties' => $entityType === 'customer' ? 'email,firstname,phone,company' : 'dealname,amount',
        ]);

        if (!$response->successful()) return [];

        $results = $response->json('results') ?? [];
        return array_map(fn($r) => array_merge(
            ['id' => $r['id']],
            $r['properties'] ?? []
        ), $results);
    }

    protected function salesforceCreate(CrmConnection $connection, string $entityType, $entity): string
    {
        $instanceUrl = $connection->instance_url;
        $token = $connection->access_token;
        $apiVersion = config('crm-integration.providers.salesforce.api_version', 'v58.0');

        $objectType = match ($entityType) {
            'customer' => 'Contact',
            'license' => 'Opportunity',
            default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}"),
        };

        $sObject = [];
        if ($entityType === 'customer') {
            $sObject = [
                'LastName' => $entity->name ?? 'Unknown',
                'Email' => $entity->email ?? '',
                'Phone' => $entity->phone ?? '',
            ];
        } elseif ($entityType === 'license') {
            $sObject = [
                'Name' => 'License: ' . ($entity->license_key ?? ''),
                'StageName' => 'Closed Won',
                'CloseDate' => now()->format('Y-m-d'),
                'Amount' => $entity->price ?? 0,
            ];
        }

        $response = Http::withToken($token)
            ->withHeaders(['Sforce-Auto-Assign' => 'FALSE'])
            ->post("{$instanceUrl}/services/data/{$apiVersion}/sobjects/{$objectType}", $sObject);

        if ($response->successful()) return $response->json('id') ?? '';
        Log::error('Salesforce create failed', [
            'objectType' => $objectType,
            'response' => $response->body(),
        ]);
        throw new \RuntimeException('Salesforce create failed: ' . $response->body());
    }

    protected function salesforceUpdate(CrmConnection $connection, string $entityType, $entity, string $remoteId): void
    {
        $instanceUrl = $connection->instance_url;
        $token = $connection->access_token;
        $apiVersion = config('crm-integration.providers.salesforce.api_version', 'v58.0');

        $objectType = $entityType === 'customer' ? 'Contact' : 'Opportunity';

        $sObject = [];
        if ($entityType === 'customer') {
            $sObject = ['LastName' => $entity->name ?? '', 'Email' => $entity->email ?? '', 'Phone' => $entity->phone ?? ''];
        }

        $response = Http::withToken($token)
            ->patch("{$instanceUrl}/services/data/{$apiVersion}/sobjects/{$objectType}/{$remoteId}", $sObject);

        if (!$response->successful()) {
            Log::error('Salesforce update failed', ['response' => $response->body()]);
        }
    }

    protected function salesforceFetch(CrmConnection $connection, string $entityType): array
    {
        $instanceUrl = $connection->instance_url;
        $token = $connection->access_token;
        $apiVersion = config('crm-integration.providers.salesforce.api_version', 'v58.0');

        $objectType = $entityType === 'customer' ? 'Contact' : 'Opportunity';
        $fields = $entityType === 'customer' ? 'Id, LastName, Email, Phone' : 'Id, Name, Amount, StageName';

        $response = Http::withToken($token)->get(
            "{$instanceUrl}/services/data/{$apiVersion}/query",
            ['q' => "SELECT {$fields} FROM {$objectType} LIMIT 50"]
        );

        if (!$response->successful()) return [];

        $records = $response->json('records') ?? [];
        return array_map(fn($r) => [
            'id' => $r['Id'],
            'name' => $r['LastName'] ?? $r['Name'] ?? '',
            'email' => $r['Email'] ?? '',
            'phone' => $r['Phone'] ?? '',
        ], $records);
    }
}
