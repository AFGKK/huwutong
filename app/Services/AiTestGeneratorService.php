<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * AI 测试用例自动生成服务 (M2-48)
 *
 * 基于 OpenAPI Schema 和 Eloquent Model 定义，
 * 自动生成单元测试 + 集成测试用例，覆盖率自动提升。
 *
 * 支持生成：
 * - PHPUnit Feature Test（API 端点测试）
 * - PHPUnit Unit Test（Service 逻辑测试）
 * - Playwright E2E Test 脚本
 */
class AiTestGeneratorService
{
    public function __construct(
        protected LlmService $llmService,
    ) {}

    /**
     * 为指定 API 端点自动生成 Feature Test
     */
    public function generateApiTest(string $controllerClass, string $method, array $options = []): string
    {
        $reflection = new \ReflectionMethod($controllerClass, $method);
        $routeInfo = $this->resolveRouteInfo($controllerClass, $method);
        $requestClass = $this->resolveRequestClass($reflection);
        $modelClass = $this->resolveModelClass($controllerClass);

        $prompt = $this->buildApiTestPrompt($routeInfo, $modelClass, $requestClass, $options);

        try {
            return $this->llmService->chat($prompt);
        } catch (\Exception $e) {
            Log::warning('AI 测试生成失败', [
                'controller' => $controllerClass,
                'method' => $method,
                'error' => $e->getMessage(),
            ]);
            return $this->generateFallbackTest($routeInfo, $modelClass);
        }
    }

    /**
     * 为指定 Service 类自动生成 Unit Test
     */
    public function generateServiceTest(string $serviceClass, array $options = []): string
    {
        $reflection = new \ReflectionClass($serviceClass);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $prompt = "为以下 Laravel Service 类生成完整的 PHPUnit Unit Test：\n\n"
            . "类名: {$serviceClass}\n"
            . "命名空间: {$reflection->getNamespaceName()}\n\n"
            . "公共方法:\n";

        foreach ($methods as $method) {
            if ($method->class !== $serviceClass) {
                continue;
            }
            $params = [];
            foreach ($method->getParameters() as $param) {
                $type = $param->getType();
                $typeName = $type ? ($type->getName() . ' ') : '';
                $params[] = "{$typeName}\${$param->getName()}";
            }
            $returnType = $method->getReturnType();
            $returnTypeName = $returnType ? ': ' . $returnType->getName() : '';
            $prompt .= "- {$method->getName()}(" . implode(', ', $params) . "){$returnTypeName}\n";
        }

        $prompt .= "\n测试要求：\n"
            . "1. 使用 PHPUnit 10 + Mockery\n"
            . "2. 每个公共方法至少覆盖：正常路径 + 异常路径\n"
            . "3. 使用 RefreshDatabase trait\n"
            . "4. 遵循 Arrange-Act-Assert 模式\n"
            . "5. 返回完整的 PHP 代码\n";

        if ($options['coverage'] ?? false) {
            $prompt .= "6. 额外覆盖：边界值、null 参数、空集合\n";
        }

        try {
            return $this->llmService->chat($prompt);
        } catch (\Exception $e) {
            Log::warning('AI Service 测试生成失败', ['service' => $serviceClass, 'error' => $e->getMessage()]);
            return "// AI 测试生成暂不可用，请手动编写 {$serviceClass} 的单元测试。\n";
        }
    }

    /**
     * 为指定路由批量生成 Feature Tests
     */
    public function generateBatchTests(array $routes, array $options = []): array
    {
        $results = [];
        foreach ($routes as $route) {
            $controllerAction = $route['action'] ?? '';
            if (! $controllerAction || ! str_contains($controllerAction, '@')) {
                continue;
            }

            [$controller, $method] = explode('@', $controllerAction);
            $controller = str_replace('App\\Http\\Controllers\\', '', $controller);

            try {
                $testContent = $this->generateApiTest(
                    "App\\Http\\Controllers\\{$controller}",
                    $method,
                    $options
                );
                $testName = $this->buildTestClassName($controller, $method);
                $results[$testName] = $testContent;
            } catch (\Exception $e) {
                Log::error("批量测试生成失败: {$controller}@{$method}", ['error' => $e->getMessage()]);
            }
        }
        return $results;
    }

    /**
     * 根据 OpenAPI Schema 生成 E2E Playwright 测试
     */
    public function generateE2ETest(string $openApiPath, array $endpoints = []): string
    {
        $prompt = "基于以下 OpenAPI 定义生成 Playwright E2E 测试脚本（JavaScript）：\n\n"
            . "API 路径: {$openApiPath}\n"
            . "目标端点: " . json_encode($endpoints, JSON_UNESCAPED_UNICODE) . "\n\n"
            . "测试要求：\n"
            . "1. 使用 @playwright/test\n"
            . "2. 覆盖用户旅程：登录 → 创建资源 → 验证 → 清理\n"
            . "3. 包含 API 响应断言\n"
            . "4. 包含错误场景测试\n"
            . "5. 使用 test.describe 组织测试套件\n";

        try {
            return $this->llmService->chat($prompt);
        } catch (\Exception $e) {
            Log::warning('AI E2E 测试生成失败', ['error' => $e->getMessage()]);
            return "// AI E2E 测试生成暂不可用\n";
        }
    }

    /**
     * 运行测试覆盖率分析并建议需要补测的路径
     */
    public function analyzeCoverageGaps(string $basePath = 'app/Services'): array
    {
        $serviceFiles = File::glob("{$basePath}/*.php");
        $testFiles = File::glob("tests/Unit/Services/*Test.php");

        $serviceNames = collect($serviceFiles)->map(fn($f) => pathinfo($f, PATHINFO_FILENAME));
        $testNames = collect($testFiles)->map(fn($f) => str_replace('Test', '', pathinfo($f, PATHINFO_FILENAME)));

        $missing = $serviceNames->diff($testNames)->values()->toArray();

        return [
            'total_services' => $serviceNames->count(),
            'total_tests' => $testNames->count(),
            'coverage_rate' => $serviceNames->count() > 0
                ? round(($testNames->count() / $serviceNames->count()) * 100, 1)
                : 0,
            'missing_tests' => $missing,
            'suggestions' => array_map(fn($s) => "为 {$s}Service 创建 Unit Test", $missing),
        ];
    }

    // ─── 内部辅助方法 ───

    protected function resolveRouteInfo(string $controllerClass, string $method): array
    {
        // 从路由注册信息推断 HTTP 方法和 URI
        $reflection = new \ReflectionClass($controllerClass);
        $controllerName = class_basename($controllerClass);
        $resourceName = Str::replaceLast('Controller', '', $controllerName);
        $resourceKebab = Str::kebab(Str::snake($resourceName));

        $methodRouteMap = [
            'index'   => ['GET', "/api/admin/{$resourceKebab}"],
            'store'   => ['POST', "/api/admin/{$resourceKebab}"],
            'show'    => ['GET', "/api/admin/{$resourceKebab}/{id}"],
            'update'  => ['PUT', "/api/admin/{$resourceKebab}/{id}"],
            'destroy' => ['DELETE', "/api/admin/{$resourceKebab}/{id}"],
        ];

        return $methodRouteMap[$method] ?? ['GET', "/api/admin/{$resourceKebab}/{$method}"];
    }

    protected function resolveRequestClass(\ReflectionMethod $method): ?string
    {
        $params = $method->getParameters();
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type && str_contains($type->getName(), 'Request')) {
                return $type->getName();
            }
        }
        return null;
    }

    protected function resolveModelClass(string $controllerClass): string
    {
        $controllerName = class_basename($controllerClass);
        $modelName = Str::replaceLast('Controller', '', $controllerName);
        return "App\\Models\\{$modelName}";
    }

    protected function buildApiTestPrompt(array $routeInfo, ?string $modelClass, ?string $requestClass, array $options): string
    {
        [$method, $uri] = $routeInfo;

        $prompt = "为以下 Laravel API 端点生成 PHPUnit Feature Test：\n\n"
            . "HTTP {$method} {$uri}\n"
            . "Controller: {$routeInfo['controller'] ?? 'unknown'}\n"
            . "Model: {$modelClass}\n";

        if ($requestClass) {
            $prompt .= "FormRequest: {$requestClass}\n";
        }

        $prompt .= "\n测试要求：\n"
            . "1. 使用 Laravel HTTP Tests (assertJson/assertStatus)\n"
            . "2. 使用 RefreshDatabase + 种子数据\n"
            . "3. 覆盖：正常请求（200）、验证失败（422）、未授权（401）、资源不存在（404）\n"
            . "4. 遵循 Arrange-Act-Assert 模式\n"
            . "5. 返回完整 PHP 代码，含 use 语句\n";

        if ($options['auth'] ?? true) {
            $prompt .= "6. 使用 Sanctum actingAs() 模拟认证用户\n";
        }

        return $prompt;
    }

    protected function buildTestClassName(string $controller, string $method): string
    {
        $parts = explode('\\', $controller);
        $name = end($parts);
        $methodName = ucfirst($method);
        return "{$name}{$methodName}Test";
    }

    protected function generateFallbackTest(array $routeInfo, ?string $modelClass): string
    {
        [$method, $uri] = $routeInfo;
        $testName = 'ExampleTest';

        return "<?php\n\n"
            . "namespace Tests\\Feature;\n\n"
            . "use Tests\\TestCase;\n\n"
            . "class {$testName} extends TestCase\n"
            . "{\n"
            . "    /** @test */\n"
            . "    public function it_can_access_{$method}_endpoint(): void\n"
            . "    {\n"
            . "        \$response = \$this->{$method}Json('{$uri}');\n"
            . "        \$response->assertStatus(200);\n"
            . "    }\n"
            . "}\n";
    }
}
