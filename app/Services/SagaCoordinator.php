<?php

namespace App\Services;

use App\Models\WorkflowInstance;
use App\Workflows\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * M2-137 Saga 分布式事务协调器
 *
 * 实现 Saga 模式的长时运行事务协调：
 * - 顺序执行事务步骤
 * - 某步骤失败时逆序补偿已成功步骤
 * - 支持嵌套 Saga
 * - 提供事务边界管理
 */
class SagaCoordinator
{
    /**
     * Saga 执行记录
     */
    protected array $executedSteps = [];

    /**
     * Saga 是否已开始补偿
     */
    protected bool $isCompensating = false;

    /**
     * Saga 上下文（跨步骤共享）
     */
    protected array $context = [];

    public function __construct(
        protected WorkflowEngine $engine,
    ) {}

    /**
     * 执行 Saga 事务
     *
     * @param string $name Saga 名称
     * @param array $steps [['name' => 'step_name', 'action' => callable, 'compensate' => callable], ...]
     * @param array $initialContext
     * @return array{success: bool, data: mixed, error: ?string, compensated: bool}
     */
    public function execute(string $name, array $steps, array $initialContext = []): array
    {
        $this->context = $initialContext;
        $this->executedSteps = [];
        $this->isCompensating = false;

        Log::info("Saga: starting {$name}", ['step_count' => count($steps)]);

        foreach ($steps as $index => $step) {
            $stepName = $step['name'] ?? "step_{$index}";

            try {
                Log::debug("Saga: executing step {$stepName}", ['index' => $index]);

                $result = $step['action']($this->context);

                $this->executedSteps[] = [
                    'name' => $stepName,
                    'index' => $index,
                    'compensate' => $step['compensate'] ?? null,
                    'result' => $result,
                ];

                // 更新上下文
                if (is_array($result)) {
                    $this->context = array_merge($this->context, $result);
                }

                Log::info("Saga: step {$stepName} completed");
            } catch (\Throwable $e) {
                Log::warning("Saga: step {$stepName} failed", [
                    'error' => $e->getMessage(),
                    'executed_steps' => count($this->executedSteps),
                ]);

                // 执行补偿
                $compensationResult = $this->compensate($name, $e->getMessage());

                return [
                    'success' => false,
                    'data' => null,
                    'error' => "步骤 {$stepName} 失败: " . $e->getMessage(),
                    'compensated' => $compensationResult,
                    'failed_step' => $stepName,
                    'completed_steps' => array_map(fn($s) => $s['name'], $this->executedSteps),
                ];
            }
        }

        Log::info("Saga: {$name} completed successfully");

        return [
            'success' => true,
            'data' => $this->context,
            'error' => null,
            'compensated' => false,
            'completed_steps' => array_map(fn($s) => $s['name'], $this->executedSteps),
        ];
    }

    /**
     * 逆序补偿已成功步骤
     */
    protected function compensate(string $name, string $error): bool
    {
        $this->isCompensating = true;
        $allCompensated = true;

        Log::warning("Saga: starting compensation for {$name}", [
            'reason' => $error,
            'steps_to_compensate' => count($this->executedSteps),
        ]);

        // 逆序遍历已执行步骤
        $reversed = array_reverse($this->executedSteps);

        foreach ($reversed as $step) {
            $compensateFn = $step['compensate'] ?? null;

            if (! $compensateFn) {
                Log::warning("Saga: step {$step['name']} has no compensate handler, skipping");
                continue;
            }

            try {
                Log::debug("Saga: compensating step {$step['name']}");
                $compensateFn($this->context, $step['result'] ?? []);
                Log::info("Saga: step {$step['name']} compensated");
            } catch (\Throwable $ce) {
                $allCompensated = false;
                Log::error("Saga: compensation failed for step {$step['name']}", [
                    'error' => $ce->getMessage(),
                ]);
                // 继续尝试补偿其他步骤（尽力补偿）
            }
        }

        if ($allCompensated) {
            Log::info("Saga: all steps compensated for {$name}");
        } else {
            Log::error("Saga: partial compensation failure for {$name}, manual intervention required");
        }

        return $allCompensated;
    }

    /**
     * 获取 Saga 状态
     */
    public function getStatus(): array
    {
        return [
            'is_compensating' => $this->isCompensating,
            'executed_steps' => array_map(fn($s) => [
                'name' => $s['name'],
                'has_compensate' => $s['compensate'] !== null,
            ], $this->executedSteps),
            'context_keys' => array_keys($this->context),
        ];
    }

    /**
     * 包装一个步骤为 Saga 兼容格式
     */
    public static function step(string $name, callable $action, ?callable $compensate = null): array
    {
        return [
            'name' => $name,
            'action' => $action,
            'compensate' => $compensate,
        ];
    }
}
