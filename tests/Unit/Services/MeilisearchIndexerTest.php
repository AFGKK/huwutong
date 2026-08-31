<?php

namespace Tests\Unit\Services;

use App\Models\Product;
use App\Models\User;
use App\Services\MeilisearchIndexer;
use App\Services\MeilisearchService;
use Mockery;
use Tests\TestCase;

class MeilisearchIndexerTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_calls_upsert_when_not_queued(): void
    {
        config([
            'meilisearch.observer.enabled' => true,
            'meilisearch.sync.queue' => false,
        ]);

        $product = new Product(['id' => 42, 'name' => 'Observer Product']);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('indexKeyForModel')->with($product)->andReturn('products');
        $meili->shouldReceive('upsertModel')->once()->with($product)->andReturn(true);

        (new MeilisearchIndexer($meili))->sync($product);

        $this->addToAssertionCount(1);
    }

    public function test_remove_calls_delete_on_meilisearch_service(): void
    {
        config(['meilisearch.observer.enabled' => true, 'meilisearch.sync.queue' => false]);

        $product = new Product(['id' => 7, 'name' => 'Deleted']);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('indexKeyForModel')->with($product)->andReturn('products');
        $meili->shouldReceive('removeModel')->once()->with($product)->andReturn(true);

        (new MeilisearchIndexer($meili))->remove($product);

        $this->addToAssertionCount(1);
    }

    public function test_sync_dispatches_queue_job_when_enabled(): void
    {
        config([
            'meilisearch.observer.enabled' => true,
            'meilisearch.sync.queue' => true,
            'meilisearch.sync.queue_name' => 'search',
        ]);

        $user = new User(['id' => 9, 'name' => 'Queued User', 'status' => 'active']);

        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('indexKeyForModel')->with($user)->andReturn('users');
        $meili->shouldReceive('upsertModel')->never();

        (new MeilisearchIndexer($meili))->sync($user);

        $this->assertTrue((new MeilisearchIndexer($meili))->usesQueue());
    }

    public function test_inactive_user_is_not_indexed(): void
    {
        $meili = app(MeilisearchService::class);
        if (! $meili->isAvailable()) {
            $this->markTestSkipped('Meilisearch 未启动');
        }

        $user = User::factory()->create(['status' => 'inactive', 'name' => 'Inactive User']);

        $this->assertFalse($meili->shouldIndex('users', $user));
    }
}
