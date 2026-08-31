<?php

namespace Tests\Unit\Services;

use App\Services\OllamaSetupService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OllamaSetupServiceTest extends TestCase
{
    public function test_health_returns_unavailable_when_ollama_down(): void
    {
        config(['local-llm.ollama.api_base' => 'http://127.0.0.1:1']);

        $health = app(OllamaSetupService::class)->health();

        $this->assertSame('unavailable', $health['status']);
        $this->assertSame(0, $health['count']);
    }

    public function test_health_returns_models_from_tags_api(): void
    {
        config(['local-llm.ollama.api_base' => 'http://127.0.0.1:11434']);

        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response([
                'models' => [
                    ['name' => 'qwen2.5:7b', 'size' => 1000, 'modified_at' => '2026-01-01'],
                ],
            ], 200),
        ]);

        $health = app(OllamaSetupService::class)->health();

        $this->assertSame('available', $health['status']);
        $this->assertSame(1, $health['count']);
        $this->assertSame('qwen2.5:7b', $health['models'][0]['name']);
    }

    public function test_pull_model_posts_to_ollama_api(): void
    {
        config(['local-llm.ollama.api_base' => 'http://127.0.0.1:11434']);

        Http::fake([
            '127.0.0.1:11434/api/tags' => Http::response(['models' => []], 200),
            '127.0.0.1:11434/api/pull' => Http::response(['status' => 'success'], 200),
        ]);

        $result = app(OllamaSetupService::class)->pullModel('qwen2.5:1.5b');

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://127.0.0.1:11434/api/pull'
                && $request['name'] === 'qwen2.5:1.5b';
        });
    }

    public function test_recommended_models_from_config(): void
    {
        config(['local-llm.recommended_models' => ['test-model:latest']]);

        $this->assertSame(['test-model:latest'], app(OllamaSetupService::class)->recommendedModels());
    }
}
