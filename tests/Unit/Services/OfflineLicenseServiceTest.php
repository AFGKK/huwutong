<?php

namespace Tests\Unit\Services;

use App\Models\License;
use App\Models\OfflineCertificate;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\OfflineLicenseService;
use App\Services\OfflineVerifier;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class OfflineLicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private OfflineLicenseService $offlineService;
    private OfflineVerifier $offlineVerifier;
    private Tenant $tenant;
    private Product $product;
    private License $license;
    private array $keyPair;

    protected function setUp(): void
    {
        parent::setUp();

        $this->offlineService = new OfflineLicenseService();
        $this->offlineVerifier = app(OfflineVerifier::class);

        // 清除离线验证相关缓存，防止跨测试干扰
        \Illuminate\Support\Facades\Cache::forget('offline_cert:latest');
        \Illuminate\Support\Facades\Cache::forget('offline_cert:' . OfflineLicenseService::KEY_VERSION);
        \Illuminate\Support\Facades\Cache::forget('crl_full');

        $this->tenant = Tenant::factory()->create();
        $this->product = Product::factory()->create();
        $this->license = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'expires_at' => now()->addYear(),
        ]);

        // 生成密钥对并保存证书
        $this->keyPair = $this->offlineService->generateKeyPair();

        OfflineCertificate::create([
            'key_version' => OfflineLicenseService::KEY_VERSION,
            'algorithm' => OfflineLicenseService::ALGORITHM_ED25519,
            'public_key' => $this->keyPair['public_key'],
            'is_active' => true,
            'expires_at' => now()->addYear(),
        ]);
    }

    public function test_generate_key_pair(): void
    {
        $keyPair = $this->offlineService->generateKeyPair();

        $this->assertArrayHasKey('private_key', $keyPair);
        $this->assertArrayHasKey('public_key', $keyPair);
        $this->assertArrayHasKey('seed', $keyPair);

        $this->assertNotEmpty($keyPair['private_key']);
        $this->assertNotEmpty($keyPair['public_key']);

        // 验证 Ed25519 公钥长度（32B → 44 Base64 chars）
        $this->assertEquals(44, strlen($keyPair['public_key']));
    }

    public function test_restore_key_pair_from_seed(): void
    {
        $restored = $this->offlineService->restoreKeyPair($this->keyPair['seed']);

        $this->assertEquals($this->keyPair['public_key'], $restored['public_key']);
        $this->assertEquals($this->keyPair['private_key'], $restored['private_key']);
    }

    public function test_generate_license_file(): void
    {
        $result = $this->offlineService->generateLicenseFile(
            $this->license,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        $this->assertArrayHasKey('file_content', $result);
        $this->assertArrayHasKey('payload', $result);
        $this->assertArrayHasKey('signature', $result);

        $payload = $result['payload'];
        $this->assertEquals($this->license->license_key, $payload['lic_key']);
        $this->assertEquals('active', $payload['status']);
        $this->assertEquals(OfflineLicenseService::KEY_VERSION, $payload['kid']);
    }

    public function test_verify_license_file_success(): void
    {
        $generated = $this->offlineService->generateLicenseFile(
            $this->license,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        $result = $this->offlineVerifier->verify($generated['file_content']);

        $this->assertTrue($result->isValid);
        $this->assertEquals('离线验证通过', $result->message);
    }

    public function test_verify_rejects_tampered_file(): void
    {
        $generated = $this->offlineService->generateLicenseFile(
            $this->license,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        // 篡改文件内容（修改 Base64 解码后的 payload 中某个字段）
        // 在 file_content 的 JSON 部分中替换 expires_at
        $decoded = base64_decode($generated['file_content']);

        // 找到 JSON 载荷起始位置（在签名之后）
        $sigLength = 64; // Ed25519
        $headerLength = 8; // 5(magic) + 2(version) + 1(alg)
        $jsonStart = $headerLength + $sigLength;
        $payload = substr($decoded, $jsonStart);

        // 篡改：把 status active 改为 revoked（签名会不匹配）
        $tamperedPayload = str_replace(
            '"status":"active"',
            '"status":"revoked"',
            $payload,
        );

        // 重新组装
        $tamperedBinary = substr($decoded, 0, $jsonStart) . $tamperedPayload;
        $tamperedContent = base64_encode($tamperedBinary);

        $result = $this->offlineVerifier->verify($tamperedContent);

        $this->assertFalse($result->isValid);
        $this->assertEquals('SIGNATURE_INVALID', $result->errorCode);
    }

    public function test_verify_rejects_expired_file(): void
    {
        $expiredLicense = License::factory()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $this->product->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $generated = $this->offlineService->generateLicenseFile(
            $expiredLicense,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        $result = $this->offlineVerifier->verify($generated['file_content']);

        $this->assertFalse($result->isValid);
        $this->assertEquals('LICENSE_EXPIRED', $result->errorCode);
    }

    public function test_verify_rejects_revoked_license(): void
    {
        $generated = $this->offlineService->generateLicenseFile(
            $this->license,
            $this->keyPair['private_key'],
            $this->keyPair['public_key'],
        );

        // 加入 CRL
        $this->offlineVerifier->revokeLicense($this->license->license_key, '测试吊销');

        $result = $this->offlineVerifier->verify($generated['file_content']);

        $this->assertFalse($result->isValid);
        $this->assertEquals('LICENSE_REVOKED', $result->errorCode);
    }

    public function test_verify_with_different_key_fails(): void
    {
        // 用不同密钥对签名
        $otherKeyPair = $this->offlineService->generateKeyPair();

        $generated = $this->offlineService->generateLicenseFile(
            $this->license,
            $otherKeyPair['private_key'],
            $otherKeyPair['public_key'],
        );

        $result = $this->offlineVerifier->verify($generated['file_content']);

        // 应该验证失败（公钥不匹配）
        $this->assertFalse($result->isValid);
    }

    public function test_crl_entry_is_created(): void
    {
        $this->offlineVerifier->revokeLicense('HWT-STD-TEST-KEY', '安全违规');

        $crl = $this->offlineVerifier->getCrl();
        $this->assertEquals(1, $crl['count']);

        $entry = $crl['entries'][0];
        $this->assertEquals('HWT-STD-TEST-KEY', $entry['license_key']);
        $this->assertEquals('安全违规', $entry['reason']);
    }

    public function test_restore_license_removes_from_crl(): void
    {
        $this->offlineVerifier->revokeLicense('HWT-STD-TO-RESTORE', '误操作');

        $crlBefore = $this->offlineVerifier->getCrl();
        $this->assertEquals(1, $crlBefore['count']);

        $this->offlineVerifier->restoreLicense('HWT-STD-TO-RESTORE');

        $crlAfter = $this->offlineVerifier->getCrl();
        $this->assertEquals(0, $crlAfter['count']);
    }

    public function test_get_public_key(): void
    {
        $result = $this->offlineVerifier->getPublicKey(OfflineLicenseService::KEY_VERSION);

        $this->assertNotNull($result);
        $this->assertEquals(OfflineLicenseService::KEY_VERSION, $result['key_version']);
        $this->assertEquals('Ed25519', $result['algorithm']);
        $this->assertEquals($this->keyPair['public_key'], $result['public_key']);
    }

    public function test_get_nonexistent_public_key_returns_null(): void
    {
        $result = $this->offlineVerifier->getPublicKey(999);
        $this->assertNull($result);
    }

    public function test_verification_result_to_array(): void
    {
        $valid = \App\Services\OfflineVerificationResult::valid('OK', ['key' => 'val'], ['meta' => 'data']);
        $array = $valid->toArray();
        $this->assertTrue($array['valid']);
        $this->assertEquals(['key' => 'val'], $array['payload']);

        $invalid = \App\Services\OfflineVerificationResult::invalid('TEST_ERROR', '测试错误');
        $this->assertFalse($invalid->isValid);
        $this->assertEquals('TEST_ERROR', $invalid->errorCode);
    }
}
