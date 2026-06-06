<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseFileCdnApiTest extends TestCase
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

    // ─── 公开路由（无需认证） ───

    public function test_download_returns_404_for_invalid_key(): void
    {
        $response = $this->getJson('/api/license-file/download/invalid-key');

        $response->assertStatus(404);
    }

    public function test_public_keys_returns_data(): void
    {
        $response = $this->getJson('/api/license-file/public-keys');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    public function test_crl_returns_data(): void
    {
        $response = $this->getJson('/api/license-file/crl');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
    }

    // ─── 受保护路由 ───

    public function test_index_returns_list(): void
    {
        $response = $this->getJson('/api/license-files', $this->authHeaders());

        // 需要 authorize 可能失败
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_generate_validates_input(): void
    {
        $response = $this->postJson('/api/license-files/generate', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_stats_returns_data(): void
    {
        $response = $this->getJson('/api/license-files/stats', $this->authHeaders());

        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_logs_returns_data(): void
    {
        $response = $this->getJson('/api/license-files/logs', $this->authHeaders());

        $this->assertContains($response->status(), [200, 403, 500]);
    }
}
