<?php

namespace Tests\Unit\Services;

use App\Models\BlogPost;
use App\Models\RssFeed;
use App\Services\BlogService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BlogServiceTest extends TestCase
{
    use RefreshDatabase;

    private BlogService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BlogService::class);
    }

    public function test_can_create_post()
    {
        $post = $this->service->createPost([
            'title' => 'Test Blog Post',
            'type' => 'blog',
            'content' => 'This is test content',
            'is_published' => false,
        ]);

        $this->assertInstanceOf(BlogPost::class, $post);
        $this->assertEquals('Test Blog Post', $post->title);
        $this->assertEquals('blog', $post->type);
        $this->assertFalse($post->is_published);
        $this->assertNotEmpty($post->slug);
    }

    public function test_can_update_post()
    {
        $post = BlogPost::create([
            'title' => 'Original Title',
            'slug' => 'original-title',
            'type' => 'changelog',
            'content' => 'Original content',
        ]);

        $updated = $this->service->updatePost($post, [
            'title' => 'Updated Title',
            'version' => 'v2.0.0',
        ]);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals('v2.0.0', $updated->version);
    }

    public function test_toggle_publish_sets_published_at()
    {
        $post = BlogPost::create([
            'title' => 'Test',
            'slug' => 'test-slug',
            'type' => 'blog',
            'content' => 'Content',
            'is_published' => false,
        ]);

        $this->assertFalse((bool)$post->is_published);
        $this->assertNull($post->published_at);

        $this->service->togglePublish($post);

        $post->refresh();
        $this->assertTrue((bool)$post->is_published);
        $this->assertNotNull($post->published_at);
    }

    public function test_toggle_featured()
    {
        $post = BlogPost::create([
            'title' => 'Test',
            'slug' => 'test-featured',
            'type' => 'blog',
            'content' => 'Content',
            'is_featured' => false,
        ]);

        $this->assertFalse((bool)$post->is_featured);

        $this->service->toggleFeatured($post);
        $post->refresh();
        $this->assertTrue((bool)$post->is_featured);

        $this->service->toggleFeatured($post);
        $post->refresh();
        $this->assertFalse((bool)$post->is_featured);
    }

    public function test_can_delete_post()
    {
        $post = BlogPost::create([
            'title' => 'Test',
            'slug' => 'test-delete',
            'type' => 'blog',
            'content' => 'Content',
        ]);

        $this->service->deletePost($post);

        $this->assertSoftDeleted($post);
    }

    public function test_get_published_posts()
    {
        BlogPost::create(['title' => 'Draft', 'slug' => 'draft', 'type' => 'blog', 'content' => 'C', 'is_published' => false]);
        BlogPost::create(['title' => 'Published 1', 'slug' => 'pub1', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'Published 2', 'slug' => 'pub2', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);

        $published = $this->service->getPublishedPosts();
        $this->assertCount(2, $published);
    }

    public function test_get_published_posts_filtered_by_type()
    {
        BlogPost::create(['title' => 'Blog', 'slug' => 'blog1', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'Changelog', 'slug' => 'cl1', 'type' => 'changelog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);

        $blogs = $this->service->getPublishedPosts('blog');
        $this->assertCount(1, $blogs);
        $this->assertEquals('blog', $blogs->first()->type  );
    }

    public function test_get_latest_changelog()
    {
        BlogPost::create(['title' => 'Old', 'slug' => 'old', 'type' => 'changelog', 'content' => 'C', 'is_published' => true, 'published_at' => now()->subDays(2)]);
        BlogPost::create(['title' => 'New', 'slug' => 'new', 'type' => 'changelog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);

        $latest = $this->service->getLatestChangelog();
        $this->assertCount(2, $latest);
        $this->assertEquals('New', $latest->first()->title);
    }

    public function test_get_featured_posts()
    {
        BlogPost::create(['title' => 'Featured 1', 'slug' => 'f1', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'is_featured' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'Featured 2', 'slug' => 'f2', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'is_featured' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'Not Featured', 'slug' => 'n1', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);

        $featured = $this->service->getFeaturedPosts();
        $this->assertCount(2, $featured);
    }

    public function test_generates_valid_rss()
    {
        RssFeed::create([
            'feed_type' => 'all',
            'title' => 'Test Feed',
            'description' => 'Test Description',
        ]);

        BlogPost::create(['title' => 'RSS Post', 'slug' => 'rss-post', 'type' => 'blog', 'content' => '<p>Hello World</p>', 'is_published' => true, 'published_at' => now()]);

        $xml = $this->service->generateRss('all');

        $this->assertStringContainsString('<?xml version="1.0"', $xml);
        $this->assertStringContainsString('<rss version="2.0"', $xml);
        $this->assertStringContainsString('<title><![CDATA[RSS Post]]></title>', $xml);
        $this->assertStringContainsString('<pubDate>', $xml);
        $this->assertStringContainsString('</rss>', $xml);
    }

    public function test_list_posts_with_filters()
    {
        BlogPost::create(['title' => 'Blog Post', 'slug' => 'bp1', 'type' => 'blog', 'content' => 'Content', 'is_published' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'Changelog v1', 'slug' => 'cl1', 'type' => 'changelog', 'content' => 'Content', 'is_published' => false]);
        BlogPost::create(['title' => 'Release v2', 'slug' => 'rn1', 'type' => 'release_note', 'content' => 'Content', 'is_published' => true, 'published_at' => now()]);

        // Filter by type
        $blogs = $this->service->listPosts(['type' => 'blog']);
        $this->assertEquals(1, $blogs->total());

        // Filter by status
        $published = $this->service->listPosts(['status' => 'published']);
        $this->assertEquals(2, $published->total());

        // Filter by search
        $searched = $this->service->listPosts(['search' => 'Changelog']);
        $this->assertEquals(1, $searched->total());
    }

    public function test_get_stats()
    {
        BlogPost::create(['title' => 'B1', 'slug' => 'b1', 'type' => 'blog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);
        BlogPost::create(['title' => 'B2', 'slug' => 'b2', 'type' => 'blog', 'content' => 'C', 'is_published' => false]);
        BlogPost::create(['title' => 'C1', 'slug' => 'c1', 'type' => 'changelog', 'content' => 'C', 'is_published' => true, 'published_at' => now()]);

        $stats = $this->service->getStats();

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(2, $stats['published']);
        $this->assertEquals(1, $stats['drafts']);
        $this->assertEquals(2, $stats['by_type']['blog']);
        $this->assertEquals(1, $stats['by_type']['changelog']);
    }

    public function test_auto_slug_generation()
    {
        $post = $this->service->createPost([
            'title' => 'My New Blog Post!',
            'type' => 'blog',
            'content' => 'Content',
        ]);

        $this->assertStringContainsString('my-new-blog-post', $post->slug);
    }

    public function test_auto_excerpt_from_content()
    {
        $post = BlogPost::create([
            'title' => 'Test',
            'slug' => 'test-excerpt',
            'type' => 'blog',
            'content' => 'This is a long piece of content that should be automatically truncated to create an excerpt for display purposes.',
        ]);

        $this->assertNotEmpty($post->excerpt);
    }
}
