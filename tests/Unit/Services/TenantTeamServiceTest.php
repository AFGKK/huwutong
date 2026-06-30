<?php

namespace Tests\Unit\Services;

use App\Mail\TeamInvitationMail;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\TenantMember;
use App\Models\User;
use App\Services\TenantTeamService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TenantTeamServiceTest extends TestCase
{
    use RefreshDatabase;

    protected TenantTeamService $service;
    protected Tenant $tenant;
    protected User $admin;
    protected User $member;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(TenantTeamService::class);

        $this->tenant = Tenant::factory()->create([
            'name' => '测试企业',
            'max_users' => 100,
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@test.com',
        ]);

        $this->member = User::factory()->create([
            'email' => 'member@test.com',
        ]);

        // 将 admin 添加为租户管理员
        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'role' => 'admin',
            'invited_by' => $this->admin->id,
            'invited_via' => 'direct_add',
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_invite_a_new_member()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'newmember@test.com',
            role: 'developer',
            invitedBy: $this->admin,
            message: '欢迎加入团队！',
        );

        $this->assertDatabaseHas('tenant_invitations', [
            'tenant_id' => $this->tenant->id,
            'email' => 'newmember@test.com',
            'role' => 'developer',
            'status' => 'pending',
        ]);

        $this->assertNotNull($invitation->token);
        $this->assertTrue($invitation->isValid());

        Mail::assertQueued(TeamInvitationMail::class, function ($mail) {
            return $mail->hasTo('newmember@test.com')
                && $mail->invitation->role === 'developer';
        });
    }

    /** @test */
    public function it_prevents_inviting_existing_member()
    {
        Mail::fake();

        // admin is already a member, try to invite their email
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('该邮箱已是团队成员');

        $this->service->inviteMember(
            tenant: $this->tenant,
            email: $this->admin->email,
            role: 'developer',
            invitedBy: $this->admin,
        );
    }

    /** @test */
    public function it_prevents_duplicate_pending_invitations()
    {
        Mail::fake();

        $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'pending@test.com',
            role: 'developer',
            invitedBy: $this->admin,
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('已有待处理的邀请');

        $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'pending@test.com',
            role: 'admin',
            invitedBy: $this->admin,
        );
    }

    /** @test */
    public function it_throws_on_invalid_role()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'test@test.com',
            role: 'invalid_role',
            invitedBy: $this->admin,
        );
    }

    /** @test */
    public function it_can_accept_invitation()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: $this->member->email,
            role: 'developer',
            invitedBy: $this->admin,
        );

        $member = $this->service->acceptInvitation($invitation->token, $this->member);

        $this->assertDatabaseHas('tenant_members', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->member->id,
            'role' => 'developer',
            'status' => 'active',
            'invited_via' => 'invitation',
        ]);

        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'accepted',
        ]);

        $this->assertEquals('developer', $member->role);
    }

    /** @test */
    public function it_rejects_invitation_with_wrong_email()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'correct@test.com',
            role: 'developer',
            invitedBy: $this->admin,
        );

        $wrongUser = User::factory()->create(['email' => 'wrong@test.com']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('邀请邮箱与当前账户邮箱不匹配');

        $this->service->acceptInvitation($invitation->token, $wrongUser);
    }

    /** @test */
    public function it_can_decline_invitation()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'decline@test.com',
            role: 'readonly',
            invitedBy: $this->admin,
        );

        $result = $this->service->declineInvitation($invitation->token);

        $this->assertTrue($result);
        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'declined',
        ]);
    }

    /** @test */
    public function it_can_cancel_invitation()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'cancel@test.com',
            role: 'developer',
            invitedBy: $this->admin,
        );

        $result = $this->service->cancelInvitation($invitation);

        $this->assertTrue($result);
        $this->assertDatabaseHas('tenant_invitations', [
            'id' => $invitation->id,
            'status' => 'cancelled',
        ]);
    }

    /** @test */
    public function it_can_update_member_role()
    {
        // Add a member
        $member = TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->member->id,
            'role' => 'developer',
            'invited_by' => $this->admin->id,
            'invited_via' => 'invitation',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $updated = $this->service->updateMemberRole($member, 'finance');

        $this->assertEquals('finance', $updated->role);
        $this->assertDatabaseHas('tenant_members', [
            'id' => $member->id,
            'role' => 'finance',
        ]);
    }

    /** @test */
    public function it_prevents_removing_last_admin()
    {
        // Only one admin (admin user), try to change role
        $adminMember = TenantMember::where('tenant_id', $this->tenant->id)
            ->where('user_id', $this->admin->id)
            ->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('至少需要保留一个管理员');

        $this->service->updateMemberRole($adminMember, 'developer');
    }

    /** @test */
    public function it_can_remove_member()
    {
        $member = TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->member->id,
            'role' => 'developer',
            'invited_by' => $this->admin->id,
            'invited_via' => 'invitation',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->service->removeMember($member, $this->admin);

        $this->assertDatabaseMissing('tenant_members', ['id' => $member->id]);
    }

    /** @test */
    public function it_can_get_members_list()
    {
        $result = $this->service->getMembers($this->tenant);

        $this->assertCount(1, $result['members']); // only admin
        $this->assertEquals('admin', $result['members']->first()->role);
        $this->assertEquals(TenantTeamService::ROLES, $result['roles']);
    }

    /** @test */
    public function it_can_get_user_role()
    {
        $role = $this->service->getUserRole($this->tenant, $this->admin);
        $this->assertEquals('admin', $role);

        $role = $this->service->getUserRole($this->tenant, $this->member);
        $this->assertNull($role); // not a member yet
    }

    /** @test */
    public function it_can_check_user_role()
    {
        $this->assertTrue(
            $this->service->userHasRole($this->tenant, $this->admin, 'admin')
        );
        $this->assertFalse(
            $this->service->userHasRole($this->tenant, $this->admin, 'developer')
        );
    }

    /** @test */
    public function it_can_leave_tenant()
    {
        // Add a second admin so the first can leave
        $secondAdmin = User::factory()->create(['email' => 'admin2@test.com']);
        TenantMember::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $secondAdmin->id,
            'role' => 'admin',
            'invited_by' => $this->admin->id,
            'invited_via' => 'direct_add',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->service->leaveTenant($this->tenant, $this->admin);

        $this->assertDatabaseMissing('tenant_members', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
        ]);
    }

    /** @test */
    public function it_prevents_last_admin_from_leaving()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('最后一个管理员');

        $this->service->leaveTenant($this->tenant, $this->admin);
    }

    /** @test */
    public function it_can_cleanup_expired_invitations()
    {
        // Create expired invitation
        TenantInvitation::create([
            'tenant_id' => $this->tenant->id,
            'email' => 'expired@test.com',
            'role' => 'developer',
            'invited_by' => $this->admin->id,
            'token' => TenantInvitation::generateToken(),
            'expires_at' => now()->subDay(),
            'status' => 'pending',
        ]);

        $count = $this->service->cleanupExpiredInvitations();

        $this->assertEquals(1, $count);
        $this->assertDatabaseHas('tenant_invitations', [
            'email' => 'expired@test.com',
            'status' => 'expired',
        ]);
    }

    /** @test */
    public function it_can_batch_invite_members()
    {
        Mail::fake();

        $invites = [
            ['email' => 'batch1@test.com', 'role' => 'developer'],
            ['email' => 'batch2@test.com', 'role' => 'finance'],
            ['email' => 'batch3@test.com', 'role' => 'readonly'],
        ];

        $result = $this->service->inviteMembers($this->tenant, $invites, $this->admin);

        $this->assertCount(3, $result['success']);
        $this->assertCount(0, $result['failed']);

        Mail::assertQueued(TeamInvitationMail::class, 3);
    }

    /** @test */
    public function it_resends_invitation()
    {
        Mail::fake();

        $invitation = $this->service->inviteMember(
            tenant: $this->tenant,
            email: 'resend@test.com',
            role: 'developer',
            invitedBy: $this->admin,
        );

        $oldToken = $invitation->token;

        $updated = $this->service->resendInvitation($invitation);

        $this->assertNotEquals($oldToken, $updated->token);
        $this->assertTrue($updated->isValid());

        Mail::assertQueued(TeamInvitationMail::class, 2); // 1 original + 1 resend
    }
}
