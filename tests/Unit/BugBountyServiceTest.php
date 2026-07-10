<?php

namespace Tests\Unit;

use App\Models\BugBountyHallOfFame;
use App\Models\BugBountyReport;
use App\Services\BugBountyService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class BugBountyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BugBountyService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BugBountyService();
    }

    /** @test */
    public function it_can_submit_a_report()
    {
        $data = [
            'title' => 'XSS in user profile page',
            'description' => 'Found an XSS vulnerability in the user profile name field.',
            'steps_to_reproduce' => "1. Go to /profile\n2. Enter <script>alert(1)</script>\n3. Save",
            'impact' => 'An attacker can steal cookies',
            'vulnerability_type' => 'XSS',
            'affected_endpoint' => '/api/user/profile',
            'reporter_email' => 'researcher@example.com',
            'reporter_name' => 'Alice',
            'reporter_handle' => 'alice_h1',
        ];

        $report = $this->service->submitReport($data);

        $this->assertInstanceOf(BugBountyReport::class, $report);
        $this->assertEquals('XSS in user profile page', $report->title);
        $this->assertEquals('submitted', $report->status);
        $this->assertEquals('XSS', $report->vulnerability_type);
        $this->assertEquals('researcher@example.com', $report->reporter_email);
        $this->assertDatabaseHas('bug_bounty_reports', ['id' => $report->id, 'status' => 'submitted']);
    }

    /** @test */
    public function it_can_review_a_report()
    {
        $report = BugBountyReport::factory()->create(['status' => 'submitted']);

        $result = $this->service->review($report->id, 'security_team_lead');

        $this->assertEquals('under_review', $result->status);
        $this->assertEquals('security_team_lead', $result->assigned_to);
    }

    /** @test */
    public function it_can_confirm_a_report()
    {
        $report = BugBountyReport::factory()->create([
            'status' => 'under_review',
            'severity' => 'medium',
        ]);

        $result = $this->service->confirm($report->id, [
            'severity' => 'high',
            'bounty_amount' => 500,
            'is_public' => true,
            'resolution_notes' => 'Confirmed as valid XSS',
        ]);

        $this->assertEquals('confirmed', $result->status);
        $this->assertEquals('high', $result->severity);
        $this->assertEquals(500, (float) $result->bounty_amount);
        $this->assertTrue((bool) $result->is_public);
        $this->assertNotNull($result->confirmed_at);
    }

    /** @test */
    public function it_can_mark_report_as_fixed()
    {
        $report = BugBountyReport::factory()->create([
            'status' => 'confirmed',
            'severity' => 'high',
            'bounty_amount' => 500,
            'is_public' => true,
        ]);

        $result = $this->service->markFixed($report->id, 'Fixed in v2.3.1');

        $this->assertEquals('fixed', $result->status);
        $this->assertNotNull($result->fixed_at);

        // Verify Hall of Fame entry was created
        $this->assertDatabaseHas('bug_bounty_hall_of_fame', [
            'hacker_handle' => $report->reporter_handle,
        ]);
    }

    /** @test */
    public function it_can_decline_a_report()
    {
        $report = BugBountyReport::factory()->create(['status' => 'submitted']);

        $result = $this->service->decline($report->id, 'Not reproducible on latest version');

        $this->assertEquals('declined', $result->status);
        $this->assertEquals('Not reproducible on latest version', $result->resolution_notes);
    }

    /** @test */
    public function it_can_mark_report_as_paid()
    {
        $report = BugBountyReport::factory()->create([
            'status' => 'fixed',
            'bounty_amount' => 500,
        ]);

        $result = $this->service->markPaid($report->id);

        $this->assertEquals('paid', $result->status);
        $this->assertNotNull($result->paid_at);
    }

    /** @test */
    public function it_returns_stats_correctly()
    {
        BugBountyReport::factory()->count(3)->create(['severity' => 'critical', 'status' => 'submitted']);
        BugBountyReport::factory()->count(5)->create(['severity' => 'high', 'status' => 'fixed']);
        BugBountyReport::factory()->count(2)->create(['severity' => 'medium', 'status' => 'paid', 'bounty_amount' => 200]);

        $stats = $this->service->getStats();

        $this->assertEquals(10, $stats['total']);
        $this->assertEquals(3, $stats['by_severity']['critical']['count']);
        $this->assertEquals(5, $stats['by_severity']['high']['count']);
        $this->assertEquals(3, $stats['by_status']['submitted']['count']);
        $this->assertEquals(2, $stats['by_status']['paid']['count']);
        $this->assertEquals(400, $stats['total_bounty_paid']); // 2 * 200
    }

    /** @test */
    public function it_lists_reports_with_filters()
    {
        BugBountyReport::factory()->create(['severity' => 'critical', 'status' => 'submitted', 'title' => 'SQL Injection']);
        BugBountyReport::factory()->create(['severity' => 'high', 'status' => 'under_review', 'title' => 'CSRF Token']);
        BugBountyReport::factory()->create(['severity' => 'low', 'status' => 'fixed', 'title' => 'Info Leak']);

        // Filter by status
        $results = $this->service->listReports(['status' => 'submitted']);
        $this->assertEquals(1, $results->total());

        // Filter by severity
        $results = $this->service->listReports(['severity' => 'high']);
        $this->assertEquals(1, $results->total());

        // Search
        $results = $this->service->listReports(['search' => 'SQL Injection']);
        $this->assertEquals(1, $results->total());
        $this->assertEquals('SQL Injection', $results->first()->title);
    }

    /** @test */
    public function it_manages_hall_of_fame()
    {
        // Create hall of fame entries
        BugBountyHallOfFame::create([
            'hacker_name' => 'Bob',
            'hacker_handle' => 'bob_h1',
            'reports_count' => 3,
            'total_bounty' => 1500,
            'rank' => 'gold',
            'is_featured' => true,
            'sort_order' => 1,
        ]);
        BugBountyHallOfFame::create([
            'hacker_name' => 'Carol',
            'hacker_handle' => 'carol_bc',
            'reports_count' => 1,
            'total_bounty' => 200,
            'rank' => 'bronze',
            'is_featured' => false,
            'sort_order' => 2,
        ]);

        $fame = $this->service->getHallOfFame();
        $this->assertCount(2, $fame);

        // Gold should come first (sorted by rank)
        $this->assertEquals('gold', $fame->first()->rank);
    }

    /** @test */
    public function it_generates_security_txt()
    {
        $txt = BugBountyService::getSecurityTxt();
        $this->assertStringContainsString('Contact: mailto:security@huwutong.com', $txt);
        $this->assertStringContainsString('Policy: https://www.huwutong.com/security-policy', $txt);
        $this->assertStringContainsString('Canonical: https://www.huwutong.com/.well-known/security.txt', $txt);
        $this->assertStringContainsString('PGP Fingerprint:', $txt);
    }

    /** @test */
    public function it_gets_policy_content()
    {
        $policy = BugBountyService::getPolicyContent();
        $this->assertEquals('互物通 (HuWuTong) Bug Bounty Program', $policy['program_name']);
        $this->assertCount(4, $policy['scope']);
        $this->assertCount(7, $policy['rules']);
        $this->assertArrayHasKey('contact', $policy);
        $this->assertArrayHasKey('email', $policy['contact']);
    }

    /** @test */
    public function suggested_bounty_by_severity()
    {
        $this->assertEquals(1000, BugBountyReport::suggestedBounty('critical'));
        $this->assertEquals(500, BugBountyReport::suggestedBounty('high'));
        $this->assertEquals(200, BugBountyReport::suggestedBounty('medium'));
        $this->assertEquals(50, BugBountyReport::suggestedBounty('low'));
        $this->assertEquals(0, BugBountyReport::suggestedBounty('informational'));
    }
}
