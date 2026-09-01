<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\FingerprintService;
use App\Services\KeyGenerator;
use Tests\Concerns\RefreshDatabase;
use Tests\Concerns\LicenseActivationHelpers;
use Tests\TestCase;

class LicenseActivationTest extends TestCase
{
    use RefreshDatabase;
    use LicenseActivationHelpers;

    private Tenant $tenant;
    private Product $product;
    private License $license;
    private string $licenseKey;
    private KeyGenerator $keyGenerator;
    private FingerprintService $fingerprintService;

    /** @var array 模拟设备组件 */
    private array $realDeviceComponents = [
        'mac' => '00:1A:2B:3C:4D:5E',
        'cpu_id' => 'BFEBFBFF000906E9',
        'motherboard' => 'ASUS ROG STRIX Z790-E',
        'disk_sn' => 'XY1234567890ABCD',
        'system_uuid' => '4C4C4544-004C-4410-8053-B4C04F4D3332',
    ];

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
    }

    // ─── 基本激活测试 ───

    public function test_activate_license_successfully(): void
    {
        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
            'platform' => 'windows',
            'os_version' => '10.0.19041',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.license_key', $this->licenseKey)
            ->assertJsonStructure([
                'data' => ['valid', 'license_key', 'status', 'expires_at', 'activation_id', 'device_id'],
            ]);
    }

    public function test_activate_with_nonexistent_key(): void
    {
        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => 'HWT-STD-NONEXISTENT-XXXX',
            'fingerprint' => 'test-fingerprint',
        ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.code', 'LICENSE_NOT_FOUND');
    }

    public function test_activate_expired_license(): void
    {
        $expiredLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $this->keyGenerator->generate('standard'),
            'status' => 'active',
            'expires_at' => now()->subDay(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $expiredLicense->license_key,
            'fingerprint' => 'test-fingerprint',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LICENSE_EXPIRED');
    }

    public function test_activate_exceeds_device_limit(): void
    {
        $limitedLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $this->keyGenerator->generate('enterprise'),
            'status' => 'active',
            'max_devices' => 1,
            'expires_at' => now()->addYear(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        // 第一次激活（成功）
        $this->securePostJson('/api/license/activate', [
            'license_key' => $limitedLicense->license_key,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
        ])->assertStatus(200);

        // 第二次激活不同设备（4个组件不同，宽容匹配不通过）
        $otherComponents = [
            'mac' => 'AA:BB:CC:DD:EE:FF',
            'cpu_id' => 'OTHER-CPU-ID',
            'motherboard' => 'OTHER-MOTHERBOARD',
            'disk_sn' => 'OTHER-DISK-SN',
            'system_uuid' => '00000000-0000-0000-0000-000000000001',
        ];
        $otherFp = $this->fingerprintService->generate($otherComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $limitedLicense->license_key,
            'fingerprint' => $otherFp,
            'components' => $otherComponents,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'DEVICE_LIMIT_EXCEEDED');
    }

    // ─── 宽容指纹匹配测试 ───

    public function test_fingerprint_tolerant_match_reuses_device(): void
    {
        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        // 首次激活
        $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
        ])->assertStatus(200);

        // 同一设备换了个硬盘（80%匹配，4/5 ≥ 3），不应计入新设备
        $changedComponents = $this->realDeviceComponents;
        $changedComponents['disk_sn'] = 'NEW-DISK-SN-987654';
        $changedFp = $this->fingerprintService->generate($changedComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $changedFp,
            'components' => $changedComponents,
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.is_existing_device'));
    }

    public function test_fingerprint_mismatch_creates_new_device(): void
    {
        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        // 首次激活
        $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
        ])->assertStatus(200);

        // 完全不同的设备（换4个组件，20%匹配）
        $differentComponents = [
            'mac' => 'AA:BB:CC:DD:EE:FF',
            'cpu_id' => 'DIFFERENT-CPU',
            'motherboard' => 'DIFFERENT-MB',
            'disk_sn' => 'DIFFERENT-DISK',
            'system_uuid' => $this->realDeviceComponents['system_uuid'],
        ];
        $differentFp = $this->fingerprintService->generate($differentComponents);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $differentFp,
            'components' => $differentComponents,
        ]);

        $response->assertStatus(200);
        $this->assertFalse($response->json('data.is_existing_device'));
    }

    // ─── 验证测试 ───

    public function test_validate_valid_license(): void
    {
        $response = $this->securePostJson('/api/license/validate', [
            'license_key' => $this->licenseKey,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true);
    }

    public function test_validate_invalid_license(): void
    {
        $response = $this->securePostJson('/api/license/validate', [
            'license_key' => 'HWT-STD-INVALID-KEY',
        ]);

        $response->assertStatus(404);
    }

    // ─── 状态测试 ───

    public function test_pending_license_auto_activates(): void
    {
        $pendingKey = $this->keyGenerator->generate('trial');
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $pendingKey,
            'status' => 'pending',
            'max_devices' => 3,
            'expires_at' => now()->addMonth(),
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $pendingKey,
            'fingerprint' => 'device-pending-001',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_revoked_license_cannot_activate(): void
    {
        $revokedKey = $this->keyGenerator->generate('standard');
        License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'license_key' => $revokedKey,
            'status' => 'revoked',
            'metadata' => ['signature_secret' => 'test-activation-secret'],
        ]);

        $response = $this->securePostJson('/api/license/activate', [
            'license_key' => $revokedKey,
            'fingerprint' => 'device-revoked-001',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'LICENSE_NOT_ACTIVATABLE');
    }

    public function test_activate_blocks_ip_outside_whitelist(): void
    {
        \App\Models\LicenseRestriction::create([
            'restrictable_type' => 'license',
            'restrictable_id' => $this->license->id,
            'type' => 'ip_range',
            'is_active' => true,
            'action' => 'block',
            'ip_ranges' => ['10.0.0.0/8'],
            'ip_whitelist' => [],
            'ip_blacklist' => [],
        ]);

        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'LICENSE_IP_RESTRICTED');
    }

    public function test_activate_blocks_unknown_geo_when_configured(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'ip-api.com/*' => \Illuminate\Support\Facades\Http::response(['status' => 'fail'], 200),
        ]);

        \App\Models\LicenseRestriction::create([
            'restrictable_type' => 'license',
            'restrictable_id' => $this->license->id,
            'type' => 'geo_fence',
            'is_active' => true,
            'action' => 'block',
            'allowed_countries' => ['CN'],
            'blocked_countries' => [],
            'unknown_location_action' => 'block',
        ]);

        $fp = $this->fingerprintService->generate($this->realDeviceComponents);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);

        $this->securePostJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fp,
            'components' => $this->realDeviceComponents,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'DEVICE_REGION_BLOCKED');
    }

    // ─── 安全中间件测试 ───

    public function test_rejects_missing_nonce(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => 'test-fp',
        ], [
            'X-Timestamp' => (string) time(),
            'X-Signature' => 'dGVzdA==',
            'X-Signature-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'MISSING_NONCE_OR_TIMESTAMP');
    }

    public function test_rejects_expired_timestamp(): void
    {
        $oldTimestamp = (string) (time() - 300);
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => 'test-fp',
        ], [
            'X-Nonce' => $this->generateNonce(),
            'X-Timestamp' => $oldTimestamp,
            'X-Signature' => 'dGVzdA==',
            'X-Signature-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'TIMESTAMP_OUT_OF_WINDOW');
    }

    public function test_rejects_wrong_signature(): void
    {
        $response = $this->postJson('/api/license/activate', [
            'license_key' => $this->licenseKey,
            'fingerprint' => 'test-fp',
        ], [
            'X-Nonce' => $this->generateNonce(),
            'X-Timestamp' => (string) time(),
            'X-Signature' => 'aW52YWxpZC1zaWc=',
            'X-Signature-Timestamp' => (string) time(),
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'SIGNATURE_MISMATCH');
    }
}
