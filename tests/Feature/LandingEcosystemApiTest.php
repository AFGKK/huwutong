<?php

namespace Tests\Feature;

use App\Models\ForumPost;
use App\Models\OfficialAccount;
use App\Models\Tenant;
use App\Models\User;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class LandingEcosystemApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function homepage_ecosystem_apis_return_flat_public_lists(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        OfficialAccount::create([
            'name' => '首页互物号',
            'slug' => 'landing-oa',
            'owner_id' => $user->id,
            'status' => 'active',
            'description' => '首页生态卡片',
        ]);

        ForumPost::create([
            'user_id' => $user->id,
            'content' => '首页社区动态内容足够长，用于落地页展示测试。',
            'status' => 'published',
        ]);

        $oa = $this->getJson('/api/official-accounts/public?per_page=4&sort=followers')
            ->assertOk()
            ->assertJsonPath('success', true);

        $oaData = $oa->json('data');
        $this->assertIsArray($oaData);
        $this->assertTrue(array_is_list($oaData), '互物号公开列表 data 必须是数组');
        $this->assertNotEmpty($oaData);
        $this->assertArrayHasKey('meta', $oa->json());

        // 首页错误地请求需登录的接口时应 401（回归保护）
        $this->getJson('/api/official-accounts?per_page=4&sort=followers')
            ->assertUnauthorized();

        $moments = $this->getJson('/api/moments/public?per_page=4')
            ->assertOk()
            ->assertJsonPath('success', true);

        $momentData = $moments->json('data');
        $this->assertIsArray($momentData);
        $this->assertTrue(array_is_list($momentData));
        $this->assertNotEmpty($momentData);
    }
}
