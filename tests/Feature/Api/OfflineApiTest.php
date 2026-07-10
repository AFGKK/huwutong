<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\OfflineCertificate;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OfflineLicenseService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfflineApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private User $user;
    private string $token;
    private OfflineLicenseService $offlineService;
    private array $keyPair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offlineService = app(OfflineLicenseService::class);

        // 清除离线验证相关缓存
        \Illuminate\Support\Facades\Cache::forget('offline_cert:' . OfflineLicenseService::KEY_VERSION);
        \Illuminate\Support\Facades\Cache::forget('offline_cert:latest');
        \Illuminate\Support\Facades\Cache::forget('crl_full');

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->token = $this->user->createToken('test-token', ['*'])->plainTextToken;

        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        // 生成并保存证书
        $this->keyPair = $this->offlineService->generateKeyPair();
        OfflineCertificate::create([
            'key_version' => OfflineLicenseService::KEY_VERSION,
            'algorithm' => OfflineLicenseService::ALGORITHM_ED25519,
            'public_key' => $this->keyPair['public_key'],
            'is_active' => true,
            'expires_at' => now()->addYear(),
        ]);
    }

    protected function authHeaders(): array
    {
        return ['Authorization' => 'Bearer ' . $this->token];
    }

    // ─── 公开端点 ───

    public function test_verify_offline_license_success(): void
    {
        $generated = $this->offlineService->generateLicenseFile(
            $this->license,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        $response = $this->postJson('/api/offline/verify', [
            'license_file' => $generated['file_content'],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.payload.lic_key', $this->license->license_key);
    }

    public function test_verify_invalid_content(): void
    {
        $response = $this->postJson('/api/offline/verify', [
            'license_file' => base64_encode('invalid-content'),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'FILE_PARSE_ERROR');
    }

    public function test_verify_missing_file(): void
    {
        $response = $this->postJson('/api/offline/verify', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_get_public_key(): void
    {
        $response = $this->getJson('/api/offline/public-key?key_version=' . OfflineLicenseService::KEY_VERSION);

        $response->assertStatus(200)
            ->assertJsonPath('data.key_version', OfflineLicenseService::KEY_VERSION)
            ->assertJsonPath('data.algorithm', 'Ed25519');
    }

    public function test_get_public_key_latest(): void
    {
        $response = $this->getJson('/api/offline/public-key');

        $response->assertStatus(200)
            ->assertJsonPath('data.key_version', OfflineLicenseService::KEY_VERSION);
    }

    public function test_get_crl(): void
    {
        $response = $this->getJson('/api/offline/crl');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['version', 'count', 'entries']]);
    }

    // ─── 管理端点 ───

    public function test_generate_offline_file_requires_auth(): void
    {
        $response = $this->postJson('/api/offline/generate', [
            'license_id' => $this->license->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_generate_offline_file(): void
    {
        $response = $this->postJson('/api/offline/generate', [
            'license_id' => $this->license->id,
        ], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['file_content', 'payload', 'signature']]);
    }

    public function test_revoke_offline_license(): void
    {
        $response = $this->postJson('/api/offline/revoke', [
            'license_key' => $this->license->license_key,
            'reason' => '安全违规',
        ], $this->authHeaders());

        $response->assertStatus(200);

        // 验证 CRL 包含该 license
        $crlResponse = $this->getJson('/api/offline/crl');
        $this->assertEquals(1, $crlResponse->json('data.count'));
    }

    public function test_restore_offline_license(): void
    {
        // 先吊销
        $this->postJson('/api/offline/revoke', [
            'license_key' => $this->license->license_key,
        ], $this->authHeaders());

        // 恢复
        $response = $this->postJson('/api/offline/restore', [
            'license_key' => $this->license->license_key,
        ], $this->authHeaders());

        $response->assertStatus(200);

        // 验证 CRL 为空
        $crlResponse = $this->getJson('/api/offline/crl');
        $this->assertEquals(0, $crlResponse->json('data.count'));
    }

    public function test_init_keys(): void
    {
        $response = $this->postJson('/api/offline/init-keys', [], $this->authHeaders());

        $response->assertStatus(201)
            ->assertJsonPath('data.key_version', OfflineLicenseService::KEY_VERSION + 1)
            ->assertJsonPath('data.algorithm', 'Ed25519');
    }

    public function test_init_keys_requires_auth(): void
    {
        $response = $this->postJson('/api/offline/init-keys');
        $response->assertStatus(401);
    }
}
