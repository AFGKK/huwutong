<?php

namespace Tests\Feature;

use App\Models\KbArticle;
use App\Models\KbCategory;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class HelpCenterPageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function help_index_and_article_pages_render_and_hide_loading_logic(): void
    {
        $this->get('/help')
            ->assertOk()
            ->assertSee('id="loading-state"', false)
            ->assertSee('function hideLoading', false)
            ->assertSee('function showLoading', false)
            ->assertSee(__('app.help_page.title'));

        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $category = KbCategory::create([
            'name' => '授权管理',
            'slug' => 'license-mgmt',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $article = KbArticle::create([
            'category_id' => $category->id,
            'author_id' => $user->id,
            'title' => 'License 激活与设备绑定',
            'slug' => 'license-activate-bind',
            'content' => '<p>激活步骤说明</p>',
            'excerpt' => '激活说明',
            'status' => 'published',
            'published_at' => now(),
            'locale' => 'zh-CN',
        ]);

        $this->get('/help/'.$article->id)
            ->assertOk()
            ->assertSee('loadArticle', false)
            ->assertSee('hideLoading()', false);

        $this->getJson('/api/kb/articles/'.$article->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $payload = $this->getJson('/api/kb/articles/'.$article->id)->json('data');
        $title = $payload['article']['title'] ?? $payload['title'] ?? null;
        $this->assertSame('License 激活与设备绑定', $title);
    }

    /** @test */
    public function help_categories_api_returns_list_for_home(): void
    {
        KbCategory::create([
            'name' => '快速入门',
            'slug' => 'quickstart',
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $this->getJson('/api/kb/categories?locale=zh-CN')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
