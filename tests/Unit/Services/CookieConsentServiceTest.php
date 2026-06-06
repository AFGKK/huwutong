<?php

namespace Tests\Unit\Services;

use App\Models\CookieConsentConfig;
use App\Services\CookieConsentService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CookieConsentServiceTest extends TestCase
{
    protected CookieConsentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CookieConsentService();
    }

    public function test_get_config_uses_cache()
    {
        $config = new CookieConsentConfig([
            'is_active' => true,
            'position' => 'bottom',
            'title' => 'Cookie 设置',
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->withArgs(fn ($key) => $key === CookieConsentService::CACHE_KEY)
            ->andReturn($config);

        $result = $this->service->getConfig();

        $this->assertSame($config, $result);
        $this->assertTrue($result->is_active);
    }

    public function test_clear_cache_works()
    {
        Cache::shouldReceive('forget')
            ->once()
            ->with(CookieConsentService::CACHE_KEY)
            ->andReturn(true);

        $this->service->clearCache();

        $this->assertTrue(true);
    }
}
