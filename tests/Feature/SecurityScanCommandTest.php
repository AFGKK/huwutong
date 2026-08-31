<?php

namespace Tests\Feature;

use App\Models\SecurityScanResult;
use Tests\TestCase;

class SecurityScanCommandTest extends TestCase
{
    public function test_security_scan_command_is_registered(): void
    {
        $this->artisan('security:scan --help')
            ->assertExitCode(0);
    }

    public function test_quick_scan_runs_static_analysis_without_zap(): void
    {
        $before = SecurityScanResult::count();

        $this->artisan('security:scan --quick')
            ->assertExitCode(0);

        $this->assertGreaterThan($before, SecurityScanResult::count());

        $latest = SecurityScanResult::latest('executed_at')->first();
        $this->assertSame('static', $latest->scan_type);
        $this->assertTrue($latest->passed);
    }

    public function test_report_option_shows_last_scan(): void
    {
        SecurityScanResult::create([
            'scan_type' => 'static',
            'target_url' => 'http://localhost:8000',
            'high_count' => 0,
            'medium_count' => 0,
            'low_count' => 0,
            'passed' => true,
            'alerts' => [],
            'executed_at' => now(),
        ]);

        $this->artisan('security:scan --report')
            ->assertExitCode(0);
    }

    public function test_zap_workflow_and_policy_exist(): void
    {
        $this->assertFileExists(base_path('.github/workflows/security-scan.yml'));
        $this->assertFileExists(base_path('.ci/zap/policies/HWT-Security-Policy.xml'));
        $this->assertFileExists(base_path('scripts/security-scan-local.ps1'));
    }

    public function test_security_scan_workflow_blocks_on_high(): void
    {
        $yaml = file_get_contents(base_path('.github/workflows/security-scan.yml'));

        $this->assertStringContainsString('zaproxy/action-baseline', $yaml);
        $this->assertStringContainsString('blocked=true', $yaml);
        $this->assertStringContainsString('exit 1', $yaml);
    }
}
