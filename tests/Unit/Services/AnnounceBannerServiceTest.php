<?php

namespace Tests\Unit\Services;

use App\Models\AnnounceBanner;
use App\Services\AnnounceBannerService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class AnnounceBannerServiceTest extends TestCase
{
    protected AnnounceBannerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AnnounceBannerService();
    }

    public function test_get_active_banners_uses_cache()
    {
        $cachedData = [
            ['id' => 1, 'title' => 'Cached Banner', 'content' => 'test', 'type' => 'info', 'position' => 'top'],
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key) => $key === AnnounceBannerService::CACHE_KEY)
            ->andReturn($cachedData);

        $result = $this->service->getActiveBanners();

        $this->assertEquals($cachedData, $result);
    }

    public function test_get_active_banners_with_role_uses_same_cache()
    {
        $cachedData = [
            ['id' => 2, 'title' => 'Admin Banner', 'content' => 'test', 'type' => 'info', 'position' => 'top'],
        ];

        Cache::shouldReceive('remember')
            ->once()
            ->with(AnnounceBannerService::CACHE_KEY, AnnounceBannerService::CACHE_TTL, Mockery::on(fn ($cb) => is_callable($cb)))
            ->andReturn($cachedData);

        $result = $this->service->getActiveBanners('admin');

        $this->assertEquals($cachedData, $result);
    }

    public function test_clear_cache_works()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with(AnnounceBannerService::CACHE_KEY)
            ->andReturn(true);

        $this->service->clearCache();

        $this->assertTrue(true);
    }

    public function test_delete_clears_cache()
    {
        $banner = Mockery::mock(AnnounceBanner::class);
        $banner->shouldReceive('delete')->once()->andReturn(true);

        Cache::shouldReceive('forget')
            ->once()
            ->with(AnnounceBannerService::CACHE_KEY)
            ->andReturn(true);

        $this->service->delete($banner);

        $this->assertTrue(true);
    }
}
