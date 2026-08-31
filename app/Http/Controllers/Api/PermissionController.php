<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PermissionAuditLog;
use App\Models\RoleTemplate;
use App\Models\User;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    // ══════════════════════════════════════════
    //  角色管理
    // ══════════════════════════════════════════

    /**
     * 角色列表
     */
    public function roles(Request $request): JsonResponse
    {
        $query = Role::with('permissions')
            ->where(function ($q) use ($request) {
                if ($tenantId = $request->user()?->tenant_id) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                }
            });

        if ($search = $request->input('search')) {
            $query->where('name', 'like', "%{$search}%");
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->orderBy('name')->paginate($perPage));
    }

    /**
     * 角色详情
     */
    public function roleShow(int $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);
        return ApiResponse::success($role);
    }

    /**
     * 创建角色
     */
    public function roleStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,guard_name,web',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'parent_role_id' => 'nullable|integer|exists:roles,id',
            'description' => 'nullable|string|max:500',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => $request->user()->tenant_id,
        ]);

        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        // 设置角色层级
        if (!empty($validated['parent_role_id'])) {
            $this->permissionService->setRoleHierarchy($role->id, $validated['parent_role_id']);
        }

        // 审计日志
        $this->permissionService->logFromRequest(
            'role_created', 'role', $role->id, $role->name,
            null, $validated, $request
        );

        return ApiResponse::created($role->load('permissions'), __('app.api.permission.role_created'));
    }

    /**
     * 更新角色
     */
    public function roleUpdate(int $id, Request $request): JsonResponse
    {
        $role = Role::findOrFail($id);
        $oldPerms = $role->permissions->pluck('name')->toArray();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id . ',id,guard_name,web',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
            'parent_role_id' => 'nullable|integer|exists:roles,id',
            'description' => 'nullable|string|max:500',
        ]);

        if (isset($validated['name'])) {
            $role->name = $validated['name'];
        }
        $role->save();

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        // 设置角色层级
        if (array_key_exists('parent_role_id', $validated)) {
            $this->permissionService->setRoleHierarchy($role->id, $validated['parent_role_id']);
        }

        // 审计日志
        $newPerms = $role->fresh()->permissions->pluck('name')->toArray();
        $this->permissionService->logFromRequest(
            'role_updated', 'role', $role->id, $role->name,
            ['permissions' => $oldPerms], ['permissions' => $newPerms],
            $request
        );

        return ApiResponse::success($role->fresh()->load('permissions'), __('app.api.permission.role_updated'));
    }

    /**
     * 删除角色
     */
    public function roleDestroy(int $id, Request $request): JsonResponse
    {
        $role = Role::findOrFail($id);

        // 保护系统角色
        if (in_array($role->name, ['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'])) {
            return ApiResponse::error('SYSTEM_ROLE', __('app.api.permission.system_role_delete'), 403);
        }

        $permSnapshot = $role->permissions->pluck('name')->toArray();
        $role->delete();

        // 审计日志
        $this->permissionService->logFromRequest(
            'role_deleted', 'role', $id, $role->name,
            ['permissions' => $permSnapshot], null, $request
        );

        return ApiResponse::success(null, __('app.api.permission.role_deleted'));
    }

    /**
     * 复制角色
     */
    public function roleDuplicate(int $id, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,guard_name,web',
        ]);

        $newRole = $this->permissionService->duplicateRole(
            $id,
            $validated['name'],
            $request->user()->tenant_id
        );

        return ApiResponse::created($newRole, __('app.api.permission.role_duplicated'));
    }

    // ══════════════════════════════════════════
    //  权限管理
    // ══════════════════════════════════════════

    /**
     * 所有权限（按分组）
     */
    public function allPermissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();

        $grouped = $permissions->groupBy(function ($p) {
            return explode('.', $p->name)[0] ?? 'other';
        });

        return ApiResponse::success([
            'all' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    /**
     * 创建新权限
     */
    public function permissionStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:permissions,name,NULL,id,guard_name,web',
            'group' => 'nullable|string|max:50',
        ]);

        $name = $validated['name'];
        // 如果提供了 group，自动添加前缀
        if (!empty($validated['group']) && !str_contains($name, '.')) {
            $name = $validated['group'] . '.' . $name;
        }

        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'web',
        ]);

        return ApiResponse::created($permission, __('app.api.permission.permission_created'));
    }

    /**
     * 批量创建权限
     */
    public function permissionBatchStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.name' => 'required|string|max:255',
            'permissions.*.group' => 'nullable|string|max:50',
        ]);

        $created = [];
        foreach ($validated['permissions'] as $item) {
            $name = $item['name'];
            if (!empty($item['group']) && !str_contains($name, '.')) {
                $name = $item['group'] . '.' . $name;
            }
            try {
                $perm = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                ]);
                $created[] = $perm;
            } catch (\Exception $e) {
                // skip duplicates
            }
        }

        return ApiResponse::created($created, __('app.api.permission.permissions_created', ['count' => count($created)]));
    }

    /**
     * 删除权限
     */
    public function permissionDestroy(int $id): JsonResponse
    {
        $permission = Permission::findOrFail($id);
        $permission->delete();

        return ApiResponse::success(null, __('app.api.permission.permission_deleted'));
    }

    // ══════════════════════════════════════════
    //  角色层级管理
    // ══════════════════════════════════════════

    /**
     * 角色层级树
     */
    public function roleHierarchy(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->permissionService->getRoleHierarchy($tenantId)
        );
    }

    // ══════════════════════════════════════════
    //  角色模板
    // ══════════════════════════════════════════

    /**
     * 角色模板列表
     */
    public function roleTemplates(Request $request): JsonResponse
    {
        $templates = $this->permissionService->getRoleTemplates($request->input('category'));
        return ApiResponse::success($templates);
    }

    /**
     * 从模板创建角色
     */
    public function roleFromTemplate(int $templateId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name,NULL,id,guard_name,web',
        ]);

        $role = $this->permissionService->createRoleFromTemplate(
            $templateId,
            $validated['name'],
            $request->user()->tenant_id
        );

        return ApiResponse::created($role, __('app.api.permission.role_created'));
    }

    /**
     * 创建自定义模板
     */
    public function templateStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:role_templates,name',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|max:50',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ]);

        $template = RoleTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? 'custom',
            'permissions' => $validated['permissions'] ?? [],
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::created($template, __('app.api.permission.template_created'));
    }

    /**
     * 删除模板
     */
    public function templateDestroy(int $id): JsonResponse
    {
        $template = RoleTemplate::findOrFail($id);
        if ($template->is_system) {
            return ApiResponse::error('SYSTEM_TEMPLATE', __('app.api.permission.system_template_delete'), 403);
        }
        $template->delete();
        return ApiResponse::success(null, __('app.api.permission.template_deleted'));
    }

    /**
     * 初始化系统模板
     */
    public function seedTemplates(): JsonResponse
    {
        $this->permissionService->seedSystemTemplates();
        return ApiResponse::success(
            RoleTemplate::where('is_system', true)->get(),
            __('app.api.permission.system_templates_seeded')
        );
    }

    // ══════════════════════════════════════════
    //  用户角色分配
    // ══════════════════════════════════════════

    /**
     * 租户下用户列表（用于分配角色）
     */
    public function tenantUsers(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $users = User::where('tenant_id', $tenantId)
            ->with('roles')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'status']);

        return ApiResponse::success($users);
    }

    /**
     * 获取用户的角色
     */
    public function userRoles(int $userId, Request $request): JsonResponse
    {
        $user = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($userId);
        return ApiResponse::success([
            'user' => $user,
            'roles' => $user->roles,
            'permissions' => $user->getAllPermissions(),
        ]);
    }

    /**
     * 分配角色给用户
     */
    public function assignRoles(int $userId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'roles' => 'required|array',
            'roles.*' => 'string|exists:roles,name',
        ]);

        $user = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($userId);
        $oldRoles = $user->roles->pluck('name')->toArray();

        app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
        $user->syncRoles($validated['roles']);

        // 审计日志
        $this->permissionService->logFromRequest(
            'user_role_assigned', 'user', $user->id, $user->name,
            ['roles' => $oldRoles], ['roles' => $validated['roles']],
            $request
        );

        return ApiResponse::success(
            $user->fresh()->load('roles'),
            __('app.api.permission.role_assigned')
        );
    }

    /**
     * 当前用户权限
     */
    public function myPermissions(Request $request): JsonResponse
    {
        $user = $request->user();
        return ApiResponse::success([
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
            'all_permissions' => Permission::orderBy('name')->pluck('name'),
        ]);
    }

    // ══════════════════════════════════════════
    //  权限审计日志
    // ══════════════════════════════════════════

    /**
     * 审计日志列表
     */
    public function auditLogs(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $perPage = min((int) $request->input('per_page', 20), 100);

        $logs = $this->permissionService->getAuditLogs($tenantId, $request->only([
            'action', 'user_id', 'date_from', 'date_to', 'search',
        ]), $perPage);

        return ApiResponse::paginated($logs);
    }

    /**
     * 审计日志统计
     */
    public function auditStats(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        return ApiResponse::success(
            $this->permissionService->getAuditStats($tenantId)
        );
    }

    // ══════════════════════════════════════════
    //  用户直接权限
    // ══════════════════════════════════════════

    /**
     * 获取用户直接权限
     */
    public function userDirectPermissions(int $userId, Request $request): JsonResponse
    {
        $user = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($userId);
        return ApiResponse::success([
            'direct_permissions' => $this->permissionService->getUserDirectPermissions($userId),
            'inherited_permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /**
     * 为用户分配直接权限
     */
    public function assignUserDirectPermissions(int $userId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $user = User::where('tenant_id', $request->user()->tenant_id)->findOrFail($userId);
        $oldPerms = $user->getDirectPermissions()->pluck('name')->toArray();

        $this->permissionService->assignUserDirectPermissions(
            $userId,
            $validated['permissions'] ?? [],
            $request->user()->tenant_id
        );

        $this->permissionService->logFromRequest(
            'permission_assigned', 'user', $user->id, $user->name,
            ['direct_permissions' => $oldPerms],
            ['direct_permissions' => $validated['permissions'] ?? []],
            $request
        );

        return ApiResponse::success(null, __('app.api.permission.direct_permission_updated'));
    }
}
