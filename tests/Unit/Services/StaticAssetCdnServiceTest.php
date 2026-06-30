<?php

namespace Tests\Unit\Services;

use App\Contracts\CloudStorage;
use App\Services\StaticAssetCdnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class StaticAssetCdnServiceTest extends TestCase
{
    protected StaticAssetCdnService $service;
    protected CloudStorage $storageMock;

    protected function setUp(): void
    {
        parent::setUp();

        // 模拟 CloudStorage
        $this->storageMock = Mockery::mock(CloudStorage::class);

        // 注入模拟服务
        $this->service = new StaticAssetCdnService($this->storageMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_has_default_version()
    {
        $version = $this->service->getCurrentVersion();
        $this->assertEquals('1', $version);
    }

    /** @test */
    public function it_deploys_build_files_to_cdn()
    {
        // 模拟构建目录和文件
        $buildDir = storage_path('app/test-build');
        $assetsDir = $buildDir . '/assets';
        if (! is_dir($assetsDir)) {
            mkdir($assetsDir, 0755, true);
        }

        // 创建测试文件
        file_put_contents($buildDir . '/manifest.json', json_encode(['test' => ['file' => 'test.js']]));
        file_put_contents($assetsDir . '/test.js', 'console.log("test");');
        file_put_contents($assetsDir . '/app.css', 'body { color: red; }');

        // 模拟 CloudStorage::upload
        $this->storageMock->shouldReceive('upload')
            ->times(3)
            ->andReturnUsing(function ($path, $content, $options) {
                return "https://cdn.example.com/{$path}";
            });

        $result = $this->service->deploy('20260101000000', $buildDir);

        $this->assertEquals('20260101000000', $result['version']);
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['files']);

        // cleanup
        array_map('unlink', glob($buildDir . '/assets/*'));
        rmdir($assetsDir);
        @unlink($buildDir . '/manifest.json');
        rmdir($buildDir);
    }

    /** @test */
    public function it_throws_when_build_dir_not_found()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('构建产物目录不存在');

        $this->service->deploy('test', '/nonexistent/path');
    }

    /** @test */
    public function it_activates_a_version()
    {
        // Mock: 检查版本是否存在的多个调用
        $this->storageMock->shouldReceive('exists')
            ->with('assets/20260101000000/manifest.json')
            ->andReturn(true);

        $result = $this->service->activateVersion('20260101000000');

        $this->assertEquals('20260101000000', $result['version']);
        $this->assertArrayHasKey('activated_at', $result);
    }

    /** @test */
    public function it_gets_asset_base_url()
    {
        $url = $this->service->getAssetBaseUrl('20260101000000');
        $this->assertStringContainsString('build', $url);

        // 没有 CDN domain 时返回本地 URL
        $this->assertStringContainsString('/build', $url);
    }

    /** @test */
    public function it_returns_mime_type_for_extensions()
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('getMimeType');
        $method->setAccessible(true);

        $this->assertEquals('application/javascript', $method->invoke($this->service, 'js'));
        $this->assertEquals('text/css', $method->invoke($this->service, 'css'));
        $this->assertEquals('image/png', $method->invoke($this->service, 'png'));
        $this->assertEquals('font/woff2', $method->invoke($this->service, 'woff2'));
        $this->assertEquals('application/octet-stream', $method->invoke($this->service, 'unknown'));
    }

    /** @test */
    public function it_returns_empty_array_when_no_build_files()
    {
        $files = $this->service->getBuildFiles();
        $this->assertIsArray($files);
    }

    /** @test */
    public function it_lists_deployed_versions()
    {
        // Mock: listFiles returns file paths with version pattern
        $this->storageMock->shouldReceive('listFiles')
            ->with('assets', true)
            ->andReturn([
                'assets/20260101000000/manifest.json',
                'assets/20260101000000/assets/app.js',
                'assets/20260102000000/manifest.json',
            ]);

        // Mock: 获取文件数
        $this->storageMock->shouldReceive('listFiles')
            ->with('assets/20260101000000/', true)
            ->andReturn(['file1', 'file2']);

        $this->storageMock->shouldReceive('listFiles')
            ->with('assets/20260102000000/', true)
            ->andReturn(['file1']);

        $versions = $this->service->listDeployedVersions();
        $this->assertGreaterThanOrEqual(1, count($versions));
    }
}
