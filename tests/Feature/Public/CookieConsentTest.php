<?php

namespace Tests\Feature\Public;

use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function cookie_policy_page_is_accessible(): void
    {
        $response = $this->get('/cookie-policy');
        $response->assertOk();
        $response->assertSee('Cookie 政策');
        $response->assertSee('必要 Cookies');
        $response->assertSee('功能 Cookies');
        $response->assertSee('分析 Cookies');
        $response->assertSee('营销 Cookies');
    }

    /** @test */
    public function privacy_page_is_accessible(): void
    {
        $response = $this->get('/privacy');
        $response->assertOk();
        $response->assertSee('隐私政策');
    }

    /** @test */
    public function cookie_banner_is_rendered_on_public_pages(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Cookie 设置');
        $response->assertSee('接受全部');
        $response->assertSee('拒绝');
        $response->assertSee('自定义');
    }

    /** @test */
    public function cookie_settings_panel_is_rendered(): void
    {
        $response = $this->get('/pricing');
        $response->assertOk();
        $response->assertSee('Cookie 偏好设置');
        $response->assertSee('必要 Cookies');
        $response->assertSee('分析 Cookies');
        $response->assertSee('营销 Cookies');
        $response->assertSee('保存设置');
    }

    /** @test */
    public function cookie_reopen_button_is_rendered(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Cookie 设置');
    }

    /** @test */
    public function tracking_scripts_are_deferred_by_default(): void
    {
        $response = $this->get('/privacy');
        $response->assertOk();

        // GA tracking ID 应存在于 data 节点而非直接加载
        $response->assertSee('tracking-ga-data');
        // 不应有旧的直接 script src 方式加载 GA
        $response->assertDontSee('<script async src');
    }

    /** @test */
    public function cookie_policy_links_to_settings_panel(): void
    {
        $response = $this->get('/cookie-policy');
        $response->assertOk();
        $response->assertSee('Cookie 偏好面板');
        $response->assertSee('自定义');
    }
}
