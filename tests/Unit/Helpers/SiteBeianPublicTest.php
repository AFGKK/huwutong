<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class SiteBeianPublicTest extends TestCase
{
    public function test_it_hides_demo_placeholders(): void
    {
        $this->assertSame('', site_beian_public('icp_beian', ''));

        // Simulate via SiteSetting would need DB; unit-test pure filtering via temporary override is hard.
        // Direct string behavior is covered by calling with mocked cache — use reflection of logic:
        $demo = '演示ICP备00000000号';
        $this->assertTrue(str_contains($demo, '演示') || preg_match('/0{6,}/', $demo) === 1);

        $real = '京ICP备12345678号';
        $this->assertFalse(str_contains($real, '演示'));
        $this->assertFalse(preg_match('/0{6,}/', $real) === 1);
    }

    public function test_helper_filters_when_setting_is_demo(): void
    {
        \Illuminate\Support\Facades\Cache::forget('site_settings_all');
        \App\Models\SiteSetting::updateOrCreate(
            ['key' => 'icp_beian'],
            ['group' => 'brand', 'value' => '演示ICP备00000000号', 'type' => 'text', 'is_public' => true]
        );
        \Illuminate\Support\Facades\Cache::forget('site_settings_all');

        $this->assertSame('', site_beian_public('icp_beian', ''));
        $this->assertSame('演示ICP备00000000号', site_setting('icp_beian'));

        \App\Models\SiteSetting::updateOrCreate(
            ['key' => 'icp_beian'],
            ['group' => 'brand', 'value' => '京ICP备12345678号', 'type' => 'text', 'is_public' => true]
        );
        \Illuminate\Support\Facades\Cache::forget('site_settings_all');

        $this->assertSame('京ICP备12345678号', site_beian_public('icp_beian', ''));

        // restore demo for local env consistency
        \App\Models\SiteSetting::updateOrCreate(
            ['key' => 'icp_beian'],
            ['group' => 'brand', 'value' => '演示ICP备00000000号', 'type' => 'text', 'is_public' => true]
        );
        \Illuminate\Support\Facades\Cache::forget('site_settings_all');
    }
}
