<?php

namespace Tests\Unit\Services;

use App\Models\CorsConfig;
use App\Services\CorsManagerService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CorsManager 纯逻辑测试 — 使用 PHPUnit mock 避免数据库连接
 */
class CorsManagerTest extends TestCase
{
    protected CorsManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CorsManagerService();
    }

    #[Test]
    public function resolve_config_matches_origin_and_route(): void
    {
        // Create a config via the model (no DB needed if we mock getActiveConfigs)
        $config = new CorsConfig([
            'name' => 'Example Config',
            'allowed_origins' => ['https://example.com'],
            'route_pattern' => 'api/*',
            'priority' => 10,
        ]);
        $config->id = 1;

        // Use reflection to inject configs directly
        $this->injectConfigs([$config]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Origin', 'https://example.com');

        $resolved = $this->service->resolveConfig($request);
        $this->assertNotNull($resolved);
        $this->assertEquals('Example Config', $resolved->name);
    }

    #[Test]
    public function no_match_returns_null_when_origin_differs(): void
    {
        $config = new CorsConfig([
            'name' => 'Example Config',
            'allowed_origins' => ['https://example.com'],
            'route_pattern' => 'api/*',
            'priority' => 10,
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Origin', 'https://other.com');

        $this->assertNull($this->service->resolveConfig($request));
    }

    #[Test]
    public function no_match_returns_null_when_route_differs(): void
    {
        $config = new CorsConfig([
            'name' => 'Example Config',
            'allowed_origins' => ['https://example.com'],
            'route_pattern' => 'api/*',
            'priority' => 10,
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/admin/test', 'GET');
        $request->headers->set('Origin', 'https://example.com');

        $this->assertNull($this->service->resolveConfig($request));
    }

    #[Test]
    public function wildcard_origin_matches_anything(): void
    {
        $config = new CorsConfig([
            'name' => 'Wildcard',
            'allowed_origins' => ['*'],
            'route_pattern' => 'api/*',
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Origin', 'https://anything.com');

        $this->assertNotNull($this->service->resolveConfig($request));
    }

    #[Test]
    public function build_headers_with_matching_config(): void
    {
        $config = new CorsConfig([
            'name' => 'Test Headers',
            'allowed_origins' => ['https://app.example.com'],
            'allowed_methods' => ['GET', 'POST'],
            'allowed_headers' => ['Content-Type', 'Authorization'],
            'exposed_headers' => ['X-Custom'],
            'allow_credentials' => true,
            'max_age' => 3600,
            'route_pattern' => 'api/*',
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/api/test', 'OPTIONS');
        $request->headers->set('Origin', 'https://app.example.com');

        $headers = $this->service->buildHeaders($request);

        $this->assertEquals('https://app.example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertEquals('GET, POST', $headers['Access-Control-Allow-Methods']);
        $this->assertEquals('true', $headers['Access-Control-Allow-Credentials']);
        $this->assertEquals('3600', $headers['Access-Control-Max-Age']);
    }

    #[Test]
    public function build_headers_returns_fallback_when_no_config(): void
    {
        $this->injectConfigs([]);

        $request = Request::create('/api/test', 'GET');
        $request->headers->set('Origin', 'https://example.com');

        $headers = $this->service->buildHeaders($request);

        $this->assertArrayHasKey('Access-Control-Allow-Origin', $headers);
        $this->assertEquals('https://example.com', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * 辅助方法：通过反射注入内存配置列表到 Service
     */
    protected function injectConfigs(array $configs): void
    {
        $ref = new \ReflectionClass($this->service);
        $method = $ref->getMethod('getActiveConfigs');

        // 清除缓存
        $this->service->clearCache();

        // Mock Cache to return our configs
        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($configs);
    }
}
