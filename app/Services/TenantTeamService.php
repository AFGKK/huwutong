<?php

namespace App\Services;

use App\Mail\TeamInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as LogFacade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * M2-129 租户内团队协作服务
 *
 * 企业客户管理团队的核心服务，支持：
 * 1. 通过邮箱邀请新成员（带角色+附言）
 * 2. 接受/拒绝/取消邀请
 * 3. 成员管理（列表/角色变更/移除）
 * 4. 角色权限体系（管理员/财务/开发者/只读）
 * 5. 同一用户多租户切换支持
 * 6. 管理员权限转让
 */
class TenantTeamService
{
    /**
     * 有效的角色列表
     */
    const ROLES = ['admin', 'finance', 'developer', 'readonly'];

    /**
     * 邀请过期天数
     */
    const INVITATION_EXPIRY_DAYS = 7;

    /**
     * 审计日志服务实例
     */
    protected CustomerAuditLogService $auditLogService;

    public function __construct()
    {
        $this->auditLogService = app(CustomerAuditLogService::class);
    }

    /**
     * 邀请成员（通过邮箱）
     *
     * 发送邀请邮件给目标邮箱，包含接受链接。
     * 如果用户已注册，接受后自动加入租户；
     * 如果未注册，引导先注册再接受邀请。
     */
    public function inviteMember(
        Tenant $tenant,
        string $email,
        string $role,
        User $invitedBy,
        ?string $message = null,
    ): TenantInvitation {
        if (! in_array($role, self::ROLES)) {
            throw new \InvalidArgumentException("无效的角色: {$role}");
        }

        // 检查邮箱是否已是成员
        $existingMember = $tenant->members()
            ->whereHas('user', fn($q) => $q->where('email', $email))
            ->exists();

        if ($existingMember) {
            throw new \RuntimeException('该邮箱已是团队成员');
        }

        // 检查是否有待处理的邀请
        $pendingInvite = TenantInvitation::where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->where('status', 'pending')
            ->first();

        if ($pendingInvite) {
            throw new \RuntimeException('该邮箱已有待处理的邀请，请等待对方回复或取消旧邀请后重试');
        }

        return DB::transaction(function () use ($tenant, $email, $role, $invitedBy, $message) {
            $token = TenantInvitation::generateToken();
            $expiresAt = now()->addDays(self::INVITATION_EXPIRY_DAYS);

            $invitation = TenantInvitation::create([
                'tenant_id' => $tenant->id,
                'email' => $email,
                'role' => $role,
                'invited_by' => $invitedBy->id,
                'token' => $token,
                'expires_at' => $expiresAt,
                'status' => 'pending',
                'message' => $message,
            ]);

            // 发送邀请邮件
            try {
                $this->sendInvitationMail($invitation, $tenant, $invitedBy);
            } catch (\Throwable $e) {
                LogFacade::error('邀请邮件发送失败', [
                    'invitation_id' => $invitation->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
                // 邮件失败不阻断邀请创建
            }

            // 记录审计日志
            $this->auditLogService->log(
                action: 'team.member.invited',
                description: sprintf('邀请 %s 加入团队（角色: %s）', $email, $role),
                tenant: $tenant,
                user: $invitedBy,
                payload: [
                    'email' => $email,
                    'role' => $role,
                    'invitation_id' => $invitation->id,
                    'message' => $message,
                ],
            );

            return $invitation;
        });
    }

    /**
     * 批量邀请成员
     *
     * @return array ['success' => TenantInvitation[], 'failed' => array{email, reason}]
     */
    public function inviteMembers(
        Tenant $tenant,
        array $invites, // [{email, role, message?}]
        User $invitedBy,
    ): array {
        $success = [];
        $failed = [];

        foreach ($invites as $invite) {
            try {
                $result = $this->inviteMember(
                    tenant: $tenant,
                    email: $invite['email'],
                    role: $invite['role'] ?? 'member',
                    invitedBy: $invitedBy,
                    message: $invite['message'] ?? null,
                );
                $success[] = $result;
            } catch (\Throwable $e) {
                $failed[] = [
                    'email' => $invite['email'],
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return compact('success', 'failed');
    }

    /**
     * 接受邀请
     *
     * 通过 token 接受邀请，创建 TenantMember 记录。
     * 如果该邮箱的用户不存在，抛出异常（应该先注册）。
     */
    public function acceptInvitation(string $token, User $user): TenantMember
    {
        $invitation = TenantInvitation::findValid($token);

        if (! $invitation) {
            throw new \RuntimeException('邀请链接无效或已过期');
        }

        // 验证邮箱
        if (strtolower($invitation->email) !== strtolower($user->email)) {
            throw new \RuntimeException('邀请邮箱与当前账户邮箱不匹配');
        }

        return DB::transaction(function () use ($invitation, $user) {
            // 检查是否已是成员
            $existingMember = TenantMember::where('tenant_id', $invitation->tenant_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingMember) {
                // 已存在则直接更新角色
                $existingMember->update([
                    'role' => $invitation->role,
                    'status' => 'active',
                    'invited_via' => 'invitation',
                ]);
                $invitation->accept();

                return $existingMember;
            }

            // 创建成员记录
            $member = TenantMember::create([
                'tenant_id' => $invitation->tenant_id,
                'user_id' => $user->id,
                'role' => $invitation->role,
                'invited_by' => $invitation->invited_by,
                'invited_via' => 'invitation',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $invitation->accept();

            // 如果用户没有主租户，设置为该租户
            if (! $user->tenant_id) {
                $user->update(['tenant_id' => $invitation->tenant_id]);
            }

            // 记录审计日志
            $this->auditLogService->log(
                action: 'team.member.joined',
                description: sprintf('%s 通过邀请加入团队', $user->name ?? $user->email),
                tenant: $invitation->tenant,
                user: $user,
                payload: [
                    'invitation_id' => $invitation->id,
                    'role' => $invitation->role,
                    'email' => $user->email,
                ],
            );

            return $member;
        });
    }

    /**
     * 拒绝邀请
     */
    public function declineInvitation(string $token): bool
    {
        $invitation = TenantInvitation::findValid($token);

        if (! $invitation) {
            throw new \RuntimeException('邀请链接无效或已过期');
        }

        return $invitation->decline();
    }

    /**
     * 取消邀请
     */
    public function cancelInvitation(TenantInvitation $invitation): bool
    {
        return $invitation->cancel();
    }

    /**
     * 重新发送邀请
     */
    public function resendInvitation(TenantInvitation $invitation): TenantInvitation
    {
        if (! $invitation->isValid()) {
            throw new \RuntimeException('只能重新发送有效的邀请');
        }

        $invitation->update([
            'token' => TenantInvitation::generateToken(),
            'expires_at' => now()->addDays(self::INVITATION_EXPIRY_DAYS),
        ]);

        try {
            $this->sendInvitationMail(
                $invitation->fresh(),
                $invitation->tenant,
                $invitation->inviter,
            );
        } catch (\Throwable $e) {
            Log::error('重发邀请邮件失败', [
                'invitation_id' => $invitation->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $invitation->fresh();
    }

    /**
     * 获取租户成员列表（含角色）
     */
    public function getMembers(Tenant $tenant, array $options = []): array
    {
        $query = $tenant->members()
            ->with(['user:id,name,email,status,last_login_at', 'inviter:id,name,email']);

        if (! empty($options['role'])) {
            $query->where('role', $options['role']);
        }

        if (! empty($options['status'])) {
            $query->where('status', $options['status']);
        }

        $members = $query->orderBy('joined_at')->get();

        return [
            'members' => $members,
            'total' => $members->count(),
            'roles' => self::ROLES,
        ];
    }

    /**
     * 获取待处理邀请列表
     */
    public function getPendingInvitations(Tenant $tenant): array
    {
        $invitations = $tenant->pendingInvitations()
            ->with('inviter:id,name,email')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'invitations' => $invitations,
            'total' => $invitations->count(),
        ];
    }

    /**
     * 更新成员角色
     */
    public function updateMemberRole(TenantMember $member, string $newRole): TenantMember
    {
        if (! in_array($newRole, self::ROLES)) {
            throw new \InvalidArgumentException("无效的角色: {$newRole}");
        }

        // 检查是否试图降级最后一个管理员
        if ($member->isAdmin() && $newRole !== 'admin') {
            $adminCount = TenantMember::where('tenant_id', $member->tenant_id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $member->id)
                ->count();

            if ($adminCount === 0) {
                throw new \RuntimeException('至少需要保留一个管理员');
            }
        }

        // 更新成员角色
        $oldRole = $member->role;
        $member->update(['role' => $newRole]);

        // 记录审计日志
        $targetUser = $member->user;
        $this->auditLogService->log(
            action: 'team.member.role_changed',
            description: sprintf(
                '%s 的角色从 %s 变更为 %s',
                $targetUser?->name ?? $targetUser?->email ?? "成员 #{$member->id}",
                $oldRole,
                $newRole,
            ),
            tenant: $member->tenant,
            user: request()->user(), // 操作者
            payload: [
                'member_id' => $member->id,
                'target_user_id' => $member->user_id,
                'old_role' => $oldRole,
                'new_role' => $newRole,
            ],
        );

        return $member->fresh();
    }

    /**
     * 移除成员
     */
    public function removeMember(TenantMember $member, User $operator): void
    {
        // 不能移除自己（除非转让所有权）
        if ($member->user_id === $operator->id) {
            throw new \RuntimeException('不能移除自己，如需退出请联系其他管理员');
        }

        // 检查是否试图移除最后一个管理员
        if ($member->isAdmin()) {
            $adminCount = TenantMember::where('tenant_id', $member->tenant_id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $member->id)
                ->count();

            if ($adminCount === 0) {
                throw new \RuntimeException('至少需要保留一个管理员');
            }
        }

        $member->delete();

        // 如果被移除用户的 tenant_id 是该租户，清空
        $user = $member->user;
        if ($user && $user->tenant_id === $member->tenant_id) {
            // 如果用户有其他活跃租户，切换到第一个
            $otherTenant = TenantMember::where('user_id', $user->id)
                ->where('status', 'active')
                ->first();

            if ($otherTenant) {
                $user->update(['tenant_id' => $otherTenant->tenant_id]);
            } else {
                $user->update(['tenant_id' => null]);
            }
        }

        // 记录审计日志
        $this->auditLogService->log(
            action: 'team.member.removed',
            description: sprintf('移除成员 %s', $user?->name ?? $user?->email ?? "成员 #{$member->id}"),
            tenant: $member->tenant,
            user: $operator,
            payload: [
                'member_id' => $member->id,
                'removed_user_id' => $member->user_id,
                'removed_user_email' => $user?->email,
                'previous_role' => $member->role,
            ],
        );
    }

    /**
     * 管理员权限转让
     */
    public function transferAdmin(TenantMember $fromMember, TenantMember $toMember): void
    {
        if (! $fromMember->isAdmin()) {
            throw new \RuntimeException('只有管理员可以转让权限');
        }

        if ($fromMember->tenant_id !== $toMember->tenant_id) {
            throw new \RuntimeException('目标成员不属于同一租户');
        }

        DB::transaction(function () use ($fromMember, $toMember) {
            $fromMember->update(['role' => 'admin']); // 保持为管理员
            $toMember->update(['role' => 'admin']);
        });

        // 记录审计日志
        $fromUser = $fromMember->user;
        $toUser = $toMember->user;
        $this->auditLogService->log(
            action: 'team.admin_transferred',
            description: sprintf(
                '管理员权限从 %s 转让给 %s',
                $fromUser?->name ?? $fromUser?->email ?? "成员 #{$fromMember->id}",
                $toUser?->name ?? $toUser?->email ?? "成员 #{$toMember->id}",
            ),
            tenant: $fromMember->tenant,
            user: $fromUser, // 转让者是发起方
            payload: [
                'from_member_id' => $fromMember->id,
                'to_member_id' => $toMember->id,
                'from_user_id' => $fromMember->user_id,
                'to_user_id' => $toMember->user_id,
            ],
        );
    }

    /**
     * 退出租户（用户自行退出）
     */
    public function leaveTenant(Tenant $tenant, User $user): void
    {
        $member = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $member) {
            throw new \RuntimeException('您不是该租户的成员');
        }

        // 最后一个管理员不能退出
        if ($member->isAdmin()) {
            $adminCount = TenantMember::where('tenant_id', $tenant->id)
                ->where('role', 'admin')
                ->where('status', 'active')
                ->where('id', '!=', $member->id)
                ->count();

            if ($adminCount === 0) {
                throw new \RuntimeException('您是最后一个管理员，请先转让管理员权限再退出');
            }
        }

        $member->delete();

        // 清除 session 中的租户信息
        if ($user->remember_tenant_id === $tenant->id) {
            $user->update(['remember_tenant_id' => null]);
        }

        // 记录审计日志
        $this->auditLogService->log(
            action: 'team.member.left',
            description: sprintf('%s 退出团队', $user->name ?? $user->email),
            tenant: $tenant,
            user: $user,
            payload: [
                'member_id' => $member->id,
                'previous_role' => $member->role,
            ],
        );
    }

    /**
     * 获取当前用户在指定租户的角色
     */
    public function getUserRole(Tenant $tenant, User $user): ?string
    {
        $member = TenantMember::where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        return $member?->role;
    }

    /**
     * 检查用户是否有指定角色
     */
    public function userHasRole(Tenant $tenant, User $user, string|array $roles): bool
    {
        $role = $this->getUserRole($tenant, $user);
        return $role !== null && in_array($role, (array) $roles);
    }

    /**
     * 发送邀请邮件
     */
    protected function sendInvitationMail(TenantInvitation $invitation, Tenant $tenant, User $inviter): void
    {
        $acceptUrl = config('app.frontend_url') . '/accept-invitation?token=' . $invitation->token;
        $declineUrl = config('app.frontend_url') . '/decline-invitation?token=' . $invitation->token;

        Mail::to($invitation->email)->queue(new TeamInvitationMail(
            invitation: $invitation,
            tenant: $tenant,
            inviterName: $inviter->name,
            acceptUrl: $acceptUrl,
            declineUrl: $declineUrl,
        ));
    }

    /**
     * 清理过期邀请
     *
     * @return int 清理数量
     */
    public function cleanupExpiredInvitations(): int
    {
        $count = TenantInvitation::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        return $count;
    }
}
