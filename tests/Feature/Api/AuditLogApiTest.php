<?php

namespace Tests\Feature\Api;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AuditLogApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private License $license;
    private User $user;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;
        $this->license = License::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    public function test_index_returns_paginated_audit_logs(): void
    {
        app(AuditService::class)->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'expired',
        );

        $response = $this->getJson('/api/audit-logs', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_action(): void
    {
        app(AuditService::class)->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'suspended',
        );

        $response = $this->getJson('/api/audit-logs?filter[action]=license.status_changed', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_show_returns_log_detail(): void
    {
        $log = app(AuditService::class)->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'expired',
        );

        $response = $this->getJson("/api/audit-logs/{$log->id}", $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_stats_returns_summary(): void
    {
        app(AuditService::class)->licenseStatusChanged(
            tenantId: $this->tenant->id,
            licenseId: $this->license->id,
            licenseKey: $this->license->license_key,
            oldStatus: 'active',
            newStatus: 'expired',
        );

        $response = $this->getJson('/api/audit-logs/stats', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['total', 'by_type', 'today', 'top_actions']]);
    }

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/audit-logs')->assertStatus(401);
    }
}
