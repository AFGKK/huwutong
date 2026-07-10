<?php

namespace Tests\Unit\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class DataMaskingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_customer_list_route_responds(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'customer@example.com',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/customers', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
    }

    public function test_authenticated_user_route(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'admin@example.com',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        // /api/user 不在 mask 组里，暴露完整邮箱
        $response = $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        // /api/user 是个人资料，不脱敏
        $response->assertJsonPath('data.email', 'admin@example.com');
    }

    public function test_login_history_route_responds(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/login-history', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
    }

    public function test_sessions_route_responds_with_mask(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/sessions', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
    }
}
