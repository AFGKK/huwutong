<?php

namespace Tests\Unit\Services;

use App\Models\CustomerFeedback;
use App\Models\FeatureVote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\FeedbackService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class FeedbackVoteTest extends TestCase
{
    use RefreshDatabase;

    protected FeedbackService $service;
    protected Tenant $tenant;
    protected User $user;
    protected CustomerFeedback $feedback;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FeedbackService::class);
        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->feedback = CustomerFeedback::factory()->create([
            'type' => 'feature_request',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_add_a_vote()
    {
        $result = $this->service->vote($this->feedback->id, $this->user, 1);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals(1, $result['vote_count']);
        $this->assertEquals(1, $result['user_vote']);
        $this->assertDatabaseHas('feature_votes', [
            'feedback_id' => $this->feedback->id,
            'user_id' => $this->user->id,
            'vote' => 1,
        ]);
    }

    /** @test */
    public function it_can_add_a_downvote()
    {
        $result = $this->service->vote($this->feedback->id, $this->user, -1);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals(-1, $result['vote_count']);
        $this->assertEquals(-1, $result['user_vote']);
    }

    /** @test */
    public function it_removes_vote_on_same_action()
    {
        $this->service->vote($this->feedback->id, $this->user, 1);
        $result = $this->service->vote($this->feedback->id, $this->user, 1);

        $this->assertEquals('removed', $result['action']);
        $this->assertEquals(0, $result['vote_count']);
        $this->assertNull($result['user_vote']);
        $this->assertDatabaseMissing('feature_votes', [
            'feedback_id' => $this->feedback->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_changes_vote_direction()
    {
        $this->service->vote($this->feedback->id, $this->user, 1);
        $result = $this->service->vote($this->feedback->id, $this->user, -1);

        $this->assertEquals('changed', $result['action']);
        $this->assertEquals(-1, $result['vote_count']);
        $this->assertEquals(-1, $result['user_vote']);
        $this->assertDatabaseHas('feature_votes', [
            'feedback_id' => $this->feedback->id,
            'user_id' => $this->user->id,
            'vote' => -1,
        ]);
    }

    /** @test */
    public function it_calculates_vote_count_correctly()
    {
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user3 = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->service->vote($this->feedback->id, $this->user, 1);
        $this->service->vote($this->feedback->id, $user2, 1);
        $this->service->vote($this->feedback->id, $user3, -1);

        $result = $this->service->vote($this->feedback->id, $this->user, 1); // toggle off

        $this->assertEquals(0, $result['vote_count']); // user1 toggles off: user2(+1) + user3(-1) = 0
    }

    /** @test */
    public function it_shows_user_vote_status()
    {
        $this->service->vote($this->feedback->id, $this->user, 1);

        $this->assertEquals(1, $this->service->getUserVote($this->feedback->id, $this->user->id));
    }

    /** @test */
    public function it_returns_null_for_non_voter()
    {
        $this->assertNull($this->service->getUserVote($this->feedback->id, $this->user->id));
    }

    /** @test */
    public function it_lists_feedback_with_vote_counts()
    {
        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->service->vote($this->feedback->id, $this->user, 1);
        $this->service->vote($this->feedback->id, $user2, 1);

        $result = $this->service->listWithVotes([], 20, $this->user->id);

        $this->assertNotEmpty($result['data']);

        $this->assertNotEmpty($result['data']);
        $found = collect($result['data'])->firstWhere('id', $this->feedback->id);
        $this->assertNotNull($found);
        $this->assertEquals(2, $found['vote_count']);
        $this->assertEquals(1, $found['user_vote']);
    }

    /** @test */
    public function it_can_sort_by_votes()
    {
        $fb2 = CustomerFeedback::factory()->create([
            'type' => 'feature_request',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $user2 = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $user3 = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // fb2 gets 1 upvote; original gets 3 upvotes
        $this->service->vote($fb2->id, $user2, 1);
        $this->service->vote($this->feedback->id, $this->user, 1);
        $this->service->vote($this->feedback->id, $user2, 1);
        $this->service->vote($this->feedback->id, $user3, 1);

        $result = $this->service->listWithVotes(['sort' => 'votes'], 20);

        $items = $result['data'];
        $this->assertEquals($this->feedback->id, $items[0]['id']); // 3 votes first
        $this->assertEquals($fb2->id, $items[1]['id']); // 1 vote second
    }

    /** @test */
    public function it_returns_vote_stats()
    {
        $fb2 = CustomerFeedback::factory()->create([
            'type' => 'feature_request',
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->service->vote($this->feedback->id, $this->user, 1);
        $this->service->vote($fb2->id, $this->user, 1);

        $stats = $this->service->getVoteStats();

        $this->assertEquals(2, $stats['total_votes']);
        $this->assertEquals(2, $stats['total_upvotes']);
        $this->assertEquals(0, $stats['total_downvotes']);
        $this->assertCount(2, $stats['most_voted']);
    }

    /** @test */
    public function it_handles_invalid_vote_value()
    {
        $result = $this->service->vote($this->feedback->id, $this->user, 999);

        $this->assertEquals('added', $result['action']);
        $this->assertEquals(1, $result['vote_count']);
    }
}
