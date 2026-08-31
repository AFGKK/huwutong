<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Observers\MeilisearchObserver;
use App\Services\MeilisearchIndexer;
use App\Services\MeilisearchService;
use Mockery;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MeilisearchObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_product_update_triggers_incremental_sync(): void
    {
        config(['meilisearch.observer.enabled' => true, 'meilisearch.sync.queue' => false]);

        $product = Product::factory()->create(['name' => 'Before Sync', 'is_active' => true]);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('indexKeyForModel')->andReturn('products');
        $meili->shouldReceive('upsertModel')->once()->andReturn(true);

        $observer = new MeilisearchObserver(new MeilisearchIndexer($meili));
        $observer->updated($product->fresh());

        $this->addToAssertionCount(1);
    }

    public function test_product_delete_triggers_index_removal(): void
    {
        config(['meilisearch.observer.enabled' => true, 'meilisearch.sync.queue' => false]);

        $product = Product::factory()->create(['name' => 'To Delete', 'is_active' => true]);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('indexKeyForModel')->andReturn('products');
        $meili->shouldReceive('removeModel')->once()->andReturn(true);

        $observer = new MeilisearchObserver(new MeilisearchIndexer($meili));
        $observer->deleted($product);

        $this->addToAssertionCount(1);
    }
}
