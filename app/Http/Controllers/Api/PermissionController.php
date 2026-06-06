<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{
    // ─── 角色管理 ───

    /**
     * 角色列表
     */
    public function roles(Request $request): JsonResponse
    {
        $query = Role::with('permissions')
            ->where(function ($q) {
                // super-admin 是平台级别，只在没有 tenant_id 时不限
                // tenant 下只能看到自己创建的角色
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
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'tenant_id' => $request->user()->tenant_id,
        ]);

        if (!empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        return ApiResponse::created($role->load('permissions'), '角色创建成功');
    }

    /**
     * 更新角色
     */
    public function roleUpdate(int $id, Request $request): JsonResponse
    {
        $role = Role::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,' . $id . ',id,guard_name,web',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['name'])) {
            $role->name = $validated['name'];
        }
        $role->save();

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return ApiResponse::success($role->fresh()->load('permissions'), '角色更新成功');
    }

    /**
     * 删除角色
     */
    public function roleDestroy(int $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        // 保护系统角色
        if (in_array($role->name, ['super-admin', 'tenant-admin', 'finance', 'developer', 'readonly'])) {
            return ApiResponse::error('SYSTEM_ROLE', '系统角色不可删除', 403);
        }

        $role->delete();
        return ApiResponse::success(null, '角色已删除');
    }

    // ─── 权限管理 ───

    /**
     * 所有权限（按分组）
     */
    public function allPermissions(): JsonResponse
    {
        $permissions = Permission::orderBy('name')->get();

        // 按组分组（权限名的第一部分）
        $grouped = $permissions->groupBy(function ($p) {
            return explode('.', $p->name)[0] ?? 'other';
        });

        return ApiResponse::success([
            'all' => $permissions,
            'grouped' => $grouped,
        ]);
    }

    // ─── 用户角色分配 ───

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
        $user->syncRoles($validated['roles']);

        return ApiResponse::success(
            $user->fresh()->load('roles'),
            '角色分配成功'
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
}
