<?php

namespace Tests\Unit\Services;

use App\Models\DemoSession;
use App\Services\DemoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DemoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DemoService::class);
    }

    /** @test */
    public function it_creates_a_new_demo_session()
    {
        $session = $this->service->createSession('test-session-1', '127.0.0.1', 'TestAgent/1.0');

        $this->assertInstanceOf(DemoSession::class, $session);
        $this->assertEquals('active', $session->status);
        $this->assertEquals('test-session-1', $session->session_id);
        $this->assertNotNull($session->token);
        $this->assertTrue(strlen($session->token) >= 32);
    }

    /** @test */
    public function it_returns_existing_active_session_for_same_session_id()
    {
        $first = $this->service->createSession('test-session-2');
        $second = $this->service->createSession('test-session-2');

        $this->assertEquals($first->id, $second->id);
        $this->assertEquals($first->token, $second->token);
    }

    /** @test */
    public function it_validates_session_token()
    {
        $session = $this->service->createSession('test-session-3');

        $found = $this->service->getSession($session->token);
        $this->assertNotNull($found);
        $this->assertEquals($session->id, $found->id);

        $notFound = $this->service->getSession('invalid-token');
        $this->assertNull($notFound);
    }

    /** @test */
    public function it_returns_null_for_expired_session()
    {
        $session = DemoSession::createSession('test-expired', '127.0.0.1');
        $session->update(['expires_at' => now()->subMinute()]);

        $found = $this->service->getSession($session->token);
        $this->assertNull($found);
    }

    /** @test */
    public function it_advances_guide_step()
    {
        $session = $this->service->createSession('test-step');
        $this->assertEquals(0, $session->step);

        $session = $this->service->advanceStep($session, 2);
        $this->assertEquals(2, $session->step);
    }

    /** @test */
    public function it_records_completed_actions()
    {
        $session = $this->service->createSession('test-actions');

        $session = $this->service->completeAction($session, 'view_dashboard');
        $this->assertContains('view_dashboard', $session->completed_actions);

        // 重复记录不增加
        $session = $this->service->completeAction($session, 'view_dashboard');
        $this->assertCount(1, $session->completed_actions);
    }

    /** @test */
    public function it_returns_correct_step_info()
    {
        $session = $this->service->createSession('test-step-info');
        $stepInfo = $this->service->getCurrentStep($session);

        $this->assertArrayHasKey('current', $stepInfo);
        $this->assertArrayHasKey('step', $stepInfo);
        $this->assertArrayHasKey('total', $stepInfo);
        $this->assertArrayHasKey('progress', $stepInfo);
        $this->assertArrayHasKey('completed_actions', $stepInfo);
        $this->assertEquals(0, $stepInfo['step']);
        $this->assertGreaterThan(0, $stepInfo['total']);
    }

    /** @test */
    public function it_extends_session_time()
    {
        $session = $this->service->createSession('test-extend');
        $originalExpiry = $session->expires_at;

        $session = $this->service->extendSession($session, 15);

        $this->assertTrue($session->expires_at->gt($originalExpiry));
    }

    /** @test */
    public function it_marks_session_as_completed()
    {
        $session = $this->service->createSession('test-complete');

        $session = $this->service->completeSession($session);
        $this->assertEquals('completed', $session->status);
    }

    /** @test */
    public function it_returns_heartbeat_data()
    {
        $session = $this->service->createSession('test-heartbeat');

        $hb = $this->service->heartbeat($session);

        $this->assertArrayHasKey('remaining_seconds', $hb);
        $this->assertArrayHasKey('expiring_soon', $hb);
        $this->assertArrayHasKey('status', $hb);
        $this->assertArrayHasKey('current_step', $hb);
        $this->assertEquals('active', $hb['status']);
    }

    /** @test */
    public function it_returns_demo_data()
    {
        $session = $this->service->createSession('test-data');

        $data = $this->service->getDemoData($session, 'dashboard');
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('revenue_trend', $data);
        $this->assertArrayHasKey('activities', $data);

        $products = $this->service->getDemoData($session, 'products');
        $this->assertCount(4, $products);
    }

    /** @test */
    public function it_cleans_up_expired_sessions()
    {
        DemoSession::createSession('clean-1', '127.0.0.1');
        $expired = DemoSession::createSession('clean-2');
        $expired->update(['expires_at' => now()->subMinute()]);

        $count = $this->service->cleanupExpiredSessions();
        $this->assertGreaterThanOrEqual(1, $count);

        $this->assertEquals('expired', $expired->fresh()->status);
    }

    /** @test */
    public function it_starts_with_welcome_step()
    {
        $session = $this->service->createSession('test-welcome');
        $info = $this->service->getCurrentStep($session);

        $this->assertEquals('welcome', $info['current']['page']);
        $this->assertEquals('欢迎', $info['current']['title']);
    }
}
