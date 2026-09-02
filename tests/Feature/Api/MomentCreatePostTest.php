<?php

namespace Tests\Feature\Api;

use App\Models\ForumPost;
use App\Models\ForumTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class MomentCreatePostTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->actingAs($this->user);
    }

    /** @test */
    public function can_create_text_post_via_json(): void
    {
        $response = $this->postJson('/api/moments', [
            'content' => '这是一条社区帖子',
            'template' => 'discuss',
        ]);

        $response->assertCreated()->assertJsonPath('success', true);
        $this->assertDatabaseHas('forum_posts', [
            'user_id' => $this->user->id,
            'content' => '这是一条社区帖子',
            'status' => 'published',
        ]);
    }

    /** @test */
    public function can_create_post_with_chinese_tags(): void
    {
        $response = $this->postJson('/api/moments', [
            'content' => '带标签的帖子',
            'tags' => ['授权', 'SDK'],
        ]);

        $response->assertCreated();
        $post = ForumPost::where('content', '带标签的帖子')->first();
        $this->assertNotNull($post);
        $this->assertGreaterThan(0, $post->tags()->count());
        foreach ($post->tags as $tag) {
            $this->assertNotSame('', $tag->slug);
        }
    }

    /** @test */
    public function can_create_post_via_multipart_form_data(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/moments', [
            'content' => 'FormData 发帖',
            'template' => 'discuss',
            'tags' => ['测试'],
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ], ['Accept' => 'application/json']);

        $response->assertCreated();
        $post = ForumPost::where('content', 'FormData 发帖')->first();
        $this->assertNotNull($post);
        $this->assertNotEmpty($post->images);
    }

    /** @test */
    public function multipart_with_forced_content_type_still_parses_content(): void
    {
        // 前端曾强制 Content-Type: multipart/form-data（无 boundary），模拟真实表单字段
        $response = $this->call(
            'POST',
            '/api/moments',
            ['content' => '纯文字发帖', 'template' => 'discuss'],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'multipart/form-data',
            ]
        );

        $response->assertCreated();
    }
}
