<?php

namespace Tests\Feature\Public;

use App\Models\Product;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MobileResponsiveTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function key_public_pages_include_viewport_and_mobile_navigation(): void
    {
        foreach (['/pricing', '/products'] as $path) {
            $response = $this->get($path);
            $response->assertOk();
            $content = $response->getContent();

            $this->assertStringContainsString('name="viewport"', $content);
            $this->assertStringContainsString('width=device-width', $content);
            $this->assertStringContainsString('id="nav-mobile"', $content);
            $this->assertStringContainsString('nav-mobile-group-btn', $content);
        }
    }

    /** @test */
    public function pricing_page_uses_contained_horizontal_scroll_regions(): void
    {
        $response = $this->get('/pricing');
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('id="plans-container"', $content);
        $this->assertStringContainsString('overflow-x-auto', $content);
        $this->assertStringContainsString('table-scroll-wrap', $content);
        $this->assertStringContainsString('id="comparison-table"', $content);
    }

    /** @test */
    public function product_detail_page_is_mobile_friendly(): void
    {
        $product = Product::factory()->create([
            'name' => '移动端测试产品',
            'slug' => 'mobile-test-product',
            'is_active' => true,
        ]);

        $response = $this->get('/products/'.$product->slug);
        $response->assertOk();

        $content = $response->getContent();
        $this->assertStringContainsString('name="viewport"', $content);
        $this->assertStringContainsString('id="detail-tabs"', $content);
        $this->assertStringContainsString('id="mobile-sticky-bar"', $content);
        $this->assertStringContainsString('overflow-x-auto', $content);
    }

    /** @test */
    public function products_page_has_balanced_navigation_markup(): void
    {
        $content = strtolower($this->get('/products')->getContent());

        $this->assertSame(
            substr_count($content, '<nav'),
            substr_count($content, '</nav>'),
            '导航标签应成对闭合'
        );
    }
}
