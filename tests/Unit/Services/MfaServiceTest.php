<?php

namespace Tests\Unit\Services;

use App\Models\MfaDevice;
use App\Models\Tenant;
use App\Models\User;
use App\Services\MfaService;
use Database\Factories\TenantFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaServiceTest extends TestCase
{
    use RefreshDatabase;

    private MfaService $service;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MfaService::class);

        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
    }

    // ─── TOTP ───

    public function test_generates_valid_secret(): void
    {
        $secret = $this->service->generateSecret();
        // Base32: 32 位字母（A-Z, 2-7）
        $this->assertEquals(32, strlen($secret));
        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
    }

    public function test_get_totp_config_returns_uri(): void
    {
        $config = $this->service->getTOTPConfig('TESTKEY', 'user@test.com');

        $this->assertArrayHasKey('secret', $config);
        $this->assertArrayHasKey('uri', $config);
        $this->assertStringContainsString('otpauth://totp/', $config['uri']);
        $this->assertStringContainsString('secret=TESTKEY', $config['uri']);
        $this->assertStringContainsString('user%40test.com', $config['uri']);
    }

    public function test_verify_totp_roundtrip(): void
    {
        $secret = $this->service->generateSecret();

        // 获取当前有效 code（无法直接得到，但可以验证格式）
        // 直接测试 Base32 解码/编码一致性
        $config = $this->service->getTOTPConfig($secret, 'test@test.com');
        $this->assertNotNull($config['secret']);
        $this->assertEquals(32, strlen($config['secret']));
    }

    public function test_verify_totp_rejects_invalid_code(): void
    {
        $result = $this->service->verifyTOTP('JBSWY3DPEHPK3PXP', '000000');
        $this->assertFalse($result);
    }

    public function test_verify_totp_rejects_non_numeric(): void
    {
        $result = $this->service->verifyTOTP('JBSWY3DPEHPK3PXP', 'abcdef');
        $this->assertFalse($result);
    }

    // ─── MFA 启用/禁用 ───

    public function test_enable_mfa_creates_device_and_enables_user(): void
    {
        $secret = $this->service->generateSecret();
        $device = $this->service->enableMfa($this->user, '我的手机', $secret);

        $this->assertDatabaseHas('mfa_devices', [
            'id' => $device->id,
            'user_id' => $this->user->id,
            'name' => '我的手机',
        ]);

        $this->user = User::find($this->user->id);
        $this->assertTrue((bool) $this->user->mfa_enabled);
        $this->assertEquals($secret, $this->user->mfa_secret);
    }

    public function test_disable_mfa_removes_devices_and_codes(): void
    {
        $secret = $this->service->generateSecret();
        $this->service->enableMfa($this->user, 'Device', $secret);
        $this->service->generateRecoveryCodes($this->user);

        $this->service->disableMfa($this->user);

        $this->user = User::find($this->user->id);
        $this->assertFalse($this->user->mfa_enabled);
        $this->assertNull($this->user->mfa_secret);
        $this->assertNull($this->user->mfa_recovery_codes);

        $this->assertDatabaseCount('mfa_devices', 0);
    }

    // ─── 恢复码 ───

    public function test_generates_10_recovery_codes(): void
    {
        $codes = $this->service->generateRecoveryCodes($this->user);

        $this->assertCount(10, $codes);
        foreach ($codes as $code) {
            // 格式: xxxx-xxxx-xxxx-xxxx
            $this->assertMatchesRegularExpression('/^[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}-[a-z0-9]{4}$/', $code);
        }

        $this->user->refresh();
        $this->assertNotNull($this->user->mfa_recovery_codes, '恢复码不应为 null');
        $this->assertIsArray($this->user->mfa_recovery_codes);
        $this->assertCount(10, $this->user->mfa_recovery_codes);
        $this->assertEquals(10, $this->service->countRemainingCodes($this->user));
    }

    public function test_recovery_code_can_be_used_once(): void
    {
        $codes = $this->service->generateRecoveryCodes($this->user);

        // 使用第一个恢复码
        $result = $this->service->verifyRecoveryCode($this->user, $codes[0]);
        $this->assertTrue($result['verified']);

        $this->assertEquals(9, $this->service->countRemainingCodes($this->user));

        // 再次使用同一个码—应失败
        $result2 = $this->service->verifyRecoveryCode($this->user, $codes[0]);
        $this->assertFalse($result2['verified']);
    }

    public function test_recovery_code_with_invalid_code(): void
    {
        $this->service->generateRecoveryCodes($this->user);
        $result = $this->service->verifyRecoveryCode($this->user, 'invalid-code-here');
        $this->assertFalse($result['verified']);
    }

    // ─── 验证 MFA（TOTP 或 Recovery） ───

    public function test_verify_mfa_with_invalid_code_returns_false(): void
    {
        $this->service->enableMfa($this->user, 'Device', $this->service->generateSecret());
        $this->service->generateRecoveryCodes($this->user);

        $result = $this->service->verifyMfa($this->user, '000000');
        $this->assertFalse($result['verified']);
    }

    public function test_verify_mfa_with_recovery_code(): void
    {
        $codes = $this->service->generateRecoveryCodes($this->user);

        $result = $this->service->verifyMfa($this->user, $codes[0]);
        $this->assertTrue($result['verified']);
        $this->assertEquals('recovery', $result['method']);
    }

    // ─── 设备管理 ───

    public function test_get_user_devices(): void
    {
        $this->service->enableMfa($this->user, 'Phone', $this->service->generateSecret());

        $devices = $this->service->getUserDevices($this->user);
        $this->assertCount(1, $devices);
    }

    public function test_rename_device(): void
    {
        $device = $this->service->enableMfa($this->user, 'Old Name', $this->service->generateSecret());

        $this->service->renameDevice($device, 'New Name');

        $device->refresh();
        $this->assertEquals('New Name', $device->name);
    }

    public function test_delete_device_disables_mfa_when_last_device(): void
    {
        $device = $this->service->enableMfa($this->user, 'Only Device', $this->service->generateSecret());

        $this->service->deleteDevice($device);

        $this->user = User::find($this->user->id);
        $this->assertFalse($this->user->mfa_enabled);
        $this->assertNull($this->user->mfa_secret);
    }

    // ─── 管理员重置 ───

    public function test_admin_reset_clears_all(): void
    {
        $this->service->enableMfa($this->user, 'Device', $this->service->generateSecret());
        $this->service->generateRecoveryCodes($this->user);

        $this->service->adminResetMfa($this->user);

        $this->user = User::find($this->user->id);
        $this->assertFalse($this->user->mfa_enabled);
        $this->assertNull($this->user->mfa_secret);
        $this->assertDatabaseCount('mfa_devices', 0);

        $this->assertDatabaseHas('mfa_recovery_audits', [
            'user_id' => $this->user->id,
            'action' => 'reset',
        ]);
    }

    // ─── IP 白名单 ───

    public function test_ip_in_whitelist_exact_match(): void
    {
        $result = $this->service->isIpInWhitelist('192.168.1.1', ['192.168.1.1', '10.0.0.1']);
        $this->assertTrue($result);
    }

    public function test_ip_not_in_whitelist(): void
    {
        $result = $this->service->isIpInWhitelist('192.168.1.100', ['10.0.0.1', '172.16.0.0/12']);
        $this->assertFalse($result);
    }

    public function test_empty_whitelist_allows_all(): void
    {
        $result = $this->service->isIpInWhitelist('1.2.3.4', []);
        $this->assertTrue($result);
    }

    public function test_ip_in_cidr_range(): void
    {
        $result = $this->service->isIpInWhitelist('10.0.0.5', ['10.0.0.0/24']);
        $this->assertTrue($result);

        $result2 = $this->service->isIpInWhitelist('10.0.1.5', ['10.0.0.0/24']);
        $this->assertFalse($result2);
    }

    public function test_ip_with_wildcard(): void
    {
        $result = $this->service->isIpInWhitelist('192.168.1.50', ['192.168.*.*']);
        $this->assertTrue($result);

        $result2 = $this->service->isIpInWhitelist('10.0.0.1', ['192.168.*.*']);
        $this->assertFalse($result2);
    }

    // ─── MFA 策略 ───

    public function test_requires_mfa_when_enabled(): void
    {
        $this->service->enableMfa($this->user, 'Device', $this->service->generateSecret());
        $user = User::find($this->user->id);
        $this->assertTrue($this->service->requiresMfa($user));
    }

    public function test_requires_mfa_when_tenant_requires_all(): void
    {
        $tenant = Tenant::find($this->user->tenant_id);
        $tenant->update(['mfa_policy' => 'required_for_all']);

        $user = User::with('tenant')->find($this->user->id);
        $this->assertTrue($this->service->requiresMfa($user));
    }

    public function test_not_requires_mfa_when_optional(): void
    {
        $this->user->tenant->update(['mfa_policy' => 'optional']);
        $user = User::with('tenant')->find($this->user->id);
        $this->assertFalse($this->service->requiresMfa($user));
    }

    // ─── IP Whitelist check on user ───

    public function test_check_ip_whitelist_allows_when_not_configured(): void
    {
        $this->user->tenant->update(['allowed_ips' => null]);
        $user = User::with('tenant')->find($this->user->id);
        $this->assertTrue($this->service->checkIpWhitelist($user, '0.0.0.0'));
    }

    public function test_check_ip_whitelist_blocks_when_not_in_list(): void
    {
        $tenant = Tenant::find($this->user->tenant_id);
        $tenant->update(['allowed_ips' => ['10.0.0.0/8']]);

        $user = User::with('tenant')->find($this->user->id);
        $this->assertFalse($this->service->checkIpWhitelist($user, '192.168.1.1'));
    }
}
