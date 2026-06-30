<?php

namespace Tests\Unit\Services;

use App\Models\LarkIntegration;
use App\Models\Tenant;
use App\Services\LarkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LarkServiceTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected LarkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->service = new LarkService();
    }

    #[Test]
    public function it_returns_null_when_no_integration_exists(): void
    {
        $integration = $this->service->getIntegration($this->tenant->id);
        $this->assertNull($integration);
    }

    #[Test]
    public function it_creates_integration_on_first_save(): void
    {
        $integration = $this->service->saveIntegration([
            'name' => '测试飞书集成',
            'app_id' => 'cli_test123',
            'is_enabled' => true,
        ], $this->tenant->id);

        $this->assertInstanceOf(LarkIntegration::class, $integration);
        $this->assertEquals('测试飞书集成', $integration->name);
        $this->assertEquals('cli_test123', $integration->app_id);
        $this->assertTrue($integration->is_enabled);
    }

    #[Test]
    public function it_updates_existing_integration(): void
    {
        $this->service->saveIntegration([
            'name' => '初始配置',
            'app_id' => 'cli_old',
        ], $this->tenant->id);

        $this->service->saveIntegration([
            'name' => '更新配置',
            'app_id' => 'cli_new',
        ], $this->tenant->id);

        $integrations = LarkIntegration::where('tenant_id', $this->tenant->id)->count();
        $this->assertEquals(1, $integrations);

        $integration = LarkIntegration::where('tenant_id', $this->tenant->id)->first();
        $this->assertEquals('更新配置', $integration->name);
        $this->assertEquals('cli_new', $integration->app_id);
    }

    #[Test]
    public function it_clears_token_when_app_credentials_change(): void
    {
        $integration = $this->service->saveIntegration([
            'name' => '测试',
            'app_id' => 'cli_test',
            'app_secret' => 'secret_old',
        ], $this->tenant->id);

        $this->assertNull($integration->tenant_token);
    }

    #[Test]
    public function it_encrypts_app_secret(): void
    {
        $this->service->saveIntegration([
            'name' => '测试',
            'app_id' => 'cli_test',
            'app_secret' => 'my_secret_value',
        ], $this->tenant->id);

        $integration = LarkIntegration::where('tenant_id', $this->tenant->id)->first();

        // DB 里存的是加密值，不是明文
        $this->assertNotEquals('my_secret_value', $integration->getRawOriginal('app_secret'));

        // 解密后应该是原始值
        $this->assertEquals('my_secret_value', $integration->getDecryptedAppSecret());
    }

    #[Test]
    public function it_retrieves_integration_for_tenant(): void
    {
        $this->service->saveIntegration([
            'name' => '我的飞书',
            'app_id' => 'cli_app',
        ], $this->tenant->id);

        $found = $this->service->getIntegration($this->tenant->id);
        $this->assertNotNull($found);
        $this->assertEquals('我的飞书', $found->name);
    }

    #[Test]
    public function test_connection_returns_failure_when_no_credentials(): void
    {
        $integration = LarkIntegration::factory()->create([
            'tenant_id' => $this->tenant->id,
            'app_id' => null,
            'app_secret' => null,
        ]);

        $result = $this->service->testConnection($integration);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['results']['tenant_token']);
    }

    #[Test]
    public function it_returns_integration_detail_without_secrets(): void
    {
        $integration = $this->service->saveIntegration([
            'name' => '测试飞书',
            'app_id' => 'cli_test_app',
            'is_enabled' => true,
        ], $this->tenant->id);

        $detail = $integration->only([
            'id', 'name', 'is_enabled', 'app_id',
            'bot_webhook_url', 'notify_enabled',
        ]);

        $this->assertEquals('测试飞书', $detail['name']);
        $this->assertEquals('cli_test_app', $detail['app_id']);
        $this->assertTrue($detail['is_enabled']);
        $this->assertStringEndsWith('_test_app', $detail['app_id']);
    }
}
