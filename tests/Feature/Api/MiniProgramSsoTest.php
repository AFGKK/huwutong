<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\MiniProgramSsoController;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MiniProgramSsoTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_requires_auth(): void
    {
        $this->postJson('/api/miniprogram/h5-sso')->assertUnauthorized();
    }

    public function test_issue_returns_one_time_code(): void
    {
        $user = User::factory()->create([
            'wechat_openid' => 'openid_sso_issue',
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/miniprogram/h5-sso');

        $response->assertOk();
        $code = $response->json('data.code');
        $this->assertNotEmpty($code);
        $this->assertGreaterThanOrEqual(32, strlen($code));
        $this->assertTrue(Cache::has(MiniProgramSsoController::CACHE_PREFIX . $code));
    }

    public function test_exchange_returns_sanctum_token_and_consumes_code(): void
    {
        $user = User::factory()->create([
            'name' => 'SSO用户',
            'wechat_openid' => 'openid_sso_ex',
        ]);
        Sanctum::actingAs($user);

        $issue = $this->postJson('/api/miniprogram/h5-sso');
        $code = $issue->json('data.code');

        $exchange = $this->postJson('/api/miniprogram/h5-sso/exchange', [
            'code' => $code,
        ]);

        $exchange->assertOk();
        $this->assertNotEmpty($exchange->json('data.token'));
        $this->assertSame($user->id, $exchange->json('data.user.id'));
        $this->assertSame('SSO用户', $exchange->json('data.user.name'));

        // 二次兑换失败
        $this->postJson('/api/miniprogram/h5-sso/exchange', [
            'code' => $code,
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'SSO_CODE_INVALID');
    }

    public function test_exchange_rejects_invalid_code(): void
    {
        $this->postJson('/api/miniprogram/h5-sso/exchange', [
            'code' => str_repeat('a', 40),
        ])->assertStatus(400)
            ->assertJsonPath('error.code', 'SSO_CODE_INVALID');
    }

    public function test_bridge_page_is_reachable(): void
    {
        $this->get('/miniprogram/bridge?redirect=/products')
            ->assertOk()
            ->assertSee('h5-sso/exchange', false);
    }
}
