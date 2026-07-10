<?php

namespace Tests\Unit\Services;

use App\Models\PublicKeyVersion;
use App\Services\PublicKeyVersionService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class PublicKeyVersionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PublicKeyVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PublicKeyVersionService::class);
    }

    /** @test */
    public function it_creates_a_new_public_key_version()
    {
        $publicKey = base64_encode(random_bytes(32));

        $version = $this->service->createVersion($publicKey, 'Ed25519');

        $this->assertDatabaseHas('public_key_versions', [
            'key_version' => $version->key_version,
            'algorithm' => 'Ed25519',
            'is_active' => true,
            'is_revoked' => false,
        ]);

        $this->assertEquals($publicKey, $version->public_key);
        $this->assertNotNull($version->expires_at);
    }

    /** @test */
    public function it_creates_incrementing_key_versions()
    {
        $v1 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $v2 = $this->service->createVersion(base64_encode(random_bytes(32)));

        $this->assertEquals(1, $v1->key_version);
        $this->assertEquals(2, $v2->key_version);
    }

    /** @test */
    public function it_deactivates_old_versions_when_creating_new_one()
    {
        $v1 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $this->assertTrue($v1->is_active);

        $v2 = $this->service->createVersion(base64_encode(random_bytes(32)));

        $v1->refresh();
        $this->assertFalse($v1->is_active);
        $this->assertTrue($v2->is_active);

        // 旧版本设置了兼容窗口到期时间
        $this->assertNotNull($v1->expires_at);
    }

    /** @test */
    public function it_returns_all_versions()
    {
        $this->service->createVersion(base64_encode(random_bytes(32)));
        $this->service->createVersion(base64_encode(random_bytes(32)));

        $versions = $this->service->getAllVersions();

        $this->assertCount(2, $versions);
        $this->assertEquals(2, $versions[0]['key_version']); // 降序
        $this->assertEquals(1, $versions[1]['key_version']);
    }

    /** @test */
    public function it_gets_active_version()
    {
        $v1 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $active = $this->service->getActiveVersion();

        $this->assertEquals($v1->key_version, $active->key_version);

        // 创建新版本后活跃版本变更
        $v2 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $active = $this->service->getActiveVersion();

        $this->assertEquals($v2->key_version, $active->key_version);
    }

    /** @test */
    public function it_revokes_a_version()
    {
        $version = $this->service->createVersion(base64_encode(random_bytes(32)));

        $result = $this->service->revokeVersion($version->key_version, '测试吊销');
        $this->assertTrue($result);

        $version->refresh();
        $this->assertTrue($version->is_revoked);
        $this->assertFalse($version->is_active);
        $this->assertNotNull($version->revoked_at);
        $this->assertEquals('测试吊销', $version->revoke_reason);
    }

    /** @test */
    public function it_activates_fallback_when_revoking_active_version()
    {
        $v1 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $v2 = $this->service->createVersion(base64_encode(random_bytes(32)));

        // 验证 v2 是活跃版本
        $this->assertTrue($v2->is_active);
        $this->assertFalse($v1->fresh()->is_active);

        // 吊销 v2
        $this->service->revokeVersion($v2->key_version, '测试');

        // v1 应该被自动激活为 fallback
        $v1->refresh();
        $this->assertTrue($v1->is_active, 'v1 应该被激活为 fallback');

        $active = $this->service->getActiveVersion();
        $this->assertNotNull($active, '应该有 fallback 版本被激活');
        $this->assertEquals($v1->key_version, $active->key_version);
    }

    /** @test */
    public function it_checks_rotation_needed()
    {
        $version = $this->service->createVersion(base64_encode(random_bytes(32)));

        // 新版本不需要轮换
        $check = $this->service->checkRotationNeeded();
        $this->assertIsArray($check);
        $this->assertArrayHasKey('needed', $check);
        $this->assertArrayHasKey('days_until_expiry', $check);
    }

    /** @test */
    public function it_returns_valid_versions_for_compat()
    {
        $v1 = $this->service->createVersion(base64_encode(random_bytes(32)));
        $v2 = $this->service->createVersion(base64_encode(random_bytes(32)));

        $valid = $this->service->getValidVersions();

        // 两个版本都应在兼容列表内（v1 仍在兼容窗口内）
        $versions = array_column($valid, 'key_version');
        $this->assertContains($v1->key_version, $versions);
        $this->assertContains($v2->key_version, $versions);
    }

    /** @test */
    public function it_returns_version_detail()
    {
        $version = $this->service->createVersion(base64_encode(random_bytes(32)));

        $detail = $this->service->getVersionDetail($version->key_version);
        $this->assertNotNull($detail);
        $this->assertEquals($version->key_version, $detail['key_version']);
        $this->assertEquals($version->public_key, $detail['public_key']);
        $this->assertArrayHasKey('is_compat_mode', $detail);
        $this->assertArrayHasKey('signed_files_count', $detail);
    }

    /** @test */
    public function it_performs_signing_test()
    {
        $keyPair = sodium_crypto_sign_keypair();
        $publicKey = base64_encode(sodium_crypto_sign_publickey($keyPair));

        $result = $this->service->testSigning($publicKey, 'Ed25519');

        $this->assertArrayHasKey('valid', $result);
        $this->assertArrayHasKey('test_message', $result);
        $this->assertArrayHasKey('signature', $result);
        $this->assertIsString($result['test_message']);
        $this->assertNotNull($result['signature']);
    }

    /** @test */
    public function it_returns_stats()
    {
        $this->service->createVersion(base64_encode(random_bytes(32)));
        $this->service->createVersion(base64_encode(random_bytes(32)));

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total_versions', $stats);
        $this->assertEquals(2, $stats['total_versions']);
        $this->assertEquals(1, $stats['active_versions']);
        $this->assertArrayHasKey('compat_window_days', $stats);
    }

    /** @test */
    public function it_purges_obsolete_versions()
    {
        // 创建版本并人工标记为废弃
        $version = $this->service->createVersion(base64_encode(random_bytes(32)));
        $this->service->revokeVersion($version->key_version, '清理测试');

        // 将过期时间设置为超过兼容窗口
        $version->update([
            'expires_at' => now()->subDays(60),
        ]);

        $count = $this->service->purgeObsoleteVersions();
        $this->assertEquals(1, $count);

        $this->assertDatabaseMissing('public_key_versions', ['id' => $version->id]);
    }
}
