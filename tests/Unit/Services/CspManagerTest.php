<?php

namespace Tests\Unit\Services;

use App\Models\CspConfig;
use App\Models\CspViolation;
use App\Services\CspManagerService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * CspManager 纯逻辑测试 — 使用 PHPUnit mock 避免数据库连接
 */
class CspManagerTest extends TestCase
{
    protected CspManagerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CspManagerService();
    }

    #[Test]
    public function to_policy_string(): void
    {
        $config = new CspConfig([
            'name' => 'Test Policy',
            'directives' => [
                'default-src' => ["'self'"],
                'script-src' => ["'self'", "'unsafe-inline'"],
            ],
        ]);
        $config->id = 1;

        $policy = $config->toPolicyString();
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline'", $policy);
    }

    #[Test]
    public function resolve_config_by_route(): void
    {
        $config = new CspConfig([
            'name' => 'API CSP',
            'directives' => ['default-src' => ["'self'"]],
            'route_pattern' => 'api/*',
            'priority' => 10,
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/api/test', 'GET');
        $resolved = $this->service->resolveConfig($request);
        $this->assertNotNull($resolved);
        $this->assertEquals('API CSP', $resolved->name);
    }

    #[Test]
    public function resolve_returns_null_when_route_not_matched(): void
    {
        $config = new CspConfig([
            'name' => 'API CSP',
            'directives' => ['default-src' => ["'self'"]],
            'route_pattern' => 'api/*',
            'priority' => 10,
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/admin/test', 'GET');
        $this->assertNull($this->service->resolveConfig($request));
    }

    #[Test]
    public function build_headers_with_enforce_config(): void
    {
        $config = new CspConfig([
            'name' => 'Enforce CSP',
            'directives' => ['default-src' => ["'self'"], 'img-src' => ["'self'", 'data:']],
            'mode' => 'enforce',
            'route_pattern' => '*',
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/any/path', 'GET');
        $headers = $this->service->buildHeaders($request);

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString('default-src', $headers['Content-Security-Policy']);
    }

    #[Test]
    public function report_only_header(): void
    {
        $config = new CspConfig([
            'name' => 'Report Only',
            'directives' => ['default-src' => ["'self'"]],
            'mode' => 'report-only',
            'route_pattern' => '*',
        ]);
        $config->id = 1;

        $this->injectConfigs([$config]);

        $request = Request::create('/any/path', 'GET');
        $headers = $this->service->buildHeaders($request);

        $this->assertArrayHasKey('Content-Security-Policy-Report-Only', $headers);
        $this->assertArrayNotHasKey('Content-Security-Policy', $headers);
    }

    #[Test]
    public function default_csp_when_no_config(): void
    {
        $this->injectConfigs([]);

        $request = Request::create('/api/test', 'GET');
        $headers = $this->service->buildHeaders($request);

        $this->assertArrayHasKey('Content-Security-Policy', $headers);
        $this->assertStringContainsString("default-src 'self'", $headers['Content-Security-Policy']);
    }

    #[Test]
    public function route_pattern_matching(): void
    {
        $config = new CspConfig([
            'name' => 'Admin Only',
            'directives' => ['default-src' => ["'self'"]],
            'route_pattern' => 'admin/*',
        ]);

        $this->assertTrue($config->matchesRoute('admin/dashboard'));
        $this->assertTrue($config->matchesRoute('admin/settings'));
        $this->assertFalse($config->matchesRoute('api/test'));
    }

    /**
     * 辅助方法：Mock Cache 来注入配置列表
     */
    protected function injectConfigs(array $configs): void
    {
        $this->service->clearCache();
        \Illuminate\Support\Facades\Cache::shouldReceive('remember')
            ->zeroOrMoreTimes()
            ->andReturn($configs);
    }
}
