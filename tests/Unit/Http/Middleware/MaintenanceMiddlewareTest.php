<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\MaintenanceMiddleware;
use App\Models\MaintenanceConfig;
use App\Services\MaintenanceModeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MaintenanceMiddlewareTest extends TestCase
{
    protected MaintenanceModeService $service;
    protected MaintenanceMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MaintenanceModeService();
        $this->middleware = new MaintenanceMiddleware($this->service);
    }

    #[Test]
    public function passes_when_maintenance_not_active(): void
    {
        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn(null);

        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function returns_503_when_maintenance_active(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'title' => '维护中',
            'message' => '请稍后再试',
            'retry_after' => 60,
        ]);
        $config->id = 1;

        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($config);

        $request = Request::create('/api/test', 'GET');

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(503, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $this->assertEquals('MAINTENANCE_MODE', $content['error']['code'] ?? '');
        $this->assertEquals('60', $response->headers->get('Retry-After'));
    }

    #[Test]
    public function whitelisted_ip_bypasses_maintenance(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'whitelist_ips' => ['192.168.1.1'],
            'retry_after' => 60,
        ]);
        $config->id = 1;

        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($config);

        $request = Request::create('/api/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.1']);

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }

    #[Test]
    public function whitelisted_path_bypasses_maintenance(): void
    {
        $config = new MaintenanceConfig([
            'is_enabled' => true,
            'whitelist_paths' => ['api/health/*'],
            'retry_after' => 60,
        ]);
        $config->id = 1;

        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($config);

        $request = Request::create('/api/health/live', 'GET');

        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
