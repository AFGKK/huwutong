<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
    }

    public function test_register_creates_user(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => '测试用户',
            'email' => 'test@example.com',
            'phone' => '13800138000',
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'phone' => '13800138000',
        ]);
    }

    public function test_register_validates_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_login_with_email(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password' => bcrypt('Test@123456'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Test@123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);
    }

    public function test_login_with_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'wrong@example.com',
            'password' => bcrypt('correct'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'wrong@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('error.code', 'AUTH_FAILED');
    }

    public function test_user_endpoint_returns_authenticated_user(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_user_endpoint_requires_auth(): void
    {
        $this->getJson('/api/user')->assertStatus(401);
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/logout', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);

        // Token 已被删除（仅统计当前用户）
        $this->assertSame(0, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_refresh_token_returns_new_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->postJson('/api/token/refresh', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['token']]);

        // 旧 token 被删除，新 token 被创建
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());
    }

    public function test_refresh_token_requires_auth(): void
    {
        $this->postJson('/api/token/refresh')->assertStatus(401);
    }

    public function test_old_token_invalid_after_refresh(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // 刷新
        $response = $this->postJson('/api/token/refresh', [], [
            'Authorization' => 'Bearer ' . $token,
        ]);

        $response->assertStatus(200);
        $newToken = $response->json('data.token');
        $this->assertNotNull($newToken);

        // 旧 token 已从数据库中删除（应只有 1 个新 token）
        $this->assertSame(1, PersonalAccessToken::where('tokenable_id', $user->id)->count());

        // 新 token 可以正常使用
        $this->getJson('/api/user', [
            'Authorization' => 'Bearer ' . $newToken,
        ])->assertStatus(200);

        // 旧 token 已被删除（数据库层面验证）
        $this->assertDatabaseMissing('personal_access_tokens', [
            'token' => hash('sha256', explode('|', $token)[1] ?? $token),
        ]);
    }

    public function test_phone_send_code_and_login_creates_user(): void
    {
        config(['sms.driver' => 'log']);

        $send = $this->postJson('/api/phone/send-code', [
            'phone' => '13900139000',
            'scene' => 'login',
        ]);
        $send->assertOk()->assertJsonPath('success', true);

        $code = \Illuminate\Support\Facades\Cache::get('phone_code:13900139000');
        $this->assertNotEmpty($code);

        $login = $this->postJson('/api/phone/login', [
            'phone' => '13900139000',
            'code' => $code,
        ]);

        $login->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token', 'is_new']]);

        $this->assertDatabaseHas('users', [
            'phone' => '13900139000',
        ]);
        $this->assertNull(User::where('phone', '13900139000')->value('email'));
    }

    public function test_phone_register_with_password(): void
    {
        config(['sms.driver' => 'log']);

        $this->postJson('/api/phone/send-code', [
            'phone' => '13900139001',
            'scene' => 'register',
        ])->assertOk();

        $code = \Illuminate\Support\Facades\Cache::get('phone_code:13900139001');

        $response = $this->postJson('/api/phone/register', [
            'name' => '手机用户',
            'phone' => '13900139001',
            'code' => $code,
            'password' => 'Test@123456',
            'password_confirmation' => 'Test@123456',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['user', 'token']]);

        $this->assertDatabaseHas('users', [
            'phone' => '13900139001',
            'name' => '手机用户',
        ]);
    }

    public function test_phone_register_rejects_existing_phone_on_send_code(): void
    {
        User::factory()->create(['phone' => '13900139002', 'email' => 'p2@example.com']);

        $this->postJson('/api/phone/send-code', [
            'phone' => '13900139002',
            'scene' => 'register',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PHONE_EXISTS');
    }
}
