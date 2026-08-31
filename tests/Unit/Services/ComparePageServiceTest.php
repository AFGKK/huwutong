<?php

namespace Tests\Unit\Services;

use App\Models\SiteSetting;
use App\Services\ComparePageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ComparePageServiceTest extends TestCase
{
    public function test_raw_config_has_competitors(): void
    {
        $config = app(ComparePageService::class)->rawConfig();
        $this->assertArrayHasKey('competitors', $config);
        $this->assertNotEmpty($config['competitors']);
        $this->assertArrayHasKey('comparison_data', $config);
        $this->assertArrayHasKey('seo', $config);
    }

    public function test_update_persists_and_public_source_switches(): void
    {
        $svc = app(ComparePageService::class);
        $before = SiteSetting::where('key', ComparePageService::SETTING_KEY)->value('value');

        try {
            $cfg = $svc->rawConfig();
            $cfg['seo']['title'] = 'UnitTestCompareTitle';
            $saved = $svc->update($cfg);
            $this->assertSame('UnitTestCompareTitle', $saved['seo']['title']);

            Cache::forget('site_settings_all');
            $this->assertSame('site_setting', $svc->getComparison()['source']);
            $this->assertSame('UnitTestCompareTitle', $svc->rawConfig()['seo']['title']);
        } finally {
            if ($before === null) {
                SiteSetting::where('key', ComparePageService::SETTING_KEY)->delete();
            } else {
                SiteSetting::where('key', ComparePageService::SETTING_KEY)->update(['value' => $before]);
            }
            Cache::forget('site_settings_all');
        }
    }
}
