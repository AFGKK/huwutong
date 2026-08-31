<?php

namespace Tests\Feature\Api;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MiniProgramLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 设置微信小程序配置
        SiteSetting::create([
            'key' => 'wechat_mini_program_appid',
            'value' => 'wx_test_appid',
            'group' => 'wechat',
        ]);
        SiteSetting::create([
            'key' => 'wechat_mini_program_secret',
            'value' => 'test_secret_key',
            'group' => 'wechat',
        ]);
    }

    /** @test */
    public function it_rejects_missing_code()
    {
        $response = $this->postJson('/api/miniprogram/login', []);

        $response->assertStatus(422);
    }

    /** @test */
    public function it_returns_error_on_wechat_api_failure()
    {
        Http::fake([
            'api.weixin.qq.com/*' => Http::response([
                'errcode' => 40029,
                'errmsg' => 'invalid code',
            ], 200),
        ]);

        $response = $this->postJson('/api/miniprogram/login', [
            'code' => 'invalid_code_001',
        ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'WECHAT_LOGIN_FAILED',
            ],
        ]);
    }

    /** @test */
    public function it_creates_new_user_on_first_login()
    {
        Http::fake([
            'api.weixin.qq.com/*' => Http::response([
                'openid' => 'wx_openid_new_001',
                'unionid' => 'wx_unionid_new_001',
                'session_key' => 'mock_session_key',
            ], 200),
        ]);

        $this->assertDatabaseMissing('users', ['wechat_openid' => 'wx_openid_new_001']);

        $response = $this->postJson('/api/miniprogram/login', [
            'code' => 'valid_code_new_user',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'device_bind_id',
                'user' => ['id', 'name', 'avatar'],
            ],
        ]);

        $this->assertDatabaseHas('users', [
            'wechat_openid' => 'wx_openid_new_001',
            'wechat_unionid' => 'wx_unionid_new_001',
            'source' => 'wechat_miniprogram',
        ]);

        $this->assertStringStartsWith('wx_mp_', $response->json('data.device_bind_id'));
        $token = $response->json('data.token');
        $this->assertNotNull($token);
    }

    /** @test */
    public function it_returns_existing_user_token_on_subsequent_login()
    {
        User::factory()->create([
            'wechat_openid' => 'wx_openid_existing_001',
            'wechat_unionid' => 'wx_unionid_existing_001',
            'source' => 'wechat_miniprogram',
        ]);

        Http::fake([
            'api.weixin.qq.com/*' => Http::response([
                'openid' => 'wx_openid_existing_001',
                'unionid' => 'wx_unionid_existing_001',
                'session_key' => 'mock_session_key',
            ], 200),
        ]);

        $response = $this->postJson('/api/miniprogram/login', [
            'code' => 'valid_code_existing_user',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'avatar'],
            ],
        ]);

        $this->assertEquals(1, User::where('wechat_openid', 'wx_openid_existing_001')->count());
    }

    /** @test */
    public function it_handles_site_setting_missing_config()
    {
        // 清空配置
        SiteSetting::where('key', 'wechat_mini_program_appid')->delete();
        SiteSetting::where('key', 'wechat_mini_program_secret')->delete();

        $response = $this->postJson('/api/miniprogram/login', [
            'code' => 'test_code',
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'error' => [
                'code' => 'WECHAT_CONFIG_INCOMPLETE',
            ],
        ]);
    }

    /** @test */
    public function it_stores_wechat_openid_unique_constraint()
    {
        User::factory()->create([
            'wechat_openid' => 'wx_unique_openid',
            'source' => 'wechat_miniprogram',
        ]);

        Http::fake([
            'api.weixin.qq.com/*' => Http::response([
                'openid' => 'wx_unique_openid',
                'session_key' => 'mock_key',
            ], 200),
        ]);

        $response = $this->postJson('/api/miniprogram/login', [
            'code' => 'duplicate_code',
        ]);

        $response->assertStatus(200);
        // 验证 data.user.id 存在且为整数
        $this->assertIsInt($response->json('data.user.id'));
    }
}
