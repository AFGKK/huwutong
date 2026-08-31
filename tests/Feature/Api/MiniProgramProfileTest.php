<?php

namespace Tests\Feature\Api;

use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MiniProgramProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SiteSetting::updateOrCreate(
            ['key' => 'wechat_mini_program_appid'],
            ['value' => 'wx_test_appid', 'group' => 'wechat', 'type' => 'text']
        );
        SiteSetting::updateOrCreate(
            ['key' => 'wechat_mini_program_secret'],
            ['value' => 'test_secret', 'group' => 'wechat', 'type' => 'password']
        );
    }

    public function test_profile_requires_auth(): void
    {
        $this->getJson('/api/miniprogram/profile')->assertUnauthorized();
    }

    public function test_profile_returns_masked_phone(): void
    {
        $user = User::factory()->create([
            'wechat_openid' => 'openid_profile_001',
            'phone' => '13812345678',
            'phone_verified_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/miniprogram/profile');

        $response->assertOk();
        $response->assertJsonPath('data.phone', '13812345678');
        $response->assertJsonPath('data.phone_masked', '138****5678');
        $response->assertJsonPath('data.phone_verified', true);
        $this->assertStringStartsWith('wx_mp_', $response->json('data.device_bind_id'));
    }

    public function test_bind_phone_requires_auth(): void
    {
        $this->postJson('/api/miniprogram/bind-phone', [
            'code' => 'phone_code',
        ])->assertUnauthorized();
    }

    public function test_bind_phone_success(): void
    {
        Http::fake([
            'api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'mock_access_token',
                'expires_in' => 7200,
            ], 200),
            'api.weixin.qq.com/wxa/business/getuserphonenumber*' => Http::response([
                'errcode' => 0,
                'errmsg' => 'ok',
                'phone_info' => [
                    'phoneNumber' => '+8613911112222',
                    'purePhoneNumber' => '13911112222',
                    'countryCode' => '86',
                ],
            ], 200),
        ]);

        $user = User::factory()->create([
            'wechat_openid' => 'openid_bind_001',
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/miniprogram/bind-phone', [
            'code' => 'valid_phone_code',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.phone', '13911112222');
        $response->assertJsonPath('data.phone_masked', '139****2222');

        $user->refresh();
        $this->assertSame('13911112222', $user->phone);
        $this->assertNotNull($user->phone_verified_at);
    }

    public function test_bind_phone_rejects_taken_number(): void
    {
        Http::fake([
            'api.weixin.qq.com/cgi-bin/token*' => Http::response([
                'access_token' => 'mock_access_token',
                'expires_in' => 7200,
            ], 200),
            'api.weixin.qq.com/wxa/business/getuserphonenumber*' => Http::response([
                'errcode' => 0,
                'phone_info' => [
                    'purePhoneNumber' => '13900001111',
                    'phoneNumber' => '13900001111',
                ],
            ], 200),
        ]);

        User::factory()->create(['phone' => '13900001111']);

        $user = User::factory()->create([
            'wechat_openid' => 'openid_bind_taken',
            'phone' => null,
        ]);

        Sanctum::actingAs($user);

        $this->postJson('/api/miniprogram/bind-phone', [
            'code' => 'taken_phone_code',
        ])->assertStatus(422)
            ->assertJsonPath('error.code', 'PHONE_TAKEN');
    }

    public function test_my_activations_lists_devices_by_openid_fingerprint(): void
    {
        $openid = 'openid_activations_001';
        $fingerprint = 'wx_mp_' . substr(hash('sha256', $openid), 0, 24);

        $user = User::factory()->create(['wechat_openid' => $openid]);
        $product = Product::factory()->create(['name' => '互物通企业版']);
        $license = License::factory()->create([
            'product_id' => $product->id,
            'license_key' => 'HWT-ACT-TEST-0001',
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        Device::factory()->create([
            'license_id' => $license->id,
            'fingerprint' => $fingerprint,
            'platform' => 'wechat_miniprogram',
            'metadata' => ['device_name' => '我的手机'],
            'last_seen_at' => now(),
        ]);

        // 其他用户设备不应出现
        Device::factory()->create([
            'fingerprint' => 'wx_mp_other_fingerprint_xxxx',
            'platform' => 'wechat_miniprogram',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/miniprogram/my-activations');

        $response->assertOk();
        $response->assertJsonPath('data.total', 1);
        $response->assertJsonPath('data.items.0.license_key', 'HWT-ACT-TEST-0001');
        $response->assertJsonPath('data.items.0.product_name', '互物通企业版');
        $response->assertJsonPath('data.items.0.device_name', '我的手机');
        $response->assertJsonPath('data.items.0.is_expired', false);
    }

    public function test_my_activations_requires_wechat_openid(): void
    {
        $user = User::factory()->create(['wechat_openid' => null]);
        Sanctum::actingAs($user);

        $this->getJson('/api/miniprogram/my-activations')
            ->assertStatus(401)
            ->assertJsonPath('error.code', 'WECHAT_OPENID_REQUIRED');
    }
}
