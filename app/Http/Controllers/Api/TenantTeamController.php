<?php

namespace App\Http\Controllers\Api;

use App\Http\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMember;
use App\Services\TenantTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * M2-129 租户内团队协作 API
 *
 * 企业客户管理团队的端点：
 * - 通过邮箱邀请新成员
 * - 接受/拒绝邀请
 * - 成员列表/角色变更/移除
 * - 管理员权限转让
 * - 退出团队
 */
class TenantTeamController extends Controller
{
    public function __construct(
        protected TenantTeamService $teamService,
    ) {}

    /**
     * 获取团队信息概览
     * GET /api/team
     */
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $members = $this->teamService->getMembers($tenant);
        $pendingInvitations = $this->teamService->getPendingInvitations($tenant);
        $userRole = $this->teamService->getUserRole($tenant, $user);

        return ApiResponse::success([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo' => $tenant->logo,
                'max_users' => $tenant->max_users,
            ],
            'user_role' => $userRole,
            'members' => $members['members'],
            'member_count' => $members['total'],
            'pending_invitations' => $pendingInvitations['invitations'],
            'pending_invitation_count' => $pendingInvitations['total'],
            'roles' => $members['roles'],
        ]);
    }

    /**
     * 获取成员列表
     * GET /api/team/members
     */
    public function members(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $result = $this->teamService->getMembers($tenant, $request->only(['role', 'status']));

        return ApiResponse::success($result);
    }

    /**
     * 邀请新成员
     * POST /api/team/invite
     */
    public function invite(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $validator = Validator::make($request->all(), [
            'email' => 'required_without:invites|email|max:255',
            'role' => 'required_without:invites|string|in:' . implode(',', TenantTeamService::ROLES),
            'message' => 'nullable|string|max:500',
            'invites' => 'required_without:email|array|max:50',
            'invites.*.email' => 'required|email|max:255',
            'invites.*.role' => 'required|string|in:' . implode(',', TenantTeamService::ROLES),
            'invites.*.message' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $data = $validator->validated();

        // 检查权限：只有 admin/finance 可以邀请成员
        $userRole = $this->teamService->getUserRole($tenant, $user);
        if (! in_array($userRole, ['admin', 'finance'])) {
            return ApiResponse::forbidden('您没有邀请成员的权限');
        }

        try {
            if (! empty($data['invites'])) {
                // 批量邀请
                $result = $this->teamService->inviteMembers($tenant, $data['invites'], $user);
                return ApiResponse::success($result, '邀请已发送');
            }

            // 单个邀请
            $invitation = $this->teamService->inviteMember(
                tenant: $tenant,
                email: $data['email'],
                role: $data['role'],
                invitedBy: $user,
                message: $data['message'] ?? null,
            );

            return ApiResponse::created(
                $invitation->load('inviter:id,name,email'),
                '邀请已发送'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('INVITE_FAILED', $e->getMessage(), 409);
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error('INVALID_INPUT', $e->getMessage(), 422);
        }
    }

    /**
     * 接受邀请（公开端点，需登录）
     * POST /api/team/invitations/accept
     */
    public function acceptInvitation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $user = $request->user();

        try {
            $member = $this->teamService->acceptInvitation($request->input('token'), $user);
            $member->load(['tenant:id,name,slug,logo', 'user:id,name,email']);

            return ApiResponse::success($member, '已加入团队');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ACCEPT_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 拒绝邀请（公开端点，无需登录）
     * POST /api/team/invitations/decline
     */
    public function declineInvitation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:64',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $this->teamService->declineInvitation($request->input('token'));
            return ApiResponse::success(null, '已拒绝邀请');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('DECLINE_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 取消邀请（邀请人/管理员）
     * POST /api/team/invitations/{invitation}/cancel
     */
    public function cancelInvitation(Request $request, TenantInvitation $invitation): JsonResponse
    {
        $user = $request->user();

        // 检查权限
        $tenant = $invitation->tenant;
        $userRole = $this->teamService->getUserRole($tenant, $user);

        if ($user->id !== $invitation->invited_by && $userRole !== 'admin') {
            return ApiResponse::forbidden('您没有取消此邀请的权限');
        }

        try {
            $this->teamService->cancelInvitation($invitation);
            return ApiResponse::success(null, '邀请已取消');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('CANCEL_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 重新发送邀请
     * POST /api/team/invitations/{invitation}/resend
     */
    public function resendInvitation(Request $request, TenantInvitation $invitation): JsonResponse
    {
        $user = $request->user();
        $tenant = $invitation->tenant;
        $userRole = $this->teamService->getUserRole($tenant, $user);

        if (! in_array($userRole, ['admin', 'finance'])) {
            return ApiResponse::forbidden('您没有重新发送邀请的权限');
        }

        try {
            $updated = $this->teamService->resendInvitation($invitation);
            return ApiResponse::success(
                $updated->load('inviter:id,name,email'),
                '邀请已重新发送'
            );
        } catch (\RuntimeException $e) {
            return ApiResponse::error('RESEND_FAILED', $e->getMessage(), 400);
        }
    }

    /**
     * 获取待处理邀请列表
     * GET /api/team/invitations/pending
     */
    public function pendingInvitations(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $result = $this->teamService->getPendingInvitations($tenant);

        return ApiResponse::success($result);
    }

    /**
     * 更新成员角色
     * PUT /api/team/members/{member}/role
     */
    public function updateMemberRole(Request $request, TenantMember $member): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        if ($member->tenant_id !== $tenant->id) {
            return ApiResponse::notFound('成员不属于此租户');
        }

        $userRole = $this->teamService->getUserRole($tenant, $user);
        if ($userRole !== 'admin') {
            return ApiResponse::forbidden('只有管理员可以修改成员角色');
        }

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:' . implode(',', TenantTeamService::ROLES),
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        try {
            $updated = $this->teamService->updateMemberRole($member, $request->input('role'));
            return ApiResponse::success($updated, '成员角色已更新');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('ROLE_UPDATE_FAILED', $e->getMessage(), 409);
        }
    }

    /**
     * 移除成员
     * DELETE /api/team/members/{member}
     */
    public function removeMember(Request $request, TenantMember $member): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        if ($member->tenant_id !== $tenant->id) {
            return ApiResponse::notFound('成员不属于此租户');
        }

        $userRole = $this->teamService->getUserRole($tenant, $user);
        if ($userRole !== 'admin') {
            return ApiResponse::forbidden('只有管理员可以移除成员');
        }

        try {
            $this->teamService->removeMember($member, $user);
            return ApiResponse::success(null, '成员已移除');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('REMOVE_FAILED', $e->getMessage(), 409);
        }
    }

    /**
     * 管理员转让
     * POST /api/team/transfer-admin
     */
    public function transferAdmin(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        $validator = Validator::make($request->all(), [
            'target_member_id' => 'required|integer|exists:tenant_members,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::error('VALIDATION_ERROR', $validator->errors()->first(), 422);
        }

        $fromMember = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $fromMember || ! $fromMember->isAdmin()) {
            return ApiResponse::forbidden('只有管理员可以转让权限');
        }

        $toMember = TenantMember::find($request->input('target_member_id'));

        try {
            $this->teamService->transferAdmin($fromMember, $toMember);
            return ApiResponse::success(null, '管理员权限已转让');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('TRANSFER_FAILED', $e->getMessage(), 409);
        }
    }

    /**
     * 退出团队
     * POST /api/team/leave
     */
    public function leave(Request $request): JsonResponse
    {
        $user = $request->user();
        $tenant = $this->resolveTenant($request, $user);

        try {
            $this->teamService->leaveTenant($tenant, $user);
            return ApiResponse::success(null, '已退出团队');
        } catch (\RuntimeException $e) {
            return ApiResponse::error('LEAVE_FAILED', $e->getMessage(), 409);
        }
    }

    /**
     * 解析当前活跃租户
     *
     * 优先从请求头 X-Tenant-Id 获取，否则使用用户的 remember_tenant_id。
     */
    protected function resolveTenant(Request $request, \App\Models\User $user): Tenant
    {
        $tenantId = $request->header('X-Tenant-Id')
            ?? $user->remember_tenant_id
            ?? $user->tenant_id;

        if (! $tenantId) {
            throw new \RuntimeException('未选择租户');
        }

        $tenant = Tenant::find($tenantId);
        if (! $tenant) {
            throw new \RuntimeException('租户不存在');
        }

        // 验证用户是否是该租户的成员
        $isMember = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        if (! $isMember && ! $user->hasRole('super-admin')) {
            throw new \RuntimeException('您无权访问该租户');
        }

        return $tenant;
    }
}
