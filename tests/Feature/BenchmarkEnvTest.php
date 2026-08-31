<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * D-39 / T-24：压测环境配置冒烟
 */
class BenchmarkEnvTest extends TestCase
{
    public function test_benchmark_compose_defines_required_services(): void
    {
        $yaml = file_get_contents(base_path('deploy/benchmark/docker-compose.benchmark.yml'));

        foreach (['postgres', 'redis', 'app-fpm', 'app-octane', 'nginx', 'queue'] as $service) {
            $this->assertStringContainsString("{$service}:", $yaml, "Missing: {$service}");
        }
    }

    public function test_benchmark_dockerfile_and_nginx_config_exist(): void
    {
        $this->assertFileExists(base_path('deploy/benchmark/Dockerfile.app'));
        $this->assertFileExists(base_path('deploy/benchmark/nginx.benchmark.conf'));
        $this->assertFileExists(base_path('deploy/benchmark/php/opcache.ini'));
    }

    public function test_benchmark_scripts_exist(): void
    {
        $this->assertFileExists(base_path('scripts/benchmark-up.ps1'));
        $this->assertFileExists(base_path('scripts/benchmark-smoke.ps1'));
    }

    public function test_benchmark_env_check_command_is_registered(): void
    {
        $this->artisan('benchmark:env-check --json')
            ->assertExitCode(1);

        $this->assertStringContainsString(
            'benchmark:env-check',
            file_get_contents(base_path('app/Console/Commands/BenchmarkEnvCheckCommand.php'))
        );
    }

    public function test_nginx_config_uses_php_fpm_upstream(): void
    {
        $nginx = file_get_contents(base_path('deploy/benchmark/nginx.benchmark.conf'));

        $this->assertStringContainsString('upstream hwt_backend', $nginx);
        $this->assertStringContainsString('server app-octane:8000', $nginx);
        $this->assertStringContainsString('server app-fpm:9000 backup;', $nginx);
    }

    public function test_opcache_enabled_in_benchmark_php_ini(): void
    {
        $ini = file_get_contents(base_path('deploy/benchmark/php/opcache.ini'));

        $this->assertStringContainsString('opcache.enable=1', $ini);
        $this->assertStringContainsString('opcache.jit=', $ini);
    }
}
