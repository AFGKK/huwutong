<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * 优化方案 1.1：弱密码拦截 + 严格 MFA（无完整会话）
 */
class AdminMfaLoginHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(array $overrides = []): User
    {
        $tenant = Tenant::factory()->create([
            'mfa_policy' => 'required_for_admin',
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'email' => 'admin-mfa@huwutong.com',
            'password' => Hash::make('StrongPass!2026'),
            'status' => 'active',
            'mfa_enabled' => false,
            'password_changed_at' => now(),
        ], $overrides));

        $user->assignRole('super-admin');

        return $user->fresh();
    }

    public function test_weak_password_is_rejected_for_admin(): void
    {
        $this->makeAdmin(['password' => Hash::make('admin123')]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin-mfa@huwutong.com',
            'password' => 'admin123',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'WEAK_PASSWORD_FORBIDDEN');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_without_mfa_gets_setup_token_only(): void
    {
        $this->makeAdmin();

        $response = $this->postJson('/api/login', [
            'email' => 'admin-mfa@huwutong.com',
            'password' => 'StrongPass!2026',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'MFA_SETUP_REQUIRED');
        $setupToken = $response->json('error.details.setup_token');
        $this->assertNotEmpty($setupToken);

        // setup token 不能访问业务接口
        $this->withToken($setupToken)
            ->getJson('/api/licenses')
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'MFA_SETUP_REQUIRED');

        // 可访问绑定接口
        $this->withToken($setupToken)
            ->getJson('/api/mfa/setup')
            ->assertOk();
    }

    public function test_admin_with_mfa_enabled_requires_mfa_login(): void
    {
        $this->makeAdmin([
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin-mfa@huwutong.com',
            'password' => 'StrongPass!2026',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'MFA_REQUIRED');
        $this->assertNull($response->json('error.details.setup_token'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_weak_password_rejected_without_pre_set_team_context(): void
    {
        // 模拟线上：请求前不保留 team id，依赖 login 内自注入
        $tenant = Tenant::factory()->create(['mfa_policy' => 'required_for_admin']);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
        Role::findOrCreate('super-admin', 'web');

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'admin-noteam@huwutong.com',
            'password' => Hash::make('admin123'),
            'status' => 'active',
            'mfa_enabled' => false,
            'password_changed_at' => now(),
        ]);
        $user->assignRole('super-admin');

        app(PermissionRegistrar::class)->setPermissionsTeamId(0);

        $response = $this->postJson('/api/login', [
            'email' => 'admin-noteam@huwutong.com',
            'password' => 'admin123',
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'WEAK_PASSWORD_FORBIDDEN');
    }
}
