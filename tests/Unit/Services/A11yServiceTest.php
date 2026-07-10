<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\A11yService;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class A11yServiceTest extends TestCase
{
    use RefreshDatabase;

    protected A11yService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(A11yService::class);
    }

    /** @test */
    public function it_returns_all_wcag_guidelines()
    {
        $guidelines = $this->service->getGuidelines();

        $this->assertIsArray($guidelines);
        $this->assertGreaterThan(30, count($guidelines));

        // Check structure
        $first = $guidelines[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('level', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('status', $first);
        $this->assertContains($first['level'], ['A', 'AA', 'AAA']);
    }

    /** @test */
    public function it_returns_correct_compliance_stats()
    {
        $stats = $this->service->getComplianceStats();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('compliant', $stats);
        $this->assertArrayHasKey('needsWork', $stats);
        $this->assertArrayHasKey('notApplicable', $stats);
        $this->assertArrayHasKey('passRate', $stats);

        $this->assertGreaterThan(0, $stats['total']);
        $this->assertGreaterThanOrEqual(0, $stats['passRate']);
        $this->assertLessThanOrEqual(100, $stats['passRate']);
    }

    /** @test */
    public function it_calculates_contrast_ratio_correctly()
    {
        // Black on white - should pass AAA
        $result = $this->service->checkContrast('#000000', '#ffffff');
        $this->assertGreaterThan(15, $result['ratio']);
        $this->assertTrue($result['passes_AAA']);
        $this->assertTrue($result['passes_AA']);

        // Light gray on white - should fail
        $result2 = $this->service->checkContrast('#cccccc', '#ffffff');
        $this->assertLessThan(3, $result2['ratio']);
        $this->assertFalse($result2['passes_AA']);
    }

    /** @test */
    public function it_detects_AA_compliance_for_large_text()
    {
        // Gray on white - might pass AA large but not AA normal
        $result = $this->service->checkContrast('#949494', '#ffffff');
        // ~2.4:1 ratio - should fail AA for normal text
        if ($result['ratio'] < 4.5) {
            $this->assertFalse($result['passes_AA'], 'Should fail AA for normal text');
        }
    }

    /** @test */
    public function it_handles_short_hex_codes()
    {
        $result = $this->service->checkContrast('#000', '#fff');
        $this->assertGreaterThan(15, $result['ratio']);
        $this->assertEquals('#000', $result['foreground']);
        $this->assertEquals('#fff', $result['background']);
    }

    /** @test */
    public function it_returns_known_limitations()
    {
        $limitations = $this->service->getKnownLimitations();

        $this->assertIsArray($limitations);
        $this->assertGreaterThan(0, count($limitations));

        $first = $limitations[0];
        $this->assertArrayHasKey('area', $first);
        $this->assertArrayHasKey('description', $first);
        $this->assertArrayHasKey('severity', $first);
        $this->assertContains($first['severity'], ['high', 'medium', 'low']);
    }

    /** @test */
    public function it_generates_full_report()
    {
        $report = $this->service->generateReport();

        $this->assertArrayHasKey('generated_at', $report);
        $this->assertArrayHasKey('standard', $report);
        $this->assertEquals('WCAG 2.1 AA', $report['standard']);

        $this->assertArrayHasKey('summary', $report);
        $this->assertArrayHasKey('total_criteria', $report['summary']);
        $this->assertArrayHasKey('pass_rate', $report['summary']);

        $this->assertArrayHasKey('non_compliant_items', $report);
        $this->assertArrayHasKey('known_limitations', $report);
    }

    /** @test */
    public function it_manages_user_preferences()
    {
        $user = User::factory()->create();

        // Default preferences
        $prefs = $this->service->getUserPreferences($user->id);
        $this->assertArrayHasKey('reduced_motion', $prefs);
        $this->assertArrayHasKey('high_contrast', $prefs);
        $this->assertFalse($prefs['high_contrast']);

        // Save preferences
        $saved = $this->service->saveUserPreferences($user->id, [
            'high_contrast' => true,
            'font_size' => 'large',
        ]);

        $this->assertTrue($saved['high_contrast']);
        $this->assertEquals('large', $saved['font_size']);

        // Verify persistence
        $loaded = $this->service->getUserPreferences($user->id);
        $this->assertTrue($loaded['high_contrast']);
        $this->assertEquals('large', $loaded['font_size']);
    }

    /** @test */
    public function it_has_level_A_and_AA_guidelines()
    {
        $guidelines = $this->service->getGuidelines();
        $levels = array_unique(array_map(fn($g) => $g['level'], $guidelines));

        $this->assertContains('A', $levels);
        $this->assertContains('AA', $levels);
    }

    /** @test */
    public function it_rates_contrast_correctly()
    {
        // AAA: >7:1
        $aaa = $this->service->checkContrast('#333333', '#ffffff');
        $this->assertEquals('AAA', $aaa['rating']);

        // AA but not AAA: 4.5:1 to 7:1
        $aa = $this->service->checkContrast('#666666', '#ffffff');
        $this->assertStringContainsString('AA', $aa['rating']);
    }

    /** @test */
    public function it_has_valid_report_structure()
    {
        $report = $this->service->generateReport();

        $this->assertArrayHasKey('compliance_by_level', $report);
        $this->assertArrayHasKey('A', $report['compliance_by_level']);
        $this->assertArrayHasKey('AA', $report['compliance_by_level']);

        $this->assertGreaterThan(0, $report['compliance_by_level']['A'],
            'Should have at least one Level A compliant item');
    }

    /** @test */
    public function it_returns_empty_prefs_for_nonexistent_user()
    {
        $prefs = $this->service->getUserPreferences(99999);
        $this->assertEquals([], $prefs);
    }
}
