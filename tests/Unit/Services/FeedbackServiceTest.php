<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\CustomerFeedback;
use App\Models\FeedbackTag;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeedbackService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class FeedbackServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeedbackService::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_can_create_feedback(): void
    {
        $feedback = $this->service->create([
            'type' => 'bug',
            'subject' => '登录页面报错',
            'message' => '点击登录后页面报500错误',
            'rating' => 2,
            'page_url' => '/login',
            'page_title' => '登录页',
            'browser' => 'Chrome 120',
            'os' => 'Windows 10',
            'screen_resolution' => '1920x1080',
        ], auth()->user());

        $this->assertNotNull($feedback);
        $this->assertEquals('bug', $feedback->fresh()->type);
        $this->assertEquals('new', $feedback->fresh()->status);
        $this->assertEquals('normal', $feedback->fresh()->priority);
        $this->assertEquals(2, $feedback->fresh()->rating);
        $this->assertEquals(auth()->id(), $feedback->fresh()->user_id);
    }

    public function test_can_list_feedback(): void
    {
        CustomerFeedback::factory()->count(5)->create();
        CustomerFeedback::factory()->create(['type' => 'feature_request']);
        CustomerFeedback::factory()->create(['status' => 'resolved']);

        $result = $this->service->list([], 10);
        $this->assertCount(7, $result->items());

        $filtered = $this->service->list(['type' => 'feature_request'], 10);
        // factory may also generate feature_request randomly, so we use >= 1
        $this->assertGreaterThanOrEqual(1, $filtered->total());

        $filtered2 = $this->service->list(['status' => 'resolved'], 10);
        $this->assertGreaterThanOrEqual(1, $filtered2->total());
    }

    public function test_can_update_feedback(): void
    {
        $feedback = CustomerFeedback::factory()->create();
        $tag = FeedbackTag::factory()->create();

        $updated = $this->service->update($feedback->id, [
            'priority' => 'high',
            'status' => 'under_review',
            'tags' => [$tag->id],
        ]);

        $this->assertEquals('high', $updated->priority);
        $this->assertEquals('under_review', $updated->status);
        $this->assertCount(1, $updated->tags);
    }

    public function test_can_assign_feedback(): void
    {
        $feedback = CustomerFeedback::factory()->create(['status' => 'new']);
        $admin = User::factory()->create();

        $assigned = $this->service->assign($feedback->id, $admin->id);

        $this->assertEquals($admin->id, $assigned->assigned_to);
        $this->assertNotNull($assigned->assigned_at);
        $this->assertEquals('under_review', $assigned->fresh()->status);
    }

    public function test_can_reply_to_feedback(): void
    {
        $feedback = CustomerFeedback::factory()->create();

        $replied = $this->service->reply($feedback->id, '感谢您的反馈，已转开发团队处理。');

        $this->assertEquals('感谢您的反馈，已转开发团队处理。', $replied->admin_reply);
        $this->assertNotNull($replied->replied_at);
        $this->assertEquals('acknowledged', $replied->status);
    }

    public function test_can_resolve_feedback(): void
    {
        $feedback = CustomerFeedback::factory()->create();

        $resolved = $this->service->resolve($feedback->id, 'resolved');

        $this->assertEquals('resolved', $resolved->status);
        $this->assertNotNull($resolved->resolved_at);
    }

    public function test_can_get_stats(): void
    {
        CustomerFeedback::factory()->count(3)->create(['type' => 'bug']);
        CustomerFeedback::factory()->create(['type' => 'feature_request', 'status' => 'resolved']);
        CustomerFeedback::factory()->create(['rating' => 4]);
        CustomerFeedback::factory()->create(['rating' => 5, 'status' => 'new']);

        $stats = $this->service->getStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_type', $stats);
        $this->assertArrayHasKey('by_priority', $stats);
        $this->assertGreaterThan(5, $stats['total']);
    }

    public function test_can_manage_tags(): void
    {
        $tag = $this->service->createTag(['name' => '前端反馈', 'color' => '#67c23a']);
        $this->assertEquals('前端反馈', $tag->name);

        $tags = $this->service->listTags();
        $this->assertCount(1, $tags);
    }

    public function test_can_get_my_feedback(): void
    {
        $user = auth()->user();
        CustomerFeedback::factory()->count(3)->create(['user_id' => $user->id]);
        CustomerFeedback::factory()->count(2)->create(); // other users

        $my = $this->service->myFeedback($user);
        $this->assertCount(3, $my->items());
    }

    public function test_can_filter_feedback_by_search(): void
    {
        CustomerFeedback::factory()->create(['message' => '数据库查询很慢']);
        CustomerFeedback::factory()->create(['message' => '页面加载很快']);

        $result = $this->service->list(['search' => '很慢'], 10);
        $this->assertCount(1, $result->items());
    }
}
