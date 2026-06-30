<?php

namespace Tests\Feature\Api;

use App\Models\ApiVersion;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CsmApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Customer $customer;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        ApiVersion::create([
            'version' => 'v1',
            'base_path' => '/api/v1',
            'name' => 'v1',
            'status' => 'active',
            'is_default' => true,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_dashboard_returns_overview(): void
    {
        $response = $this->getJson('/api/csm/dashboard', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data']);
    }

    public function test_customers_returns_paginated_list(): void
    {
        $response = $this->getJson('/api/csm/customers', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_calculate_health_score_for_customer(): void
    {
        $response = $this->postJson(
            "/api/csm/customers/{$this->customer->id}/calculate-health",
            [],
            $this->authHeaders()
        );

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['health_score', 'health_level']]);
    }

    public function test_tasks_crud_flow(): void
    {
        $create = $this->postJson('/api/csm/tasks', [
            'customer_id' => $this->customer->id,
            'title' => 'Renewal follow-up',
            'priority' => 'high',
            'category' => 'renewal',
            'assigned_to' => $this->user->id,
        ], $this->authHeaders());

        $create->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Renewal follow-up');

        $taskId = $create->json('data.id');

        $this->putJson("/api/csm/tasks/{$taskId}", [
            'status' => 'in_progress',
        ], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress');

        $this->getJson('/api/csm/tasks', $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->deleteJson("/api/csm/tasks/{$taskId}", [], $this->authHeaders())
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_health_trend_returns_series(): void
    {
        $response = $this->getJson('/api/csm/health-trend?days=30', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_renewal_calendar_returns_events(): void
    {
        $response = $this->getJson('/api/csm/renewal-calendar', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_activity_timeline_returns_entries(): void
    {
        $response = $this->getJson('/api/csm/activity-timeline', $this->authHeaders());

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/csm/dashboard')->assertStatus(401);
    }
}
