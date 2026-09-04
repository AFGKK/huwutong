<?php

namespace Tests\Feature;

use App\Services\MeilisearchService;
use Mockery;
use Tests\TestCase;

class PublicSearchPageTest extends TestCase
{
    protected function tearDown(): void
    {
        $this->app->forgetInstance(MeilisearchService::class);
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function search_page_renders_for_guests(): void
    {
        $this->get('/search')
            ->assertOk()
            ->assertSee('id="search-form"', false)
            ->assertSee('/api/meilisearch/unified-search', false);
    }

    /** @test */
    public function guests_can_call_public_unified_search_api(): void
    {
        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('unifiedSearch')
            ->once()
            ->with('License', Mockery::type('array'))
            ->andReturn([
                'query' => 'License',
                'results' => ['products' => ['total' => 1, 'hits' => []]],
                'ranked' => [
                    ['_content_type' => 'products', 'id' => 1, 'name' => 'HWT License Pro'],
                ],
                'total_types' => 1,
                'total' => 1,
                'sort' => 'relevance',
            ]);
        $this->app->instance(MeilisearchService::class, $meili);

        $this->getJson('/api/meilisearch/unified-search?q=License&limit=20')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.ranked.0.name', 'HWT License Pro');
    }

    /** @test */
    public function guests_can_call_suggest_and_trending_apis(): void
    {
        $meili = Mockery::mock(MeilisearchService::class);
        $meili->shouldReceive('searchSuggest')->once()->andReturn([
            'query' => 'Lic',
            'suggestions' => [
                ['type' => 'products', 'title' => 'License', 'id' => 1, 'type_label' => '商品'],
            ],
        ]);
        $meili->shouldReceive('trending')->once()->with(3)->andReturn([
            ['type' => 'products', 'title' => 'Hot', 'id' => 2, 'label' => '商品', 'icon' => '📦'],
        ]);
        $this->app->instance(MeilisearchService::class, $meili);

        $this->getJson('/api/meilisearch/suggest?q=Lic')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.suggestions.0.title', 'License');

        $this->getJson('/api/meilisearch/trending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.0.title', 'Hot');
    }
}
