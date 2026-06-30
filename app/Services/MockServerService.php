<?php

namespace App\Services;

use App\Models\MockRule;
use Illuminate\Support\Facades\Log;

/**
 * M1.4-59 API Mock Server 服务
 *
 * 基于规则匹配的 API Mock，支持自定义响应、延迟模拟、错误率模拟。
 */
class MockServerService
{
    /**
     * 处理 Mock 请求
     */
    public function handle(string $method, string $path, array $params = []): array
    {
        // 查找匹配的规则
        $rule = MockRule::active()
            ->byMethod($method)
            ->byPath($path)
            ->orderBy('sort_order')
            ->first();

        if (!$rule) {
            // 尝试路径匹配（忽略末尾的 ID）
            $cleanedPath = preg_replace('#/\d+$#', '/{id}', $path);
            $rule = MockRule::active()
                ->byMethod($method)
                ->where('path', $cleanedPath)
                ->orderBy('sort_order')
                ->first();
        }

        if (!$rule) {
            return $this->defaultResponse(404, ['success' => false, 'error' => ['code' => 'MOCK_NOT_FOUND', 'message' => '未找到匹配的 Mock 规则']]);
        }

        // 模拟延迟
        $delayMs = $rule->delay_ms ?: config('mock-server.defaults.delay_ms', 0);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

        // 模拟错误率
        $errorRate = config('mock-server.defaults.error_rate', 0);
        if ($errorRate > 0 && random_int(1, 100) <= $errorRate) {
            return $this->defaultResponse(500, ['success' => false, 'error' => ['code' => 'MOCK_ERROR', 'message' => '模拟服务错误']]);
        }

        Log::debug('MockServer: matched rule', [
            'method' => $method,
            'path' => $path,
            'rule_id' => $rule->id,
            'description' => $rule->description,
        ]);

        return $this->defaultResponse($rule->status_code, $rule->response);
    }

    /**
     * 获取所有规则
     */
    public function getRules(): array
    {
        return MockRule::orderBy('sort_order')->get()->toArray();
    }

    /**
     * 创建规则
     */
    public function createRule(array $data): MockRule
    {
        return MockRule::create([
            'method' => strtoupper($data['method']),
            'path' => $data['path'],
            'status_code' => $data['status_code'] ?? 200,
            'response' => $data['response'] ?? ['success' => true, 'data' => null],
            'description' => $data['description'] ?? null,
            'delay_ms' => $data['delay_ms'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
        ]);
    }

    /**
     * 更新规则
     */
    public function updateRule(MockRule $rule, array $data): MockRule
    {
        $rule->update($data);
        return $rule->fresh();
    }

    /**
     * 删除规则
     */
    public function deleteRule(MockRule $rule): void
    {
        $rule->delete();
    }

    /**
     * 导入预建规则
     */
    public function importPrebuilt(): int
    {
        $prebuilt = config('mock-server.prebuilt_rules', []);
        $count = 0;

        foreach ($prebuilt as $ruleData) {
            $exists = MockRule::byMethod($ruleData['method'])
                ->byPath($ruleData['path'])
                ->where('description', $ruleData['description'])
                ->exists();

            if (!$exists) {
                $this->createRule($ruleData);
                $count++;
            }
        }

        return $count;
    }

    /**
     * 清空所有规则
     */
    public function clearAll(): int
    {
        return MockRule::count();
    }

    /**
     * 获取默认响应
     */
    protected function defaultResponse(int $statusCode, mixed $data): array
    {
        return ['status_code' => $statusCode, 'data' => $data];
    }
}
