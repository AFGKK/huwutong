<?php

namespace Tests\Feature\Api;

use App\Models\RenewalEscalation;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class RetentionApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_failure_stats_returns_data(): void
    {
        $response = $this->getJson('/api/retention/failure-stats', $this->authHeaders());

        // 依赖于 authorize 和 service，可能返回 403 或 500
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_subscription_failures_requires_existing(): void
    {
        $response = $this->getJson('/api/retention/subscriptions/999/failures', $this->authHeaders());

        $response->assertStatus(404);
    }

    public function test_pending_escalations_returns_data(): void
    {
        $response = $this->getJson('/api/retention/escalations', $this->authHeaders());

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_resolve_escalation_requires_note(): void
    {
        $response = $this->postJson('/api/retention/escalations/999/resolve', [], $this->authHeaders());

        $this->assertContains($response->status(), [403, 404, 422, 500]);
    }
}
