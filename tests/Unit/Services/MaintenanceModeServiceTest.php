<?php

namespace Tests\Unit\Services;

use App\Models\MaintenanceConfig;
use App\Services\MaintenanceModeService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceModeServiceTest extends TestCase
{
    protected MaintenanceModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaintenanceModeService();
    }

    #[Test]
    public function is_active_returns_false_when_no_config(): void
    {
        $this->injectConfig(null);
        $this->assertFalse($this->service->isActive());
    }

    #[Test]
    public function is_active_returns_true_when_enabled(): void
    {
        // Mock the config
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'retry_after' => 60,
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $this->assertTrue($this->service->isActive());
    }

    #[Test]
    public function is_active_returns_false_when_auto_disable_expired(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'auto_disable_at' => now()->subHour(),
            'retry_after' => 60,
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $this->assertFalse($this->service->isActive());
    }

    #[Test]
    public function can_bypass_with_whitelisted_ip(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'whitelist_ips' => ['192.168.1.1', '10.0.0.*'],
            'retry_after' => 60,
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $this->assertTrue($this->service->canBypass('192.168.1.1', '/api/test'));
    }

    #[Test]
    public function can_bypass_with_whitelisted_path(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'whitelist_paths' => ['api/health/*', 'api/maintenance/*'],
            'retry_after' => 60,
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $this->assertTrue($this->service->canBypass('127.0.0.1', 'api/health/live'));
        $this->assertTrue($this->service->canBypass('127.0.0.1', 'api/maintenance/status'));
        $this->assertFalse($this->service->canBypass('127.0.0.1', 'api/license/activate'));
    }

    #[Test]
    public function cannot_bypass_when_not_whitelisted(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'whitelist_ips' => ['192.168.1.1'],
            'whitelist_paths' => ['api/health/*'],
            'retry_after' => 60,
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $this->assertFalse($this->service->canBypass('10.0.0.1', 'api/license/activate'));
    }

    #[Test]
    public function get_maintenance_data_returns_defaults_when_no_config(): void
    {
        $this->injectConfig(null);

        $data = $this->service->getMaintenanceData();

        $this->assertEquals('系统维护中', $data['title']);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('retry_after', $data);
    }

    #[Test]
    public function get_maintenance_data_returns_config_values(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'title' => '计划内维护',
            'message' => '预计 2 小时后恢复',
            'retry_after' => 120,
            'scheduled_end_at' => now()->addHours(2),
        ]);
        $config->id = 1;

        $this->injectConfig($config);

        $data = $this->service->getMaintenanceData();

        $this->assertEquals('计划内维护', $data['title']);
        $this->assertEquals(120, $data['retry_after']);
        $this->assertNotNull($data['scheduled_end_at']);
    }

    #[Test]
    public function whitelisted_ip_check(): void
    {
        $config = new MaintenanceConfig([
            'whitelist_ips' => ['192.168.1.1', '10.0.0.1'],
        ]);

        $this->assertTrue($config->isIpWhitelisted('192.168.1.1'));
        $this->assertTrue($config->isIpWhitelisted('10.0.0.1'));
        $this->assertFalse($config->isIpWhitelisted('192.168.1.2'));
    }

    #[Test]
    public function whitelisted_path_check(): void
    {
        $config = new MaintenanceConfig([
            'whitelist_paths' => ['api/health/*', 'api/maintenance/*'],
        ]);

        $this->assertTrue($config->isPathWhitelisted('api/health/live'));
        $this->assertTrue($config->isPathWhitelisted('api/health/ready'));
        $this->assertTrue($config->isPathWhitelisted('api/maintenance/status'));
        $this->assertFalse($config->isPathWhitelisted('api/license/activate'));
    }

    /**
     * 辅助：Mock Cache 注入配置
     */
    protected function injectConfig(?MaintenanceConfig $config): void
    {
        \Illuminate\Support\Facades\Cache::shouldReceive('forget')
            ->zeroOrMoreTimes()
            ->andReturn(true);

        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($config);
    }
}
