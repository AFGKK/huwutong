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
        foreach ([
            '/pricing',
            '/help',
            '/sdk',
            '/about',
            '/contact',
            '/docs',
            '/docs/quickstart',
            '/api-docs',
            '/docs/error-codes',
            '/docs/webhooks',
        ] as $path) {
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
    public function sdk_doc_pages_are_available_per_language(): void
    {
        foreach (['php', 'node', 'python', 'go', 'java', 'csharp', 'flutter', 'electron', 'tauri'] as $lang) {
            $sdk = config('sdk-docs.sdks.'.$lang);
            $this->assertIsArray($sdk);

            $this->get('/docs/sdk/'.$lang)
                ->assertOk()
                ->assertSee($sdk['package'], false)
                ->assertSee($sdk['name'], false);
        }

        $this->get('/docs/sdk/javascript')
            ->assertRedirect('/docs/sdk/node');
    }

    /** @test */
    public function public_api_docs_json_returns_endpoints(): void
    {
        $this->getJson('/api/api-docs/public')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'groups',
                    'total_endpoints',
                    'source',
                ],
            ]);
    }

    /** @test */
    public function pricing_comparison_matrix_uses_plan_limits(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        \App\Models\PricingPlan::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'starter',
            'name' => 'Starter',
            'price_monthly' => 49,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
            'limits' => [
                'max_products' => 2,
                'max_activations' => 500,
                'api_rate_limit' => 120,
                'max_api_keys' => 3,
                'team_members' => 2,
            ],
            'metadata' => [
                'comparison' => [
                    'webhook' => true,
                    'trial_management' => '14',
                ],
            ],
        ]);

        $html = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('id="comparison-table"', $html);
        $this->assertStringContainsString('Starter', $html);
        $this->assertStringContainsString('2', $html);
        $this->assertStringContainsString('120', $html);
    }

    /** @test */
    public function pricing_page_exposes_plan_ids_in_subscribe_links(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();
        $plan = \App\Models\PricingPlan::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'basic',
            'name' => '基础版',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 2,
        ]);

        $html = $this->get('/pricing')->assertOk()->getContent();

        $this->assertStringContainsString('data-plan-id="'.$plan->id.'"', $html);
        $this->assertStringContainsString('/build/subscribe/'.$plan->id.'?period=monthly', $html);
        $this->assertStringContainsString('class="sticky-cta', $html);
        $this->assertStringContainsString('id="sticky-price-'.$plan->slug.'"', $html);
        $this->assertStringContainsString('table-scroll-wrap', $html);
        $this->assertStringNotContainsString('data-plan-id=""', $html);
    }

    /** @test */
    public function blog_rss_redirects_to_api_rss(): void
    {
        $this->get('/blog/rss')->assertRedirect('/api/rss');
        $this->get('/blog/rss/latest')->assertRedirect('/api/rss/latest');
        $this->get('/blog/rss/changelog')->assertRedirect('/api/rss/changelog');

        $html = $this->get('/blog')->assertOk()->getContent();
        $this->assertStringContainsString('href="/api/rss"', $html);
        $this->assertStringNotContainsString('href="/blog/rss"', $html);
    }
}
