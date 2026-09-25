<?php

namespace Tests\Feature\Public;

use App\Models\BlogPost;
use App\Models\Tenant;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BlogDetailPageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function blog_detail_page_keeps_core_reading_actions_only(): void
    {
        $tenant = Tenant::factory()->create();
        $post = BlogPost::factory()->create([
            'tenant_id' => $tenant->id,
            'slug' => 'simplify-detail-ux',
            'is_published' => true,
            'published_at' => now(),
            'type' => 'blog',
        ]);

        $html = $this->get('/blog/'.$post->slug)->assertOk()->getContent();

        // 核心阅读能力保留
        $this->assertStringContainsString('id="post-detail"', $html);
        $this->assertStringContainsString('id="detail-content"', $html);
        $this->assertStringContainsString('id="blog-like-btn"', $html);
        $this->assertStringContainsString('id="blog-copy-btn"', $html);
        $this->assertStringContainsString('id="comment-list"', $html);
        $this->assertStringContainsString('id="related-posts-section"', $html);
        $this->assertStringContainsString('id="reading-progress-bar"', $html);

        // 多余/低频功能已移除
        $this->assertStringNotContainsString('id="ai-summary-box"', $html);
        $this->assertStringNotContainsString('id="post-toc"', $html);
        $this->assertStringNotContainsString('id="poster-modal"', $html);
        $this->assertStringNotContainsString('id="blog-fav-btn"', $html);
        $this->assertStringNotContainsString('id="blog-readlater-btn"', $html);
        $this->assertStringNotContainsString('id="detail-follow-btn"', $html);
        $this->assertStringNotContainsString('generatePoster', $html);
        $this->assertStringNotContainsString('shareBlog(', $html);
        $this->assertStringNotContainsString('qrcode.min.js', $html);
        $this->assertStringNotContainsString('分享得积分', $html);
        $this->assertStringContainsString('id="detail-author-avatar"', $html);
        $this->assertStringContainsString('fillDetailAuthor', $html);
        $this->assertStringContainsString('renderAuthorBadge', $html);
    }
}
