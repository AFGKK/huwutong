<?php

namespace Tests\Feature\Api;

use App\Models\License;
use App\Models\MiniprogramExpirySubscription;
use App\Models\Product;
use App\Models\SiteSetting;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MiniProgramSubscribeTest extends TestCase
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
        SiteSetting::updateOrCreate(
            ['key' => 'wechat_mini_subscribe_template_id'],
            ['value' => 'TMPL_TEST_001', 'group' => 'wechat', 'type' => 'text']
        );
    }

    public function test_subscribe_config_returns_template(): void
    {
        $response = $this->getJson('/api/miniprogram/subscribe-config');

        $response->assertOk();
        $response->assertJsonPath('data.enabled', true);
        $response->assertJsonPath('data.template_id', 'TMPL_TEST_001');
    }

    public function test_subscribe_expiry_requires_auth(): void
    {
        $this->postJson('/api/miniprogram/subscribe-expiry', [
            'license_key' => 'HWT-TEST',
        ])->assertUnauthorized();
    }

    public function test_subscribe_expiry_stores_subscription(): void
    {
        $user = User::factory()->create([
            'wechat_openid' => 'openid_subscribe_001',
        ]);

        $product = Product::factory()->create();
        $license = License::factory()->create([
            'product_id' => $product->id,
            'license_key' => 'HWT-SUB-TEST-0001',
            'status' => 'active',
            'expires_at' => now()->addDays(10),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/miniprogram/subscribe-expiry', [
            'license_key' => $license->license_key,
            'remind_days' => 7,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('miniprogram_expiry_subscriptions', [
            'user_id' => $user->id,
            'license_key' => $license->license_key,
            'wechat_openid' => 'openid_subscribe_001',
            'status' => 'active',
        ]);
    }
}
