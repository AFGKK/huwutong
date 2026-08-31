<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\ProductSku;
use App\Services\MeilisearchService;
use App\Services\ProductSearchService;
use Mockery;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class ProductSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_uses_database_when_engine_is_database(): void
    {
        config(['product-search.engine' => 'database']);

        $product = Product::factory()->create(['name' => 'UniqueDbWidget', 'is_active' => true]);
        ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-DB-001',
            'name' => 'SKU Widget',
            'price' => 10,
            'stock' => 5,
            'is_active' => true,
        ]);

        $service = app(ProductSearchService::class);

        $this->assertFalse($service->usesMeilisearch());
        $this->assertNull($service->resolveProductIds('Widget', []));

        $results = $service->search(['search' => 'Widget']);
        $this->assertGreaterThanOrEqual(1, $results->total());
    }

    public function test_uses_meilisearch_product_ids_when_engine_is_meilisearch(): void
    {
        config(['product-search.engine' => 'meilisearch']);

        $product = Product::factory()->create(['name' => 'MeiliCloud Pro', 'is_active' => true]);
        ProductSku::create([
            'product_id' => $product->id,
            'sku_code' => 'SKU-MEILI-001',
            'name' => 'Cloud Annual',
            'price' => 99,
            'stock' => 10,
            'is_active' => true,
        ]);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('isAvailable')->andReturn(true);
        $meili->shouldReceive('searchProductsForService')
            ->andReturn([
                'hits' => [['id' => $product->id, 'name' => 'MeiliCloud Pro']],
                'total' => 1,
            ]);
        $meili->shouldReceive('searchProducts')->andReturn(['hits' => []]);

        $this->app->instance(MeilisearchService::class, $meili);

        $service = app(ProductSearchService::class);

        $this->assertTrue($service->usesMeilisearch());
        $this->assertSame([$product->id], $service->resolveProductIds('Cloud', []));

        $results = $service->search(['search' => 'Cloud']);
        $item = $results->items()[0];
        $name = is_array($item) ? ($item['name'] ?? '') : ($item->name ?? '');
        $this->assertSame('Cloud Annual', $name);
    }

    public function test_suggest_falls_back_to_database_when_meilisearch_unavailable(): void
    {
        config(['product-search.engine' => 'meilisearch']);

        Product::factory()->create(['name' => 'FallbackSuggestItem', 'is_active' => true]);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('isAvailable')->andReturn(false);
        $this->app->instance(MeilisearchService::class, $meili);

        $service = app(ProductSearchService::class);
        $suggestions = $service->suggest('Fallback');

        $this->assertContains('FallbackSuggestItem', $suggestions);
    }
}
