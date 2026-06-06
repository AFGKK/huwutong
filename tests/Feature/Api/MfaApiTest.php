<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Services\MfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MfaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private MfaService $mfaService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mfaService = app(MfaService::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    protected function authHeaders(): array
    {
        $token = $this->user->createToken('test-token')->plainTextToken;
        return ['Authorization' => 'Bearer ' . $token];
    }

    protected function authWithMfa(): array
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);
        return [
            'headers' => [
                ...$this->authHeaders(),
                'X-MFA-Code' => $this->generateValidTotp($secret),
            ],
            'secret' => $secret,
        ];
    }

    // ─── 获取 TOTP 设置 ───

    public function test_get_setup_requires_auth(): void
    {
        $this->getJson('/api/mfa/setup')
            ->assertStatus(401);
    }

    public function test_get_setup_returns_secret(): void
    {
        $response = $this->getJson('/api/mfa/setup', $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['secret', 'uri', 'digits', 'interval'],
            ]);

        $this->assertEquals(32, strlen($response->json('data.secret')));
    }

    // ─── 确认并启用 MFA ───

    public function test_confirm_enables_mfa(): void
    {
        $secret = $this->mfaService->generateSecret();

        $code = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/mfa/confirm', [
            'secret' => $secret,
            'code' => $code,
            'device_name' => '我的手机',
        ], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('message', 'MFA 已启用')
            ->assertJsonStructure([
                'data' => ['device', 'recovery_codes'],
            ]);

        $this->assertCount(10, $response->json('data.recovery_codes'));

        $this->user = User::find($this->user->id);
        $this->assertTrue($this->user->mfa_enabled);
    }

    public function test_confirm_with_invalid_code_returns_error(): void
    {
        $secret = $this->mfaService->generateSecret();

        $response = $this->postJson('/api/mfa/confirm', [
            'secret' => $secret,
            'code' => '000000',
        ], $this->authHeaders());

        $response->assertStatus(400);
    }

    // ─── 验证 MFA ───

    public function test_verify_with_totp_succeeds(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $code = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/mfa/verify', [
            'code' => $code,
        ], [
            ...$this->authHeaders(),
            'X-MFA-Code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.method', 'totp');
    }

    public function test_verify_with_recovery_code_succeeds(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);
        $codes = $this->mfaService->generateRecoveryCodes($this->user);
        $totpCode = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/mfa/verify', [
            'code' => $codes[0],
        ], [
            ...$this->authHeaders(),
            'X-MFA-Code' => $totpCode,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.method', 'recovery');
    }

    // ─── 设备管理 ───

    public function test_list_devices(): void
    {
        $mfa = $this->authWithMfa();

        $response = $this->getJson('/api/mfa/devices', $mfa['headers']);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_rename_device(): void
    {
        $mfa = $this->authWithMfa();

        $devices = $this->mfaService->getUserDevices($this->user);
        $deviceId = $devices->first()->id;

        $response = $this->putJson("/api/mfa/devices/{$deviceId}/rename", [
            'name' => '新设备名称',
        ], $mfa['headers']);

        $response->assertStatus(200)
            ->assertJsonPath('message', '设备已重命名');
    }

    public function test_delete_device(): void
    {
        $mfa = $this->authWithMfa();

        $devices = $this->mfaService->getUserDevices($this->user);
        $deviceId = $devices->first()->id;

        $response = $this->deleteJson("/api/mfa/devices/{$deviceId}", [], $mfa['headers']);

        $response->assertStatus(200)
            ->assertJsonPath('message', '设备已解绑');
    }

    // ─── 恢复码管理 ───

    public function test_recovery_codes_status(): void
    {
        $mfa = $this->authWithMfa();
        $this->mfaService->generateRecoveryCodes($this->user);

        $response = $this->getJson('/api/mfa/recovery-codes', $mfa['headers']);

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 10)
            ->assertJsonPath('data.remaining', 10)
            ->assertJsonPath('data.has_codes', true);
    }

    public function test_regenerate_recovery_codes(): void
    {
        $mfa = $this->authWithMfa();
        $this->mfaService->generateRecoveryCodes($this->user);

        $response = $this->postJson('/api/mfa/recovery-codes/regenerate', [], $mfa['headers']);

        $response->assertStatus(200)
            ->assertJsonCount(10, 'data.recovery_codes');
    }

    // ─── 禁用 MFA ───

    public function test_disable_mfa_with_valid_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $codes = $this->mfaService->generateRecoveryCodes($this->user);
        $totpCode = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/mfa/disable', [
            'code' => $codes[0],
        ], [
            ...$this->authHeaders(),
            'X-MFA-Code' => $totpCode,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'MFA 已禁用');

        $this->user = User::find($this->user->id);
        $this->assertFalse($this->user->mfa_enabled);
    }

    // ─── MFA 强制登录 ───

    public function test_check_required_when_mfa_enabled(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $response = $this->postJson('/api/mfa/check-required', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonPath('data.mfa_enabled', true);
    }

    public function test_check_required_when_tenant_requires_all(): void
    {
        $this->tenant->update(['mfa_policy' => 'required_for_all']);

        $response = $this->postJson('/api/mfa/check-required', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonPath('data.mfa_enabled', false)
            ->assertJsonPath('data.mfa_setup_required', true);
    }

    public function test_check_required_with_wrong_password(): void
    {
        $response = $this->postJson('/api/mfa/check-required', [
            'email' => $this->user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401);
    }

    public function test_mfa_login_with_totp(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $code = $this->generateValidTotp($secret);

        $response = $this->postJson('/api/mfa/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'mfa_code' => $code,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['user', 'token', 'mfa_method']])
            ->assertJsonPath('data.mfa_method', 'totp');
    }

    public function test_mfa_login_with_invalid_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $response = $this->postJson('/api/mfa/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'mfa_code' => '000000',
        ]);

        $response->assertStatus(401);
    }

    public function test_mfa_login_with_recovery_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);
        $codes = $this->mfaService->generateRecoveryCodes($this->user);

        $response = $this->postJson('/api/mfa/login', [
            'email' => $this->user->email,
            'password' => 'password',
            'mfa_code' => $codes[0],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.mfa_method', 'recovery');
    }

    // ─── MFA 中间件测试 ───

    public function test_mfa_middleware_blocks_without_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $response = $this->getJson('/api/mfa/devices', $this->authHeaders());

        $response->assertStatus(403);
    }

    public function test_mfa_middleware_passes_with_valid_code(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $code = $this->generateValidTotp($secret);

        $response = $this->getJson('/api/mfa/devices', [
            ...$this->authHeaders(),
            'X-MFA-Code' => $code,
        ]);

        $response->assertStatus(200);
    }

    public function test_ip_whitelist_blocks_unauthorized_ip(): void
    {
        $this->tenant->update(['allowed_ips' => ['10.0.0.0/8']]);

        $response = $this->getJson('/api/mfa/setup', $this->authHeaders());

        $response->assertStatus(403);
    }

    // ─── 管理接口 ───

    public function test_admin_reset_user_mfa(): void
    {
        $secret = $this->mfaService->generateSecret();
        $this->setupMfa($secret);

        $response = $this->postJson("/api/admin/users/{$this->user->id}/reset-mfa", [], $this->authHeaders());

        $response->assertStatus(200)
            ->assertJsonPath('message', '用户 MFA 已重置');

        $this->user = User::find($this->user->id);
        $this->assertFalse($this->user->mfa_enabled);
    }

    // ─── Helpers ───

    protected function setupMfa(string $secret): void
    {
        $this->mfaService->enableMfa($this->user, 'Test Device', $secret);
        $this->user = User::find($this->user->id);
    }

    protected function generateValidTotp(string $secret): string
    {
        $ref = new \ReflectionMethod($this->mfaService, 'generateOTP');
        $ref->setAccessible(true);
        $secretBin = $this->callProtectedMethod('base32Decode', [$secret]);
        $counter = floor(time() / 30);

        return $ref->invoke($this->mfaService, $secretBin, (int) $counter);
    }

    protected function callProtectedMethod(string $method, array $args): mixed
    {
        $ref = new \ReflectionMethod($this->mfaService, $method);
        $ref->setAccessible(true);
        return $ref->invokeArgs($this->mfaService, $args);
    }
}
