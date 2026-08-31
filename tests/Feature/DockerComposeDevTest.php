<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * T-17 / D-20：Docker Compose 配置冒烟
 */
class DockerComposeDevTest extends TestCase
{
    public function test_root_compose_includes_dev_stack(): void
    {
        $root = base_path('docker-compose.yml');

        $this->assertFileExists($root);
        $this->assertStringContainsString('docker-compose.dev.yml', file_get_contents($root));
    }

    public function test_dev_compose_defines_required_services(): void
    {
        $yaml = file_get_contents(base_path('deploy/docker/docker-compose.dev.yml'));

        foreach (['postgres', 'redis', 'meilisearch', 'ollama', 'reverb', 'queue', 'mailpit'] as $service) {
            $this->assertStringContainsString("{$service}:", $yaml, "Missing service: {$service}");
        }
    }

    public function test_cli_dockerfile_and_entrypoint_exist(): void
    {
        $this->assertFileExists(base_path('deploy/docker/Dockerfile.cli'));
        $this->assertFileExists(base_path('deploy/docker/entrypoint-cli.sh'));
    }

    public function test_docker_up_scripts_exist(): void
    {
        $this->assertFileExists(base_path('scripts/docker-up.ps1'));
        $this->assertFileExists(base_path('scripts/docker-up.sh'));
    }

    public function test_docker_compose_config_valid_when_docker_available(): void
    {
        if (! $this->dockerAvailable()) {
            $this->markTestSkipped('Docker 未安装，跳过 compose config 校验');
        }

        $file = base_path('deploy/docker/docker-compose.dev.yml');
        $cmd = 'docker compose -f ' . escapeshellarg($file) . ' config 2>&1';
        $output = shell_exec($cmd);

        $this->assertIsString($output);
        $this->assertStringContainsString('postgres:', $output);
        $this->assertStringContainsString('reverb:', $output);
        $this->assertStringNotContainsString('validating', strtolower($output));
    }

    protected function dockerAvailable(): bool
    {
        $out = shell_exec('docker compose version 2>&1');

        return is_string($out) && str_contains($out, 'Docker Compose');
    }
}
