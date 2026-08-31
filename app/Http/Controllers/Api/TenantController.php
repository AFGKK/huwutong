<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TenantController extends Controller
{
    /**
     * 租户列表（管理后台）
     */
    public function index(Request $request): JsonResponse
    {
        $query = Tenant::withCount('users', 'customers', 'licenses');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('domain', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('filter.status')) {
            $query->where('status', $status);
        }

        $sortField = $request->input('sort_field', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        if (in_array($sortField, ['id', 'name', 'status', 'created_at', 'users_count'])) {
            $query->orderBy($sortField, $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);

        return ApiResponse::paginated($query->paginate($perPage));
    }

    /**
     * 租户详情
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadCount('users', 'customers', 'licenses', 'devices', 'invoices');
        $tenant->load('members.user:id,name,email');

        return ApiResponse::success($tenant);
    }

    /**
     * 创建租户
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:100|unique:tenants,slug',
            'domain' => 'nullable|string|max:255|unique:tenants,domain',
            'logo' => 'nullable|string|max:500',
            'status' => 'nullable|string|in:active,inactive,suspended',
            'data_region' => 'nullable|string|max:50',
            'subscription_plan' => 'nullable|string|max:100',
            'branding' => 'nullable|json',
            'mfa_policy' => 'nullable|string|in:optional,required,disabled',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? str($data['name'])->slug(),
            'domain' => $data['domain'] ?? null,
            'logo' => $data['logo'] ?? null,
            'status' => $data['status'] ?? 'active',
            'data_region' => $data['data_region'] ?? null,
            'subscription_plan' => $data['subscription_plan'] ?? null,
            'branding' => $data['branding'] ?? null,
            'mfa_policy' => $data['mfa_policy'] ?? 'optional',
            'allowed_ips' => $data['allowed_ips'] ?? null,
        ]);

        return ApiResponse::created($tenant, __('app.api.tenant.created'));
    }

    /**
     * 更新租户
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'slug' => 'nullable|string|max:100|unique:tenants,slug,' . $tenant->id,
            'domain' => 'nullable|string|max:255|unique:tenants,domain,' . $tenant->id,
            'logo' => 'nullable|string|max:500',
            'status' => 'sometimes|string|in:active,inactive,suspended',
            'data_region' => 'nullable|string|max:50',
            'subscription_plan' => 'nullable|string|max:100',
            'branding' => 'nullable|json',
            'mfa_policy' => 'sometimes|string|in:optional,required,disabled',
            'allowed_ips' => 'nullable|array',
            'allowed_ips.*' => 'string',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $tenant->update($validator->validated());

        return ApiResponse::success($tenant->fresh(), __('app.api.tenant.updated'));
    }

    /**
     * 删除租户
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        // 安全：不允许删除有关联数据的租户（有用户或客户）
        if ($tenant->users()->count() > 0) {
            return ApiResponse::error('FORBIDDEN', __('app.api.tenant.has_users'), 409);
        }
        if ($tenant->customers()->count() > 0) {
            return ApiResponse::error('FORBIDDEN', __('app.api.tenant.has_customers'), 409);
        }

        $tenant->delete();

        return ApiResponse::success(null, __('app.api.tenant.deleted'));
    }

    /**
     * 切换租户状态
     */
    public function toggleStatus(Tenant $tenant): JsonResponse
    {
        $newStatus = $tenant->status === 'active' ? 'inactive' : 'active';
        $tenant->status = $newStatus;
        $tenant->save();

        return ApiResponse::success(['status' => $newStatus], $newStatus === 'active' ? __('app.api.tenant.enabled') : __('app.api.tenant.disabled'));
    }

    /**
     * 租户成员列表
     */
    public function members(Tenant $tenant): JsonResponse
    {
        $members = $tenant->members()
            ->with('user:id,name,email,status')
            ->orderBy('created_at')
            ->get();

        return ApiResponse::success($members);
    }

    /**
     * 添加租户成员
     */
    public function addMember(Request $request, Tenant $tenant): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'nullable|string|in:admin,member,viewer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        // 检查是否已存在
        $existing = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $data['user_id'])
            ->first();

        if ($existing) {
            return ApiResponse::error('CONFLICT', __('app.api.tenant.already_member'), 409);
        }

        $member = TenantMember::create([
            'tenant_id' => $tenant->id,
            'user_id' => $data['user_id'],
            'role' => $data['role'] ?? 'member',
            'invited_by' => $request->user()->id,
            'status' => 'active',
        ]);

        $member->load('user:id,name,email');

        return ApiResponse::created($member, __('app.api.tenant.member_added'));
    }

    /**
     * 移除租户成员
     */
    public function removeMember(Tenant $tenant, TenantMember $member): JsonResponse
    {
        if ($member->tenant_id !== $tenant->id) {
            return ApiResponse::error('NOT_FOUND', __('app.api.tenant.member_not_found'), 404);
        }

        $member->delete();

        return ApiResponse::success(null, __('app.api.tenant.member_removed'));
    }

    /**
     * 更新成员角色
     */
    public function updateMemberRole(Request $request, Tenant $tenant, TenantMember $member): JsonResponse
    {
        if ($member->tenant_id !== $tenant->id) {
            return ApiResponse::error('NOT_FOUND', __('app.api.tenant.member_not_found'), 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,member,viewer',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $member->role = $validator->validated()['role'];
        $member->save();

        return ApiResponse::success($member->fresh(), __('app.api.tenant.member_role_updated'));
    }

    /**
     * 租户统计
     */
    public function stats(): JsonResponse
    {
        $total = Tenant::count();
        $active = Tenant::where('status', 'active')->count();
        $inactive = Tenant::where('status', 'inactive')->count();
        $suspended = Tenant::where('status', 'suspended')->count();

        $totalUsers = User::count();
        $avgUsersPerTenant = $total > 0 ? round($totalUsers / $total, 1) : 0;

        return ApiResponse::success([
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'suspended' => $suspended,
            'total_users' => $totalUsers,
            'avg_users_per_tenant' => $avgUsersPerTenant,
        ]);
    }
}
