<?php

namespace App\Workflows;

/**
 * 工作流步骤接口
 *
 * 每个工作流步骤实现此接口，提供执行和补偿方法。
 * 设计兼容 Temporal：后续切换到 Temporal 时只需将步骤标记为 @Temporal\Activity。
 */
interface WorkflowStep
{
    /**
     * 执行步骤
     *
     * @param \App\Models\WorkflowInstance $instance 工作流实例
     * @param array $context 当前上下文
     * @param array $input 步骤输入参数
     * @return array 返回合并到上下文的结果
     * @throws \Throwable 执行失败时抛出，触发重试或补偿
     */
    public function execute(\App\Models\WorkflowInstance $instance, array &$context, array $input = []): array;

    /**
     * 补偿步骤（回滚）
     * 当后续步骤失败时，框架会按逆序调用已成功步骤的 compensate 方法。
     *
     * @param \App\Models\WorkflowInstance $instance 工作流实例
     * @param array $context 当前上下文
     * @param array $input 步骤的原始输入
     * @param array $output 步骤的原始输出
     */
    public function compensate(\App\Models\WorkflowInstance $instance, array &$context, array $input, array $output): void;

    /**
     * 步骤名称
     */
    public function name(): string;

    /**
     * 步骤描述（用于监控面板显示）
     */
    public function description(): string;

    /**
     * 最大重试次数（默认 3）
     */
    public function maxRetries(): int;

    /**
     * 重试延迟（秒），支持数组（逐次递增）或单值
     */
    public function retryDelay(): array|int;

    /**
     * 步骤超时（秒），0=不超时
     */
    public function timeout(): int;
}
