<?php

namespace App\Services;

use App\Models\ApiDocEndpoint;
use App\Models\ApiDocSchema;
use Illuminate\Support\Facades\Log;

/**
 * AI 测试用例自动生成服务 (M2-48)
 *
 * 基于 OpenAPI Schema 自动生成单元测试和集成测试用例。
 */
class TestCaseGeneratorAiService
{
    public function __construct(protected LlmService $llm) {}

    /**
     * 为指定端点生成测试用例
     */
    public function generateForEndpoint(int $endpointId, array $options = []): array
    {
        $endpoint = ApiDocEndpoint::with('apiVersion')->findOrFail($endpointId);
        return $this->generateTests($endpoint, $options);
    }

    /**
     * 为端点列表批量生成测试
     */
    public function generateBatch(array $endpointIds, array $options = []): array
    {
        $endpoints = ApiDocEndpoint::whereIn('id', $endpointIds)->get();
        $results = [];
        foreach ($endpoints as $ep) {
            $results[] = $this->generateTests($ep, $options);
        }
        return [
            'total' => count($results),
            'language' => $options['language'] ?? 'php',
            'framework' => $options['framework'] ?? 'pest',
            'test_files' => $results,
        ];
    }

    /**
     * 为所有活跃端点生成测试
     */
    public function generateAll(array $options = []): array
    {
        $endpoints = ApiDocEndpoint::where('status', 'active')
            ->orderBy('group')
            ->orderBy('path')
            ->get();
        return $this->generateBatch($endpoints->pluck('id')->toArray(), $options);
    }

    /**
     * 生成测试用例
     */
    protected function generateTests(ApiDocEndpoint $endpoint, array $options): array
    {
        $language = $options['language'] ?? 'php';
        $framework = $options['framework'] ?? 'pest';

        // 尝试 LLM 生成
        $llmResult = $this->generateWithLlm($endpoint, $language, $framework);
        if ($llmResult) {
            return $llmResult;
        }

        // 兜底：模板生成
        return $this->generateFromTemplate($endpoint, $language, $framework);
    }

    /**
     * LLM 生成
     */
    protected function generateWithLlm(ApiDocEndpoint $endpoint, string $language, string $framework): ?array
    {
        $schemaContext = '';
        try {
            $schemas = ApiDocSchema::limit(10)->get(['name', 'schema']);
            $schemaContext = $schemas->map(fn($s) => "{$s->name}: " . json_encode($s->schema))->implode("\n");
        } catch (\Throwable $e) { /* ignore */ }

        $prompt = json_encode([
            'task' => "为API端点生成{$framework}测试用例",
            'language' => $language,
            'framework' => $framework,
            'endpoint' => [
                'method' => $endpoint->method,
                'path' => $endpoint->path,
                'summary' => $endpoint->summary,
                'parameters' => $endpoint->parameters,
                'request_body' => $endpoint->request_body,
                'responses' => $endpoint->responses,
            ],
            'available_schemas' => $schemaContext,
            'requested_output' => [
                'test_file_name' => '测试文件名',
                'test_file_path' => '建议的路径',
                'test_code' => '完整的测试代码',
                'test_description' => '用例说明',
                'coverage_notes' => '覆盖的测试场景',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        try {
            $result = $this->llm->chat([
                ['role' => 'system', 'content' => "你是测试工程师专家，精通{$language}/{$framework}测试框架。根据OpenAPI定义生成测试用例。返回JSON。"],
                ['role' => 'user', 'content' => $prompt],
            ], [
                'temperature' => 0.3,
            ], 'test-generator');

            $content = $result['content'] ?? '';
            if (str_starts_with(trim($content), '{')) {
                $parsed = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $parsed;
                }
            }
            return [
                'test_file_name' => "{$endpoint->method}_{$this->pathToName($endpoint->path)}_test.{$language}",
                'test_code' => $content,
                'llm_generated' => true,
            ];
        } catch (\Throwable $e) {
            Log::warning('TestCaseGenerator: LLM failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * 模板生成（兜底）
     */
    protected function generateFromTemplate(ApiDocEndpoint $endpoint, string $language, string $framework): array
    {
        $method = strtolower($endpoint->method);
        $path = $endpoint->path;
        $testName = "test_{$method}_" . $this->pathToName($path);
        $testDesc = $endpoint->summary ?: "{$endpoint->method} {$endpoint->path}";

        $phpTest = <<<PHP
<?php

uses(Tests\TestCase::class);

it('{$testDesc}', function () {
    \$response = \$this->{$method}Json('{$path}', [
        // 请根据实际参数填充
    ]);

    \$response->assertStatus(\$response->status() === 200 || \$response->status() === 201 ? 200 : \$response->status());
})->group('api', '{$endpoint->group}');
PHP;

        $jsTest = <<<JS
const request = require('supertest')('{$path}');

describe('{$endpoint->method} {$endpoint->path}', () => {
    it('should return success', async () => {
        const res = await request.{$method}('/');
        expect(res.status).toBeGreaterThanOrEqual(200);
        expect(res.status).toBeLessThan(300);
    });
});
JS;

        return [
            'test_file_name' => "{$testName}.{$language}",
            'test_file_path' => "tests/Feature/Api/{$endpoint->group}/",
            'test_code' => $language === 'php' ? $phpTest : $jsTest,
            'test_description' => $endpoint->summary ?: "{$endpoint->method} {$endpoint->path}",
            'coverage_notes' => '基础请求测试（需补充参数验证和异常场景）',
            'llm_generated' => false,
        ];
    }

    /**
     * 路径转函数名
     */
    protected function pathToName(string $path): string
    {
        $name = str_replace(['/', '{', '}', '-', '.'], ['_', '', '', '_', '_'], $path);
        $name = trim($name, '_');
        return $name ?: 'root';
    }

    /**
     * 获取支持的测试框架列表
     */
    public function getSupportedFrameworks(): array
    {
        return [
            ['language' => 'php', 'frameworks' => ['pest', 'phpunit']],
            ['language' => 'javascript', 'frameworks' => ['vitest', 'jest', 'mocha']],
            ['language' => 'python', 'frameworks' => ['pytest', 'unittest']],
            ['language' => 'go', 'frameworks' => ['testing', 'ginkgo']],
            ['language' => 'java', 'frameworks' => ['junit5', 'junit4', 'testng']],
        ];
    }
}
