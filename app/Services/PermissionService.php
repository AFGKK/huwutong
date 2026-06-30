<?php

namespace App\Services;

use App\Models\PermissionAuditLog;
use App\Models\RoleHierarchy;
use App\Models\RoleTemplate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    /**
     * 记录权限变更审计日志
     */
    public function logAudit(
        string $action,
        ?string $targetType,
        ?int $targetId,
        ?string $targetName,
        $oldValues = null,
        $newValues = null,
        ?int $tenantId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): PermissionAuditLog {
        return PermissionAuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'target_name' => $targetName,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * 从请求中提取审计信息
     */
    public function logFromRequest(string $action, string $targetType, ?int $targetId, ?string $targetName, $oldValues, $newValues, Request $request): PermissionAuditLog
    {
        return $this->logAudit(
            $action,
            $targetType,
            $targetId,
            $targetName,
            $oldValues,
            $newValues,
            $request->user()?->tenant_id,
            $request->user()?->id,
            $request->ip(),
            $request->userAgent()
        );
    }

    /**
     * 获取审计日志列表
     */
    public function getAuditLogs(int $tenantId, array $filters = [], int $perPage = 20)
    {
        $query = PermissionAuditLog::with('user:id,name,email')
            ->byTenant($tenantId);

        if (!empty($filters['action'])) {
            $query->byAction($filters['action']);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('target_name', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * 获取审计日志统计
     */
    public function getAuditStats(int $tenantId): array
    {
        $logs = PermissionAuditLog::byTenant($tenantId);

        return [
            'total_changes' => $logs->count(),
            'by_action' => $logs->selectRaw('action, count(*) as total')
                ->groupBy('action')
                ->pluck('total', 'action')
                ->toArray(),
            'recent_days' => $logs->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    // ─── 角色层级 ───

    /**
     * 设置角色继承关系
     */
    public function setRoleHierarchy(int $roleId, ?int $parentRoleId): void
    {
        // 清除现有层级
        RoleHierarchy::where('role_id', $roleId)->delete();

        if ($parentRoleId && $parentRoleId !== $roleId) {
            // 检测循环继承
            if ($this->wouldCreateCycle($roleId, $parentRoleId)) {
                throw new \InvalidArgumentException('角色继承关系会导致循环引用');
            }
            RoleHierarchy::create([
                'role_id' => $roleId,
                'parent_role_id' => $parentRoleId,
            ]);
        }
    }

    /**
     * 检测循环继承
     */
    protected function wouldCreateCycle(int $roleId, int $parentRoleId): bool
    {
        $visited = [$roleId];
        $current = $parentRoleId;

        while ($current) {
            if (in_array($current, $visited)) {
                return true;
            }
            $visited[] = $current;
            $hierarchy = RoleHierarchy::where('role_id', $current)->first();
            $current = $hierarchy?->parent_role_id;
        }

        return false;
    }

    /**
     * 获取角色的继承链权限
     */
    public function getInheritedPermissions(int $roleId): array
    {
        $allPerms = [];
        $visited = [$roleId];
        $current = $roleId;

        while ($current) {
            $role = Role::with('permissions')->find($current);
            if ($role) {
                foreach ($role->permissions as $perm) {
                    $allPerms[$perm->name] = true;
                }
            }
            $hierarchy = RoleHierarchy::where('role_id', $current)->first();
            $current = $hierarchy?->parent_role_id;
            if ($current && in_array($current, $visited)) {
                break; // 安全保护
            }
            if ($current) {
                $visited[] = $current;
            }
        }

        return array_keys($allPerms);
    }

    /**
     * 获取角色层级树
     */
    public function getRoleHierarchy(int $tenantId): array
    {
        $roles = Role::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
        })->with('permissions')->get();

        $hierarchies = RoleHierarchy::with('parentRole')
            ->whereIn('role_id', $roles->pluck('id'))
            ->get()
            ->keyBy('role_id');

        $tree = [];
        foreach ($roles as $role) {
            $node = [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'tenant_id' => $role->tenant_id,
                'permissions_count' => $role->permissions->count(),
                'parent' => null,
            ];

            if (isset($hierarchies[$role->id])) {
                $node['parent'] = [
                    'id' => $hierarchies[$role->id]->parentRole->id,
                    'name' => $hierarchies[$role->id]->parentRole->name,
                ];
            }

            $tree[] = $node;
        }

        return $tree;
    }

    // ─── 角色模板 ───

    /**
     * 从模板创建角色
     */
    public function createRoleFromTemplate(int $templateId, string $roleName, int $tenantId): Role
    {
        $template = RoleTemplate::findOrFail($templateId);
        $permissions = $template->permissions ?? [];

        $role = Role::create([
            'name' => $roleName,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        if (!empty($permissions)) {
            $role->givePermissionTo($permissions);
        }

        return $role->load('permissions');
    }

    /**
     * 获取角色模板列表
     */
    public function getRoleTemplates(string $category = null)
    {
        $query = RoleTemplate::query();

        if ($category) {
            $query->byCategory($category);
        }

        return $query->orderBy('is_system', 'desc')
            ->orderBy('name')
            ->get();
    }

    /**
     * 初始化系统角色模板
     */
    public function seedSystemTemplates(): void
    {
        $templates = [
            [
                'name' => '技术支持',
                'description' => '处理客户工单和技术问题',
                'category' => 'industry',
                'permissions' => [
                    'customers.view',
                    'products.view',
                    'licenses.view',
                    'devices.view',
                    'subscriptions.view',
                    'invoices.view',
                    'logs.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => '运营人员',
                'description' => '日常运营和数据分析',
                'category' => 'industry',
                'permissions' => [
                    'users.view',
                    'customers.view', 'customers.create', 'customers.edit',
                    'products.view',
                    'licenses.view', 'licenses.create', 'licenses.edit',
                    'subscriptions.view',
                    'logs.view', 'logs.export',
                ],
                'is_system' => true,
            ],
            [
                'name' => '审计员',
                'description' => '只读审计和合规审查',
                'category' => 'industry',
                'permissions' => [
                    'users.view',
                    'customers.view',
                    'products.view',
                    'licenses.view',
                    'devices.view',
                    'subscriptions.view',
                    'invoices.view',
                    'earnings.view',
                    'logs.view', 'logs.export',
                    'permissions.view',
                    'settings.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => '销售代表',
                'description' => '客户开发和销售管理',
                'category' => 'industry',
                'permissions' => [
                    'customers.view', 'customers.create', 'customers.edit',
                    'products.view',
                    'licenses.view', 'licenses.create', 'licenses.activate',
                    'subscriptions.view', 'subscriptions.create',
                    'invoices.view',
                ],
                'is_system' => true,
            ],
            [
                'name' => '自定义 - 有限管理',
                'description' => '有限的管理权限，适合初级管理员',
                'category' => 'custom',
                'permissions' => [
                    'users.view',
                    'customers.view',
                    'products.view',
                    'licenses.view',
                    'logs.view',
                    'settings.view',
                ],
                'is_system' => true,
            ],
        ];

        foreach ($templates as $template) {
            RoleTemplate::firstOrCreate(
                ['name' => $template['name'], 'is_system' => true],
                $template
            );
        }
    }

    // ─── 用户直接权限 ───

    /**
     * 获取用户的直接权限（不通过角色继承）
     */
    public function getUserDirectPermissions(int $userId): array
    {
        $user = \App\Models\User::findOrFail($userId);
        return $user->getDirectPermissions()->pluck('name')->toArray();
    }

    /**
     * 为用户分配直接权限
     */
    public function assignUserDirectPermissions(int $userId, array $permissions, ?int $tenantId): void
    {
        $user = \App\Models\User::findOrFail($userId);
        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($tenantId ?? $user->tenant_id);
        $user->syncPermissions($permissions);
    }

    /**
     * 复制角色
     */
    public function duplicateRole(int $roleId, string $newName, int $tenantId): Role
    {
        $sourceRole = Role::with('permissions')->findOrFail($roleId);

        $role = Role::create([
            'name' => $newName,
            'guard_name' => 'web',
            'tenant_id' => $tenantId,
        ]);

        $permNames = $sourceRole->permissions->pluck('name')->toArray();
        if (!empty($permNames)) {
            $role->givePermissionTo($permNames);
        }

        return $role->load('permissions');
    }
}
