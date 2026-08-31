<?php

namespace Tests\Unit\Services;

use App\Models\Page;
use App\Services\LegalCmsPageService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LegalCmsPageServiceTest extends TestCase
{
    public function test_draft_uses_static_about_blade(): void
    {
        $page = Page::firstOrCreate(
            ['slug' => 'about'],
            ['title' => '关于我们', 'content' => '<p>draft body long enough for cms</p>', 'status' => 'draft', 'locale' => 'zh-CN']
        );
        $was = $page->status;
        $page->update(['status' => 'draft']);

        $view = app(LegalCmsPageService::class)->resolve('about');
        $this->assertSame('public.about', $view->name());

        $page->update(['status' => $was]);
    }

    public function test_published_with_content_uses_cms(): void
    {
        $page = Page::firstOrCreate(
            ['slug' => 'privacy'],
            ['title' => '隐私政策', 'content' => '', 'status' => 'draft', 'locale' => 'zh-CN']
        );
        $orig = $page->only(['status', 'content', 'published_at']);

        try {
            $page->update([
                'status' => 'published',
                'content' => '<h2>信息收集</h2><p>我们收集必要信息用于提供服务，并采取合理安全措施保护您的数据。</p>',
                'published_at' => now(),
            ]);

            $view = app(LegalCmsPageService::class)->resolve('privacy');
            $this->assertSame('public.cms-page', $view->name());
        } finally {
            $page->update($orig);
        }
    }

    public function test_published_empty_falls_back_to_static(): void
    {
        $page = Page::firstOrCreate(
            ['slug' => 'terms'],
            ['title' => '服务条款', 'content' => 'x', 'status' => 'draft', 'locale' => 'zh-CN']
        );
        $orig = $page->only(['status', 'content', 'published_at']);

        try {
            $page->update([
                'status' => 'published',
                'content' => '<p>短</p>',
                'published_at' => now(),
            ]);

            $view = app(LegalCmsPageService::class)->resolve('terms');
            $this->assertSame('public.terms', $view->name());
        } finally {
            $page->update($orig);
        }
    }

    public function test_contact_always_static_form(): void
    {
        $view = app(LegalCmsPageService::class)->resolve('contact');
        $this->assertSame('public.contact', $view->name());
    }
}
