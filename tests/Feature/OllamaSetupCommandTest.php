<?php

namespace Tests\Feature;

use App\Services\OllamaSetupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaSetupCommandTest extends TestCase
{
    public function test_setup_status_command_fails_when_unavailable(): void
    {
        config(['local-llm.ollama.api_base' => 'http://127.0.0.1:1']);

        $this->artisan('ollama:setup', ['--status' => true])
            ->expectsOutputToContain('Ollama 未运行')
            ->assertExitCode(1);
    }

    public function test_setup_status_command_succeeds_with_models(): void
    {
        config(['local-llm.ollama.api_base' => 'http://127.0.0.1:11434']);

        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5:7b'],
                ],
            ], 200),
        ]);

        $this->artisan('ollama:setup', ['--status' => true])
            ->expectsOutputToContain('available')
            ->expectsOutputToContain('qwen2.5:7b')
            ->assertExitCode(0);
    }

    /**
     * @group ollama-integration
     */
    public function test_live_ollama_tags_when_running(): void
    {
        $service = app(OllamaSetupService::class);
        if (! $service->isAvailable()) {
            $this->markTestSkipped('Ollama 未启动，跳过集成测试');
        }

        $health = $service->health();
        $this->assertSame('available', $health['status']);
    }
}
