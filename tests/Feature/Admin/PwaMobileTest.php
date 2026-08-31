<?php

namespace Tests\Feature\Admin;

use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * D-27: 管理后台 PWA 移动关键页 — 仪表盘/License/通知 手机布局适配
 *
 * 验证内容:
 * 1. PWA manifest JSON 端点可用
 * 2. 管理后台 HTML 包含 viewport meta、manifest link、theme-color
 * 3. 关键页面可正常访问
 */
class PwaMobileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_blade_has_viewport_meta(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('viewport', false);
        $response->assertSee('width=device-width', false);
        $response->assertSee('viewport-fit=cover', false);
    }

    public function test_admin_blade_has_manifest_link(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('<link rel="manifest"', false);
    }

    public function test_admin_blade_has_apple_touch_icon(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('apple-touch-icon', false);
    }

    public function test_admin_blade_has_theme_color_meta(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('theme-color', false);
    }

    public function test_admin_blade_has_apple_meta_tags(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('apple-mobile-web-app-capable', false);
        $response->assertSee('apple-mobile-web-app-status-bar-style', false);
        $response->assertSee('black-translucent', false);
    }

    public function test_admin_blade_has_mobile_web_app_meta(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('mobile-web-app-capable', false);
        $response->assertSee('application-name', false);
    }

    public function test_admin_blade_has_admin_app_root(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('id="admin-app"', false);
    }

    public function test_admin_blade_has_loading_spinner(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('app-loading', false);
        $response->assertSee('loading-spinner', false);
        $response->assertSee('加载中...', false);
    }

    public function test_admin_blade_has_skip_link(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('skip-link', false);
        $response->assertSee('跳转到主内容', false);
    }

    public function test_admin_blade_has_a11y_announcers(): void
    {
        $response = $this->get('/build/dashboard');

        $response->assertOk();
        $response->assertSee('a11y-announcer-polite', false);
        $response->assertSee('a11y-announcer-assertive', false);
    }

    public function test_license_page_accepts_web_request(): void
    {
        $response = $this->get('/build/licenses');
        $response->assertOk();
    }

    public function test_notifications_page_accepts_web_request(): void
    {
        $response = $this->get('/build/notifications');
        $response->assertOk();
    }

    public function test_pwa_offline_page_is_accessible(): void
    {
        $response = $this->get('/build/offline');
        $response->assertOk();
    }
}
