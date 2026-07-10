<?php

namespace Tests\Unit\Services;

use App\Models\BlogPost;
use App\Models\Page;
use App\Models\SeoMetadata;
use App\Models\Tenant;
use App\Models\UrlRedirect;
use App\Services\SeoService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SeoService $service;
    protected Tenant $tenant;
    protected Page $page;
    protected BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SeoService::class);
        $this->tenant = Tenant::factory()->create();
        $this->page = Page::create([
            'slug' => 'about',
            'title' => 'About Us',
            'content' => 'About page content',
            'status' => 'published',
        ]);
        $this->post = BlogPost::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Test Blog Post',
            'slug' => 'test-blog-post',
            'content' => 'Blog content',
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    // ═══════ SEO 元数据 ═══════

    /** @test */
    public function it_creates_seo_metadata()
    {
        $data = [
            'meta_title' => 'About Us - SEO Title',
            'meta_description' => 'Learn about our company',
            'meta_keywords' => 'about, company, team',
            'robots' => 'index,follow',
            'priority' => '0.8',
            'change_frequency' => 'monthly',
        ];

        $metadata = $this->service->upsertMetadata($this->page, $this->tenant->id, $data);

        $this->assertDatabaseHas('seo_metadata', [
            'seoable_type' => get_class($this->page),
            'seoable_id' => $this->page->id,
            'meta_title' => 'About Us - SEO Title',
        ]);
        $this->assertEquals('0.8', $metadata->priority);
    }

    /** @test */
    public function it_updates_existing_seo_metadata()
    {
        $this->service->upsertMetadata($this->page, $this->tenant->id, [
            'meta_title' => 'Original Title',
        ]);

        $this->service->upsertMetadata($this->page, $this->tenant->id, [
            'meta_title' => 'Updated Title',
        ]);

        $this->assertEquals(1, SeoMetadata::count());
        $this->assertDatabaseHas('seo_metadata', [
            'seoable_id' => $this->page->id,
            'meta_title' => 'Updated Title',
        ]);
    }

    /** @test */
    public function it_retrieves_seo_metadata_for_a_model()
    {
        $this->service->upsertMetadata($this->page, $this->tenant->id, [
            'meta_title' => 'Page Title',
        ]);

        $metadata = $this->service->getMetadataFor($this->page);

        $this->assertNotNull($metadata);
        $this->assertEquals('Page Title', $metadata->meta_title);
    }

    /** @test */
    public function it_returns_null_when_no_metadata_exists()
    {
        $metadata = $this->service->getMetadataFor($this->page);
        $this->assertNull($metadata);
    }

    /** @test */
    public function it_deletes_seo_metadata()
    {
        $this->service->upsertMetadata($this->page, $this->tenant->id, [
            'meta_title' => 'To Delete',
        ]);

        $this->service->deleteMetadata($this->page);

        $this->assertDatabaseMissing('seo_metadata', [
            'seoable_id' => $this->page->id,
        ]);
    }

    // ═══════ URL 重定向 ═══════

    /** @test */
    public function it_creates_a_redirect()
    {
        $redirect = $this->service->createRedirect($this->tenant->id, [
            'source_url' => '/old-page',
            'target_url' => '/new-page',
            'status_code' => 301,
        ]);

        $this->assertDatabaseHas('url_redirects', [
            'source_url' => '/old-page',
            'target_url' => '/new-page',
        ]);
        $this->assertEquals(301, $redirect->status_code);
    }

    /** @test */
    public function it_normalizes_source_url_with_leading_slash()
    {
        $redirect = $this->service->createRedirect($this->tenant->id, [
            'source_url' => 'old-page-no-slash',
            'target_url' => '/new-page',
        ]);

        $this->assertEquals('/old-page-no-slash', $redirect->source_url);
    }

    /** @test */
    public function it_updates_a_redirect()
    {
        $redirect = UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_url' => '/old',
            'target_url' => '/new',
        ]);

        $this->service->updateRedirect($redirect->id, [
            'target_url' => '/updated',
        ]);

        $this->assertDatabaseHas('url_redirects', [
            'id' => $redirect->id,
            'target_url' => '/updated',
        ]);
    }

    /** @test */
    public function it_deletes_a_redirect()
    {
        $redirect = UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->service->deleteRedirect($redirect->id);

        $this->assertDatabaseMissing('url_redirects', ['id' => $redirect->id]);
    }

    /** @test */
    public function it_lists_redirects_with_filters()
    {
        UrlRedirect::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_url' => '/special-page',
            'status_code' => 301,
        ]);

        $results = $this->service->listRedirects($this->tenant->id, ['search' => 'special']);

        $this->assertCount(1, $results->items());
    }

    /** @test */
    public function it_records_a_redirect_hit()
    {
        $redirect = UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hit_count' => 0,
        ]);

        $this->service->recordRedirectHit($redirect);

        $this->assertEquals(1, $redirect->fresh()->hit_count);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    /** @test */
    public function it_resolves_exact_url_redirect()
    {
        $redirect = UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_url' => '/old-page',
            'target_url' => '/new-page',
            'is_active' => true,
        ]);

        $result = $this->service->resolveRedirect('/old-page', $this->tenant->id);

        $this->assertNotNull($result);
        $this->assertEquals('/new-page', $result->target_url);
    }

    /** @test */
    public function it_returns_null_for_non_matching_path()
    {
        UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_url' => '/old-page',
            'is_active' => true,
        ]);

        $result = $this->service->resolveRedirect('/non-existent', $this->tenant->id);

        $this->assertNull($result);
    }

    // ═══════ 站点地图 ═══════

    /** @test */
    public function it_generates_sitemap_entries()
    {
        // Page is already created in setUp
        $entries = $this->service->getAllIndexableContent($this->tenant->id);

        $this->assertCount(2, $entries); // 1 page + 1 blog post
        $this->assertEquals('/pages/about', $entries[0]['url']);
        $this->assertEquals('/blog/test-blog-post', $entries[1]['url']);
    }

    /** @test */
    public function it_generates_sitemap_xml()
    {
        $xml = $this->service->generateSitemap($this->tenant->id);

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('/pages/about', $xml);
        $this->assertStringContainsString('/blog/test-blog-post', $xml);
    }

    // ═══════ 批量导入 ═══════

    /** @test */
    public function it_bulk_imports_redirects()
    {
        $entries = [
            ['source' => '/page1', 'target' => '/new-page1'],
            ['source' => '/page2', 'target' => '/new-page2', 'status_code' => 302],
        ];

        $result = $this->service->bulkImportRedirects($this->tenant->id, $entries);

        $this->assertEquals(2, $result['imported']);
        $this->assertEquals(0, $result['skipped']);
        $this->assertDatabaseHas('url_redirects', ['source_url' => '/page1', 'target_url' => '/new-page1']);
    }

    /** @test */
    public function it_skips_duplicate_imports()
    {
        $this->service->createRedirect($this->tenant->id, [
            'source_url' => '/page1',
            'target_url' => '/new-page1',
        ]);

        $entries = [
            ['source' => '/page1', 'target' => '/new-page1'],
        ];

        $result = $this->service->bulkImportRedirects($this->tenant->id, $entries);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(1, $result['skipped']);
    }

    /** @test */
    public function it_skips_invalid_entries_during_bulk_import()
    {
        $entries = [
            ['source' => '', 'target' => '/new-page'],
            ['source' => '/page2', 'target' => ''],
            [],
        ];

        $result = $this->service->bulkImportRedirects($this->tenant->id, $entries);

        $this->assertEquals(0, $result['imported']);
        $this->assertEquals(3, $result['skipped']);
    }

    // ═══════ 建议与统计 ═══════

    /** @test */
    public function it_returns_dashboard_stats()
    {
        UrlRedirect::factory()->create([
            'tenant_id' => $this->tenant->id,
            'source_url' => '/old',
            'target_url' => '/new',
            'hit_count' => 10,
        ]);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertEquals(1, $stats['total_redirects']);
        $this->assertEquals(10, $stats['total_hits']);
        $this->assertEquals('/old', $stats['most_hit_url']);
    }

    /** @test */
    public function it_returns_suggestions_for_missing_metadata()
    {
        // Page has no SEO metadata
        $suggestions = $this->service->getGlobalSeoSuggestions($this->tenant->id);

        $this->assertNotEmpty($suggestions);
        $hasMetaSuggestion = collect($suggestions)->contains(fn($s) => str_contains($s['message'], '页面缺少SEO元数据'));
        $this->assertTrue($hasMetaSuggestion);
    }

    /** @test */
    public function it_returns_suggestions_for_no_redirects()
    {
        $suggestions = $this->service->getGlobalSeoSuggestions($this->tenant->id);

        $this->assertNotEmpty($suggestions);
        $hasRedirectSuggestion = collect($suggestions)->contains(fn($s) => str_contains($s['message'], 'URL重定向'));
        $this->assertTrue($hasRedirectSuggestion);
    }
}
