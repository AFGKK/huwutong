<?php

namespace Tests\Feature\Api;

use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AffiliateAgentReviewTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $applicant;
    private User $adminUser;
    private string $applicantToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->applicant = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $this->adminUser->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->adminUser->load('roles');

        $this->applicantToken = $this->applicant->createToken('applicant', ['*'])->plainTextToken;
        $this->adminToken = $this->adminUser->createToken('admin', ['*'])->plainTextToken;
    }

    private function applicantHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->applicantToken];
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    /** @test */
    public function user_can_apply_to_become_agent(): void
    {
        $response = $this->postJson('/api/store-affiliate/apply-agent', [], $this->applicantHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('agents', [
            'user_id' => $this->applicant->id,
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function duplicate_pending_application_is_rejected(): void
    {
        Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFPENDING1',
            'level' => 'basic',
            'status' => 'pending',
            'commission_rate' => 10,
        ]);

        $this->postJson('/api/store-affiliate/apply-agent', [], $this->applicantHeaders())
            ->assertStatus(400);
    }

    /** @test */
    public function rejected_user_can_reapply(): void
    {
        Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFREJECT1',
            'level' => 'basic',
            'status' => 'rejected',
            'notes' => '资料不全',
            'commission_rate' => 10,
        ]);

        $this->postJson('/api/store-affiliate/apply-agent', [], $this->applicantHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('agents', [
            'user_id' => $this->applicant->id,
            'status' => 'pending',
            'notes' => null,
        ]);
    }

    /** @test */
    public function admin_can_list_pending_agents(): void
    {
        Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFLIST01',
            'level' => 'basic',
            'status' => 'pending',
            'commission_rate' => 10,
        ]);

        $response = $this->getJson('/api/store-affiliate/pending-agents', $this->adminHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function non_admin_cannot_list_pending_agents(): void
    {
        $this->getJson('/api/store-affiliate/pending-agents', $this->applicantHeaders())
            ->assertStatus(403);
    }

    /** @test */
    public function admin_can_approve_pending_agent(): void
    {
        $agent = Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFAPPR01',
            'level' => 'basic',
            'status' => 'pending',
            'commission_rate' => 10,
        ]);

        $this->postJson("/api/store-affiliate/agents/{$agent->id}/review", [
            'action' => 'approved',
        ], $this->adminHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'active');

        $agent->refresh();
        $this->assertEquals('active', $agent->status);
        $this->assertNotNull($agent->approved_at);
    }

    /** @test */
    public function admin_can_reject_pending_agent(): void
    {
        $agent = Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFREJ01',
            'level' => 'basic',
            'status' => 'pending',
            'commission_rate' => 10,
        ]);

        $this->postJson("/api/store-affiliate/agents/{$agent->id}/review", [
            'action' => 'rejected',
            'notes' => '不符合条件',
        ], $this->adminHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'status' => 'rejected',
            'notes' => '不符合条件',
        ]);
    }

    /** @test */
    public function cannot_review_agent_that_is_not_pending(): void
    {
        $agent = Agent::create([
            'user_id' => $this->applicant->id,
            'agent_code' => 'AFACTIVE',
            'level' => 'basic',
            'status' => 'active',
            'commission_rate' => 10,
            'approved_at' => now(),
        ]);

        $this->postJson("/api/store-affiliate/agents/{$agent->id}/review", [
            'action' => 'approved',
        ], $this->adminHeaders())
            ->assertStatus(422);
    }
}
