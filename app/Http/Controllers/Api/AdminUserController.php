<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    /**
     * 用户列表
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::with('tenant:id,name', 'roles:id,name');

        // �?super-admin 只能看到本租户用�?
        if (!$request->user()->hasRole('super-admin')) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        // 搜索
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // 筛�?
        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }
        if ($role = $request->input('filter.role')) {
            $query->whereHas('roles', fn($q) => $q->where('name', $role));
        }
        if ($tenantId = $request->input('filter.tenant_id')) {
            if ($request->user()->hasRole('super-admin')) {
                $query->where('tenant_id', $tenantId);
            }
        }

        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'name', 'email', 'status', 'created_at', 'last_login_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 用户详情
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $user->load('tenant:id,name', 'roles:id,name', 'permissions:id,name');

        return ApiResponse::success($user);
    }

    /**
     * 创建用户
     */
    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', User::class);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|max:128',
            'status' => 'nullable|string|in:active,inactive,locked',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
            'tenant_id' => 'nullable|integer|exists:tenants,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        // �?super-admin 不能指定 tenant_id，只能创建到自己的租�?
        if (!$request->user()->hasRole('super-admin')) {
            $data['tenant_id'] = $request->user()->tenant_id;
        }

        $data['password'] = Hash::make($data['password']);

        /** @var User $user */
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'status' => $data['status'] ?? 'active',
            'tenant_id' => $data['tenant_id'] ?? $request->user()->tenant_id,
        ]);

        // 分配角色
        if (!empty($data['roles'])) {
            $roles = Role::whereIn('id', $data['roles'])->get();
            $user->syncRoles($roles);
        }

        $user->load('roles:id,name');

        return ApiResponse::created($user, '用户创建成功');
    }

    /**
     * 更新用户
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $rules = [
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:20',
            'status' => 'sometimes|string|in:active,inactive,locked',
            'roles' => 'nullable|array',
            'roles.*' => 'integer|exists:roles,id',
        ];

        // 只有 super-admin 可以修改 email �?tenant_id
        if ($request->user()->hasRole('super-admin')) {
            $rules['email'] = 'sometimes|email|max:255|unique:users,email,' . $user->id;
            $rules['tenant_id'] = 'nullable|integer|exists:tenants,id';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        // �?super-admin 不能修改敏感字段
        if (!$request->user()->hasRole('super-admin')) {
            unset($data['email'], $data['tenant_id']);
        }

        $user->update($data);

        // 同步角色
        if (isset($data['roles'])) {
            $roles = Role::whereIn('id', $data['roles'])->get();
            $user->syncRoles($roles);
        }

        $user->load('roles:id,name');

        return ApiResponse::success($user, '用户更新成功');
    }

    /**
     * 删除用户（软删除�?
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $user->status = 'inactive';
        $user->save();
        $user->delete(); // soft delete

        return ApiResponse::success(null, '用户已停用');
    }

    /**
     * 重置用户密码
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('resetPassword', $user);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|max:128|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $user->password = Hash::make($validator->validated()['password']);
        $user->save();

        return ApiResponse::success(null, '密码重置成功');
    }

    /**
     * 启用/禁用用户
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        $this->authorize('toggleStatus', $user);

        if ($user->status === 'active') {
            // 封禁：需要原因
            $reason = $request->input('reason', '管理员手动禁用');
            try {
                $user = app(\App\Services\AccountAppealService::class)->banUser(
                    $user->id,
                    $request->user()->id,
                    $reason,
                );
            } catch (\RuntimeException $e) {
                return ApiResponse::error('BAN_FAILED', $e->getMessage(), 422);
            }
            return ApiResponse::success(['status' => 'inactive'], '用户已封禁');
        } else {
            // 解封
            try {
                $user = app(\App\Services\AccountAppealService::class)->unbanUser(
                    $user->id,
                    $request->user()->id,
                );
            } catch (\RuntimeException $e) {
                return ApiResponse::error('UNBAN_FAILED', $e->getMessage(), 422);
            }
            return ApiResponse::success(['status' => 'active'], '用户已解封');
        }
    }

    /**
     * 用户统计
     */
    public function stats(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        if (!$request->user()->hasRole('super-admin')) {
            $query->where('tenant_id', $request->user()->tenant_id);
        }

        $total = $query->count();
        $active = (clone $query)->where('status', 'active')->count();
        $inactive = (clone $query)->where('status', 'inactive')->count();
        $locked = (clone $query)->where('status', 'locked')->count();
        $recentLogins = (clone $query)->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDays(7))
            ->count();

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'locked' => $locked,
            'recent_logins' => $recentLogins,
        ]);
    }
}

