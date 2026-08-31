<?php

namespace Tests\Feature\Public;

use App\Models\DemoBooking;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MarketingPagesTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function marketing_pages_are_accessible(): void
    {
        foreach (['/pricing', '/help', '/sdk', '/about', '/contact'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    /** @test */
    public function contact_form_creates_demo_booking(): void
    {
        $response = $this->postJson('/api/public/contact', [
            'company_name' => '测试科技有限公司',
            'contact_name' => '张三',
            'email' => 'zhangsan@example.com',
            'phone' => '13800138000',
            'employee_count' => '11-50 人',
            'product_interest' => 'License授权管理',
            'message' => '希望预约产品演示',
            'source' => 'contact',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('demo_bookings', [
            'company_name' => '测试科技有限公司',
            'contact_name' => '张三',
            'email' => 'zhangsan@example.com',
            'source' => 'contact',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function enterprise_contact_form_accepts_legacy_field_names(): void
    {
        $response = $this->postJson('/api/public/enterprise-contact', [
            'company' => '大型企业集团',
            'name' => '李四',
            'email' => 'lisi@enterprise.com',
            'employees' => '1000+',
            'message' => '需要私有化部署方案',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('demo_bookings', [
            'company_name' => '大型企业集团',
            'contact_name' => '李四',
            'email' => 'lisi@enterprise.com',
            'source' => 'pricing',
        ]);
    }

    /** @test */
    public function honeypot_submissions_are_silently_accepted_without_persisting(): void
    {
        $before = DemoBooking::count();

        $this->postJson('/api/public/contact', [
            'company_name' => 'Bot Corp',
            'contact_name' => 'Bot',
            'email' => 'bot@spam.com',
            'website_url' => 'http://spam.test',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame($before, DemoBooking::count());
    }

    /** @test */
    public function public_sdks_endpoint_returns_configured_sdks(): void
    {
        $response = $this->getJson('/api/public/sdks');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    ['id', 'name', 'install_command'],
                ],
            ]);
    }

    /** @test */
    public function sdk_doc_route_redirects_to_help_search(): void
    {
        $this->get('/docs/sdk/php')
            ->assertRedirect('/help?search=PHP+SDK');
    }
}
