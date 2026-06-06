<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyApiTest extends TestCase
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

    // ─── CRUD ───

    public function test_index_returns_keys(): void
    {
        ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_test_123',
            'name' => '测试密钥',
            'secret' => bcrypt('secret'),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/api-keys', $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => [['id', 'key_id', 'name', 'is_active']]]);
    }

    public function test_store_creates_key(): void
    {
        $response = $this->postJson('/api/api-keys', [
            'name' => '我的 API 密钥',
        ], $this->authHeaders());

        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['id', 'key_id', 'name', 'secret']]);
        $this->assertDatabaseHas('api_keys', ['name' => '我的 API 密钥']);
    }

    public function test_store_validates_name_required(): void
    {
        $response = $this->postJson('/api/api-keys', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_store_limits_to_10_keys(): void
    {
        for ($i = 0; $i < 10; $i++) {
            ApiKey::create([
                'tenant_id' => $this->tenant->id,
                'key_id' => "ak_$i",
                'name' => "Key $i",
                'secret' => bcrypt('secret'),
            ]);
        }

        $response = $this->postJson('/api/api-keys', ['name' => '第11个'], $this->authHeaders());

        $response->assertStatus(422);
        $response->assertJsonPath('error.code', 'MAX_KEYS_REACHED');
        $response->assertJsonPath('error.message', '最多可创建 10 个 API 密钥');
    }

    public function test_show_returns_key(): void
    {
        $apiKey = ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_show',
            'name' => '详情测试',
            'secret' => bcrypt('secret'),
        ]);

        $response = $this->getJson("/api/api-keys/{$apiKey->id}", $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $apiKey->id);
    }

    public function test_update_modifies_key(): void
    {
        $apiKey = ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_update',
            'name' => '旧名称',
            'secret' => bcrypt('secret'),
        ]);

        $response = $this->putJson("/api/api-keys/{$apiKey->id}", [
            'name' => '新名称',
            'is_active' => false,
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('api_keys', [
            'id' => $apiKey->id,
            'name' => '新名称',
            'is_active' => false,
        ]);
    }

    public function test_destroy_deletes_key(): void
    {
        $apiKey = ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_delete',
            'name' => '待删除',
            'secret' => bcrypt('secret'),
        ]);

        $response = $this->deleteJson("/api/api-keys/{$apiKey->id}", [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('api_keys', ['id' => $apiKey->id]);
    }

    public function test_show_returns_403_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $apiKey = ApiKey::create([
            'tenant_id' => $otherTenant->id,
            'key_id' => 'ak_other',
            'name' => '其他租户密钥',
            'secret' => bcrypt('secret'),
        ]);

        $response = $this->getJson("/api/api-keys/{$apiKey->id}", $this->authHeaders());

        $response->assertStatus(403);
    }

    // ─── 重新生成 ───

    public function test_regenerate_returns_new_secret(): void
    {
        $apiKey = ApiKey::create([
            'tenant_id' => $this->tenant->id,
            'key_id' => 'ak_regen',
            'name' => '重新生成测试',
            'secret' => bcrypt('old_secret'),
        ]);

        $response = $this->postJson("/api/api-keys/{$apiKey->id}/regenerate", [], $this->authHeaders());

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure(['data' => ['id', 'key_id', 'secret']]);
    }
}
