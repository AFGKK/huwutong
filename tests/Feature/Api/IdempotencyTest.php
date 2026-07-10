<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\FingerprintService;
use App\Services\KeyGenerator;
use Tests\Concerns\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\LicenseActivationHelpers;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;
    use LicenseActivationHelpers;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private string $licenseKey;
    private KeyGenerator $keyGenerator;
    private FingerprintService $fingerprintService;

    private array $deviceComponents;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyGenerator = app(KeyGenerator::class);
        $this->fingerprintService = app(FingerprintService::class);

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();

        $this->licenseKey = $this->keyGenerator->generate('standard');
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $this->licenseKey,
            'status' => 'active',
            'max_devices' => 3,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $this->activationTenantId = $this->tenant->id;
        $this->activationProductId = $this->product->id;

        $this->deviceComponents = [
            'mac' => '00:1A:2B:3C:4D:5E',
            'cpu_id' => 'BFEBFBFF000906E9',
            'motherboard' => 'ASUS ROG STRIX Z790-E',
            'disk_sn' => 'XY1234567890ABCD',
            'system_uuid' => '4C4C4544-004C-4410-8053-B4C04F4D3332',
        ];
    }

    public function test_activate_without_idempotency_key_still_works(): void
    {
        $fp = $this->fingerprintService->generate($this->deviceComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->deviceComponents,
        ]);

        $response->assertStatus(200);
    }

    public function test_activate_with_idempotency_key_returns_same_result(): void
    {
        $idempotencyKey = Str::uuid()->toString();
        $fp = $this->fingerprintService->generate($this->deviceComponents);

        $headers = ['Idempotency-Key' => $idempotencyKey];

        // 第一次请求
        $first = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->deviceComponents,
        ], $headers);

        $first->assertStatus(200);
        $firstData = $first->json('data');

        // 第二次请求（相同 key）
        $second = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->deviceComponents,
        ], $headers);

        $second->assertStatus(200);
        $this->assertEquals('true', $second->headers->get('X-Idempotency-Replayed'));
        $this->assertEquals($firstData, $second->json('data'));
    }

    public function test_invalid_idempotency_key_returns_error(): void
    {
        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => 'test-fp',
        ], ['Idempotency-Key' => 'not-a-uuid']);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_validate_with_idempotency_key(): void
    {
        $idempotencyKey = Str::uuid()->toString();
        $headers = ['Idempotency-Key' => $idempotencyKey];

        $first = $this->securePostJson('/api/license/validate', [
            'license_key' => $this->licenseKey,
        ], $headers);

        $first->assertStatus(200);

        $second = $this->securePostJson('/api/license/validate', [
            'license_key' => $this->licenseKey,
        ], $headers);

        $second->assertStatus(200);
        $this->assertEquals('true', $second->headers->get('X-Idempotency-Replayed'));
    }

    public function test_different_idempotency_keys_produce_independent_results(): void
    {
        $fp = $this->fingerprintService->generate($this->deviceComponents);

        $first = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->deviceComponents,
        ], ['Idempotency-Key' => Str::uuid()->toString()]);

        $first->assertStatus(200);

        $second = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->deviceComponents,
        ], ['Idempotency-Key' => Str::uuid()->toString()]);

        $second->assertStatus(200);
        // 第二次应该是匹配到已有设备，is_existing_device = true
        $this->assertTrue($second->json('data.is_existing_device'));
    }
}
