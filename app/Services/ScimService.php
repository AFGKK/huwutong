<?php

namespace App\Services;

use App\Models\ScimConfig;
use App\Models\ScimResourceMapping;
use App\Models\ScimSyncLog;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * SCIM 自动用户同步服务 (M2-51)
 *
 * 实现 SCIM 2.0 标准 (RFC 7643/7644) 的用户和组同步。
 * 支持与 Okta/Azure AD/OneLogin/通用 SCIM 兼容 IdP 对接。
 */
class ScimService
{
    const SCIM_CONTENT_TYPE = 'application/scim+json';

    /**
     * 获取租户下的所有 SCIM 配置
     */
    public function getConfigs(int $tenantId)
    {
        return ScimConfig::where('tenant_id', $tenantId)->orderBy('name')->get();
    }

    /**
     * 创建或更新 SCIM 配置
     */
    public function saveConfig(int $tenantId, array $data, ?int $configId = null): ScimConfig
    {
        $data['tenant_id'] = $tenantId;

        if ($configId) {
            $config = ScimConfig::where('id', $configId)->where('tenant_id', $tenantId)->firstOrFail();
            $config->update($data);
            return $config->fresh();
        }

        return ScimConfig::create($data);
    }

    /**
     * 删除 SCIM 配置
     */
    public function deleteConfig(int $tenantId, int $configId): void
    {
        $config = ScimConfig::where('id', $configId)->where('tenant_id', $tenantId)->firstOrFail();
        $config->resourceMappings()->delete();
        $config->syncLogs()->delete();
        $config->delete();
    }

    /**
     * 测试 SCIM 连接
     */
    public function testConnection(ScimConfig $config): array
    {
        try {
            $response = Http::withToken($config->api_token)
                ->withHeaders(['Content-Type' => self::SCIM_CONTENT_TYPE])
                ->timeout(10)
                ->get(rtrim($config->base_url, '/') . '/ServiceProviderConfigs');

            if ($response->successful()) {
                return ['success' => true, 'message' => '连接成功'];
            }
            return ['success' => false, 'message' => '连接失败: HTTP ' . $response->status()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => '连接异常: ' . $e->getMessage()];
        }
    }

    /**
     * 执行用户同步（从 IdP 拉取用户）
     */
    public function syncUsers(ScimConfig $config): ScimSyncLog
    {
        $log = ScimSyncLog::create([
            'scim_config_id' => $config->id,
            'tenant_id' => $config->tenant_id,
            'direction' => 'inbound',
            'status' => 'running',
            'started_at' => now(),
        ]);

        $created = 0;
        $updated = 0;
        $deactivated = 0;
        $errors = [];

        try {
            $users = $this->fetchAllUsers($config);
            $options = $config->options ?? [];
            $attrMap = $config->attribute_mapping ?? ScimService::getDefaultAttributeMapping();
            $autoCreate = $options['auto_create'] ?? true;
            $autoUpdate = $options['auto_update'] ?? true;
            $autoDeprovision = $options['auto_deprovision'] ?? false;
            $roleMap = $options['role_map'] ?? [];

            foreach ($users as $scimUser) {
                try {
                    $externalId = $scimUser['id'];
                    $userName = $scimUser['userName'] ?? '';
                    $active = $scimUser['active'] ?? true;

                    // 提取属性
                    $attrs = $this->mapAttributes($scimUser, $attrMap);

                    // 查找已有的映射
                    $mapping = ScimResourceMapping::where('tenant_id', $config->tenant_id)
                        ->where('scim_config_id', $config->id)
                        ->where('external_id', $externalId)
                        ->first();

                    if ($mapping) {
                        // 已存在映射 → 更新
                        if (!$active && $autoDeprovision) {
                            // 停用用户
                            User::where('id', $mapping->internal_id)
                                ->where('tenant_id', $config->tenant_id)
                                ->update(['status' => 'inactive']);
                            $deactivated++;
                        } elseif ($autoUpdate) {
                            $user = User::where('id', $mapping->internal_id)
                                ->where('tenant_id', $config->tenant_id)
                                ->first();
                            if ($user) {
                                $user->update(array_merge($attrs, [
                                    'email' => $attrs['email'] ?? $user->email,
                                ]));
                                $updated++;
                            }
                        }

                        $mapping->update([
                            'external_user_name' => $userName,
                            'status' => $active ? 'active' : 'inactive',
                        ]);
                    } elseif ($active && $autoCreate) {
                        // 新用户 → 创建
                        $email = $attrs['email'] ?? $userName . '@scim.sync';
                        $name = $attrs['name'] ?? $userName;

                        // 去重检查
                        $existingUser = User::where('tenant_id', $config->tenant_id)
                            ->where('email', $email)
                            ->first();

                        if ($existingUser) {
                            // 已存在此邮箱，创建映射关联
                            ScimResourceMapping::create([
                                'tenant_id' => $config->tenant_id,
                                'scim_config_id' => $config->id,
                                'resource_type' => 'User',
                                'external_id' => $externalId,
                                'external_user_name' => $userName,
                                'internal_id' => $existingUser->id,
                                'status' => 'active',
                            ]);
                            if ($autoUpdate) {
                                $existingUser->update($attrs);
                            }
                            $updated++;
                        } else {
                            $user = User::create([
                                'tenant_id' => $config->tenant_id,
                                'name' => $name,
                                'email' => $email,
                                'password' => Hash::make(Str::random(32)),
                                'status' => 'active',
                            ]);

                            ScimResourceMapping::create([
                                'tenant_id' => $config->tenant_id,
                                'scim_config_id' => $config->id,
                                'resource_type' => 'User',
                                'external_id' => $externalId,
                                'external_user_name' => $userName,
                                'internal_id' => $user->id,
                                'status' => 'active',
                            ]);

                            // 角色映射
                            if (!empty($roleMap)) {
                                $scimRoles = $scimUser['roles'] ?? [];
                                foreach ($scimRoles as $scimRole) {
                                    if (isset($roleMap[$scimRole])) {
                                        // 应用角色分配
                                        $this->assignRole($user->id, $roleMap[$scimRole], $config->tenant_id);
                                    }
                                }
                            }

                            $created++;
                        }
                    }
                } catch (\Throwable $e) {
                    $errors[] = ['user' => $scimUser['userName'] ?? 'unknown', 'error' => $e->getMessage()];
                }
            }

            $log->update([
                'status' => 'completed',
                'total_processed' => count($users),
                'created_count' => $created,
                'updated_count' => $updated,
                'deactivated_count' => $deactivated,
                'error_count' => count($errors),
                'errors' => $errors,
                'summary' => "同步完成: 新增{$created}, 更新{$updated}, 停用{$deactivated}, 错误" . count($errors),
                'completed_at' => now(),
            ]);

            $config->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'success',
                'last_sync_error' => null,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'errors' => [['error' => $e->getMessage()]],
                'summary' => '同步失败: ' . $e->getMessage(),
                'completed_at' => now(),
            ]);

            $config->update([
                'last_sync_at' => now(),
                'last_sync_status' => 'failed',
                'last_sync_error' => $e->getMessage(),
            ]);
        }

        return $log->fresh();
    }

    /**
     * 获取同步日志
     */
    public function getSyncLogs(int $tenantId, int $configId, int $limit = 20)
    {
        return ScimSyncLog::where('tenant_id', $tenantId)
            ->where('scim_config_id', $configId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 获取仪表盘统计
     */
    public function getDashboard(int $tenantId): array
    {
        $configs = ScimConfig::where('tenant_id', $tenantId)->get();
        $enabledCount = $configs->where('enabled', true)->count();
        $totalMappings = ScimResourceMapping::where('tenant_id', $tenantId)->count();
        $activeMappings = ScimResourceMapping::where('tenant_id', $tenantId)
            ->where('status', 'active')->count();

        $recentLogs = ScimSyncLog::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return [
            'total_configs' => $configs->count(),
            'enabled_configs' => $enabledCount,
            'total_mappings' => $totalMappings,
            'active_mappings' => $activeMappings,
            'recent_logs' => $recentLogs,
            'configs' => $configs,
        ];
    }

    // ─── SCIM 标准端点 ───

    /**
     * SCIM ServiceProviderConfig 端点
     */
    public function getServiceProviderConfig(): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
            'patch' => ['supported' => true],
            'bulk' => ['supported' => false, 'maxOperations' => 0, 'maxPayloadSize' => 0],
            'filter' => ['supported' => true, 'maxResults' => 200],
            'changePassword' => ['supported' => true],
            'sort' => ['supported' => false],
            'etag' => ['supported' => false],
            'authenticationSchemes' => [
                [
                    'name' => 'OAuth Bearer Token',
                    'description' => 'Authentication Scheme using Bearer Token',
                    'specUri' => 'https://tools.ietf.org/html/rfc6750',
                    'type' => 'oauthbearertoken',
                    'primary' => true,
                ],
            ],
        ];
    }

    /**
     * SCIM /Users 端点 - 列出用户
     */
    public function listUsers(int $tenantId, array $params = []): array
    {
        $query = User::where('tenant_id', $tenantId);

        if (!empty($params['filter'])) {
            // 简化 filter 解析：支持 userName eq "xxx"
            if (preg_match('/userName\s+eq\s+"([^"]+)"/', $params['filter'], $m)) {
                $query->where('name', $m[1]);
            } elseif (preg_match('/email\s+eq\s+"([^"]+)"/', $params['filter'], $m)) {
                $query->where('email', $m[1]);
            }
        }

        $startIndex = max(1, (int)($params['startIndex'] ?? 1));
        $count = min(200, max(1, (int)($params['count'] ?? 50)));
        $total = $query->count();

        $users = $query->skip($startIndex - 1)->take($count)->get();

        $resources = $users->map(fn($u) => $this->formatScimUser($u));

        return [
            'schemas' => ['urn:ietf:params:scim:api:messages:2.0:ListResponse'],
            'totalResults' => $total,
            'startIndex' => $startIndex,
            'itemsPerPage' => $count,
            'Resources' => $resources->toArray(),
        ];
    }

    /**
     * SCIM /Users/{id} 端点
     */
    public function getUser(int $tenantId, int $userId): ?array
    {
        $user = User::where('id', $userId)->where('tenant_id', $tenantId)->first();
        if (!$user) return null;
        return $this->formatScimUser($user);
    }

    /**
     * SCIM /Users 端点 - 创建用户
     */
    public function createUser(int $tenantId, array $scimUser): array
    {
        $userName = $scimUser['userName'] ?? '';
        $name = $scimUser['name']['givenName'] ?? $userName;
        $email = '';

        foreach ($scimUser['emails'] ?? [] as $e) {
            if (($e['primary'] ?? false) || empty($email)) {
                $email = $e['value'];
            }
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'email' => $email ?: $userName . '@scim.local',
            'password' => Hash::make(Str::random(32)),
            'status' => ($scimUser['active'] ?? true) ? 'active' : 'inactive',
        ]);

        return $this->formatScimUser($user);
    }

    /**
     * SCIM /Users/{id} 端点 - 更新用户 (PUT)
     */
    public function updateUser(int $tenantId, int $userId, array $scimUser): ?array
    {
        $user = User::where('id', $userId)->where('tenant_id', $tenantId)->first();
        if (!$user) return null;

        $update = ['name' => $scimUser['userName'] ?? $user->name];

        foreach ($scimUser['emails'] ?? [] as $e) {
            if (($e['primary'] ?? false) || empty($update['email'])) {
                $update['email'] = $e['value'];
            }
        }

        if (isset($scimUser['active'])) {
            $update['status'] = $scimUser['active'] ? 'active' : 'inactive';
        }

        $user->update($update);
        return $this->formatScimUser($user->fresh());
    }

    /**
     * SCIM PATCH /Users/{id} 端点
     */
    public function patchUser(int $tenantId, int $userId, array $patch): ?array
    {
        $user = User::where('id', $userId)->where('tenant_id', $tenantId)->first();
        if (!$user) return null;

        foreach ($patch['Operations'] ?? [] as $op) {
            $path = $op['path'] ?? '';
            $value = $op['value'] ?? null;

            match ($path) {
                'active' => $user->update(['status' => $value ? 'active' : 'inactive']),
                'userName' => $user->update(['name' => $value]),
                'emails' => $this->patchEmails($user, $value),
                'name.givenName' => $user->update(['name' => $value]),
                default => null,
            };
        }

        return $this->formatScimUser($user->fresh());
    }

    /**
     * SCIM DELETE /Users/{id} 端点
     */
    public function deleteUser(int $tenantId, int $userId): bool
    {
        $user = User::where('id', $userId)->where('tenant_id', $tenantId)->first();
        if (!$user) return false;
        $user->update(['status' => 'inactive']);
        return true;
    }

    // ─── 私有方法 ───

    private function fetchAllUsers(ScimConfig $config): array
    {
        $allUsers = [];
        $startIndex = 1;
        $count = 100;
        $maxPages = 20;

        for ($page = 0; $page < $maxPages; $page++) {
            try {
                $response = Http::withToken($config->api_token)
                    ->withHeaders(['Content-Type' => self::SCIM_CONTENT_TYPE])
                    ->timeout(15)
                    ->get(rtrim($config->base_url, '/') . '/Users', [
                        'startIndex' => $startIndex,
                        'count' => $count,
                    ]);

                if (!$response->successful()) break;

                $data = $response->json();
                $users = $data['Resources'] ?? [];
                $allUsers = array_merge($allUsers, $users);

                $total = $data['totalResults'] ?? 0;
                if ($startIndex + $count > $total) break;

                $startIndex += $count;
            } catch (\Throwable $e) {
                Log::warning('SCIM fetch page failed', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        }

        return $allUsers;
    }

    private function mapAttributes(array $scimUser, array $mapping): array
    {
        $attrs = [];
        foreach ($mapping as $scimAttr => $localField) {
            $value = data_get($scimUser, $scimAttr);
            if ($value !== null) {
                // 处理 emails[0].value 类型
                if (is_array($value)) {
                    if (isset($value[0]['value'])) {
                        $value = $value[0]['value'];
                    } elseif (isset($value['value'])) {
                        $value = $value['value'];
                    } else {
                        continue;
                    }
                }
                $attrs[$localField] = $value;
            }
        }
        return $attrs;
    }

    private function formatScimUser(User $user): array
    {
        return [
            'schemas' => ['urn:ietf:params:scim:schemas:core:2.0:User'],
            'id' => (string)$user->id,
            'userName' => $user->name,
            'name' => [
                'givenName' => $user->name,
                'familyName' => '',
            ],
            'emails' => [
                ['value' => $user->email, 'primary' => true],
            ],
            'active' => $user->status === 'active',
            'meta' => [
                'resourceType' => 'User',
                'created' => $user->created_at?->toIso8601String(),
                'lastModified' => $user->updated_at?->toIso8601String(),
            ],
        ];
    }

    private function patchEmails(User $user, mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $email) {
                if (($email['primary'] ?? false) || empty($user->email)) {
                    $user->update(['email' => $email['value'] ?? '']);
                    break;
                }
            }
        }
    }

    private function assignRole(int $userId, string $role, int $tenantId): void
    {
        // 简化版角色分配 - 实际应使用 Spatie Permission 或 TenantMember
        TenantMember::updateOrCreate(
            ['user_id' => $userId, 'tenant_id' => $tenantId],
            ['role' => $role]
        );
    }

    public static function getDefaultAttributeMapping(): array
    {
        return [
            'userName' => 'name',
            'emails' => 'email',
            'name.givenName' => 'name',
            'active' => 'status',
        ];
    }

    public static function getProviderOptions(string $provider): array
    {
        return match ($provider) {
            'okta' => [
                'base_url_suffix' => '/scim',
                'description' => 'Okta SCIM 集成',
                'doc_url' => 'https://developer.okta.com/docs/concepts/scim/',
            ],
            'azure' => [
                'base_url_suffix' => '/scim',
                'description' => 'Azure AD / Entra ID SCIM 集成',
                'doc_url' => 'https://learn.microsoft.com/en-us/azure/active-directory/app-provisioning/',
            ],
            'onelogin' => [
                'base_url_suffix' => '/scim',
                'description' => 'OneLogin SCIM 集成',
                'doc_url' => 'https://developers.onelogin.com/scim',
            ],
            default => [
                'base_url_suffix' => '',
                'description' => '通用 SCIM 2.0 兼容 IdP',
                'doc_url' => 'https://datatracker.ietf.org/doc/rfc7644/',
            ],
        };
    }
}
