<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\DataProcessingAgreement;
use App\Models\GdprDataRequest;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GdprComplianceApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private User $admin;
    private string $userToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->userToken = $this->user->createToken('user-token', ['*'])->plainTextToken;
        $this->adminToken = $this->admin->createToken('admin-token', ['*'])->plainTextToken;
    }

    protected function userHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->userToken];
    }

    protected function adminHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->adminToken];
    }

    public function test_user_can_submit_dsr_request(): void
    {
        $response = $this->postJson('/api/gdpr/requests', [
            'type' => 'access',
            'reason' => 'I want to see my personal data',
        ], $this->userHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'access')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('gdpr_data_requests', [
            'user_id' => $this->user->id,
            'type' => 'access',
            'status' => 'pending',
        ]);
    }

    public function test_user_can_list_my_requests(): void
    {
        GdprDataRequest::factory()->count(2)->create(['user_id' => $this->user->id]);
        GdprDataRequest::factory()->create(['user_id' => $this->admin->id]);

        $response = $this->getJson('/api/gdpr/my-requests', $this->userHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_admin_can_list_all_requests(): void
    {
        GdprDataRequest::factory()->count(3)->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/gdpr/requests?status=pending', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
        $this->assertGreaterThanOrEqual(3, $response->json('data.total'));
    }

    public function test_admin_can_process_access_request(): void
    {
        $request = GdprDataRequest::factory()->create([
            'user_id' => $this->user->id,
            'type' => GdprDataRequest::TYPE_ACCESS,
            'status' => GdprDataRequest::STATUS_PENDING,
        ]);

        $response = $this->postJson(
            "/api/gdpr/requests/{$request->id}/process",
            [],
            $this->adminHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');
    }

    public function test_admin_can_review_request(): void
    {
        $request = GdprDataRequest::factory()->create([
            'user_id' => $this->user->id,
            'status' => GdprDataRequest::STATUS_PENDING,
        ]);

        $response = $this->postJson(
            "/api/gdpr/requests/{$request->id}/review",
            ['action' => 'approve'],
            $this->adminHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_stats_returns_overview(): void
    {
        GdprDataRequest::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => GdprDataRequest::STATUS_PENDING,
        ]);
        DataProcessingAgreement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => DataProcessingAgreement::STATUS_PUBLISHED,
        ]);

        $response = $this->getJson('/api/gdpr/stats', $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pending', 2)
            ->assertJsonPath('data.published_dpa', 1);
    }

    public function test_dpa_publish_and_sign_flow(): void
    {
        $dpa = DataProcessingAgreement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => DataProcessingAgreement::STATUS_DRAFT,
        ]);

        $this->postJson("/api/gdpr/dpa/{$dpa->id}/publish", [], $this->adminHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->postJson("/api/gdpr/dpa/{$dpa->id}/sign", [], $this->userHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/gdpr/dpa/my-status', $this->userHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_admin_can_create_dpa(): void
    {
        $response = $this->postJson('/api/gdpr/dpa', [
            'title' => 'Standard DPA',
            'version' => '2.0',
            'content' => 'Full agreement text here.',
            'tenant_id' => $this->tenant->id,
            'data_categories' => ['account data'],
        ], $this->adminHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/gdpr/stats')->assertStatus(401);
        $this->postJson('/api/gdpr/requests', ['type' => 'access'])->assertStatus(401);
    }
}
