<?php

namespace Tests\Feature\Api;

use App\Models\AffiliateCampaign;
use App\Models\AffiliateCreative;
use App\Models\Agent;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AffiliateCreativeSubmitTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $agentUser;
    private User $adminUser;
    private string $agentToken;
    private string $adminToken;
    private AffiliateCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->agentUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->adminUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Agent::create([
            'user_id' => $this->agentUser->id,
            'agent_code' => 'AFTEST001',
            'level' => 'basic',
            'status' => 'active',
            'commission_rate' => 10,
        ]);

        $role = Role::findOrCreate('super-admin', 'web');
        \DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => $role->id, 'model_type' => User::class, 'model_id' => $this->adminUser->id],
            ['tenant_id' => $this->tenant->id]
        );
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->adminUser->load('roles');

        $this->campaign = AffiliateCampaign::factory()->create([
            'status' => AffiliateCampaign::STATUS_ACTIVE,
            'created_by' => $this->adminUser->id,
        ]);

        $this->agentToken = $this->agentUser->createToken('agent', ['*'])->plainTextToken;
        $this->adminToken = $this->adminUser->createToken('admin', ['*'])->plainTextToken;
    }

    private function agentHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->agentToken];
    }

    private function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    /** @test */
    public function active_agent_can_submit_creative_for_review(): void
    {
        $response = $this->postJson('/api/store-affiliate/creatives/submit', [
            'campaign_id' => $this->campaign->id,
            'type' => 'banner',
            'name' => '春季推广横幅',
            'url' => 'https://example.com/promo',
            'content' => '限时优惠',
            'image_url' => 'https://example.com/banner.png',
        ], $this->agentHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('affiliate_creatives', [
            'campaign_id' => $this->campaign->id,
            'created_by' => $this->agentUser->id,
            'status' => 'pending',
            'name' => '春季推广横幅',
        ]);
    }

    /** @test */
    public function non_agent_cannot_submit_creative(): void
    {
        $guest = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $token = $guest->createToken('guest', ['*'])->plainTextToken;

        $this->postJson('/api/store-affiliate/creatives/submit', [
            'campaign_id' => $this->campaign->id,
            'type' => 'text',
            'name' => '测试文案',
        ], ['Authorization' => 'Bearer ' . $token])
            ->assertStatus(403);
    }

    /** @test */
    public function agent_can_list_own_submitted_creatives(): void
    {
        AffiliateCreative::create([
            'campaign_id' => $this->campaign->id,
            'type' => 'text',
            'name' => '我的文案',
            'status' => 'pending',
            'created_by' => $this->agentUser->id,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/store-affiliate/my-creatives', $this->agentHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    /** @test */
    public function admin_can_approve_creative_and_activate_it(): void
    {
        $creative = AffiliateCreative::create([
            'campaign_id' => $this->campaign->id,
            'type' => 'image',
            'name' => '待审图片',
            'status' => 'pending',
            'created_by' => $this->agentUser->id,
            'is_active' => false,
        ]);

        $this->postJson("/api/store-affiliate/campaigns/{$this->campaign->id}/creatives/{$creative->id}/review", [
            'action' => 'approved',
            'review_notes' => '符合规范',
        ], $this->adminHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('affiliate_creatives', [
            'id' => $creative->id,
            'status' => 'approved',
            'is_active' => true,
        ]);
    }

    /** @test */
    public function agent_can_resubmit_rejected_creative(): void
    {
        $creative = AffiliateCreative::create([
            'campaign_id' => $this->campaign->id,
            'type' => 'banner',
            'name' => '被驳回素材',
            'status' => 'rejected',
            'review_notes' => '图片不清晰',
            'created_by' => $this->agentUser->id,
            'is_active' => false,
        ]);

        $this->postJson("/api/store-affiliate/creatives/{$creative->id}/resubmit", [], $this->agentHeaders())
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('affiliate_creatives', [
            'id' => $creative->id,
            'status' => 'pending',
            'review_notes' => null,
            'is_active' => false,
        ]);
    }

    /** @test */
    public function portal_creative_list_only_shows_approved_materials(): void
    {
        AffiliateCreative::create([
            'campaign_id' => $this->campaign->id,
            'type' => 'banner',
            'name' => '已通过素材',
            'status' => 'approved',
            'is_active' => true,
        ]);
        AffiliateCreative::create([
            'campaign_id' => $this->campaign->id,
            'type' => 'banner',
            'name' => '待审素材',
            'status' => 'pending',
            'created_by' => $this->agentUser->id,
            'is_active' => false,
        ]);

        $response = $this->getJson("/api/store-affiliate/campaigns/{$this->campaign->id}/creatives", $this->agentHeaders());

        $response->assertStatus(200)->assertJsonCount(1, 'data');
        $this->assertEquals('approved', $response->json('data.0.status'));
    }
}
