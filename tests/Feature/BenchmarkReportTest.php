<?php

namespace Tests\Feature;

use Tests\TestCase;

class BenchmarkReportTest extends TestCase
{
    public function test_benchmark_report_command_is_registered(): void
    {
        $this->artisan('benchmark:report --help')
            ->assertExitCode(0);
    }

    public function test_qps_target_script_exists(): void
    {
        $js = file_get_contents(base_path('benchmarks/k6/scripts/qps-target.js'));

        $this->assertStringContainsString('constant-arrival-rate', $js);
        $this->assertStringContainsString('TARGET_QPS', $js);
        $this->assertStringContainsString('k6-qps-summary.json', $js);
    }

    public function test_load_test_has_d40_stage(): void
    {
        $js = file_get_contents(base_path('benchmarks/k6/scripts/load-test.js'));

        $this->assertStringContainsString("STAGE=d40", $js);
        $this->assertStringContainsString('d40Stages', $js);
    }

    public function test_benchmark_run_full_scripts_exist(): void
    {
        $this->assertFileExists(base_path('scripts/benchmark-run-full.ps1'));
        $this->assertFileExists(base_path('scripts/benchmark-run-full.sh'));
    }

    public function test_benchmark_report_generates_json(): void
    {
        $this->artisan('benchmark:report', [
            '--base-url' => 'http://127.0.0.1:1/api',
            '--requests' => 10,
            '--concurrency' => 2,
            '--target-qps' => 5000,
            '--skip-server' => true,
        ])->assertExitCode(1);

        $path = base_path('benchmarks/results/benchmark-result.json');
        $this->assertFileExists($path);

        $data = json_decode(file_get_contents($path), true);
        $this->assertSame('D-40', $data['mission']);
        $this->assertArrayHasKey('summary', $data);
    }
}
