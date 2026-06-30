<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\CustomDomain;
use App\Models\PortalBrandingConfig;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PortalBrandingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PortalBrandingService $service;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PortalBrandingService::class);
        $this->tenant = Tenant::factory()->create();
    }

    /** @test */
    public function it_returns_default_config_when_no_tenant_config_exists()
    {
        // 先创建默认配置
        PortalBrandingConfig::create([
            'tenant_id' => null,
            'locale' => 'zh-CN',
            'brand_name' => '默认品牌',
            'primary_color' => '#409eff',
            'is_active' => true,
            'is_default' => true,
        ]);

        $config = $this->service->getConfig($this->tenant->id);

        $this->assertNotNull($config);
        $this->assertEquals('默认品牌', $config->brand_name);
    }

    /** @test */
    public function it_returns_tenant_specific_config()
    {
        PortalBrandingConfig::create([
            'tenant_id' => $this->tenant->id,
            'locale' => 'zh-CN',
            'brand_name' => '租户品牌',
            'primary_color' => '#1890ff',
            'is_active' => true,
        ]);

        $config = $this->service->getConfig($this->tenant->id);

        $this->assertNotNull($config);
        $this->assertEquals('租户品牌', $config->brand_name);
        $this->assertEquals('#1890ff', $config->primary_color);
    }

    /** @test */
    public function it_creates_config_with_defaults_when_not_exists()
    {
        $config = $this->service->getOrCreateConfig($this->tenant->id);

        $this->assertNotNull($config);
        $this->assertEquals('#409eff', $config->primary_color);
        $this->assertTrue($config->is_active);
    }

    /** @test */
    public function it_updates_branding_config()
    {
        $config = $this->service->getOrCreateConfig($this->tenant->id);

        $updated = $this->service->updateConfig($this->tenant->id, 'zh-CN', [
            'brand_name' => '更新后的品牌',
            'primary_color' => '#722ed1',
            'login_page_title' => '欢迎登录',
        ]);

        $this->assertEquals('更新后的品牌', $updated->brand_name);
        $this->assertEquals('#722ed1', $updated->primary_color);
        $this->assertEquals('欢迎登录', $updated->login_page_title);
    }

    /** @test */
    public function it_generates_css_variables_string()
    {
        PortalBrandingConfig::create([
            'tenant_id' => $this->tenant->id,
            'locale' => 'zh-CN',
            'brand_name' => '测试',
            'primary_color' => '#ff6600',
            'secondary_color' => '#00cc66',
            'text_color' => '#222222',
            'is_active' => true,
        ]);

        $css = $this->service->getCssVariables($this->tenant->id);

        $this->assertStringContainsString('--brand-primary', $css);
        $this->assertStringContainsString('#ff6600', $css);
        $this->assertStringContainsString(':root {', $css);
    }

    /** @test */
    public function it_returns_branding_data_with_css_variables()
    {
        PortalBrandingConfig::create([
            'tenant_id' => $this->tenant->id,
            'locale' => 'zh-CN',
            'brand_name' => '完整测试',
            'primary_color' => '#52c41a',
            'login_page_title' => '定制登录',
            'login_page_subtitle' => '欢迎使用',
            'login_bg_image' => 'https://example.com/bg.jpg',
            'is_active' => true,
        ]);

        $data = $this->service->getBrandingData($this->tenant->id);

        $this->assertArrayHasKey('config', $data);
        $this->assertArrayHasKey('css_variables', $data);
        $this->assertArrayHasKey('css_string', $data);
        $this->assertEquals('定制登录', $data['config']['login_page_title']);
        $this->assertEquals('#52c41a', $data['css_variables']['--brand-primary']);
    }

    /** @test */
    public function it_resets_to_default()
    {
        PortalBrandingConfig::create([
            'tenant_id' => null,
            'locale' => 'zh-CN',
            'brand_name' => '默认品牌',
            'primary_color' => '#409eff',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->service->getOrCreateConfig($this->tenant->id);
        $updated = $this->service->updateConfig($this->tenant->id, 'zh-CN', [
            'brand_name' => '自定义品牌',
            'primary_color' => '#ff0000',
        ]);

        $this->assertEquals('自定义品牌', $updated->brand_name);

        // 重置后应恢复到默认值
        $reset = $this->service->resetToDefault($this->tenant->id);
        $this->assertEquals('默认品牌', $reset->brand_name);
        $this->assertEquals('#409eff', $reset->primary_color);
    }

    /** @test */
    public function it_returns_theme_templates()
    {
        $templates = $this->service->getThemeTemplates();

        $this->assertCount(6, $templates);
        $this->assertEquals('default', $templates[0]['id']);
        $this->assertEquals('dark', $templates[5]['id']);
    }

    /** @test */
    public function it_supports_login_page_branding_fields()
    {
        $config = $this->service->getOrCreateConfig($this->tenant->id);

        $this->service->updateConfig($this->tenant->id, 'zh-CN', [
            'login_page_title' => '我的品牌登录',
            'login_page_subtitle' => '欢迎回来',
            'login_bg_image' => 'https://cdn.example.com/login-bg.jpg',
            'logo_url' => 'https://cdn.example.com/logo.png',
            'favicon_url' => 'https://cdn.example.com/favicon.ico',
        ])->fresh();

        $data = $this->service->getBrandingData($this->tenant->id);

        $this->assertEquals('我的品牌登录', $data['config']['login_page_title']);
        $this->assertEquals('欢迎回来', $data['config']['login_page_subtitle']);
        $this->assertEquals('https://cdn.example.com/login-bg.jpg', $data['config']['login_bg_image']);
        $this->assertEquals('https://cdn.example.com/logo.png', $data['config']['logo_url']);
    }

    /** @test */
    public function it_returns_null_config_when_nothing_exists()
    {
        $data = $this->service->getBrandingData(999);

        $this->assertNull($data['config']);
        $this->assertEmpty($data['css_variables']);
        $this->assertEmpty($data['css_string']);
    }

    /** @test */
    public function it_resolves_tenant_by_custom_domain()
    {
        $domain = 'branded.example.com';
        CustomDomain::create([
            'tenant_id' => $this->tenant->id,
            'domain' => $domain,
            'cname_target' => 'cname.huwutong.com.',
            'verified' => true,
            'is_active' => true,
            'status' => 'active',
        ]);

        PortalBrandingConfig::create([
            'tenant_id' => $this->tenant->id,
            'locale' => 'zh-CN',
            'brand_name' => '自定义域名品牌',
            'primary_color' => '#ff6600',
            'is_active' => true,
        ]);

        // 模拟域名解析
        $customDomain = CustomDomain::where('domain', $domain)
            ->where('verified', true)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($customDomain);
        $this->assertEquals($this->tenant->id, $customDomain->tenant_id);

        $data = $this->service->getBrandingData($customDomain->tenant_id);
        $this->assertEquals('自定义域名品牌', $data['config']['brand_name']);
    }
}
